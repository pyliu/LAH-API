if (Vue) {
    Vue.component("case-reg-overdue", {
        components: { "countdown": VueCountdown },
        template: `<div>
            <div style="right: 2.5rem; position:absolute; top: 0.5rem;" v-if="!inSearch">
                <b-form-checkbox v-b-tooltip.hover.top="modeTooltip" inline v-model="overdueMode" switch style="margin-right: 0rem; margin-top: .15rem;" :class="['align-baseline', 'btn', 'btn-sm', is_overdue_mode ? '' : 'border-warning', 'p-1']">
                    <span>{{modeText}}</span>
                </b-form-checkbox>
                <b-button v-show="empty(reviewerId)" variant="secondary" size="sm" @click="switchMode()">
                    <b-icon v-if="listMode" icon="bar-chart-fill" font-scale="1"></b-icon>
                    <b-icon v-else icon="table" font-scale="1"></b-icon>
                    {{listMode ? "統計圖表" : "表格顯示"}}
                </b-button>
                <b-button id="reload" variant="primary" size="sm" @click="load">
                    <i class="fas fa-sync"></i>
                    刷新
                    <b-badge variant="light">
                        <countdown ref="countdown" :time="milliseconds" @end="handleCountdownEnd" @start="handleCountdownStart" :auto-start="false">
                            <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }}</template>
                        </countdown>
                        <span class="sr-only">倒數</span>
                    </b-badge>
                </b-button>
            </div>
            <lah-transition @after-leave="afterTableLeave">
                <b-table
                    ref="case_list_tbl"
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
                    :items="inSearch ? case_store.getters.list_by_id[reviewerId] : case_store.getters.list"
                    :fields="fields"
                    :busy="isBusy"
                    v-show="listMode"
                    class="text-center"
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
                        <b-button v-if="!inSearch && !reviewerId" :variant="is_overdue_mode ? 'outline-danger' : 'warning'" :size="small ? 'sm' : 'md'" @click="searchByReviewer(data.value)" :title="'查詢 '+data.value+' 的'+(is_overdue_mode ? '逾期' : '即將逾期')+'案件'">{{data.value.split(" ")[0]}}</b-button>
                        <span v-else>{{data.value.split(" ")[0]}}</span>
                    </template>
                </b-table>
            </lah-transition>
            
            <lah-transition @after-leave="afterStatsLeave">
                <div class="mt-5" v-show="statsMode">
                    <div class="mx-auto w-75">
                        <chart-component ref="statsChart"></chart-component>
                    </div>
                    <b-button-group style="margin-left: 12.5%" class="w-75 mt-2">
                        <b-button size="sm" variant="primary" @click="chartType = 'bar'"><i class="fas fa-chart-bar"></i> 長條圖</b-button>
                        <b-button size="sm" variant="secondary" @click="chartType = 'pie'"><i class="fas fa-chart-pie"></i> 圓餅圖</b-button>
                        <b-button size="sm" variant="success" @click="chartType = 'line'"><i class="fas fa-chart-line"></i> 線型圖</b-button>
                        <b-button size="sm" variant="warning" @click="chartType = 'polarArea'"><i class="fas fa-chart-area"></i> 區域圖</b-button>
                        <b-button size="sm" variant="info" @click="chartType = 'doughnut'"><i class="fab fa-edge"></i> 甜甜圈</b-button>
                        <b-button size="sm" variant="dark" @click="chartType = 'radar'"><i class="fas fa-broadcast-tower"></i> 雷達圖</b-button>
                    </b-button-group>
                </div>
            </lah-transition>
        </div>`,
        props: ['reviewerId', 'inSearch', 'compact', 'store'],
        data: function () {
            return {
                fields: [
                    '序號',
                    {key: "收件字號", sortable: true},
                    {key: "登記原因", sortable: true},
                    {key: "辦理情形", sortable: true},
                    {key: "初審人員", sortable: true},
                    {key: "作業人員", sortable: true},
                    {key: "收件時間", sortable: true},
                    {key: "限辦期限", sortable: true}
                ],
                height: 0,  // the height inside the modal
                caption: "查詢中 ... ",
                small: false,
                milliseconds: 15 * 60 * 1000,
                listMode: true,
                statsMode: false,
                overdueMode: true,
                modeText: "逾期模式",
                modeTooltip: "逾期案件查詢模式",
                chartType: "bar",
                title: "逾期"
            }
        },
        computed: {
            total_case() {
                return this.case_store.getters.list_count;
            },
            total_people() {
                return this.case_store.getters.list_by_id_count;
            },
            case_list() {
                return this.case_store.getters.list;
            },
            case_list_by_id() {
                return this.case_store.getters.list_by_id;
            },
            is_overdue_mode() {
                return this.case_store.getters.is_overdue_mode;
            },
            case_store() {
                // in-search mode component will use this.store, root one will use this.$store
                return this.store || this.$store;
            }
        },
        watch: {
            chartType: function (val) {
                this.$refs.statsChart.type = val;
            },
            overdueMode: function(isChecked) {
                // also update store's flag
                this.case_store.commit("is_overdue_mode", isChecked);
                this.title = isChecked ? "逾期" : "即將逾期";
                this.modeText = isChecked ? "逾期模式" : "即將逾期"
                this.modeTooltip = isChecked ? "逾期案件查詢模式" : "即將逾期模式(4小時內)";
                // calling api must be the last one to do
                this.load();
            },
            isBusy: function(flag) {
                if (flag) {
                    addLDAnimation("#reload .fas.fa-sync", "ld-cycle");
                    if (this.statsMode) this.busyOn("body", "lg");
                } else {
                    clearLDAnimation("#reload .fas.fa-sync");
                    if (this.statsMode) this.busyOff("body");
                }
            }
        },
        methods: {
            empty: function (variable) {
                if (variable === undefined || $.trim(variable) == "") {
                    return true;
                }
                
                if (typeof variable == "object" && variable.length == 0) {
                    return true;
                }
                return false;
            },
            switchMode: function() {
                if (this.listMode) {
                    // use afterTableLeave to control this.statsMode
                    this.listMode = false;
                }
                if (this.statsMode) {
                    // use afterStatsLeave to control this.listMode
                    this.statsMode = false;
                }
            },
            afterTableLeave: function () {
                this.statsMode = true;
            },
            afterStatsLeave: function () {
                this.listMode = true;
            },
            setChartData: function() {
                this.$refs.statsChart.items = [];
                let total = 0;
                for (let id in this.case_list_by_id) {
                    let item = [this.case_list_by_id[id][0]["初審人員"], this.case_list_by_id[id].length];
                    this.$refs.statsChart.items.push(item);
                    total += this.case_list_by_id[id].length;
                }
                this.$refs.statsChart.label = `${this.is_overdue_mode ? "" : "即將"}逾期案件統計表 (${this.total_people}人，共${this.total_case}件)`;
            },
            resetCountdown: function () {
                this.$refs.countdown.totalMilliseconds = this.milliseconds;
            },
            startCountdown: function () {
                this.$refs.countdown.start();
            },
            endCountdown: function () {
                this.$refs.countdown.end();
            },
            makeCaseIDClickable: function () {
                addAnimatedCSS("table tr td:nth-child(2)", { name: "flash" })
                .off("click")
                .on("click", window.vueApp.fetchRegCase)
                .addClass("reg_case_id");
            },
            searchByReviewer: function(reviewer_data) {
                // reviewer_data, e.g. "曾奕融 HB1184"
                showModal({
                    title: `查詢 ${reviewer_data} 登記案件(${this.title})`,
                    message: this.$createElement('case-reg-overdue', {
                        props: {
                            reviewerId: reviewer_data.split(" ")[1],
                            inSearch: true,
                            store: this.$store
                        }
                    }),
                    size: "xl"
                });
            },
            handleCountdownStart: function (e) {},
            handleCountdownEnd: function(e) { this.load(e); },
            load: function(e) {
                // busy ...
                this.isBusy = true;
                this.title = this.is_overdue_mode ? "逾期" : "即將逾期";
                if (this.inSearch) {
                    // in-search, by clicked the first reviewer button
                    let case_count = this.case_list_by_id[this.reviewerId].length || 0;
                    this.caption = `${case_count} 件`;
                    addNotification({ title: `查詢登記案件(${this.title})`, message: `查詢到 ${case_count} 件案件` });
                    // release busy ...
                    this.isBusy = false;
                    Vue.nexTick ? Vue.nexTick(this.makeCaseIDClickable) : setTimeout(this.makeCaseIDClickable, 800);
                } else {
                    let params = {
                        type: this.is_overdue_mode ? "overdue_reg_cases" : "almost_overdue_reg_cases",
                        reviewer_id: this.reviewerId
                    }
                    this.$http.post(CONFIG.JSON_API_EP, params).then(res => {
                        let jsonObj = res.data;
                        console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL || jsonObj.status == XHR_STATUS_CODE.SUCCESS_WITH_NO_RECORD, `查詢登記案件(${this.title})回傳狀態碼有問題【${jsonObj.status}】`);

                        // set data to store
                        // NOTE: the payload must be valid or it will not update UI correctly
                        this.case_store.commit("list", jsonObj.items);
                        this.case_store.commit("list_by_id", jsonObj.items_by_id);

                        this.caption = `${jsonObj.data_count} 件，更新時間: ${new Date()}`;

                        let now = new Date();
                        if (now.getHours() >= 7 && now.getHours() < 17) {
                            // auto start countdown to prepare next reload
                            this.resetCountdown();
                            this.startCountdown();
                            // add effect to catch attention
                            addAnimatedCSS("#reload, caption", {name: "flash"});
                        } else {
                            console.warn("非上班時間，停止自動更新。");
                            addNotification({
                                title: "自動更新停止通知",
                                message: "非上班時間，停止自動更新。",
                                type: "warning"
                            });
                        }

                        // prepare the chart data for rendering
                        this.setChartData();
                        // make .reg_case_id clickable
                        Vue.nextTick(this.makeCaseIDClickable);
                        // release busy ...
                        this.isBusy = false;
                        // send notification
                        addNotification({
                            title: `查詢登記案件(${this.title})`,
                            message: `查詢到 ${jsonObj.data_count} 件案件`,
                            type: this.is_overdue_mode ? "danger" : "warning"
                        });
                    }).catch(ex => {
                        console.error("case-reg-overdue::created parsing failed", ex);
                        showAlert({message: "case-reg-overdue::created XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                    });
                }
            }
        },
        mounted() {
            if (this.inSearch === true) {
                // in modal dialog
                this.height = window.innerHeight - 185 + "px";
                this.small = true;
            } else {
                this.height = window.innerHeight - 145 + "px";
            }
            this.load();
        }
    });
} else {
    console.error("vue.js not ready ... case-reg-overdue component can not be loaded.");
}
