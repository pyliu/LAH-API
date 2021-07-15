if (Vue) {
    Vue.component('lah-system-connectivity', {
        template: `<lah-transition appear>
            <b-card border-variant="secondary" class="shadow">
                <template v-slot:header>
                    <div class="d-flex w-100 justify-content-between mb-0">
                        <h6 class="my-auto font-weight-bolder"><lah-fa-icon :icon="headerIcon" size="lg" :variant="headerLight"> 系統狀態監控 </lah-fa-icon></h6>
                        <b-button-group>
                            <lah-button v-if="type != 'light'" icon="sync" variant='outline-secondary' no-border @click="reload(true)" action="cycle" title="重新讀取"></lah-button>
                            <lah-button v-if="!maximized" no-border :icon="btnIcon" variant="outline-primary" title="顯示模式" @click="switchType"></lah-button>
                            <lah-button v-if="!maximized" no-border regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popupMaximized" action="heartbeat"></lah-button>
                            <lah-button icon="question" variant="outline-success" no-border @click="popupQuestion" title="說明"></lah-button>
                        </b-button-group>
                    </div>
                </template>
                <div v-if="type == 'light'" :id="container_id" class="grids">
                    <div v-for="entry in list" class="grid-one-third">
                        <lah-ip-connectivity :ip="entry.target_ip" block></lah-ip-connectivity>
                    </div>
                </div>
                <div v-else :id="container_id">
                    <div v-if="showHeadLight" class="d-flex justify-content-between mx-auto">
                        <lah-fa-icon v-for="entry in list" icon="circle" :variant="light(entry)" :action="action(entry)" v-b-popover.hover.focus.top="'回應時間: '+entry.latency+'ms'">{{entry.name}}</lah-fa-icon>
                    </div>
                    <lah-chart ref="chart" :label="chartLabel" :items="chartItems" :type="charType" :aspect-ratio="aspectRatio" :bg-color="chartItemColor"></lah-chart>
                </div>
            </b-card>
        </lah-transition>`,
        props: {
            type: {
                type: String,
                default: 'chart'
            },
            demo: {
                type: Boolean,
                default: false
            },
            maximized: {
                type: Boolean,
                default: false
            }
        },
        data: () => ({
            container_id: 'grids-container',
            list: [],
            chartLabel: 'PING回應時間(微秒)',
            charType: 'bar',
            chartItems: [],
            reload_timer: null
        }),
        computed: {
            reload_ms() { return this.demo ? 5000 : 15 * 60 * 1000 },
            btnIcon() {
                return this.type == 'light' ? 'chart-bar' : 'traffic-light'
            },
            headerIcon() {
                return this.type == 'light' ? 'traffic-light' : 'chart-bar'
            },
            aspectRatio() {
                return this.showHeadLight ? this.viewportRatio + 0.2 : this.viewportRatio - 0.2
            },
            showHeadLight() {
                return this.type == 'full'
            },
            headerLight() {
                let latency_light = 'success';
                for (let i = 0; i < this.list.length; i++) {
                    let this_light = this.light(this.list[i]);
                    if (this_light == 'danger') return 'danger';
                }
                return latency_light;
            }
        },
        watch: {
            demo(flag) { this.reload() },
            list(arr) { this.updChartData(arr) },
            type(dontcare) { this.reload() }
        },
        methods: {
            switchType() {
                this.type = this.type == 'light' ? 'chart' : 'light'
            },
            chartItemColor(dataset_item, opacity) {
                let rgb, value = dataset_item[1];
                if (value > CONFIG.PING.BLACK) {
                    rgb = `rgb(179, 0, 255, ${opacity})`
                } else if (value > CONFIG.PING.DANGER) {
                    rgb = `rgb(243, 0, 19, ${opacity})`
                } // red
                else if (value > CONFIG.PING.WARNING) {
                    rgb = `rgb(238, 182, 1, ${opacity})`;
                } // yellow
                else {
                    rgb = `rgb(0, 200, 0, ${opacity})`
                }
                return rgb;
            },
            action(entry) {
                let light = this.light(entry);
                switch (light) {
                    case 'danger':
                        return 'tremble';
                    case 'warning':
                        return 'beat';
                    default:
                        return '';
                }
            },
            light(entry) {
                if (entry.latency > CONFIG.PING.DANGER) return 'danger';
                if (entry.latency > CONFIG.PING.WARNING) return 'warning';
                return 'success';
            },
            popupQuestion() {
                this.msgbox({
                    title: '系統狀態監控說明',
                    message: `
                        <h6 class="my-2"><i class="fa fa-circle text-danger fa-lg"></i> Ping回應值超過0.03秒</h6>
                        <h6 class="my-2"><i class="fa fa-circle text-warning fa-lg"></i> Ping回應值超過0.015秒</h6>
                        <h6 class="my-2"><i class="fa fa-circle text-success fa-lg"></i> Ping回應值正常</h6>
                    `,
                    size: 'lg'
                });
            },
            popupMaximized() {
                this.msgbox({
                    title: `放大顯示`,
                    message: this.$createElement('lah-system-connectivity', {
                        props: {
                            type: 'full',
                            demo: this.demo,
                            aspectRatio: this.viewportRatio,
                            maximized: true
                        }
                    }),
                    size: "xl"
                });
            },
            demoReload() {
                clearTimeout(this.reload_timer);
                this.list.forEach(item => {
                    item.latency = this.rand(2500);
                });
                this.updChartData(this.list);
                this.reload_timer = this.timeout(() => this.reload(), this.reload_ms);
            },
            prepare() {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.STATS, {
                    type: "stats_connectivity_target"
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        // initializing monitor list entries from DB
                        this.list = [];
                        // raw is array of { 'AP31': {ip: 'xxx.xxx.xxx.31', name: 'AP31', port: '', note: 'XXX'} }
                        for (const [name, raw_obj] of Object.entries(res.data.raw)) {
                            this.list.push({
                                name: name,
                                target_ip: raw_obj.ip,
                                latency: 0.0,
                                status: 'DOWN',
                                log_time: '20201005181631'
                            });
                        }
                    } else {
                        this.notify({
                            title: "初始化圖表監測目標",
                            message: `${res.data.message}`,
                            type: "warning"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            reload(force = false) {
                if (this.demo) {
                    this.demoReload();
                } else if (this.type != 'light') {
                    clearTimeout(this.reload_timer);
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_connectivity_history",
                        force: force,
                        timeout: this.list.length * 1000 + 3000 // maximum number of timeout in milliseconds
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            //array of { target_ip: 'xxx.xxx.xxx.xxx', latency: 2000.0, status: 'DOWN', log_time: '20201005181631' }
                            res.data.raw.forEach(item => {
                                let found = this.list.find((oitem, idx, array) => {
                                    return oitem.target_ip == item.target_ip;
                                });
                                if (found) {
                                    // the dataset item format is ['text', 123]
                                    found.latency = item.latency;
                                    found.status = item.status;
                                    found.log_time = item.log_time;
                                    // not reactively ... manual set chartData
                                    if (this.type != 'light' && this.$refs.chart) {
                                        this.$refs.chart.changeValue(found.name, found.latency);
                                    }
                                } else {
                                    this.$warn(`Can not find ${item.target_ip} data.`);
                                }
                            });
                        } else {
                            this.notify({
                                title: "同步異動主機狀態檢視",
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
            },
            updChartData(data) {
                data.forEach((item, raw_idx, array) => {
                    // item = { name: 'AP33', ip: 'xxx.xxx.xxx.xxx', latency: 43.2 }
                    let name = item.name;
                    if (this.empty(name)) return;
                    let value = parseFloat(item.latency); // ms to min
                    let found = this.chartItems.find((oitem, idx, array) => {
                        return oitem[0] == name;
                    });
                    if (found) {
                        // the dataset item format is ['text', 123]
                        found[1] = item.latency;
                        // not reactively ... manual set chartData
                        if (this.$refs.chart) {
                            this.$refs.chart.changeValue(name, value);
                        }
                    } else {
                        this.chartItems.push([name, value]);
                    }
                });
            }
        },
        created() {
            this.container_id = this.uuid();
            this.prepare();
        },
        mounted() {
            // if (this.autoHeight) $(`#${this.container_id}`).css('height', `${window.innerHeight-195}px`);
            this.reload();
        }
    });
} else {
    console.error("vue.js not ready ... lah-system-connectivity component can not be loaded.");
}
