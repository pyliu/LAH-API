if (Vue) {
    Vue.component("fee-query-board", {
        template: `<fieldset>
            <legend>規費資料</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <b-input-group size="sm">
                        <b-input-group-prepend is-text>日期</b-input-group-prepend>
                        <b-form-input
                            id="fee_query_date"
                            type="date"
                            v-model="bc_date"
                            size="sm"
                            :class="['no-cache']"
                            :formatter="toTWDate"
                        ></b-form-input>
                    </b-input-group>
                </b-col>
                <b-col>
                    <b-input-group size="sm">
                        <b-input-group-prepend is-text>電腦給號</b-input-group-prepend>
                        <b-form-input
                            v-model="number"
                            id="fee_query_number"
                            type="number"
                            placeholder="7碼數字"
                            :state="isNumberValid"
                            size="sm"
                            max=9999999
                            min=1
                            trim
                            number
                            :class="['no-cache']"
                        >
                        </b-form-input>
                    </b-input-group>
                </b-col>
            </b-form-row>
            <b-form-row align-h="around" align-v="center">
                <b-col>
                    <b-button pill block @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                </b-col>
                <b-col>
                    <b-button pill block @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
                </b-col>
                <b-col>
                    <b-button pill block @click="obsolete" variant="outline-secondary" size="sm" title="新增作廢假資料">
                        <span class="fa-stack" style="font-size: 0.5rem">
                            <i class="fas fa-file-alt fa-stack-1x"></i>
                            <i class="fas fa-ban fa-stack-2x text-danger"></i>
                        </span>
                        作廢
                    </b-button>
                </b-col>
            </b-form-row>
        </fieldset>`,
        data: () => {
            return {
                date: "",
                bc_date: "2020-01-09",
                number: ""
            }
        },
        computed: {
            isNumberValid: function() {
                if (this.number == '' || this.number == undefined) {
                    return null;
                }
                let intVal = parseInt(this.number);
                if (intVal < 9999999 && intVal > 0) {
                    return true;
                }
                return false;
            }
        },
        watch: {
            number: function(nVal, oVal) {
                let intVal = parseInt(this.number);
                if (intVal > 9999999)
                    this.number = 9999999;
                else if (Number.isNaN(intVal) || intVal < 1)
                    this.number = '';
            }
        },
        methods: {
            toTWDate: function(val) {
                let d = new Date(val);
                this.date = (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
                // also clear the number for query by date purpose
                this.number = '';
                return val;
            },
            query: function(e) {
                if (this.bc_date == "NaNaNaN" || this.bc_date == "" || this.bc_date == undefined) {
                    let d = new Date();
                    this.date = (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
                }
                if (isEmpty(this.number)) {
                    this.fetchList(e);
                } else {
                    let VNode = this.$createElement("fee-detail-mgt", {
                        props: { date: this.date, pc_number: this.number.toString().padStart(7, "0")}
                    });
                    showModal({
                        message: VNode,
                        title: "規費資料詳情",
                        size: "lg"
                    });
                }
            },
            fetchList: function(e) {
                let body = new FormData();
                body.append("type", "expaa");
                body.append("qday", this.date);
                body.append("num", this.number);
                body.append("list_mode", true);
                
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    if (jsonObj.data_count == 0) {
                        addNotification({
                            title: "查詢規費統計",
                            message: `${this.date} 查無資料`,
                            type: "warning"
                        });
                        return;
                    }
                    let that = this;
                    let VNode = this.$createElement("expaa-category-dashboard", {
                        props: {
                            raw_data: jsonObj.raw
                        },
                        on: {
                            "number_clicked": function(number) {
                               that.number = number;
                            }
                        }
                    });
                    showModal({
                        message: VNode,
                        title: `${this.date} 規費統計`
                    });
                }).catch(ex => {
                    console.error("fee-query-board::fetchList parsing failed", ex);
                    showAlert({title: "fee-query-board::fetchList", message: ex.toString(), type: "danger"});
                });
            },
            popup: function(e) {
                showModal({
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
                let body = new FormData();
                body.append("type", "get_dummy_ob_fees");

                toggle(e.target);

                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    toggle(e.target);

                    // use the fee-obsolete-mgt sub-component to do the addition
                    let VNode = this.$createElement("fee-obsolete-mgt", {
                        props: {
                            raw_data: jsonObj.raw
                        }
                    });
                    
                    showModal({
                        title: "規費作廢假資料",
                        message: VNode,
                        size: "lg",
                        callback: () => addUserInfoEvent()
                    });
                }).catch(ex => {
                    console.error("fee-query-board::obsolete parsing failed", ex);
                    showAlert({
                        title: "fee-query-board::obsolete",
                        message: ex.message,
                        type: "danger"
                    });
                });
            }
        },
        created: function() {
            let d = new Date();
            this.date = (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
            this.bc_date = d.getFullYear() + "-" + ("0" + (d.getMonth()+1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
            if (this.number > 9999999) this.number = 9999999;
            else if (this.number < 1) this.number = '';
        },
        mounted: function() { },
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
                data: () => {
                    return {
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
                    }
                },
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
                        showModal({
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
                        // To use map function to make the result array of AA28 ($$) list (exclude the obsolete one, AA02 is the obsolete date) then uses reduce function to accumulate the numbers and return.
                        return collection.map(element => isEmpty(element["AA02"]) ? element["AA28"] : 0).reduce((acc, curr) => acc + parseInt(curr), 0);
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
                                showModal({
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

                }
            },
            "fee-obsolete-mgt": {
                template: `<div class="small">
                    下一筆假資料：<br />
                    ※ 電腦給號：{{next_pc_number}} <br />
                    ※ 日期：{{today}}
                    <hr>
                    <div id="obsolete_container" class="form-row">
                        <div class="input-group input-group-sm col-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-operator">作業人員</span>
                            </div>
                            <b-form-input
                                v-model="operator"
                                id="dummy_operator"
                                placeholder="HBXXXX"
                                :state="isOperatorValid"
                                size="sm"
                                trim
                            >
                            </b-form-input>
                        </div>
                        <div class="input-group input-group-sm col-4">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-fee-number">收據號碼</span>
                            </div>
                            <b-form-input
                                v-model="AB_number"
                                id="dummy_fee_number"
                                placeholder="ABXXXXXXXX"
                                :state="isNumberValid"
                                size="sm"
                                trim
                            >
                            </b-form-input>
                        </div>
                        <div class="input-group input-group-sm col-4">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-obsolete-reason">作廢原因</span>
                            </div>
                            <b-form-input
                                v-model="reason"
                                id="dummy_obsolete_reason"
                                placeholder="卡紙"
                                :state="isReasonValid"
                                size="sm"
                                trim
                            >
                            </b-form-input>
                        </div>
                        <div class="btn-group-sm col-1" role="group">
                            <b-button @click="add" variant="outline-primary" :disabled="isDisabled" size="sm" pill>新增</b-button>
                        </div>
                    </div>
                    <hr>
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
                            <td><span :data-id="item['AA39']" class="user_tag">{{item['AA39']}}</span></td>
                        </tr>
                    </table>
                </div>`,
                props: ["raw_data"],
                data: function() {
                    return {
                        year: "109",
                        next_pc_number: 9109001,  // 9 + year (3 digits) + serial (3 digits)
                        today: "",
                        operator: "",   // 作業人員
                        AB_number: "",  // 收據編號
                        reason: ""      // 作廢原因
                    }
                },
                computed: {
                    count: function() {
                        return this.raw_data.length;
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
                        return !this.isOperatorValid || !this.isNumberValid || !this.isReasonValid;
                    }
                },
                methods: {
                    add: function(e) {
                        let operator = this.operator.replace(/[^A-Za-z0-9]/g, "");
                        let fee_number = this.AB_number.replace(/[^A-Za-z0-9]/g, "");
                        let reason = this.reason.replace(/[\'\"]/g, "");

                        if (!this.isOperatorValid) {
                            addAnimatedCSS("#dummy_operator", { name: "tada", callback: () => $("#dummy_operator").focus() });
                            addNotification({
                                title: "作廢資料",
                                message: "請填入作業人員代碼！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        if (!this.isNumberValid) {
                            addAnimatedCSS("#dummy_fee_number", { name: "tada", callback: () => $("#dummy_fee_number").focus() });
                            addNotification({
                                title: "作廢資料",
                                message: "請填入收據編號！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        if (!this.isReasonValid) {
                            addAnimatedCSS("#dummy_obsolete_reason", { name: "tada", callback: () => $("#dummy_obsolete_reason").focus() });
                            addNotification({
                                title: "作廢資料",
                                message: "請填入作廢原因！",
                                pos: "tc",
                                type: "warning"
                            });
                            return false;
                        }
                        
                        let that = this;
                        showConfirm("確定要新增一個新的假資料？", () => {
                            let body = new FormData();
                            body.append("type", "add_dummy_ob_fees");
                            body.append("today", that.today);
                            body.append("pc_number", that.next_pc_number);
                            body.append("operator", operator);
                            body.append("fee_number", fee_number);
                            body.append("reason", reason);

                            toggle(e.target);

                            asyncFetch(CONFIG.JSON_API_EP, {
                                method: "POST",
                                body: body
                            }).then(jsonObj => {
                                closeModal(() => {
                                    addNotification({
                                        title: "新增假規費資料",
                                        body: jsonObj.message,
                                        type: "success",
                                        pos: "tc"
                                    });
                                });
                            }).catch(ex => {
                                console.error("fee-obsolete-mgt::add parsing failed", ex);
                                showAlert({
                                    title: "fee-obsolete-mgt::add",
                                    message: ex.message,
                                    type: "danger"
                                });
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
        template: `<div class='form-row form-inline small-font'>
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
                let body = new FormData();
                body.append("type", "expaa_AA100_update");
                body.append("date", this.date);
                body.append("number", this.pc_number);
                body.append("update_value", this.value);
        
                toggle(e.target);
        
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    addNotification({
                        title: "修改規費付款方式",
                        subtitle: `${this.date} ${this.pc_number}`,
                        message: jsonObj.message,
                        type: jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                    });
                    toggle(e.target);
                    closeModal();
                });
            }
        }
    });

    // It needs to be used in popover, so register it to global scope
    Vue.component("fee-detail-print-mgt", {
        template: `<div class='form-row form-inline small-font'>
            <div class='input-group input-group-sm col-8'>
                <div class="input-group-prepend">
                    <span class="input-group-text" id="inputGroup-exapp_print_select">列印狀態</span>
                </div>
                <select id='exapp_print_select' class='form-control' v-model="value">
                    <option value='0'>未印[0]</option>
                    <option value='1'>已印[1]</option>
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
                    showConfirm("確定要修改列印註記？", (e) => {
                        that.doUpdate(e);
                    });
                }
            },
            doUpdate: function(e) {
                let body = new FormData();
                body.append("type", "expaa_AA09_update");
                body.append("date", this.date);
                body.append("number", this.pc_number);
                body.append("update_value", this.value);
        
                toggle(e.target);
        
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    addNotification({
                        title: "修改列印註記",
                        subtitle: `${this.date} ${this.pc_number}`,
                        message: jsonObj.message,
                        type: jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                    });
                    toggle(e.target);
                    closeModal();
                });
            }
        }
    });

    // It needs to be used in expaa-list-mgt & fee-query-board, so register it to global scope
    Vue.component("fee-detail-mgt", {
        template: `<b-container fluid :class="['small-font']">
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
        data: function() {
            return {
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
            }
        },
        created: function() {
            this.expac_year = this.date.substring(0, 3) || "109";
            this.fetchEXPAA();
            this.fetchEXPAC();
        },
        methods: {
            fetchEXPAA: function() {
                let body = new FormData();
                body.append("type", "expaa");
                body.append("qday", this.date);
                body.append("num", this.pc_number);
                body.append("list_mode", false);
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.expaa_data = jsonObj.raw;
                    }
                }).catch(ex => {
                    console.error("fee-detail-mgt::fetchEXPAA parsing failed", ex);
                    showAlert({title: "fee-detail-mgt::fetchEXPAA", message: ex.toString(), type: "danger"});
                });
            },
            fetchEXPAC: function() {
                // EXPAC data fetch
                let body = new FormData();
                body.append("type", "expac");
                body.append("year", this.expac_year);
                body.append("num", this.pc_number);
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            title: "查詢收費項目資料集",
                            message: `找不到規費收費項目資料！【年度： ${this.expac_year}, 電腦給號： ${this.pc_number}】`,
                            type: "warning"
                        });
                    } else {
                        this.expac_data = jsonObj.raw;
                    }
                }).catch(ex => {
                    console.error("fee-detail-mgt::fetchEXPAC parsing failed", ex);
                    showAlert({title: "fee-detail-mgt::fetchEXPAC", message: ex.toString(), type: "danger"});
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
                data: function() {
                    return {
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
                            { value: "45", text: "45：109年度登記罰鍰" }
                        ]
                    }
                },
                methods: {
                    update: function(e, idx) {
                        let record = this.expac_list[idx];
                        let body = new FormData();
                        body.append("type", "mod_expac");
                        body.append("year", record["AC25"]);
                        body.append("num", record["AC04"]);
                        body.append("code", record["AC20"]);
                        body.append("amount", record["AC30"]);

                        toggle(e.target);

                        asyncFetch(CONFIG.JSON_API_EP, {
                            method: "POST",
                            body: body
                        }).then(jsonObj => {
                            let the_one = this.expe_list.find(function(element) {
                                return element.value == record["AC20"];
                            });
                            if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                addNotification({
                                    title: "修改收費項目",
                                    subtitle: `${record["AC25"]}-${record["AC04"]}`,
                                    message: `金額 ${record["AC30"]} 項目修正為「${the_one.text}」完成`,
                                    type: "success"
                                });
                                $(e.target).data("orig", record["AC20"]);
                            } else {
                                addNotification({
                                    title: "修改收費項目",
                                    subtitle: `${record["AC25"]}-${record["AC04"]}`,
                                    message: `金額 ${record["AC30"]} 項目修正為「${the_one.text}」失敗`,
                                    type: "danger"
                                });
                            }
                            toggle(e.target);
                        }).catch(ex => {
                            showAlert({
                                title: "fee-detail-expac-mgt::update",
                                message: ex.toString(),
                                type: "danger"
                            });
                        });
                    }
                },
                mounted: function() {},
                updated() {
                    Vue.nextTick(() => 
                        addAnimatedCSS(".reg_case_id", {
                            name: "flash"
                        })
                        .off("click")
                        .on("click", window.utilApp.fetchRegCase)
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
                            toggle(e.target);
        
                            let body = new FormData();
                            body.append("type", "fix_easycard");
                            body.append("qday", qday);
                            body.append("pc_num", pc_number);
        
                            asyncFetch(CONFIG.JSON_API_EP, {
                                method: "POST",
                                body: body
                            }).then(jsonObj => {
                                if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    addNotification({
                                        title: "悠遊卡自動加值扣款失敗修正",
                                        message: `日期: ${qday}, 電腦給號: ${pc_number}, 金額: ${amount} 悠遊卡付款資料修正成功!`,
                                        type: "success"
                                    });
                                    $(e.target).remove();
                                } else {
                                    throw new Error("回傳狀態碼不正確!【" + jsonObj.message + "】");
                                }
                            }).catch(ex => {
                                console.error("fee-detail-fix-ezcard::fixEzcardPayment parsing failed", ex);
                                showAlert({
                                    title: "fee-detail-fix-ezcard::fixEzcardPayment",
                                    message: ex.toString(),
                                    type: "danger"
                                });
                            });
                        });
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... fee-query-board component can not be loaded.");
}
