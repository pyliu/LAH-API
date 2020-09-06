if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<div>
            <div class="text-justify">
                <span class="align-middle small">{{title}}</span>
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
        </div>`,
        props: {
            site: { type: String, default: 'H0' },
        },
        data: () => ({
            items: [],
            type: 'bar'
        }),
        computed: {
            label() { return `${this.site}` },
            title() { return `跨所AP ${this.site}歷史連線數` }
        },
        methods: {
            reload(force) {
                if (force || this.isOfficeHours()) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ap_conn_HX_history",
                        site: this.site
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得跨所 AP ${this.site} 歷史連線數回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: `跨所 AP ${this.site} 歷史連線數`, message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                res.data.raw.forEach(item => {
                                    // e.g. item => { count: 34, ip: "220.1.35.123", log_time: "20200904175957", site: "H0" }
                                    let found = this.items.find((oitem, index, array) => {
                                        return item.site == oitem[0];
                                    });
                                    if (found) {
                                        found[1] = item.count;
                                    } else {
                                        // chart item format is array => ['text', 'count']
                                        this.items.push([item.site, item.count]);
                                    }
                                });
                            }
                        } else {
                            this.alert({title: `取得跨所 AP ${this.site} 歷史連線數`, message: `取得跨所 AP ${this.site} 歷史連線數回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
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
        }
    });
} else {
    console.error("vue.js not ready ... lah-xap-history-chart component can not be loaded.");
}