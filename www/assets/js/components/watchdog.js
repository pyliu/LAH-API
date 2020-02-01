if (Vue) {
    Vue.component("watchdog", {
        template: `<b-row>
            <b-col>
                <b-card  title="排程" sub-title="顯示最近排程執行結果"></b-card>
            </b-col>
            <b-col>
                <b-card  title="記錄檔" sub-title="顯示最近記錄檔"></b-card>
            </b-col>
        </b-row>`,
        data: function () {
            return {}
        },
        mounted() {
            
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
