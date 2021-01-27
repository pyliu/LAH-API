
if (Vue) {
    let temperatureMixin = {
        methods: {
            thermoIcon(degree) {
                let fd = parseFloat(degree);
                if (isNaN(fd)) return 'temperature-low';
                if (fd < 36.0) return 'thermometer-empty';
                if (fd < 36.5) return 'thermometer-quarter';
                if (fd < 37.0) return 'thermometer-half';
                if (fd < 37.5) return 'thermometer-three-quarters';
                return 'thermometer-full';
            },
            thermoColor(degree) {
                let fd = parseFloat(degree);
                if (isNaN(fd) || fd < 35) return 'secondary';
                if (fd < 35.5) return 'dark';
                if (fd < 36) return 'info';
                if (fd < 36.5) return 'primary';
                if (fd < 37.0) return 'success';
                if (fd < 37.5) return 'warning';
                return 'danger';
            }
        }
    };

    Vue.component("lah-temperature", {
        mixins: [temperatureMixin],
        template: `<b-card>
            <template v-slot:header>
                <h6 class="d-flex justify-content-between mb-0">
                    <span class="my-auto">體溫紀錄 {{today}}</span>
                    <b-button @click="overview" variant="primary" size="sm">全所登錄一覽</b-button>
                </h6>
            </template>
            <b-form-row>
                <b-col>
                    <lah-user-id-input v-model="id" :only-on-board="true" ref="userid"></lah-user-id-input>
                </b-col>
                <b-col cols="auto">
                    <b-input-group class="mb-1">
                        <b-input-group-prepend is-text><lah-fa-icon :icon="thermoIcon(temperature)" :variant="thermoColor(temperature)"> 體溫</la-fa-icon></b-input-group-prepend>
                        <b-form-input
                            type="number"
                            v-model="temperature"
                            min="34"
                            max="41"
                            step="0.1"
                            class="no-cache"
                            @keyup.enter="register"
                        >
                        </b-form-input>
                    </b-input-group>
                </b-col>
                <b-col cols="auto">
                    <b-button variant="outline-primary" @click="register" :disabled="disabled">登錄</b-button>
                </b-col>
            </b-form-row>
            <div v-if="seen">
                <h6 class="my-2">今日紀錄</h6>
                <b-list-group class="small">
                    <b-list-group-item v-for="item in list" :primary-key="item['datetime']" v-if="onlyToday(item)" >
                        <a href="javascript:void(0)" @click="doDeletion(item)" v-if="allowDeletion(item)"><lah-fa-icon class="times-circle float-right" icon="times-circle" prefix="far" variant="danger"></lah-fa-icon></a>
                        {{item['datetime']}} - {{item['id']}}:{{userNames[item['id']]}} - 
                        <lah-fa-icon :icon="thermoIcon(item['value'])" :variant="thermoColor(item['value'])"> {{item['value']}} &#8451;</lah-fa-icon>
                    </b-list-group-item>
                </b-list-group>
            </div>
            <template v-if="seen" v-slot:footer>
                <div class="my-2">
                    <b-button-group size="sm" class="float-right">
                        <b-button variant="primary" @click="chart_type = 'bar'"><i class="fas fa-chart-bar"></i></b-button>
                        <b-button variant="success" @click="chart_type = 'line'"><i class="fas fa-chart-line"></i></b-button>
                    </b-button-group>
                    <lah-chart
                        ref="chart"
                        :items="chart_items"
                        :type="chart_type"
                        :begin-at-zero="false"
                        :bg-color="chartBgColor"
                        label="歷史紀錄" 
                        class="clearfix"
                    >
                    </lah-chart>
                </div>
            </template>
        </b-card>`,
        data: () => ({
            today: undefined,
            ad_today: undefined,
            id: undefined,
            temperature: '',
            chart_items: undefined,
            chart_type: 'line',
            list: undefined
        }),
        computed: {
            ID() {
                return this.id ? this.id.toUpperCase() : null
            },
            name() {
                return this.userNames[this.ID] || ''
            },
            validateID() {
                return (/^HB\d{4}$/i).test(this.ID)
            },
            validateTemperature() {
                let fn = parseFloat(this.temperature);
                return !isNaN(fn) && fn >= 34 && fn <= 41;
            },
            disabled() {
                return !this.validateID || !this.validateTemperature
            },
            seen() {
                return this.chart_items !== undefined && this.chart_items.length != 0
            }
        },
        watch: {
            name(val) {
                if (this.empty(val)) this.chart_items = undefined
            },
            id(val) {
                if (this.validateID) this.history()
            },
            myid(val) {
                this.id = val
            }
        },
        methods: {
            onlyToday(item) {
                return item['datetime'].split(' ')[0].replace(/\-/gi, '') == this.ad_today
            },
            allowDeletion(item) {
                // control deletion by AM/PM
                let now = parseInt(this.nowTime.replace(/\:/gi, ''));
                let AMPM = (now - 120000) > 0 ? 'PM' : 'AM';
                let time = parseInt(item['datetime'].split(' ')[1].replace(/\:/gi, ''));
                if (AMPM == 'AM') {
                    return time - 120000 < 0;
                }
                return time - 120000 >= 0;
            },
            doDeletion(item) {
                this.$confirm(`刪除 ${this.userNames[item['id']]} ${item['value']} &#8451;紀錄？`, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: 'remove_temperature',
                        id: item['id'],
                        datetime: item['datetime']
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "刪除體溫資料回傳狀態碼有問題【" + res.data.status + "】");
                        this.notify({
                            title: "刪除體溫紀錄",
                            subtitle: `${item['id']}:${this.userNames[item['id'].toUpperCase()]}-${item['value']}`,
                            message: "刪除成功。",
                            type: "success"
                        });
                        this.history();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            register() {
                if (this.disabled) {
                    this.$warn('輸入資料未齊全，跳過登錄。')
                    return;
                }
                if (this.empty(this.name)) {
                    this.notify({
                        message: `無法找到使用者 ${this.ID}，無法登錄。`,
                        type: "warning"
                    });
                    return;
                }
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'add_temperature',
                    id: this.ID,
                    temperature: this.temperature
                }).then(res => {
                    this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "設定體溫資料回傳狀態碼有問題【" + res.data.status + "】");
                    if (res.data.status != XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.notify({
                            title: "新增體溫紀錄",
                            message: res.data.message,
                            type: "warning",
                            pos: 'tc'
                        });
                    } else {
                        this.notify({
                            title: "新增體溫紀錄",
                            message: "已設定完成。<p>" + this.ID + "-" + this.name + "-" + this.temperature + "</p>",
                            type: "success"
                        });
                        this.history();
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            history(all = false) {
                this.isBusy = true;
                if (all) this.chart_items = undefined;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'temperatures',
                    id: all ? '' : this.ID
                }).then(res => {
                    this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "取得體溫資料回傳狀態碼有問題【" + res.data.status + "】");
                    this.list = res.data.raw;
                    this.prepareChartData();
                    Vue.nextTick(() => $(".times-circle i.far").on("mouseenter", e => {
                        this.animated(e.target, {
                            name: "tada"
                        });
                    }));
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            chartBgColor(data, opacity) {
                let degree = data[1]; // data[0] - text, data[1] - value
                let fd = parseFloat(degree);
                //if (isNaN(fd) || fd < 35) return `rgb(91, 93, 94, ${opacity})`;
                // if (fd < 35.5) return `rgb(41, 43, 44, ${opacity})`;
                // if (fd < 36) return `rgb(91, 192, 222, ${opacity})`;
                // if (fd < 36.5) return `rgb(2, 117, 216, ${opacity})`;
                if (fd < 37.0) return `rgb(92, 184, 92, ${opacity})`;
                if (fd < 37.5) return `rgb(240, 173, 78, ${opacity})`;
                return `rgb(217, 83, 79, ${opacity})`;
            },
            prepareChartData() {
                let chart_items = [];
                let count = 0;
                this.list.forEach((item) => {
                    if (count < 10) {
                        let date = item['datetime'].substring(5, 10); // remove year
                        let hour = item['datetime'].substring(11, 13);
                        let AMPM = parseInt(hour) < 12 ? 'AM' : 'PM';
                        chart_items.push([
                            `${date} ${AMPM}`,
                            item['value']
                        ]);
                        count++;
                    }
                });
                this.chart_items = chart_items.reverse();
            },
            overview() {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.USER, {
                    type: 'on_board_users'
                }).then(res => {
                    let vn = this.$createElement('lah-temperature-list', {
                        props: {
                            userList: res.data.raw
                        }
                    });
                    this.msgbox({
                        title: `<i class="fa fa-temperature-low fa-lg"></i> 全所體溫一覽表 ${this.today}`,
                        message: vn,
                        size: "xl"
                    });
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        },
        created() {
            let now = new Date();
            let mon = ("0" + (now.getMonth() + 1)).slice(-2);
            let day = ("0" + now.getDate()).slice(-2);
            this.today = now.getFullYear() - 1911 + mon + day;
            this.ad_today = now.getFullYear() + mon + day;
            this.id = this.getUrlParameter('id');
        }
    });

    Vue.component('lah-temperature-list', {
        template: `<div>
            <b-button-group size="sm" class="float-right">
                <b-button variant="light" @click="selectBtn('all')" class="border"><i class="fas fa-list"></i> 全部</b-button>
                <b-button variant="success" @click="selectBtn('.all-done')"><i class="fas fa-check"></i> 已完成</b-button>
                <b-button variant="primary" @click="selectBtn('.half-done')"><i class="fas fa-temperature-low"></i> 登錄中</b-button>
                <b-button variant="secondary" @click="selectBtn('.not-done')"><i class="fas fa-exclamation-circle"></i> 未登錄</b-button>
            </b-button-group>
            <div v-for="item in filtered" class="clearfix my-2">
                <h5><lah-fa-icon icon="address-book" prefix="far"> {{item['UNIT']}}</lah-fa-icon></h5>
                <div>
                    <lah-user-temperature
                        v-for="user in item['LIST']"
                        :raw-user-data="user"
                        class="float-left ml-1 mb-1"
                    ></lah-user-temperature>
                </div>
            </div>
        </div>`,
        props: {
            userList: {
                type: Object,
                default: null
            },
            date: {
                type: String,
                default: this.today
            }
        },
        data: () => ({
            filtered: null
        }),
        computed: {
            today() {
                return this.nowDate
            }
        },
        methods: {
            filter() {
                let hr = this.userList.filter(this_record => this_record["unit"] == "人事室");
                let accounting = this.userList.filter(this_record => this_record["unit"] == "會計室");
                let director = this.userList.filter(this_record => this_record["unit"] == "主任室");
                let secretary = this.userList.filter(this_record => this_record["unit"] == "秘書室");
                let adm = this.userList.filter(this_record => this_record["unit"] == "行政課");
                let reg = this.userList.filter(this_record => this_record["unit"] == "登記課");
                let val = this.userList.filter(this_record => this_record["unit"] == "地價課");
                let sur = this.userList.filter(this_record => this_record["unit"] == "測量課");
                let inf = this.userList.filter(this_record => this_record["unit"] == "資訊課");
                this.filtered = [{
                        UNIT: '主任室',
                        LIST: director
                    },
                    {
                        UNIT: '秘書室',
                        LIST: secretary
                    },
                    {
                        UNIT: '人事室',
                        LIST: hr
                    },
                    {
                        UNIT: '會計室',
                        LIST: accounting
                    },
                    {
                        UNIT: '行政課',
                        LIST: adm
                    },
                    {
                        UNIT: '登記課',
                        LIST: reg
                    },
                    {
                        UNIT: '地價課',
                        LIST: val
                    },
                    {
                        UNIT: '測量課',
                        LIST: sur
                    },
                    {
                        UNIT: '資訊課',
                        LIST: inf
                    }
                ];
            },
            prepareTodayTemperatures() {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'temperatures_by_date',
                    date: this.today
                }).then(res => {
                    this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得 ${this.today} 體溫資料回傳狀態碼有問題【${res.data.status}】`);
                    this.addToStoreParams('todayTemperatures', res.data.raw);
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            selectBtn(selector) {
                $(`button.temperature`).hide();
                switch (selector) {
                    case ".all-done":
                    case ".half-done":
                    case ".not-done":
                        $(selector).show();
                        break;
                    default:
                        $(`button.temperature`).show();
                }
            }
        },
        created() {
            this.prepareTodayTemperatures();
        },
        mounted() {
            this.filter();
        }
    });

    Vue.component('lah-user-temperature', {
        mixins: [temperatureMixin],
        template: `<b-button
            :id="btnid"
            :data-id="id"
            :data-name="name"
            :variant="style"
            :class="[selector, 'text-left', 'mr-1', 'mb-1', 'temperature', 'lah-user-card', 'position-relative']"
            size="sm"
            @click="usercard"
            v-b-hover="hover"
            style="width: 100.3px; height: 73.81px;"
        >
            <div><b-avatar button variant="light" :size="avatar_size" :src="avatar_src"></b-avatar> {{name}}</div>
            <lah-fa-icon  v-if="temperature['AM'] > 0" :icon="am_icon" :variant="am_color" class="d-block"> {{temperature['AM']}} &#8451; AM</lah-fa-icon>
            <lah-fa-icon v-if="temperature['PM'] > 0" :icon="pm_icon" :variant="pm_color" class="d-block"> {{temperature['PM']}} &#8451; PM</lah-fa-icon>
            <b-popover :target="btnid" triggers="hover focus" placement="top" delay="1250">
                {{id}} : {{name}}
                <lah-fa-icon :icon="am_icon" :variant="am_color" class="d-block"> {{temperature['AM']}} &#8451; AM</lah-fa-icon>
                <lah-fa-icon :icon="pm_icon" :variant="pm_color" class="d-block"> {{temperature['PM']}} &#8451; PM</lah-fa-icon>
            </b-popover>
        </b-button>`,
        props: ['rawUserData', 'inId'],
        data: () => ({
            temperature: {
                AM: 0,
                PM: 0
            },
            avatar_size: '1.2rem',
            btnid: '',
        }),
        watch: {
            my_temperature(val) {
                this.empty(val) ? void(0) : this.setMyTemperature()
            }
        },
        computed: {
            id() {
                return this.rawUserData ? $.trim(this.rawUserData['id']) : this.inId
            },
            name() {
                return this.rawUserData ? this.rawUserData['name'] : ''
            },
            today() {
                return this.nowDate
            },
            now_ampm() {
                return (parseInt(this.nowTime.replace(/\:/gi, '')) - 120000) >= 0 ? 'PM' : 'AM'
            },
            not_ready() {
                return this.temperature.AM == 0 || this.temperature.PM == 0
            },
            ready_half() {
                return this.temperature.AM != 0 || this.temperature.PM != 0
            },
            ready() {
                return this.temperature.AM != 0 && this.temperature.PM != 0
            },
            style() {
                if (this.ready) {
                    if (this.temperature.AM >= 37.5 || this.temperature.PM >= 37.5) return 'outline-danger';
                    if (this.temperature.AM >= 37 || this.temperature.PM >= 37) return 'outline-warning';
                    return 'outline-success';
                }
                return this.ready_half ? 'outline-primary' : 'outline-secondary';
            },
            temperatures() {
                return this.storeParams['todayTemperatures']
            },
            my_temperature() {
                return this.temperatures ? this.temperatures.filter(this_record => $.trim(this_record["id"]) == $.trim(this.id)) : []
            },
            store_ready() {
                return this.temperatures == undefined
            },
            avatar_src() {
                return `get_user_img.php?name=${this.name}_avatar`
            },
            am_icon() {
                return this.thermoIcon(this.temperature['AM'])
            },
            am_color() {
                return this.thermoColor(this.temperature['AM'])
            },
            pm_icon() {
                return this.thermoIcon(this.temperature['PM'])
            },
            pm_color() {
                return this.thermoColor(this.temperature['PM'])
            },
            selector() {
                if (this.ready) return 'all-done';
                if (this.ready_half) return 'half-done';
                return 'not-done';
            }
        },
        methods: {
            hover(flag, e) {
                if (flag) {
                    $(e.target).find(".b-avatar").addClass('avatar_scale_center');
                    //$(e.target).find("div:first-child").addClass('pl-3');
                    this.avatar_size = '3.8rem';
                } else {
                    $(e.target).find(".b-avatar").removeClass('avatar_scale_center');
                    //$(e.target).find("div:first-child").removeClass('pl-3');
                    this.avatar_size = '1.2rem';
                }
            },
            setMyTemperature() {
                this.my_temperature.forEach(item => {
                    let time = parseInt(item['datetime'].split(' ')[1].replace(/\:/gi, ''));
                    if (time - 120000 >= 0) {
                        // PM
                        this.temperature.PM = item['value'];
                    } else {
                        // AM
                        this.temperature.AM = item['value'];
                    }
                });
            },
            getMyTemperatures() {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'temperatures_by_id_date',
                    id: this.id,
                    date: this.today
                }).then(res => {
                    this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得 ${this.id}:${this.name} 體溫資料回傳狀態碼有問題【${res.data.status}】`);
                    /**
                        datetime: "2020-04-03 16:19:45"
                        id: "HB0541"
                        value: 37.2
                        note: ""
                    */
                    // control deletion by AM/PM
                    if (res.data.data_count > 0) {
                        res.data.raw.forEach(item => {
                            let time = parseInt(item['datetime'].split(' ')[1].replace(/\:/gi, ''));
                            if (time - 120000 >= 0) {
                                // PM
                                this.temperature.PM = item['value'];
                            } else {
                                // AM
                                this.temperature.AM = item['value'];
                            }
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        },
        mounted() {
            if (this.inId) {
                this.getMyTemperatures();
            } else {
                this.timeout(() => this.id = this.getUrlParameter('id'), 400);
            }
            this.btnid = this.uuid();
        }
    });

} else {
    console.error("vue.js not ready ... lah-temperature relative components can not be loaded.");
}
