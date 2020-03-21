if (Vue) {
    Vue.component("case-reg-search", {
        template: `<fieldset>
            <legend>登記案件查詢</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="regQuery" type="reg" prefix="case_reg"></case-input-group-ui>
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
            regQuery: function(e) {
                let data = {year: this.year, code: this.code, num: this.num};
                if (!window.vueApp.checkCaseUIData(data)) {
                    addNotification({
                        title: "登記案件查詢",
                        subtitle: `${data.year}-${data.code}-${data.num}`,
                        message: `輸入資料格式有誤，無法查詢。`,
                        type: "warning"});
                    return false;
                }

                this.isBusy = true;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: trim(`${this.year}${this.code}${this.num}`)
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        showAlert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                        return;
                    } else {
                        showModal({
                            message: this.$createElement("case-reg-detail", {
                                props: {
                                    jsonObj: res.data
                                }
                            }),
                            title: "登記案件詳情",
                            size: "lg"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            prcQuery: function(e) {
                let data = {year: this.year, code: this.code, num: this.num};
                if (!window.vueApp.checkCaseUIData(data)) {
                    addNotification({
                        title: "地價案件查詢",
                        subtitle: `${data.year}-${data.code}-${data.num}`,
                        message: `輸入資料格式有誤，無法查詢。`,
                        type: "warning"});
                    return false;
                }
                this.isBusy = true;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "prc_case",
                    id: trim(`${this.year}${this.code}${this.num}`)
                }).then(res => {
                    showPrcCaseDetail(res.data);
                    this.isBusy = false;
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        },
        components: {}
    });
} else {
    console.error("vue.js not ready ... case-reg-search component can not be loaded.");
}
