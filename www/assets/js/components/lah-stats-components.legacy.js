if (Vue) {
    /**
     * Stats Relative Components
     */
    Vue.component("lah-stats-range", {
        template: `<div>
            <h6><lah-fa-icon icon="angle-double-right" variant="primary">篩選條件</lah-fa-icon></h6>
            <b-card class="shadow">
                <b-form-row class="mt-2">
                    <b-input-group size="sm" :prepend="date" class="col">
                        <b-form-input id="stat_range" v-model="value" type="range" :min="1" :max="max" class="h-100"></b-form-input>
                    </b-input-group>
                    <b-input-group size="sm" prepend="筆數大於" class="col-2">
                        <b-form-input
                            type="number"
                            v-model="filter"
                            size="sm"
                            min="0"
                            max="1000"
                            class="no-cache h-100"
                        ></b-form-input>
                    </b-input-group>
                    <b-input-group size="sm" prepend="關鍵字" class="col-2">
                        <b-form-input
                            type="text"
                            v-model="keyword"
                            size="sm"
                            class="no-cache h-100"
                        ></b-form-input>
                        <lah-button v-if="button" icon="edit" size="sm" variant="outline-primary" class="ml-2" @click.stop="update">更新</lah-button>
                    </b-input-group>
                    <b-form-checkbox inline v-model="reg_reason" switch class="h-100 my-auto small">顯示所有</b-form-checkbox>
                </b-form-row>
            </b-card>
        </div>`,
        props: {
            button: {
                type: Boolean,
                default: false
            }
        },
        data: () => ({
            year: 110,
            month: 03,
            base: 0,
            max: 36,
            value: 35,
            filter: 0,
            keyword: '',
            reg_reason: false,
            value_timer: null,
            filter_timer: null,
            keyword_timer: null,
            reg_reason_timer: null,
            delay_ms: 1000
        }),
        computed: {
            date() {
                return `${this.year}${("0" + this.month).slice(-2)}`
            }
        },
        watch: {
            value(nVal, oVal) {
                let after = this.base - this.max + parseInt(nVal) - 1;
                this.year = parseInt(after / 12);
                this.month = after % 12 + 1;
                if (!this.button) {
                    // delay the reload action 
                    clearTimeout(this.value_timer);
                    this.value_timer = this.timeout(() => {
                        this.storeParams['stats_date'] = this.date;
                    }, this.delay_ms);
                }
            },
            filter(nVal, oVal) {
                if (nVal < 0 || nVal > 1000 || isNaN(nVal)) {
                    this.filter = 0;
                }
                if (!this.button) {
                    // delay the reload action 
                    clearTimeout(this.filter_timer);
                    this.filter_timer = this.timeout(() => {
                        this.storeParams['stats_filter'] = nVal;
                    }, this.delay_ms);
                }
            },
            keyword(nVal, oVal) {
                if (!this.button) {
                    clearTimeout(this.keyword_timer);
                    this.keyword_timer = this.timeout(() => {
                        this.storeParams['stats_keyword'] = nVal;
                    }, this.delay_ms);
                }
            },
            reg_reason(nVal, oVal) {
                if (!this.button) {
                    clearTimeout(this.reg_reason_timer);
                    this.reg_reason_timer = this.timeout(() => {
                        this.storeParams['stats_reg_reason'] = nVal;
                    }, this.delay_ms);
                }
            }
        },
        methods: {
            update() {
                this.storeParams['stats_date'] = this.date;
                this.storeParams['stats_filter'] = this.filter;
                this.storeParams['stats_keyword'] = this.keyword;
            }
        },
        mounted() {
            let now = new Date();
            this.year = now.getFullYear() - 1911;
            this.month = now.getMonth(); // set last month as default
            this.value = this.max - 1;
            this.base = this.year * 12 + now.getMonth() + 1;
            // to fix cross year issue
            this.month === 0 && (this.month = 12, this.year--);
            this.addToStoreParams('stats_date', this.date);
            this.addToStoreParams('stats_filter', this.filter);
            this.addToStoreParams('stats_keyword', this.keyword);
            this.addToStoreParams('stats_reg_reason', this.reg_reason);
        }
    });

    Vue.component("lah-stats-dashboard", {
        template: `<div>
            <h6 class="d-flex w-100 justify-content-between mb-0">
                <lah-fa-icon icon="angle-double-right" variant="success">查詢結果</lah-fa-icon>
                <lah-button icon="sync" action="cycle" no-border @click="refresh" variant="outline-secondary" title="重新整理"></lah-button>
            </h6>
            <b-card-group v-if="all" columns>
                <transition-group name="list">
                    <b-card no-body v-for="(item, idx) in items" :key="'stats_'+idx" :border-variant="border_var(item)" class="shadow my-2">
                        <b-list-group-item button class="d-flex justify-content-between align-items-center" @click.stop="query(item)" title="按我取得詳細資料">
                            <div>
                                <lah-button pill icon="file-excel" variant="outline-success" action="move-fade-ltr" title="匯出EXCEL" @click="xlsx(item)"></lah-button>
                                {{empty(item.id) ? '' : item.id+'：'}}{{item.text}}
                            </div>
                            <b-badge :variant="badge_var(item.count)" pill>{{item.count}}</b-badge>
                        </b-list-group-item>
                    </b-card>
                </transition-group>
                <lah-fa-icon v-if="!ok" icon="exclamation-triangle" variant="danger"> 查詢後端資料失敗</lah-fa-icon>
            </b-card-group>
            <b-list-group v-else :title="header">
                <transition-group name="list">
                    <b-list-group-item flush button v-if="ok" v-for="(item, idx) in items" :key="'stats_'+idx" class="d-flex justify-content-between align-items-center" @click.stop="query(item)">
                        <div>
                            <lah-button pill icon="file-excel" variant="outline-success" action="move-fade-ltr" @click="xlsx(item)"></lah-button>
                            {{empty(item.id) ? '' : item.id+'：'}}{{item.text}}
                        </div>
                        <b-badge variant="primary" pill>{{item.count}}</b-badge>
                    </b-list-group-item>
                </transition-group>
                <b-list-group-item v-if="!ok" class="d-flex justify-content-between align-items-center">
                    <lah-fa-icon icon="exclamation-triangle" variant="danger"> 執行查詢失敗 {{category}}</lah-fa-icon>
                </b-list-group-item>
            </b-list-group>
        </div>`,
        props: {
            category: {
                type: String,
                default: 'all'
            },
        },
        data: () => ({
            items: [],
            ok: false,
            default_date: '',
            queue: []
        }),
        computed: {
            date() {
                return this.storeParams['stats_date'] || this.default_date
            },
            keyword() {
                return this.storeParams['stats_keyword'] || ''
            },
            filter() {
                return parseInt(this.storeParams['stats_filter'] || 0)
            },
            all_reg_reason() {
                return this.storeParams['stats_reg_reason'] || false
            },
            header() {
                switch (this.category) {
                    case "stats_court":
                        return `法院囑託案件 (${this.date})`;
                    case "stats_refund":
                        return `主動申請退費案件 (${this.date})`;
                    case "stats_sur_rain":
                        return `因雨延期測量案件 (${this.date})`;
                    case "stats_reg_reason":
                        return `各項登記(特定)原因案件 (${this.date})`;
                    case "stats_reg_reject":
                        return `登記駁回案件 (${this.date})`;
                    case "stats_reg_fix":
                        return `登記補正案件 (${this.date})`;
                    case "stats_reg_all":
                        return `各項登記原因案件 (${this.date})`;
                    case "stats_reg_remote":
                        return `遠途先審案件 (${this.date})`;
                    case "stats_reg_subcase":
                        return `本所處理跨所子號案件 (${this.date})`;
                    case "stats_regf":
                        return `外國人地權登記統計 (${this.date})`;
                    case "all":
                        return `所有支援的統計資料 (${this.date})`;
                    default:
                        return `不支援的類型-${this.category}`;
                }
            },
            all() {
                return this.category == 'all'
            }
        },
        watch: {
            date(nVal, oVal) {
                this.reload()
            },
            filter(nVal, oVal) {
                this.reload()
            },
            keyword(nVal, oVal) {
                this.reload()
            },
            all_reg_reason(nVal, oVal) {
                this.reload()
            }
        },
        methods: {
            refresh() {
                this.$confirm(`確定要清除 ${this.date} 已快取資料?`, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: 'stats_refresh_month',
                        date: this.date
                    }).then(res => {
                        let ok = res.data.status > 0;
                        let msg = res.data.message + " " + this.responseMessage(res.data.status);
                        this.notify({
                            message: msg,
                            type: ok ? "success" : "danger"
                        });
                        if (ok) this.reload();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            border_var(item) {
                switch (item.category) {
                    case "stats_court":
                    case "stats_refund":
                    case "stats_reg_reject":
                    case "stats_reg_fix":
                    case "stats_reg_remote":
                    case "stats_reg_subcase":
                    case "stats_regf":
                        return 'info';
                    case "stats_reg_reason":
                        return 'primary';
                    case "stats_sur_rain":
                        return 'warning';
                    default:
                        return 'secondary';
                }
            },
            badge_var(count) {
                if (count < 10) {
                    return 'secondary';
                } else if (count < 50) {
                    return 'dark';
                } else if (count < 100) {
                    return 'info';
                } else if (count < 200) {
                    return 'primary';
                } else if (count < 400) {
                    return 'success';
                } else if (count < 750) {
                    return 'warning';
                }
                return 'danger';
            },
            get_stats(type) {
                if (this.isBusy) {
                    this.queue.push(this.get_stats.bind(this, type));
                    return;
                }
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.STATS, {
                    type: type,
                    date: this.date
                }).then(res => {
                    this.ok = res.data.status > 0;
                    if (this.ok) {
                        this.$assert(res.data.data_count > 0, "response data count is not correct.", res.data.data_count);
                        for (let i = 0; i < res.data.data_count; i++) {
                            if (res.data.raw[i].count >= this.filter) {
                                // prevent duplication
                                let existed = this.items.find((item, index, array) => {
                                    return item.text == res.data.raw[i].text;
                                });
                                if (existed !== undefined) continue;

                                if (this.empty(this.keyword)) {
                                    this.items.push({
                                        id: res.data.raw[i].id || '',
                                        text: res.data.raw[i].text,
                                        count: res.data.raw[i].count,
                                        category: type
                                    });
                                } else {
                                    let txt = this.keyword.replace("?", ""); // prevent out of memory
                                    let keyword = new RegExp(txt, "i");
                                    if (keyword.test(res.data.raw[i].id) || keyword.test(res.data.raw[i].text)) {
                                        this.items.push({
                                            id: res.data.raw[i].id || '',
                                            text: res.data.raw[i].text,
                                            count: res.data.raw[i].count,
                                            category: type
                                        });
                                    }
                                }
                            }
                        }
                    } else {
                        this.notify({
                            message: res.data.message + " " + this.responseMessage(res.data.status),
                            type: "warning"
                        });
                        this.$warn(type + " " + this.responseMessage(res.data.status) + " " + res.data.status);
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                    let callback = this.queue.pop();
                    if (callback) {
                        callback();
                    }
                });
            },
            reload_stats_cache(type) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.STATS, {
                    type: type,
                    date: this.date,
                    reload: true
                }).then(res => {
                    this.ok = res.data.status > 0;
                    if (this.ok) {
                        this.notify({
                            message: type + " (" + this.date + ") 已成功更新" + this.responseMessage(res.data.status),
                            type: "success"
                        });
                    } else {
                        this.notify({
                            message: res.data.message + " " + this.responseMessage(res.data.status),
                            type: "warning"
                        });
                        this.$warn(type + " " + this.responseMessage(res.data.status));
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            reload() {
                this.items = [];
                switch (this.category) {
                    case "stats_court":
                    case "stats_refund":
                    case "stats_sur_rain":
                    case "stats_reg_reason":
                    case "stats_reg_reject":
                    case "stats_reg_fix":
                    case "stats_reg_all":
                    case "stats_reg_remote":
                    case "stats_reg_subcase":
                    case "stats_regf":
                        this.get_stats(this.category);
                        break;
                    case "all":
                        this.get_stats('stats_reg_subcase');
                        this.get_stats('stats_reg_remote');
                        this.get_stats('stats_court');
                        this.get_stats('stats_refund');
                        this.get_stats('stats_sur_rain');
                        this.get_stats('stats_reg_reject');
                        this.get_stats('stats_reg_fix');
                        this.get_stats('stats_regf');
                        this.timeout(() => this.all_reg_reason ? this.get_stats('stats_reg_all') : this.get_stats('stats_reg_reason'), 1000);
                        break;
                    default:
                        this.$warn("Not supported category.", this.category);
                        this.alert({
                            message: "lah-stats-item: Not supported category.【" + this.category + "】",
                            type: "warning"
                        });
                }
            },
            showRegCases(title, data) {
                this.msgbox({
                    title: title,
                    message: this.$createElement('lah-reg-table', {
                        props: {
                            bakedData: data,
                            iconVariant: "success",
                            icon: "chevron-circle-right",
                            type: 'md'
                        }
                    }),
                    size: 'xl'
                });
            },
            showRegularCases(title, data) {
                this.msgbox({
                    title: title,
                    message: this.$createElement('b-table', {
                        props: {
                            striped: true,
                            hover: true,
                            headVariant: 'dark',
                            bordered: true,
                            captionTop: true,
                            caption: `找到 ${data.length} 件`,
                            items: data
                        }
                    }),
                    size: 'xl'
                });
            },
            xhr(type, title, reason_code = undefined) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: type,
                    query_month: this.date,
                    reason_code: reason_code
                }).then(res => {
                    if (
                        res.data.status == XHR_STATUS_CODE.SUCCESS_WITH_MULTIPLE_RECORDS ||
                        res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                    ) {
                        if (title == "主動退費案件" || title == "測量因雨延期案件" || title == "遠途先審案件" || title == "本所處理跨所子號案件" || title == "外國人地權登記統計") {
                            this.showRegularCases(title, res.data.raw);
                            // e.g. stats_regf may need to reload the stats count since it will have data after 1st day of month ... 
                            this.sync_data_count(title, res.data.raw);
                        } else {
                            this.showRegCases(title, res.data.baked);
                        }
                    } else {
                        let err = this.responseMessage(res.data.status);
                        this.$warn(err);
                        this.notify({
                            message: err,
                            type: "warning"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            query(item) {
                if (this.empty(item.id)) {
                    switch (item.category) {
                        case "stats_court":
                            this.xhr('reg_court_cases_by_month', '法院囑託案件');
                            break;
                        case "stats_reg_fix":
                            this.xhr('reg_fix_cases_by_month', '登記補正案件');
                            break;
                        case "stats_reg_reject":
                            this.xhr('reg_reject_cases_by_month', '登記駁回案件');
                            break;
                        case "stats_refund":
                            this.xhr('expba_refund_cases_by_month', '主動退費案件');
                            break;
                        case "stats_sur_rain":
                            this.xhr('sur_rain_cases_by_month', '測量因雨延期案件');
                            break;
                        case "stats_reg_remote":
                            this.xhr('reg_remote_cases_by_month', '遠途先審案件');
                            break;
                        case "stats_reg_subcase":
                            this.xhr('reg_subcases_by_month', '本所處理跨所子號案件');
                            break;
                        case "stats_regf":
                            this.xhr('regf_by_month', '外國人地權登記統計');
                            break;
                        default:
                            this.$warn("無登記原因代碼，無法查詢案件。");
                            this.notify({
                                message: '本項目未支援取得詳細列表功能',
                                type: "warning"
                            })
                    }
                } else {
                    this.$log(item.category);
                    this.xhr('reg_reason_cases_by_month', item.text, item.id);
                }
            },
            xlsx_export(item) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'xlsx_params',
                    xlsx_type: 'stats_export',
                    xlsx_item: Object.assign({
                        query_month: this.date
                    }, item)
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.notify({
                            title: '匯出EXCEL檔案',
                            message: '<i class="fas fa-cog ld ld-spin"></i> 後端處理中 ... ',
                            type: "warning",
                            duration: 2000
                        });
                        // second param usage => e.target.title to get the title
                        this.open(CONFIG.API.FILE.XLSX, {
                            target: {
                                title: '下載XLSX'
                            }
                        });
                        this.timeout(() => closeModal(() => this.notify({
                            title: '匯出EXCEL檔案',
                            message: '<i class="fas fa-check ld ld-pulse"></i> 後端作業完成',
                            type: "success"
                        })), 2000);
                    } else {
                        let err = this.responseMessage(res.data.status);
                        let message = `${err} - ${res.data.status}`;
                        this.$warn(`紀錄 XLSX 參數失敗: ${message}`);
                        this.alert({
                            title: '紀錄 XLSX 參數',
                            message: message,
                            type: "danger"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            xlsx(item) {
                // item.id is reg reason code
                switch (item.category) {
                    case "stats_court":
                    case "stats_reg_fix":
                    case "stats_reg_reject":
                    case "stats_refund":
                    case "stats_sur_rain":
                    case "stats_reg_remote":
                    case "stats_reg_subcase":
                    case "stats_regf":
                    case "stats_reg_reason":
                        this.xlsx_export(item);
                        break;
                    default:
                        this.$warn("無分類代碼，無法匯出資料。", item);
                        this.notify({
                            message: '本項目未支援匯出XLSX功能',
                            type: "warning"
                        })
                }
            },
            sync_data_count(title, qry_data) {
                // NOTE: use title to check the count sync
                let need_to_sync = this.items.filter((item, index, array) => {
                    return qry_data.length != item.count && item.text == title;
                });
                if (need_to_sync) {
                    need_to_sync.forEach(element => {
                        this.reload_stats_cache(element.category);
                    });
                }
            }
        },
        mounted() {
            // set default to the last month, e.g. 10904
            let now = new Date();
            this.default_date = now.getFullYear() - 1911 + ("0" + (now.getMonth())).slice(-2);
        }
    });
} else {
    console.error("vue.js not ready ... lah-stats relative components can not be loaded.");
}