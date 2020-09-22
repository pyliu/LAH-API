if (Vue) {
    Vue.component("tasklog", {
        template: `<div>
            <lah-transition>
                <schedule-task
                    v-show="showScheduleTask"
                    class="mb-1"
                    ref="task"
                    @fail-not-valid-server="handleFailed"
                    @succeed-valid-server="handleSucceeded"
                ></schedule-task>
            </lah-transition>
            <log-viewer ref="log"></log-viewer>
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
            "log-viewer": {
                template: `<b-card bo-body :header="'紀錄儀表版 ' + query_data_count + ' / ' + query_total_count">
                    <div class="d-flex w-100 justify-content-between">
                        <b-input-group size="sm" style="width:155px">
                            <b-input-group-prepend is-text>顯示筆數</b-input-group-prepend>
                            <b-form-input
                                ref="log"
                                type="number"
                                v-model="count"
                                size="sm"
                                min="1"
                                class="h-100"
                            ></b-form-input>
                        </b-input-group>
                        <a href="javascript:void(0)" @click="download"><i class="fas fa-download"></i> 下載</a>
                        <small class="text-muted text-center">
                            <b-button @click="zip" size="sm" title="壓縮伺服器端紀錄檔案(不包含今天)">壓縮</b-button>
                            <b-button variant="primary" size="sm" @click="loadLog">
                                <i class="fas fa-sync"></i>
                                刷新
                                <b-badge variant="light">
                                    <countdown ref="countdown" :time="milliseconds" :auto-start="false" @end="loadLog">
                                        <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }}</template>
                                    </countdown>
                                    <span class="sr-only">倒數</span>
                                </b-badge>
                            </b-button>
                        </small>
                    </div>
                    <b-list-group flush class="small">
                        <b-list-group-item v-for="item in log_list">{{item}}</b-list-group-item>
                    </b-list-group>
                </b-card>`,
                components: { "countdown": VueCountdown },
                data: function () {
                    return {
                        log_list: [],
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
                    loadLog: function (e) {
                        this.isBusy = true;
                        let dt = new Date();
                        this.log_update_time = `${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                        this.log_filename = `log-${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}.log`
                        this.$http.post(CONFIG.API.FILE.LOAD, {
                            type: "load_log",
                            log_filename: this.log_filename,
                            slice_offset: this.count * -1   // get lastest # records
                        }).then(res => {
                            // normal success res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.query_data_count = res.data.data_count;
                                this.query_total_count = res.data.total_count;
                                res.data.data.forEach((item, index, array) => {
                                    this.addLogList(item);
                                });
                                this.resetCountdown();
                                this.startCountdown();
                            } else {
                                // stop the timer if API tells it is not working
                                this.addLogList(`${this.log_update_time} 錯誤: ${res.data.message}`);
                                console.warn(res.data.message);
                            }
                        }).catch(err => {
                            this.abortCountdown();
                            this.addLogList(`${this.log_update_time} 錯誤: ${err.message}`);
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    },
                    addLogList: function (message) {
                        if (this.log_list.length == this.count) {
                            this.log_list.pop();
                        } else if (this.log_list.length > this.count) {
                            this.log_list = [];
                        }
                        this.log_list.unshift(message);
                    },
                    download: function(e) {
                        let dt = new Date();
                        // e.g. 2020-02-24
                        let date = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))}`;
                        window.vueApp.download(CONFIG.API.FILE.EXPORT, {
                            type: "file_log",
                            date: date,
                            filename: this.log_filename
                        });
                    },
                    zip: function(e) {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: 'zip_log'
                        }).then(res => {
                            this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + res.data.message + "】");
                            this.notify({
                                title: "壓縮LOG檔",
                                message: `<i class="text-success fas fa-circle"></i> 任務完成！`,
                                type: "success"
                            });
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    }
                },
                mounted() {
                    this.timeout(() => {
                        this.count = this.$refs.log.$el.value || 50;
                        this.loadLog();
                    }, 400);
                    
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
                                <i class="fas fa-calendar-check"></i>
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
                components: { "countdown": VueCountdown },
                data: function() {
                    return {
                        milliseconds: 15 * 60 * 1000,
                        count: 4,
                        history: [],
                        timer: null,
                        anim_pattern: ["ld-bounceAlt", "ld-breath", "ld-rubber-v", "ld-beat", "ld-float", "ld-dim", "ld-damage"],
                        busy: false
                    }
                },
                watch: {
                    busy: function(flag) {
                        if (flag) {
                            addLDAnimation(".fas.fa-calendar-check", "ld-heartbeat");
                        } else {
                            clearLDAnimation(".fas.fa-calendar-check");
                        }
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
                        this.isBusy = true;
                        // generate current date time string
                        let dt = new Date();
                        let now = `${dt.getFullYear()}-${(dt.getMonth()+1).toString().padStart(2, '0')}-${(dt.getDate().toString().padStart(2, '0'))} ${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}:${dt.getSeconds().toString().padStart(2, '0')}`;
                        
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "watchdog"
                        }).then(res => {
                            // normal success res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL
                            if (res.data.status == XHR_STATUS_CODE.FAIL_NOT_VALID_SERVER) {
                                // 此功能僅在伺服器上執行！
                                this.$emit("fail-not-valid-server");
                                this.$warn(res.data.message);
                            } else {
                                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    this.addHistory(`${now}：執行結果正常。`);
                                } else {
                                    this.addHistory(`${now}：${res.data.message}`);
                                    console.warn(res.data.message);
                                }
                                this.resetCountdown();
                                this.startCountdown();
                                this.$emit("succeed-valid-server");
                            }
                        }).catch(err => {
                            this.abortCountdown();
                            this.addHistory(`${now} 結果: ${err.message}`);
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
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
