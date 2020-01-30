if (Vue) {
    Vue.component("overdue-reg-cases", {
        template: `<small>WIP ... </small>`,
        data: function () {
            return {
                
            }
        },
        mounted() { }
    });
} else {
    console.error("vue.js not ready ... overdue-reg-cases component can not be loaded.");
}
