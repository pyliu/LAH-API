if (Vue) {
  Vue.component('lah-court-log-search', {
    components: {
      'lah-court-log-item': {
        template: ``
      }
    },
    template: `<fieldset>
      <legend v-b-tooltip="'匯出謄本查詢紀錄'">
        <i class="fas fa-stamp"></i>
        謄本紀錄查詢
        <b-button class="border-0"  @click="popup" variant="outline-success" size="sm"><i class="fas fa-question"></i></b-button>
      </legend>
      <b-form-row class="mb-2">
        <b-col>
          <b-input-group size="sm" prepend="段小段">
            <b-form-select ref="section" v-model="section_code" :options="sections">
                <template v-slot:first>
                    <b-form-select-option :value="null" disabled>-- 請選擇段別 --</b-form-select-option>
                </template>
            </b-form-select>
          </b-input-group>
        </b-col>
      </b-form-row>
      <b-form-row>
        <b-col>
          <div class="d-flex">
            <b-input-group size="sm" prepend="地/建號" title="以-分隔子號">
              <b-form-input v-model="land_build_number"></b-form-input>
            </b-input-group>
            <b-button @click="addLandNumber" variant="outline-primary" size="sm" title="增加地號" class="mx-1"><i class="fas fa-mountain"></i></b-button>
            <b-button @click="addBuildNumber" variant="outline-primary" size="sm" title="增加建號"><i class="fas fa-home"></i></b-button>
          </div>
        </b-col>
      </b-form-row>
    </fieldset>`,
    props: { },
    data: () => ({
      cache_key: 'lah-court-log-search-section-list',
      sections: [],
      section_code: '0200',
      land_build_number: ''
    }),
    methods: {
      prepare(json) {
        if (json && json.data_count > 0) {
          json.raw.forEach(item => {
            if (item["段代碼"] == '0500' || item["段代碼"] == '/*  */') return;
            this.sections.push({
              value: item["段代碼"],
              text: (item["區代碼"] == '03' ? '中壢區' : '觀音區') + '：【' + item["段代碼"] + '】' + item["段名稱"]
            });
          });
        } else {
          this.notify({ message: '無法取得正確段代碼資料', type: 'warning'});
        }
      },
      popup() {
        this.msgbox({
          title: '<i class="fa fa-search fa-lg"></i> 謄本紀錄查詢',
          message: `依序輸入下列條件來查找。 <ol><li>選擇段小段別</li> <li>輸入地、建號</li> <li>點選查詢</li> </ol>`,
          size: "sm"
        });
      },
      addLandNumber() {},
      addBuildNumber() {}
    },
    watch: { },
    computed: {
      list_key() { return 'target-number-list' },
      list() { return this.storeParams[this.list_key] }
    },
    created() {
      this.getLocalCache(this.cache_key).then(json => {
        if (json) {
          this.prepare(json);
        } else {
          this.isBusy = true;
          this.$http.post(CONFIG.QUERY_JSON_API_EP, {
              type: 'ralid',
              text: ''
          }).then(res => {
              this.prepare(res.data);
              this.setLocalCache(this.cache_key, res.data, 24 * 60 * 60 * 1000);
          }).catch(err => {
              this.error = err;
          }).finally(() => {
              this.isBusy = false;
          });
        }
      });
      // init global store param
      this.addToStoreParams(this.list_key, []);
      this.$log(this.list_key);
      this.$log(this.list);
    },
    mounted() { }
  });
}