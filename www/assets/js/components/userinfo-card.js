if (Vue) {
    Vue.component("userinfo-card", {
        template: `<div v-if="showCard">
            <b-card-group columns>
                <b-card
                    v-for="user_data in user_rows"
                    class="overflow-hidden
                    bg-light"
                    style="max-width: 540px; font-size: 0.9rem;"
                    :title="user_data['AP_USER_NAME']"
                    :sub-title="user_data['AP_JOB']"
                >
                    <b-link :href="'get_pho_img.php?name='+user_data['AP_USER_NAME']" target="_blank">
                        <b-card-img
                            :src="'get_pho_img.php?name='user_data['AP_USER_NAME']"
                            :alt="user_data['AP_USER_NAME']"
                            class="img-thumbnail float-right ml-2"
                            style="max-width: 220px"
                        ></b-card-img>
                    </b-link>
                    <b-card-text>
                        <p v-if="user_data['AP_OFF_JOB'] == 'N'" class='text-danger'>已離職【{{user_data["AP_OFF_DATE"]}}】</p>
                        <div>ID：{{user_data["DocUserID"]}}</div>
                        <div>電腦：{{user_data["AP_PCIP"]}}</div>
                        <div>生日：{{user_data["AP_BIRTH"]}} <b-badge v-show="birthAge(user_data['AP_BIRTH']) !== false" :variant="birthAgeVariant(user_data['AP_BIRTH'])" pill>{{birthAge(user_data["AP_BIRTH"])}}歲</b-badge></div>
                        <div>單位：{{user_data["AP_UNIT_NAME"]}}</div>
                        <div>工作：{{user_data["AP_WORK"]}}</div>
                        <div>學歷：{{user_data["AP_HI_SCHOOL"]}}</div>
                        <div>考試：{{user_data["AP_TEST"]}}</div>
                        <div>手機：{{user_data["AP_SEL"]}}</div>
                        <div>到職：{{user_data["AP_ON_DATE"]}} <b-badge v-show="workAge(user_data) !== false" :variant="workAgeVariant(user_data)" pill>{{workAge(user_data)}}年</b-badge></div>
                    </b-card-text>
                </b-card>
            </b-card-group>
        </div>`,
        props: ['id', 'name', 'ip'],
        data: function() { return {
            disabled: CONFIG.DISABLE_MSDB_QUERY,
            user_rows: null
        } },
        watch: {
            user_rows: function(val) {
                console.log(val, this.showCard);

            }
        },
        computed: {
            showCard: function() {
                return !this.disabled && this.user_rows !== null && this.user_rows !== undefined && this.user_rows.length > 0;
            }
        },
        methods: {
            birthAgeVariant: function(AP_BIRTH) {
                let badge_age = this.birthAge(AP_BIRTH);
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
            birthAge: function(AP_BIRTH) {
                let birth = AP_BIRTH;
                let birth_regex = /^\d{3}\/\d{2}\/\d{2}$/;
                if (birth.match(birth_regex)) {
                    birth = (parseInt(birth.substring(0, 3)) + 1911) + birth.substring(3);
                    let temp = Date.parse(birth);
                    if (temp) {
                        let born = new Date(temp);
                        return ((now - born) / year).toFixed(1);
                    }
                }
                return false;
            },
            workAge: function(user_data) {
                let AP_ON_DATE = user_data["AP_ON_DATE"];
                let AP_OFF_JOB = user_data["AP_OFF_JOB"];
                let AP_OFF_DATE = user_data["AP_OFF_DATE"];

                if(AP_ON_DATE != undefined && AP_ON_DATE != null) {
                    let on_board_date = AP_ON_DATE.date ? AP_ON_DATE.date.split(" ")[0] :　AP_ON_DATE;
                    let temp = Date.parse(on_board_date.replace('/-/g', "/"));
                    if (temp && temp.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
                        let on = new Date(temp);
                        let now = new Date();
                        if (AP_OFF_JOB == "Y") {
                            let off_board_date = AP_OFF_DATE;
                            off_board_date = (parseInt(off_board_date.substring(0, 3)) + 1911) + off_board_date.substring(3);
                            temp = Date.parse(off_board_date.replace('/-/g', "/"));
                            if (temp) {
                                // replace now Date to off board date
                                now = new Date(temp);
                            }
                        }
                        return ((now - on) / year).toFixed(1);
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
                        subtitle: `${name}, ${id}, ${ip}`,
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
