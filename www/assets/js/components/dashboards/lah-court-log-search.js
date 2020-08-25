if (Vue) {
  Vue.component('lah-court-log-search', {
    components: {
      'lah-court-log-search-items': {
        template: `<div>
          <b-form-row v-for="item in list">
            <b-col>
              <lah-fa-icon icon="mountain" v-if="item.type == 'land'" variant="primary"> 土地</lah-fa-icon>
              <lah-fa-icon icon="home" v-else variant="success"> 建物</lah-fa-icon>
              : {{item.value}}
              <b-button-close @click="remove(item)" title="刪除這個項目" class="text-danger"></b-button-close>
            </b-col>
          </b-form-row>
        </div>`,
        data: () => ({}),
        computed: {
          list_key() { return this.$parent.list_key },
          list() { return this.storeParams[this.list_key] }
        },
        methods: {
          remove(item) {
            for(let i = 0; i < this.list.length; i++) {
              if ( this.list[i].type == item.type && this.list[i].value == item.value ) {
                this.list.splice(i, 1);
              }
            }
          }
        },
        created() { }
      }
    },
    template: `<fieldset>
      <legend v-b-tooltip="'匯出謄本查詢紀錄'">
        <i class="fas fa-stamp"></i>
        謄本紀錄查詢
        <b-button class="border-0"  @click="popup" variant="outline-success" size="sm"><i class="fas fa-question"></i></b-button>
      </legend>
      <b-form-row class="mb-1">
        <b-col class="d-flex">
          <b-input-group size="sm" prepend="段小段">
            <b-form-select ref="section" v-model="section_code" :options="sections">
                <template v-slot:first>
                    <b-form-select-option :value="null" disabled>-- 請選擇段別 --</b-form-select-option>
                </template>
            </b-form-select>
          </b-input-group>
          <b-button @click="query" variant="outline-primary" size="sm" title="搜尋" class="ml-1"><i class="fas fa-search"></i></b-button>
        </b-col>
      </b-form-row>
      <b-form-row>
        <b-col>
          <div class="d-flex">
            <b-input-group size="sm" prepend="地/建號" title="以-分隔子號">
              <b-form-input :state="validate_input" v-model="land_build_number" class="h-100 no-cache" placeholder="123-1" @input="filter"></b-form-input>
            </b-input-group>
            <b-button @click="addLandNumber" variant="outline-primary" size="sm" title="增加地號" class="mx-1 text-nowrap" :disabled="!land_btn_on"><i class="fas fa-plus"> 土地</i></b-button>
            <b-button @click="addBuildNumber" variant="outline-success" size="sm" title="增加建號" class="text-nowrap" :disabled="!build_btn_on"><i class="fas fa-plus"> 建物</i></b-button>
          </div>
        </b-col>
      </b-form-row>
      <b-form-row>
        <b-col>
          <lah-court-log-search-items></lah-court-log-search-items>
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
      query() {
        this.msgbox({
          title: '<i class="fa fa-search fa-lg"></i> 謄本紀錄查詢',
            message: `建置中 ... `,
            size: "sm"
        });
      },
      addLandNumber() {
        this.list.push({ type: 'land', value: this.land_build_number });
      },
      addBuildNumber() {
        this.list.push({ type: 'build', value: this.land_build_number });
      },
      filter() {
        this.land_build_number = this.land_build_number.replace(/(^\s*)|(\s*$)/g, '').replace(/\-0+$/g, '');
      }
    },
    watch: { },
    computed: {
      list_key() { return 'target-number-list' },
      list() { return this.storeParams[this.list_key] },
      land_btn_on() {
        let testee = this.land_build_number;
        if (this.empty(testee) || parseInt(testee) == 0) return false;
        if (parseInt(testee) == 0) return false;
        if (testee.includes('-') && testee.match(/^\d{1,4}(\-\d{1,4})?$/g) === null) return false;
        return testee.length < 5 || testee.match(/^\d{1,4}(\-\d{1,4})?$/g) !== null;
      },
      build_btn_on() {
        let testee = this.land_build_number;
        if (this.empty(testee) || parseInt(testee) == 0) return false;
        if (testee.includes('-') && testee.match(/^\d{1,5}(\-\d{1,3})?$/g) === null) return false;
        return testee.length < 6 || testee.match(/^\d{1,5}(\-\d{1,3})?$/g) !== null;
      },
      validate_input() { return this.land_btn_on || this.build_btn_on; }
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
      // e.g. [{type: 'land', value: '123-1'}, {type: 'build', value: '00456-002'}]
      this.addToStoreParams(this.list_key, []);
      // this.$log(this.list_key);
      // this.$log(this.list);
    },
    mounted() { }
  });
}