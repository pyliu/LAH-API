if (Vue) {
  Vue.component('lah-lxhweb-traffic-light', {
    template: `<lah-transition appear>
      <b-card border-variant="secondary" class="shadow">
        <template v-slot:header>
          <div class="d-flex w-100 justify-content-between mb-0">
            <h6 class="my-auto font-weight-bolder"><lah-fa-icon :icon="headerIcon" size="lg" :variant="headerLight"> {{site}} 同步異動監控 </lah-fa-icon></h6>
            <b-button-group>
              <lah-button v-if="show_broken_btn" icon="unlink" variant='danger' class="border-0" @click="showBrokenTable" action="damage" title="檢視損毀資料表"><b-badge variant="light" pill>{{broken_tbl_count}}</b-badge></lah-button>
              <lah-button icon="sync" variant='outline-secondary' class="border-0" @click="reload" action="cycle" title="重新讀取"></lah-button>
              <lah-button v-if="!maximized" class="border-0" :icon="btnIcon" variant="outline-primary" title="顯示模式" @click="switchType"></lah-button>
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
      site: { type: String, default: 'L3HWEB' },
      type: { type: String, default: 'light' },
      demo: { type: Boolean, default: false },
      maximized: { type: Boolean, default: false }
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
      reload_timer: null,
      broken_tbl_raw: null
    }),
    computed: {
      btnIcon() { return this.type == 'light' ? 'chart-bar' : 'traffic-light' },
      headerIcon() { return this.type == 'light' ? 'traffic-light' : 'chart-bar' },
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
      },
      broken_tbl_count() { return this.empty(this.broken_tbl_raw) ? 0 : this.broken_tbl_raw.length },
      show_broken_btn() { return this.broken_tbl_count > 0 },
      reload_ms() { return this.demo ? 5000 : 1 * 60 * 1000 }
    },
    watch: {
      demo(flag) { this.reload() },
      list(arr) { this.updChartData(arr) }
    },
    methods: {
      switchType() { this.type = this.type == 'light' ? 'chart' : 'light' },
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
            title: this.site,
            message: this.$createElement('lah-lxhweb-traffic-light', {
                props: {
                    site: this.site,
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
          this.reload_timer = this.timeout(() => this.reload(), this.reload_ms);
        } else {
          this.isBusy = true;
          this.$http.post(CONFIG.API.JSON.LXHWEB, {
            type: "lxhweb_site_update_time",
            site: this.site
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
            this.reload_timer = this.timeout(() => this.reload(), this.reload_ms);  // a minute
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
      },
      checkBrokenTable() {
        this.$http.post(CONFIG.API.JSON.LXHWEB, {
            type: "lxhweb_broken_table",
            site: this.site
        }).then(res => {
            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
              // found
              this.alert({
                  title: "同步異動表格檢測",
                  message: `<i class="fa fa-exclamation-triangle fa-lg ld ld-beat"></i> 找到 ${res.data.data_count} 筆損毀表格`,
                  type: "danger",
                  delay: 20000
              });
              this.broken_tbl_raw = res.data.raw;
            } else {
              this.$log(res.data.message);
            }
        }).catch(err => {
            this.error = err;
        }).finally(() => {
            this.isBusy = false;
        });
      },
      showBrokenTable() {
        if (!this.empty(this.broken_tbl_raw)) { 
          this.msgbox({
            title: '同步異動損毀表格',
            message: this.$createElement('b-table', {
                props: {
                    striped: true,
                    hover: true,
                    headVariant: 'dark',
                    bordered: true,
                    captionTop: true,
                    caption: `找到 ${this.broken_tbl_count} 件`,
                    items: this.broken_tbl_raw
                }
            }),
            size: 'xl'
          });
        }
      }
    },
    created() {
      this.container_id = this.uuid();
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
      this.checkBrokenTable();
    }
  });
} else {
  console.error("vue.js not ready ... lah-lxhweb-traffic-light component can not be loaded.");
}
