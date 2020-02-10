if (Vue) {
    Vue.component("case-reg-overdue", {
        template: `<div>
            <div style="right: 2rem; position:absolute; top: 0.5rem;" v-if="!inSearch">
                <b-button variant="secondary" size="sm" @click="list_mode = !list_mode">{{list_mode ? "統計圖表" : "回列表模式"}}</b-button>
                <b-button variant="primary" size="sm" @click="load">
                    刷新
                    <b-badge variant="light">
                        <countdown ref="countdown" :time="milliseconds" :auto-start="false">
                            <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }}</template>
                        </countdown>
                        <span class="sr-only">倒數</span>
                    </b-badge>
                </b-button>
            </div>
            <b-table
                striped
                hover
                responsive
                bordered
                head-variant="dark"
                caption-top
                no-border-collapse
                :small="small"
                :caption="caption"
                :sticky-header="height"
                :items="items"
                :fields="fields"
                :busy="busy"
                v-show="list_mode"
            >
                <template v-slot:table-busy>
                    <div class="text-center text-danger my-5">
                        <b-spinner class="align-middle"></b-spinner>
                        <strong>查詢中 ...</strong>
                    </div>
                </template>
                <template v-slot:cell(序號)="data">
                    {{data.index + 1}}
                </template>
                <template v-slot:cell(初審人員)="data">
                    <b-button v-if="!inSearch && !reviewerId" variant="outline-danger" :size="small ? 'sm' : 'md'" @click="searchByReviewer(data.value)" :title="'查詢 '+data.value+' 的逾期案件'">{{data.value.split(" ")[0]}}</b-button>
                    <span v-else>{{data.value.split(" ")[0]}}</span>
                </template>
            </b-table>
            <div class="mt-3" v-show="!list_mode">
                <div class="mx-auto w-75">
                    <canvas id="overdue-reg-cases-chart">圖形初始化失敗</canvas>
                </div>
                <b-button-group style="margin-left: 12.5%" class="w-75 mt-2">
                    <b-button size="sm" variant="primary" @click="chartType = 'bar'">長條圖</b-button>
                    <b-button size="sm" variant="secondary" @click="chartType = 'pie'">圓餅圖</b-button>
                    <b-button size="sm" variant="info" @click="chartType = 'doughnut'">甜甜圈圖</b-button>
                    <b-button size="sm" variant="warning" @click="chartType = 'radar'">雷達圖</b-button>
                    <b-button size="sm" variant="success" @click="chartType = 'line'">線型圖</b-button>
                </b-button-group>
            </div>
        </div>`,
        props: ['reviewerId', 'inSearch', 'compact', 'itemsIn'],
        components: {
            "countdown": VueCountdown
        },
        data: function () {
            return {
                items: {},
                items_by_id: {},
                fields: [
                    '序號',
                    {key: "收件字號", sortable: true},
                    {key: "登記原因", sortable: true},
                    {key: "辦理情形", sortable: true},
                    {key: "初審人員", sortable: true},
                    {key: "作業人員", sortable: true},
                    {key: "限辦期限", sortable: true},
                    {key: "收件時間", sortable: true}
                ],
                height: true,
                caption: "查詢中 ... ",
                busy: true,
                small: false,
                timer_handle: null,
                milliseconds: 15 * 60 * 1000,
                list_mode: true,
                chartType: "bar",
                chartInst: null,
                chartData: null
            }
        },
        watch: {
            chartType: function (val) {
                this.buildChart();
            },
            chartData: function(newObj) {
                this.buildChart();
            }
        },
        methods: {
            resetCountdown: function () {
                this.$refs.countdown.totalMilliseconds = this.milliseconds;
            },
            startCountdown: function () {
                this.$refs.countdown.start();
            },
            endCountdown: function () {
                this.$refs.countdown.totalMilliseconds = 0;
            },
            load: function() {
                clearTimeout(this.timer_handle);
                if (!this.inSearch) {
                    this.endCountdown();
                }
                this.busy = true;
                if (this.itemsIn) {
                    this.busy = false;
                    this.items = this.itemsIn;
                    this.caption = `${this.itemsIn.length} 件`;
                    this.items_by_id = this.items_by_id;
                    setTimeout(() => {
                        $("table tr td:nth-child(2)").off("click").on("click", window.utilApp.fetchRegCase).addClass("reg_case_id");
                    }, 1000);
                    addNotification({ title: "查詢登記逾期案件", message: `查詢到 ${this.itemsIn.length} 件案件` });
                } else {
                    let form_body = new FormData();
                    form_body.append("type", "overdue_reg_cases");
                    if (!isEmpty(this.reviewerId)) {
                        form_body.append("reviewer_id", this.reviewerId);
                    }
                    asyncFetch("query_json_api.php", {
                        method: 'POST',
                        body: form_body
                    }).then(jsonObj => {
                        console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "查詢登記逾期案件回傳狀態碼有問題【" + jsonObj.status + "】");
                        
                        this.busy = false;
                        this.items = jsonObj.items;
                        this.items_by_id = jsonObj.items_by_id;
                        this.caption = `${jsonObj.data_count} 件，更新時間: ${new Date()}`;

                        setTimeout(() => {
                            $("table tr td:nth-child(2)").off("click").on("click", window.utilApp.fetchRegCase).addClass("reg_case_id");
                            addNotification({ title: "查詢登記逾期案件", message: `查詢到 ${jsonObj.data_count} 件案件`, type: "success" });
                        }, 1000);

                        if (!this.inSearch) {

                            this.resetCountdown();
                            this.startCountdown();

                            let now = new Date();
                            if (now.getHours() >= 7 && now.getHours() < 17) {
                                // auto next reload
                                this.timer_handle = setTimeout(this.load, this.milliseconds);
                            } else {
                                console.warn("非上班時間，停止自動更新。");
                                addNotification({
                                    title: "自動更新停止通知",
                                    message: "非上班時間，停止自動更新。",
                                    type: "warning"
                                });
                                this.endCountdown();
                            }
                            // prepare the chart data for rendering
                            this.setChartData();
                        }
                    }).catch(ex => {
                        console.error("case-reg-overdue::created parsing failed", ex);
                        showAlert({message: "case-reg-overdue::created XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                    });
                }
            },
            searchByReviewer: function(reviewer_data) {
                // e.g. "賴奕文 HB1159"
                let id = trim(reviewer_data);
                showModal({
                    title: `查詢 ${reviewer_data} 逾期案件`,
                    message: this.$createElement('case-reg-overdue', {
                        props: {
                            reviewerId: id,
                            inSearch: true,
                            itemsIn: this.items_by_id[id]
                        }
                    }),
                    size: "xl"
                });
            },
            resetChartData: function() {
                this.chartData = {
                    labels:[],
                    legend: {
                        display: true,
                        labels: { boxWidth: 20 }
                    },
                    datasets:[{
                        label: "數量分布統計",
                        backgroundColor:[],
                        data: [],
                        borderColor:[],
                        order: 1,
                        opacity: 0.8,
                        snapGaps: true
                    }]
                };
            },
            setChartData: function() {
                this.resetChartData();
                let opacity = this.chartData.datasets[0].opacity;
                this.chartData.datasets[0].borderColor = `rgb(22, 22, 22)`;
                for (let id in this.items_by_id) {
                    this.chartData.labels.push(this.items_by_id[id][0]["初審人員"]);
                    this.chartData.datasets[0].backgroundColor.push(`rgb(${this.rand(255)}, ${this.rand(255)}, ${this.rand(255)}, ${opacity})`);
                    this.chartData.datasets[0].data.push(this.items_by_id[id].length);
                }
            },
            buildChart: function () {
                if (this.chartInst) {
                    // reset the chart
                    this.chartInst.destroy();
                    this.chartInst = null;
                }
                // use chart.js directly
                let ctx = $('#overdue-reg-cases-chart');
                this.chartInst = new Chart(ctx, {
                    type: this.chartType,
                    data: this.chartData,
                    options: {}
                });
            },
            rand: (range) => Math.floor(Math.random() * Math.floor(range || 100))
        },
        mounted() {
            this.load();
            if (this.inSearch === true) {
                // in modal dialog
                this.height = $(document).height() - 185 + "px";
                this.small = true;
            } else {
                this.height = $(document).height() - 145 + "px";
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-reg-overdue component can not be loaded.");
}
