if (Vue) {
    Vue.component("announcement-mgt", {
        template: `<fieldset>
            <legend>
                <i class="fas fa-bullhorn"></i>
                公告維護
                <b-button class="border-0" @click="popup" variant="outline-success" size="sm" title="備註"><i class="fas fa-question"></i></b-button>
            </legend>
            <div class="d-flex">
                <announcement-mgt-item :reset-flag="reset_flag" @update-announcement-done="updated" @reset-flags-done="done"></announcement-mgt-item>
                <b-button @click="clear" variant="outline-danger" size="sm" v-b-tooltip="'清除准登旗標'" class="ml-1"><i class="fas fa-flag"></i></b-button>
            </div>
        </fieldset>`,
        data: () => ({
            reset_flag: false
        }),
        methods: {
            updated: function(updated_data) {
                //console.log(updated_data);
            },
            done: () => {
                this.reset_flag = false;
            },
            clear: function(e) {
                showConfirm("請確認清除所有登記原因的准登旗標？", () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "clear_announcement_flag"
                    }).then(res => {
                        // let component knows it needs to clear the flag
                        this.reset_flag = true;
                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除先行准登回傳狀態碼有問題【" + res.data.status + "】");
                        addNotification({ title: "清除全部先行准登旗標", message: "已清除完成", type: "success" });
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
                        <option v-for="(item, index) in data" :value="item['RA01'] + ',' + item['KCNT'] + ',' + item['RA02'] + ',' + item['RA03']">
                            {{item["RA01"]}} : {{item["KCNT"]}} 【{{item['RA02']}}, {{item['RA03']}}】
                        </option>
                    </b-form-select>
                    &ensp;
                    <b-button @click="change" variant="outline-primary" size="sm" v-b-tooltip="'開啟編輯視窗'"><i class="fas fa-external-link-alt"></i></b-button>
                </div>`,
                props: ["resetFlag"],
                data: () => ({
                    data: [],
                    val: ""
                }),
                watch: {
                    resetFlag: function(nval, oval) {
                        if (nval) {
                            this.data.forEach(element => {
                                if (element["RA03"] != 'N') {
                                    element["RA03"] = 'N';
                                    // set selected value
                                    this.val = element['RA01'] + ',' + element['KCNT'] + ',' + element['RA02'] + ',' + element['RA03'];
                                }
                            });
                            this.$emit("reset-flags-done");
                        }
                    }
                },
                methods: {
                    change: function(e) {
                        if (this.empty(this.val)) {
                            return;
                        }
                        let vnode = this.$createElement("announcement-mgt-dialog", {
                            props: {
                                data: this.val.split(",")
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
                        this.data.forEach(element => {
                            if (element["RA01"] == data.reason_code) {
                                element["RA02"] = data.day;
                                element["RA03"] = data.flag;
                                // set selected value
                                this.val = element['RA01'] + ',' + element['KCNT'] + ',' + element['RA02'] + ',' + element['RA03'];
                            }
                        });
                        this.$emit("update-announcement-done", data);
                    }
                },
                async created() {
                    try {
                        const json = await this.getLocalCache('announcement_data');
                        if (json !== false) {
                            // within a day use the cached data
                            this.data = json || {};
                            if (this.empty(this.data)) this.removeLocalCache(announcement_data);
                        } else {
                            this.$http.post(CONFIG.JSON_API_EP, {
                                type: 'announcement_data'
                            }).then(async res => {
                                this.data = res.data.raw;
                                // dayMilliseconds from $store
                                this.setLocalCache('announcement_data', this.data, this.dayMilliseconds);
                            }).catch(err => {
                                this.error = err;
                            });
                        }
                    } catch (err) {
                        console.error(err);
                    }
                },
                mounted: async function(e) {
                    // get cached data and set selected option
                    this.val = await this.$lf.getItem("announcement_list");
                },
                components: {
                    "announcement-mgt-dialog": {
                        template: `<div>
                            <div class="form-row">
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="inputGroup-annoumcement_code">登記代碼</span>
                                    </div>
                                    <input type="text" id="annoumcement_code" name="annoumcement_code" class="form-control" :value="data[0]" readonly />
                                </div>
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="inputGroup-annoumcement_reason">登記原因</span>
                                    </div>
                                    <input type="text" id="annoumcement_reason" name="annoumcement_reason" class="form-control" :value="data[1]" readonly />
                                </div>
                            </div>
                            <div class="form-row mt-1">
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" :id="'inputGroup-ann_day_'+data[0]">公告天數</span>
                                    </div>
                                    <select class='no-cache form-control' v-model="day"><option>15</option><option>30</option><option>45</option><option>60</option><option>75</option><option>90</option></select>
                                </div>
                                <div class="input-group input-group-sm col">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" :id="'inputGroup-ann_reg_flag_'+data[0]">先行准登</span>
                                    </div>
                                    <select v-model="flag" class='no-cache form-control'><option>N</option><option>Y</option></select>
                                </div>
                                <div class="filter-btn-group col">
                                    <button :id="'ann_upd_btn_'+data[0]" class="btn btn-sm btn-outline-primary" @click="update">更新</button>
                                </div>
                            </div>
                        </div>`,
                        props: ["data"],
                        data: () => ({
                            reason_code: this.data[0],
                            day: this.data[2],
                            flag: this.data[3]
                        }),
                        methods: {
                            update: function(e) {
                                let reason_code = this.data[0];
                                let reason_cnt = this.data[1];
                                let day = this.day;
                                let flag = this.flag;
                                if (this.data[2] == day && this.data[3] == flag) {
                                    addNotification({
                                        title: "更新公告資料",
                                        message: "無變更，不需更新！",
                                        type: "warning"
                                    });
                                    return;
                                }
                                console.assert(reason_code.length == 2, "登記原因代碼應為2碼，如'30'");
                                showConfirm("確定要更新公告資料？", () => {
                                    this.isBusy = true;
                                    this.$http.post(CONFIG.JSON_API_EP, {
                                        type: 'update_announcement_data',
                                        code: reason_code,
                                        day: day,
                                        flag: flag
                                    }).then(res => {
                                        console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "更新公告期限回傳狀態碼有問題【" + res.data.status + "】");
                                        addNotification({
                                            title: reason_cnt,
                                            message: `公告已更新【天數：${this.data[2]} => ${day}, 准登：${this.data[3]} => ${flag}】`,
                                            type: "success"
                                        });
                                        this.data[2] = day;
                                        this.data[3] = flag;
                                        // notify parent the data is changed
                                        this.$emit("announcement-update", {
                                            reason_code: reason_code,
                                            day: day,
                                            flag: flag
                                        });
                                        closeModal();
                                    }).catch(err => {
                                        this.error = err;
                                    }).finally(() => {
                                        this.isBusy = false;
                                    });
                                    
                                });
                            }
                        }
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... announcement-mgt component can not be loaded.");
}
