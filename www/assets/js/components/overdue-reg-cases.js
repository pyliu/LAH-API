if (Vue) {
    Vue.component("overdue-reg-cases", {
        template: `<div>
            <b-table
                striped
                hover
                responsive
                bordered
                head-variant="light"
                :sticky-header="height"
                :items="items"
            ></b-table>
        </div>`,
        data: function () {
            return {
                items: [],
                height: true
            }
        },
        created() {
            let form_body = new FormData();
            form_body.append("type", "overdue_reg_cases");
            asyncFetch("query_json_api.php", {
                method: 'POST',
                body: form_body
            }).then(jsonObj => {
                console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "查詢登記逾期案件回傳狀態碼有問題【" + jsonObj.status + "】");
                this.items = jsonObj.items;
                addNotification({ title: "查詢登記逾期案件", message: `查詢到${jsonObj.data_count}件案件`, type: "success" });
            }).catch(ex => {
                console.error("overdue-reg-cases::created parsing failed", ex);
                showAlert({message: "overdue-reg-cases::created XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
            });
        },
        mounted() {
            this.height = $(document).height() - 145 + "px";
            setTimeout(() => {
                $("table tr td:first-child").on("click", window.utilApp.fetchRegCase).addClass("reg_case_id");
            }, 1000);
        }
    });
} else {
    console.error("vue.js not ready ... overdue-reg-cases component can not be loaded.");
}
