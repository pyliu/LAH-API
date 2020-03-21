if (Vue) {
    Vue.component("case-reg-detail", {
        template: `<div>
            <p v-html="jsonObj.tr_html"></p>
            <b-card no-body>
                <b-tabs card :end="tabsAtEnd" :pills="tabsAtEnd">
                    <b-tab title="收件資料">
                        <b-card-body>
                            <b-form-row class="mb-1">
                                <b-col>    
                                    <lah-transition appear>
                                        <div v-show="show_op_card" class="mr-1 float-right" style="width:400px">
                                            <lah-fa-icon icon="user" variant="dark" prefix="far"> 作業人員</lah-fa-icon>
                                            <lah-user-card @not-found="handleNotFound" :id="jsonObj.作業人員ID"></lah-user-card>
                                        </div>
                                    </lah-transition>
                                    <div v-if="jsonObj.跨所 == 'Y'"><span class='bg-info text-white rounded p-1'>跨所案件 ({{jsonObj.資料收件所}} => {{jsonObj.資料管轄所}})</span></div>
                                    收件字號：
                                    <a :title="'收件資料 on ' + ap_server" href="javascript:void(0)" @click="window.vueApp.open(case_data_url, $event)">
                                        {{jsonObj.收件字號}}
                                    </a> <br/>
                                    收件時間：{{jsonObj.收件時間}} <br/>
                                    測量案件：{{jsonObj.測量案件}} <br/>
                                    限辦期限：<span v-html="jsonObj.限辦期限"></span> <br/>
                                    作業人員：<span class='user_tag'>{{jsonObj.作業人員}}</span> <br/>
                                    辦理情形：{{jsonObj.辦理情形}} <br/>
                                    登記原因：{{jsonObj.登記原因}} <br/>
                                    區域：{{area}}【{{jsonObj.RM10}}】 <br/>
                                    段小段：{{jsonObj.段小段}}【{{jsonObj.段代碼}}】 <br/>
                                    地號：{{jsonObj.地號}} <br/>
                                    建號：{{jsonObj.建號}} <br/>
                                    件數：{{jsonObj.件數}} <br/>
                                    登記處理註記：{{jsonObj.登記處理註記}} <br/>
                                    地價處理註記：{{jsonObj.地價處理註記}} <br/>
                                    手機號碼：{{jsonObj.手機號碼}}
                                </b-col>
                            </b-form-row>
                            <b-form-row>
                                <b-col class="text-center">
                                    <b-button variant="outline-primary" size="sm" @click="window.vueApp.open(case_data_url, $event)" :title="'收件資料 on ' + ap_server"><i class="fas fa-search"></i> 另開視窗查詢</b-button>
                                </b-col>
                            </b-form-row>
                        </b-card-body>
                    </b-tab>
                    <b-tab title="辦理情形">
                        <b-card-body>
                            <b-list-group flush compact>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col :title="jsonObj.預定結案日期">預定結案：<span v-html="jsonObj.限辦期限"></span></b-col>
                                        <b-col :title="jsonObj.結案與否">
                                            結案與否：
                                            <span v-if="is_ongoing" class='text-danger'><strong>尚未結案！</strong></span>
                                            <span v-else class='text-success'><strong>已結案</strong></span>
                                        </b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.代理人統編)">
                                    <b-form-row>
                                        <b-col>代理人統編：{{jsonObj.代理人統編}}</b-col>
                                        <b-col>代理人姓名：{{jsonObj.代理人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.權利人統編)">
                                    <b-form-row>
                                        <b-col>權利人統編：{{jsonObj.權利人統編}}</b-col>
                                        <b-col>權利人姓名：{{jsonObj.權利人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.義務人統編)">
                                    <b-form-row>
                                        <b-col>義務人統編：{{jsonObj.義務人統編}}</b-col>
                                        <b-col>義務人姓名：{{jsonObj.義務人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col>登記原因：{{jsonObj.登記原因}}</b-col>
                                        <b-col>辦理情形：<span :class="jsonObj.案件紅綠燈CSS">{{jsonObj.辦理情形}}</span></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col>收件人員：<span class='user_tag'  :data-id="jsonObj.收件人員ID" :data-name="jsonObj.收件人員">{{jsonObj.收件人員}}</span></b-col>
                                        <b-col>收件時間：{{jsonObj.收件時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.移轉課長)">
                                    <b-form-row>
                                        <b-col>移轉課長：<span class='user_tag' >{{jsonObj.移轉課長}}</span></b-col>
                                        <b-col>移轉課長時間：{{jsonObj.移轉課長時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.移轉秘書)">
                                    <b-form-row>
                                        <b-col>移轉秘書：<span class='user_tag' >{{jsonObj.移轉秘書}}</span></b-col>
                                        <b-col>移轉秘書時間：{{jsonObj.移轉秘書時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.初審人員)">
                                    <b-form-row>
                                        <b-col>初審人員：<span class='user_tag' >{{jsonObj.初審人員}}</span></b-col>
                                        <b-col>初審時間：{{jsonObj.初審時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.複審人員)">
                                    <b-form-row>
                                        <b-col>複審人員：<span class='user_tag' >{{jsonObj.複審人員}}</span></b-col>
                                        <b-col>複審時間：{{jsonObj.複審時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.駁回日期)">
                                    <b-form-row>
                                        <b-col>駁回日期：{{jsonObj.駁回日期}}</b-col>
                                        <b-col></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.公告日期)">
                                    <b-form-row>
                                        <b-col>公告日期：{{jsonObj.公告日期}}</b-col>
                                        <b-col>公告到期：{{jsonObj.公告期滿日期}} 天數：{{jsonObj.公告天數}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.通知補正日期)">
                                    <b-form-row>
                                        <b-col>通知補正：{{jsonObj.通知補正日期}}</b-col>
                                        <b-col>補正期滿：{{jsonObj.補正期滿日期}} 天數：{{jsonObj.補正期限}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.補正日期)">
                                    <b-form-row>
                                        <b-col>補正日期：{{jsonObj.補正日期}}</b-col>
                                        <b-col></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.請示人員)">
                                    <b-form-row>
                                        <b-col>請示人員：<span class='user_tag' >{{jsonObj.請示人員}}</span></b-col>
                                        <b-col>請示時間：{{jsonObj.請示時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.展期人員)">
                                    <b-form-row>
                                        <b-col>展期人員：<span class='user_tag' >{{jsonObj.展期人員}}</span></b-col>
                                        <b-col>展期日期：{{jsonObj.展期日期}} 天數：{{jsonObj.展期天數}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.准登人員)">
                                    <b-form-row>
                                        <b-col>准登人員：<span class='user_tag' >{{jsonObj.准登人員}}</span></b-col>
                                        <b-col>准登日期：{{jsonObj.准登日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.登錄人員)">
                                    <b-form-row>
                                        <b-col>登錄人員：<span class='user_tag' >{{jsonObj.登錄人員}}</span></b-col>
                                        <b-col>登錄日期：{{jsonObj.登錄日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.校對人員)">
                                    <b-form-row>
                                        <b-col>校對人員：<span class='user_tag' >{{jsonObj.校對人員}}</span></b-col>
                                        <b-col>校對日期：{{jsonObj.校對日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(jsonObj.結案人員)">
                                    <b-form-row>
                                        <b-col>結案人員：<span class='user_tag' >{{jsonObj.結案人員}}</span></b-col>
                                        <b-col>結案日期：{{jsonObj.結案日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                            </b-list-group>
                            <b-form-row class="mt-2">
                                <b-col class="text-center">
                                    <b-button variant="outline-primary" size="sm" @click="window.vueApp.open(case_status_url, $event)" title="案件辦理情形"><i class="fas fa-search"></i> 另開視窗查詢</b-button>
                                </b-col>
                            </b-form-row>
                        </b-card-body>
                    </b-tab>
                    <b-tab v-if="isAdmin" title="狀態管理" lazy><lah-reg-case-state-mgt :raw="jsonObj"></lah-reg-case-state-mgt></b-tab>
                    <b-tab v-if="isAdmin" title="同步資料管理" lazy>TODO</b-tab>
                    <b-tab v-if="isAdmin" title="暫存檔管理" lazy>TODO</b-tab>
                </b-tabs>
            </b-card>
        </div>`,
        props: ['jsonObj', 'tabsEnd'],
        data: () => {
            return {
                area: "",
                rm10: null,
                ap_server: "220.1.35.123",
                case_status_url: "",
                case_data_url: "",
                is_ongoing: false,
                show_op_card: true
            }
        },
        computed: {
            tabsAtEnd() { return !this.empty(this.tabsEnd) }
        },
        methods: {
            handleNotFound: function(input) { this.show_op_card = false }
        },
        created() {
            this.rm10 = this.jsonObj.RM10 ? this.jsonObj.RM10 : "XX";
            switch (this.rm10) {
                case "03":
                    this.area = "中壢區";
                    break;
                case "12":
                    this.area = "觀音區";
                    break;
                default:
                    this.area = "其他(" + this.jsonObj.資料管轄所 + "區)";
                    break;
            }
            this.case_status_url = `http://${this.ap_server}:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=${this.jsonObj["RM01"]}&word=${this.jsonObj["RM02"]}&code=${this.jsonObj["RM03"]}&sdlyn=N&RM90=`;
            this.case_data_url = `http://${this.ap_server}:9080/LandHB/CAS/CCD01/CCD0103.jsp?rm01=${this.jsonObj["RM01"]}&rm02=${this.jsonObj["RM02"]}&rm03=${this.jsonObj["RM03"]}`
            this.is_ongoing = this.empty(this.jsonObj.結案已否);
        },
        mounted() {
            addUserInfoEvent();
        }
    });
    Vue.component('lah-reg-case-state-mgt', {
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
                window.vueApp.fetchRegCase(e);
            });
        }
    });
} else {
    console.error("vue.js not ready ... case-reg-detail component can not be loaded.");
}
