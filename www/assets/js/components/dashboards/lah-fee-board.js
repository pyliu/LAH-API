if (Vue) {
    Vue.component('lah-fee-board', {
        template: `<b-container id="expaa-list-container" fluid :class="['small', 'text-center']">
            <b-row no-gutters>
                <b-col class="mx-1" v-b-tooltip.top.d750="money_all+'元'">
                    <b-button variant="info" block @click="open('全部規費列表', raw_data)" size="sm">
                        全部 <b-badge variant="light">{{count_all}} <span class="sr-only">全部收費數量</span></b-badge>
                    </b-button>
                </b-col>
                <b-col class="mx-1" v-b-tooltip.top.d750="money_cash+'元'">
                    <b-button variant="success" block @click="open('現金規費列表', cash)" size="sm">
                        現金 <b-badge variant="light">{{count_cash}} <span class="sr-only">現金收費數量</span></b-badge>
                    </b-button>
                </b-col>
                <b-col class="mx-1" v-b-tooltip.top.d750="money_ezcard+'元'">
                    <b-button variant="primary" block @click="open('悠遊卡規費列表', ezcard)" size="sm">
                        悠遊卡 <b-badge variant="light">{{count_ezcard}} <span class="sr-only">悠遊卡收費數量</span></b-badge>
                    </b-button>
                </b-col>
            </b-row>
            <b-row :class="['mt-1', 'mb-2']" no-gutters>
                <b-col class="mx-1" v-b-tooltip.bottom.d750="money_mobile+'元'">
                    <b-button variant="danger" block @click="open('行動支付規費列表', mobile)" size="sm">
                        行動支付 <b-badge variant="light">{{count_mobile}} <span class="sr-only">行動支付收費數量</span></b-badge>
                    </b-button>
                </b-col>
                <b-col class="mx-1" v-b-tooltip.bottom.d750="money_credit+'元'">
                    <b-button variant="warning" block @click="open('信用卡規費列表', credit)" size="sm">
                        信用卡 <b-badge variant="light">{{count_credit}} <span class="sr-only">信用卡收費數量</span></b-badge>
                    </b-button>
                </b-col>
                <b-col class="mx-1" v-b-tooltip.bottom.d750="money_other+'元'">
                    <b-button variant="secondary" block @click="open('其他規費列表', other)" size="sm">
                        其他 <b-badge variant="light">{{count_other}} <span class="sr-only">其他收費數量</span></b-badge>
                    </b-button>
                </b-col>
            </b-row>
            <b-row no-gutters>
                <b-col><canvas id="feeBarChart" class="w-100"></canvas></b-col>
            </b-row>
        </b-container>`,
        data: () => ({
            date_obj: null,
            query_date: "1090311",
            raw_data: null,
            cash: [],
            ezcard: [],
            mobile: [],
            credit: [],
            other: [],
            chartInst: null,
            chartData: {
                labels:[],
                legend: {
                    display: true,
                    labels: { boxWidth: 20 }
                },
                datasets:[{
                    label: "數量統計",
                    backgroundColor:[],
                    data: [],
                    borderColor:[],
                    fill: true,
                    type: "bar",
                    order: 1,
                    opacity: 0.8,
                    snapGaps: true
                }, {
                    label: "金額統計",
                    backgroundColor:[],
                    data: [],
                    borderColor:[],
                    fill: true,
                    type: "line",
                    order: 2,
                    opacity: 0.7,
                    snapGaps: true
                }]
            }
        }),
        computed: {
            count_cash: function() { return this.cash.length; },
            count_ezcard: function() { return this.ezcard.length; },
            count_mobile: function() { return this.mobile.length; },
            count_other: function() { return this.other.length; },
            count_credit: function() { return this.credit.length; },
            count_all: function() { return this.empty(this.raw_data) ? 0 : this.raw_data.length; },
            money_cash: function() { return this.sum(this.cash); },
            money_ezcard: function() { return this.sum(this.ezcard); },
            money_mobile: function() { return this.sum(this.mobile); },
            money_other: function() { return this.sum(this.other); },
            money_credit: function() { return this.sum(this.credit); },
            money_all: function() { return this.sum(this.raw_data); },
        },
        methods: {
            open: function(title, data) {
                if (data.length == 0) {
                    return false;
                }
                let that = this;
                this.msgbox({
                    title: title,
                    message: this.$createElement("expaa-list-mgt", {
                        props: { items: data || [] },
                        on: {
                            "number_clicked": function(number) {
                                that.$emit("number_clicked", number);
                            }
                        }
                    }),
                    size: data.length < 51 ? "md" : data.length < 145 ? "lg" : "xl",
                    backdrop_close: true
                });
            },
            sum: function(collection) {
                if (this.empty(this.raw_data)) return 0;
                let that = this;
                // To use map function to make the result array of AA28 ($$) list (exclude the obsolete one, AA02 is the obsolete date) then uses reduce function to accumulate the numbers and return.
                return collection.map(element => that.empty(element["AA02"]) ? element["AA28"] : 0).reduce((acc, curr) => acc + parseInt(curr), 0);
            },
            buildChart: function() {
                // prepare chart data
                this.chartData.labels = ["現金", "悠遊卡", "信用卡", "行動支付", "其他"];
                let bar_opacity = this.chartData.datasets[0].opacity;
                this.chartData.datasets[0].backgroundColor = [`rgb(92, 184, 92, ${bar_opacity})`, `rgb(2, 117, 216, ${bar_opacity})`, `rgb(240, 173, 78, ${bar_opacity})`, `rgb(217, 83, 79, ${bar_opacity})`, `rgb(108, 117, 126, ${bar_opacity})`];
                let line_opacity = this.chartData.datasets[1].opacity;
                this.chartData.datasets[1].backgroundColor = [`rgb(92, 184, 92, ${line_opacity})`, `rgb(2, 117, 216, ${line_opacity})`, `rgb(240, 173, 78, ${line_opacity})`, `rgb(217, 83, 79, ${line_opacity})`, `rgb(108, 117, 126, ${line_opacity})`];
                this.chartData.datasets[0].data = [
                    this.count_cash,
                    this.count_ezcard,
                    this.count_credit,
                    this.count_mobile,
                    this.count_other
                ];
                this.chartData.datasets[1].data = [
                    this.money_cash,
                    this.money_ezcard,
                    this.money_credit,
                    this.money_mobile,
                    this.money_other
                ];
                this.chartData.datasets[0].borderColor = `rgb(2, 117, 216)`;
                this.chartData.datasets[1].borderColor = `rgb(2, 117, 216, ${line_opacity})`;
                // use chart.js directly
                let ctx = $('#feeBarChart');
                this.chartInst = new Chart(ctx, {
                    type: 'bar',
                    data: this.chartData,
                    options: {
                        legend: { display: true, labels: { fontColor: "black" } }
                    }
                });
                this.timeout(() => { this.chartInst.update() }, 400);
            }
        },
        components: {
            "expaa-list-mgt": {
                template: `<div>
                    <b-button
                        @click="open(item['AA01'], item['AA04'])"
                        :variant="item['AA09'] == 1 ? 'outline-primary' : item['AA08'] == 1 ? 'danger' : 'dark'"
                        pill
                        size="sm" 
                        :class="['float-left', 'mr-2', 'mb-2']"
                        v-for="(item, idx) in items"
                        :id="'fee_btn_'+idx"
                    >
                        {{item["AA04"]}}
                        <b-popover :target="'fee_btn_'+idx" triggers="hover focus" delay="750">
                            <template v-slot:title>序號: {{item["AA05"]}} 金額: {{item['AA28']}}元</template>
                            <fee-detail-print-mgt :value="item['AA09']" :date="item['AA01']" :pc_number="item['AA04']" :no-confirm=true></fee-detail-print-mgt>
                            <fee-detail-payment-mgt :value="item['AA100']" :date="item['AA01']" :pc_number="item['AA04']" :no-confirm=true></fee-detail-payment-mgt>
                        </b-popover>
                    </b-button>
                </div>`,
                props: ["items"],
                methods: {
                    open: function(date, pc_number) {
                        let VNode = this.$createElement("fee-detail-mgt", {
                            props: { date: date, pc_number: pc_number}
                        });
                        this.msgbox({
                            message: VNode,
                            title: "規費資料詳情",
                            backdrop_close: true,
                            size: "lg"
                        });
                        this.$emit("number_clicked", pc_number);
                    }
                }
            }
        },
        created: function () {
            this.isBusy = true;
            this.date_obj = new Date();
            this.query_date = (this.date_obj.getFullYear() - 1911) + ("0" + (this.date_obj.getMonth()+1)).slice(-2) + ("0" + this.date_obj.getDate()).slice(-2);
            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "expaa",
                qday: this.query_date,
                list_mode: true
            }).then(res => {
                if (res.data.data_count == 0) {
                    this.notify({
                        title: "查詢規費統計",
                        message: `${this.query_date} 查無資料`,
                        type: "warning"
                    });
                    return;
                }
                this.raw_data = res.data.raw;
                this.cash = this.raw_data.filter(this_record => this_record["AA100"] == "01");
                this.ezcard = this.raw_data.filter(this_record => this_record["AA100"] == "06");
                this.mobile = this.raw_data.filter(this_record => this_record["AA100"] == "09");
                this.credit = this.raw_data.filter(this_record => this_record["AA100"] == "08");
                this.other = this.raw_data.filter(this_record => {
                    return this_record["AA100"] != "06" && this_record["AA100"] != "01" && this_record["AA100"] != "08" && this_record["AA100"] != "09";
                });
                this.buildChart();
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        },
        mounted: function() {}
    });
} else {
    console.error("vue.js not ready ... lah-fee-board component can not be loaded.");
}
