if (Vue) {
    Vue.component("xcase-check", {
        template: `<fieldset>
            <legend>跨所註記檢測</legend>
            <b-button @click="check" size="sm" variant="outline-primary"><i class="fas fa-cogs"></i> 檢測</b-button>
            <b-button @click="showNote" size="sm" variant="outline-success"><i class="far fa-comment"></i> 備註</b-button>
        </fieldset>`,
        methods: {
            showNote: function(e) {
                showModal({
                    title: "跨所註記遺失檢測 小幫手提示",
                    body: `<div class="d-block">
                        <h5><span class="text-danger">※</span>通常發生的情況是案件內的權利人/義務人/代理人姓名內有罕字造成。</h5>
                        <h5><span class="text-danger">※</span>僅檢測一周內資料。</h5>
                        <p class="text-info">QUERY:</p>
                        &emsp;SELECT * <br />
                        &emsp;FROM SCRSMS <br />
                        &emsp;WHERE  <br />
                        &emsp;&emsp;RM07_1 >= '1080715' <br />
                        &emsp;&emsp;AND RM02 LIKE 'H%1' <br />
                        &emsp;&emsp;AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR RM101_1 is NULL) 
                        <br /><br />
                        <p class="text-success">FIX:</p>
                        &emsp;UPDATE MOICAS.CRSMS SET <br />
                        &emsp;&emsp;RM99 = 'Y', <br />
                        &emsp;&emsp;RM100 = '資料管轄所代碼', <br />
                        &emsp;&emsp;RM100_1 = '資料管轄所縣市代碼', <br />
                        &emsp;&emsp;RM101 = '收件所代碼', RM101_1 = '收件所縣市代碼' <br />
                        &emsp;WHERE RM01 = '收件年' AND RM02 = '收件字' AND RM03 = '收件號'
                    </div>`,
                    size: "lg"
                });
            },
            check: function(e) {
                const h = this.$createElement;

                toggle(e.target);
	
                let body = new FormData();
                body.append("type", "xcase-check");

                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    return response.json();
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        let vnode = h("xcase-check-item", { props: { ids: jsonObj.case_ids } });
                        showModal({
                            title: "<i class='fas fa-circle text-danger'></i>&ensp;<strong class='text-info'>請查看並修正下列案件</strong>",
                            body: vnode,
                            size: "md"
                        });
                    } else if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            title: "檢測系統跨所註記遺失問題",
                            message: "<i class='fas fa-circle text-success'></i>&ensp;目前無跨所註記遺失問題",
                            type: "success"
                        });
                    } else {
                        showAlert({ title: "檢測系統跨所註記遺失問題", message: jsonObj.message, type: "danger" });
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("xcase-check::check parsing failed", ex);
                    showAlert({message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                });
            }
        },
        components: {
            "xcase-check-item": {
                template: `<ul style="font-size: 0.9rem">
                    <li v-for="(item, index) in ids">
                        <a href='javascript:void(0)' class='reg_case_id' @click="query">{{item}}</a>
                        <button class='fix_xcase_button btn btn-sm btn-outline-success' :data-id='item' @click.once="fix">修正</button>
                    </li>
                </ul>`,
                props: ["ids"],
                methods: {
                    query: function(e) {
                        xhrRegQueryCaseDialog(e);
                    },
                    fix: function(e) {
                        let id = $(e.target).data("id").replace(/[^a-zA-Z0-9]/g, "");
                        console.log("The problematic xcase id: "+id);
        
                        let body = new FormData();
                        body.append("type", "fix_xcase");
                        body.append("id", id);
        
                        let li = $(e.target).closest("li");
                        $(e.target).remove();
        
                        fetch("query_json_api.php", {
                            method: "POST",
                            body: body
                        }).then(response => {
                            if (response.status != 200) {
                                throw new Error("XHR連線異常，回應非200");
                            }
                            return response.json();
                        }).then(jsonObj => {
                            let msg = `<strong class='text-success'>${id} 跨所註記修正完成!</strong>`;
                            if (jsonObj.status != XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                msg = `<span class='text-danger'>${id} 跨所註記修正失敗! (${jsonObj.status})</span>`;
                            }
                            addNotification({ message: msg, variant: "success" });
                            li.html(msg);
                        }).catch(ex => {
                            console.error("xcase-check-item::fix parsing failed", ex);
                            showAlert({message: ex.toString(), type: "danger"});
                        });
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... xcase-check component can not be loaded.");
}
