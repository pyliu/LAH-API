if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<b-card border-variant="secondary" class="shadow">
            <lah-chart ref="chart" :label="label" :items="items" :type="type" :bg-color="bg_color" @click="show_count"></lah-chart>
            <div class="d-flex justify-content-between mt-1">
                <span class="align-middle small my-auto">
                    <lah-fa-icon icon="network-wired" title="現在連線數"> <b-badge :variant="badge_variant">{{now_count}}</b-badge></lah-fa-icon>
                    <lah-fa-icon icon="clock" prefix="far" title="更新時間">
                        <b-badge v-if="isOfficeHours() || demo" variant="secondary">{{last_update_time}}</b-badge>
                        <b-badge v-else variant="danger" title="非上班時間所以停止更新">已停止更新</b-badge>
                    </lah-fa-icon>
                </span>
                <b-button-group size="sm">
                    <lah-button icon="chart-bar" variant="primary" v-if="type != 'bar'" @click="type = 'bar'" title="切換長條圖"></lah-button>
                    <lah-button icon="chart-line" variant="success" v-if="type != 'line'" @click="type = 'line'" title="切換線型圖"></lah-button>
                    <b-form-spinbutton v-model="mins" min="5" max="60" size="sm" inline></b-form-spinbutton>
                    <lah-button v-if="popupButton" regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popup" action="heartbeat"></lah-button>
                </b-button-group>
            </div>
        </b-card>`,
        props: {
            site: { type: String, default: 'H0' },
            mins: { type: Number, default: 15 },
            type: { type: String, default: 'line' },
            demo: { type: Boolean, default: false},
            popupButton: { type: Boolean, default: true }
        },
        data: () => ({
            items: [],
            timer_ms: 60000,
            spin_timer: null,
            site_tw: '地政局',
            last_update_time: '',
            now_count: 0
        }),
        watch: {
            mins(nVal, oVal) {
                clearTimeout(this.spin_timer);
                this.spin_timer = this.delay(() => {
                    this.items.length = 0;
                    this.reload(true);
                }, 1000);
            },
            site(nVal, oVal) { this.set_site_tw(nVal) }
        },
        computed: {
            label() { return `${this.site_tw}` },
            title() { return `${this.site_tw}連線` },
            badge_variant() {
                if (this.now_count > 200) return `danger`;   // red
                if (this.now_count > 100) return `warning`;  // yellow
                if (this.now_count > 10) return `success`;   // green
                return `muted`;                              // muted
            }
        },
        methods: {
            bg_color(dataset_item, opacity) {
                if (dataset_item[1] > 200) return `rgb(243, 0, 19, ${opacity})`;   // red
                if (dataset_item[1] > 100) return `rgb(238, 182, 1, ${opacity})`; // yellow
                if (dataset_item[1] > 10) return `rgb(0, 200, 0, ${opacity})`;   // green
                return `rgb(207, 207, 207, ${opacity})`;                           // muted
            },
            set_site_tw(site_code) {
                switch(site_code) {
                    case 'H0': this.site_tw = '地政局'; break;
                    case 'HA': this.site_tw = '桃園所'; break;
                    case 'HB': this.site_tw = '中壢所'; break;
                    case 'HC': this.site_tw = '大溪所'; break;
                    case 'HD': this.site_tw = '楊梅所'; break;
                    case 'HE': this.site_tw = '蘆竹所'; break;
                    case 'HF': this.site_tw = '八德所'; break;
                    case 'HG': this.site_tw = '平鎮所'; break;
                    case 'HH': this.site_tw = '龜山所'; break;
                    default: this.site_tw = '未知';
                }
            },
            set_items(raw) {
                raw.forEach((item, raw_idx, raw) => {
                    let text = (raw_idx == 0) ? '現在' : `${raw_idx}分前`;
                    let val = this.demo ? this.rand(300) : item.count;
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
                if (force || this.isOfficeHours() || this.demo) {
                    //this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ap_conn_HX_history",
                        site: this.site,
                        count: parseInt(this.mins) + 1
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得跨所 AP ${this.site_tw} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: `跨所 AP ${this.site_tw} 連線趨勢圖`, message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                this.set_items(res.data.raw);
                            }
                        } else {
                            this.alert({title: `取得跨所 AP ${this.site_tw} 連線趨勢圖`, message: `取得跨所 AP ${this.site_tw} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        //this.isBusy = false;
                        this.delay(this.reload, this.timer_ms);
                        Vue.nextTick(() => {
                            this.$refs.chart.update();
                        });
                    });
                } else {
                    // check after an hour
                    this.delay(this.reload, 3600000);
                }
            },
            popup() {
                this.msgbox({
                    title: `跨所AP ${this.site_tw}連線`,
                    message: this.$createElement('lah-xap-history-chart', { props: { site: this.site, mins: 60, demo: this.demo, popupButton: false } }),
                    size: "xl"
                });
            },
            show_count(e, payload) {
                if (this.empty(payload['label'])) return;
                let variant = 'info', action = '';
                if (payload['value'] > 200) { variant = 'danger'; action='shiver'; }
                else if (payload['value'] > 100) { variant = 'warning'; action='beat'; }
                else if (payload['value'] > 10) { variant = 'success'; action='jump'; }
                else variant = 'muted';
                this.notify({
                    title: `${this.site_tw}連線數`,
                    subtitle: `${payload['label']}`,
                    message: `<i class="fas fa-network-wired ld ld-${action}"></i> ${payload['value']}`,
                    type: variant
                });
            }
        },
        created() {
            this.timer_ms = this.demo ? 5000 : 60000;
            this.set_site_tw(this.site);
            this.reload(true);
        }
    });
} else {
    console.error("vue.js not ready ... lah-xap-history-chart component can not be loaded.");
}
