if (Vue) {
    Vue.component("lah-case-reg-overdue", {
        components: { "countdown": VueCountdown },
        template: `<div>
            <div style="right: 1rem; position:absolute; top: 0.5rem;" v-if="!is_in_modal_mode">
                <b-form-checkbox v-b-tooltip.hover.top="modeTooltip" inline v-model="overdueMode" switch style="margin-right: 0rem; margin-top: .15rem;" :class="['align-baseline', 'btn', 'btn-sm', is_overdue_mode ? '' : 'border-warning', 'p-1']">
                    <span>{{modeText}}</span>
                </b-form-checkbox>
                <b-button variant="outline-success" size="sm" title="已傳送的通知計數(1090214起)" @click="message">
                    通知
                    <b-badge variant="success">
                    {{message_count}}
                    </b-badge>
                    人次
                </b-button>
                <b-button v-show="statsMode" size="sm" @click="downloadPNG">
                    <b-icon icon="download" font-scale="1"></b-icon>
                    下載圖檔
                </b-button>
                <b-button v-show="all_cases_mode" variant="secondary" size="sm" @click="switchMode()">
                    <b-icon v-if="listMode" icon="bar-chart-fill" font-scale="1"></b-icon>
                    <b-icon v-else icon="table" font-scale="1"></b-icon>
                    {{listMode ? "統計圖表" : "表格顯示"}}
                </b-button>
                <b-button id="reload" variant="primary" size="sm" @click="reload">
                    <i class="fas fa-sync"></i>
                    刷新
                    <b-badge variant="light">
                        <countdown ref="countdown" :time="milliseconds" @end="handleCountdownEnd" @start="handleCountdownStart" :auto-start="false">
                            <template slot-scope="props">{{ props.minutes.toString().padStart(2, '0') }}:{{ props.seconds.toString().padStart(2, '0') }}</template>
                        </countdown>
                        <span class="sr-only">倒數</span>
                    </b-badge>
                </b-button>
            </div>
            <lah-transition @after-leave="afterTableLeave">
                <b-table
                    ref="case_list_tbl"
                    striped
                    hover
                    responsive
                    bordered
                    head-variant="dark"
                    caption-top
                    no-border-collapse
                    :small="small"
                    :caption="caption"
                    :items="table_items"
                    :fields="fields"
                    :busy="isBusy"
                    v-show="listMode"
                    class="text-center s-90"
                >
                    <template v-slot:table-busy>
                        <div class="text-center text-danger my-5">
                            <strong>查詢中 ...</strong>
                        </div>
                    </template>
                    <template v-slot:cell(序號)="data">
                        {{data.index + 1}}
                    </template>
                    <template v-slot:cell(初審人員)="data">
                        <b-button v-if="all_cases_mode" :variant="is_overdue_mode ? 'outline-danger' : 'warning'" :size="small ? 'sm' : 'md'" @click="searchByReviewer(data.value)" :title="'查詢 '+data.value+' 的'+(is_overdue_mode ? '逾期' : '即將逾期')+'案件'">{{data.value.split(" ")[0]}}</b-button>
                        <span v-else>{{data.value.split(" ")[0]}}</span>
                    </template>
                    <template v-slot:cell(作業人員)="data">
                        <b-button :data-name="data.value" :data-id="data.value" variant="outline-secondary" :size="small ? 'sm' : 'md'" @click="usercard" :title="'查詢 '+data.value+' 的使用者訊息'">{{data.value}}</b-button>
                    </template>
                </b-table>
            </lah-transition>
            
            <lah-transition @after-leave="afterStatsLeave">
                <div class="mt-5" v-show="statsMode">
                    <div class="mx-auto w-75">
                        <lah-chart ref="statsChart" :type="chartType" :label="chart_legend" :items="chartItems" @click="handleChartClick"></lah-chart>
                    </div>
                    <b-button-group style="margin-left: 12.5%" class="w-75 mt-2" size="sm">
                        <b-button variant="primary" @click="chartType = 'bar'"><i class="fas fa-chart-bar"></i> 長條圖</b-button>
                        <b-button variant="secondary" @click="chartType = 'pie'"><i class="fas fa-chart-pie"></i> 圓餅圖</b-button>
                        <b-button variant="success" @click="chartType = 'line'"><i class="fas fa-chart-line"></i> 線型圖</b-button>
                        <b-button variant="warning" @click="chartType = 'polarArea'"><i class="fas fa-chart-area"></i> 區域圖</b-button>
                        <b-button variant="info" @click="chartType = 'doughnut'"><i class="fab fa-edge"></i> 甜甜圈</b-button>
                        <b-button variant="dark" @click="chartType = 'radar'"><i class="fas fa-broadcast-tower"></i> 雷達圖</b-button>
                    </b-button-group>
                </div>
            </lah-transition>
        </div>`,
        props: ['inSearchID'],
        data: () => ({
            fields: [
                '序號',
                {key: "收件字號", sortable: true},
                {key: "登記原因", sortable: true},
                {key: "辦理情形", sortable: true},
                {key: "初審人員", sortable: true},
                {key: "作業人員", sortable: true},
                {key: "收件時間", sortable: true},
                {key: "限辦期限", sortable: true}
            ],
            reviewerID: "",
            height: 0,  // the height inside the modal
            caption: "查詢中 ... ",
            small: true,
            milliseconds: 15 * 60 * 1000,
            listMode: true,
            statsMode: false,
            overdueMode: true,
            modeText: "逾期模式",
            modeTooltip: "逾期案件查詢模式",
            chartType: "bar",
            chartItems: [],
            title: "逾期",
            message_count: 0,
            storeModule: {
                namespaced: true,
                state: function() { return {
                    list : {},
                    list_count: 0,
                    list_by_id: {},
                    list_by_id_count: 0,
                    is_overdue_mode: true
                } },
                getters: {
                    list: state => state.list,
                    list_count: state => state.list_count,
                    list_by_id: state => state.list_by_id,
                    list_by_id_count: state => state.list_by_id_count,
                    is_overdue_mode: state => state.is_overdue_mode
                },
                mutations: {
                    list(state, jsonPayload) {
                        state.list = jsonPayload || {};
                        state.list_count = Object.keys(state.list).length;
                    },
                    list_by_id(state, jsonPayload) {
                        state.list_by_id = jsonPayload || {};
                        state.list_by_id_count = Object.keys(state.list_by_id).length;
                    },
                    is_overdue_mode(state, flagPayload) {
                        state.is_overdue_mode = flagPayload;
                    }
                }
            }
        }),
        computed: {
            total_case() { return this.$store.getters['overdue_reg_cases/list_count']; },
            total_people() { return this.$store.getters['overdue_reg_cases/list_by_id_count']; },
            case_list() { return this.$store.getters['overdue_reg_cases/list']; },
            case_list_by_id() { return this.$store.getters['overdue_reg_cases/list_by_id']; },
            is_overdue_mode() { return this.$store.getters['overdue_reg_cases/is_overdue_mode']; },
            is_in_modal_mode() { return !this.empty(this.inSearchID); },
            all_cases_mode() { return this.empty(this.reviewerID) && !this.is_in_modal_mode; },
            table_items() { return this.is_in_modal_mode ? this.case_list_by_id[this.inSearchID] : this.case_list; },
            cache_key() { return this.reviewerID + '-' + (this.is_overdue_mode ? "overdue_reg_cases" : "almost_overdue_reg_cases"); },
            chart_legend() { return `${this.is_overdue_mode ? "" : "即將"}逾期案件統計表 (${this.total_people}人，共${this.total_case}件)` }
        },
        watch: {
            overdueMode: function(isChecked) {
                // also update store's flag
                this.$store.commit("overdue_reg_cases/is_overdue_mode", isChecked);
                this.title = isChecked ? "逾期" : "即將逾期";
                this.modeText = isChecked ? "逾期模式" : "即將逾期"
                this.modeTooltip = isChecked ? "逾期案件查詢模式" : "即將逾期模式(4小時內)";
                // calling api must be the last one to do
                this.load();
            },
            isBusy: function(flag) {
                if (flag) {
                    addLDAnimation("#reload .fas.fa-sync", "ld-cycle");
                    if (this.statsMode) this.busyOn("body", "lg");
                } else {
                    clearLDAnimation("#reload .fas.fa-sync");
                    if (this.statsMode) this.busyOff("body");
                }
            }
        },
        methods: {
            switchMode: function() {
                if (this.listMode) {
                    // use afterTableLeave to control this.statsMode
                    this.listMode = false;
                }
                if (this.statsMode) {
                    // use afterStatsLeave to control this.listMode
                    this.statsMode = false;
                }
            },
            afterTableLeave: function () {
                this.statsMode = true;
            },
            afterStatsLeave: function () {
                this.listMode = true;
            },
            setChartData: function() {
                this.chartItems = [];
                let total = 0;
                for (let id in this.case_list_by_id) {
                    let item = [this.case_list_by_id[id][0]["初審人員"], this.case_list_by_id[id].length];
                    this.chartItems.push(item);
                    total += this.case_list_by_id[id].length;
                }
            },
            resetCountdown: function () {
                this.$refs.countdown.totalMilliseconds = this.milliseconds;
            },
            setCountdown: function (milliseconds) {
                this.$refs.countdown.totalMilliseconds = milliseconds || this.milliseconds;
            },
            startCountdown: function () {
                this.$refs.countdown.start();
            },
            endCountdown: function () {
                this.$refs.countdown.end();
            },
            makeCaseIDClickable: function () {
                this.animated("table tr td:nth-child(2)", { name: "flash" })
                .off("click")
                .on("click", window.vueApp.fetchRegCase)
                .addClass("reg_case_id");
            },
            searchByReviewer: function(reviewer_data) {
                if (this.empty(reviewer_data)) {
                    this.$warn(`reviewer_data is empty. skip searchByReviewer function call.`);
                    return;
                }
                // reviewer_data, e.g. "ＯＯＯ HB1184"
                this.msgbox({
                    title: `查詢 ${reviewer_data} 登記案件(${this.title})`,
                    message: this.$createElement('lah-case-reg-overdue', { props: { inSearchID: reviewer_data.split(" ")[1] } }),
                    size: "xl"
                });
            },
            handleCountdownStart: function (e) {},
            handleCountdownEnd: function(e) { this.load(e); },
            handleChartClick: function (e, payload) {
                // show the modal of user's case table
                // payload, e.g. {point: i, label: "黃欣怡 HB1206", value: 5}
                this.searchByReviewer(payload.label);
            },
            reload: async function () {
                try {
                    const succeed = await this.removeLocalCache(this.cache_key);
                    if (succeed) this.load();
                } catch (err) {
                    console.error(err);
                }
            },
            load: function(e) {
                // busy ...
                this.isBusy = true;
                this.title = this.is_overdue_mode ? "逾期" : "即將逾期";
                if (this.is_in_modal_mode) {
                    // in-search, by clicked the first reviewer button
                    let case_count = this.case_list_by_id[this.inSearchID].length || 0;
                    this.caption = `${case_count} 件`;
                    this.notify({ title: `查詢登記案件(${this.title})`, message: `查詢到 ${case_count} 件案件` });
                    // release busy ...
                    this.isBusy = false;
                    Vue.nexTick ? Vue.nexTick(this.makeCaseIDClickable) : this.timeout(this.makeCaseIDClickable, 800);
                } else {
                    this.getLocalCache(this.cache_key).then(jsonObj => {
                        if (jsonObj === false) {
                            this.$http.post(CONFIG.API.JSON.QUERY, {
                                type: this.is_overdue_mode ? "overdue_reg_cases" : "almost_overdue_reg_cases",
                                reviewer_id: this.reviewerID
                            }).then(res => {
                                this.setLocalCache(this.cache_key, res.data, this.milliseconds - 5000);   // expired after 14 mins 55 secs
                                console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL || res.data.status == XHR_STATUS_CODE.SUCCESS_WITH_NO_RECORD, `查詢登記案件(${this.title})回傳狀態碼有問題【${res.data.status}】`);
                                if (res.data.status != XHR_STATUS_CODE.SUCCESS_NORMAL && res.data.status != XHR_STATUS_CODE.SUCCESS_WITH_NO_RECORD) {
                                    this.removeLocalCache(this.cache_key);
                                }
                                this.loaded(res.data);
                            }).catch(err => {
                                this.caption = err.message;
                                this.error = err;
                            }).finally(() => {
                                this.isBusy = false;
                            });
                        } else {
                            // cache hit!
                            this.loaded(jsonObj);
                            this.getLocalCacheExpireRemainingTime(this.cache_key).then(remaining_cache_time => {
                                this.setCountdown(remaining_cache_time + 5000);
                                this.caption = `${jsonObj.data_count} 件，更新時間: ${new Date(+new Date() - this.milliseconds + remaining_cache_time - 5000)}`;
                                this.$warn(`快取資料將在 ${(remaining_cache_time / 1000).toFixed(1)} 秒後到期。`);
                            });
                        }
                    });
                }
            },
            loaded: function (jsonObj) {
                // set data to store
                // NOTE: the payload must be valid or it will not update UI correctly
                this.$store.commit("overdue_reg_cases/list", jsonObj.items);
                this.$store.commit("overdue_reg_cases/list_by_id", jsonObj.items_by_id);

                this.caption = `${jsonObj.data_count} 件，更新時間: ${new Date()}`;

                // release busy ...
                this.isBusy = false;
                // prepare the chart data for rendering
                this.setChartData();
                // make .reg_case_id clickable
                Vue.nextTick(this.makeCaseIDClickable);
                // send notification
                this.notify({
                    title: `查詢登記案件(${this.title})`,
                    message: `查詢到 ${jsonObj.data_count} 件案件`,
                    type: this.is_overdue_mode ? "danger" : "warning"
                });
                
                let now = new Date();
                if (now.getHours() >= 7 && now.getHours() < 17) {
                    // auto start countdown to prepare next reload
                    this.resetCountdown();
                    this.startCountdown();
                    // add effect to catch attention
                    this.animated("#reload, caption", {name: "flash"});
                } else {
                    console.warn("非上班時間，停止自動更新。");
                    this.notify({
                        title: "自動更新停止通知",
                        message: "非上班時間，停止自動更新。",
                        type: "warning"
                    });
                }
            },
            getOverdueMessageStats: function(e) {
                this.$http.post(CONFIG.API.JSON.QUERY,{
                    type: "stats_overdue_msg_total"
                }).then(res => {
                    console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `查詢逾期案件統計回傳狀態碼有問題【${res.data.status}】`);
                    this.message_count = parseInt(res.data.total);
                }).catch(ex => {
                    console.error("lah-case-reg-overdue::getOverdueMessageStats parsing failed", ex);
                    this.alert({message: "lah-case-reg-overdue::getOverdueMessageStats XHR連線查詢有問題!!【" + ex.message + "】", type: "danger"});
                });
            },
            downloadPNG: function() {
                this.$refs.statsChart.downloadBase64PNG(`${this.chartType}.png`);
            },
            message: function() {
                this.msgbox({
                    title: '我的信差訊息',
                    message: this.$createElement(
                        'lah-user-message-history',
                        { props: { count: 9, tabs: true }}
                    )
                });
            }
        },
        mounted() {
            // if (this.is_in_modal_mode) {
            //     // in modal dialog
            //     this.height = window.innerHeight - 190 + "px";
            //     this.small = true;
            // } else {
            //     this.height = window.innerHeight - 100 + "px";
            // }
        },
        created() {
            if (!this.is_in_modal_mode) {
                // register specific data store for using in both mode (page/modal)
                this.$store.registerModule('overdue_reg_cases', this.storeModule);
                this.getOverdueMessageStats();
            }
            this.reviewerID = this.getUrlParameter("ID") || this.getUrlParameter("reviewerID");
            this.load();
        },
        beforeDestroy() {
            this.$store.unregisterModule('overdue_reg_cases');
        }
    });
} else {
    console.error("vue.js not ready ... lah-case-reg-overdue component can not be loaded.");
}
