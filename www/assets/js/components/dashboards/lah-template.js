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
    computed: { },
    watch: { },
    methods: {},
    created() { },
    mounted() { }
  });
} else {
  console.error("vue.js not ready ... lah-xxxxxxxxx component can not be loaded.");
}