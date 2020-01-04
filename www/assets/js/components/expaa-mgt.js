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
                        :state="isDateValid"
                        :class="['no-cache', 'bg-light', 'border', 'pl-2']"
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
                    >
                    </b-form-input>
                    &ensp;
                    <button @click="query" class="btn btn-sm btn-outline-primary">查詢</button>
                    &ensp;
                    <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
                    &ensp;
                    <button @click="obsolete" class="btn btn-sm btn-outline-danger" title="新增作廢假資料"><i class="fas fa-ban"></i></button>
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
            isDateValid: function() {
                if (this.date == '' || this.date == undefined) {
                    return null;
                } else if (this.date.length == 7 && !isNaN(this.date)) {
                    return true;
                }
                return false;
            },
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
                else if (this.number < 1) this.number = 1;
            }
        },
        methods: {
            query: function(e) {
                console.log(this.date, this.number);
                return;

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
                    // only has one record
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        let html = "<div class='text-info'>規費資料：</div>";
                        html += "<ul>";
                        for (let key in jsonObj.raw) {
                            html += "<li>";
                            html += key + "：";
                            if (key == "列印註記") {
                                html += "<div class='form-row form-inline'>"
                                    + "<div class='input-group input-group-sm col-3'>"
                                    + "<select id='exapp_print_select' class='form-control'>"
                                    + "<option value='0'" + (jsonObj.raw[key] == 0 ? "selected" : "") + ">【0】未印</option>"
                                    + "<option value='1'" + (jsonObj.raw[key] == 1 ? "selected" : "") + ">【1】已印</option>"
                                    + "</select> "
                                    + "</div>"
                                    + `<div class='filter-btn-group col'>
                                            <button id='exapp_print_button' class='btn btn-sm btn-outline-primary'>修改</button>
                                            <span id='exapp_print_status'></span>
                                        </div>`
                                    + "</div>";
                            } else if (key == "繳費方式代碼") {
                                html += "<div class='form-row form-inline'>"
                                    + "<div class='input-group input-group-sm col-3'>"
                                    + "<select id='exapp_method_select' class='form-control'>"
                                    + getExpaaAA100Options(jsonObj.raw[key])
                                    + "</select> "
                                    + "</div>"
                                    + `<div class='filter-btn-group col'>
                                            <button id='exapp_method_button' class='btn btn-sm btn-outline-primary'>修改</button>
                                            <span id='exapp_method_status'></span>
                                        </div>`
                                    + "</div>";
                            } else if (key == "悠遊卡繳費扣款結果") {
                                html += jsonObj.raw[key];
                                //  無作廢原因才可進行修正
                                if (isEmpty(jsonObj.raw["作廢原因"]) && jsonObj.raw[key] != 1) {
                                    html += "&ensp;<button class='btn btn-sm btn-outline-danger' id='fix_exapp_easycard_payment_btn" + "' onclick='xhrFixEasycardPayment(\"" + jsonObj.raw["開單日期"] + "\", \"" + jsonObj.raw["電腦給號"] + "\", \"" + jsonObj.raw["實收總金額"] + "\", \"fix_exapp_easycard_payment_btn" + "\")'>修正為扣款成功</button>";
                                }
                            } else {
                                // others just show info
                                html += jsonObj.raw[key];
                            }
                            html += "</li>";
                        };
                        html += "</ul>";
                        $("#expaa_query_display").html(html);
                        // attach event handler for the buttons
                        $("#exapp_print_button").off("click").on("click", xhrUpdateExpaaAA09.bind({
                            date: $("#expaa_query_date").val(),
                            number: $("#expaa_query_number").val(),
                            select_id: "exapp_print_select"
                        }));
                        $("#exapp_method_button").off("click").on("click", xhrUpdateExpaaAA100.bind({
                            date: $("#expaa_query_date").val(),
                            number: $("#expaa_query_number").val(),
                            select_id: "exapp_method_select"
                        }));
                    } else if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_WITH_MULTIPLE_RECORDS) {
                        // has many records
                        let html = "<div>" 
                                + "<span class='block-secondary'>現金</span> "
                                + "<span class='block-primary'>悠遊卡</span> "
                                + "<span class='block-warning'>信用卡</span> "
                                + "<span class='block-danger'>行動支付</span> "
                                + "<span class='block-dark'>其他方式</span> "
                                + "</div>";
                        html += "<div class='text-success'>" + jsonObj.message + "</div>";
                        for (let i = 0; i < jsonObj.data_count; i++) {
                            html += "<a href='javascript:void(0)' class='float-left mr-2 mb-2 expaa_a_aa04 "
                                + getAA04DisplayCss(jsonObj.raw[i])
                                + " "
                                + (jsonObj.raw[i]["AA09"] == 1 ? "text-secondary" : "text-danger font-weight-bold")
                                + " ' title='"
                                + getExpaaTooltip(jsonObj.raw[i])
                                + "'>"
                                + jsonObj.raw[i]["AA04"]
                                + "</a>";
                        }
                        showModal({
                            body: html,
                            title: "搜尋規費列表",
                            size: "lg",
                            callback: () => {
                                $("a.expaa_a_aa04").off("click").on("click", e => {
                                    let pc_num = $(e.target).text();
                                    $("#expaa_query_number").val(pc_num);
                                    $("#expac_query_number").val(pc_num);
                                    xhrGetExpaaData.call(null, [e]);
                                    xhrGetExpacItems.call(null, [e]);
                                    closeModal();
                                });
                            }
                        });
                    } else {
                        showAlert({message: `找不到規費資料【日期：${txt}, 電腦給號：${number}】`, type: "warning"});
                    }
                    toggle("[id*=expaa_query_]");
                }).catch(ex => {
                    console.error("xhrGetExpaaData parsing failed", ex);
                    showAlert({message: ex.toString(), type: "danger"});
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
                    var now = new Date();
                    let last_pc_number = jsonObj.raw ? jsonObj.raw[0]["AA04"] : 0;
                    let today = (now.getFullYear() - 1911) +
                        ("0" + (now.getMonth() + 1)).slice(-2) +
                        ("0" + now.getDate()).slice(-2);

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
            else if (this.number < 1) this.number = 1;
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
            "expaa-list-mgt": {
                template: `<div id="expaa-list-container">
                </div>`,
                props: ["raw_data"],
                data: () => {
                    return {
                        raw_data: []
                    }
                },
                computed: {
                    count_cash: function() {},
                    count_ezcard: function() {},
                    count_mobile: function() {},
                    count_other: function() {},
                    count_credit: function() {}
                },
                methods: {},
                created: function () {},
                mounted: function () {}
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

                        if (isEmpty(operator) || isEmpty(fee_number) || isEmpty(reason)) {
                            addNotification({
                                title: "作廢資料",
                                message: "需求欄位有問題，請檢查！",
                                type: "danger"
                            });
                            addAnimatedCSS("#obsolete_container input", { name: "tada" });
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
