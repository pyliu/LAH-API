if (Vue) {
    Vue.component('lah-ap-connection-history-chart', {
        template: `<lah-transition appear>
            <b-card border-variant="secondary" class="shadow" v-b-visible="visible">
                <lah-chart ref="chart" :label="label" :items="items" :type="type" :title="title" title-pos="left" :aspect-ratio="aspectRatio" :bg-color="bg_color"></lah-chart>
                <div class="d-flex justify-content-between mt-1">
                    <span class="small align-middle my-auto">
                    <b-form-checkbox v-model="allSwitch" switch inline><span title="全部/使用者切換">{{all_switch_desc}}</span></b-form-checkbox>
                    <lah-fa-icon icon="database" title="資料庫連線數"> <b-badge variant="muted" pill>{{db_count}}</b-badge></lah-fa-icon>
                        <lah-fa-icon icon="clock" prefix="far" title="更新時間" variant="secondary">
                            <b-badge v-if="isOfficeHours() || demo" variant="muted">{{last_update_time}}</b-badge>
                            <b-badge v-else variant="danger" title="非上班時間所以停止更新">已停止更新</b-badge>
                        </lah-fa-icon>
                    </span>
                    <div :title="ip">
                        <b-button-group size="sm">
                            <lah-button icon="chart-bar" variant="primary" v-if="type != 'bar'" @click="type = 'bar'" title="切換長條圖"></lah-button>
                            <lah-button icon="chart-pie" variant="secondary" v-if="type != 'pie'" @click="type = 'pie'" title="切換圓餅圖"></lah-button>
                            <lah-button icon="chart-line" variant="success" v-if="type != 'line' && items.length > 2" @click="type = 'line'" title="切換長線型圖"></lah-button>
                            <lah-button icon="chart-area" variant="warning" v-if="type != 'polarArea'" @click="type = 'polarArea'" title="切換區域圖"></lah-button>
                            <lah-button brand icon="edge" variant="info" v-if="type != 'doughnut'" @click="type = 'doughnut'" title="切換甜甜圈"></lah-button>
                            <lah-button icon="broadcast-tower" variant="dark" v-if="type != 'radar' && items.length > 2" @click="type = 'radar'" title="切換雷達圖"></lah-button>
                            <lah-button v-if="popupButton" regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popup" action="heartbeat"></lah-button>
                        </b-button-group>
                    </div>
                </div>
                <div class="d-flex justify-content-between position-absolute w-100 mt-3" style="top:0;left:0;">
                    <lah-button icon="chevron-left" variant="outline-muted" size="sm" action="pulse" @click="nav_left"></lah-button>
                    <lah-button icon="chevron-right" variant="outline-muted" size="sm" action="pulse" @click="nav_right"></lah-button>
                </div>
            </b-card>
        </lah-transition>`,
        props: {
            ip: {
                type: String,
                default: CONFIG.AP_SVR || '220.1.35.123'
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
            allSwitch: { type: Boolean, default: false },
            aspectRatio: { type: Number, default: 2 }
        },
        data: () => ({
            items: [],
            db_count: 0,
            total_count: 0,
            ap_count: 0,
            last_update_time: '',
            reload_timer: null,
            refresh_ip_timer: null,
            type_carousel: ['bar', 'line', 'pie', 'polarArea', 'doughnut', 'radar'],
            svr_carousel: ['31', '32', '33', '34', '35', '36', '70', '123']
        }),
        watch: {
            disableOfficeHours(val) { if (val) this.reload() },
            demo(val) { this.reload() },
            ip(val) {
                clearTimeout(this.refresh_ip_timer);
                this.refresh_ip_timer = this.timeout(() => this.reload(true), 250);
            },
            allSwitch(val) { this.reload(true) },
            type(val) { this.reload(true) }
        },
        computed: {
            timer_ms() { return this.demo ? 5000 : 15000 },
            label() { return this.allSwitch ? `AP所有連線數 [${this.ip}]` : `AP使用者連線數 [${this.ip}]` },
            title() { return (this.type == 'line' || this.type == 'bar' || this.type == 'radar') ? '' : this.label },
            all_switch_desc() { return this.allSwitch ? '全部顯示' : '僅使用者' },
            curr_svr() { return this.ip.split('.')[3] },
            prev_svr() {
                let curr_idx = -1;
                this.svr_carousel.find((item, idx, array) => {
                    curr_idx = item == this.curr_svr ? idx : -1;
                    return item == this.curr_svr;
                });
                let next_idx = (curr_idx + 1) % this.svr_carousel.length;
                return `220.1.35.${this.svr_carousel[next_idx]}`;
            },
            next_svr() {
                let curr_idx = -1;
                this.svr_carousel.find((item, idx, array) => {
                    curr_idx = item == this.curr_svr ? idx : -1;
                    return item == this.curr_svr;
                });
                let prev_idx = (curr_idx - 1) == -1 ? this.svr_carousel.length - 1 : curr_idx - 1;
                return `220.1.35.${this.svr_carousel[prev_idx]}`;
            }
        },
        methods: {
            reload(force = false) {
                clearTimeout(this.reload_timer);
                if (this.demo && this.items.length > 0) {
                    this.reload_demo_data();
                    this.reload_timer = this.timeout(this.reload, this.timer_ms);
                } else if (force || this.isOfficeHours()) {
                    //this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_latest_ap_conn",
                        ap_ip: this.ip,
                        all: this.allSwitch
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得AP連線數回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({
                                    title: 'AP連線數',
                                    subtitle: this.ip,
                                    message: '無資料，無法繪製圖形',
                                    type: 'warning'
                                });
                            } else {
                                this.items = [];
                                // reset connection count top site
                                this.storeParams['XAP_CONN_TOP_SITES'] = [];
                                res.data.raw.forEach((item, raw_idx, array) => {
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
                                    if (item.name == '資料庫') {
                                        this.db_count = item.count;
                                    } else if (item.est_ip == '127.0.0.1') {
                                        // skip 127.0.0.1, SYSTEM ADMIN default ip
                                    } else {
                                        if (this.xapMap.has(item.est_ip)) {
                                            this.storeParams['XAP_CONN_TOP_SITES'].push(this.xapMap.get(item.est_ip).code);
                                        }
                                        this.items.push([item.name, item.count]);
                                    }
                                });
                                this.last_update_time = this.now().split(' ')[1];
                            }
                        } else {
                            this.alert({
                                title: `取得${this.ip}連線數`,
                                message: `取得AP連線數回傳狀態碼有問題【${res.data.status}】`,
                                variant: "warning"
                            });
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        //this.isBusy = false;
                        // reload every 15s
                        this.reload_timer = this.timeout(this.reload, this.timer_ms);
                        Vue.nextTick(() => {
                            this.$refs.chart.update();
                        });
                    });
                } else {
                    // check after an hour
                    this.reload_timer = this.timeout(this.reload, 3600000);
                }
            },
            reload_demo_data() {
                this.db_count = this.rand(500);
                this.items.forEach((item, raw_idx, array) => {
                    let text = item[0];
                    let value = this.rand(75);
                    item[1] = value;
                    this.$refs.chart.changeValue(text, value);
                });
                this.last_update_time = this.now().split(' ')[1];
                this.type = this.type_carousel[this.rand(this.type_carousel.length)];
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
                    title: `AP連線數分析`,
                    message: this.$createElement('lah-ap-connection-history-chart', {
                        props: {
                            type: this.type,
                            popupButton: false,
                            demo: this.demo,
                            ip: this.ip,
                            aspectRatio: this.viewportRatio,
                            allSwitch: true
                        }
                    }),
                    size: "xl"
                });
            },
            nav_left() { this.ip = this.prev_svr },
            nav_right() { this.ip = this.next_svr },
            style_by_count(value, opacity = 0.6) {
                let variant, action, rgb, icon;
                icon = 'question';
                variant = 'secondary';
                action = '';
                if (this.type == 'bar') {
                    if (value > 800) {
                        rgb = `rgb(179, 0, 255, ${opacity})`;
                    } // bright purple
                    else if (value > 600) {
                        rgb = `rgb(114, 0, 159, ${opacity})`;
                    } // dark purple
                    else if (value > 400) {
                        rgb = `rgb(255, 0, 19, ${opacity})`;
                    } // bright red
                    else if (value > 200) {
                        rgb = `rgb(200, 0, 19, ${opacity})`
                    } // dark red
                    else if (value > 100) {
                        rgb = `rgb(238, 182, 1, ${opacity})`;
                    } // yellow
                    else if (value > 5) {
                        rgb = `rgb(0, 200, 0, ${opacity})`
                    } // green
                    else {
                        rgb = `rgb(207, 207, 207, ${opacity})`;
                    } // muted
                    return [variant, action, rgb, icon]
                } else {
                    return [variant, action, `rgb(${this.rand(255)}, ${this.rand(255)}, ${this.rand(255)}, ${opacity})`, icon];
                }
            },
            bg_color(dataset_item, opacity) {
                [variant, action, rgb, icon] = this.style_by_count(dataset_item[1], opacity);
                return rgb;
            },
        },
        created() {
            this.reload(true);
            this.addToStoreParams('XAP_CONN_TOP_SITES', []);
        }
    });
} else {
    console.error("vue.js not ready ... lah-ap-connection-history-chart component can not be loaded.");
}