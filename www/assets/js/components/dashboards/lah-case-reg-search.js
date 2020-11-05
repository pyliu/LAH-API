if (Vue) {
    Vue.component("lah-case-reg-search", {
        template: `<b-card>
            <template #header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder">
                        <lah-fa-icon icon="scroll">
                            登記案件查詢
                            <span v-if="validate">{{ID}}</span>
                            <lah-fa-icon v-else-if="!empty(ID)" icon="exclamation-circle" variant="danger" append action="damage" title="案件ID有問題">{{ID}}</lah-fa-icon>
                        </lah-fa-icon>
                    </h6>
                    <lah-button icon="question" @click="help" size="sm" variant="outline-success" no-border></lah-button>
                </div>
            </template>
            <div class="d-flex">
                <lah-case-input-group-ui v-model="id" @enter="regQuery" type="reg" prefix="case_reg"></lah-case-input-group-ui>
                <lah-button icon="briefcase" @click="regQuery" variant="outline-primary" size="sm" v-b-tooltip="'查登記案件'" class="mx-1" :disabled="!validate"></lah-button>
                <lah-button icon="hand-holding-usd" @click="prcQuery" variant="outline-secondary" size="sm" v-b-tooltip="'查地價案件'" :disabled="!validate"></lah-button>
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
                    this.$warn(this.id, "year format is not valid.");
                    return false;
                }
                regex = /^[A-Z0-9]{4}$/i;
                let regex_4num = /^[0-9]{4}$/i;
                if (regex_4num.test(code) || !regex.test(code)) {
                    this.$warn(this.id, "code format is not valid.");
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
            help() {
                this.msgbox({
                    title: "登記案件查詢說明",
                    body: `<div>請輸入案件收件年、字、號查詢。</div>`,
                    size: "md"
                });
            },
            regQuery: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "reg_case",
                        id: this.id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                            this.notify({
                                title: "顯示登記案件詳情",
                                subtitle: this.ID,
                                message: res.data.message,
                                type: "warning"
                            });
                        } else {
                            this.msgbox({
                                message: this.$createElement("lah-reg-case-detail", {
                                    props: {
                                        bakedData: res.data.baked
                                    }
                                }),
                                title: `登記案件詳情 ${this.ID}`,
                                size: "lg"
                            });
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                } else {
                    this.alert({
                        title: '登記案件搜尋',
                        message: `案件ID有問題，請檢查後再重試！ (${this.ID})`,
                        variant: 'warning'
                    });
                }
            },
            prcQuery: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "prc_case",
                        id: this.id
                    }).then(res => {
                        this.showPrcCaseDetail(res.data);
                        this.isBusy = false;
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                } else {
                    this.alert({
                        title: '地價案件狀態查詢',
                        message: `案件ID有問題，請檢查後再重試！ (${this.id})`,
                        variant: 'warning'
                    });
                }
            },
            showPrcCaseDetail(jsonObj) {
                if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    this.notify({
                        title: this.id,
                        message: "查無地價案件資料",
                        type: "warning"
                    });
                } else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                    throw new Error("查詢失敗：" + jsonObj.message);
                } else {
                    let html = "<p>" + jsonObj.html + "</p>";
                    let modal_size = "lg";
                    this.msgbox({
                        body: html,
                        title: "地價案件詳情",
                        size: modal_size,
                        callback: () => { $(".prc_case_serial").off("click").on("click", window.vueApp.fetchRegCase); }
                    });
                }
            }
        },
        components: {}
    });
} else {
    console.error("vue.js not ready ... lah-case-reg-search component can not be loaded.");
}
