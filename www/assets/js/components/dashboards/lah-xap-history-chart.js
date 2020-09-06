if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<div>
            <div class="text-justify">
                <span class="align-middle small">{{title}}</span>
                <b-button-group size="sm" class="float-right">
                    <b-button variant="primary" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                    <b-button variant="success" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                    <b-form-spinbutton v-model="mins" min="5" max="45" size="sm" inline></b-form-spinbutton>
                </b-button-group>
            </div>
            <lah-chart ref="chart" :label="label" :items="items" :type="type"></lah-chart>
        </div>`,
        props: {
            site: { type: String, default: 'H0' },
            mins: { type: Number, default: 10 }
        },
        data: () => ({
            items: [],
            type: 'line',
            timer_ms: 60000,
            spin_timer: null
        }),
        watch: {
            mins(nVal, oVal) {
                clearTimeout(this.spin_timer);
                this.spin_timer = this.delay(this.reload.bind(this, true), 1000);
            }
        },
        computed: {
            label() { return `${this.site}` },
            title() { return `跨所 AP ${this.site} 連線趨勢圖` }
        },
        methods: {
            set_items(raw) {
                this.items.length = 0;
                let mins = this.mins;
                raw.forEach((item, raw_idx, raw) => {
                    let text = (raw_idx == mins) ? '現在' : `${mins - raw_idx}分前`;
                    this.items.push([text, item.count]);
                });
            },
            reload(force) {
                if (force || this.isOfficeHours()) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ap_conn_HX_history",
                        site: this.site,
                        count: this.mins + 1
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得跨所 AP ${this.site} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: `跨所 AP ${this.site} 連線趨勢圖`, message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                this.set_items(res.data.raw.reverse());
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