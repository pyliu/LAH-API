if (Vue) {
    Vue.component('lah-ip-connectivity', {
        template: `<lah-button
            icon="no"
            :action="style.action"
            :badge-text="latency_txt"
            :variant="style.variant"
            :badge-variant="style.badge"
            :block="block"
            title="重新整理"
            @click="reload(true)"
        >{{resolved_name}}</lah-button>`,
        props: {
            ip: { type: String, default: '127.0.0.1' },
            block: { type: Boolean, default: false },
            demo: { type: Boolean, default:false }
        },
        data: () => ({
            name: undefined,
            latency: 0.0,
            status: 'DOWN',
            log_time: '20201016185331',
            reload_timer: null
        }),
        computed: {
            reload_ms() { return this.demo ? 5000 : 15 * 60 * 1000 },
            resolved_name() { return this.name || this.ip },
            latency_txt() { return `${this.latency} ms` },
            name_map() { return this.storeParams['lah-ip-connectivity-map'] },
            style() {
                if (this.latency == 0 || this.latency > 1999) return { action: 'tremble', variant: 'outline-danger', badge: 'danger' };
                if (this.latency > 1000) return { action: 'beat', variant: 'outline-warning', badge: 'warning' };
                return { action: '', variant: 'outline-success', badge: 'success' };
            }
        },
        watch: {
            demo(val) { this.reload() },
            name_map(val) {
                if (val && val.size > 0) {
                    this.name = this.storeParams['lah-ip-connectivity-map'].get(this.ip);
                }
            }
        },
        methods: {
            prepare() {
                this.getLocalCache('lah-ip-connectivity-map').then((cached) => {
                    if (cached === false) {
                        if (!this.storeParams.hasOwnProperty('lah-ip-connectivity-map')) {
                            // add new property to the storeParam with don't care value to reduce the xhr request (lock concept)
                            this.addToStoreParams('lah-ip-connectivity-map', true);
                            // store a mapping table in Vuex
                            this.$http.post(CONFIG.API.JSON.STATS, {
                                type: "stats_connectivity_target"
                            }).then(res => {
                                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    let map = new Map();
                                    // raw is object of { 'AP31': 'xxx.xxx.xxx.31'}
                                    for (const [name, ip] of Object.entries(res.data.raw)) {
                                        map.set(ip, name);
                                    }
                                    // prepared map to the Vuex param
                                    this.storeParams['lah-ip-connectivity-map'] = map;
                                    this.name = this.storeParams['lah-ip-connectivity-map'].get(this.ip);
                                    this.setLocalCache('lah-ip-connectivity-map', res.data.raw);
                                } else {
                                    this.notify({
                                        title: "初始化 lah-ip-connectivity-map",
                                        message: `${res.data.message}`,
                                        type: "warning"
                                    });
                                }
                            }).catch(err => {
                                this.error = err;
                            }).finally(() => {
                                this.isBusy = false;
                            });
                        }
                    } else {
                        // commit to Vuex store
                        if (!this.storeParams.hasOwnProperty('lah-ip-connectivity-map')) {
                            this.addToStoreParams('lah-ip-connectivity-map', true);
                        }
                        let map = new Map();
                        for (const [name, ip] of Object.entries(cached)) {
                            map.set(ip, name);
                        }
                        this.name = map.get(this.ip)
                        // prepared map to the Vuex param
                        this.storeParams['lah-ip-connectivity-map'] = map;
                    }
                });
            },
            reload(force = false) {
                clearTimeout(this.reload_timer);
                if (this.demo) {
                    this.latency = this.rand(3000);
                    this.status = this.latency > 1999 ? 'DOWN' : 'UP';
                    this.log_time = this.now().replace(/[\-\s:]*/, '');
                    this.reload_timer = this.timeout(() => this.reload(), this.reload_ms); // default is 15 mins
                } else {
                    if (force) this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ip_connectivity_history",
                        force: force,
                        ip: this.ip
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            // res.data.raw: object of { target_ip: 'xxx.xxx.xxx.xxx', latency: 2000.0, status: 'DOWN', log_time: '20201005181631' }
                            // this.$log(res.data.raw);
                            this.latency = res.data.raw.latency;
                            this.status = res.data.raw.status;
                            this.log_time = res.data.raw.log_time;
                        } else {
                            this.notify({
                                title: "檢查IP連結狀態",
                                message: `${res.data.message}`,
                                type: "warning"
                            });
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                        this.reload_timer = this.timeout(() => this.reload(), this.reload_ms); // default is 15 mins
                    });
                }
            }
        },
        created() {
            this.prepare();
            this.reload();
            this.busyIconSize = '1x';
        }
    });
} else {
    console.error("vue.js not ready ... lah-ip-connectivity component can not be loaded.");
}
