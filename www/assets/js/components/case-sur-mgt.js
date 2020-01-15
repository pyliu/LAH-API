if (Vue) {
    Vue.component("case-sur-mgt", {
        template: `<fieldset>
            <legend>複丈案件查詢(修正已結延期、修改連件數)</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="query" type="sur" prefix="case_sur"></case-input-group-ui>
                </b-col>
            </b-form-row>
            <b-form-row>
                <b-col>
                    <b-button block pill @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                </b-col>
                <b-col>
                    <b-button block pill  @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
                </b-col>
            </b-form-row>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB12",
                num: "000100"
            }
        },
        methods: {
            handleUpdate: function(e, data) {
                this.year = data.year;
                this.code = data.code;
                this.num = data.num;
            },
            query: function(e) {
                let data = {year: this.year, code: this.code, num: this.num};
                if (!checkCaseUIData(data)) {
                    addNotification({
                        title: "案件查詢",
                        subtitle: `${data.year}-${data.code}-${data.num}`,
                        message: `輸入資料格式有誤，無法查詢。`,
                        type: "warning"});
                    return false;
                }
                let year = this.year;
                let code = this.code;
                let number = this.num;
                // prepend "0"
                var offset = 6 - number.length;
                for (var i = 0; i < offset; i++) {
                    number = "0" + number;
                }
                // prepare post params
                let id = trim(year + code + number);
                let body = new FormData();
                body.append("type", "sur_case");
                body.append("id", id);
                
                toggle(e.target);
            
                asyncFetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL && jsonObj.data_count == 0) {
                        addNotification({
                            title: "測量案件查詢",
                            subtitle: `${year}-${code}-${number}`,
                            message: "查無資料",
                            type: "warning"
                        });
                    } else {
                        this.dialog(jsonObj);
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("case-sur-mgt::query parsing failed", ex);
                    showAlert({
                        title: "查詢測量案件",
                        message: "<strong class='text-danger'>無法取得 " + id + " 資訊!【" + ex + "】</strong>",
                        type: "danger"
                    });
                });
            },
            dialog: (jsonObj) => {
                if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    let html = "收件字號：" + "<a title='案件辦理情形 on " + landhb_svr + "' href='#' onclick='javascript:window.open(\"http://\"\+landhb_svr\+\":9080/LandHB/Dispatcher?REQ=CMC0202&GRP=CAS&MM01="+ jsonObj.raw["MM01"] +"&MM02="+ jsonObj.raw["MM02"] +"&MM03="+ jsonObj.raw["MM03"] +"&RM90=\")'>" + jsonObj.收件字號 + "</a> </br>";
                    html += "收件時間：" + jsonObj.收件時間 + " <br/>";
                    html += "收件人員：" + jsonObj.收件人員 + " <br/>";
                    html += "　連件數：<input type='text' id='mm24_upd_text' value='" + jsonObj.raw["MM24"] + "' /> <button id='mm24_upd_btn' data-table='SCMSMS' data-case-id='" + jsonObj.收件字號.replace(/[^a-zA-Z0-9]/g, "") + "' data-origin-value='" + jsonObj.raw["MM24"] + "' data-column='MM24' data-input-id='mm24_upd_text' data-title=' " + jsonObj.raw["MM01"] + "-" + jsonObj.raw["MM02"] + "-" + jsonObj.raw["MM03"] + " 連件數'>更新</button><br/>";
                    html += "申請事由：" + jsonObj.raw["MM06"] + "：" + jsonObj.申請事由 + " <br/>";
                    html += "　段小段：" + jsonObj.raw["MM08"] + " <br/>";
                    html += "　　地號：" + (isEmpty(jsonObj.raw["MM09"]) ? "" : jsonObj.地號) + " <br/>";
                    html += "　　建號：" + (isEmpty(jsonObj.raw["MM10"]) ? "" : jsonObj.建號) + " <br/>";
                    html += "<span class='text-info'>辦理情形</span>：" + jsonObj.辦理情形 + " <br/>";
                    html += "結案狀態：" + jsonObj.結案狀態 + " <br/>";
                    html += "<span class='text-info'>延期原因</span>：" + jsonObj.延期原因 + " <br/>";
                    html += "<span class='text-info'>延期時間</span>：" + jsonObj.延期時間 + " <br/>";
                    if (jsonObj.結案已否 && jsonObj.raw["MM22"] == "C") {
                        html += '<h6 class="mt-2 mb-2"><span class="text-danger">※</span> ' + "發現 " + jsonObj.收件字號 + " 已「結案」但辦理情形為「延期複丈」!" + '</h6>';
                        html += "<button id='sur_delay_case_fix_button' class='text-danger' data-trigger='manual' data-toggle='popover' data-content='需勾選右邊其中一個選項才能進行修正' title='錯誤訊息' data-placement='top'>修正</button> ";
                        html += "<label for='sur_delay_case_fix_set_D'><input id='sur_delay_case_fix_set_D' type='checkbox' checked /> 辦理情形改為核定</label> ";
                        html += "<label for='sur_delay_case_fix_clear_delay_datetime'><input id='sur_delay_case_fix_clear_delay_datetime' type='checkbox' checked /> 清除延期時間</label> ";
                    }
                    showModal({
                        title: "測量案件查詢",
                        body: html,
                        size: "md",
                        callback: function() {
                            $("#sur_delay_case_fix_button").off("click").one("click", xhrFixSurDelayCase.bind(jsonObj.收件字號));
                            $("#mm24_upd_btn").off("click").one("click", e => {
                                // input validation
                                let number = $("#mm24_upd_text").val().replace(/\D/g, "");
                                $("#mm24_upd_text").val(number);
                                xhrUpdateCaseColumnData(e);
                            });
                            addUserInfoEvent();
                        }
                    });
                } else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                    throw new Error("查詢失敗：" + jsonObj.message);
                }
            },
            popup: () => {
                showModal({
                    title: "測量案件資料 小幫手提示",
                    body: `<h5><span class="text-danger">※</span>注意：本功能會清除如下圖之欄位資料並將案件辦理情形改為【核定】，請確認後再執行。</h5>
                    <img src="assets/howto/107-HB18-3490_測丈已結案案件辦理情形出現(逾期)延期複丈問題調整【參考】.jpg" />
                    <h5><span class="text-danger">※</span> 問題原因說明</h5>
                    <div>原因是 CMB0301 延期複丈功能，針對於有連件案件在做處理時，會自動根據MM24案件數，將後面的案件自動做延期複丈的更新。導致後續已結案的案件會被改成延期複丈的狀態 MM22='C' 就是 100、200、300、400為四連件，所以100的案件 MM24='4'，200、300、400 的 MM24='0' 延期複丈的問題再將100號做延期複丈的時候，會將200、300、400也做延期複丈的更新，所以如果400已經結案，100做延期複丈，那400號就會變成 MM22='C' MM23='A' MM24='4' 的異常狀態。</div>`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-sur-mgt component can not be loaded.");
}
