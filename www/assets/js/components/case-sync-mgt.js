if (Vue) {
    Vue.component("case-sync-mgt", {
        template: `<fieldset id="case-sync-mgt-fieldset">
            <legend>同步案件</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="check" type="sync" prefix="case_sync"></case-input-group-ui>
                </b-col>
            </b-form-row>
            <b-form-row>
                <b-col>
                    <b-button block pill @click="check" variant="outline-primary" size="sm"><i class="fas fa-sync"></i> 比對</b-button>
                </b-col>
                <b-col>
                    <b-button block pill @click="popup" variant="outline-success" size="sm"><i class="fas fa-question"></i> 功能說明</b-button>
                </b-col>
            </b-form-row>
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
                if (this.empty(year)) {
                    addNotification({
                        title: "查詢遠端案件資料",
                        subtitle: `${year}-${code}-${num}`,
                        message: "請重新選擇「年」欄位!",
                        type: "warning"
                    });
                    return false;
                }
                if (this.empty(code)) {
                    addNotification({
                        title: "查詢遠端案件資料",
                        subtitle: `${year}-${code}-${num}`,
                        message: "請重新選擇「字」欄位!",
                        type: "warning"
                    });
                    return false;
                }
                let number = num.replace(/\D/g, "");
                let offset = 6 - number.length;
                if (this.empty(number) || isNaN(number) || offset < 0) {
                    addNotification({
                        title: "查詢遠端案件資料",
                        subtitle: `${year}-${code}-${number}`,
                        message: `「號」格式有問題，請查明修正【目前：${num}】`,
                        type: "danger"
                    });
                    return false;
                }
                return true;
            },
            check: function(e) {
                if (!this.validate()) {
                    showAlert({message: `輸入資料格式有誤，無法查詢 ${this.year}-${this.code}-${this.num}`, type: "warning"});
                    return false;
                }
                
                let year = this.year;
                let code = this.code;
                let number = this.num;
                
                // toggle button disable attr
                this.isBusy = true;
            
                let offset = 6 - number.length;
                if (offset > 0) {
                    // padding leading zero for the number
                    number = number.padStart(6, "0");
                }

                // prepare post params
                let id = trim(year + code + number);
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "diff_xcase",
                    id: id
                }).then(res => {
                    let html = "<div>案件詳情：<a href='javascript:void(0)' id='sync_x_case_serial'>" + year + "-" + code + "-" + number + "</a><div>";
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        html += "<i class='fas fa-circle text-warning'></i>&ensp;請參考下列資訊： <button id='sync_x_case_confirm_button' class='btn btn-sm btn-success' title='同步全部欄位'>同步</button>";
                        html += "<table class='table table-hover text-center mt-1'>";
                        html += "<tr><th>欄位名稱</th><th>欄位代碼</th><th>局端</th><th>本所</th><th>單欄同步</th></tr>";
                        for (let key in res.data.raw) {
                            html += "<tr>";
                            html += "<td>" + res.data.raw[key]["TEXT"] + "</td>";
                            html += "<td>" + res.data.raw[key]["COLUMN"] + "</td>";
                            html += "<td class='text-danger'>" + res.data.raw[key]["REMOTE"] + "</td>";
                            html += "<td class='text-info'>" + res.data.raw[key]["LOCAL"] + "</td>";
                            html += "<td><button id='sync_column_" + res.data.raw[key]["COLUMN"] + "' data-column='" + res.data.raw[key]["COLUMN"] + "' class='btn btn-sm btn-outline-dark sync_column_button'>同步" + res.data.raw[key]["COLUMN"] + "</button></td>";
                            html += "</tr>";
                        };
                        html += "</table>";
                        showModal({
                            title: "案件比對詳情",
                            body: html,
                            callback: () => {
                                $("#sync_x_case_confirm_button").off("click").on("click", this.syncWholeCase.bind(this, id));
                                let that = this;
                                $(".sync_column_button").off("click").each((idx, element) => {
                                    let column = $(element).data("column");
                                    $(element).on("click", that.syncCaseColumn.bind(that, id, column));
                                });
                                $("#sync_x_case_serial").off("click").on("click", function(e) {
                                    window.vueApp.fetchRegCase(e, true)
                                });
                            },
                            size: "lg"
                        });
                    } else if (res.data.status == XHR_STATUS_CODE.FAIL_WITH_LOCAL_NO_RECORD) {
                        showModal({
                            title: "本地端無資料",
                            body: `<div>
                                <i class='fas fa-circle text-warning'></i>&ensp;
                                ${res.data.message}
                                <button id='inst_x_case_confirm_button'>新增本地端資料</button>
                            </div>`,
                            callback: () => {
                                $("#sync_x_case_serial").off("click").on("click", window.vueApp.fetchRegCase);
                                $("#inst_x_case_confirm_button").off("click").on("click", this.instRemoteCase.bind(this, id));
                            },
                            size: "md"
                        });
                    } else if (res.data.status == XHR_STATUS_CODE.FAIL_WITH_REMOTE_NO_RECORD) {
                        html += "<div><i class='fas fa-circle text-secondary'></i>&ensp;" + res.data.message + "</div>";
                        addNotification({
                            title: "查詢遠端案件資料",
                            subtitle: `${year}-${code}-${number}`,
                            message: html,
                            type: "warning"
                        });
                    } else {
                        html += "<div><i class='fas fa-circle text-success'></i>&ensp;" + res.data.message + "</div>";
                        addNotification({
                            title: "查詢遠端案件資料",
                            subtitle: `${year}-${code}-${number}`,
                            message: html,
                            type: "success",
                            callback: () => $("#sync_x_case_serial").off("click").on("click", function(e) {
                                window.vueApp.fetchRegCase(e, true);
                            })
                        });
                    }
                }).catch(err => {
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
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            syncCaseColumn: function(id, column) {
                showConfirm(`確定要同步${column}？`, () => {
                    this.$assert(id != '' && id != undefined && id != null, "the remote case id should not be empty");
                    let td = $(`#sync_column_${column}`).parent();
                    $(`#sync_column_${column}`).remove();
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "sync_xcase_column",
                        id: id,
                        column: column
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            td.html("<span class='text-success'>" + column + " 同步成功！</span>");
                        } else {
                            td.html("<span class='text-danger'>" + res.data.message + "</span>");
                        }
                    }).catch(err => {
                        this.error = err;
                        td.html("<span class='text-danger'>" + err.message + "</span>");
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            syncWholeCase: function(id) {
                showConfirm(`同步局端資料至本所資料庫【${id}】？`, () => {
                    this.$assert(id != '' && id != undefined && id != null, "the remote case id should not be empty");
                    $("#sync_x_case_confirm_button").remove();
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "sync_xcase",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            addNotification({
                                title: "同步局端資料至本所資料庫",
                                subtitle: id,
                                message: "同步成功！",
                                type: "success"
                            });
                        } else {
                            showAlert({
                                message: res.data.message,
                                type: "danger"
                            });
                        }
                        closeModal();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            instRemoteCase: function(id) {
                showConfirm("確定要拉回局端資料新增於本所資料庫(CRSMS)？", () => {
                    this.$assert(id != '' && id != undefined && id != null, "the remote case id should not be empty");
                    $("#inst_x_case_confirm_button").remove();
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "inst_xcase",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            addNotification({
                                title: "新增遠端案件資料",
                                subtitle: id,
                                message: "新增成功",
                                type: "success"
                            });
                        } else {
                            addNotification({
                                title: "新增遠端案件資料",
                                subtitle: id,
                                message: res.data.message,
                                type: "danger"
                            });
                        }
                        closeModal();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
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
