if (Vue) {
    Vue.component('lah-ap-connection-chart', {
        template: `<b-card>
            <div class="text-justify">
                <span class="align-middle">{{title}}</span>
                <b-button-group size="sm" class="float-right">
                    <b-button variant="primary" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                    <b-button variant="secondary" @click="type = 'pie'"><i class="fas fa-chart-pie"></i></b-button>
                    <b-button variant="success" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                    <b-button variant="warning" @click="type = 'polarArea'"><i class="fas fa-chart-area"></i></b-button>
                    <b-button variant="info" @click="type = 'doughnut'"><i class="fab fa-edge"></i></b-button>
                    <b-button variant="dark" @click="type = 'radar'"><i class="fas fa-broadcast-tower"></i></b-button>
                </b-button-group>
            </div>
            <lah-chart :label="label" :items="items" :type="type"></lah-chart>
        </b-card>`,
        props: {
            ip: { type: String, default: CONFIG.AP_SVR || '220.1.35.123' },
            type: { type: String, default: 'line' }
        },
        data: () => ({
            items: [],
            db_count: 0,
            total_count: 0
        }),
        computed: {
            label() { return `DB: ${this.db_count}, TOTAL: ${this.total_count}` },
            title() { return `${this.ip} 連線數` }
        },
        methods: {
            reload() {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.STATS, {
                    type: "stats_ap_conn_latest",
                    ip: this.ip,
                    count: 11
                }).then(res => {
                    console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得AP連線數回傳狀態碼有問題【${res.data.status}】`);
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        if (res.data.data_count == 0) {
                            this.notify({title: 'AP連線數', message: '無資料，無法繪製圖形', type: 'warning'});
                        } else {
                            //this.items.length = 0;
                            res.data.raw.reverse().forEach(item => {
                                // e.g. item => { count: 911, ip: "220.1.35.123", log_time: "20200904175957", site: "HB" }
                                if (item.site == 'TOTAL') { this.total_count = item.count; }
                                else if (item.site == 'DB') { this.db_count = item.count; }
                                else {
                                    let found = this.items.find((oitem, index, array) => {
                                        return item.site == oitem[0];
                                    });
                                    if (found) {
                                        found[1] = item.count;
                                    } else {
                                        // chart item format is array => ['text', 'count']
                                        this.items.push([item.site, item.count]);
                                    }
                                }
                            });
                        }
                    } else {
                        this.alert({title: `取得${this.ip}連線數`, message: `取得AP連線數回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                    // reload every 15s
                    setTimeout(this.reload, 15000);
                });
            }
        },
        created() {
            this.reload();
        },
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... lah-ap-connection-chart component can not be loaded.");
}
