if (Vue) {
    // for using countdown
    Vue.component(VueCountdown.name, VueCountdown);
    Vue.component("watchdog", {
        template: `<div>
            <my-transition>
                <b-form-row class="mb-1" v-show="showScheduleTask">
                    <b-col>
                        <schedule-task ref="task" @fail-not-valid-server="handleFailed" @succeed-valid-server="handleSucceeded"></schedule-task>
                    </b-col>
                </b-form-row>
            </my-transition>
            <b-form-row>
                <b-col>
                    <log-viewer ref="log"></log-viewer>
                </b-col>
            </b-form-row>

        </div>`,
        data: function() {
            return {
                showScheduleTask: false
            }
        },
        methods: {
            handleFailed: function() { this.showScheduleTask = false; },
            handleSucceeded: function() { this.showScheduleTask = true; },
        },
        components: {
            "my-transition": VueTransition,
            "log-viewer": {
                template: `<b-card bo-body :header="'紀錄儀表版 ' + query_data_count + ' / ' + query_total_count">
                    <div class="d-flex w-100 justify-content-between">
                        <b-input-group size="sm" style="width:135px">
                            <b-input-group-prepend is-text>顯示筆數</b-input-group-prepend>
                            <b-form-input
                                type="number"
                                v-model="count"
                                size="sm"
                                min="1"
                            ></b-form-input>
                        </b-input-group>
                        <a :href="'logs/' + log_filename" target="_blank">下載</a>
                        <small class="text-muted text-center">
                            <b-button variant="primary" size="sm" @click="callLogAPI">
                                <i class="fas fa-sync"></i>
                                刷新
                                <b-badge variant="light">
                                    <countdown ref="countdown" :time="milliseconds" :auto-start="false" @end="callLogAPI">
                                        <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }}</template>
                                    </countdown>
                                    <span class="sr-only">倒數</span>
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
                        milliseconds: 5 * 60 * 1000,
                        count: 50,
                        log_update_time: "10:48:00",
                        query_data_count: 0,
                        query_total_count: 0,
                        log_filename: ""
                    }
                },
                methods: {
                    resetCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = this.milliseconds;
                    },
                    abortCountdown: function () {
                        this.$refs.countdown.abort();
                    },
                    startCountdown: function () {
                        this.$refs.countdown.start();
                    },
                    endCountdown: function () {
                        this.$refs.countdown.end();
                    },
                    callLogAPI: function (e) {
                        let dt = new Date();
                        this.log_update_time = `${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                        this.log_filename = `log-${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}.log`
                        let body = new FormData();
                        body.append("type", "load_log");
                        body.append("log_filename", this.log_filename);
                        body.append("slice_offset", this.count * -1);   // get lastest # records
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
                                this.resetCountdown();
                                this.startCountdown();
                            } else {
                                // stop the timer if API tells it is not working
                                this.addLogList(`${this.log_update_time} 錯誤: ${jsonObj.message}`);
                                console.warn(jsonObj.message);
                            }
                        }).catch(ex => {
                            this.abortCountdown();
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
                        <strong id="schedule-wip-message" class="text-danger">排程執行中，請勿關閉本頁面。</strong>
                        <small class="text-muted text-center">
                            <b-button variant="primary" size="sm" @click="callWatchdogAPI">
                                執行
                                <b-badge variant="light">
                                    <countdown ref="countdown" :time="milliseconds" :auto-start="false" @end="handleCountdownEnd">
                                        <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }} </template>
                                    </countdown>
                                    <span class="sr-only">倒數</span></b-badge>
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
                        timer: null,
                        anim_pattern: ["ld-bounceAlt", "ld-breath", "ld-rubber-v", "ld-beat", "ld-float", "ld-dim", "ld-damage"]
                    }
                },
                methods: {
                    resetCountdown: function () {
                        this.$refs.countdown.totalMilliseconds = this.milliseconds;
                    },
                    abortCountdown: function () {
                        this.$refs.countdown.abort();
                    },
                    startCountdown: function () {
                        this.$refs.countdown.start();
                    },
                    endCountdown: function () {
                        this.$refs.countdown.end();
                    },
                    handleCountdownEnd: function(e) {
                        // call api endpoint
                        this.callWatchdogAPI();
                        // update the message animation
                        this.changeWIPMessageAnim();
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
                            if (jsonObj.status == XHR_STATUS_CODE.FAIL_NOT_VALID_SERVER) {
                                // 此功能僅在伺服器上執行！
                                this.$emit("fail-not-valid-server");
                                showAlert({
                                    title: "伺服器排程停止通知",
                                    message: `${jsonObj.message}`,
                                    type: "warning"
                                });
                            } else {
                                if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    this.addHistory(`${now}：執行結果正常。`);
                                } else {
                                    this.addHistory(`${now}：${jsonObj.message}`);
                                    console.warn(jsonObj.message);
                                }
                                this.resetCountdown();
                                this.startCountdown();
                                this.$emit("succeed-valid-server");
                            }
                        }).catch(ex => {
                            this.abortCountdown();
                            this.addHistory(`${now} 結果: ${ex.message}`);
                            showAlert({
                                title: 'schedule-task::callWatchdogAPI parsing failed',
                                message: ex.message,
                                type: 'danger'
                            });
                            console.error("schedule-task::callWatchdogAPI parsing failed", ex);
                        });
                    },
                    changeWIPMessageAnim: function() {
                        let len = this.anim_pattern.length;
                        addLDAnimation("#schedule-wip-message", this.anim_pattern[this.rand(len)]);
                    },
                    rand: (range) => Math.floor(Math.random() * Math.floor(range || 100))
                },
                mounted() {
                    this.callWatchdogAPI();
                    this.changeWIPMessageAnim();
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... watchdog component can not be loaded.");
}
