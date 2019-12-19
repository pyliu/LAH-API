if (Vue) {
    // this puts inside xcase-check will not seeable by dynamic Vue generation
    Vue.component("xcase-check-item", {
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
    });
    Vue.component("xcase-check", {
        template: `<fieldset>
            <legend>跨所註記遺失檢測<small>(一周內)</small></legend>
            <button @click="check" class="btn btn-sm btn-outline-primary" data-toggle='tooltip'>立即檢測</button>
            <button @click="show = true" class="btn btn-sm btn-outline-success">備註</button>
            <b-modal
                v-model="show"
                size="lg"
                scrollable
                centered
                hide-footer
                no-close-on-backdrop
                content-class="shadow"
            >
                <template v-slot:modal-title>跨所註記遺失檢測 小幫手提示</template>
                <div class="d-block">
                    <h5><span class="text-danger">※</span>通常發生的情況是案件內的權利人/義務人/代理人姓名內有罕字造成。</h5>
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
                </div>
            </b-modal>
        </fieldset>`,
        data: () => {
            return { show: false }
        },
        methods: {
            check: function(e) {
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
                        showModal({
                            title: "<span class='rounded-circle bg-danger'> &emsp; </span>&ensp;<strong class='text-info'>請查看並修正下列案件</strong>",
                            body: `<div id='xcase_check_item_app_012'><xcase-check-item :ids='found'></xcase-check-item></div>`,
                            size: "md",
                            callback: () => {
                                new Vue({
                                    el: "#xcase_check_item_app_012",
                                    data: function() {
                                        return {
                                            found: jsonObj.case_ids
                                        }
                                    },
                                });
                            }
                        });
                    } else if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            message: "目前無跨所註記遺失問題",
                            successSpinner: true
                        });
                    } else {
                        showAlert({message: jsonObj.message, type: "danger"});
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("xcase-check::check parsing failed", ex);
                    showAlert({message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... xcase-check component can not be loaded.");
}
