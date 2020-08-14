if (Vue) {
  Vue.component('lah-court-log-search', {
    template: `<b-card>
      謄本記錄查詢
    </b-card>`,
    props: {
      sample: {
        type: Boolean,
        default: false
      }
    },
    data: () => ({ }),
    methods: {},
    watch: { },
    computed: { },
    created() { },
    mounted() { }
  });
}