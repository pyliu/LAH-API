if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<lah-transition appear>
            <b-card border-variant="secondary" class="shadow" v-b-visible="visible">
                <lah-chart ref="chart" :label="label" :items="items" :type="type" :bg-color="bg_color" @click="show_count" :aspect-ratio="aspectRatio"></lah-chart>
                <div class="d-flex justify-content-between mt-1">
                    <span class="align-middle small my-auto">
                        <lah-fa-icon :icon="network_icon" title="現在連線數" :action="icon_action" :size="icon_size"> <b-badge :variant="badge_variant">{{now_count}}</b-badge></lah-fa-icon>
                        <lah-fa-icon icon="clock" prefix="far" title="更新時間">
                            <b-badge v-if="isOfficeHours() || demo" variant="secondary">{{last_update_time}}</b-badge>
                            <b-badge v-else variant="danger" title="非上班時間所以停止更新">已停止更新</b-badge>
                        </lah-fa-icon>
                    </span>
                    <b-button-group size="sm">
                        <lah-button icon="chart-bar" variant="primary" v-if="type != 'bar'" @click="type = 'bar'" title="切換長條圖"></lah-button>
                        <lah-button icon="chart-line" variant="success" v-if="type != 'line'" @click="type = 'line'" title="切換線型圖"></lah-button>
                        <b-form-spinbutton v-model="mins" min="5" max="60" size="sm" inline class="h-100"></b-form-spinbutton>
                        <lah-button v-if="popupButton" regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popup" action="heartbeat"></lah-button>
                    </b-button-group>
                </div>
                <div class="d-flex justify-content-between position-absolute w-100 mt-3" style="top:0;left:0;">
                    <lah-button icon="chevron-left" variant="outline-muted" size="sm" action="pulse" @click="nav_left"></lah-button>
                    <lah-button icon="chevron-right" variant="outline-muted" size="sm" action="pulse" @click="nav_right"></lah-button>
                </div>
            </b-card>
        </lah-transition>`,
        props: {
            watchTopSiteIndex: { type: Number, default: -1 },
            site: {
                type: String,
                default: 'H0'
            },
            mins: {
                type: Number,
                default: 15
            },
            type: {
                type: String,
                default: 'bar'
            },
            demo: {
                type: Boolean,
                default: false
            },
            popupButton: {
                type: Boolean,
                default: true
            },
            aspectRatio: {
                type: Number,
                default: 2
            }
        },
        data: () => ({
            items: [],
            reload_timer: null,
            spin_timer: null,
            site_tw: '地政局',
            site_code: 'H0',
            last_update_time: '',
            now_count: 0
        }),
        watch: {
            disableOfficeHours(val) { if (val) this.reload(true) },
            mins(dont_care) {
                clearTimeout(this.spin_timer);
                this.spin_timer = this.timeout(() => {
                    this.items.length = 0;
                    this.reload(true);
                }, 1000);
            },
            site(val) {
                this.site_code = val
            },
            site_code(val) {
                this.site_tw = '未知';
                for (var value of this.xapMap.values()) {
                    if (value.code == val) {
                        this.site_tw = value.name;
                    }
                }
                this.items.length = 0;
                this.reload(true);
            },
            demo(val) {
                this.reload()
            },
            top_site(arr) {
                if (this.watchTopSiteIndex != -1 && Array.isArray(arr) && arr[this.watchTopSiteIndex]) {
                    this.site = arr[this.watchTopSiteIndex];
                }
            }
        },
        computed: {
            timer_ms() {
                return this.demo ? 5000 : 60000
            },
            label() {
                return `${this.site_tw}`
            },
            title() {
                return `${this.site_tw}連線`
            },
            network_icon() {
                [variant, action, rgb, icon, size] = this.style_by_count(this.now_count);
                return icon;
            },
            badge_variant() {
                [variant, action, rgb, icon, size] = this.style_by_count(this.now_count);
                return variant;
            },
            icon_action() {
                [variant, action, rgb, icon, size] = this.style_by_count(this.now_count);
                return action;
            },
            icon_size() {
                [variant, action, rgb, icon, size] = this.style_by_count(this.now_count);
                return size;
            },
            top_site() { return this.storeParams['XAP_CONN_TOP_SITES'] },
            ip() {
                for (var value of this.xapMap.values()) {
                    if (value.code == this.site_code) {
                        return value.ip;
                    }
                }
                return false;
            }
        },
        methods: {
            style_by_count(value, opacity = 0.6) {
                let variant, action, rgb, icon, size = '';
                if (value > 800) {
                    icon = 'network-wired';
                    variant = 'danger';
                    action = 'surprise';
                    rgb = `rgb(179, 0, 255, ${opacity})`;
                    size = '5x';
                } // bright purple
                else if (value > 600) {
                    icon = 'network-wired';
                    variant = 'danger';
                    action = 'coin-h';
                    rgb = `rgb(114, 0, 159, ${opacity})`;
                    size = '4x';
                } // dark purple
                else if (value > 400) {
                    icon = 'network-wired';
                    variant = 'danger';
                    action = 'shiver';
                    rgb = `rgb(255, 0, 19, ${opacity})`;
                    size = '3x';
                } // bright red
                else if (value > 200) {
                    icon = 'network-wired';
                    variant = 'danger';
                    action = 'tremble';
                    rgb = `rgb(200, 0, 19, ${opacity})`;
                    size = '2x';
                } // dark red
                else if (value > 100) {
                    icon = 'network-wired';
                    variant = 'warning';
                    action = 'beat';
                    rgb = `rgb(238, 182, 1, ${opacity})`;
                    size = 'lg';
                } // yellow
                else if (value > 5) {
                    icon = 'network-wired';
                    variant = 'success';
                    action = 'jump';
                    rgb = `rgb(0, 200, 0, ${opacity})`
                } // green
                else {
                    icon = 'ethernet';
                    variant = 'muted';
                    action = '';
                    rgb = `rgb(207, 207, 207, ${opacity})`;
                } // muted
                return [variant, action, rgb, icon, size]
            },
            bg_color(dataset_item, opacity) {
                [variant, action, rgb, icon, size] = this.style_by_count(dataset_item[1], opacity);
                return rgb;
            },
            set_items(raw) {
                raw.forEach((item, raw_idx, raw) => {
                    /*
                        item = {
                            log_time: '20201005181631',
                            ap_ip: '220.1.35.123',
                            est_ip: '220.1.35.36',
                            count: '2',
                            batch: '490',
                            name: '資訊主機'
                        }
                    */
                    let text = (raw_idx == 0) ? '現在' : `${raw_idx}分前`;
                    let val = item.count;
                    if (this.items.length == raw.length) {
                        this.items[raw_idx][1] = val;
                        // not reactively ... manual set chartData
                        this.$refs.chart.changeValue(text, val);
                    } else {
                        this.items.push([text, val]);
                    }
                });
                this.last_update_time = this.now().split(' ')[1];
                this.now_count = this.items[0][1];
            },
            reload(force) {
                clearTimeout(this.reload_timer);
                if (this.demo && this.items.length > 0) {
                    this.reload_demo_data();
                    this.reload_timer = this.timeout(this.reload, this.timer_ms);
                } else if (force || this.isOfficeHours()) {
                    this.request();
                } else {
                    // check after an hour
                    this.reload_timer = this.timeout(this.reload, 3600000);
                }
            },
            request() {
                //this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.STATS, {
                    type: "stats_ap_conn_history",
                    ap_ip: this.ip,
                    count: parseInt(this.mins) + 1
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        if (res.data.data_count == 0) {
                            this.notify({
                                title: `跨所 AP ${this.site_tw} 連線趨勢圖`,
                                subtitle: this.site_tw,
                                message: '無資料，無法繪製圖形',
                                type: 'warning'
                            });
                        } else {
                            this.set_items(res.data.raw);
                        }
                    } else {
                        this.alert({
                            title: `跨所 AP ${this.site_tw} 連線趨勢圖`,
                            message: `取得跨所 AP ${this.site_tw} ${this.ip} 連線資料失敗。【${res.data.status}】`,
                            variant: "warning"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    //this.isBusy = false;
                    this.reload_timer = this.timeout(this.reload, this.timer_ms);
                    Vue.nextTick(() => {
                        this.$refs.chart.update();
                    });
                });
            },
            reload_demo_data() {
                this.items.forEach((item, raw_idx, raw) => {
                    let val = this.rand(300);
                    item[1] = val;
                    // not reactively ... manual set chartData
                    this.$refs.chart.changeValue(item[0], val);
                });
                this.last_update_time = this.now().split(' ')[1];
                this.now_count = this.items[0][1];
                this.type = this.now_count % 2 == 0 ? 'bar' : 'line';
            },
            visible(isVisible) {
                if (isVisible) {
                    this.reload_timer = this.reload(true);
                } else {
                    clearTimeout(this.reload_timer);
                }
            },
            popup() {
                this.msgbox({
                    title: `跨所AP ${this.site_tw}連線`,
                    message: this.$createElement('lah-xap-history-chart', {
                        props: {
                            site: this.site_code,
                            mins: 60,
                            demo: this.demo,
                            popupButton: false,
                            aspectRatio: this.viewportRatio
                        }
                    }),
                    size: "xl"
                });
            },
            show_count(e, payload) {
                if (this.empty(payload['label'])) return;
                [variant, action, rgb, icon, size] = this.style_by_count(payload['value']);
                this.notify({
                    title: `${this.site_tw}連線數`,
                    subtitle: `${payload['label']}`,
                    message: `<i class="fas fa-network-wired ld ld-${action}"></i> ${payload['value']}`,
                    type: variant
                });
            },
            nav_left() {
                switch (this.site_code) {
                    case 'H0':
                        this.site_code = 'HH';
                        break;
                    case 'HA':
                        this.site_code = 'H0';
                        break;
                    case 'HB':
                        this.site_code = 'HA';
                        break;
                    case 'HC':
                        this.site_code = 'HB';
                        break;
                    case 'HD':
                        this.site_code = 'HC';
                        break;
                    case 'HE':
                        this.site_code = 'HD';
                        break;
                    case 'HF':
                        this.site_code = 'HE';
                        break;
                    case 'HG':
                        this.site_code = 'HF';
                        break;
                    case 'HH':
                        this.site_code = 'HG';
                        break;
                    default:
                        this.site_code = '未知';
                }
                this.reload(true);
            },
            nav_right() {
                switch (this.site_code) {
                    case 'H0':
                        this.site_code = 'HA';
                        break;
                    case 'HA':
                        this.site_code = 'HB';
                        break;
                    case 'HB':
                        this.site_code = 'HC';
                        break;
                    case 'HC':
                        this.site_code = 'HD';
                        break;
                    case 'HD':
                        this.site_code = 'HE';
                        break;
                    case 'HE':
                        this.site_code = 'HF';
                        break;
                    case 'HF':
                        this.site_code = 'HG';
                        break;
                    case 'HG':
                        this.site_code = 'HH';
                        break;
                    case 'HH':
                        this.site_code = 'H0';
                        break;
                    default:
                        this.site_code = '未知';
                }
                this.reload(true);
            }
        },
        created() {
            this.site_code = this.site;
            this.reload(true);
        }
    });
} else {
    console.error("vue.js not ready ... lah-xap-history-chart component can not be loaded.");
}