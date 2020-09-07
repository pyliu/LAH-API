if (Vue) {
    Vue.component('lah-xap-history-chart', {
        template: `<b-card border-variant="secondary">
            <div class="text-justify">
                <span class="align-middle small">{{title}}</span>
                <b-button-group size="sm" class="float-right">
                    <b-button variant="primary" v-if="type != 'bar'" @click="type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                    <b-button variant="success" v-if="type != 'line'" @click="type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                    <b-form-spinbutton v-model="mins" min="5" max="60" size="sm" inline></b-form-spinbutton>
                    <lah-button v-if="popupButton" icon="external-link-alt" variant="outline-primary" title="放大顯示" @click="popup"></lah-button>
                </b-button-group>
            </div>
            <lah-chart ref="chart" :label="label" :items="items" :type="type"></lah-chart>
        </b-card>`,
        props: {
            site: { type: String, default: 'H0' },
            mins: { type: Number, default: 10 },
            type: { type: String, default: 'line' },
            demo: { type: Boolean, default: false},
            popupButton: { type: Boolean, default: true }
        },
        data: () => ({
            items: [],
            timer_ms: 60000,
            spin_timer: null,
            site_tw: '地政局',
        }),
        watch: {
            mins(nVal, oVal) {
                clearTimeout(this.spin_timer);
                this.spin_timer = this.delay(this.reload.bind(this, true), 1000);
            },
            site(nVal, oVal) { this.set_site_tw(nVal) }
        },
        computed: {
            label() { return `${this.site_tw}` },
            title() { return `${this.site_tw}連線` }
        },
        methods: {
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
                this.items.length = 0;
                let mins = (raw.length <= this.mins)? raw.length - 1 : this.mins;
                raw.forEach((item, raw_idx, raw) => {
                    let text = (raw_idx == mins) ? '現在' : `${mins - raw_idx}分前`;
                    this.items.push([text, this.demo ? this.rand() : item.count]);
                });
            },
            reload(force) {
                if (force || this.isOfficeHours() || this.demo) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.STATS, {
                        type: "stats_ap_conn_HX_history",
                        site: this.site,
                        count: this.mins + 1
                    }).then(res => {
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得跨所 AP ${this.site_tw} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`);
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            if (res.data.data_count == 0) {
                                this.notify({title: `跨所 AP ${this.site_tw} 連線趨勢圖`, message: '無資料，無法繪製圖形', type: 'warning'});
                            } else {
                                this.set_items(res.data.raw.reverse());
                            }
                        } else {
                            this.alert({title: `取得跨所 AP ${this.site_tw} 連線趨勢圖`, message: `取得跨所 AP ${this.site_tw} 連線趨勢圖回傳狀態碼有問題【${res.data.status}】`, variant: "warning"});
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
            },
            popup() {
                this.msgbox({
                    title: `跨所AP ${this.site_tw}連線`,
                    message: this.$createElement('lah-xap-history-chart', { props: { site: this.site, mins: 45, demo: this.demo, popupButton: false } }),
                    size: "xl"
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
