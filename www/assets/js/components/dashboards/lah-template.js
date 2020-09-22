if (Vue) {
  Vue.component('lah-xxxxxxxxx', {
    template: `<b-card>
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
} else {
  console.error("vue.js not ready ... lah-export-txt component can not be loaded.");
}