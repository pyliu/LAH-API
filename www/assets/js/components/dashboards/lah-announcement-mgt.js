if (Vue) {
    Vue.component("lah-announcement-mgt", {
        template: `<b-card>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="bullhorn">公告維護</lah-fa-icon></h6>
                    <lah-button icon="question" no-border @click="popup" variant="outline-success" size="sm" title="備註"></lah-button>
                </div>
            </template>
            <div class="d-flex">
                <announcement-mgt-item :reset-flag="reset_flag" @update-announcement-done="updated" @reset-flags-done="done"></announcement-mgt-item>
                <b-button @click="clear" variant="outline-danger" size="sm" v-b-tooltip="'清除准登旗標'" class="ml-1"><i class="fas fa-flag"></i></b-button>
            </div>
        </b-card>`,
        data: () => ({
            reset_flag: false
        }),
        methods: {
            updated: function(updated_data) {
                //console.log(updated_data);
            },
            done: function() {
                this.reset_flag = false;
            },
            clear: function(e) {
                this.$confirm("清除所有登記原因的准登旗標？", () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "clear_announcement_flag"
                    }).then(res => {
                        // let component knows it needs to clear the flag
                        this.reset_flag = true;
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除先行准登回傳狀態碼有問題【" + res.data.status + "】");
                        this.notify({ title: "清除全部先行准登旗標", message: "已清除完成", type: "success" });
                        // clear cached data when flags are cleared
                        this.removeLocalCache('announcement_data');
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            popup: function(e) {
                this.msgbox({
                    title: "公告期限維護 小幫手提示",
                    body: `
                        <h5><span class="text-danger">※</span>注意：中壢所規定超過30件案件才能執行此功能，並於完成時須馬上關掉以免其他案件誤登。</h5>
                        <h5><span class="text-danger">※</span>注意：准登完後該案件須手動於資料庫中調整辦理情形（RM30）為「公告」（H）。</h5>
                        <img src="assets/howto/登記原因先行准登設定.jpg" class="img-responsive img-thumbnail" />
                    `,
                    size: "lg"
                });
            }
        },
        components: {
            "announcement-mgt-item": {
                template: `<div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-announcement_list">公告項目</span>
                    </div>
                    <b-form-select
                        v-model="val"
                        size="sm"
                        id="announcement_list"
                        :class="['h-100']"
                    >
                        <template v-slot:first>
                            <option value="" disabled>-- 請選擇一個項目 --</option>
                        </template>
                        <option v-for="(item, index) in announcement_data" :value="item['RA01'] + ',' + item['KCNT'] + ',' + item['RA02'] + ',' + item['RA03']">
                            {{item["RA01"]}} : {{item["KCNT"]}} 【{{item['RA02']}}, {{item['RA03']}}】
                        </option>
                    </b-form-select>
                    &ensp;
                    <b-button @click.stop="click" variant="outline-primary" size="sm" v-b-tooltip="'開啟編輯視窗'"><i class="fas fa-external-link-alt"></i></b-button>
                </div>`,
                props: ["resetFlag"],
                data: () => ({
                    announcement_data: [],
                    flag_on_announcements: [],
                    val: ""
                }),
                watch: {
                    resetFlag: function(nval, oval) {
                        if (nval) {
                            this.announcement_data.forEach(element => {
                                if (element["RA03"] != 'N') {
                                    element["RA03"] = 'N';
                                    // set selected value
                                    this.val = element['RA01'] + ',' + element['KCNT'] + ',' + element['RA02'] + ',' + element['RA03'];
                                }
                            });
                            this.$emit("reset-flags-done");
                        }
                    },
                    flag_on_announcements: function(nVal, oVal) {
                        if (!this.empty(nVal)) {
                            let html = '<h6><i class="fas fa-exclamation-triangle text-danger fa-lg"></i> 請注意下列已開啟准登之登記原因</h6>';
                            nVal.forEach(item => {
                                // RA01: "02" KCNT: "第一次登記" RA02: "15" RA03: "N"
                                html += `<p>代碼：${item['RA01']} 原因：${item['KCNT']}</p>`
                            });
                            this.notify({
                                title: '先行准登警示',
                                message: html,
                                type: 'danger',
                                delay: 10000
                            });
                        }
                    }
                },
                methods: {
                    click: function(e) {
                        if (this.empty(this.val)) return;
                        let vnode = this.$createElement("announcement-mgt-dialog", {
                            props: {
                                inData: this.val.split(",")
                            },
                            on: {
                                "announcement-update": this.update
                            }
                        });
                        this.msgbox({
                            title: "更新公告資料",
                            body: vnode,
                            size: "md"
                        });
                    },
                    update: function(data) {
                        this.announcement_data.forEach(element => {
                            if (element["RA01"] == data.reason_code) {
                                element["RA02"] = data.day;
                                element["RA03"] = data.flag;
                                // set selected value
                                this.val = element['RA01'] + ',' + element['KCNT'] + ',' + element['RA02'] + ',' + element['RA03'];
                            }
                        });
                        this.$emit("update-announcement-done", data);
                        this.reload();
                    },
                    reload: function() {
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: 'announcement_data'
                        }).then(res => {
                            this.announcement_data = res.data.raw;
                            // dayMilliseconds from mixin
                            this.setLocalCache('announcement_data', this.announcement_data, this.dayMilliseconds);
                            this.flags();
                        }).catch(err => {
                            this.error = err;
                        });
                    },
                    flags: function() {
                        this.flag_on_announcements = this.announcement_data.filter(item => {
                            /*
                                RA01: "02"
                                KCNT: "第一次登記"
                                RA02: "15"
                                RA03: "N"
                            */
                            return item['RA03'] == 'Y';
                        })
                    }
                },
                created() {
                    this.getLocalCache('announcement_data').then(json => {
                        if (json !== false) {
                            // within a day use the cached data
                            this.announcement_data = json || {};
                            if (this.empty(this.announcement_data)) this.removeLocalCache(announcement_data);
                            this.flags();
                        } else {
                            this.reload();
                        }
                    });
                },
                mounted: function(e) {
                    // get cached data and set selected option
                    this.$lf.getItem("announcement_list").then(val => this.val = val);
                },
                components: {
                    "announcement-mgt-dialog": {
                        template: `<div>
                            <div class="form-row">
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="inputGroup-annoumcement_code">登記代碼</span>
                                    </div>
                                    <input type="text" id="annoumcement_code" name="annoumcement_code" class="form-control" :value="o_reason_code" readonly />
                                </div>
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="inputGroup-annoumcement_reason">登記原因</span>
                                    </div>
                                    <input type="text" id="annoumcement_reason" name="annoumcement_reason" class="form-control" :value="o_reason_cnt" readonly />
                                </div>
                            </div>
                            <div class="form-row mt-1">
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" :id="'inputGroup-ann_day_'+o_reason_code">公告天數</span>
                                    </div>
                                    <select class='no-cache form-control' v-model="day"><option>15</option><option>30</option><option>45</option><option>60</option><option>75</option><option>90</option></select>
                                </div>
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" :id="'inputGroup-ann_reg_flag_'+o_reason_code">先行准登</span>
                                    </div>
                                    <select v-model="flag" class='no-cache form-control'><option>N</option><option>Y</option></select>
                                </div>
                                <div class="filter-btn-group col">
                                    <button :id="'ann_upd_btn_'+o_reason_code" class="btn btn-sm btn-outline-primary" @click="update" :disabled="!changed">更新</button>
                                </div>
                            </div>
                        </div>`,
                        props: ["inData"],
                        data: () => ({
                            reason_code: '',
                            reason_cnt: '',
                            day: '',
                            flag: '',
                            o_reason_code: '',
                            o_reason_cnt: '',
                            o_day: '',
                            o_flag: ''
                        }),
                        computed: {
                            changed() { return this.o_day != this.day || this.o_flag != this.flag }
                        },
                        methods: {
                            update: function(e) {
                                if (!this.changed) {
                                    this.notify({
                                        title: "更新公告資料",
                                        message: "無變更，不需更新！",
                                        type: "warning"
                                    });
                                    return;
                                }
                                this.$assert(this.reason_code.length == 2, "登記原因代碼應為2碼，如'30'");
                                this.$confirm("確定要更新公告資料？", () => {
                                    this.isBusy = true;
                                    this.$http.post(CONFIG.API.JSON.QUERY, {
                                        type: 'update_announcement_data',
                                        code: this.reason_code,
                                        day: this.day,
                                        flag: this.flag
                                    }).then(res => {
                                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "更新公告期限回傳狀態碼有問題【" + res.data.status + "】");
                                        this.notify({
                                            title: this.reason_cnt,
                                            message: `公告已更新【天數：${this.o_day} => ${this.day}, 准登：${this.o_flag} => ${this.flag}】`,
                                            type: "success"
                                        });
                                        this.o_day = this.day;
                                        this.o_flag = this.flag;
                                        // notify parent the data is changed
                                        this.$emit("announcement-update", {
                                            reason_code: this.reason_code,
                                            day: this.day,
                                            flag: this.flag
                                        });
                                    }).catch(err => {
                                        this.error = err;
                                    }).finally(() => {
                                        this.isBusy = false;
                                    });
                                    
                                });
                            }
                        },
                        created() {
                            this.reason_code = this.inData[0];
                            this.reason_cnt = this.inData[1];
                            this.day = this.inData[2];
                            this.flag = this.inData[3];
                            this.o_reason_code = this.inData[0];
                            this.o_reason_cnt = this.inData[1];
                            this.o_day = this.inData[2];
                            this.o_flag = this.inData[3];
                        }
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... lah-announcement-mgt component can not be loaded.");
}
