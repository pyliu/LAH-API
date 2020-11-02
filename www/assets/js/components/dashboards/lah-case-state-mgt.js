if (Vue) {
    Vue.component("lah-case-state-mgt", {
        template: `<fieldset>
            <legend>
                <i class="far fa-folder"></i>
                登記案件狀態
                <lah-button icon="question" no-border  @click="popup" variant="outline-success" size="sm"></lah-button>
            </legend>
            <div class="d-flex">
                <lah-case-input-group-ui v-model="id" @enter="query" type="reg" prefix="case_state"></lah-case-input-group-ui>
                <b-button @click="query" variant="outline-primary" size="sm" class="ml-1" v-b-tooltip="'搜尋案件'"><i class="fas fa-search"></i></b-button>
            </div>
        </fieldset>`,
        data: () => ({
            dialog: null,
            id: undefined
        }),
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
                regex = /^[A-Z0-9]{4}$/i;
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
            query: function(e) {
                if (this.validate) {
                    // prepare post params
                    let id = this.id;
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "reg_case",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                            this.notify({
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
                            this.msgbox({
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
                } else {
                    this.alert({
                        title: '登記案件狀態',
                        message: `案件ID有問題，請檢查後再重試！ (${this.id})`,
                        variant: 'warning'
                    });
                }
            },
            popup: function() {
                this.msgbox({
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
    console.error("vue.js not ready ... lah-case-state-mgt component can not be loaded.");
}
