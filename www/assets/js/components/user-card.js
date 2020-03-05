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
                    <div>生日：{{user_data["AP_BIRTH"]}} <b-badge v-show="birthAge(user_data['AP_BIRTH']) !== false" :variant="birthAgeVariant(user_data['AP_BIRTH'])" pill>{{birthAge(user_data["AP_BIRTH"])}}歲</b-badge></div>
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
            isLeft: function(user_data) {
                return user_data['AP_OFF_JOB'] == 'Y';
            },
            photoUrl: function (user_data) {
                return `get_pho_img.php?name=${user_data['AP_USER_NAME']}`;
            },
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
                // mocks for testing
                let that = this;
                axios.get('assets/js/mocks/user_info.json')
                .then(function(response) {
                    that.user_rows = response.data.raw;
                }).catch(err => {
                    console.error(err)
                }).finally(function() {

                });
                //this.user_rows = JSON.parse('{"status":1,"data_count":1,"raw":[{"AP_USER_ID":"BA0045","AP_UNIT_NAME":"\u767b\u8a18\u8ab2","AP_JOB":"\u8ab2\u54e1","AP_USER_NAME":"\u6e38\u4f69\u7a4e","AP_BIRTH":"084\/07\/09","AP_TEL":"03-3414400","AP_SEL":"0976830559","AP_ADR":null,"AP_WORK":"\u767b\u8a18\u696d\u52d9","AP_HI_SCHOOL":"\u570b\u7acb\u653f\u6cbb\u5927\u5b78\u5730\u653f\u5b78\u7cfb","AP_TEST":"108\u5e74\u516c\u52d9\u4eba\u54e1\u9ad8\u7b49\u8003\u8a66\u4e09\u7d1a\u5730\u653f\u985e\u79d1","AP_DATE_1":"108\/10\/28","AP_DATE_2":"108\/10\/28","AP_OFF_JOB":"N","AP_OFF_DATE":"","AP_JOB1":"","AP_SAL":0,"AP_LOGIN_NAME":"1216","AP_UNIT_ID":"A01","AP_JOB_ID":null,"AP_LIMIT":null,"AP_INT":555,"AP_SEX":"\u5973","AP_ON_Days":106,"AP_ON_DATE":"2019\/10\/28","AP_PCIP":"220.1.35.186","AP_Working":"2\u670821\u65e5","AP_PCTYPE":"win7","AP_UNIT_ID2":null,"AP_Network":"220.1.35.xx","DocUserID":"HB1216","photo":null,"unitname2":null,"createunit":null,"creator":null,"createdate":null,"modunit":null,"modifier":null,"modifydate":null}],"query_string":"id=HB1216&name=\u6e38\u4f69\u7a4e"}').raw;
                return;

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
