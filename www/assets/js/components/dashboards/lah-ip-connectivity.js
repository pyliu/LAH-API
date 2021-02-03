if (Vue) {
    Vue.component('lah-ip-connectivity', {
        template: `<lah-button
            :icon="icon"
            :action="style.action"
            :badge-text="latency_txt"
            :variant="style.variant"
            :badge-variant="style.badge"
            :block="block"
            :title="title"
            @click="reload(true)"
        >{{resolved_name}}</lah-button>`,
        props: {
            name: { type: String, default: undefined },
            ip: { type: String, default: '127.0.0.1' },
            port: { type: Number, default: 0 },
            desc: { type: String, default: '' },
            block: { type: Boolean, default: false },
            demo: { type: Boolean, default:false }
        },
        data: () => ({
            latency: 0.0,
            status: 'DOWN',
            log_time: '20201016185331',
            reload_timer: null
        }),
        computed: {
            icon() { return 'server' },
            title() { return `重新整理 ${this.ip}:${this.port} 回應時間`},
            reload_ms() { return this.demo ? 5000 : 15 * 60 * 1000 },   // default is 15 mins
            resolved_name() { return this.name || (this.ip == '127.0.0.1' ? '本機' : this.ip) },
            latency_txt() {
                return `${this.latency.toFixed(0)} ms`
            },
            name_map() { return this.storeParams['lah-ip-connectivity-map'] },
            style() {
                if (this.latency == 0) return { action: 'blur', variant: 'secondary', badge: 'light' };
                if (this.latency > CONFIG.PING.DANGER) return { action: 'tremble', variant: 'outline-danger', badge: 'danger' };
                if (this.latency > CONFIG.PING.WARNING) return { action: 'beat', variant: 'outline-warning', badge: 'warning' };
                return { action: 'breath', variant: 'outline-success', badge: 'success' };
            }
        },
        watch: {
            demo(val) { this.reload() },
            name_map(val) {
                if (val && val.size > 0) {
                    let entry = this.storeParams['lah-ip-connectivity-map'].get(this.ip);
                    if (entry) {
                        this.name = entry.name;
                        // no specify port number, use the port stored in the DB
                        if (this.port == 0 && !this.empty(entry.port)) {
                            this.port = entry.port;
                        }
                    } else {
                        this.name = undefined;
                    }
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
                                    this.setLocalCache('lah-ip-connectivity-map', res.data.raw);
                                    let map = new Map();
                                    /* raw is array of { "XXXX": { 
                                                ip: "220.xxx.xxx.xxx"
                                                name: "XXXX"
                                                note: "說明"
                                                port: (default is NULL)
                                            }
                                        }
                                    */
                                    for (const [name, raw_obj] of Object.entries(res.data.raw)) {
                                        map.set(raw_obj.ip, raw_obj);
                                    }
                                    // prepared map to the Vuex param
                                    this.storeParams['lah-ip-connectivity-map'] = map;
                                    this.name = map.get(this.ip) ? map.get(this.ip).name : undefined;
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
                        for (const [name, raw_obj] of Object.entries(cached)) {
                            map.set(raw_obj.ip, raw_obj);
                        }
                        this.name = map.get(this.ip) ? map.get(this.ip).name : undefined;
                        // prepared map to the Vuex param
                        this.storeParams['lah-ip-connectivity-map'] = map;
                    }
                });
            },
            reload(force = false) {
                clearTimeout(this.reload_timer);
                if (this.demo) {
                    this.latency = this.rand(3000);
                    this.status = this.latency > CONFIG.PING.DANGER ? 'DOWN' : 'UP';
                    this.log_time = this.now().replace(/[\-\s:]*/, '');
                } else {
                    if (force) this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ip_connectivity_history",
                        force: force,
                        ip: this.ip,
                        port: this.port
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
                    });
                }
                this.reload_timer = this.timeout(() => this.reload(), this.reload_ms);
            }
        },
        created() {
            this.prepare();
            this.busyIconSize = '1x';
        },
        mounted() {
            Vue.nextTick(() => this.reload());
        }
    });
} else {
    console.error("vue.js not ready ... lah-ip-connectivity component can not be loaded.");
}
