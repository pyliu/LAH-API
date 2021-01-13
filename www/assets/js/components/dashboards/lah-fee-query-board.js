if (Vue) {
    Vue.component("lah-fee-query-board", {
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="wallet">規費資料查詢</lah-fa-icon></h6>
                    <lah-button icon="question" no-border @click="popup" variant="outline-success" size="sm"></lah-button>
                </div>
            </template>
            <b-form-row class="mb-1">
                <b-input-group size="sm">
                    <b-input-group-prepend is-text>&emsp;日期&emsp;</b-input-group-prepend>
                    <b-form-datepicker
                        value-as-date
                        v-model="date_obj"
                        placeholder="請選擇日期"
                        size="sm"
                        :date-disabled-fn="dateDisabled"
                        :max="new Date()"
                    ></b-form-datepicker>
                    <b-button class="ml-1" @click="queryByDate" variant="outline-primary" size="sm" title="依據日期"><i class="fas fa-search"></i> 查詢</b-button>
                </b-input-group>
            </b-form-row>
            <b-form-row class="mb-1">
                <b-input-group size="sm">
                    <b-input-group-prepend is-text>電腦給號</b-input-group-prepend>
                    <b-form-input
                        ref="number"
                        v-model="number"
                        type="number"
                        placeholder="0005789"
                        :state="isNumberValid"
                        size="sm"
                        max=9999999
                        min=1
                        trim
                        number
                        @keyup.enter="queryByNumber"
                    >
                    </b-form-input>
                    <b-button class="mx-1" @click="queryByNumber" variant="outline-primary" size="sm" title="依據電腦給號"><i class="fas fa-search"></i> 查詢</b-button>
                    <b-button @click="obsolete" variant="warning" size="sm" class="h-100" title="無電腦給號規費聯單作廢">
                        <span class="fa-stack" style="font-size: 0.5rem">
                            <i class="fas fa-file-alt fa-stack-1x"></i>
                            <i class="fas fa-ban fa-stack-2x text-danger"></i>
                        </span>
                        作廢
                    </b-button>
                </b-input-group>
            </b-form-row>
        </b-card>`,
        data: () => ({
            date_obj: null,    // v-model as a date object
            query_date: "",
            number: ""
        }),
        watch: {
            date_obj: function(nVal, oVal) {
                this.query_date = `${nVal.getFullYear() - 1911}${("0" + (nVal.getMonth()+1)).slice(-2)}${("0" + nVal.getDate()).slice(-2)}`;
            },
            number: function(nVal, oVal) {
                let intVal = parseInt(this.number);
                if (intVal > 9999999)
                    this.number = 9999999;
                else if (Number.isNaN(intVal) || intVal < 1)
                    this.number = '';
            }
        },
        computed: {
            isNumberValid: function() {
                let intVal = parseInt(this.number);
                if (intVal < 9999999 && intVal > 0) {
                    return true;
                }
                return false;
            }
        },
        methods: {
            dateDisabled(ymd, date) {
                const weekday = date.getDay();
                // Disable weekends (Sunday = `0`, Saturday = `6`)
                // Return `true` if the date should be disabled
                return weekday === 0// || weekday === 6;
            },
            queryByDate: function(e) {
                this.isBusy = true;
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
                    let VNode = this.$createElement("expaa-category-dashboard", {
                        props: {
                            raw_data: res.data.raw
                        },
                        on: {
                            "number_clicked": number => {
                               this.number = number;
                            }
                        }
                    });
                    this.msgbox({
                        message: VNode,
                        title: `${this.query_date} 規費統計`
                    });
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            queryByNumber: function(e) {
                if (this.number > 0) {
                    let VNode = this.$createElement("fee-detail-mgt", {
                        props: { date: this.query_date, pc_number: this.number.toString().padStart(7, "0")}
                    });
                    this.msgbox({
                        message: VNode,
                        title: "規費資料詳情",
                        size: "lg"
                    });
                } else {
                    this.notify({ title: "查詢規費收據", subtitle: "依據電腦給號", message: "請輸入正確的電腦給號碼！", type: "warning"});
                }
            },
            popup: function(e) {
                this.msgbox({
                    title: "規費資料 小幫手提示",
                    body: `AA09 - 列印註記【1：已印，0：未印】<br />
                    AA100 - 付款方式<br />
                    <img src="assets/img/EXPAA_AA100_Update.jpg" class="img-responsive img-thumbnail my-1" /><br />
                    AA106 - 悠遊卡繳費扣款結果<br />
                    AA107 - 悠遊卡交易流水號<br />
                    <img src="assets/img/easycard_screenshot.jpg" class="img-responsive img-thumbnail my-1" />
                    AA28、AA39 - 規費資料集(EXPAA)中記載金額的兩個欄位<br />
                    AC29、AC30 - 規費項目資料集(EXPAC)中記載收費項目之金額<br />
                    <img src="assets/howto/EXPAA_EXPAC_AMOUNT_MOD.jpg" class="img-responsive img-thumbnail my-1" />`,
                    size: "lg"
                });
            },
            obsolete: function(e) {
                // query first then do the creation
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "get_dummy_ob_fees"
                }).then(res => {
                    // use the fee-obsolete-mgt sub-component to do the addition
                    let VNode = this.$createElement("fee-obsolete-mgt", {
                        props: {
                            raw_data: res.data.raw
                        }
                    });
                    this.msgbox({
                        title: "無電腦給號規費聯單作廢",
                        message: VNode,
                        size: "md",
                        callback: () => addUserInfoEvent()
                    });
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        },
        created: function() {
            this.date_obj = new Date();
            this.query_date = (this.date_obj.getFullYear() - 1911) + ("0" + (this.date_obj.getMonth()+1)).slice(-2) + ("0" + this.date_obj.getDate()).slice(-2);
            if (this.number > 9999999) this.number = 9999999;
            else if (this.number < 1) this.number = '';
        },
        mounted() {
            // restore cached data back
            this.timeout(() => this.number = this.$refs.number.$el.value, 200);
        },
        components: {
            "expaa-category-dashboard": {
                template: `<b-container id="expaa-list-container" fluid :class="['small', 'text-center']">
                    <b-row no-gutters>
                        <b-col class="mx-1" v-b-tooltip.top.d750="money_all+'元'">
                            <b-button variant="info" block @click="open('全部規費列表', raw_data)">
                                全部 <b-badge variant="light">{{count_all}} <span class="sr-only">全部收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col class="mx-1" v-b-tooltip.top.d750="money_cash+'元'">
                            <b-button variant="success" block @click="open('現金規費列表', cash)">
                                現金 <b-badge variant="light">{{count_cash}} <span class="sr-only">現金收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col class="mx-1" v-b-tooltip.top.d750="money_ezcard+'元'">
                            <b-button variant="primary" block @click="open('悠遊卡規費列表', ezcard)">
                                悠遊卡 <b-badge variant="light">{{count_ezcard}} <span class="sr-only">悠遊卡收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                    </b-row>
                    <b-row :class="['mt-1', 'mb-2']" no-gutters>
                        <b-col class="mx-1" v-b-tooltip.bottom.d750="money_mobile+'元'">
                            <b-button variant="danger" block @click="open('行動支付規費列表', mobile)">
                                行動支付 <b-badge variant="light">{{count_mobile}} <span class="sr-only">行動支付收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col class="mx-1" v-b-tooltip.bottom.d750="money_credit+'元'">
                            <b-button variant="warning" block @click="open('信用卡規費列表', credit)">
                                信用卡 <b-badge variant="light">{{count_credit}} <span class="sr-only">信用卡收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col class="mx-1" v-b-tooltip.bottom.d750="money_other+'元'">
                            <b-button variant="secondary" block @click="open('其他規費列表', other)">
                                其他 <b-badge variant="light">{{count_other}} <span class="sr-only">其他收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                    </b-row>
                    <b-row no-gutters>
                        <b-col><canvas id="feeBarChart" class="w-100"></canvas></b-col>
                    </b-row>
                </b-container>`,
                props: ["raw_data"],
                data: () => ({
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
                    count_all: function() { return this.raw_data.length; },
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
                        let that = this;
                        // To use map function to make the result array of AA28 ($$) list (exclude the obsolete one, AA02 is the obsolete date) then uses reduce function to accumulate the numbers and return.
                        return collection.map(element => that.empty(element["AA02"]) ? element["AA28"] : 0).reduce((acc, curr) => acc + parseInt(curr), 0);
                    }
                },
                components: {
                    "expaa-list-mgt": {
                        template: `<div>
                            <b-button
                                v-for="(item, idx) in items"
                                @click="open(item['AA01'], item['AA04'])"
                                :variant="variant(item)"
                                pill
                                size="sm" 
                                :class="['float-left', 'mr-2', 'mb-2']"
                                :id="'fee_btn_'+idx"
                            >
                                {{item["AA04"]}}
                                <b-popover :target="'fee_btn_'+idx" triggers="hover focus" delay="750">
                                    <template v-slot:title>序號: {{item["AA05"]}} 金額: {{item['AA28']}}元</template>
                                    <fee-detail-print-mgt :value="item['AA09']" :date="item['AA01']" :pc_number="item['AA04']" :no-confirm=true></fee-detail-print-mgt>
                                    <fee-detail-payment-mgt :value="item['AA100']" :date="item['AA01']" :pc_number="item['AA04']" :no-confirm=true></fee-detail-payment-mgt>
                                    <fee-detail-obselete-mgt :value="item['AA08']" :date="item['AA01']" :pc_number="item['AA04']" :no-confirm=true></fee-detail-obselete-mgt>
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
                            },
                            variant: function(item) {
                                if (item['AA08'] == 0) return 'secondary';
                                return item['AA09'] == 1 ? 'outline-primary' : 'danger';
                            }
                        }
                    }
                },
                created: function () {
                    /* AA100 mapping
                        "01","現金"
                        "02","支票"
                        "03","匯票"
                        "04","iBon"
                        "05","ATM"
                        "06","悠遊卡"
                        "07","其他匯款"
                        "08","信用卡"
                        "09","行動支付"
                    */
                    this.cash = this.raw_data.filter(this_record => this_record["AA100"] == "01");
                    this.ezcard = this.raw_data.filter(this_record => this_record["AA100"] == "06");
                    this.mobile = this.raw_data.filter(this_record => this_record["AA100"] == "09");
                    this.credit = this.raw_data.filter(this_record => this_record["AA100"] == "08");
                    this.other = this.raw_data.filter(this_record => {
                        return this_record["AA100"] != "06" && this_record["AA100"] != "01" && this_record["AA100"] != "08" && this_record["AA100"] != "09";
                    });
                },
                mounted: function() {
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
            "fee-obsolete-mgt": {
                template: `<div class="small">
                    下一筆假資料：<br />
                    ※ 電腦給號：{{next_pc_number}} <br />
                    <hr/>
                    <b-form-row class="mb-1">
                        <b-col cols="5">
                            <b-input-group size="sm" title="民國年月日">
                                <b-input-group-prepend is-text>結帳日期</b-input-group-prepend>
                                <b-form-input
                                    id="dummy_obsolete_date"
                                    v-model="today"
                                    placeholder="1090225"
                                    size="sm"
                                    trim
                                    :state="isDateValid"
                                >
                                </b-form-input>
                            </b-input-group>
                        </b-col>
                        <b-col>
                            <b-input-group size="sm">
                                <b-input-group-prepend is-text>作廢原因</b-input-group-prepend>
                                <b-form-input
                                    v-model="reason"
                                    id="dummy_obsolete_reason"
                                    placeholder="卡紙"
                                    :state="isReasonValid"
                                    size="sm"
                                    trim
                                >
                                </b-form-input>
                            </b-input-group>
                        </b-col>
                    </b-form-row>
                    <b-form-row>
                        <b-col cols="5">
                            <b-input-group size="sm" title="作業人員">
                                <b-input-group-prepend is-text>{{operator_name || '作業人員'}}</b-input-group-prepend>
                                <b-form-input
                                    v-model="operator"
                                    id="dummy_operator"
                                    placeholder="HBXXXX"
                                    size="sm"
                                    trim
                                    :state="isOperatorValid"
                                >
                                </b-form-input>
                            </b-input-group>
                        </b-col>
                        <b-col>
                            <b-input-group size="sm" title="AB開頭編號共10碼">
                                <b-input-group-prepend is-text>收據號碼</b-input-group-prepend>
                                <b-form-input
                                    v-model="AB_number"
                                    id="dummy_fee_number"
                                    placeholder="ABXXXXXXXX"
                                    :state="isNumberValid"
                                    size="sm"
                                    trim
                                >
                                </b-form-input>
                                &ensp;
                                <b-button @click="add" variant="outline-primary" :disabled="isDisabled" size="sm">新增</b-button>
                            </b-input-group>
                        </b-col>
                    </b-form-row>
                    <hr/>
                    <p>目前系統中({{year}}年度)的假資料有 {{count}} 筆：</p>
                    <table class="table text-center">
                        <tr>
                            <th>日期</th>
                            <th>電腦給號</th>
                            <th>收據編號</th>
                            <th>作廢原因</th>
                            <th>作業人員</th>
                        </tr>
                        <tr v-for="item in raw_data">
                            <td>{{item["AA01"]}}</td>
                            <td>{{item["AA04"]}}</td>
                            <td>{{item["AA05"]}}</td>
                            <td>{{item["AA104"]}}</td>
                            <td><span :data-id="item['AA39']" :data-name="userNames[item['AA39']]" class="user_tag" :title="item['AA39']">{{userNames[item["AA39"]] || item["AA39"]}}</span></td>
                        </tr>
                    </table>
                </div>`,
                props: ["raw_data"],
                data: () => ({
                    year: "109",
                    next_pc_number: 9109001,  // 9 + year (3 digits) + serial (3 digits)
                    today: "",
                    operator: "",   // 作業人員
                    operator_name: "",
                    AB_number: "",  // 收據編號
                    reason: ""      // 作廢原因
                }),
                watch: {
                    operator: function(val) {
                        this.operator_name = this.userNames[val] || '';
                    }
                },
                computed: {
                    count: function() {
                        return this.raw_data.length;
                    },
                    isDateValid: function() {
                        let regex = /[0-9]{7}/i;
                        return regex.test(this.today) && this.today.length == 7;
                    },
                    isOperatorValid: function() {
                        let regex = /^HB/i;
                        return regex.test(this.operator) && this.operator.length == 6;
                    },
                    isReasonValid: function() {
                        return this.reason != '' && this.reason != undefined && this.reason != null;
                    },
                    isNumberValid: function() {
                        let regex = /^AB/i;
                        return regex.test(this.AB_number) && this.AB_number.length == 10;
                    },
                    isDisabled: function() {
                        return !this.isOperatorValid || !this.isNumberValid || !this.isReasonValid || !this.isDateValid;
                    }
                },
                methods: {
                    add: function(e) {
                        let operator = this.operator.replace(/[^A-Za-z0-9]/g, "");
                        let fee_number = this.AB_number.replace(/[^A-Za-z0-9]/g, "");
                        let reason = this.reason.replace(/[\'\"]/g, "");

                        if (!this.isOperatorValid) {
                            this.animated("#dummy_operator", { name: "tada", callback: () => $("#dummy_operator").focus() });
                            this.notify({
                                title: "作廢資料",
                                message: "請填入作業人員代碼！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        if (!this.isNumberValid) {
                            this.animated("#dummy_fee_number", { name: "tada", callback: () => $("#dummy_fee_number").focus() });
                            this.notify({
                                title: "作廢資料",
                                message: "請填入收據編號！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        if (!this.isReasonValid) {
                            this.animated("#dummy_obsolete_reason", { name: "tada", callback: () => $("#dummy_obsolete_reason").focus() });
                            this.notify({
                                title: "作廢資料",
                                message: "請填入作廢原因！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        if (!this.isDateValid) {
                            this.animated("#dummy_obsolete_date", { name: "tada", callback: () => $("#dummy_obsolete_date").focus() });
                            this.notify({
                                title: "日期",
                                message: "請填入正確日期格式(民國)！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        
                        showConfirm("確定要新增一個新的假資料以供作廢之用？", () => {
                            this.isBusy = true;
                            this.$http.post(CONFIG.API.JSON.QUERY, {
                                type: "add_dummy_ob_fees",
                                today: this.today,
                                pc_number: this.next_pc_number,
                                operator: operator,
                                fee_number: fee_number,
                                reason: reason
                            }).then(res => {
                                closeModal(() => {
                                    this.notify({
                                        title: "新增假規費資料",
                                        body: res.data.message,
                                        type: "success",
                                        pos: "tc"
                                    });
                                });
                            }).catch(err => {
                                this.error = err;
                            }).finally(() => {
                                this.isBusy = false;
                            });
                        });
                    }
                },
                created: function() {
                    var now = new Date();
                    this.year = now.getFullYear() - 1911;
                    this.today = this.year +
                        ("0" + (now.getMonth() + 1)).slice(-2) +
                        ("0" + now.getDate()).slice(-2);
                    if (!this.raw_data) this.raw_data = [];
                    this.next_pc_number = this.raw_data.length > 0 ? parseInt(this.raw_data[0]["AA04"]) + 1 : `9${this.year}001`;
                }
            }
        }
    });

    // It needs to be used in popover, so register it to global scope
    Vue.component("fee-detail-payment-mgt", {
        template: `<div class='form-row form-inline small'>
            <div class='input-group input-group-sm col-8'>
                <div class="input-group-prepend">
                    <span class="input-group-text" id="inputGroup-exapp_method_select">付款方式</span>
                </div>
                <select id='exapp_method_select' class='form-control' v-model="value">
                    <option value='01'>現金[01]</option>
                    <option value='02'>支票[02]</option>
                    <option value='03'>匯票[03]</option>
                    <option value='04'>iBon[04]</option>
                    <option value='05'>ATM[05]</option>
                    <option value='06'>悠遊卡[06]</option>
                    <option value='07'>其他匯款[07]</option>
                    <option value='08'>信用卡[08]</option>
                    <option value='09'>行動支付[09]</option>
                </select>
            </div>
            <div class='filter-btn-group col'>
                <b-button @click="update" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</button>
            </div>
        </div>`,
        props: ["value", "date", "pc_number", "noConfirm"],
        methods: {
            update: function(e) {
                if (this.noConfirm) {
                    this.doUpdate(e);
                } else {
                    let that = this;
                    showConfirm("確定要規費付款方式？", () => that.doUpdate(e));
                }
            },
            doUpdate: function(e) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "expaa_AA100_update",
                    date: this.date,
                    number: this.pc_number,
                    update_value: this.value
                }).then(res => {
                    this.notify({
                        title: "修改規費付款方式",
                        subtitle: `${this.date} ${this.pc_number}`,
                        message: res.data.message,
                        type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                    });
                    closeModal();
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        }
    });

    // It needs to be used in popover, so register it to global scope
    Vue.component("fee-detail-obselete-mgt", {
        template: `<b-form-row>
            <b-col cols="8">
                <b-input-group size="sm" prepend="單據狀態">
                    <b-form-select ref="expaa_obselete" v-model="value" :options="opts"></b-form-select>
                </b-input-group>
            </b-col>
            <b-col>
                <b-button @click="update" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</button>
            </b-col>
        </b-form-row>`,
        props: ["value", "date", "pc_number", "noConfirm"],
        data: () => ({
            opts: [{
                value: 0,
                text: "作廢[0]"
            }, {
                value: 1,
                text: "正常[1]"
            }]
        }),
        methods: {
            update: function(e) {
                if (this.noConfirm) {
                    this.doUpdate(e);
                } else {
                    showConfirm("確定要修改單據狀態？", (e) => {
                        this.doUpdate(e);
                    });
                }
            },
            doUpdate: function(e) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "expaa_AA08_update",
                    date: this.date,
                    number: this.pc_number,
                    update_value: this.value
                }).then(res => {
                    closeModal(() => this.notify({
                            title: "修改單據狀態",
                            subtitle: `${this.date} ${this.pc_number}`,
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                        })
                    );
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        }
    });

    // It needs to be used in popover, so register it to global scope
    Vue.component("fee-detail-print-mgt", {
        template: `<b-form-row>
            <b-col cols="8">
                <b-input-group size="sm" prepend="列印狀態">
                    <b-form-select ref="expaa_print" v-model="value" :options="opts"></b-form-select>
                </b-input-group>
            </b-col>
            <b-col>
                <b-button @click="update" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</button>
            </b-col>
        </b-form-row>`,
        props: ["value", "date", "pc_number", "noConfirm"],
        data: () => ({
            opts: [{
                value: 0,
                text: "未印[0]"
            }, {
                value: 1,
                text: "已印[1]"
            }]
        }),
        methods: {
            update: function(e) {
                if (this.noConfirm) {
                    this.doUpdate(e);
                } else {
                    let that = this;
                    showConfirm("確定要修改列印註記？", (e) => {
                        that.doUpdate(e);
                    });
                }
            },
            doUpdate: function(e) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "expaa_AA09_update",
                    date: this.date,
                    number: this.pc_number,
                    update_value: this.value
                }).then(res => {
                    closeModal(() => this.notify({
                            title: "修改列印註記",
                            subtitle: `${this.date} ${this.pc_number}`,
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                        })
                    );
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        }
    });

    // It needs to be used in expaa-list-mgt & lah-fee-query-board, so register it to global scope
    Vue.component("fee-detail-mgt", {
        template: `<b-container fluid :class="['small']">
            <b-row>
                <b-col id="fee_detail_plate" cols="6">
                    <fieldset>
                        <legend>規費資料集</legend>
                        <h6 v-if="expaa_data.length == 0"><i class="fas fa-exclamation-circle text-danger"></i> {{date}} 找不到 {{pc_number}} 規費詳細資料</h6>
                        <div v-for="(item, key) in expaa_data">
                            <span v-if="key == '列印註記'">
                                <fee-detail-print-mgt :value="item" :date="date" :pc_number="pc_number"></fee-detail-print-mgt>
                            </span>
                            <span v-else-if="key == '繳費方式代碼'">
                                <fee-detail-payment-mgt :value="item" :date="date" :pc_number="pc_number"></fee-detail-print-mgt>
                            </span>
                            <span v-else-if="key == '悠遊卡繳費扣款結果'">
                                <fee-detail-fix-ezcard :raw="expaa_data" :date="date" :pc_number="pc_number"></fee-detail-fix-ezcard>
                            </span>
                            <span v-else-if="key == '單據狀況'">
                            <fee-detail-obselete-mgt :value="item" :date="date" :pc_number="pc_number"></fee-detail-obselete-mgt>
                            </span>
                            <span v-else>{{key}}：{{item}}</span>
                        </div>
                    </fieldset>
                </b-col>
                <b-col cols="6">
                    <fieldset>
                        <legend>收費項目資料集</legend>
                        <h6 v-if="expac_data.length == 0"><i class="fas fa-exclamation-circle text-danger"></i> {{date}} 找不到 {{pc_number}} 付款項目詳細資料</h6>
                        <fee-detail-expac-mgt :expac_list="expac_data" :date="date" :pc_number="pc_number"></fee-detail-expac-mgt>
                    </fieldset>
                </b-col>
            </b-row>
        </b-container>`,
        props: ["date", "pc_number"],
        data: () => ({
            expaa_data: [],
            expac_data: [/*{  // mock data
                AC16: "108",
                AC17: "HB04",
                AC18: "000010",
                AC25: "108",
                AC04: "0000001",
                AC29: "100",
                AC30: "80",
                AC20: "07"
            }*/],
            expac_year: "109"
        }),
        created: function() {
            this.expac_year = this.date.substring(0, 3) || "109";
            this.fetchEXPAA();
            this.fetchEXPAC();
        },
        methods: {
            fetchEXPAA: function() {
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "expaa",
                    qday: this.date,
                    num: this.pc_number,
                    list_mode: false
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.expaa_data = res.data.raw;
                    }
                }).catch(err => {
                    this.error = err;
                });
            },
            fetchEXPAC: function() {
                // EXPAC data fetch
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "expac",
                    year: this.expac_year,
                    num: this.pc_number
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        this.notify({
                            title: "查詢收費項目資料集",
                            message: `找不到規費收費項目資料！【年度： ${this.expac_year}, 電腦給號： ${this.pc_number}】`,
                            type: "warning"
                        });
                    } else {
                        this.expac_data = res.data.raw;
                    }
                }).catch(err => {
                    this.error = err;
                });
            }
        },
        components: {
            "fee-detail-expac-mgt": {
                template: `<div>
                    <h6 v-if="expac_list.length > 0">
                        <b-button variant="outline-info" :pressed="true">
                            規費年度
                            <b-badge variant="light">{{date.substring(0, 3)}} <span class="sr-only">規費年度</span></b-badge>
                        </b-button>
                        &ensp;
                        <b-button variant="outline-info" :pressed="true">
                            電腦給號
                            <b-badge variant="light">{{pc_number}} <span class="sr-only">電腦給號</span></b-badge>
                        </b-button>
                    </h6>
                    <div class='border border-dark rounded p-2 mb-2' v-for="(record, idx) in expac_list">
                        <div class="mb-1">
                            <b-button variant="warning" :class="['reg_case_id']">
                                案號
                                <b-badge variant="light">{{record["AC16"]}}-{{record["AC17"]}}-{{record["AC18"]}} <span class="sr-only">案件號</span></b-badge>
                            </b-button>
                            <!--應收：{{record["AC29"]}}-->
                            <span>實收金額：{{record["AC30"]}}元</span>
                        </div>
                        <div class='form-row form-inline'>
                            <div class='input-group input-group-sm col-9'>
                                <b-form-select
                                    v-model="expac_list[idx]['AC20']"
                                    :options="expe_list"
                                    size="sm"
                                >
                                <template v-slot:first>
                                    <option value="" disabled>-- 請選擇一個項目 --</option>
                                </template>
                                </b-form-select>
                            </div>
                            <div class='filter-btn-group col'>
                                <b-button @click="update($event, idx)" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</b-button>
                            </div>
                        </div>
                    </div>
                </div>`,
                props: ["expac_list", "date", "pc_number"],
                data: () => ({
                    expe_list: [ // from MOIEXP.EXPE
                        { value: "01", text: "01：土地法65條登記費" },
                        { value: "02", text: "02：土地法76條登記費" },
                        { value: "03", text: "03：土地法67條書狀費" },
                        { value: "04", text: "04：地籍謄本工本費" },
                        { value: "06", text: "06：檔案閱覽抄錄複製費" },
                        { value: "07", text: "07：閱覽費" },
                        { value: "08", text: "08：門牌查詢費" },
                        { value: "09", text: "09：複丈費及建物測量費" },
                        { value: "10", text: "10：地目變更勘查費" },
                        { value: "14", text: "14：電子謄本列印" },
                        { value: "18", text: "18：塑膠樁土地界標" },
                        { value: "19", text: "19：鋼釘土地界標(大)" },
                        { value: "30", text: "30：104年度登記罰鍰" },
                        { value: "31", text: "31：100年度登記罰鍰" },
                        { value: "32", text: "32：101年度登記罰鍰" },
                        { value: "33", text: "33：102年度登記罰鍰" },
                        { value: "34", text: "34：103年度登記罰鍰" },
                        { value: "35", text: "35：其他" },
                        { value: "36", text: "36：鋼釘土地界標(小)" },
                        { value: "37", text: "37：105年度登記罰鍰" },
                        { value: "38", text: "38：106年度登記罰鍰" },
                        { value: "39", text: "39：塑膠樁土地界標(大)" },
                        { value: "40", text: "40：107年度登記罰鍰" },
                        { value: "41", text: "41：108年度登記罰鍰" },
                        { value: "42", text: "42：土地法第76條登記費（跨縣市）" },
                        { value: "43", text: "43：書狀費（跨縣市）" },
                        { value: "44", text: "44：罰鍰（跨縣市）" },
                        { value: "45", text: "45：109年度登記罰鍰" },
                        { value: "46", text: "46：110年度登記罰鍰" }
                    ]
                }),
                methods: {
                    update: function(e, idx) {
                        let record = this.expac_list[idx];
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "mod_expac",
                            year: record["AC25"],
                            num: record["AC04"],
                            code: record["AC20"],
                            amount: record["AC30"]
                        }).then(res => {
                            let the_one = this.expe_list.find(function(element) {
                                return element.value == record["AC20"];
                            });
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.notify({
                                    title: "修改收費項目",
                                    subtitle: `${record["AC25"]}-${record["AC04"]}`,
                                    message: `金額 ${record["AC30"]} 項目修正為「${the_one.text}」完成`,
                                    type: "success"
                                });
                                $(e.target).data("orig", record["AC20"]);
                            } else {
                                this.notify({
                                    title: "修改收費項目",
                                    subtitle: `${record["AC25"]}-${record["AC04"]}`,
                                    message: `金額 ${record["AC30"]} 項目修正為「${the_one.text}」失敗`,
                                    type: "danger"
                                });
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    }
                },
                mounted: function() {},
                updated() {
                    Vue.nextTick(() => 
                        this.animated(".reg_case_id", {
                            name: "flash"
                        })
                        .off("click")
                        .on("click", window.vueApp.fetchRegCase)
                        .removeClass("reg_case_id")
                    );
                }
            },
            "fee-detail-fix-ezcard": {
                template: `<div class='form-row form-inline'>
                    <div class='input-group input-group-sm col-auto'>
                        悠遊卡付款狀態：{{raw['悠遊卡繳費扣款結果']}}
                    </div>
                    <div class='filter-btn-group col' v-show="(raw['作廢原因'] == '' || raw['作廢原因'] == undefined) && raw['悠遊卡繳費扣款結果'] != 1">
                        <b-button @click="fixEzcardPayment" size="sm" variant="outline-primary"><i class="fas fa-tools"></i> 修正</button>
                    </div>
                </div>`,
                props: ["raw", "date", "pc_number"],
                methods: {
                    fixEzcardPayment: function(e) {
                        //console.log(this.raw);
                        let amount = this.raw["應收總金額"];
                        let qday = this.date;
                        let pc_number = this.pc_number;
                        let message = `確定要修正 日期: ${qday}, 電腦給號: ${pc_number}, 金額: ${amount} 悠遊卡付款資料為正常？`;
                        showConfirm(message, () => {
                            this.isBusy = true;
        
                            this.$http.post(CONFIG.API.JSON.QUERY, {
                                type: "fix_easycard",
                                qday: qday,
                                pc_num: pc_number
                            }).then(res => {
                                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    this.notify({
                                        title: "悠遊卡自動加值扣款失敗修正",
                                        message: `日期: ${qday}, 電腦給號: ${pc_number}, 金額: ${amount} 悠遊卡付款資料修正成功!`,
                                        type: "success"
                                    });
                                    $(e.target).remove();
                                } else {
                                    throw new Error("回傳狀態碼不正確!【" + res.data.message + "】");
                                }
                            }).catch(err => {
                                this.error = err;
                            }).finally(() => {
                                this.isBusy = false;
                            });
                        });
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... lah-fee-query-board component can not be loaded.");
}
