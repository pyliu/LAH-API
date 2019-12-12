if (Vue) {
    Vue.component("lah-xcase-check", {
        template: `<div>
            <fieldset>
                <legend>跨所註記遺失檢測<small>(一周內)</small></legend>
                <div class="form-row">
                    <div class="filter-btn-group col">
                        <button @click="xhrCheck" class="btn btn-sm btn-outline-primary" data-toggle='tooltip'>立即檢測</button>
                        <button @click="toggleQuote" class="btn btn-sm btn-outline-success">備註</button>
                    </div>
                </div>
                <blockquote id="cross_case_check_quote" class="hide" data-title="跨所註記遺失檢測">
                    <h5><span class="text-danger">※</span>通常發生的情況是案件內的權利人/義務人/代理人姓名內有罕字造成。</h5>
                    QUERY: <br />
                    SELECT * <br />
                        FROM SCRSMS <br />
                    WHERE RM07_1 >= '1080715' <br />
                        AND RM02 LIKE 'H%1' <br />
                        AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR RM101_1 is NULL) 
                    <br /><br />
                    FIX: <br />
                    UPDATE MOICAS.CRSMS SET RM99 = 'Y', RM100 = '資料管轄所代碼', RM100_1 = '資料管轄所縣市代碼', RM101 = '收件所代碼', RM101_1 = '收件所縣市代碼' <br />
                    WHERE RM01 = '收件年' AND RM02 = '收件字' AND RM03 = '收件號'
                </blockquote>
                <div id="cross_case_check_query_display" class="message"></div>
            </fieldset>
        </div>`,
        methods: {
            xhrCheck: function(e) {
                xhrCheckProblematicXCase(e);
            },
            toggleQuote: function(e) {
                let el = $(e.target);
                let fs = $(el.closest("fieldset"));
                quote = fs.find("blockquote");
                if (quote.length > 0) {
                    //quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
                    showModal({
                        title: quote.data("title") + " 小幫手提示",
                        body: quote.html(),
                        size: "lg"
                    });
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... xcase-dashboard component can not be loaded.");
}
