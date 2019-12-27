if (Vue) {
    Vue.component("expaa-mgt", {
        template: `<fieldset">
            <legend>規費資料集修正</legend>
        </fieldset>`,
        data: () => {
            return {
                date: "1081227"
            }
        },
        methods: { }
    });
} else {
    console.error("vue.js not ready ... expaa-mgt component can not be loaded.");
}
