if (Vue) {
    Vue.component('lah-reg-table', {
        template: `<lah-transition appear slide-down>
            <b-table
                ref="reg_case_tbl"
                striped
                hover
                responsive
                borderless
                no-border-collapse
                small
                head-variant="dark"
                caption-top
                class="text-center"
                :sticky-header="sticky"
                :caption="caption"
                :items="bakeddata"
                :fields="fields"
                :style="style"
                :busy="!bakeddata"
            >
                <template v-slot:table-busy>
                    <b-spinner class="align-middle" variant="danger" type="grow" small label="讀取中..."></b-spinner>
                </template>

                <template v-slot:cell(序號)="row">
                    {{row.index + 1}}
                </template>

                <template v-slot:cell(RM01)="row">
                    <lah-fa-icon :icon="icon" :variant="iconVariant" v-if='icon'></lah-fa-icon>
                    <a href="javascript:void(0)" @click="fetch(row.item)">{{row.item["收件字號"]}}</a>
                </template>

                <template v-slot:cell(RM09)="row">
                    {{row.item["RM09"] + ":" + row.item["RM09_CHT"]}}</span>
                </template>
            </b-table>
        </lah-transition>`,
        props: ['bakeddata', 'maxHeight', 'icon', 'iconVariant'],
        data: () => { return {
            size: "sm"
        } },
        computed: {
            fields: function() {
                switch(this.size) {
                    case "md":
                        return [
                            '序號',
                            {key: "RM01", label: "收件字號", sortable: true},
                            {key: "RM07_1", label: "收件日期", sortable: true},
                            {key: "RM09", label: "登記代碼", sortable: true}
                            /**收件字號	收件日期	限辦	辦理情形	收件人員	作業人員	初審人員	複審人員	准登人員	登記人員	校對人員	結案人員 */
                        ];
                    case "lg":
                        return ['序號'];
                    case "xl":
                        return ['序號'];
                    default:
                        return [
                            '序號',
                            {key: "RM01", label: "收件字號", sortable: true},
                            {key: "RM07_1", label: "收件日期", sortable: true},
                            {key: "RM09", label: "登記代碼", sortable: true}
                        ];
                }
            },
            source() { return this.bakeddata },
            count() { return this.source ? this.source.length : 0 },
            caption() { return this.source ? '登記案件找到 ' + this.count + '件' : '讀取中' },
            sticky() { return this.maxHeight ? true : false },
            style() {
                const parsed = parseInt(this.maxHeight);
                return isNaN(parsed) ? '' : `max-height: ${parsed}px`;
            }
        },
        watch: {
            bakeddata: function(nVal, oVal) {
                //this.$log(nVal, oVal);
            }
        },
        methods: {
            fetch(data) {
                let id = `${data["RM01"]}${data["RM02"]}${data["RM03"]}`;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        showAlert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                    } else {
                        showModal({
                            message: this.$createElement("case-reg-detail", { props: { jsonObj: res.data.baked } }),
                            title: "登記案件詳情",
                            size: "lg"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                });
            }
        },
        created() {},
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... lah-reg-table component can not be loaded.");
}
