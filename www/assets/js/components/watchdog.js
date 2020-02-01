if (Vue) {
    Vue.component(VueCountdown.name, VueCountdown);
    Vue.component("watchdog", {
        template: `<b-row>
            <b-col>
                <b-card header="排程儀表版">
                    <countdown ref="countdown" :time="timer_milliseconds" :auto-start="false">
                        <template slot-scope="props">下次執行 {{ props.minutes }}:{{ props.seconds }}</template>
                    </countdown>
                    <b-list-group flush>
                        <b-list-group-item v-for="item in schedule_history">{{item}}</b-list-group-item>
                    </b-list-group>
                </b-card>
            </b-col>
            <b-col>
                <b-card header="紀錄儀表版"></b-card>
            </b-col>
        </b-row>`,
        data: function () {
            return {
                schedule_history: [],
                watchdog_timer: null,
                timer_milliseconds: 15 * 60 * 1000,  // 15 minutes
                log_list: []
            }
        },
        methods: {
            resetCountdown: function () {
                this.$refs.countdown.totalMilliseconds = this.timer_milliseconds;
                this.$refs.countdown.start();
            },
            startCountdown: function () {
                this.$refs.countdown.start();
            },
            endCountdown: function () {
                this.$refs.countdown.totalMilliseconds = 0;
            },
            addScheduleHistory: function (message) {
                if (this.schedule_history.length > 4) {
                    this.schedule_history.pop();
                }
                this.schedule_history.unshift(message);
            },
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
                    // normal success jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.addScheduleHistory(`${now} 結果: OK`);
                        // Backend will check if it needs to do or not
                        this.watchdog_timer = setTimeout(this.callWatchdogAPI, this.timer_milliseconds);	// call the watchdog every 15 mins
                        this.startCountdown();
                    } else {
                        // stop the timer if API tells it is not working
                        this.addScheduleHistory(`${now} 結果: ${jsonObj.message}`);
                        console.warn(jsonObj.message);
                    }
                }).catch(ex => {
                    this.endCountdown();
                    this.addScheduleHistory(`${now} 結果: ${ex.message}`);
                    showAlert({
                        title: 'watchdog::callWatchdogAPI parsing failed',
                        message: ex.message,
                        type: 'danger'
                    });
                    console.error("watchdog::callWatchdogAPI parsing failed", ex);
                });
            },
            getTodayLog: function() {
                let dt = new Date();
                let log_filename = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}.log`
                jQuery.get(`logs/${log_filename}`, function(data) {
                    alert(data);
                });
            }
        },
        mounted() {
            this.callWatchdogAPI();
            this.getTodayLog();
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
