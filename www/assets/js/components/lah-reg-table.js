if (Vue) {
    Vue.component('lah-reg-table', {
        template: `<lah-transition slide-down>
            <b-table
                ref="reg_case_tbl"
                striped
                hover
                responsive
                borderless
                no-border-collapse
                small
                sticky-header
                head-variant="dark"
                caption-top
                :caption="'登記案件找到 ' + json.raw.length + '件'"
                :items="rawData"
                :fields="fields"
                class="text-center"
            >
                <template v-slot:cell(序號)="data">
                    {{data.index + 1}}
                </template>
                <template v-slot:cell(RM01)="data">
                    <span class="reg_case_id">{{data.item["RM01"] + "-" + data.item["RM02"] + "-" +  data.item["RM03"]}}</span>
                </template>
                <template v-slot:cell(RM09)="data">
                    {{data.item["RM09"] + ":" + data.item["RM09_CHT"]}}</span>
                </template>
            </b-table>
        </lah-transition>`,
        props: ['rawData'],
        data: () => { return {
            sm_fields: [
                '序號',
                {key: "RM01", label: "收件字號", sortable: true},
                {key: "RM07_1", label: "收件日期", sortable: true},
                {key: "RM09", label: "登記代碼", sortable: true}
            ],
            md_fields: {},
            lg_fields: {},
            xl_fields: {},
            size: "sm"
        } },
        computed: {
            fields: function() {
                switch(this.size) {
                    case "md":
                        return this.md_fields;
                    case "lg":
                        return this.lg_fields;
                    case "xl":
                        return this.xl_fields;
                    default:
                        return this.sm_fields;
                }
            }
        },
        methods: {},
        created() {},
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... lah-reg-table component can not be loaded.");
}
