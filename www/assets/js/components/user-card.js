if (Vue) {
    Vue.component("user-card", {
        template: `<b-card-group columns v-show="showCard">
            <b-card
                v-for="user_data in user_rows"
                class="overflow-hidden bg-light"
                style="max-width: 540px; font-size: 0.9rem;"
                :title="user_data['AP_USER_NAME']"
                :sub-title="user_data['AP_JOB']"
            >
                <b-link :href="photoUrl(user_data)" target="_blank">
                    <b-card-img
                        :src="photoUrl(user_data)"
                        :alt="user_data['AP_USER_NAME']"
                        class="img-thumbnail float-right ml-2"
                        style="max-width: 220px"
                    ></b-card-img>
                </b-link>
                <b-card-text>
                    <p v-if="isLeft(user_data)" class='text-danger'>已離職【{{user_data["AP_OFF_DATE"]}}】</p>
                    <div>ID：{{user_data["DocUserID"]}}</div>
                    <div>電腦：{{user_data["AP_PCIP"]}}</div>
                    <div>生日：{{user_data["AP_BIRTH"]}} <b-badge v-show="birthAge(user_data) !== false" :variant="birthAgeVariant(user_data)" pill>{{birthAge(user_data)}}歲</b-badge></div>
                    <div>單位：{{user_data["AP_UNIT_NAME"]}}</div>
                    <div>工作：{{user_data["AP_WORK"]}}</div>
                    <div>學歷：{{user_data["AP_HI_SCHOOL"]}}</div>
                    <div>考試：{{user_data["AP_TEST"]}}</div>
                    <div>手機：{{user_data["AP_SEL"]}}</div>
                    <div>到職：{{user_data["AP_ON_DATE"]}} <b-badge v-show="workAge(user_data) !== false" :variant="workAgeVariant(user_data)" pill>{{workAge(user_data)}}年</b-badge></div>
                </b-card-text>
            </b-card>
        </b-card-group>`,
        props: ['id', 'name', 'ip'],
        data: function() { return {
            disabled: CONFIG.DISABLE_MSDB_QUERY,
            user_rows: null,
            now: new Date(),
            year: 31536000000
        } },
        computed: {
            showCard: function() {
                return !this.disabled && this.user_rows !== null && this.user_rows !== undefined && this.user_rows.length > 0;
            }
        },
        methods: {
            isLeft: function(user_data) {
                return user_data['AP_OFF_JOB'] == 'Y';
            },
            photoUrl: function (user_data) {
                return `get_pho_img.php?name=${user_data['AP_USER_NAME']}`;
            },
            toADString: function(tw_date) {
                let ad_date = tw_date.replace('/-/g', "/");
                // detect if it is TW date
                if (ad_date.match(/^\d{3}\/\d{2}\/\d{2}$/)) {
                    // to AD date
                    ad_date = (parseInt(ad_date.substring(0, 3)) + 1911) + ad_date.substring(3);
                }
                return ad_date;
            },
            toTWString: function(ad_date) {
                tw_date = ad_date.replace('/-/g', "/");
                // detect if it is AD date
                if (tw_date.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
                    // to TW date
                    tw_date = (parseInt(tw_date.substring(0, 4)) - 1911) + tw_date.substring(4);
                }
                return tw_date;
            },
            birthAgeVariant: function(user_data) {
                let badge_age = this.birthAge(user_data["AP_BIRTH"]);
                if (badge_age < 30) {
                    return "success";
                } else if (badge_age < 40) {
                    return "primary";
                } else if (badge_age < 50) {
                    return "warning";
                } else if (badge_age < 60) {
                    return "danger";
                }
                return "dark";
            },
            birthAge: function(user_data) {
                let birth = user_data["AP_BIRTH"];
                if (birth) {
                    birth = this.toADString(birth);
                    let temp = Date.parse(birth);
                    if (temp) {
                        let born = new Date(temp);
                        return ((this.now - born) / this.year).toFixed(1);
                    }
                }
                return false;
            },
            workAge: function(user_data) {
                let AP_ON_DATE = user_data["AP_ON_DATE"];
                let AP_OFF_JOB = user_data["AP_OFF_JOB"];
                let AP_OFF_DATE = user_data["AP_OFF_DATE"];

                if(AP_ON_DATE != undefined && AP_ON_DATE != null) {
                    AP_ON_DATE = AP_ON_DATE.date ? AP_ON_DATE.date.split(" ")[0] :　AP_ON_DATE;
                    AP_ON_DATE = this.toADString(AP_ON_DATE);
                    let temp = Date.parse(AP_ON_DATE);
                    if (temp) {
                        let on = new Date(temp);
                        let now = this.now;
                        if (AP_OFF_JOB == "Y") {
                            AP_OFF_DATE = this.toADString(AP_OFF_DATE);
                            temp = Date.parse(off_boAP_OFF_DATEard_date);
                            if (temp) {
                                // replace now Date to off board date
                                now = new Date(temp);
                            }
                        }
                        return ((now - on) / this.year).toFixed(1);
                    }
                }
                return false;
            },
            workAgeVariant: function(user_data) {
                let work_age = this.workAge(user_data);
                if (work_age < 5) {
                    return 'success';
                } else if (work_age < 10) {
                    return 'primary';
                } else if (work_age < 20) {
                    return 'warning';
                }
                return 'danger';
            },
            cacheUserRows: function() {
                let json_str = JSON.stringify(this.user_rows);
                let payload = {};
                if (!isEmpty(this.id)) { payload[this.id] = json_str; }
                if (!isEmpty(this.name)) { payload[this.name] = json_str; }
                if (!isEmpty(this.ip)) { payload[this.ip] = json_str; }
                this.$gstore.commit('cache', payload);
            }
        },
        created() {
            if (!this.disabled) {
                // mocks for testing
                /*
                let that = this;
                axios.get('assets/js/mocks/user_info.json')
                .then(function(response) {
                    that.user_rows = response.data.raw;
                    that.cacheUserRows()
                }).catch(err => {
                    console.error(err)
                }).finally(function() {

                });
                return;
                */
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "user_info",
                    name: $.trim(this.name),
                    id: $.trim(this.id),
                    ip: $.trim(this.ip)
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.user_rows = res.data.raw;
                        this.cacheUserRows();
                    } else {
                        addNotification({ message: `找不到 '${name} 或 ${id} 或 ${ip}' 資料` });
                    }
                }).catch(err => {
                    console.error("userinfo-card::created parsing failed", err);
                    showAlert({
                        title: "使用者資訊",
                        subtitle: `${this.name}, ${this.id}, ${this.ip}`,
                        message: err.message,
                        type: "danger"
                    });
                });
            }
        },
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... user-info-card component can not be loaded.");
}
