if (Vue) {
    // this puts inside xcase-check will not seeable by dynamic Vue generation
    Vue.component("case-state-mgt", {
        template: `<fieldset>
            <legend>調整登記案件欄位資料</legend>
            <div class="form-row">
            <div class="col-9">
                <case-input-group-ui @update="handleUpdate" type="reg"></case-input-group-ui>
            </div>
            <div class="filter-btn-group col-3">
                <button @click="query" class="btn btn-sm btn-outline-primary">查詢</button>
                <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
            </div>
            </div>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB04",
                num: "000010"
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
                if (!checkCaseUIData(data)) {
                    addNotification({message: `輸入案件資料格式有誤，無法查詢案件。 (${data.toString()})`});
                    return false;
                }
                let year = this.year;
                let code = this.code;
                let number = this.num;
                // prepend "0"
                var offset = 6 - number.length;
                for (var i = 0; i < offset; i++) {
                    number = "0" + number;
                }
                // prepare post params
                let id = trim(year + code + number);
                let body = new FormData();
                body.append("type", "reg_case");
                body.append("id", id);
                
                toggle(e.target);
            
                fetch("query_json_api.php", {
                    method: "POST",
                    //headers: { "Content-Type": "application/json" },
                    body: body
                }).then(response => {
                    return response.json();
                }).then(jsonObj => {
                    showRegCaseUpdateDetail(jsonObj);
                    toggle(e.target);
                }).catch(ex => {
                    console.error("case-state-mgt::query parsing failed", ex);
                    showAlert({message: "無法取得 " + id + " 資訊!【" + ex.toString() + "】", type: "danger"});
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
