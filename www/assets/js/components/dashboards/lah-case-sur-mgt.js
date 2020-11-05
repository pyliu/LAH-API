if (Vue) {
    Vue.component("lah-case-sur-mgt", {
        template: `<b-card>
            <template #header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="map-marker-alt">
                        複丈案件查詢
                        <span v-if="validate">{{ID}}</span>
                        <lah-fa-icon v-else-if="!empty(ID)" icon="exclamation-circle" variant="danger" append action="damage" title="案件ID有問題">{{ID}}</lah-fa-icon>
                    </lah-fa-icon></h6>
                    <lah-button icon="question" no-border @click="help" variant="outline-success" size="sm" title="說明"></lah-button>
                </div>
            </template>
            <div class="d-flex">
                <lah-case-input-group-ui v-model="id" @enter="query" type="sur" prefix="case_sur"></lah-case-input-group-ui>
                <lah-button icon="search" @click="query" variant="outline-primary" size="sm" class="ml-1" title="查詢測量案件" :disabled="!validate"></lah-button>
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
                regex = /^H[A-Z0-9]{3}$/i;
                if (!regex.test(code)) {
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
            query: function(e) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "sur_case",
                        id: this.id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL && res.data.data_count == 0) {
                            this.notify({
                                title: "測量案件查詢",
                                subtitle: `${this.ID}`,
                                message: "查無資料",
                                type: "warning"
                            });
                        } else {
                            if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                                this.msgbox({
                                    title: `測量案件查詢 ${this.ID}`,
                                    message: this.$createElement("lah-sur-case-dialog", { props: { json: res.data } }),
                                    callback: () => addUserInfoEvent()
                                });
                            } else if (res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                                throw new Error("查詢失敗：" + res.data.message);
                            }
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                } else {
                    this.alert({
                        title: '測量案件狀態',
                        message: `測量案件ID有問題，請檢查後再重試！ (${this.ID})`,
                        variant: 'warning'
                    });
                }
            },
            help: function() {
                this.msgbox({
                    title: "測量案件資料 小幫手提示",
                    message: `<h5><span class="text-danger">※</span>注意：本功能會清除如下圖之欄位資料並將案件辦理情形改為【核定】，請確認後再執行。</h5>
                    <img src="assets/howto/107-HB18-3490_測丈已結案案件辦理情形出現(逾期)延期複丈問題調整【參考】.jpg" class="img-responsive img-thumbnail"/>
                    <h5><span class="text-danger">※</span> 問題原因說明</h5>
                    <div>原因是 CMB0301 延期複丈功能，針對於有連件案件在做處理時，會自動根據MM24案件數，將後面的案件自動做延期複丈的更新。導致後續已結案的案件會被改成延期複丈的狀態 MM22='C' 就是 100、200、300、400為四連件，所以100的案件 MM24='4'，200、300、400 的 MM24='0' 延期複丈的問題再將100號做延期複丈的時候，會將200、300、400也做延期複丈的更新，所以如果400已經結案，100做延期複丈，那400號就會變成 MM22='C' MM23='A' MM24='4' 的異常狀態。</div>`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... lah-case-sur-mgt component can not be loaded.");
}
