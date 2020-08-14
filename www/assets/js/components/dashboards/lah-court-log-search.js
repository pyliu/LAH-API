if (Vue) {
  Vue.component('lah-court-log-search', {
    template: `<b-card>
      <b-form-select ref="section" v-model="section_code" :options="sections">
          <template v-slot:first>
              <b-form-select-option :value="null" disabled>-- 請選擇段別 --</b-form-select-option>
          </template>
      </b-form-select>
    </b-card>`,
    props: { },
    data: () => ({
      cache_key: 'lah-court-log-search-section-list',
      sections: [],
      section_code: '0200'
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
      }
    },
    watch: { },
    computed: { },
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
    },
    mounted() { }
  });
}