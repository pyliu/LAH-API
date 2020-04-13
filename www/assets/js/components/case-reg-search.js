if (Vue) {
    Vue.component("case-reg-search", {
        template: `<fieldset>
            <legend>登記案件查詢</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui v-model="id" @enter="regQuery" type="reg" prefix="case_reg"></case-input-group-ui>
                </b-col>
            </b-form-row>
            <b-form-row>
                <b-col>
                    <b-button block pill @click="regQuery" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 登記</b-button>
                </b-col>
                <b-col>
                    <b-button block pill  @click="prcQuery" variant="outline-secondary" size="sm"><i class="far fa-comment"></i> 地價</b-button>
                </b-col>
            </b-form-row>
        </fieldset>`,
        data: () => {
            return {
                id: undefined
            }
        },
        computed: {
            validate() {
                let year = this.id.substring(0, 3);
                let code = this.id.substring(3, 7);
                let num = this.id.substring(7);
                let regex = /^[0-9]{3}$/i;
                if (!regex.test(year)) {
                    this.$warn(this.id, "year format is not valid.");
                    return false;
                }
                regex = /^H[A-Z0-9]{3}$/i;
                if (!regex.test(code)) {
                    this.$warn(this.id, "code format is not valid.");
                    return false;
                }
                let number = parseInt(num);
                if (this.empty(number) || isNaN(number)) {
                    this.$warn(this.id, "number is empty or NaN!");
                    return false;
                }
                return true;
            }
        },
        methods: {
            regQuery: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "reg_case",
                        id: this.id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                            showAlert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                            return;
                        } else {
                            showModal({
                                message: this.$createElement("lah-reg-case-detail", {
                                    props: {
                                        bakedData: res.data.baked
                                    }
                                }),
                                title: `登記案件詳情 ${this.id}`,
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
                        message: `案件ID有問題，請檢查後再重試！ (${this.id})`,
                        variant: 'warning'
                    });
                }
            },
            prcQuery: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
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
                    showAlert({
                        message: "查無地價案件資料",
                        type: "warning"
                    });
                    return;
                } else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                    throw new Error("查詢失敗：" + jsonObj.message);
                }
                let html = "<p>" + jsonObj.html + "</p>";
                let modal_size = "lg";
                showModal({
                    body: html,
                    title: "地價案件詳情",
                    size: modal_size,
                    callback: () => { $(".prc_case_serial").off("click").on("click", window.vueApp.fetchRegCase); }
                });
            }
        },
        components: {}
    });
} else {
    console.error("vue.js not ready ... case-reg-search component can not be loaded.");
}
