if (Vue) {
    let VueCRSMS = {
        template: `<div v-html="message"></div>`,
        props: ["pid"],
        data: function() {
            return {
                json: null,
                message: `<i class="text-primary fas fa-sync ld ld-spin"></i> 登記案件資料查詢中 ...`
            }
        },
        created() {
            this.$http.post(
                CONFIG.JSON_API_EP,
                { type: 'crsms', id: this.pid }
            ).then(response => {
                // on success
                this.json = response.data;
                let count = this.json.data_count;
                if (count == 0) {
                    this.message = "本所登記案件資料庫查無統編「"+this.pid+"」收件資料。";
                } else {
                    this.message = `<p>登記案件( <b class="text-info">${count}件</b> )：`;
                    for (let i=0; i<count; i++) {
                        this.message += "<div class='reg_case_id'>" + this.json.raw[i]["RM01"] + "-" + this.json.raw[i]["RM02"]  + "-" + this.json.raw[i]["RM03"] + "</div>";
                    }
                    this.message += "</p>";
                    setTimeout(() => {
                        // make click case id tr can bring up the detail dialog 【use reg_case_id css class as identifier to bind event】
                        addAnimatedCSS(".reg_case_id", {
                            name: "flash"
                        }).off("click").on("click", window.utilApp.fetchRegCase);
                        $(".reg_case_id").attr("title", "點我取得更多資訊！");
                    }, 250);
                }
            }).catch(error => {
                // on error
                console.error(error.toJson());
            }).finally(() => {
                // finally
            });
        },
        mounted() { }
    };
    let VueCMSMS = {
        template: `<div v-html="message"></div>`,
        props: ["pid"],
        data: function() {
            return {
                json: null,
                message: `<i class="fas fa-sync ld ld-spin"></i> 測量案件資料查詢中 ...`
            }
        },
        created() {
            this.$http.post(
                CONFIG.JSON_API_EP,
                { type: 'cmsms', id: this.pid }
            ).then(response => {
                // on success
                this.json = response.data;
                let count = this.json.data_count;
                if (count == 0) {
                    this.message = "本所測量案件資料庫查無統編「"+this.pid+"」收件資料。";
                } else {
                    this.message = `<p>測量案件( <b class="text-info">${count}件</b> )：`;
                    for (let i=0; i<count; i++) {
                        this.message += "<div>" + this.json.raw[i]["MM01"] + "-" + this.json.raw[i]["MM02"]  + "-" + this.json.raw[i]["MM03"] + "</div>";
                    }
                    this.message += "</p>";
                }
            }).catch(error => {
                // on error
                console.error(error.toJson());
            }).finally(() => {
                // finally
            });
        }
    }
    Vue.component("case-query-by-pid", {
        components: {
            "crsms-case": VueCRSMS,
            "cmsms-case": VueCMSMS
        },
        template: `<fieldset>
            <legend>法院來函查統編</legend>
            <b-input-group size="sm">
                <b-input-group-prepend is-text>統編</b-input-group-prepend>
                <b-form-input id="pid" v-model="pid" placeholder="A123456789"></b-form-input>
                &ensp;
                <b-button size="sm" @click="search" variant="outline-primary"><i class="fas fa-search"></i> 搜尋</b-button>
                &ensp;
                <b-button size="sm" @click="showModal(noteObj)" variant="outline-success"><i class="far fa-comment"></i> 備註</b-button>
            </b-input-group>
            <div id="id_query_crsms_result"></div>
            <div id="id_query_cmsms_result"></div>
        </fieldset>`,
        data: function() {
            return {
                pid: '',
                noteObj: {
                    title: "跨所註記遺失檢測 小幫手提示",
                    body: `<div class="d-block">
                        -- 【法院來函查統編】MOICAS_CRSMS 土地登記案件查詢-權利人+義務人+代理人+複代 <br/>
                        SELECT t.* <br/>
                        &emsp;FROM MOICAS.CRSMS t <br/>
                        WHERE t.RM18 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM21 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM24 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM25 = 'H221350201'; <br/>
                        <br/>
                        -- 【法院來函查統編】MOICAS_CMSMS 測量案件資料查詢-申請人+代理人+複代 <br/>
                        SELECT t.* <br/>
                        &emsp;FROM MOICAS.CMSMS t <br/>
                        WHERE t.MM13 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.MM17_1 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.MM17_2 = 'H221350201';
                    </div>`,
                    size: "lg"
                }
            }
        },
        methods: {
            search: function(e) {
                let h = this.$createElement;
                let vNodes = h(
                    'div',
                    [
                        h("crsms-case", { props: { pid: this.pid } }),
                        h("cmsms-case", { props: { pid: this.pid } })
                    ]
                );
                showModal({
                    title: `查詢案件 BY 統編 「${this.pid}」`,
                    message: vNodes
                });
            }
        },
        mounted() {
            let that = this;
            setTimeout(() => that.pid = $("#pid").val(), 100);
        }
    });
} else {
    console.error("vue.js not ready ... case-query-by-pid component can not be loaded.");
}
