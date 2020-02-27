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
                    <b-button block pill  @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
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

                let year = this.year;
                let code = this.code;
                let number = this.num;
                
                // prepare post params
                let id = trim(year + code + number);
                
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
                    this.isBusy = false;
                }).catch(err => {
                    console.error("case-state-mgt::query parsing failed", err);
                    showAlert({
                        title: "查詢案件",
                        subtitle: id,
                        message: err.message,
                        type: "danger"
                    });
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
                                <option value="A">A: 初審</option>
                                <option value="B">B: 複審</option>
                                <option value="H">H: 公告</option>
                                <option value="I">I: 補正</option>
                                <option value="R">R: 登錄</option>
                                <option value="C">C: 校對</option>
                                <option value="U">U: 異動完成</option>
                                <option value="F">F: 結案</option>
                                <option value="X">X: 補正初核</option>
                                <option value="Y">Y: 駁回初核</option>
                                <option value="J">J: 撤回初核</option>
                                <option value="K">K: 撤回</option>
                                <option value="Z">Z: 歸檔</option>
                                <option value="N">N: 駁回</option>
                                <option value="L">L: 公告初核</option>
                                <option value="E">E: 請示</option>
                                <option value="D">D: 展期</option>
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
                                <option value="B">B: 登錄開始</option>
                                <option value="R">R: 登錄完成</option>
                                <option value="C">C: 校對開始</option>
                                <option value="D">D: 校對完成</option>
                                <option value="S">S: 異動開始</option>
                                <option value="F">F: 異動完成</option>
                                <option value="G">G: 異動有誤</option>
                                <option value="P">P: 競合暫停</option>
                            </select>
                        </div>
                        <div v-if="wip" class="filter-btn-group col-auto">
                            <button @click="updateRM39" class="btn btn-sm btn-outline-primary">更新</button>
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
                        rm31: "",
                        sync_rm30_1: true,
                        wip: false
                    }
                },
                methods: {
                    updateRM30: function(e) {
                        if (this.rm30 == this.rm30_orig) {
                            addNotification({title: "更新案件辦理情形",  message: "案件辦理情形沒變動", type: "warning"});
                            return;
                        }
                        let that = this;
                        window.vueApp.confirm(`您確定要更新辦理情形為「${that.rm30}」?`, {
                            title: '請確認更新案件辦理情形',
                            callback: () => {
                                xhrUpdateRegCaseCol({
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
                                    xhrUpdateRegCaseCol({
                                        rm01: this.raw["RM01"],
                                        rm02: this.raw["RM02"],
                                        rm03: this.raw["RM03"],
                                        col: "RM30_1",
                                        val: isEmpty(rm30_1) ? "XXXXXXXX" : rm30_1
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
                                xhrUpdateRegCaseCol({
                                    rm01: that.raw["RM01"],
                                    rm02: that.raw["RM02"],
                                    rm03: that.raw["RM03"],
                                    col: "RM39",
                                    val: that.rm39
                                });
                            }
                        });
                    }
                },
                mounted: function(e) {
                    this.rm30 = this.raw["RM30"] || "";
                    this.rm39 = this.raw["RM39"] || "";
                    this.rm30_orig = this.raw["RM30"] || "";
                    this.rm39_orig = this.raw["RM39"] || "";
                    this.rm31 = this.raw["RM31"];
                    this.wip = isEmpty(this.rm31);
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
