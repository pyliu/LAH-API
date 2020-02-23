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
                num: "000100",
                busy: false
            }
        },
        watch: {
            busy: function(flag) { flag ? vueApp.busyOn(this.$el) : vueApp.busyOff(this.$el); }
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
                let year = this.year;
                let code = this.code;
                let number = this.num;
                
                // prepare post params
                let id = trim(year + code + number);
                
                this.busy = true;
            
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    window.vueApp.showRegCase(res.data, true);
                    this.busy = false;
                }).catch(err => {
                    console.error("case-reg-search::regQuery parsing failed", err);
                    showAlert({
                        title: "查詢登記案件",
                        subtitle: id,
                        message: err.message,
                        type: "danger"
                    });
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
                let year = this.year;
                let code = this.code;
                let number = this.num;
                // prepare post params
                let id = trim(year + code + number);
                
                this.busy = true;
            
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "prc_case",
                    id: id
                }).then(res => {
                    showPrcCaseDetail(res.data);
                    this.busy = false;
                }).catch(err => {
                    console.error("case-reg-search::prcQuery parsing failed", err);
                    showAlert({
                        title: "查詢地價案件",
                        subtitle: id,
                        message: err.message,
                        type: "danger"
                    });
                });
            }
        },
        components: {}
    });
} else {
    console.error("vue.js not ready ... case-reg-search component can not be loaded.");
}
