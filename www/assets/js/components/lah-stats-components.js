if (Vue) {
    /**
     * Stats Relative Components
     */
    Vue.component("lah-stats-range", {
        template: `<div class="st-filter-card">
            <div class="st-filter-header">
                <span><i class="fas fa-sliders-h mr-1"></i> 篩選與月份設定</span>
                <span class="st-date-pill"><i class="far fa-calendar-alt mr-1"></i> 統計月份：{{ date.substr(0, 3) }} 年 {{ date.substr(3, 2) }} 月 ({{ date }})</span>
            </div>
            <b-form-row class="align-items-center">
                <div class="col-xl-3 col-lg-4 col-md-6 mb-2 mb-xl-0">
                    <b-input-group size="sm" prepend="統計年月">
                        <b-input-group-prepend>
                            <b-button variant="outline-secondary" @click="stepMonth(-1)" title="上個月">
                                <i class="fas fa-chevron-left"></i> 上月
                            </b-button>
                        </b-input-group-prepend>
                        <b-form-input
                            id="stat_range"
                            v-model="ym_input"
                            type="text"
                            maxlength="5"
                            placeholder="例: 11509"
                            :state="ym_valid"
                            @input="onYmInput"
                            @blur="normalizeYm"
                            @keyup.enter="applyYmNow"
                            class="text-center font-weight-bold no-cache h-100"
                        ></b-form-input>
                        <b-input-group-append>
                            <b-button variant="outline-secondary" @click="stepMonth(1)" :disabled="!canNextMonth" title="下個月">
                                下月 <i class="fas fa-chevron-right"></i>
                            </b-button>
                        </b-input-group-append>
                    </b-input-group>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-6 mb-2 mb-xl-0">
                    <b-input-group size="sm" prepend="筆數 ≥">
                        <b-form-input
                            type="number"
                            v-model="filter"
                            size="sm"
                            min="0"
                            max="1000"
                            class="no-cache h-100"
                        ></b-form-input>
                    </b-input-group>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 mb-2 mb-md-0">
                    <b-input-group size="sm" prepend="排序">
                        <b-form-select
                            v-model="sort_by"
                            :options="sort_options"
                            size="sm"
                            class="no-cache h-100"
                        ></b-form-select>
                    </b-input-group>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-5 mb-2 mb-md-0">
                    <b-input-group size="sm" prepend="關鍵字">
                        <b-form-input
                            type="text"
                            v-model="keyword"
                            size="sm"
                            placeholder="代碼或原因名稱…"
                            class="no-cache h-100"
                        ></b-form-input>
                        <lah-button v-if="button" icon="edit" size="sm" variant="outline-primary" class="ml-2" @click="update">更新</lah-button>
                    </b-input-group>
                </div>
                <div class="col-xl-2 col-lg-12 col-md-3 text-md-right">
                    <b-form-checkbox inline v-model="reg_reason" switch class="my-auto small font-weight-bold">顯示所有原因</b-form-checkbox>
                </div>
            </b-form-row>
        </div>`,
        props: {
            button: {
                type: Boolean,
                default: false
            }
        },
        data: () => ({
            year: 110,
            month: 3,
            ym_input: '',
            max_ym: '',
            base: 0,
            max: 36,
            value: 35,
            filter: 0,
            sort_by: 'default',
            sort_options: [
                { value: 'default', text: '預設分類' },
                { value: 'count_desc', text: '數量：多 → 少' },
                { value: 'count_asc', text: '數量：少 → 多' },
                { value: 'code_asc', text: '代碼 / 名稱' }
            ],
            keyword: '',
            reg_reason: false,
            value_timer: null,
            filter_timer: null,
            keyword_timer: null,
            reg_reason_timer: null,
            delay_ms: 600
        }),
        computed: {
            date() {
                return `${("00" + this.year).slice(-3)}${("0" + this.month).slice(-2)}`
            },
            ym_valid() {
                if (!/^\d{5}$/.test(this.ym_input)) return false;
                const y = parseInt(this.ym_input.substr(0, 3), 10);
                const m = parseInt(this.ym_input.substr(3, 2), 10);
                if (y < 100 || m < 1 || m > 12) return false;
                if (this.max_ym && this.ym_input > this.max_ym) return false;
                return null;
            },
            canNextMonth() {
                if (!this.max_ym) return true;
                return this.date < this.max_ym;
            }
        },
        watch: {
            sort_by(nVal) {
                this.storeParams['stats_sort'] = nVal;
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
            onYmInput(val) {
                const digits = String(val || '').replace(/\D/g, '').slice(0, 5);
                if (digits !== this.ym_input) {
                    this.ym_input = digits;
                }
                if (digits.length === 5) {
                    const y = parseInt(digits.substr(0, 3), 10);
                    const m = parseInt(digits.substr(3, 2), 10);
                    if (y >= 100 && m >= 1 && m <= 12 && (!this.max_ym || digits <= this.max_ym)) {
                        this.year = y;
                        this.month = m;
                        if (!this.button) {
                            clearTimeout(this.value_timer);
                            this.value_timer = this.timeout(() => {
                                this.storeParams['stats_date'] = this.date;
                            }, this.delay_ms);
                        }
                    }
                }
            },
            applyYmNow() {
                this.normalizeYm();
                clearTimeout(this.value_timer);
                this.storeParams['stats_date'] = this.date;
            },
            normalizeYm() {
                if (this.ym_valid === false) {
                    this.ym_input = this.date;
                }
            },
            stepMonth(delta) {
                let totalMonths = this.year * 12 + (this.month - 1) + delta;
                let nYear = Math.floor(totalMonths / 12);
                let nMonth = (totalMonths % 12) + 1;
                const nextYm = `${("00" + nYear).slice(-3)}${("0" + nMonth).slice(-2)}`;
                if (nYear < 100) return;
                if (this.max_ym && nextYm > this.max_ym) return;
                this.year = nYear;
                this.month = nMonth;
                this.ym_input = nextYm;
                if (!this.button) {
                    clearTimeout(this.value_timer);
                    this.value_timer = this.timeout(() => {
                        this.storeParams['stats_date'] = this.date;
                    }, 300);
                }
            },
            update() {
                this.normalizeYm();
                this.storeParams['stats_date'] = this.date;
                this.storeParams['stats_filter'] = this.filter;
                this.storeParams['stats_keyword'] = this.keyword;
            }
        },
        mounted() {
            let now = new Date();
            let curY = now.getFullYear() - 1911;
            let curM = now.getMonth() + 1;
            this.max_ym = `${("00" + curY).slice(-3)}${("0" + curM).slice(-2)}`;
            this.year = curY;
            this.month = now.getMonth(); // set last month as default
            this.base = this.year * 12 + now.getMonth() + 1;
            // to fix cross year issue
            this.month === 0 && (this.month = 12, this.year--);
            this.ym_input = this.date;
            this.addToStoreParams('stats_date', this.date);
            this.addToStoreParams('stats_filter', this.filter);
            this.addToStoreParams('stats_sort', this.sort_by);
            this.addToStoreParams('stats_keyword', this.keyword);
            this.addToStoreParams('stats_reg_reason', this.reg_reason);
        }
    });

    Vue.component("lah-stats-dashboard", {
        template: `<div>
            <div class="st-kpi-grid" v-if="all">
                <div class="st-kpi">
                    <div>
                        <div class="st-kpi-label">統計項目數</div>
                        <div class="st-kpi-num">{{ items.length }}</div>
                    </div>
                    <div class="st-kpi-ico brand"><i class="fas fa-th-large"></i></div>
                </div>
                <div class="st-kpi">
                    <div>
                        <div class="st-kpi-label">案件總計筆數</div>
                        <div class="st-kpi-num">{{ items.reduce((s, it) => s + (parseInt(it.count) || 0), 0) }}</div>
                    </div>
                    <div class="st-kpi-ico ok"><i class="fas fa-calculator"></i></div>
                </div>
                <div class="st-kpi">
                    <div>
                        <div class="st-kpi-label">專項業務指標</div>
                        <div class="st-kpi-num">{{ items.filter(it => it.category !== 'stats_reg_reason' && it.category !== 'stats_reg_all').length }}</div>
                    </div>
                    <div class="st-kpi-ico info"><i class="fas fa-briefcase"></i></div>
                </div>
                <div class="st-kpi">
                    <div>
                        <div class="st-kpi-label">登記原因指標</div>
                        <div class="st-kpi-num">{{ items.filter(it => it.category === 'stats_reg_reason' || it.category === 'stats_reg_all').length }}</div>
                    </div>
                    <div class="st-kpi-ico warn"><i class="fas fa-tags"></i></div>
                </div>
            </div>
            <div class="st-sec-bar">
                <div class="st-sec-title">
                    <i class="fas fa-chart-pie text-primary mr-1"></i>
                    <span>查詢結果</span>
                    <small class="st-sec-sub" v-if="date">（{{ date.substr(0, 3) }} 年 {{ date.substr(3, 2) }} 月，點擊卡片檢視明細，點擊左測圖示匯出 EXCEL）</small>
                </div>
                <lah-button icon="sync" action="cycle" size="sm" @click="refresh" variant="outline-primary" class="st-refresh-btn" title="清除快取並重新整理">重新整理快取</lah-button>
            </div>
            <div v-if="all">
                <transition-group name="list" tag="div" class="st-grid">
                    <div v-for="(item, idx) in sortedItems" :key="'stats_' + item.category + '_' + (item.id || idx)" class="st-item" :class="'cat-' + border_var(item)" @click.stop="query(item)" title="按我取得詳細資料">
                        <div class="st-item-main">
                            <lah-button pill icon="file-excel" size="sm" variant="outline-success" action="move-fade-ltr" title="匯出EXCEL" @click="xlsx(item)" class="st-xlsx-btn"></lah-button>
                            <div class="st-item-text">
                                <span v-if="!empty(item.id)" class="st-code">{{ item.id }}</span>
                                <span class="st-name">{{ item.text }}</span>
                            </div>
                        </div>
                        <b-badge :variant="badge_var(item.count)" pill class="st-count">{{ item.count }}</b-badge>
                    </div>
                </transition-group>
                <div v-if="!isBusy && items.length === 0" class="st-empty">
                    <i class="far fa-folder-open"></i>
                    <div>{{ ok ? '沒有符合篩選條件的統計項目' : '查詢後端資料失敗或尚無統計資料' }}</div>
                </div>
            </div>
            <b-list-group v-else :title="header" class="st-list">
                <transition-group name="list">
                    <b-list-group-item flush button v-if="ok" v-for="(item, idx) in sortedItems" :key="'stats_'+idx" class="d-flex justify-content-between align-items-center" @click.stop="query(item)">
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
            sort_by() {
                return this.storeParams['stats_sort'] || 'default'
            },
            sortedItems() {
                const list = this.items.slice();
                if (this.sort_by === 'count_desc') {
                    return list.sort((a, b) => (parseInt(b.count, 10) || 0) - (parseInt(a.count, 10) || 0));
                }
                if (this.sort_by === 'count_asc') {
                    return list.sort((a, b) => (parseInt(a.count, 10) || 0) - (parseInt(b.count, 10) || 0));
                }
                if (this.sort_by === 'code_asc') {
                    return list.sort((a, b) => {
                        const codeA = a.id || '';
                        const codeB = b.id || '';
                        if (codeA !== codeB) {
                            return codeA.localeCompare(codeB, 'zh-Hant');
                        }
                        return (a.text || '').localeCompare(b.text || '', 'zh-Hant');
                    });
                }
                return list;
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
                    const label = this.empty(item.text) ? `登記原因 ${item.id}` : `${item.id}：${item.text}`;
                    this.xhr('reg_reason_cases_by_month', label, item.id);
                }
            },
            xlsx_export(item) {
                if (typeof XLSX === 'undefined') {
                    this.alert({
                        title: '匯出 EXCEL 檔案',
                        message: '前端 XLSX 套件尚未載入，請重新整理頁面再試。',
                        type: 'danger'
                    });
                    return;
                }
                let qType = '';
                let isRegCase = false;
                if (this.empty(item.id)) {
                    switch (item.category) {
                        case "stats_court":
                            qType = 'reg_court_cases_by_month';
                            isRegCase = true;
                            break;
                        case "stats_reg_fix":
                            qType = 'reg_fix_cases_by_month';
                            isRegCase = true;
                            break;
                        case "stats_reg_reject":
                            qType = 'reg_reject_cases_by_month';
                            isRegCase = true;
                            break;
                        case "stats_refund":
                            qType = 'expba_refund_cases_by_month';
                            break;
                        case "stats_sur_rain":
                            qType = 'sur_rain_cases_by_month';
                            break;
                        case "stats_reg_remote":
                            qType = 'reg_remote_cases_by_month';
                            break;
                        case "stats_reg_subcase":
                            qType = 'reg_subcases_by_month';
                            break;
                        case "stats_regf":
                            qType = 'regf_by_month';
                            break;
                        default:
                            this.notify({ message: '本項目未支援匯出XLSX功能', type: 'warning' });
                            return;
                    }
                } else {
                    qType = 'reg_reason_cases_by_month';
                    isRegCase = true;
                }

                this.isBusy = true;
                this.notify({
                    title: '匯出 EXCEL 檔案',
                    message: `<i class="fas fa-cog ld ld-spin"></i> 正在擷取「${item.text}」資料並產生 XLSX ...`,
                    type: 'info',
                    duration: 2000
                });

                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: qType,
                    query_month: this.date,
                    reason_code: item.id || undefined
                }).then(res => {
                    if (
                        res.data.status == XHR_STATUS_CODE.SUCCESS_WITH_MULTIPLE_RECORDS ||
                        res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                    ) {
                        let rows = isRegCase ? (res.data.baked || []) : (res.data.raw || []);
                        if (!rows || rows.length === 0) {
                            this.notify({ title: '匯出 EXCEL 檔案', message: '查無明細資料可匯出', type: 'warning' });
                            return;
                        }
                        let exportRows = [];
                        if (isRegCase) {
                            const regCols = [
                                '收件字號', '收件時間', '登記原因', '辦理情形',
                                '收件人員', '作業人員', '初審人員', '複審人員',
                                '准登人員', '登錄人員', '校對人員', '結案人員', '結案狀態'
                            ];
                            exportRows = rows.map(r => {
                                let o = {};
                                regCols.forEach(k => { o[k] = r[k] !== undefined && r[k] !== null ? String(r[k]) : ''; });
                                return o;
                            });
                        } else {
                            exportRows = rows.map(r => {
                                let o = {};
                                Object.keys(r).forEach(k => { o[k] = r[k] !== undefined && r[k] !== null ? String(r[k]) : ''; });
                                return o;
                            });
                        }

                        const ws = XLSX.utils.json_to_sheet(exportRows);
                        // Force all data cells as string type so leading zeros are preserved
                        const range = XLSX.utils.decode_range(ws['!ref'] || 'A1');
                        const colWidths = [];
                        for (let C = range.s.c; C <= range.e.c; ++C) {
                            let maxLen = 10;
                            for (let R = range.s.r; R <= range.e.r; ++R) {
                                const addr = XLSX.utils.encode_cell({ r: R, c: C });
                                const cell = ws[addr];
                                if (cell && cell.v !== undefined) {
                                    cell.t = 's';
                                    cell.v = String(cell.v);
                                    const len = cell.v.replace(/[^\x00-\xff]/g, 'xx').length;
                                    if (len > maxLen) maxLen = len;
                                }
                            }
                            colWidths.push({ wch: Math.min(maxLen + 3, 42) });
                        }
                        ws['!cols'] = colWidths;

                        const wb = XLSX.utils.book_new();
                        const sheetName = (item.text || '統計明細').replace(/[\\/?*[\]:]/g, '').slice(0, 31) || 'Sheet1';
                        XLSX.utils.book_append_sheet(wb, ws, sheetName);

                        const d = new Date();
                        const today = `${d.getFullYear() - 1911}${('0' + (d.getMonth() + 1)).slice(-2)}${('0' + d.getDate()).slice(-2)}`;
                        const codePrefix = this.empty(item.id) ? '' : `${item.id}_`;
                        const filename = `${today}_${this.date}_${codePrefix}${item.text}.xlsx`;
                        XLSX.writeFile(wb, filename);

                        this.notify({
                            title: '匯出 EXCEL 檔案',
                            message: `<i class="fas fa-check"></i> 已下載 <b>${filename}</b>（共 ${exportRows.length} 筆）`,
                            type: 'success'
                        });
                    } else {
                        let err = this.responseMessage(res.data.status);
                        this.notify({ title: '匯出 EXCEL 檔案', message: err, type: 'warning' });
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
                    case "stats_reg_all":
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