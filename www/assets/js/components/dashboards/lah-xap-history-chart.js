if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<div>
            <div class="text-justify">
                <span class="align-middle small">{{title}}</span>
                <b-button-group size="sm" class="float-right">
                    <b-button variant="primary" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                    <b-button variant="success" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                </b-button-group>
            </div>
            <lah-chart :label="label" :items="items" :type="type"></lah-chart>
        </div>`,
        props: {
            site: { type: String, default: 'H0' },
        },
        data: () => ({
            items: [],
            type: 'line',
            count: 20,
            timer_ms: 60000
        }),
        computed: {
            label() { return `${this.site}` },
            title() { return `跨所 AP ${this.site} 連線趨勢圖` }
        },
        methods: {
            raw_idx_to_text(raw_idx) {
                switch(raw_idx) {
                    case 0: return '19分前';
                    case 1: return '18分前';
                    case 2: return '17分前';
                    case 3: return '16分前';
                    case 4: return '15分前';
                    case 5: return '14分前';
                    case 6: return '13分前';
                    case 7: return '12分前';
                    case 8: return '11分前';
                    case 9: return '10分前';
                    case 10: return '9分前';
                    case 11: return '8分前';
                    case 12: return '7分前';
                    case 13: return '6分前';
                    case 14: return '5分前';
                    case 15: return '4分前';
                    case 16: return '3分前';
                    case 17: return '2分前';
                    case 18: return '1分前';
                    case 19: return '現在';
                    default: return 'N/A';
                }
            },
            reload(force) {
                if (force || this.isOfficeHours()) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ap_conn_HX_history",
                        site: this.site,
                        count: this.count
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得跨所 AP ${this.site} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: `跨所 AP ${this.site} 連線趨勢圖`, message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                res.data.raw.reverse().forEach((item, raw_idx, raw) => {
                                    // e.g. item => { count: 34, ip: "220.1.35.123", log_time: "20200904175957", site: "H0" }
                                    let found = this.items.find((oitem, oidx, items) => {
                                        return this.raw_idx_to_text(raw_idx) == oitem[0];
                                    });
                                    if (found) {
                                        found[1] = item.count;
                                    } else {
                                        // chart item format is array => ['text', 'count']
                                        this.items.push([this.raw_idx_to_text(raw_idx), item.count]);
                                    }
                                });
                            }
                        } else {
                            this.alert({title: `取得跨所 AP ${this.site} 連線趨勢圖`, message: `取得跨所 AP ${this.site} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                        this.delay(this.reload, this.timer_ms);
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