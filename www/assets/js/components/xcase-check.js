if (Vue) {
    Vue.component("xcase-check-item", {
        template: `<div>
            <div class='my-1'><span class='rounded-circle bg-danger'> &emsp; </span>&ensp;<strong class='text-info'>請查看並修正下列案件：</strong></div>
            <ul v-for="(item, index) in ids">
                <li>
                    <a href='javascript:void(0)' class='reg_case_id' @click="query">{{item}}</a>
                    <button class='fix_xcase_button btn btn-sm btn-outline-success' :data-id='item' @click.once="fix">修正</button>
                    <span :id='item'></span>
                </li>
            </ul>
        </div>`,
        props: ["ids"],
        methods: {
            query: function(e) {
                xhrRegQueryCaseDialog(e);
            },
            fix: function(e) {
                xhrFixProblematicXCase(e);
            }
        }
    });
    Vue.component("xcase-check", {
        template: `<div>
            <fieldset>
                <legend>跨所註記遺失檢測<small>(一周內)</small></legend>
                <button @click="check" class="btn btn-sm btn-outline-primary" data-toggle='tooltip'>立即檢測</button>
                <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
                <div id="cross_case_check_query_display" class="message"></div>
            </fieldset>
        </div>`,
        methods: {
            check: function(e) {
                toggle(e.target);
	
                let body = new FormData();
                body.append("type", "x");

                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    return response.json();
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        showModal({
                            title: "跨所註記遺失查詢",
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
                            body: "<span class='rounded-circle bg-success'> &emsp; </span>&ensp;目前無跨所註記遺失問題。"
                        });
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("xcase-check::check parsing failed", ex);
                    showAlert({message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                });
            },
            popup: function(e) {
                showModal({
                    title: "跨所註記遺失檢測 小幫手提示",
                    body: `
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
                    `,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... xcase-check component can not be loaded.");
}
