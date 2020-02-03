if (Vue) {
    // for using countdown
    Vue.component(VueCountdown.name, VueCountdown);
    Vue.component("watchdog", {
        template: `<b-form-row>
            <b-col>
                <schedule-task></schedule-task>
            </b-col>
            <b-col>
                <log-viewer></log-viewer>
            </b-col>
        </b-form-row>`,
        components: {
            "log-viewer": {
                template: `<b-card bo-body :header="'紀錄儀表版 ' + query_data_count + ' / ' + query_total_count">
                    <div class="d-flex w-100 justify-content-between">
                        <b-input-group size="sm" style="width:125px">
                            <b-input-group-prepend is-text>顯示筆數</b-input-group-prepend>
                            <b-form-input
                                type="number"
                                v-model="count"
                                size="sm"
                                min="1"
                            ></b-form-input>
                        </b-input-group>
                        <small class="text-muted text-center">
                            <b-button variant="primary" size="sm" @click="callLogAPI">
                                刷新
                                <b-badge variant="light">
                                    <countdown ref="countdown" :time="milliseconds" :auto-start="false">
                                        <template slot-scope="props">{{ props.minutes }}:{{ props.seconds }}</template>
                                    </countdown>
                                    <span class="sr-only">countdown</span>
                                </b-badge>
                            </b-button>
                        </small>
                    </div>
                    <small>
                        <b-list-group flush>
                            <b-list-group-item v-for="item in list">{{item}}</b-list-group-item>
                        </b-list-group>
                    </small>
                </b-card>`,
                data: function () {
                    return {
                        list: [],
                        log_timer: null,
                        milliseconds: 15 * 60 * 1000,
                        count: 10,
                        log_update_time: "10:48:00",
                        query_data_count: 0,
                        query_total_count: 0
                    }
                },
                methods: {
                    resetCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = this.milliseconds;
                    },
                    startCountdown: function () {
                        this.$refs.countdown.start();
                    },
                    endCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = 0;
                    },
                    callLogAPI: function () {
                        this.endCountdown();
                        clearTimeout(this.log_timer);
                        let dt = new Date();
                        this.log_update_time = `${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                        let log_filename = `log-${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}.log`
                        let body = new FormData();
                        body.append("type", "load_log");
                        body.append("log_filename", log_filename);
                        body.append("slice_offset", -50);   // get last 50 records
                        asyncFetch("load_file_api.php", {
                            method: "POST",
                            body: body
                        }).then(jsonObj => {
                            // normal success jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                            if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.query_data_count = jsonObj.data_count;
                                this.query_total_count = jsonObj.total_count;
                                let that = this;
                                jsonObj.data.forEach(function(item, index, array){
                                    that.addLogList(item);
                                });
                                this.log_timer = setTimeout(this.callLogAPI, this.milliseconds);
                                this.resetCountdown();
                                this.startCountdown();
                            } else {
                                // stop the timer if API tells it is not working
                                this.addLogList(`${this.log_update_time} 錯誤: ${jsonObj.message}`);
                                console.warn(jsonObj.message);
                            }
                        }).catch(ex => {
                            this.endCountdown();
                            this.addLogList(`${this.log_update_time} 錯誤: ${ex.message}`);
                            showAlert({
                                title: 'watchdog::callLogAPI parsing failed',
                                message: ex.message,
                                type: 'danger'
                            });
                            console.error("watchdog::callLogAPI parsing failed", ex);
                        });
                    },
                    addLogList: function (message) {
                        if (this.list.length == this.count) {
                            this.list.pop();
                        } else if (this.list.length > this.count) {
                            this.list = [];
                        }
                        this.list.unshift(message);
                    }
                },
                mounted() {
                    this.callLogAPI();
                }
            },
            "schedule-task": {
                template: `<b-card header="排程儀表版">
                    <div class="d-flex w-100 justify-content-between">
                        <b-input-group size="sm" style="width:125px">
                            <b-input-group-prepend is-text>顯示筆數</b-input-group-prepend>
                            <b-form-input
                                type="number"
                                v-model="count"
                                size="sm"
                                min="1"
                            ></b-form-input>
                        </b-input-group>
                        <small class="text-muted text-center">
                            <b-button variant="primary" size="sm" @click="callWatchdogAPI">
                                執行
                                <b-badge variant="light">
                                    <countdown ref="countdown" :time="milliseconds" :auto-start="false">
                                        <template slot-scope="props">{{ props.minutes }}:{{ props.seconds }} </template>
                                    </countdown>
                                    <span class="sr-only">count down</span></b-badge>
                            </b-button>
                        </small>
                    </div>
                    <small>
                        <b-list-group flush>
                            <b-list-group-item v-for="item in history">{{item}}</b-list-group-item>
                        </b-list-group>
                    </small>
                </b-card>`,
                data: function() {
                    return {
                        milliseconds: 15 * 60 * 1000,
                        count: 4,
                        history: [],
                        watchdog_timer: null
                    }
                },
                methods: {
                    resetCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = this.milliseconds;
                    },
                    startCountdown: function () {
                        this.$refs.countdown.start();
                    },
                    endCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = 0;
                    },
                    addHistory: function (message) {
                        if (this.history.length == this.count) {
                            this.history.pop();
                        } else if (this.history.length > this.count) {
                            this.history = [];
                        }
                        this.history.unshift(message);
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
                                this.addHistory(`${now} 結果: OK`);
                                // Backend will check if it needs to do or not
                                this.watchdog_timer = setTimeout(this.callWatchdogAPI, this.milliseconds);	// call the watchdog every 15 mins
                                this.resetCountdown();
                                this.startCountdown();
                            } else {
                                // stop the timer if API tells it is not working
                                this.addHistory(`${now} 結果: ${jsonObj.message}`);
                                console.warn(jsonObj.message);
                            }
                        }).catch(ex => {
                            this.endCountdown();
                            this.addHistory(`${now} 結果: ${ex.message}`);
                            showAlert({
                                title: 'schedule-task::callWatchdogAPI parsing failed',
                                message: ex.message,
                                type: 'danger'
                            });
                            console.error("schedule-task::callWatchdogAPI parsing failed", ex);
                        });
                    }
                },
                mounted() {
                    this.callWatchdogAPI();
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
