if (Vue) {
    Vue.component('lah-xap-connection-chart', {
        template: `<b-card border-variant="secondary">
            <div class="d-flex justify-content-between">
                <span class="small mt-1 float-left">
                    <lah-fa-icon icon="database" title="資料庫連線數"> <b-badge :variant="db_variant" pill>{{db_count}}</b-badge></lah-fa-icon>
                    <lah-fa-icon icon="calculator" title="跨所AP上所有連線數"> <b-badge variant="info" pill>{{total_count}}</b-badge></lah-fa-icon>
                    <lah-fa-icon icon="clock" title="更新時間"> <b-badge variant="secondary" pill>{{last_update_time}}</b-badge></lah-fa-icon>
                </span>
                <div class="mb-2" :title="ip">
                    <b-button-group size="sm">
                        <b-button variant="primary" v-if="type != 'bar'" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                        <b-button variant="secondary" v-if="type != 'pie'" @click="type = 'pie'"><i class="fas fa-chart-pie"></i></b-button>
                        <b-button variant="success" v-if="type != 'line'" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                        <b-button variant="warning" v-if="type != 'polarArea'" @click="type = 'polarArea'"><i class="fas fa-chart-area"></i></b-button>
                        <b-button variant="info" v-if="type != 'doughnut'" @click="type = 'doughnut'"><i class="fab fa-edge"></i></b-button>
                        <b-button variant="dark" v-if="type != 'radar'" @click="type = 'radar'"><i class="fas fa-broadcast-tower"></i></b-button>
                        <lah-button v-if="popupButton" alt icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popup"></lah-button>
                    </b-button-group>
                </div>
            </div>
            <lah-chart :label="label" :items="items" :type="type"></lah-chart>
        </b-card>`,
        props: {
            ip: { type: String, default: CONFIG.AP_SVR || '220.1.35.123' },
            type: { type: String, default: 'bar' },
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
                    this.isBusy = true;
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
                            }
                        } else {
                            this.alert({title: `取得${this.ip}連線數`, message: `取得AP連線數回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
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
