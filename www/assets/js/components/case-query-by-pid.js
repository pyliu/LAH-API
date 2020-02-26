if (Vue) {
    let VueCRSMS = {
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
                :items="json.raw"
                :fields="fields"
                class="text-center"
                v-if="isTableReady"
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
            <div v-else v-html="message"></div>
        </lah-transition>`,
        props: ["pid"],
        data: function() {
            return {
                json: null,
                fields: [
                    '序號',
                    {key: "RM01", label: "收件字號", sortable: true},
                    {key: "RM07_1", label: "收件日期", sortable: true},
                    {key: "RM09", label: "登記代碼", sortable: true}
                ],
                message: '<i class="text-primary ld ld-spin ld-spinner"></i> 登記案件資料查詢中 ...'
            }
        },
        computed: {
            isTableReady: function() {
                return this.json && this.json.data_count > 0;
            }
        },
        created() {
            this.$http.post(
                CONFIG.JSON_API_EP,
                { type: 'crsms', id: this.pid }
            ).then(response => {
                // on success
                this.json = response.data;
                if (this.json.data_count == 0) {
                    this.message = `<i class="text-info fas fa-exclamation-circle"></i> 查無登記案件資料`;
                }
                Vue.nextTick(() => {
                    // make click case id tr can bring up the detail dialog 【use reg_case_id css class as identifier to bind event】
                    addAnimatedCSS(".reg_case_id", {
                        name: "flash"
                    }).off("click").on("click", window.vueApp.fetchRegCase);
                    $(".reg_case_id").attr("title", "點我取得更多資訊！");
                });
            }).catch(error => {
                // on error
                console.error(error.toJson());
                this.message = `<i class="text-danger fas fa-exclamation-circle"></i> 查詢登記案件發生錯誤！【${error}】`;
            }).finally(() => {
                // finally
            });
        }
    };
    let VueCMSMS = {
        template: `<lah-transition slide-up>
            <b-table
                ref="sur_case_tbl"
                striped
                hover
                responsive
                borderless
                no-border-collapse
                small
                sticky-header
                head-variant="dark"
                caption-top
                :caption="'測量案件找到 ' + json.raw.length + '件'"
                :items="json.raw"
                :fields="fields"
                :busy="isBusy"
                class="text-center"
                v-if="json && json.data_count > 0"
            >
                <template v-slot:cell(序號)="data">
                    {{data.index + 1}}
                </template>
                <template v-slot:cell(MM01)="data">
                    <span>{{data.item["MM01"] + "-" + data.item["MM02"] + "-" +  data.item["MM03"]}}</span>
                </template>
                <template v-slot:cell(MM06)="data">
                    {{data.item["MM06"] + ":" + data.item["MM06_CHT"]}}</span>
                </template>
            </b-table>
            <div v-else v-html="message"></div>
        </lah-transition>`,
        props: ["pid"],
        data: function() {
            return {
                json: null,
                fields: [
                    '序號',
                    {key: "MM01", label: "收件字號", sortable: true},
                    {key: "MM04_1", label: "收件日期", sortable: true},
                    {key: "MM06", label: "申請事由", sortable: true}
                ],
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
                if (this.json.data_count == 0) {
                    this.message = `<i class="text-secondary fas fa-exclamation-circle"></i> 查無測量案件資料`;
                }
            }).catch(error => {
                // on error
                console.error(error.toJson());
                this.message = `<i class="text-danger fas fa-exclamation-circle"></i> 查詢測量案件發生錯誤！【${error}】`;
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
            <legend>查詢人民申請案件</legend>
            <b-input-group size="sm">
                <b-input-group-prepend is-text><b-icon icon="person-fill"></b-icon></b-input-group-prepend>
                <b-form-input
                    id="pid"
                    v-model="pid"
                    placeholder="A123456789"
                    :state="valid"
                    @keyup.enter="search"
                    title="身分證號"
                ></b-form-input>
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
        computed: {
            valid: function() {
                if (this.pid == '') return null;
                return this.checkID();
            }
        },
        methods: {
            search: function(e) {
                if (this.valid) {
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
                } else {
                    addNotification({
                        message: `「${this.pid}」 統編格式錯誤`,
                        type: "warning"
                    });
                }
            },
            checkID: function() {
                let id = this.pid;
                tab = "ABCDEFGHJKLMNPQRSTUVXYWZIO"                     
                A1 = new Array (1,1,1,1,1,1,1,1,1,1,2,2,2,2,2,2,2,2,2,2,3,3,3,3,3,3 );
                A2 = new Array (0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5 );
                Mx = new Array (9,8,7,6,5,4,3,2,1,1);

                if ( id.length != 10 ) return false;
                i = tab.indexOf( id.charAt(0) );
                if ( i == -1 ) return false;
                sum = A1[i] + A2[i]*9;

                for ( i=1; i<10; i++ ) {
                    v = parseInt( id.charAt(i) );
                    if ( isNaN(v) ) return false;
                    sum = sum + v * Mx[i];
                }
                if ( sum % 10 != 0 ) return false;
                return true;
            }
        },
        mounted() {
            let that = this;
            // wait cached data write back
            setTimeout(() => that.pid = $("#pid").val(), 200);
        }
    });
} else {
    console.error("vue.js not ready ... case-query-by-pid component can not be loaded.");
}
