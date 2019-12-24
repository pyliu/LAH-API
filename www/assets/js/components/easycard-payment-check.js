if (Vue) {
    Vue.component("easycard-payment-check", {
        template: `<fieldset>
            <legend>悠遊卡自動加值付款失敗回復</legend>
            <div class="form-row">
                <div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-easycard_query_day">日期</span>
                    </div>
                    <input @keyup.enter="query" type="text" id="easycard_query_day" name="easycard_query_day" class="form-control easycard_query date_picker no-cache" placeholder="1081217"  data-trigger="manual" data-toggle="popover" data-content="e.g. 1081217" data-placement="bottom" v-model="date" />
                </div>
                <div class="filter-btn-group col">
                    <button id="easycard_query_button" class="btn btn-sm btn-outline-primary easycard_query" @click="query">查詢</button>
                    <button id="easycard_quote_button" class="btn btn-sm btn-outline-success" @click="popup">備註</button>
                </div>
            </div>
        </fieldset>`,
        data: () => {
            return {
                date: null
            }
        },
        methods: {
            query: function(e) {
                // basic checking for tw date input
                let regex = /^\d{7}$/;
                let txt = $("#easycard_query_day").val();
                if (!isEmpty(txt) && txt.match(regex) == null) {
                    showPopper("#easycard_query_day");
                    return;
                }

                toggle(".easycard_query");

                let body = new FormData();
                body.append("type", "easycard");
                body.append("qday", txt);

                const h = this.$createElement;
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            message: jsonObj.message,
                            successSpinner: true
                        });
                    } else {
                        let vnode = h("easycard-payment-check-item", {
                            props: { data: jsonObj.raw }
                        });
                        showModal({
                            title: "<i class='fas fa-circle text-warning'></i>&ensp;<strong class='text-danger'>找到下列資料</strong>",
                            body: vnode,
                            size: "md"
                        });
                    }
                    toggle(".easycard_query");
                }).catch(ex => {
                    console.error("easycard-payment-check::query parsing failed", ex);
                    showAlert({message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                });
            },
            popup: () => {
                showModal({
                    title: "悠遊卡自動加值付款失敗回復 小幫手提示",
                    body: `
                        <ol>
                            <li>櫃台來電通知悠遊卡扣款成功但地政系統卻顯示扣款失敗，需跟櫃台要【電腦給號】</li>
                            <li>管理師處理方法：AA106為'2' OR '8'將AA106更正為'1'即可【AA01:事發日期、AA04:電腦給號】。<br />
                            UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01='1070720' AND AA04='0043405'
                            </li>
                        </ol>
                        <img src="assets/img/easycard_screenshot.jpg" class="img-responsive img-thumbnail" />
                    `,
                    size: "lg"
                });
            }
        },
        components: {
            "easycard-payment-check-item": {
                template: `<ul style="font-size: 0.9rem">
                    <li v-for="(item, index) in data" class='easycard_item'>
                        日期: {{item["AA01"]}}, 電腦給號: {{item["AA04"]}}, 實收金額: {{item["AA28"]}}<b-badge v-if="!isEmpty(item['AA104'])" variant="danger">, 作廢原因: {{item["AA104"]}}</b-badge>, 目前狀態: {{status(item["AA106"])}}
                        <button :id="'fix_ez_btn'+index" v-if="isEmpty(item['AA104'])" @click="fix(item, index)" class="btn btn-sm btn-outline-success">修正</button>
                    </li>
                </ul>`,
                props: ["data"],
                methods: {
                    fix: function(item, index) {
                        let el = $("#fix_ez_btn"+index);
                        let qday = item["AA01"], pc_number = item["AA04"], amount = item["AA28"];
                        let message = "確定要修正 日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料?";
                        showConfirm(message, () => {
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
                                    el.closest("li").html("修正 日期: " + qday + ", 電腦給號: " + pc_number + " <strong class='text-success'>成功</strong>!");
                                } else {
                                    throw new Error("回傳狀態碼不正確!【" + jsonObj.message + "】");
                                }
                                el.remove();
                            }).catch(ex => {
                                console.error("easycard-payment-check-item::fix parsing failed", ex);
                                showAlert({message: `easycard-payment-check-item::fix parsing failed. ${ex.toString()}`, type: "danger"});
                            });
                        });
                    },
                    status: function(AA106) {
                        let status = "未知的狀態碼【" + AA106 + "】";
                        /*
                            1：扣款成功
                            2：扣款失敗
                            3：取消扣款
                            8：扣款異常交易
                            9：取消扣款異常交易
                        */
                        switch(AA106) {
                            case "1":
                                status = "扣款成功";
                                break;
                            case "2":
                                status = "扣款失敗";
                                break;
                            case "3":
                                status = "取消扣款";
                                break;
                            case "8":
                                status = "扣款異常交易";
                                break;
                            case "9":
                                status = "取消扣款異常交易";
                                break;
                            default:
                                break;
                        }
                        return status;
                    }
                }
            }
        },
        mounted: function() {
            var d = new Date();
            this.date = (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
            if ($("#easycard_query_day").datepicker) {
                $("#easycard_query_day").datepicker({
                    daysOfWeekDisabled: "",
                    language: "zh-TW",
                    daysOfWeekHighlighted: "1,2,3,4,5",
                    //todayBtn: true,
                    todayHighlight: true,
                    autoclose: true,
                    format: {
                        /*
                        * Say our UI should display a week ahead,
                        * but textbox should store the actual date.
                        * This is useful if we need UI to select local dates,
                        * but store in UTC
                        */
                        toDisplay: function (date, format, language) {
                        var d = new Date(date);
                        return (d.getFullYear() - 1911)
                                + ("0" + (d.getMonth()+1)).slice(-2)
                                + ("0" + d.getDate()).slice(-2);
                        },
                        toValue: function (date, format, language) {
                            // initialize to now
                            return new Date();
                        }
                    }
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... easycard-payment-check component can not be loaded.");
}
