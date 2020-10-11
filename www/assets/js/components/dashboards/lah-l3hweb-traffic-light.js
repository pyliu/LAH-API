if (Vue) {
  Vue.component('lah-l3hweb-traffic-light', {
    template: `<b-card>
      <template v-slot:header>
        <div class="d-flex w-100 justify-content-between mb-0">
          <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="traffic-light" size="lg" :variant="headerLight"> L3HWEB 資料庫更新監控 </lah-fa-icon></h6>
          <b-button-group>
            <lah-button icon="sync" variant='outline-primary' class="border-0" @click="reload" action="cycle"></lah-button>
            <lah-button icon="question" variant="outline-success" class="border-0" @click="popup"></lah-button>
          </b-button-group>
        </div>
      </template>
      <div v-if="!fullHeight" :id="container_id" class="grids">
        <div v-for="entry in list" class="grid">
          <lah-fa-icon icon="circle" :variant="light(entry)" :action="action(entry)" v-b-popover.hover.focus.top="'最後更新時間: '+entry.UPDATE_DATETIME">{{name(entry)}}</lah-fa-icon>
        </div>
      </div>
      <div v-else :id="container_id">
        <div class="d-flex justify-content-between mx-auto" style="width: 85%">
          <lah-fa-icon v-for="entry in list" icon="circle" :variant="light(entry)" :action="action(entry)" v-b-popover.hover.focus.top="'最後更新時間: '+entry.UPDATE_DATETIME">{{name(entry)}}</lah-fa-icon>
        </div>
        <hr/>
        <lah-chart ref="chart" :label="chartLabel" :items="chartItems" :type="charType" :aspect-ratio="viewportRatio+0.3" :bg-color="chartItemColor"></lah-chart>
      </div>
    </b-card>`,
    props: {
      fullHeight: {
        type: Boolean,
        default: false
      },
      demo: {
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
      popup() {
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
          this.timeout(() => this.reload(), 5000);
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
      if (this.fullHeight) $(`#${this.container_id}`).css('height', `${window.innerHeight-195}px`);
      this.updChartData(this.list);
    }
  });
} else {
  console.error("vue.js not ready ... lah-l3hweb-traffic-light component can not be loaded.");
}