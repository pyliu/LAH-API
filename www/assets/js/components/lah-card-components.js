if (Vue) {
    // b-card components
    Vue.component("lah-user-board", {
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="users">使用者看板</lah-fa-icon></h6>
                    <b-button-group size="sm" class="align-middle my-auto">
                        <lah-button no-border @click="location.href='http://'+location.host+':8080/admin/users'" variant="outline-primary" icon="users-cog"></lah-button>
                        <lah-button no-border  @click="location.href='org.html'" variant="outline-primary" icon="sitemap"></lah-button>
                        <lah-button no-border  @click="popup" variant="outline-success" icon="question"></lah-button>
                    </b-button-group>
                </div>
            </template>
            <b-input-group size="sm" prepend="關鍵字">
                <b-form-input
                    placeholder="🔍 'HB05' OR '憶如' OR '220.1.35.x'"
                    ref="input"
                    v-model="input"
                    @keyup.enter="query"
                    title="HAXXXX 或 姓名 或 IP"
                    :state="validate"
                    class="no-cache"
                ></b-form-input>
                <template v-slot:append>
                    <lah-button v-if="false" @click="query" variant="outline-primary" size="sm" v-b-tooltip="'搜尋使用者'" icon="search"></lah-button>
                </template>
            </b-input-group>
            <template #footer>
                <div id="usertag_container" class="clearfix overflow-auto" :style="style">
                    <transition-group name="list" mode="out-in">
                        <div
                            v-for="(userinfo, idx) in usernames"
                            class='float-left m-1 usercard'
                            style='font-size: .75rem;'
                            :data-id="userinfo.id"
                            :data-name="userinfo.name"
                            :key="'usertag_'+userinfo.id"
                            @click.stop="popUsercard(userinfo.id)"
                            v-if="usertag_flags[userinfo.id]"
                            v-b-popover="popover(userinfo)"
                        >
                            <b-avatar v-if="avatar" button size="1.2rem" :src="avatar_src(userinfo.name)" variant="light"></b-avatar>
                            {{dogtag(userinfo)}}
                        </div>
                    </transition-group>
                </div>
            </template>
        </b-card>`,
        props: {
            avatar: {
                type: Boolean,
                default: true
            },
            maxHeight: {
                type: String,
                default: ''
            }
        },
        data: () => ({
            input: '',
            last_hit_ids: [],
            ids: [],
            usertag_flags: {},
            keyup_timer: null,
            delay_ms: 800,
            usernames: []
        }),
        watch: {
            input(nVal, oVal) {
                this.filter()
            },
            myid(val) { this.input = val.substring(0, 5) },
            usernames(val) {
                this.ids = val.map((item, idx, array) => {
                    // set all flags to false at first
                    Vue.set(this.usertag_flags, item.id, false);
                    return item.id;
                });
                this.ids.sort();
            }
        },
        computed: {
            validate() {
                return this.empty(this.input) ? null : this.length(this.input) > 1
            },
            style() {
                return this.empty($.trim(this.maxHeight)) ? '' : `max-height: ${this.maxHeight}px`
            }
        },
        methods: {
            popover(userinfo) {
                let left = this.left(userinfo) ? `<div>離職：${userinfo.offboard_date} <i class="fa fa-ban text-danger mx-auto"></i></div>` : '';
                let admin = '';
                if (this.isAdmin) {
                    admin = `
                        <div>生日：${userinfo.birthday || ''}</div>
                        <div>學歷：${userinfo.education}</div>
                        <div>考試：${userinfo.exam}</div>
                        <div>手機：${userinfo.cell}</div>
                        <div>到職：${userinfo.onboard_date}</div>    
                    `;
                }
                let html = `<div class="small">
                    <div>職稱：${userinfo.title}</div>
                    <div>分機：${userinfo.ext}</div>
                    <div>單位：${userinfo.unit}</div>
                    <div>工作：${userinfo.work}</div>
                    ${admin}
                    ${left}
                </div>`;
                return {
                    content: html,
                    variant: this.empty(userinfo.offboard_date) ? 'muted' : 'dark',
                    trigger: 'hover',
                    delay: 800,
                    // customClass: 's-80',
                    html: true
                };
            },
            left(userinfo) { return userinfo.authority & 1 },   // 1 means DISABLED
            length(s) {
                var b = 0,
                    i = 0,
                    c;
                for (; c = s.charCodeAt(i++); b += c >> 11 ? 3 : c >> 7 ? 2 : 1);
                return b
            },
            dogtag(userinfo) { return `${userinfo.id}: ${userinfo.name||'XXXXXX'}` },
            avatar_src(name) {
                return `get_user_img.php?name=${name}_avatar`
            },
            filter() {
                if (this.keyup_timer) {
                    clearTimeout(this.keyup_timer);
                    this.keyup_timer = null;
                }
                this.keyup_timer = this.timeout(this.mark, this.delay_ms);
            },
            mark() {
                if (this.validate) {
                    this.last_hit_ids.forEach(id => {
                        this.usertag_flags[id] = false;
                    });
                    // clear last on flags
                    this.last_hit_ids = [];

                    this.input = this.input.replace("?", ""); // prevent out of memory
                    let keyword = new RegExp(this.input, "i");
                    this.ids.forEach(id => {
                        let text = `${id}: ${this.userNames[id]}`;
                        this.usertag_flags[id] = keyword.test(text);
                        if (this.usertag_flags[id]) {
                            this.last_hit_ids.push(id);
                        }
                    });
                    // rendering may take some time so use Vue.nextTick ... 
                    Vue.nextTick(() => {
                        $('#usertag_container').unmark({
                            element: "strong",
                            className: "highlight",
                            done: () => {
                                $('#usertag_container').mark(this.input, {
                                    element: "strong",
                                    className: "highlight"
                                });
                            }
                        });
                    });
                }
            },
            query() {
                let keyword = $.trim(this.input.replace(/\?/g, ""));
                if (this.empty(keyword)) {
                    this.$warn("Keyword field should not be empty.");
                    return;
                }
                this.$http.post(CONFIG.API.JSON.USER, {
                    type: 'search_user',
                    keyword: keyword
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        let card = this.$createElement("lah-user-card", {
                            props: {
                                inUserRows: res.data.raw
                            }
                        });
                        this.$modal(card, {
                            title: "搜尋使用者資訊"
                        });
                    } else {
                        this.notify({
                            title: "搜尋使用者",
                            message: res.data.message,
                            type: "warning"
                        });
                        this.$warn(res.data.message);
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {

                });
            },
            popup() {
                this.msgbox({
                    title: '<i class="fa fa-search fa-lg"></i> 使用者搜尋說明',
                    message: `輸入下列條件來查找。 <ul><li>使用者代碼(如：HB1184)</li> <li>名稱(如：奕)</li> <li>電腦IP位址(如：192.168.22.7)</li> </ul>`,
                    size: "sm"
                });
            },
            load_usernames() {
                this.getLocalCache('user_names').then(raw => {
                    if (raw === false) {
                        this.busy = true;
                        this.$http.post(CONFIG.API.JSON.USER, {
                            type: 'user_names'
                        }).then(res => {
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                res.data.raw.forEach(userinfo => {
                                    this.usernames.push({...userinfo});
                                });
                                this.setLocalCache('user_names', res.data.raw);
                            } else {
                                this.notify({
                                    title: "使用者名冊",
                                    message: res.data.message,
                                    type: "warning"
                                });
                                this.$warn(res.data.message);
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.busy = false;
                        });
                    } else {
                        raw.forEach(userinfo => {
                            this.usernames.push({...userinfo});
                        });
                    }
                });
            }
        },
        created() { this.load_usernames() }
    });

    Vue.component('lah-report', {
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="file-excel" prefix="far">報表匯出</lah-fa-icon></h6>
                    <lah-button icon="question" no-border @click="popup" variant="outline-success" size="sm" title="備註"></lah-button>
                    {{selected}}
                </div>
            </template>
            <b-input-group size="sm">
                <b-input-group-prepend is-text>預載查詢選項</b-input-group-prepend>
                <b-form-select ref="select" v-model="selected" :options="options" @change="change"></b-form-select>
                <lah-button icon="file-export" class="ml-1" @click="output" variant="outline-primary" v-b-tooltip="'匯出'" :disabled="!validate"></lah-button>
            </b-input-group>
            <b-form-textarea
                ref="sql"
                placeholder="SELECT SQL text ..."
                rows="3"
                max-rows="8"
                v-model="sql"
                size="sm"
                class="mt-1 overflow-auto no-cache"
                :state="validate"
            ></b-form-textarea>
        </b-card>`,
        data: () => ({
            selected: '',
            selected_label: '',
            sql: '',
            options: [
                {
                    label: '==== 所內登記案件統計 ====',
                    options: [{
                            text: '每月案件統計',
                            value: '01_reg_case_monthly.sql'
                        },
                        {
                            text: '每月案件 by 登記原因',
                            value: '11_reg_reason_query_monthly.sql'
                        },
                        {
                            text: '每月遠途先審案件',
                            value: '02_reg_remote_case_monthly.sql'
                        },
                        {
                            text: '每月跨所案件【本所代收】',
                            value: '03_reg_other_office_case_monthly.sql'
                        },
                        {
                            text: '每月跨所案件【非本所收件】',
                            value: '04_reg_other_office_case_2_monthly.sql'
                        },
                        {
                            text: '每月跨所子號案件【本所代收】',
                            value: '09_reg_other_office_case_3_monthly.sql'
                        },
                        {
                            text: '每月跨所各登記原因案件統計 by 收件所',
                            value: '10_reg_reason_stats_monthly.sql'
                        },
                        {
                            text: '每月權利人＆義務人為外國人案件',
                            value: '07_reg_foreign_case_monthly.sql'
                        },
                        {
                            text: '每月外國人地權登記統計',
                            value: '07_regf_foreign_case_monthly.sql'
                        },
                        {
                            text: '每月土地建物登記統計檔',
                            value: '17_rega_case_stats_monthly.sql'
                        },
                        {
                            text: '外站人員謄本核發量',
                            value: '08_reg_workstation_case.sql'
                        }
                    ]
                },
                {
                    label: '==== 所內其他統計 ====',
                    options: [{
                            text: '已結卻延期之複丈案件',
                            value: '16_sur_close_delay_case.sql'
                        },
                        {
                            text: '因雨延期測量案件數',
                            value: '14_sur_rain_delay_case.sql'
                        },
                        {
                            text: '段小段面積統計',
                            value: '05_adm_area_size.sql'
                        },
                        {
                            text: '段小段土地標示部筆數',
                            value: '06_adm_area_blow_count.sql'
                        },
                        {
                            text: '未完成地價收件資料',
                            value: '12_prc_not_F_case.sql'
                        },
                        {
                            text: '法院謄本申請LOG檔查詢 BY 段、地建號',
                            value: '13_log_court_cert.sql'
                        },
                        {
                            text: '某段之土地所有權人清冊資料',
                            value: '15_reg_land_stats.sql'
                        },
                        {
                            text: '全國跨縣市收件資料',
                            value: '18_cross_county_crsms.sql'
                        }
                    ]
                },{
                    label: '==== 地籍資料 ====',
                    options: [{
                            text: 'AI00301 - 土地標示部資料',
                            value: 'txt_AI00301.sql'
                        },
                        {
                            text: 'AI00401 - 土地所有權部資料',
                            value: 'txt_AI00401.sql'
                        },
                        {
                            text: 'AI00601 - 土地管理者資料',
                            value: 'txt_AI00601_B.sql'
                        },
                        {
                            text: 'AI00601 - 建物管理者資料',
                            value: 'txt_AI00601_E.sql'
                        },
                        {
                            text: 'AI00701 - 建物標示部資料',
                            value: 'txt_AI00701.sql'
                        },
                        {
                            text: 'AI00801 - 基地坐落資料',
                            value: 'txt_AI00801.sql'
                        },
                        {
                            text: 'AI00901 - 建物分層及附屬資料',
                            value: 'txt_AI00901.sql'
                        },
                        {
                            text: 'AI01001 - 主建物與共同使用部分資料',
                            value: 'txt_AI01001.sql'
                        },
                        {
                            text: 'AI01101 - 建物所有權部資料',
                            value: 'txt_AI01101.sql'
                        },
                        {
                            text: 'AI02901 - 土地各部別之其他登記事項列印',
                            value: 'txt_AI02901_B.sql'
                        },
                        {
                            text: 'AI02901 - 建物各部別之其他登記事項列印',
                            value: 'txt_AI02901_E.sql'
                        }
                    ]
                }
            ]
        }),
        computed: {
            validate() {
                return this.empty(this.sql) ? null : /^select/gi.test(this.sql)
            },
            cache_key() {
                return `lah-report_sql`
            }
        },
        methods: {
            change(val) {
                let opt = $("select.custom-select optgroup option[value='" + val + "']")[0];
                this.$assert(opt, "找不到選取的 option。", $("select.custom-select optgroup option[value='" + val + "']"));
                this.selected_label = opt.label;
                this.$http.post(CONFIG.API.FILE.LOAD, {
                    type: "load_select_sql",
                    file_name: this.selected
                }).then(res => {
                    this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `讀取異常，回傳狀態值為 ${res.data.status}，${res.data.message}`);
                    this.sql = res.data.data;
                    this.setLocalCache(this.cache_key, this.sql, 0); // no expiring
                    // let cache work
                    Vue.nextTick(() => $(this.$refs.sql.$el).trigger("blur"));
                }).catch(err => {
                    this.error = err;
                }).finally(() => {

                });
            },
            output(e) {
                if (this.selected.startsWith("txt_")) {
                    this.download('file_sql_txt');
                } else {
                    this.download('file_sql_csv');
                }
            },
            download(type) {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.FILE.EXPORT, {
                        type: type,
                        sql: this.sql,
                        responseType: 'blob'
                    }).then(res => {
                        // BE should not response anything (wrote tmp.txt or tmp.csv)
                        if (res.data.length > 0) {
                            this.$error(typeof res.data, res.data.length);
                            this.notify({
                                title: '下載報表檔案',
                                message: res.data,
                                type: 'danger'
                            });
                        } else {
                            let notify_title = '匯出CSV檔案';
                            let iframe_title = '下載CSV';
                            let api = CONFIG.API.FILE.CSV + '?filename=' + this.selected_label;
                            if (type == "file_sql_txt") {
                                notify_title = '匯出TXT檔案';
                                iframe_title = '下載TXT';
                                api = CONFIG.API.FILE.TXT + '?filename=' + this.selected_label;
                            }
                            this.notify({
                                title: notify_title,
                                message: '<i class="fas fa-cog ld ld-spin"></i> 後端處理中 ... ',
                                type: "warning",
                                duration: 2000
                            });
                            // second param usage => e.target.title to get the title
                            this.open(api, {
                                target: {
                                    title: iframe_title
                                }
                            });
                            this.timeout(() => closeModal(() => this.notify({
                                title: notify_title,
                                message: '<i class="fas fa-check ld ld-pulse"></i> 後端作業完成',
                                type: "success"
                            })), 2000);
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                } else {
                    this.notify({
                        title: "匯出SQL檔案報表",
                        message: "SQL內容不能為空的。",
                        type: "warning"
                    });
                }
            },
            popup(e) {
                this.msgbox({
                    title: '報表檔案匯出功能提示',
                    message: `
                        <h5>地政局索取地籍資料備註</h5>
                        <span class="text-danger mt-2">※</span> 系統管理子系統/資料轉入轉出 (共14個txt檔案，地/建號範圍從 00000000 ~ 99999999) <br/>
                        　- <small class="mt-2 mb-2"> 除下面標示為黃色部分須至地政系統產出並下載，其餘皆可於「報表匯出」區塊產出。</small> <br/>
                        　AI001-10 <br/>
                        　　AI00301 - 土地標示部 <br/>
                        　　AI00401 - 土地所有權部 <br/>
                        　　AI00601 - 管理者資料【土地、建物各做一次】 <br/>
                        　　AI00701 - 建物標示部 <br/>
                        　　AI00801 - 基地坐落 <br/>
                        　　AI00901 - 建物分層及附屬 <br/>
                        　　AI01001 - 主建物與共同使用部分 <br/>
                        　AI011-20 <br/>
                        　　AI01101 - 建物所有權部 <br/>
                        　　<span class="text-warning">AI01901 - 土地各部別</span> <br/>
                        　AI021-40 <br/>
                        　　<span class="text-warning">AI02101 - 土地他項權利部</span> <br/>
                        　　<span class="text-warning">AI02201 - 建物他項權利部</span> <br/>
                        　　AI02901 - 各部別之其他登記事項【土地、建物各做一次】 <br/><br/>

                        <span class="text-danger">※</span> 測量子系統/測量資料管理/資料輸出入 【請至地政系統WEB版產出】<br/>
                        　地籍圖轉出(數值地籍) <br/>
                        　　* 輸出DXF圖檔【含控制點】及 NEC重測輸出檔 <br/>
                        　地籍圖轉出(圖解數化) <br/>
                        　　* 同上兩種類皆輸出，並將【分幅管理者先接合】下選項皆勾選 <br/><br/>
                            
                        <span class="text-danger">※</span> <del>登記子系統/列印/清冊報表/土地建物地籍整理清冊【土地、建物各產一次存PDF，請至地政系統WEB版產出】</del> 【2021-04-13起確認不能給】 <br/>
                    `,
                    size: 'lg'
                });
            }
        },
        mounted() {
            this.getLocalCache(this.cache_key).then(sql => {
                this.sql = sql;
                if (this.sql === false) this.sql = '';
            });
            this.timeout(() => {
                this.selected = this.$refs.select.$el.value;
                if ($("select.custom-select optgroup option[value='" + this.selected + "']").length > 0) {
                    let opt = $("select.custom-select optgroup option[value='" + this.selected + "']")[0];
                    this.$assert(opt, "找不到選取的 option。", $("select.custom-select optgroup option[value='" + this.selected + "']"));
                    this.selected_label = opt.label;
                }
            }, 400);
        }
    });
    
    Vue.component("lah-section-search", {
        components: {
            "lah-area-search-results": {
                template: `<div>
                    <b-table
                        v-if="count > 0"
                        ref="section_search_tbl"
                        :responsive="'sm'"
                        :striped="true"
                        :hover="true"
                        :bordered="true"
                        :small="true"
                        :no-border-collapse="true"
                        :head-variant="'dark'"

                        :items="json.raw"
                        :fields="fields"
                        :busy="!json"
                        primary-key="段代碼"

                        class="text-center"
                        caption-top
                    >
                        <template v-slot:cell(面積)="{ item }">
                            <span v-b-tooltip.d400="area(item.面積)">{{areaM2(item.面積)}}</span>
                        </template>
                        <template v-slot:cell(土地標示部筆數)="{ item }">
                            {{format(item.土地標示部筆數)}} 筆
                        </template>
                    </b-table>
                    <lah-fa-icon v-else icon="exclamation-triangle" variant="danger" size="lg"> {{input}} 查無資料</lah-fa-icon>
                </div>`,
                props: {
                    json: {
                        type: Object,
                        default: {}
                    },
                    input: {
                        type: String,
                        default: ''
                    }
                },
                data: () => ({
                    fields: [
                        {
                            key: "區代碼",
                            sortable: true
                        },{
                            key: "區名稱",
                            sortable: true
                        },

                        {
                            key: "段代碼",
                            sortable: true
                        },
                        {
                            key: "段名稱",
                            sortable: true
                        },
                        {
                            key: "面積",
                            sortable: true
                        },
                        {
                            key: "土地標示部筆數",
                            sortable: true
                        },
                    ]
                }),
                computed: {
                    count() {
                        return this.json.data_count || 0
                    }
                },
                methods: {
                    format(val) {
                        return val ? val.replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''
                    },
                    area(val) {
                        return val ? this.format((val * 3025 / 10000).toFixed(2)) + ' 坪' : ''
                    },
                    areaM2(val) {
                        return val ? this.format(val) + ' 平方米' : ''
                    }
                }
            }
        },
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="map">轄區段別資料</lah-fa-icon></h6>
                    <lah-button icon="question" no-border @click="popup" variant="outline-success" size="sm"></lah-button>
                </div>
            </template>
            <a href="assets/files/桃園市土地基本資料庫電子資料收費標準.pdf" target="_blank">電子資料申請收費標準</a>
            <a href="assets/files/%E5%9C%9F%E5%9C%B0%E5%9F%BA%E6%9C%AC%E8%B3%87%E6%96%99%E5%BA%AB%E9%9B%BB%E5%AD%90%E8%B3%87%E6%96%99%E6%B5%81%E9%80%9A%E7%94%B3%E8%AB%8B%E8%A1%A8.doc" target="_blank">電子資料申請書</a> <br />
            <b-input-group size="sm" prepend="關鍵字/段代碼">
                <b-form-input
                    placeholder="🔍 '榮民段' OR '0200'"
                    ref="text"
                    v-model="text"
                    @keyup.enter="query"
                    :state="validate"
                ></b-form-input>
                <template v-slot:append>
                    <b-button @click="query" variant="outline-primary" size="sm" v-b-tooltip="'搜尋段小段'" :disabled="!validate"><i class="fas fa-search"></i></b-button>
                </template>
            </b-input-group>
        </b-card>`,
        data: () => ({
            text: ''
        }),
        computed: {
            validate() {
                return isNaN(parseInt(this.text)) ? true : (this.text <= 2000 && this.text >= 1)
            },
            cache_key() {
                return 'lah-section-search_' + this.text
            }
        },
        methods: {
            query() {
                this.getLocalCache(this.cache_key).then(json => {
                    if (json) {
                        this.result(json);
                    } else {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: 'ralid',
                            text: this.text
                        }).then(res => {
                            this.result(res.data);
                            this.setLocalCache(this.cache_key, res.data, 24 * 60 * 60 * 1000);
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    }
                });
            },
            result(json) {
                this.msgbox({
                    title: "段小段查詢結果",
                    message: this.$createElement("lah-area-search-results", {
                        props: {
                            json: json,
                            input: this.text
                        }
                    }),
                    size: "lg"
                });
            },
            popup() {
                this.msgbox({
                    title: '土地標示部筆數＆面積查詢',
                    message: `-- 段小段筆數＆面積計算 (RALID 登記－土地標示部) <br/>
                    SELECT m.KCDE_2 as "段代碼", <br/>
                    　　m.KCNT as "段名稱", <br/>
                    　　SUM(t.AA10) as "面積", <br/>
                    　　COUNT(t.AA10) as "土地標示部筆數", <br/>
                    　　t.AA46 as "區代碼", <br/>
                    　　q.KNAME as "區名稱" <br/>
                    FROM MOIADM.RKEYN m <br/>
                    LEFT JOIN MOICAD.RALID t <br/>
                    　　ON m.KCDE_2 = t.AA48 -- 段小段面積計算 (RALID 登記－土地標示部) <br/>
                    LEFT JOIN MOIADM.RKEYN_ALL q <br/>
                    　　ON q.KCDE_1 = '46' <br/>
                    AND q.KCDE_2 = 'H' <br/>
                    AND t.AA46 = q.KCDE_3 <br/>
                    WHERE m.KCDE_1 = '48' <br/>
                    AND m.KCDE_2 NOT LIKE '/*%' <br/>
                    AND t.AA46 IS NOT NULL <br/>
                    GROUP BY m.KCDE_2, m.KCNT, t.AA46, q.KNAME <br/>`,
                    size: 'lg'
                });
            }
        },
        mounted() {
            this.timeout(() => this.text = this.$refs.text.$el.value, 400);
        }
    });
   /**
     * Vuex switches
     */
    Vue.component("lah-system-config", {
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="cogs"> 系統設定</lah-fa-icon></h6>
                    <div>
                        <lah-button icon="cog" @click="open" size="sm" variant="outline-primary" no-border></lah-button>
                        <lah-button icon="question" @click="popup" size="sm" variant="outline-success" no-border></lah-button>
                    </div>
                </div>
            </template>
            <b-row class="mb-1">
                <div class="my-auto mr-1 ml-3">更新快取資料</div>
                <b-button-group size="sm">
                    <b-button variant="outline-info" title="使用者名稱對應" @click="table_import('l3hweb_sysauth1')" style="border-radius: 15px">SYSAUTH1</b-button>
                    <b-button variant="outline-info" title="地政WEB版代碼檔" @click="table_import('rkeyn')" class="mx-1" style="border-radius: 15px">RKEYN</b-button>
                    <b-button variant="outline-info" title="全國鄉鎮區段代碼" @click="table_import('rkeyn_all')" style="border-radius: 15px">RKEYN_ALL</b-button>
                </b-button-group>
            </b-row>
            <b-form-checkbox v-if="false" v-model="enable_msdb_query" switch><span title="是否啟用MSSQL資料庫連結">啟用 SQL SERVER 連線功能</span></b-form-checkbox>
            <b-form-checkbox v-if="false" v-model="enable_office_hours" switch><span title="是否啟用工作天時檢查">啟用排程工作天檢查</span></b-form-checkbox>
            <b-form-checkbox v-if="show_mock_mode_switch" v-model="enable_mock_mode" switch><span title="是否啟用模擬模式">啟用模擬模式</span></b-form-checkbox>
        </b-card>`,
        props: {
            heading: { type: Boolean, default: true }
        },
        data: () => ({
            enable_msdb_query: undefined,
            enable_office_hours: undefined,
            enable_mock_mode: undefined,
            svr_ip: '127.0.0.1'
        }),
        computed: {
            show_mock_mode_switch() { return this.myip != '127.0.0.1' }
        },
        watch: {
            enable_msdb_query(nFlag, oFlag) {
                if (oFlag !== undefined) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.SYSTEM, {
                        type: 'switch_set_mssql_mode',
                        flag: nFlag
                    }).then(res => {
                        this.$warn(res.data.message);
                    }).catch(err => {
                        this.$error(err);
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            },
            enable_office_hours(nFlag, oFlag) {
                if (oFlag !== undefined) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.SYSTEM, {
                        type: 'switch_set_office_hours_mode',
                        flag: nFlag
                    }).then(res => {
                        this.$warn(res.data.message);
                    }).catch(err => {
                        this.$error(err);
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            },
            enable_mock_mode(nFlag, oFlag) {
                if (oFlag !== undefined) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.SYSTEM, {
                        type: nFlag ? 'switch_enable_mock' : 'switch_disable_mock'
                    }).then(res => {
                        this.$warn(res.data.message);
                    }).catch(err => {
                        this.$error(err);
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            },
            configs(val) {
                // system configs loaded 
                this.enable_office_hours = val.ENABLE_OFFICE_HOURS !== 'false';
                this.enable_msdb_query = val.ENABLE_MSSQL_CONN !== 'false';
                this.enable_mock_mode = val.ENABLE_MOCK_MODE !== 'false';
            }
        },
        methods: {
            open () {
                this.openNewWindow(`http://${this.svr_ip}:8080/admin/configs/`);
            },
            popup() {
                this.msgbox({
                    title: "系統設定 相關設定說明",
                    body: `
                        <ul>
                            <li>更新快取資料 - 從地政系統WEB版資料庫快取相關代碼檔至本地端使用。</li>
                            <li>啟用SQL SERVER連線功能 - 有關SQL SERVER查詢都會影響。</li>
                            <li>啟用排程工作天檢查 - 排程工作是否受工作天設定影響。</li>
                            <li>啟用模擬模式 - 伺服器是否只會傳回快取的資料。</li>
                        </ul>
                    `,
                    size: "lg"
                });
            },
            table_import(type) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.SYSTEM, {
                    type: `import_${type}`
                }).then(res => {
                    const succeed = res.data.status > 0;
                    succeed ? this.$error(res.data.message) : this.$warn(res.data.message);
                    this.notify({
                        title: `import_${type}`,
                        message: res.data.message,
                        type: succeed ? 'success' : 'warning'
                    });
                }).catch(err => {
                    this.$error(err);
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        },
        created () {
            this.$axios.post(CONFIG.API.JSON.QUERY, {
                type: 'svr'
            }).then(({ data }) => {
                this.svr_ip = data.ips[data.ips.length - 1]
            }).catch((err) => {
                this.$error = err;
            }).finally(() => {

            })
        }
    });
} else {
    console.error("vue.js not ready ... lah-card relative components can not be loaded.");
}