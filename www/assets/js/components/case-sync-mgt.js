if (Vue) {
    Vue.component("case-sync-mgt", {
        template: `<fieldset id="case-sync-mgt-fieldset">
            <legend>同步跨所案件資料</legend>
            <div class="form-row">
            <div class="col-9">
                <case-input-group-ui @update="handleUpdate" @enter="check" type="sync" prefix="case_sync"></case-input-group-ui>
            </div>
            <div class="filter-btn-group col-3">
                <button @click="check" class="btn btn-sm btn-outline-primary">比對</button>
                <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
            </div>
            </div>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB04",
                num: "000010"
            }
        },
        methods: {
            handleUpdate: function(e, data) {
                this.year = data.year;
                this.code = data.code;
                this.num = data.num;
            },
            validate: function() {
                let year = this.year;
                let code = this.code;
                let num = this.num;
                if (isEmpty(year)) {
                    showAlert({
                        message: "請重新選擇「年」欄位!",
                        type: "danger"
                    });
                    return false;
                }
                if (isEmpty(code)) {
                    showAlert({
                        message: "請重新選擇「字」欄位!",
                        type: "danger"
                    });
                    return false;
                }
                let number = num.replace(/\D/g, "");
                let offset = 6 - number.length;
                if (isEmpty(number) || isNaN(number) || offset < 0) {
                    showAlert({
                        message: `「號」格式有問題，請查明修正【目前：${num}】`,
                        type: "danger"
                    });
                    return false;
                }
                return true;
            },
            check: function(e) {
                if (!this.validate()) {
                    addNotification({message: `輸入資料格式有誤，無法查詢 ${this.year}-${this.code}-${this.num}`, type: "warning"});
                    return false;
                }
                
                let year = this.year;
                let code = this.code;
                let number = this.num;
                
                // toggle button disable attr
                toggle(e.target);
            
                // prepare post params
                let id = trim(year + code + number);
                let body = new FormData();
                body.append("type", "diff_xcase");
                body.append("id", id);
            
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
                }).then(jsonObj => {
                    let html = "<div>案件詳情：<a href='javascript:void(0)' id='sync_x_case_serial'>" + year + "-" + code + "-" + number + "</a><div>";
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        html += "<i class='fas fa-circle text-warning'></i>&ensp;請參考下列資訊： <button id='sync_x_case_confirm_button' class='btn btn-sm btn-success' title='同步全部欄位'>同步</button>";
                        html += "<table class='table table-hover text-center mt-1'>";
                        html += "<tr><th>欄位名稱</th><th>欄位代碼</th><th>局端</th><th>本所</th><th>單欄同步</th></tr>";
                        for (let key in jsonObj.raw) {
                            html += "<tr>";
                            html += "<td>" + jsonObj.raw[key]["TEXT"] + "</td>";
                            html += "<td>" + jsonObj.raw[key]["COLUMN"] + "</td>";
                            html += "<td class='text-danger'>" + jsonObj.raw[key]["REMOTE"] + "</td>";
                            html += "<td class='text-info'>" + jsonObj.raw[key]["LOCAL"] + "</td>";
                            html += "<td><button data-column='" + jsonObj.raw[key]["COLUMN"] + "' class='btn btn-sm btn-outline-dark sync_column_button'>同步" + jsonObj.raw[key]["COLUMN"] + "</button></td>";
                            html += "</tr>";
                        };
                        html += "</table>";
                        showModal({
                            title: "案件比對詳情",
                            body: html,
                            callback: () => {
                                $("#sync_x_case_confirm_button").off("click").on("click", xhrSyncXCase.bind(id));
                                $(".sync_column_button").off("click").on("click", xhrSyncXCaseColumn.bind(id));
                                $("#inst_x_case_confirm_button").off("click").on("click", xhrInsertXCase.bind(id));
                                $("#sync_x_case_serial").off("click").on("click", xhrRegQueryCaseDialog);
                            },
                            size: "lg"
                        });
                    } else if (jsonObj.status == XHR_STATUS_CODE.FAIL_WITH_LOCAL_NO_RECORD) {
                        showModal({
                            title: "本地端無資料",
                            body: `<div>
                                <i class='fas fa-circle text-warning'></i>&ensp;
                                ${jsonObj.message}
                                <button id='inst_x_case_confirm_button'>新增本地端資料</button>
                            </div>`,
                            callback: () => {
                                $("#sync_x_case_serial").off("click").on("click", xhrRegQueryCaseDialog);
                                $("#inst_x_case_confirm_button").off("click").on("click", xhrInsertXCase.bind(id));
                            },
                            size: "md"
                        });
                    } else if (jsonObj.status == XHR_STATUS_CODE.FAIL_WITH_REMOTE_NO_RECORD) {
                        html += "<div><i class='fas fa-circle text-secondary'></i>&ensp;" + jsonObj.message + "</div>";
                        showAlert({
                            message: html,
                            type: "warning"
                        });
                    } else {
                        html += "<div><i class='fas fa-circle text-success'></i>&ensp;" + jsonObj.message + "</div>";
                        addNotification({
                            message: html,
                            type: "success",
                            callback: () => $("#sync_x_case_serial").off("click").on("click", xhrRegQueryCaseDialog)
                        });
                    }
                    toggle(e.target);
                }).catch(ex => {
                    // remove the fieldset since the function is not working ... 
                    let fieldset = $("#case-sync-mgt-fieldset");
                    let container = fieldset.closest("div.col-6");
                    addAnimatedCSS(fieldset, {
                        name: ANIMATED_TRANSITIONS[rand(ANIMATED_TRANSITIONS.length)].out,
                        callback: () => {
                            fieldset.remove();
                            container.append(jQuery.parseHTML('<i class="ld ld-breath fas fa-ban text-danger fa-3x"></i>')).addClass("my-auto text-center");
                        }
                    });
                    console.error("case-sync-mgt::check parsing failed", ex);
                    showAlert({
                        message: ex.toString(),
                        type: "danger"
                    });
                });
            },
            popup: () => {
                showModal({
                    title: "案件暫存檔清除 小幫手提示",
                    body: `
                        <h6>將局端跨所資料同步回本所資料庫</h6>
                        <div><span class="text-danger">※</span>新版跨縣市回寫機制會在每一分鐘時自動回寫，故局端資料有可能會比較慢更新。【2019-06-26】</div>
                        <div><span class="text-danger">※</span>局端針對遠端連線同步異動資料庫有鎖IP，故<span class="text-danger">IP不在局端白名單內的主機將無法使用本功能</span>。【2019-10-01】</div>
                    `,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-sync-mgt component can not be loaded.");
}
