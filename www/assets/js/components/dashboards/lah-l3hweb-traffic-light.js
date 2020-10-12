if (Vue) {
  Vue.component('lah-l3hweb-traffic-light', {
    template: `<lah-transition appear>
      <b-card border-variant="secondary" class="shadow">
        <template v-slot:header>
          <div class="d-flex w-100 justify-content-between mb-0">
            <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="traffic-light" size="lg" :variant="headerLight"> L3HWEB 同步異動監控 </lah-fa-icon></h6>
            <b-button-group>
              <lah-button icon="sync" variant='outline-secondary' class="border-0" @click="reload" action="cycle" title="重新讀取"></lah-button>
              <lah-button v-if="!maximized" class="border-0" regular icon="window-maximize" variant="outline-primary" title="放大顯示" @click="popupMaximized" action="heartbeat"></lah-button>
              <lah-button icon="question" variant="outline-success" class="border-0" @click="popupQuestion" title="說明"></lah-button>
            </b-button-group>
          </div>
        </template>
        <div v-if="type == 'light'" :id="container_id" class="grids">
          <div v-for="entry in list" class="grid">
            <lah-fa-icon icon="circle" :variant="light(entry)" :action="action(entry)" v-b-popover.hover.focus.top="'最後更新時間: '+entry.UPDATE_DATETIME">{{name(entry)}}</lah-fa-icon>
          </div>
        </div>
        <div v-else :id="container_id">
          <div v-if="showHeadLight" class="d-flex justify-content-between mx-auto">
            <lah-fa-icon v-for="entry in list" icon="circle" :variant="light(entry)" :action="action(entry)" v-b-popover.hover.focus.top="'最後更新時間: '+entry.UPDATE_DATETIME">{{name(entry)}}</lah-fa-icon>
          </div>
          <lah-chart ref="chart" :label="chartLabel" :items="chartItems" :type="charType" :aspect-ratio="aspectRatio" :bg-color="chartItemColor"></lah-chart>
        </div>
      </b-card>
    </lah-transition>`,
    props: {
      type: {
        type: String,
        default: 'light'
      },
      demo: {
        type: Boolean,
        default: false
      },
      maximized: {
        type: Boolean,
        default: false
      }
    },
    data: () => ({
      container_id: 'grids-container',
      list: [],
      chartLabel: '未更新時間(分鐘)',
      charType: 'bar',
      chartItems: [
        ['桃園所', 0],
        ['中壢所', 0],
        ['大溪所', 0],
        ['楊梅所', 0],
        ['蘆竹所', 0],
        ['八德所', 0],
        ['平鎮所', 0],
        ['龜山所', 0]
      ],
      reload_timer: null
    }),
    computed: {
      aspectRatio() { return this.showHeadLight ? this.viewportRatio + 0.2 : this.viewportRatio - 0.2 },
      showHeadLight() { return this.type == 'full' },
      headerLight() {
        let site_light = 'success';
        for (let i = 0; i < this.list.length; i++) {
          let this_light = this.light(this.list[i]);
          if (this_light == 'warning') site_light = 'warning';
          if (this_light == 'danger') return 'danger';
        }
        return site_light;
      }
    },
    watch: {
      demo(flag) { this.reload() },
      list(arr) { this.updChartData(arr) }
    },
    methods: {
      randDate() {
        let rand_date = new Date(+new Date() - this.rand(45 * 60 * 1000));
        return rand_date.getFullYear() + "-" +
          ("0" + (rand_date.getMonth() + 1)).slice(-2) + "-" +
          ("0" + rand_date.getDate()).slice(-2) + " " +
          ("0" + rand_date.getHours()).slice(-2) + ":" +
          ("0" + rand_date.getMinutes()).slice(-2) + ":" +
          ("0" + rand_date.getSeconds()).slice(-2);
      },
      chartItemColor(dataset_item, opacity) {
        let rgb, value = dataset_item[1];
        if (value > 30) {
          rgb = `rgb(243, 0, 19, ${opacity})`
        } // red
        else if (value > 15) {
          rgb = `rgb(238, 182, 1, ${opacity})`;
        } // yellow
        else {
          rgb = `rgb(0, 200, 0, ${opacity})`
        }
        return rgb;
      },
      action(entry) {
        let light = this.light(entry);
        switch (light) {
          case 'danger':
            return 'tremble';
          case 'warning':
            return 'beat';
          default:
            return '';
        }
      },
      light(entry) {
        const now = +new Date(); // in ms
        const last_update = +new Date(entry.UPDATE_DATETIME.replace(' ', 'T'));
        let offset = now - last_update;
        if (offset > 30 * 60 * 1000) return 'danger';
        else if (offset > 15 * 60 * 1000) return 'warning';
        return 'success';
      },
      name(entry) {
        for (var value of this.xapMap.values()) {
          if (value.code == entry.SITE) {
            return value.name;
          }
        }
      },
      popupQuestion() {
        this.msgbox({
          title: '同步異動資料庫監控說明',
          message: `
              <h6 class="my-2"><i class="fa fa-circle text-danger fa-lg"></i> 已超過半小時未更新</h6>
              <h6 class="my-2"><i class="fa fa-circle text-warning fa-lg"></i> 已超過15分鐘未更新</h6>
              <h6 class="my-2"><i class="fa fa-circle text-success fa-lg"></i> 15分鐘內更新</h6>
            `,
          size: 'lg'
        });
      },
      popupMaximized() {
        this.msgbox({
            title: `燈&圖顯示`,
            message: this.$createElement('lah-l3hweb-traffic-light', {
                props: {
                    type: 'full',
                    demo: this.demo,
                    aspectRatio: this.viewportRatio,
                    maximized: true
                }
            }),
            size: "xl"
        });
      },
      reload() {
        clearTimeout(this.reload_timer);
        if (this.demo) {
          this.list = [
            { SITE: 'HA', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HB', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HC', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HD', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HE', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HF', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HG', UPDATE_DATETIME: this.randDate() },
            { SITE: 'HH', UPDATE_DATETIME: this.randDate() }
          ];
          this.reload_timer = this.timeout(() => this.reload(), 5000);
        } else {
          this.isBusy = true;
          this.$http.post(CONFIG.API.JSON.QUERY, {
            type: "l3hweb_update_time"
          }).then(res => {
            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
              // array of {SITE: 'HB', UPDATE_DATETIME: '2020-10-08 21:47:00'}
              this.list = res.data.raw;
            } else {
              this.notify({
                title: "同步異動主機狀態檢視",
                message: `${res.data.message}`,
                type: "warning"
              });
            }
          }).catch(err => {
            this.error = err;
          }).finally(() => {
            this.isBusy = false;
            this.reload_timer = this.timeout(() => this.reload(), 60 * 1000);  // a minute
          });
        }
      },
      updChartData(data) {
        const now = +new Date(); // in ms
        data.forEach((item, raw_idx, array) => {
          // item = { SITE: 'HB', UPDATE_DATETIME: '2020-10-10 19:58:01' }
          let name = this.name(item);
          if (this.empty(name)) {
            this.$warn(`${item.SITE} can not find the mapping name.`);
          } else {
            let last_update = +new Date(item.UPDATE_DATETIME.replace(' ', 'T'));
            let value = parseInt((now - last_update) / 60000); // ms to min
            let found = this.chartItems.find((oitem, idx, array) => { return oitem[0] == name; });
            if (found) {
              // the dataset item format is ['text', 123]
              found[1] = value;
              // not reactively ... manual set chartData
              if (this.$refs.chart) {
                this.$refs.chart.changeValue(name, value);
              }
            } else {
              this.$warn(`Can not find ${name} in chartItems.`);
            }
          }
        });
      }
    },
    created() {
      // mock data
      this.list = [
        { SITE: 'HA', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HB', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HC', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HD', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HE', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HF', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HG', UPDATE_DATETIME: this.randDate() },
        { SITE: 'HH', UPDATE_DATETIME: this.randDate() }
      ];
      this.reload();
    },
    mounted() {
      if (this.autoHeight) $(`#${this.container_id}`).css('height', `${window.innerHeight-195}px`);
    }
  });
} else {
  console.error("vue.js not ready ... lah-l3hweb-traffic-light component can not be loaded.");
}
