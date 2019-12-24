if (Vue) {
    Vue.component("announcement-mgt", {
        template: `<fieldset>
            <legend>公告期限維護<small>(先行准登)</small></legend>
            <div class="form-row">
                <announcement-mgt-item :data="announcement_data"></announcement-mgt-item>
                <div class="filter-btn-group col">
                    <button class="btn btn-sm btn-outline-primary" @click="clear">清除准登</button>
                    <button class="btn btn-sm btn-outline-success" @click="popup">備註</button>
                </div>
            </div>

            <div id='prereg_update_ui' class='mt-1'></div>
        </fieldset>`,
        methods: {
            reload: function(e) {
                // reload the Vue (hack ... not beautiful ... i know)
                if (window.announcementMgtVue) {
                    $("#announcement-mgt").html("<announcement-mgt></announcement-mgt>");
                    window.announcementMgtVue.$destroy();
                    window.announcementMgtVue = new Vue({
                        el: "#announcement-mgt"
                    });
                }
            },
            clear: function(e) {
                let that = this;
                showConfirm("請確認要是否要清除所有登記原因的准登旗標？", () => {
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
                        console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除先行准登回傳狀態碼有問題【" + jsonObj.status + "】");
                        addNotification({ message: "<strong class='text-success'>已全部清除完成</strong>", type: "success" });
                        that.reload();
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
        data: () => {
            return {
                announcement_data: []
            }
        },
        components: {
            "announcement-mgt-item": {
                template: `<div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-prereg_announcement_select">公告項目</span>
                    </div>
                    <select @change.lazy="change" id='prereg_announcement_select' class='form-control' v-model="val">
                        <option value=''></option>
                        <option v-for="(item, index) in data" :value="item['RA01'] + ',' + item['KCNT'] + ',' + item['RA02'] + ',' + item['RA03']">
                            {{item["RA01"]}} : {{item["KCNT"]}} 【{{item['RA02']}}, {{item['RA03']}}】
                        </option>
                    </select>
                    &ensp;
                    <button class="btn btn-sm btn-outline-primary" @click="change">變更</button>
                </div>`,
                data: () => {
                    return {
                        data: [],
                        val: ""
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
                            }
                        });
                        showModal({
                            title: "更新公告資料",
                            body: vnode,
                            size: "md"
                        });
                    }
                },
                mounted: function(e) {
                    let that = this;
                    
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
                        that.data = jsonObj.raw;
                    });

                    let mounted_el = $(this.$el);
                    setTimeout(() => {
                        that.val = mounted_el.find("#prereg_announcement_select").val();
                    }, 150);    // cache.js delay 100ms to wait Vue instance ready, so here delays 150ms
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
                                let day = this.day;
                                let flag = this.flag;
                                if (this.data[2] == day && this.data[3] == flag) {
                                    showAlert({
                                        message: "無變更，不需更新！",
                                        type: "warning"
                                    });
                                    return;
                                }
                                console.assert(reason_code.length == 2, "登記原因代碼應為2碼，如'30'");
                                let that = this;
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
                                            body: "<strong class='text-success'>更新完成</strong>"
                                        });
                                        // reload the Vue (hack ... not beautiful ... i know)
                                        if (window.announcementMgtVue) {
                                            $("#announcement-mgt").html("<announcement-mgt></announcement-mgt>");
                                            window.announcementMgtVue.$destroy();
                                            window.announcementMgtVue = new Vue({
                                                el: "#announcement-mgt"
                                            });
                                        }
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
