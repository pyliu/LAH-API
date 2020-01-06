if (Vue) {
    Vue.component("fee-query-board", {
        template: `<fieldset>
            <legend>規費資料</legend>
            <b-container :class="['form-row']" fluid>
                <div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-fee_query_date">日期</span>
                    </div>
                    <b-form-input
                        v-model="date"
                        id="fee_query_date"
                        placeholder="民國年月日"
                        :class="['form-control', 'no-cache', 'bg-light', 'border', 'pl-2', 'h-100']"
                        size="sm"
                        plaintext
                        trim
                    >
                    </b-form-input>
                    <div class="input-group-prepend ml-1">
                        <span class="input-group-text" id="inputGroup-fee_query_number">電腦給號</span>
                    </div>
                    <b-form-input
                        v-model="number"
                        id="fee_query_number"
                        type="number"
                        placeholder="七碼電腦給號"
                        :state="isNumberValid"
                        size="sm"
                        max=9999999
                        min=1
                        trim
                        number
                        :class="['form-control', 'h-100', 'no-cache']"
                    >
                    </b-form-input>
                    &ensp;
                    <b-button @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                    &ensp;
                    <b-button @click="obsolete" variant="outline-secondary" size="sm" title="作廢假資料">
                        <span class="fa-stack">
                            <i class="fas fa-file-alt fa-stack-1x"></i>
                            <i class="fas fa-ban fa-stack-2x text-danger"></i>
                        </span>
                    </b-button>
                    &ensp;
                    <b-button @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
                </div>
            </b-container>
        </fieldset>`,
        data: () => {
            return {
                date: "",
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
            query: function(e) {
                if (isEmpty(this.number)) {
                    this.fetchList();
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
            fetchList: function() {
                let body = new FormData();
                body.append("type", "expaa");
                body.append("qday", this.date);
                body.append("num", this.number);
                body.append("list_mode", true);
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
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
                    <img src="assets/howto/EXPAA_EXPAC_AMOUNT_MOD.jpg" class="img-responsive img-thumbnail my-1" />
                    `,
                    size: "lg"
                });
            },
            obsolete: function(e) {
                // query first then do the creation
                let body = new FormData();
                body.append("type", "get_dummy_ob_fees");

                toggle(e.target);

                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
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
                        callback: () => {
                            addUserInfoEvent();
                        }
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
            var d = new Date();
            this.date = toTWDate(d);
            if (this.number > 9999999) this.number = 9999999;
            else if (this.number < 1) this.number = '';
        },
        mounted: function() {
            let that = this;
            setTimeout(() => that.number = $("#fee_query_number").val(), 150);
            if ($("#fee_query_date").datepicker) {
                $("#fee_query_date").datepicker({
                    daysOfWeekDisabled: "",
                    language: "zh-TW",
                    daysOfWeekHighlighted: "1,2,3,4,5",
                    todayHighlight: true,
                    autoclose: true,
                    format: {
                        toDisplay: (date, format, language) => toTWDate(new Date(date)),
                        toValue: (date, format, language) => new Date()
                    }
                });
            }
        },
        components: {
            "expaa-category-dashboard": {
                template: `<b-container id="expaa-list-container" fluid :class="['small', 'text-center']">
                    <b-row>
                        <b-col>
                            <b-button variant="info" block @click="open('全部規費列表', raw_data)">
                                全部 <b-badge variant="light">{{count_all}} <span class="sr-only">全部收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col>
                            <b-button variant="success" block @click="open('現金規費列表', cash)">
                                現金 <b-badge variant="light">{{count_cash}} <span class="sr-only">現金收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col>
                            <b-button variant="primary" block @click="open('悠遊卡規費列表', ezcard)">
                                悠遊卡 <b-badge variant="light">{{count_ezcard}} <span class="sr-only">悠遊卡收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                    </b-row>
                    <b-row :class="['mt-1']">
                        <b-col>
                            <b-button variant="danger" block @click="open('行動支付規費列表', mobile)">
                                行動支付 <b-badge variant="light">{{count_mobile}} <span class="sr-only">行動支付收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col>
                            <b-button variant="warning" block @click="open('信用卡規費列表', credit)">
                                信用卡 <b-badge variant="light">{{count_credit}} <span class="sr-only">信用卡收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                        <b-col>
                            <b-button variant="secondary" block @click="open('其他規費列表', other)">
                                其他 <b-badge variant="light">{{count_other}} <span class="sr-only">其他收費數量</span></b-badge>
                            </b-button>
                        </b-col>
                    </b-row>
                </b-container>`,
                props: ["raw_data"],
                data: () => {
                    return {
                        cash: [],
                        ezcard: [],
                        mobile: [],
                        credit: [],
                        other: []
                    }
                },
                computed: {
                    count_cash: function() { return this.cash.length; },
                    count_ezcard: function() { return this.ezcard.length; },
                    count_mobile: function() { return this.mobile.length; },
                    count_other: function() { return this.other.length; },
                    count_credit: function() { return this.credit.length; },
                    count_all: function() { return this.raw_data.length; }
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
                            size: "lg",
                            backdrop_close: true
                        });
                    }
                },
                components: {
                    "expaa-list-mgt": {
                        template: `<b-container fluid>
                            <b-button @click="open(item['AA01'], item['AA04'])" variant="outline-primary" pill size="sm" :class="['float-left', 'mr-2', 'mb-2']" v-for="(item, idx) in items">{{item["AA04"]}}</b-button>
                        </b-container>`,
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

                            fetch("query_json_api.php", {
                                method: "POST",
                                body: body
                            }).then(response => {
                                if (response.status != 200) {
                                    throw new Error("XHR連線異常，回應非200");
                                }
                                return response.json();
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
    // It needs to be used in expaa-list-mgt & fee-query-board, so register it to global scope 
    Vue.component("fee-detail-mgt", {
        template: `<b-container fluid>
            <h6 v-if="expaa_data.length == 0"><i class="fas fa-exclamation-circle text-danger"></i> {{date}} 找不到 {{pc_number}} 規費詳細資料</h6>
            <h6 v-if="expac_data.length == 0"><i class="fas fa-exclamation-circle text-danger"></i> {{date}} 找不到 {{pc_number}} 付款項目詳細資料</h6>
            <b-row>
                <b-col id="fee_detail_plate" cols="6">
                    <ul>
                        <li v-for="(item, key) in expaa_data">
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
                        </li>
                    <ul>
                </b-col>
                <b-col cols="6"></b-col>
            </b-row>
        </b-container>`,
        props: ["date", "pc_number"],
        components: {
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

                            fetch("query_json_api.php", {
                                method: "POST",
                                body: body
                            }).then(response => {
                                if (response.status != 200) {
                                    throw new Error("XHR連線異常，回應非200");
                                }
                                return response.json();
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
            },
            "fee-detail-payment-mgt": {
                template: `<div class='form-row form-inline'>
                    <div class='input-group input-group-sm col-9'>
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="inputGroup-exapp_method_select">付款方式</span>
                        </div>
                        <select id='exapp_method_select' class='form-control' v-model="value">
                            <option value='01'>【01】現金</option>
                            <option value='02'>【02】支票</option>
                            <option value='03'>【03】匯票</option>
                            <option value='04'>【04】iBon</option>
                            <option value='05'>【05】ATM</option>
                            <option value='06'>【06】悠遊卡</option>
                            <option value='07'>【07】其他匯款</option>
                            <option value='08'>【08】信用卡</option>
                            <option value='09'>【09】行動支付</option>
                        </select>
                    </div>
                    <div class='filter-btn-group col'>
                        <b-button @click="updateExpaaAA100" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</button>
                    </div>
                </div>`,
                props: ["value", "date", "pc_number"],
                methods: {
                    updateExpaaAA100: function(e) {
                        let that = this;
                        showConfirm("確定要規費付款方式？", () => {
                            let body = new FormData();
                            body.append("type", "expaa_AA100_update");
                            body.append("date", that.date);
                            body.append("number", that.pc_number);
                            body.append("update_value", that.value);
                    
                            toggle(e.target);
                    
                            fetch("query_json_api.php", {
                                method: "POST",
                                body: body
                            }).then(response => {
                                if (response.status != 200) {
                                    throw new Error("XHR連線異常，回應非200");
                                }
                                return response.json();
                            }).then(jsonObj => {
                                addNotification({
                                    title: "修改規費付款方式",
                                    message: jsonObj.message,
                                    type: jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                                });
                                $(e.target).remove();
                            });
                        });
                    }
                }
            },
            "fee-detail-print-mgt": {
                template: `<div class='form-row form-inline'>
                    <div class='input-group input-group-sm col-8'>
                        <select id='exapp_print_select' class='form-control' v-model="value">
                            <option value='0'>【0】未印</option>
                            <option value='1'>【1】已印</option>
                        </select>
                    </div>
                    <div class='filter-btn-group col-4'>
                        <b-button @click="updateExpaaAA09" size="sm" variant="outline-primary"><i class="fas fa-edit"></i> 修改</button>
                    </div>
                </div>`,
                props: ["value", "date", "pc_number"],
                methods: {
                    updateExpaaAA09: function(e) {
                        let that = this;
                        showConfirm("確定要修改列印註記？", () => {
                            let body = new FormData();
                            body.append("type", "expaa_AA09_update");
                            body.append("date", that.date);
                            body.append("number", that.pc_number);
                            body.append("update_value", that.value);
                    
                            toggle(e.target);
                    
                            fetch("query_json_api.php", {
                                method: "POST",
                                body: body
                            }).then(response => {
                                if (response.status != 200) {
                                    throw new Error("XHR連線異常，回應非200");
                                }
                                return response.json();
                            }).then(jsonObj => {
                                addNotification({
                                    title: "修改列印註記",
                                    message: jsonObj.message,
                                    type: jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? "success" : "danger"
                                });
                                $(e.target).remove();
                            });
                        });
                    }
                }
            }
        },
        data: function() {
            return {
                expaa_data: [],
                expac_data: [],
                expac_year: "109",
                expe: { // from MOIEXP.EXPE
                    "01": "土地法65條登記費",
                    "02": "土地法76條登記費",
                    "03": "土地法67條書狀費",
                    "04": "地籍謄本工本費",
                    "06": "檔案閱覽抄錄複製費",
                    "07": "閱覽費",
                    "08": "門牌查詢費",
                    "09": "複丈費及建物測量費",
                    "10": "地目變更勘查費",
                    "14": "電子謄本列印",
                    "18": "塑膠樁土地界標",
                    "19": "鋼釘土地界標(大)",
                    "30": "104年度登記罰鍰",
                    "31": "100年度登記罰鍰",
                    "32": "101年度登記罰鍰",
                    "33": "102年度登記罰鍰",
                    "34": "103年度登記罰鍰",
                    "35": "其他",
                    "36": "鋼釘土地界標(小)",
                    "37": "105年度登記罰鍰",
                    "38": "106年度登記罰鍰",
                    "39": "塑膠樁土地界標(大)",
                    "40": "107年度登記罰鍰",
                    "41": "108年度登記罰鍰",
                    "42": "土地法第76條登記費（跨縣市）",
                    "43": "書狀費（跨縣市）",
                    "44": "罰鍰（跨縣市）",
                    "45": "109年度登記罰鍰"
                }
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
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
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
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
                }).then(jsonObj => {

                }).catch(ex => {
                    console.error("fee-detail-mgt::fetchEXPAC parsing failed", ex);
                    showAlert({title: "fee-detail-mgt::fetchEXPAC", message: ex.toString(), type: "danger"});
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... fee-query-board component can not be loaded.");
}
