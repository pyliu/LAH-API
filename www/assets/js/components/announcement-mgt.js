if (Vue) {
    Vue.component("announcement-mgt", {
        template: `<fieldset>
            <legend>公告維護</legend>
            <b-row class="mb-2">
                <b-col>
                    <announcement-mgt-item :reset-flag="reset_flag" @update-announcement-done="updated" @reset-flags-done="done"></announcement-mgt-item>
                </b-col>
            </b-row>
            <b-row no-gutters>
                <b-col>
                    <b-button block pill @click="clear" variant="outline-secondary" size="sm" v-b-popover.hover.focus.top="'清除准登旗標'"><i class="fas fa-broom"></i> 清除准登旗標</b-button>
                </b-col>
                &ensp;
                <b-col>
                    <b-button block pill @click="popup" variant="outline-success" size="sm" title="備註"><i class="far fa-comment"></i> 備註</b-button>
                </b-col>
            </b-row>
        </fieldset>`,
        data: () => {
            return {
                reset_flag: false
            }
        },
        methods: {
            updated: function(updated_data) {
                //console.log(updated_data);
            },
            done: () => {
                this.reset_flag = false;
            },
            clear: function(e) {
                let that = this;
                showConfirm("請確認清除所有登記原因的准登旗標？", () => {
                    toggle(e.target);
                    let form_body = new FormData();
                    form_body.append("type", "clear_announcement_flag");
                    fetch("query_json_api.php", {
                        method: 'POST',
                        body: form_body
                    }).then(response => {
                        if (response.status != 200) {
                            throw new Error("XHR連線異常，回應非200");
                        }
                        return response.json();
                    }).then(jsonObj => {
                        // let component knows it needs to clear the flag
                        this.reset_flag = true;
                        console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除先行准登回傳狀態碼有問題【" + jsonObj.status + "】");
                        addNotification({ title: "清除全部先行准登旗標", message: "已清除完成", type: "success" });
                        toggle(e.target);
                    }).catch(ex => {
                        console.error("announcement-mgt::clear parsing failed", ex);
                        showAlert({message: "announcement-mgt::clear XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
                    });
                });
            },
            popup: function(e) {
                showModal({
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
                        <span class="input-group-text" id="inputGroup-prereg_announcement_select">公告項目</span>
                    </div>
                    <select @change.lazy="change" id='prereg_announcement_select' class='form-control h-100' v-model="val">
                        <option value=''></option>
                        <option v-for="(item, index) in data" :value="item['RA01'] + ',' + item['KCNT'] + ',' + item['RA02'] + ',' + item['RA03']">
                            {{item["RA01"]}} : {{item["KCNT"]}} 【{{item['RA02']}}, {{item['RA03']}}】
                        </option>
                    </select>
                    &ensp;
                    <b-button @click="change" variant="outline-primary" size="sm" v-b-popover.hover.focus.top="'開啟編輯視窗'"><i class="fas fa-external-link-alt"></i> 編輯</b-button>
                </div>`,
                props: ["resetFlag"],
                data: () => {
                    return {
                        data: [],
                        val: ""
                    }
                },
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
                        if (isEmpty(this.val)) {
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
                        showModal({
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
                created: function(e) {
                    let form_body = new FormData();
                    form_body.append("type", "announcement_data");
                    fetch("query_json_api.php", {
                        method: 'POST',
                        body: form_body
                    }).then(response => {
                        if (response.status != 200) {
                            throw new Error("XHR連線異常，回應非200");
                        }
                        return response.json();
                    }).then(jsonObj => {
                        this.data = jsonObj.raw;
                    });
                },
                mounted: function(e) {
                    // get cached data and set selected option
                    this.val = localStorage.getItem("prereg_announcement_select");
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
                        data: function(e) {
                            return {
                                reason_code: this.data[0],
                                day: this.data[2],
                                flag: this.data[3]
                            }
                        },
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
                                    let form_body = new FormData();
                                    form_body.append("type", "update_announcement_data");
                                    form_body.append("code", reason_code);
                                    form_body.append("day", day);
                                    form_body.append("flag", flag);
                                    
                                    fetch("query_json_api.php", {
                                        method: 'POST',
                                        body: form_body
                                    }).then(response => {
                                        if (response.status != 200) {
                                            throw new Error("XHR連線異常，回應非200");
                                        }
                                        return response.json();
                                    }).then(jsonObj => {
                                        console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "更新公告期限回傳狀態碼有問題【" + jsonObj.status + "】");
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
                                    }).catch(ex => {
                                        console.error("announcement-mgt-dialog::update parsing failed", ex);
                                        showAlert({message: "announcement-mgt-dialog::update XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
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
