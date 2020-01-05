if (Vue) {
    Vue.component("expaa-mgt", {
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
                        :class="['form-control', 'h-100']"
                    >
                    </b-form-input>
                    &ensp;
                    <b-button @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                    &ensp;
                    <b-button @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
                    &ensp;
                    <b-button @click="obsolete" variant="outline-secondary" size="sm" title="作廢假資料">
                        <span class="fa-stack">
                            <i class="fas fa-file-alt fa-stack-1x"></i>
                            <i class="fas fa-ban fa-stack-2x text-danger"></i>
                        </span>
                    </b-button>
                </div>
            </b-container>
        </fieldset>`,
        data: () => {
            return {
                date: "",
                number: "",
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
        computed: {
            isNumberValid: function() {
                if (this.number == '' || this.number == undefined) {
                    return null;
                } else if (this.number.toString().length <= 7 && !isNaN(this.number)) {
                    return true;
                }
                return false;
            }
        },
        watch: {
            number: function(nVal, oVal) {
                if (this.number > 9999999) this.number = 9999999;
                else if (this.number < 1) this.number = '';
            }
        },
        methods: {
            query: function(e) {
                let jsonObj = JSON.parse(`
                {
                    "status": 2,
                    "data_count": 250,
                    "message": "\u65bc 1090103 \u627e\u5230 250 \u7b46\u8cc7\u6599",
                    "query_string": "qday=1090103",
                    "raw": [
                      {
                        "AA01": "1090103",
                        "AA04": "0000377",
                        "AA05": "AB00117257",
                        "AA06": "1",
                        "AA07": "0",
                        "AA08": "1",
                        "AA09": "1",
                        "AA10": "H200864399",
                        "AA11": "\u9127\u5982\u6842",
                        "AA12": null,
                        "AA13": "H200864399",
                        "AA14": null,
                        "AA02": null,
                        "AA24": "1090103",
                        "AA25": "109",
                        "AA27": "20",
                        "AA28": "20",
                        "AA39": "HB0514",
                        "AA95": null,
                        "AA96": "1",
                        "AA88": "0",
                        "AA89": null,
                        "AA09F": "0",
                        "AA40": null,
                        "AA100": "01",
                        "AA101": null,
                        "AA102": null,
                        "AA103": null,
                        "AA104": null,
                        "AA105": "HB",
                        "AA106": null,
                        "AA107": null,
                        "AA108": null
                      },
                      {
                        "AA01": "1090103",
                        "AA04": "0000378",
                        "AA05": "AB00117258",
                        "AA06": "1",
                        "AA07": "0",
                        "AA08": "1",
                        "AA09": "1",
                        "AA10": "H122007305",
                        "AA11": "\u9ec3\u51a0\u9298",
                        "AA12": null,
                        "AA13": "H122007305",
                        "AA14": null,
                        "AA02": null,
                        "AA24": "1090103",
                        "AA25": "109",
                        "AA27": "60",
                        "AA28": "60",
                        "AA39": "HB0514",
                        "AA95": null,
                        "AA96": "1",
                        "AA88": "0",
                        "AA89": null,
                        "AA09F": "0",
                        "AA40": null,
                        "AA100": "08",
                        "AA101": null,
                        "AA102": null,
                        "AA103": null,
                        "AA104": null,
                        "AA105": "HB",
                        "AA106": null,
                        "AA107": null,
                        "AA108": null
                      },
                      {
                        "AA01": "1090103",
                        "AA04": "0000379",
                        "AA05": "AB00108571",
                        "AA06": "1",
                        "AA07": "0",
                        "AA08": "1",
                        "AA09": "1",
                        "AA10": "P120246587",
                        "AA11": "\u66fe\u660e\u5c71",
                        "AA12": null,
                        "AA13": "P120246587",
                        "AA14": null,
                        "AA02": null,
                        "AA24": "1090103",
                        "AA25": "109",
                        "AA27": "40",
                        "AA28": "40",
                        "AA39": "HB1213",
                        "AA95": null,
                        "AA96": "1",
                        "AA88": "0",
                        "AA89": null,
                        "AA09F": "0",
                        "AA40": null,
                        "AA100": "06",
                        "AA101": null,
                        "AA102": null,
                        "AA103": null,
                        "AA104": null,
                        "AA105": "HB",
                        "AA106": "1",
                        "AA107": "00003791090103081948",
                        "AA108": null
                      },
                      {
                        "AA01": "1090103",
                        "AA04": "0000625",
                        "AA05": "AB00109993",
                        "AA06": "1",
                        "AA07": "0",
                        "AA08": "1",
                        "AA09": "1",
                        "AA10": "H100739844",
                        "AA11": "\u5433\u5609\u70b3",
                        "AA12": null,
                        "AA13": "H100739844",
                        "AA14": null,
                        "AA02": null,
                        "AA24": "1090103",
                        "AA25": "109",
                        "AA27": "60",
                        "AA28": "60",
                        "AA39": "HB1200",
                        "AA95": null,
                        "AA96": "1",
                        "AA88": "0",
                        "AA89": null,
                        "AA09F": "0",
                        "AA40": null,
                        "AA100": "05",
                        "AA101": null,
                        "AA102": null,
                        "AA103": null,
                        "AA104": null,
                        "AA105": "HB",
                        "AA106": null,
                        "AA107": null,
                        "AA108": null
                      },
                      {
                        "AA01": "1090103",
                        "AA04": "0000626",
                        "AA05": "AB00116125",
                        "AA06": "1",
                        "AA07": "0",
                        "AA08": "1",
                        "AA09": "1",
                        "AA10": "H122217356",
                        "AA11": "\u8cf4\u660e\u7687",
                        "AA12": "\u8cb7\u8ce3",
                        "AA13": "H122217356",
                        "AA14": "\u8cf4\u660e\u7687",
                        "AA02": null,
                        "AA24": "1090103",
                        "AA25": "109",
                        "AA27": "3055",
                        "AA28": "3055",
                        "AA39": "HB0167",
                        "AA95": null,
                        "AA96": null,
                        "AA88": "1",
                        "AA89": "HB0167",
                        "AA09F": "0",
                        "AA40": null,
                        "AA100": "09",
                        "AA101": null,
                        "AA102": null,
                        "AA103": null,
                        "AA104": null,
                        "AA105": "HB",
                        "AA106": null,
                        "AA107": null,
                        "AA108": null
                      }
                    ]
                  }
                `);
                let VNode = this.$createElement("expaa-category-dashboard", {
                    props: {
                        raw_data: jsonObj.raw
                    }
                });
                showModal({
                    message: VNode,
                    title: `${this.date} 規費`
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

                    // use the expaa-obsolete-mgt sub-component to do the addition
                    let VNode = this.$createElement("expaa-obsolete-mgt", {
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
                    console.error("expaa-mgt::obsolete parsing failed", ex);
                    showAlert({
                        title: "expaa-mgt::obsolete",
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
                        showModal({
                            title: title,
                            message: this.$createElement("expaa-list-mgt", { props: { items: data || [] } }),
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
                            let VNode = this.$createElement("expaa-fee-detail", {
                              props: { date: date, pc_number: pc_number}
                            });
                            showModal({
                              message: VNode,
                              title: "規費資料詳情",
                              backdrop_close: true
                            })
                          }
                        },
                        components: {
                          "expaa-fee-detail": {
                            template: `<b-container fluid>
                              {{date}}, {{pc_number}}
                            </b-container>`,
                            props: ["date", "pc_number"],
                            data: function() {
                              return {
                                expaa_data: [],
                                expac_data: []
                              }
                            },
                            created: function() {
                              // todo: fetch remote expaa, expac data
                            }
                          }
                        }
                    }
                }
            },
            "expaa-obsolete-mgt": {
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
                                console.error("expaa-obsolete-mgt::add parsing failed", ex);
                                showAlert({
                                    title: "expaa-obsolete-mgt::add",
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
} else {
    console.error("vue.js not ready ... expaa-mgt component can not be loaded.");
}
