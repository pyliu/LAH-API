if (Vue) {
  Vue.component('lah-l3hweb-traffic-light', {
    template: `<b-card>
      <template v-slot:header>
        <div class="d-flex w-100 justify-content-between mb-0">
          <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="traffic-light" size="lg"> 同步異動資料庫監控</lah-fa-icon></h6>
          <lah-button icon="question" variant="outline-success" class="border-0" @click="popup"></lah-button>
        </div>
      </template>
      <b-list-group flush>
          <b-list-group-item v-for="entry in list">
            <lah-fa-icon icon="circle" variant="success">{{entry.SITE}} : {{entry.UPDATE_DATETIME}}</lah-fa-icon>
          </b-list-group-item>
      </b-list-group>
    </b-card>`,
    props: {
      sample: {
        type: Boolean,
        default: false
      }
    },
    data: () => ({
      list: [{
          SITE: 'HA',
          UPDATE_DATETIME: '2020-10-08 20:47:00'
        },
        {
          SITE: 'HB',
          UPDATE_DATETIME: '2020-10-08 21:47:00'
        },
        {
          SITE: 'HC',
          UPDATE_DATETIME: '2020-10-08 22:47:00'
        },
        {
          SITE: 'HD',
          UPDATE_DATETIME: '2020-10-08 23:47:00'
        },
        {
          SITE: 'HE',
          UPDATE_DATETIME: '2020-10-09 00:47:00'
        },
        {
          SITE: 'HF',
          UPDATE_DATETIME: '2020-10-09 01:47:00'
        },
        {
          SITE: 'HG',
          UPDATE_DATETIME: '2020-10-09 02:47:00'
        },
        {
          SITE: 'HH',
          UPDATE_DATETIME: '2020-10-09 03:47:00'
        }
      ]
    }),
    computed: {},
    watch: {},
    methods: {
      popup() {
        this.msgbox({
            title: '同步異動提資料庫監控',
            message: `
                同步異動資料庫監控
            `,
            size: 'lg'
        });
    }
    },
    created() {
      // this.isBusy = true;
      // this.$http.post(CONFIG.API.JSON.QUERY, {
      //   type: "l3hweb_update_time"
      // }).then(res => {
      //   if (this.empty(res.data.data_count)) {
      //     this.notify({
      //       title: "同步異動主機狀態檢視",
      //       message: `${this.nowDate} ${this.nowTime} 查無資料`,
      //       type: "warning"
      //     });
      //   } else {
      //     // array of {SITE: 'HB', UPDATE_DATETIME: '2020-10-08 21:47:00'}
      //     this.list = res.data.raw;
      //   }
      // }).catch(err => {
      //   this.error = err;
      // }).finally(() => {
      //   this.isBusy = false;
      // });
    },
    mounted() {}
  });
} else {
  console.error("vue.js not ready ... lah-l3hweb-traffic-light component can not be loaded.");
}