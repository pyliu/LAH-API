if (Vue) {
    Vue.component('lah-xap-connection-chart', {
        template: `<b-card border-variant="secondary" class="shadow">
            <lah-chart ref="chart" :label="label" :items="items" :type="type" :bg-color="bg_color"></lah-chart>
            <div class="d-flex justify-content-between">
                <span class="small align-middle my-auto">
                    <lah-fa-icon icon="database" title="資料庫連線數"> <b-badge :variant="db_variant" pill>{{db_count}}</b-badge></lah-fa-icon>
                    <lah-fa-icon icon="calculator" title="跨所AP上所有連線數"> <b-badge variant="info" pill>{{total_count}}</b-badge></lah-fa-icon>
                    <lah-fa-icon icon="clock" prefix="far" title="更新時間"> <b-badge variant="secondary">{{last_update_time}}</b-badge></lah-fa-icon>
                </span>
                <div :title="ip">
                    <b-button-group size="sm">
                        <lah-button icon="chart-bar" variant="primary" v-if="type != 'bar'" @click="type = 'bar'" title="切換長條圖"></lah-button>
                        <lah-button icon="chart-pie" variant="secondary" v-if="type != 'pie'" @click="type = 'pie'" title="切換圓餅圖"></lah-button>
                        <lah-button icon="chart-line" variant="success" v-if="type != 'line'" @click="type = 'line'" title="切換長線型圖"></lah-button>
                        <lah-button icon="chart-area" variant="warning" v-if="type != 'polarArea'" @click="type = 'polarArea'" title="切換區域圖"></lah-button>
                        <lah-button brand icon="edge" variant="info" v-if="type != 'doughnut'" @click="type = 'doughnut'" title="切換甜甜圈"></lah-button>
                        <lah-button icon="broadcast-tower" variant="dark" v-if="type != 'radar'" @click="type = 'radar'" title="切換雷達圖"></lah-button>
                        <lah-button v-if="popupButton" regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popup" action="heartbeat"></lah-button>
                    </b-button-group>
                </div>
            </div>
        </b-card>`,
        props: {
            ip: { type: String, default: CONFIG.AP_SVR || '220.1.35.123' },
            type: { type: String, default: 'doughnut' },
            popupButton: { type: Boolean, default: true }
        },
        data: () => ({
            items: [],
            db_count: 0,
            total_count: 0,
            last_update_time: ''
        }),
        computed: {
            label() { return `跨所AP連線數` },
            db_variant() {
                if (this.db_count > 3000) return 'dark';
                if (this.db_count > 1800) return 'danger';
                if (this.db_count > 1000) return 'warning';
                return 'success';
            }
        },
        methods: {
            bg_color(label, opacity) {
                switch(label) {
                    case '地政局': return `rgb(207, 207, 207, ${opacity})`;    // H0
                    case '桃園所': return `rgb(255, 20, 147, ${opacity})`;     // HA
                    case '中壢所': return `rgb(92, 184, 92, ${opacity})`;      // HB
                    case '大溪所': return `rgb(2, 117, 216, ${opacity})`;      // HC
                    case '楊梅所': return `rgb(57, 86, 73, ${opacity})`;       // HD
                    case '蘆竹所': return `rgb(240, 173, 78, ${opacity})`;     // HE
                    case '八德所': return `rgb(217, 83, 79, ${opacity})`;      // HF
                    case '平鎮所': return `rgb(78, 51, 87, ${opacity})`;       // HG
                    case '龜山所': return `rgb(108, 21, 240, ${opacity})`;     // HH
                    default: `rgb(${this.rand(255)}, ${this.rand(255)}, ${this.rand(255)}, ${opacity})`;
                }
            },
            get_site_tw(site_code) {
                switch(site_code) {
                    case 'H0': return '地政局';
                    case 'HA': return '桃園所';
                    case 'HB': return '中壢所';
                    case 'HC': return '大溪所';
                    case 'HD': return '楊梅所';
                    case 'HE': return '蘆竹所';
                    case 'HF': return '八德所';
                    case 'HG': return '平鎮所';
                    case 'HH': return '龜山所';
                    default: return '未知';
                }
            },
            reload(force = false) {
                if (force || this.isOfficeHours()) {
                    //this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_xap_conn_latest",
                        count: 11   // why 11? => H0 HA-H DB TOTAL
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得AP連線數回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: 'AP連線數', message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                this.items.length = 0;
                                res.data.raw.reverse().forEach(item => {
                                    // e.g. item => { count: 911, ip: "220.1.35.123", log_time: "20200904175957", site: "HB" }
                                    if (item.site == 'TOTAL') { this.total_count = item.count; }
                                    else if (item.site == 'DB') { this.db_count = item.count; }
                                    else {
                                        this.items.push([this.get_site_tw(item.site), item.count]);
                                    }
                                });
                                this.last_update_time = this.now().split(' ')[1];
                                // to workaround the line chart not rendering well issue
                                this.delay(() => this.$refs.chart.update(), 0);
                            }
                        } else {
                            this.alert({title: `取得${this.ip}連線數`, message: `取得AP連線數回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        //this.isBusy = false;
                        // reload every 15s
                        this.delay(this.reload, 15000);
                    });
                } else {
                    // check after an hour
                    this.delay(this.reload, 3600000);
                }
            },
            popup() {
                this.msgbox({
                    title: `跨所AP各所連線數`,
                    message: this.$createElement('lah-xap-connection-chart', { props: { type: 'line', popupButton: false } }),
                    size: "xl"
                });
            }
        },
        created() {
            this.reload(true);
        },
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... lah-xap-connection-chart component can not be loaded.");
}
