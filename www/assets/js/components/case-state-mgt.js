if (Vue) {
    // this puts inside xcase-check will not seeable by dynamic Vue generation
    Vue.component("case-state-mgt-dialog", {
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
                <div v-if="wip" class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-reg_case_RM30_1_checkbox">同步作業人員</span>
                    </div>
                    <input v-model="sync_rm30_1" type="checkbox" id="reg_case_RM30_1_checkbox" class="form-control" aria-label="同步作業人員" aria-describedby="inputGroup-reg_case_RM30_1_checkbox" required />
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
                rm39: "",
                rm31: "",
                sync_rm30_1: true,
                wip: false
            }
        },
        methods: {
            updateRM30: function(e) {
                closeModal();
                let that = this;
                this.$bvModal.msgBoxConfirm(`您確定要更新辦理情形為「${that.rm30}」?`, {
					title: '請確認更新案件辦理情形',
					size: 'sm',
					buttonSize: 'sm',
					okVariant: 'outline-success',
                    okTitle: '確定',
                    cancelVariant: 'secondary',
					cancelTitle: '取消',
					footerClass: 'p-2',
					hideHeaderClose: false,
                    centered: false,
                    noStacking: true
				}).then(value => {
					if (value) {
                        that.callXhrRM30Request();
                    }
				}).catch(err => {
					console.error(err);
				});
            },
            updateRM39: function(e) {
                closeModal();
                let that = this;
                this.$bvModal.msgBoxConfirm(`您確定要更新登記處理註記為「${that.rm39}」?`, {
					title: '請確認更新登記處理註記',
					size: 'sm',
					buttonSize: 'sm',
					okVariant: 'outline-success',
                    okTitle: '確定',
                    cancelVariant: 'secondary',
					cancelTitle: '取消',
					footerClass: 'p-2',
					hideHeaderClose: false,
                    centered: false,
                    noStacking: true
				}).then(value => {
					if (value) {
                        xhrUpdateRegCaseCol({
                            rm01: that.raw["RM01"],
                            rm02: that.raw["RM02"],
                            rm03: that.raw["RM03"],
                            col: "RM39",
                            val: that.rm39
                        });
                    }
				}).catch(err => {
					console.error(err);
				});
            },
            callXhrRM30Request: function() {
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
                    }
                    xhrUpdateRegCaseCol({
                        rm01: this.raw["RM01"],
                        rm02: this.raw["RM02"],
                        rm03: this.raw["RM03"],
                        col: "RM30_1",
                        val: rm30_1
                    });
                }
            }
        },
        mounted: function(e) {
            this.rm30 = this.raw["RM30"] || "";
            this.rm39 = this.raw["RM39"] || "";
            this.rm31 = this.raw["RM31"];
            this.wip = isEmpty(this.rm31);
            addUserInfoEvent(e);
            $(".reg_case_id").off("click").on("click", xhrRegQueryCaseDialog);
        }
    });
    Vue.component("case-state-mgt", {
        template: `<fieldset>
            <legend>調整登記案件欄位資料</legend>
            <div class="form-row">
            <div class="col-9">
                <case-input-group-ui @update="handleUpdate" type="reg"></case-input-group-ui>
            </div>
            <div class="filter-btn-group col-3">
                <button @click="query" class="btn btn-sm btn-outline-primary">查詢</button>
                <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
            </div>
            </div>
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
                if (!checkCaseUIData(data)) {
                    addNotification({body: `輸入案件資料格式有誤，無法查詢案件。`});
                    return false;
                }
                let year = this.year;
                let code = this.code;
                let number = this.num;
                // prepend "0"
                var offset = 6 - number.length;
                for (var i = 0; i < offset; i++) {
                    number = "0" + number;
                }
                // prepare post params
                let id = trim(year + code + number);
                let body = new FormData();
                body.append("type", "reg_case");
                body.append("id", id);
                
                toggle(e.target);

                let that = this;

                fetch("query_json_api.php", {
                    method: "POST",
                    //headers: { "Content-Type": "application/json" },
                    body: body
                }).then(response => {
                    return response.json();
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                        showAlert({
                            message: jsonObj.message,
                            type: "danger"
                        });
                    } else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        throw new Error("查詢失敗：" + jsonObj.message);
                    } else {
                        showModal({
                            title: "調整登記案件欄位資料",
                            body: `<div id="case-state-mgt-dialog-app"><case-state-mgt-dialog :raw="raw" :tr="tr"></case-state-mgt-dialog></div>`,
                            size: "md",
                            callback: () => {
                                that.dialog = new Vue({
                                    el: "#case-state-mgt-dialog-app",
                                    data: {
                                        raw: jsonObj.raw,
                                        tr: jsonObj.tr_html
                                    }
                                });
                            }
                        });
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("case-state-mgt::query parsing failed", ex);
                    showAlert({message: "無法取得 " + id + " 資訊!【" + ex.toString() + "】", type: "danger"});
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
        }
    });
} else {
    console.error("vue.js not ready ... case-state-mgt component can not be loaded.");
}
