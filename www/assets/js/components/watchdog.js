if (Vue) {
    Vue.component("watchdog", {
        template: `<b-card>
        </b-card>`,
        data: function () {
            return {}
        },
        mounted() {
            
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
