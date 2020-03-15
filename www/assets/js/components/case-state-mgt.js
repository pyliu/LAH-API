if (Vue) {
    Vue.component("case-state-mgt", {
        template: `<fieldset>
            <legend>案件狀態</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="query" type="reg" prefix="case_state"></case-input-group-ui>
                </b-col>
            </b-form-row>
            <b-form-row>
                <b-col>
                    <b-button block pill @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                </b-col>
                <b-col>
                    <b-button block pill  @click="popup" variant="outline-success" size="sm"><i class="fas fa-question"></i> 功能說明</b-button>
                </b-col>
            </b-form-row>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB04",
                num: "000010",
                dialog: null
            }
        },
        methods: {
            handleUpdate: function(e, data) {
                this.year = data.year;
                this.code = data.code;
                this.num = data.num;
            },
            query: function(e) {
                let data = {year: this.year, code: this.code, num: this.num};
                if (!window.vueApp.checkCaseUIData(data)) {
                    addNotification({
                        title: "案件查詢",
                        subtitle: `${data.year}-${data.code}-${data.num}`,
                        message: `輸入資料格式有誤，無法查詢。`,
                        type: "warning"});
                    return false;
                }

                // prepare post params
                let id = trim(`${this.year}${this.code}${this.num}`);
                
                this.isBusy = true;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        addNotification({
                            title: "案件查詢",
                            subtitle: id,
                            message: res.data.message,
                            type: "warning"
                        });
                    } else if (res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        throw new Error("查詢失敗：" + res.data.message);
                    } else {
                        // create sub-component dynamically
                        let v = this.$createElement("case-state-mgt-dialog", {
                            props: {
                                raw: res.data.raw,
                                tr: res.data.tr_html
                            }
                        })
                        showModal({
                            title: "調整登記案件欄位資料",
                            body: v,
                            size: "md"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            popup: () => {
                showModal({
                    title: "調整登記案件欄位資料 小幫手提示",
                    body: `<ul>
                        <li>使用情境1：先行准登後案件須回復至公告</li>
                        <li>使用情境2：案件卡住需退回初審</li>
                        <li>使用情境3：案件辦理情形與登記處理註記不同步造成地價課無法登錄收件卡住</li>
                    </ul>`,
                    size: "lg"
                });
            }
        },
        components: {
            "case-state-mgt-dialog": {
                template: `<div>
                    <div class="form-row mt-1">
                        <div class="input-group input-group-sm col">	
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-reg_case_RM30_select">案件辦理情形</span>
                            </div>
                            <select v-model="rm30" id='reg_case_RM30_select' class="form-control" aria-label="案件辦理情形" aria-describedby="inputGroup-reg_case_RM30_select" required>
                                <option v-for="(item, key) in rm30_mapping" :value="key">{{key}}: {{item}}</option>
                            </select>
                        </div>
                        <div v-if="wip" class="input-group input-group-sm col-3 small">
                            <input v-model="sync_rm30_1" type="checkbox" id="reg_case_RM30_1_checkbox" class="my-auto mr-2" aria-label="同步作業人員" aria-describedby="inputGroup-reg_case_RM30_1_checkbox" required />
                            <label for="reg_case_RM30_1_checkbox" class="my-auto">同步作業人員</label>
                        </div>
                        <div v-if="wip" class="filter-btn-group col-auto">
                            <button @click="updateRM30" class="btn btn-sm btn-outline-primary">更新</button>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div class="input-group input-group-sm col">	
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-reg_case_RM39_select">登記處理註記</span>
                            </div>
                            <select v-model="rm39" id='reg_case_RM39_select' class="form-control" aria-label="登記處理註記" aria-describedby="inputGroup-reg_case_RM39_select" required>
                                <option value=""></option>
                                <option v-for="(item, key) in rm39_mapping" :value="key">{{key}}: {{item}}</option>
                            </select>
                        </div>
                        <div v-if="wip" class="filter-btn-group col-auto">
                            <button @click="updateRM39" class="btn btn-sm btn-outline-primary">更新</button>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div class="input-group input-group-sm col">	
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="inputGroup-reg_case_RM42_select">地價處理註記</span>
                            </div>
                            <select v-model="rm42" id='reg_case_RM42_select' class="form-control" aria-label="地價處理註記" aria-describedby="inputGroup-reg_case_RM42_select" required>
                                <option value=""></option>
                                <option v-for="(item, key) in rm42_mapping" :value="key">{{key}}: {{item}}</option>
                            </select>
                        </div>
                        <div v-if="wip" class="filter-btn-group col-auto">
                            <button @click="updateRM42" class="btn btn-sm btn-outline-primary">更新</button>
                        </div>
                    </div>
                    <p class="mt-1" v-html="tr"></p>
                </div>`,
                props: ["raw", "tr"],    // jsonObj.raw, jsonObj.tr_html
                data: () => {
                    return {
                        rm30: "",
                        rm30_orig: "",
                        rm39: "",
                        rm39_orig: "",
                        rm42: "",
                        rm42_orig: "",
                        rm31: "",
                        sync_rm30_1: true,
                        wip: false,
                        rm30_mapping: {
                            A: "初審",
                            B: "複審",
                            H: "公告",
                            I: "補正",
                            R: "登錄",
                            C: "校對",
                            U: "異動完成",
                            F: "結案",
                            X: "補正初核",
                            Y: "駁回初核",
                            J: "撤回初核",
                            K: "撤回",
                            Z: "歸檔",
                            N: "駁回",
                            L: "公告初核",
                            E: "請示",
                            D: "展期"
                        },
                        rm39_mapping: {
                            B: "登錄開始",
                            R: "登錄完成",
                            C: "校對開始",
                            D: "校對完成",
                            S: "異動開始",
                            F: "異動完成",
                            G: "異動有誤",
                            P: "競合暫停"
                        },
                        rm42_mapping: {
                            '0': "登記移案",
                            B: "登錄中",
                            R: "登錄完成",
                            C: "校對中",
                            D: "校對完成",
                            E: "登錄有誤",
                            S: "異動開始",
                            F: "異動完成",
                            G: "異動有誤"
                        }
                    }
                },
                methods: {
                    updateRegCaseCol: function(arguments) {
                        if ($(arguments.el).length > 0) {
                            // remove the button
                            $(arguments.el).remove();
                        }
                        this.isBusy = true;
                        this.$http.post(CONFIG.JSON_API_EP, {
                            type: "reg_upd_col",
                            rm01: arguments.rm01,
                            rm02: arguments.rm02,
                            rm03: arguments.rm03,
                            col: arguments.col,
                            val: arguments.val
                        }).then(res => {
                            console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `更新案件「${arguments.col}」欄位回傳狀態碼有問題【${res.data.status}】`);
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                addNotification({title: "更新案件欄位", message: `「${arguments.col}」更新完成`, variant: "success"});
                            } else {
                                addNotification({title: "更新案件欄位", message: `「${arguments.col}」更新失敗【${res.data.status}】`, variant: "warning"});
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    },
                    updateRM30: function(e) {
                        if (this.rm30 == this.rm30_orig) {
                            addNotification({title: "更新案件辦理情形",  message: "案件辦理情形沒變動", type: "warning"});
                            return;
                        }
                        let that = this;
                        window.vueApp.confirm(`您確定要更新辦理情形為「${that.rm30}」?`, {
                            title: '請確認更新案件辦理情形',
                            callback: () => {
                                that.updateRegCaseCol({
                                    rm01: this.raw["RM01"],
                                    rm02: this.raw["RM02"],
                                    rm03: this.raw["RM03"],
                                    col: "RM30",
                                    val: this.rm30
                                });
                                if (this.sync_rm30_1) {
                                    /**
                                     * RM45 - 初審 A
                                     * RM47 - 複審 B
                                     * RM55 - 登錄 R
                                     * RM57 - 校對 C
                                     * RM59 - 結案 F
                                     * RM82 - 請示 E
                                     * RM88 - 展期 D
                                     * RM93 - 撤回 K
                                     * RM91_4 - 歸檔 Z
                                     */
                                    let rm30_1 = "";
                                    switch (this.rm30) {
                                        case "A":
                                            rm30_1 = this.raw["RM45"];
                                            break;
                                        case "B":
                                            rm30_1 = this.raw["RM47"];
                                            break;
                                        case "R":
                                            rm30_1 = this.raw["RM55"];
                                            break;
                                        case "C":
                                            rm30_1 = this.raw["RM57"];
                                            break;
                                        case "F":
                                            rm30_1 = this.raw["RM59"];
                                            break;
                                        case "E":
                                            rm30_1 = this.raw["RM82"];
                                            break;
                                        case "D":
                                            rm30_1 = this.raw["RM88"];
                                            break;
                                        case "K":
                                            rm30_1 = this.raw["RM93"];
                                            break;
                                        case "Z":
                                            rm30_1 = this.raw["RM91_4"];
                                            break;
                                        default:
                                            rm30_1 = "XXXXXXXX";
                                            break;
                                    }
                                    that.updateRegCaseCol({
                                        rm01: this.raw["RM01"],
                                        rm02: this.raw["RM02"],
                                        rm03: this.raw["RM03"],
                                        col: "RM30_1",
                                        val: that.empty(rm30_1) ? "XXXXXXXX" : rm30_1
                                    });
                                }
                            }
                        });
                    },
                    updateRM39: function(e) {
                        if (this.rm39 == this.rm39_orig) {
                            addNotification({title: "更新登記處理註記", message: "登記處理註記沒變動", type: "warning"});
                            return;
                        }
                        let that = this;
                        window.vueApp.confirm(`您確定要更新登記處理註記為「${that.rm39}」?`, {
                            title: '請確認更新登記處理註記',
                            callback: () => {
                                that.updateRegCaseCol({
                                    rm01: that.raw["RM01"],
                                    rm02: that.raw["RM02"],
                                    rm03: that.raw["RM03"],
                                    col: "RM39",
                                    val: that.rm39
                                });
                            }
                        });
                    },
                    updateRM42: function(e) {
                        if (this.rm42 == this.rm42_orig) {
                            addNotification({title: "更新地價處理註記", message: "地價處理註記沒變動", type: "warning"});
                            return;
                        }
                        let that = this;
                        window.vueApp.confirm(`您確定要更新地價處理註記為「${that.rm42}」?`, {
                            title: '請確認更新地價處理註記',
                            callback: () => {
                                that.updateRegCaseCol({
                                    rm01: that.raw["RM01"],
                                    rm02: that.raw["RM02"],
                                    rm03: that.raw["RM03"],
                                    col: "RM42",
                                    val: that.rm42
                                });
                            }
                        });
                    }
                },
                mounted: function(e) {
                    this.rm30 = this.raw["RM30"] || "";
                    this.rm39 = this.raw["RM39"] || "";
                    this.rm42 = this.raw["RM42"] || "";
                    this.rm30_orig = this.raw["RM30"] || "";
                    this.rm39_orig = this.raw["RM39"] || "";
                    this.rm42_orig = this.raw["RM42"] || "";
                    this.rm31 = this.raw["RM31"];
                    this.wip = this.empty(this.rm31);
                    addUserInfoEvent(e);
                    addAnimatedCSS(".reg_case_id", {
                        name: "flash"
                    }).off("click").on("click", function(e) {
                        window.vueApp.fetchRegCase(e, true);
                    });
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-state-mgt component can not be loaded.");
}
