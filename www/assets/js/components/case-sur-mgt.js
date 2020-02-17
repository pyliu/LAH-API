if (Vue) {
    Vue.component("case-sur-mgt", {
        template: `<fieldset>
            <legend>複丈案件查詢(修正已結延期、修改連件數)</legend>
            <b-form-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="query" type="sur" prefix="case_sur"></case-input-group-ui>
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
                code: "HB12",
                num: "000100"
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
                // prepend "0"
                var offset = 6 - number.length;
                for (var i = 0; i < offset; i++) {
                    number = "0" + number;
                }
                // prepare post params
                let id = trim(year + code + number);
                let body = new FormData();
                body.append("type", "sur_case");
                body.append("id", id);
                
                toggle(e.target);
            
                asyncFetch(CONFIG.JSON_API_EP, {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL && jsonObj.data_count == 0) {
                        addNotification({
                            title: "測量案件查詢",
                            subtitle: `${year}-${code}-${number}`,
                            message: "查無資料",
                            type: "warning"
                        });
                    } else {
                        this.dialog(jsonObj);
                    }
                    toggle(e.target);
                }).catch(ex => {
                    console.error("case-sur-mgt::query parsing failed", ex);
                    showAlert({
                        title: "查詢測量案件",
                        message: "<strong class='text-danger'>無法取得 " + id + " 資訊!【" + ex + "】</strong>",
                        type: "danger"
                    });
                });
            },
            dialog: function(jsonObj) {
                if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    showModal({
                        title: "測量案件查詢",
                        message: this.$createElement("case-sur-dialog", { props: { json: jsonObj } }),
                        callback: () => addUserInfoEvent()
                    });
                } else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                    throw new Error("查詢失敗：" + jsonObj.message);
                }
            },
            popup: () => {
                showModal({
                    title: "測量案件資料 小幫手提示",
                    body: `<h5><span class="text-danger">※</span>注意：本功能會清除如下圖之欄位資料並將案件辦理情形改為【核定】，請確認後再執行。</h5>
                    <img src="assets/howto/107-HB18-3490_測丈已結案案件辦理情形出現(逾期)延期複丈問題調整【參考】.jpg" />
                    <h5><span class="text-danger">※</span> 問題原因說明</h5>
                    <div>原因是 CMB0301 延期複丈功能，針對於有連件案件在做處理時，會自動根據MM24案件數，將後面的案件自動做延期複丈的更新。導致後續已結案的案件會被改成延期複丈的狀態 MM22='C' 就是 100、200、300、400為四連件，所以100的案件 MM24='4'，200、300、400 的 MM24='0' 延期複丈的問題再將100號做延期複丈的時候，會將200、300、400也做延期複丈的更新，所以如果400已經結案，100做延期複丈，那400號就會變成 MM22='C' MM23='A' MM24='4' 的異常狀態。</div>`,
                    size: "lg"
                });
            }
        },
        components: {
            "case-sur-dialog": {
                template: `<div>
                    收件字號：<a title="案件辦理情形 on ${CONFIG.AP_SVR}" href="javascript:void(0)" @click="open($event, 'http://${CONFIG.AP_SVR}:9080/LandHB/Dispatcher?REQ=CMC0202&GRP=CAS&MM01=' + json.raw['MM01'] + '&MM02=' + json.raw['MM02'] + '&MM03=' + json.raw['MM03'] + '&RM90=')">{{json.收件字號}}</a> </br>
                    收件時間：{{json.收件時間}} <br/>
                    收件人員：<span v-html="json.收件人員"></span> <br/>
                    <b-form-row class="w-100">
                        <b-col cols="4">
                            <b-input-group size="sm">
                                <b-input-group-prepend is-text>連件數</b-input-group-prepend>
                                <b-form-input
                                    v-model="count"
                                    id='mm24_upd_text'
                                    type="number"
                                    min="0"
                                    inline
                                ></b-form-input>
                            </b-input-group>
                        </b-col>
                        <b-col>
                            <b-button
                                id='mm24_upd_btn'
                                size="sm"
                                variant="outline-primary"
                                @click="update"
                                :disabled="orig_count == count"
                            >更新</b-button>
                        </b-col>
                    </b-form-row>
                    申請事由：{{json.raw["MM06"]}}：{{json.申請事由}} <br/>
                    　段小段：{{json.raw["MM08"]}} <br/>
                    　　地號：{{isEmpty(json.raw["MM09"]) ? "" : this.json.地號}} <br/>
                    　　建號：{{isEmpty(json.raw["MM10"]) ? "" : this.json.建號}} <br/>
                    <span class='text-info'>辦理情形</span>：{{json.辦理情形}} <br/>
                    結案狀態：{{json.結案狀態}} <br/>
                    <span class='text-info'>延期原因</span>：{{json.延期原因}} <br/>
                    <span class='text-info'>延期時間</span>：{{json.延期時間}} <br/>
                    <div v-if="json.結案已否 && json.raw['MM22'] == 'C'">
                        <h6 class="mt-2 mb-2"><span class="text-danger">※</span> 發現 {{json.收件字號}} 已「結案」但辦理情形為「延期複丈」!</h6>
                        <b-button
                            variant="outline-danger"
                            id="sur_delay_case_fix_button"
                            data-trigger="manual"
                            data-toggle="popover"
                            data-content="需勾選右邊其中一個選項才能進行修正"
                            title="錯誤訊息"
                            data-placement="top"
                            size="sm"
                            @click="fix"
                        >修正</b-button>
                        <b-form-checkbox
                            id='sur_delay_case_fix_set_D'
                            v-model="setD"
                            size="sm"
                            inline
                        >辦理情形改為核定</b-form-checkbox>
                        <b-form-checkbox
                            id='sur_delay_case_fix_clear_delay_datetime'
                            type='checkbox'
                            v-model="clearDatetime"
                            size="sm"
                            inline
                        >清除延期時間</b-form-checkbox>
                        <b-popover
                            :disabled.sync="disabled_popover"
                            target="sur_delay_case_fix_button"
                            title="錯誤提示"
                            ref="popover"
                            placement="top"
                        >
                            需勾選右邊其中一個選項才能進行修正
                        </b-popover>
                    </div>
                    <div v-if="debug">{{setD}}, {{clearDatetime}}, {{count}}</div>
                </div>`,
                props: ["json"],
                data: () => {
                    return {
                        id: "",
                        setD: true,
                        clearDatetime: true,
                        count: 0,
                        orig_count: 0,
                        disabled_popover: true,
                        debug: false
                    }
                },
                methods: {
                    update: function(e) {
                        /**
                         * add various data attrs in the button tag
                         */
                        let title = this.json.raw['MM01'] + '-' + this.json.raw['MM02'] + '-' + this.json.raw['MM03'] + '連件數';
                        if (this.orig_count != this.count) {
                            let that = this;
                            showConfirm("確定要修改 " + title + " 為「" + this.count + "」？",function () {
                                let body = new FormData();
                                body.append("type", "upd_case_column");
                                body.append("id", that.id);
                                body.append("table", "SCMSMS");
                                body.append("column", "MM24");
                                body.append("value", that.count);

                                toggle(e.target);

                                asyncFetch(CONFIG.JSON_API_EP, {
                                    method: "POST",
                                    body: body
                                }).then(jsonObj => {
                                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                        addNotification({
                                            title: "更新連件數",
                                            subtitle: that.id,
                                            message: title + "更新為「" + that.count + "」更新成功",
                                            type: "success"
                                        });
                                        that.orig_count = that.count;
                                    } else {
                                        addNotification({
                                            title: "更新連件數",
                                            subtitle: that.id,
                                            message: jsonObj.message,
                                            type: "danger"
                                        });
                                    }
                                    toggle(e.target);
                                }).catch(ex => {
                                    console.error("case-sur-dialog::update parsing failed", ex);
                                    showAlert({
                                        message: ex.toString(),
                                        subtitle: that.id,
                                        title: "更新欄位失敗",
                                        type: "danger"
                                    });
                                });
                            });
                        } else {
                            addNotification("連件數未變更，不需更新。");
                        }
                    },
                    fix: function(e) {
                        if (!this.setD && !this.clearDatetime) {
                            this.disabled_popover = false;
                            return;
                        }
                        this.disabled_popover = true;
                        let id = this.id;
                        let upd_mm22 = this.setD;
                        let clr_delay = this.clearDatetime;
                        let that = this;
                        showConfirm("確定要修正本案件?", function() {
                            toggle(e.target);
                            //fix_sur_delay_case
                            let body = new FormData();
                            body.append("type", "fix_sur_delay_case");
                            body.append("id", id);
                            body.append("UPD_MM22", upd_mm22);
                            body.append("CLR_DELAY", clr_delay);
                            asyncFetch(CONFIG.JSON_API_EP, {
                                method: "POST",
                                body: body
                            }).then(jsonObj => {
                                if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    addNotification({
                                        title: "修正複丈案件",
                                        subtitle: id,
                                        type: "success",
                                        message: "修正成功!"
                                    });
                                    // update the data will affect UI
                                    that.json.raw['MM22'] = 'D';
                                } else {
                                    let msg = "回傳狀態碼不正確!【" + jsonObj.message + "】";
                                    showAlert({
                                        title: "修正複丈案件失敗",
                                        subtitle: id,
                                        message: msg,
                                        type: "danger"
                                    });
                                }
                            }).catch(ex => {
                                console.error("case-sur-dialog::fix parsing failed", ex);
                                showAlert({title: "修正複丈案件失敗", subtitle: id, message: "修正失敗!【" + ex.toString() + "】", type: "danger"});
                            });
                        });
                    },
                    open: function(e, url) {
                        let h = window.innerHeight - 160;
                        showModal({
                            title: e.target.title || `外部連結 - ${CONFIG.AP_SVR}`,
                            message: `<iframe src="${url}" class="w-100" height="${h}" frameborder="0"></iframe>`,
                            size: "xl"
                        });
                    },
                    empty: variable => {
                        if (variable === undefined || $.trim(variable) == "") {
                            return true;
                        }
                        
                        if (typeof variable == "object" && variable.length == 0) {
                            return true;
                        }
                        return false;
                    }
                },
                created: function() {
                    this.orig_count = this.count = this.json.raw["MM24"];
                    this.id = `${this.json.raw['MM01']}${this.json.raw['MM02']}${this.json.raw['MM03']}`;
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-sur-mgt component can not be loaded.");
}
