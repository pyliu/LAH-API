if (Vue) {
    Vue.component("lah-case-sync-mgt", {
        template: `<b-card>
            <template #header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder">
                        <lah-fa-icon icon="sync-alt">
                            同步登記案件
                            <span v-if="validate">{{ID}}</span>
                            <lah-fa-icon v-else-if="!empty(ID)" icon="exclamation-circle" variant="danger" append action="damage" title="案件ID有問題">{{ID}}</lah-fa-icon>
                        </lah-fa-icon>
                    </h6>
                    <b-button-group>
                        <lah-button action="heartbeat" regular icon="window-maximize" no-border @click="syncStatus" variant="outline-primary" size="sm" title="檢視同步異動狀態"></lah-button>
                        <lah-button icon="question" no-border @click="popup" variant="outline-success" size="sm"></lah-button>
                    </b-button-group>
                </div>
            </template>
            <div class="d-flex">
                <lah-case-input-group-ui v-model="id" @enter="check" type="sync" prefix="case_sync"></lah-case-input-group-ui>
                <lah-button icon="sync" action="cycle" @click="check" variant="outline-primary" size="sm" class="ml-1" title="搜尋案件" :disabled="!validate"></lah-button>
            </div>
        </b-card>`,
        data: () => ({
            id: undefined
        }),
        computed: {
            ID() {
                if (this.empty(this.id)) return '';
                return this.id.substring(0, 3) + '-' + this.id.substring(3, 7) + '-' + this.id.substring(7).padStart(6, '0');
            },
            validate() {
                if (this.empty(this.id)) return null;
                let year = this.id.substring(0, 3);
                let code = this.id.substring(3, 7);
                let num = this.id.substring(7);
                let regex = /^[0-9]{3}$/i;
                if (!regex.test(year)) {
                    this.$warn("year format is not valid.", year, this.id);
                    return false;
                }
                regex = /^H[A-Z0-9]{3}$/i;
                if (!regex.test(code)) {
                    this.$warn("code format is not valid.", code, this.id);
                    return false;
                }
                let number = parseInt(num);
                if (isNaN(number) || !(number > 0 && number < 1000000) || num.length > 6) {
                    this.$warn(this.id, "number is not valid!");
                    return false;
                }
                return true;
            }
        },
        methods: {
            check: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "diff_xcase",
                        id: this.id
                    }).then(res => {
                        let html = "<div>案件詳情：<a href='javascript:void(0)' id='sync_x_case_serial'>" + this.ID + "</a><div>";
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            html += "<i class='fas fa-circle text-warning'></i>&ensp;請參考下列資訊： <button id='sync_x_case_confirm_button' class='btn btn-sm btn-success' title='同步全部欄位'>同步</button>";
                            html += "<table class='table table-hover b-table table-striped table-bordered table-sm text-center mt-1'>";
                            html += "<tr class='bg-dark text-white forn-weight-bold'><th>欄位名稱</th><th>欄位代碼</th><th>局端</th><th>本所</th><th>單欄同步</th></tr>";
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
                            this.msgbox({
                                title: `案件比對詳情 ${this.ID}`,
                                body: html,
                                callback: () => {
                                    $("#sync_x_case_confirm_button").off("click").on("click", this.syncWholeCase.bind(this, this.id));
                                    $(".sync_column_button").off("click").each((idx, element) => {
                                        let column = $(element).data("column");
                                        $(element).on("click", this.syncCaseColumn.bind(this, this.id, column));
                                    });
                                    $("#sync_x_case_serial").off("click").on("click", function(e) {
                                        window.vueApp.fetchRegCase(e)
                                    });
                                },
                                size: "lg"
                            });
                        } else if (res.data.status == XHR_STATUS_CODE.FAIL_WITH_LOCAL_NO_RECORD) {
                            this.msgbox({
                                title: `本地端無資料 ${this.ID}`,
                                body: `<div>
                                    <i class='fas fa-circle text-warning'></i>&ensp;
                                    ${res.data.message}
                                    <button id='inst_x_case_confirm_button'>新增本地端資料</button>
                                </div>`,
                                callback: () => {
                                    $("#sync_x_case_serial").off("click").on("click", window.vueApp.fetchRegCase);
                                    $("#inst_x_case_confirm_button").off("click").on("click", this.instRemoteCase.bind(this, this.id));
                                },
                                size: "md"
                            });
                        } else if (res.data.status == XHR_STATUS_CODE.FAIL_WITH_REMOTE_NO_RECORD) {
                            html += "<div><i class='fas fa-circle text-secondary'></i>&ensp;" + res.data.message + "</div>";
                            this.notify({
                                title: "查詢遠端案件資料",
                                subtitle: ` ${this.ID}`,
                                message: html,
                                type: "warning"
                            });
                        } else {
                            html += "<div><i class='fas fa-circle text-success'></i>&ensp;" + res.data.message + "</div>";
                            this.notify({
                                title: "查詢遠端案件資料",
                                subtitle: ` ${this.ID}`,
                                message: html,
                                type: "success",
                                callback: () => $("#sync_x_case_serial").off("click").on("click", function(e) {
                                    window.vueApp.fetchRegCase(e);
                                })
                            });
                        }
                    }).catch(err => {
                        // remove the fieldset since the function is not working ... 
                        let fieldset = $("#lah-case-sync-mgt-fieldset");
                        let container = fieldset.closest("div.col-6");
                        this.animated(fieldset, {
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
                } else {
                    this.alert({
                        title: '案件同步查詢',
                        message: `案件ID有問題，請檢查後再重試！ (${this.ID})`,
                        variant: 'warning'
                    });
                }
            },
            syncCaseColumn: function(id, column) {
                showConfirm(`確定要同步${column}？`, () => {
                    this.$assert(id != '' && id != undefined && id != null, "the remote case id should not be empty");
                    let td = $(`#sync_column_${column}`).parent();
                    $(`#sync_column_${column}`).remove();
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
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
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "sync_xcase",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            this.notify({
                                title: "同步局端資料至本所資料庫",
                                subtitle: id,
                                message: "同步成功！",
                                type: "success"
                            });
                        } else {
                            this.alert({
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
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "inst_xcase",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            this.notify({
                                title: "新增遠端案件資料",
                                subtitle: id,
                                message: "新增成功",
                                type: "success"
                            });
                        } else {
                            this.notify({
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
            popup: function() {
                this.msgbox({
                    title: "案件同步 小幫手提示",
                    body: `
                        <h6>將局端跨所資料同步回本所資料庫</h6>
                        <div><span class="text-danger">※</span>新版跨縣市回寫機制會在每一分鐘時自動回寫，故局端資料有可能會比較慢更新。【2019-06-26】</div>
                        <div><span class="text-danger">※</span>局端針對遠端連線同步異動資料庫有鎖IP，故<span class="text-danger">IP不在局端白名單內或不再集中化內的伺服器主機將無法使用本功能</span>。【2020-07-15】</div>
                    `,
                    size: "lg"
                });
            },
            syncStatus() {
                this.msgbox({
                    title: "L3HWEB同步異動狀態",
                    message: this.$createElement('lah-lxhweb-traffic-light', {
                        props: { type: 'full', maximized: true }
                    }),
                    size: "xl"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... lah-case-sync-mgt component can not be loaded.");
}
