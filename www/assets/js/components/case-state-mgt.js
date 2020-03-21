if (Vue) {
    Vue.component("case-state-mgt", {
        template: `<fieldset>
            <legend>案件狀態</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="query" type="reg" prefix="case_state"></case-input-group-ui>
                </b-col>
            </b-form-row>
            <b-form-row>
                <b-col>
                    <b-button block pill @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                </b-col>
                <b-col>
                    <b-button block pill  @click="popup" variant="outline-success" size="sm"><i class="fas fa-question"></i> 功能說明</b-button>
                </b-col>
            </b-form-row>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB04",
                num: "000010",
                dialog: null
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
                if (!window.vueApp.checkCaseUIData(data)) {
                    addNotification({
                        title: "案件查詢",
                        subtitle: `${data.year}-${data.code}-${data.num}`,
                        message: `輸入資料格式有誤，無法查詢。`,
                        type: "warning"});
                    return false;
                }

                // prepare post params
                let id = trim(`${this.year}${this.code}${this.num}`);
                
                this.isBusy = true;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            title: "案件查詢",
                            subtitle: id,
                            message: res.data.message,
                            type: "warning"
                        });
                    } else if (res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        throw new Error("查詢失敗：" + res.data.message);
                    } else {
                        // create sub-component dynamically
                        let v = this.$createElement("lah-reg-case-state-mgt", {
                            props: {
                                bakedData: res.data.baked,
                                progress: true
                            }
                        })
                        showModal({
                            title: "調整登記案件欄位資料",
                            body: v,
                            size: "md"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            popup: () => {
                showModal({
                    title: "調整登記案件欄位資料 小幫手提示",
                    body: `<ul>
                        <li>使用情境1：先行准登後案件須回復至公告</li>
                        <li>使用情境2：案件卡住需退回初審</li>
                        <li>使用情境3：案件辦理情形與登記處理註記不同步造成地價課無法登錄收件卡住</li>
                    </ul>`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-state-mgt component can not be loaded.");
}
