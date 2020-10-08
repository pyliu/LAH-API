if (Vue) {
  Vue.component('lah-l3hweb-traffic-light', {
    template: `<b-card>
    </b-card>`,
    props: {
      sample: {
        type: Boolean,
        default: false
      }
    },
    data: () => ({
        list: null
    }),
    computed: { },
    watch: { },
    methods: {},
    created() {
        this.isBusy = true;
        this.$http.post(CONFIG.API.JSON.QUERY, {
            type: "l3hweb_update_time"
        }).then(res => {
            if (this.empty(res.data.data_count)) {
                this.notify({
                    title: "同步異動主機狀態檢視",
                    message: `${this.nowDate} ${this.nowTime} 查無資料`,
                    type: "warning"
                });
                return;
            }
            this.list = res.data.raw;
        }).catch(err => {
            this.error = err;
        }).finally(() => {
            this.isBusy = false;
        });
    },
    mounted() { }
  });
} else {
  console.error("vue.js not ready ... lah-l3hweb-traffic-light component can not be loaded.");
}