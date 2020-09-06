if (Vue) {
    Vue.component('lah-xap-connection-chart', {
        template: `<div>
            <div class="text-justify mb-2">
                <b-button-group size="sm">
                    <b-button variant="primary" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                    <b-button variant="secondary" @click="type = 'pie'"><i class="fas fa-chart-pie"></i></b-button>
                    <b-button variant="success" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                    <b-button variant="warning" @click="type = 'polarArea'"><i class="fas fa-chart-area"></i></b-button>
                    <b-button variant="info" @click="type = 'doughnut'"><i class="fab fa-edge"></i></b-button>
                    <b-button variant="dark" @click="type = 'radar'"><i class="fas fa-broadcast-tower"></i></b-button>
                </b-button-group>
                <span class="small float-right mt-1">
                    資料庫: <b-badge :variant="db_variant" pill>{{db_count}}</b-badge>
                    全部: <b-badge variant="info" pill>{{total_count}}</b-badge>
                </span>
            </div>
            <lah-chart :label="label" :items="items" :type="type"></lah-chart>
        </div>`,
        props: {
            ip: { type: String, default: CONFIG.AP_SVR || '220.1.35.123' },
            type: { type: String, default: 'bar' }
        },
        data: () => ({
            items: [],
            db_count: 0,
            total_count: 0
        }),
        computed: {
            label() { return `${this.ip} 連線數` },
            db_variant() {
                if (this.db_count > 3000) return 'dark';
                if (this.db_count > 1800) return 'danger';
                if (this.db_count > 1000) return 'warning';
                return 'success';
            }
        },
        methods: {
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
                        this.delay(this.reload, 15000);
                    });
                } else {
                    // check after an hour
                    this.delay(this.reload, 3600000);
                }
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
