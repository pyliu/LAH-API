if (Vue) {
    Vue.component("watchdog", {
        template: `<b-row>
            <b-col>
                <b-card  title="排程" sub-title="顯示最近排程執行歷程">
                    <b-card-text v-for="item in schedule_history">{{item}}</b-card-text>
                </b-card>
            </b-col>
            <b-col>
                <b-card  title="記錄檔" sub-title="顯示最近記錄檔"></b-card>
            </b-col>
        </b-row>`,
        data: function () {
            return {
                schedule_history: [],
                watchdog_timer: null,
                log_list: []
            }
        },
        methods: {
            callWatchdogAPI: function() {
                // generate current date time string
                let dt = new Date();
                let now = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))} ${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                
                let body = new FormData();
                body.append("type", "watchdog");
                asyncFetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    this.schedule_history.push(`${now} result: ${jsonObj.status}`);
                    // normal success jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {       
                        // Backend will check if it needs to go or not
                        this.watchdog_timer = setTimeout(this.callWatchdogAPI, 1000 * 60 * 15);	// call the watchdog every 15 mins         
                    } else {
                        // stop the timer if API tells it is not working
                        this.schedule_history.push(`${now} result: ${jsonObj.message}`);
                        console.warn(jsonObj.message);
                    }
                }).catch(ex => {
                    this.schedule_history.push(`${now} result: ${ex.message}`);
                    showAlert({
                        title: 'watchdog::callWatchdogAPI parsing failed',
                        message: ex.message,
                        type: 'danger'
                    });
                    console.error("watchdog::callWatchdogAPI parsing failed", ex);
                });
            }
        },
        mounted() {
            this.callWatchdogAPI();
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
