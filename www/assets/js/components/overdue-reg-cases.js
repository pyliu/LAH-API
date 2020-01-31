if (Vue) {
    Vue.component("overdue-reg-cases", {
        template: `<div>
            <b-table
                striped
                hover
                responsive
                bordered
                head-variant="dark"
                caption-top
                :caption="caption"
                :sticky-header="height"
                :items="items"
                :fields="fields"
                :busy="busy"
            >
                <template v-slot:table-busy>
                    <div class="text-center text-danger my-5">
                        <b-spinner class="align-middle"></b-spinner>
                        <strong>查詢中 ...</strong>
                    </div>
                </template>
                <template v-slot:cell(序號)="data">
                    {{data.index + 1}}
                </template>
                <template v-slot:cell(初審人員)="data">
                    <b class="text-info">{{data.value}}</b>
                </template>
            </b-table>
        </div>`,
        props: ['reviewerId'],
        data: function () {
            return {
                items: [],
                fields: [
                    '序號',
                    {key: "收件字號", sortable: true},
                    {key: "登記原因", sortable: true},
                    {key: "辦理情形", sortable: true},
                    {key: "初審人員", sortable: true},
                    {key: "作業人員", sortable: true},
                    {key: "限辦期限", sortable: true},
                    {key: "收件時間", sortable: true}
                ],
                height: true,
                caption: "查詢中 ... ",
                busy: true,
                timer_handle: null
            }
        },
        methods: {
            load: function() {
                this.busy = true;
                let form_body = new FormData();
                form_body.append("type", "overdue_reg_cases");
                if (!isEmpty(this.reviewerId)) {
                    form_body.append("reviewer_id", this.reviewerId);
                }
                asyncFetch("query_json_api.php", {
                    method: 'POST',
                    body: form_body
                }).then(jsonObj => {
                    console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "查詢登記逾期案件回傳狀態碼有問題【" + jsonObj.status + "】");
                    this.busy = false;
                    this.items = jsonObj.items;
                    this.caption = `${jsonObj.data_count} 件，更新時間: ${new Date()}`;
                    setTimeout(() => {
                        $("table tr td:nth-child(2)").on("click", window.utilApp.fetchRegCase).addClass("reg_case_id");
                        addNotification({ title: "查詢登記逾期案件", message: `查詢到 ${jsonObj.data_count} 件案件`, type: "success" });
                    }, 1000);
                }).catch(ex => {
                    console.error("overdue-reg-cases::created parsing failed", ex);
                    showAlert({message: "overdue-reg-cases::created XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                });
            }
        },
        created() {
            this.load();
            // reload the table every 15 mins
            this.timer_handle = setInterval(this.load, 15 * 60 * 1000);
        },
        mounted() {
            this.height = $(document).height() - 145 + "px";
        }
    });
} else {
    console.error("vue.js not ready ... overdue-reg-cases component can not be loaded.");
}
