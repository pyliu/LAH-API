if (Vue) {
    Vue.component(VueCountdown.name, VueCountdown);
    Vue.component("watchdog", {
        template: `<b-row>
            <b-col>
                <b-card header="排程儀表版">
                    <b-row>
                        <b-col cols="8">
                            <countdown ref="countdown" :time="timer_milliseconds" :auto-start="false">
                                <template slot-scope="props">{{ props.minutes }}:{{ props.seconds }} 後自動執行</template>
                            </countdown>
                        </b-col>
                        <b-col cols="4">
                            <b-input-group size="sm">
                                <b-input-group-prepend is-text>顯示個數</b-input-group-prepend>
                                <b-form-input
                                    type="number"
                                    v-model="display_count"
                                    size="sm"
                                ></b-form-input>
                            </b-input-group>
                        </b-col>
                    </b-row>
                    <small>
                        <b-list-group flush>
                            <b-list-group-item v-for="item in schedule_history">{{item}}</b-list-group-item>
                        </b-list-group>
                    </small>
                </b-card>
            </b-col>
            <b-col>
                <b-card bo-body header="紀錄儀表版">
                    <div class="d-flex w-100 justify-content-between">
                        <b-button variant="outline-primary" size="sm" @click="callLogAPI">刷新</b-button>
                        <small class="text-muted">更新時間: {{log_update_time}}</small>
                    </div>
                    <small>
                        <b-list-group flush>
                            <b-list-group-item v-for="item in log_list">{{item}}</b-list-group-item>
                        </b-list-group>
                    </small>
                </b-card>
            </b-col>
        </b-row>`,
        data: function () {
            return {
                schedule_history: [],
                watchdog_timer: null,
                log_timer: null,
                timer_milliseconds: 15 * 60 * 1000,  // 15 minutes
                log_list: [],
                log_update_time: "08:10:11",
                display_count: 10
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
                if (this.schedule_history.length == this.display_count) {
                    this.schedule_history.pop();
                } else if (this.schedule_history.length > this.display_count) {
                    this.schedule_history = [];
                }
                this.schedule_history.unshift(message);
            },
            addLogList: function (message) {
                if (this.log_list.length == this.display_count) {
                    this.log_list.pop();
                } else if (this.log_list.length > this.display_count) {
                    this.log_list = [];
                }
                this.log_list.unshift(message);
            },
            callLogAPI: function () {
                clearTimeout(this.watchdog_timer);
                let dt = new Date();
                this.log_update_time = `${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                let log_filename = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}.log`
                let body = new FormData();
                body.append("type", "load_log");
                body.append("log_filename", log_filename);
                asyncFetch("load_file_api.php", {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    // normal success jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        let that = this;
                        jsonObj.data.forEach(function(item, index, array){
                            that.addLogList(item);
                        });
                        this.log_timer = setTimeout(this.callLogAPI, this.timer_milliseconds);
                    } else {
                        // stop the timer if API tells it is not working
                        this.addLogList(`${this.log_update_time} 錯誤: ${jsonObj.message}`);
                        console.warn(jsonObj.message);
                    }
                }).catch(ex => {
                    this.addLogList(`${this.log_update_time} 錯誤: ${ex.message}`);
                    showAlert({
                        title: 'watchdog::callLogAPI parsing failed',
                        message: ex.message,
                        type: 'danger'
                    });
                    console.error("watchdog::callLogAPI parsing failed", ex);
                });
            },
            callWatchdogAPI: function() {
                this.endCountdown();
                clearTimeout(this.watchdog_timer);

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
            }
        },
        mounted() {
            this.callWatchdogAPI();
            this.callLogAPI();
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
