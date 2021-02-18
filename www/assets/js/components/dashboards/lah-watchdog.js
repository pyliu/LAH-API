if (Vue) {
  Vue.component('lah-watchdog', {
    template: `<b-card>
        <template v-slot:header>
            <div class="d-flex w-100 justify-content-between mb-0">
                <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="dog"> 快速檢測</lah-fa-icon></h6>
            </div>
        </template>
        <b-button-group class="my-1">
            <lah-button icon="cog" action="spin" variant="outline-primary" @click="checkXcase" title="檢測跨所註記遺失問題">跨所註記遺失</lah-button>
            <lah-button icon="question" variant="success" @click="popupXcaseHelp" title="檢測跨所註記遺失說明"></lah-button>
        </b-button-group>
        <b-button-group class="my-1">
            <lah-button icon="cog" action="spin" variant="outline-primary" @click="checkEzPayment" title="檢測悠遊卡付款問題">悠遊卡付款</lah-button>
            <lah-button icon="question" variant="success" @click="popupEzPaymentHelp" title="檢測悠遊卡付款問題說明"></lah-button>
        </b-button-group>
        <b-button-group class="my-1">
            <lah-button icon="cog" action="spin" variant="outline-primary" @click="checkSurCase" title="檢測測量問題案件">測量問題案件</lah-button>
            <lah-button icon="question" variant="success" @click="popupSurCaseHelp" title="檢測測量問題案件說明"></lah-button>
        </b-button-group>
    </b-card>`,
    components: {
        "lah-problem-surcases": {
            template: `<ul style="font-size: 0.9rem">
                <li v-for="(id, index) in ids">
                    <a href='javascript:void(0)' @click="query(id)">{{display(id)}}</a>
                    <lah-button icon="hammer" variant="outline-success" @click="fix(id, $event)">修正</button>
                </li>
            </ul>`,
            props: ["ids"],
            methods: {
                display(id) {
                    if (id === undefined || id === '' || id.length !== 13) {
                        this.$warn(`id is not a valid string, can not oonvert to formated case id. (${id})`);
                        return id;
                    }
                    return `${id.substring(0, 3)}-${id.substring(3, 7)}-${id.substring(7)}`;
                },
                query(id) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "sur_case",
                        id: id
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL && res.data.data_count == 0) {
                            this.notify({
                                title: "測量案件查詢",
                                subtitle: `${this.id}`,
                                message: "查無資料",
                                type: "warning"
                            });
                        } else {
                            if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                                this.msgbox({
                                    title: "測量案件查詢",
                                    message: this.$createElement("lah-sur-case-dialog", { props: { json: res.data } }),
                                    callback: () => addUserInfoEvent()
                                });
                            } else if (res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                                throw new Error("查詢失敗：" + res.data.message);
                            }
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                },
                fix(id, evt) {
                    id = id.replace(/[^a-zA-Z0-9]/g, "");
                    showConfirm(`確定要修正本案件 ${this.display(id)} ?<p class="pt-3"><i class="fa fa-exclamation-circle text-danger"></i> 將修正下列欄位：</p>1.辦理情形改為核定<br/>2.清除延期時間<br/>3.連件數設為1<br/><br/>如需個別修正請點選連結再行修正。`, () => {
                        this.$log("The problematic sur case id: "+id);
                        this.isBusy = true;
                        $(evt.target).remove();
                        //fix_sur_delay_case
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "fix_sur_delay_case",
                            id: id,
                            UPD_MM22: true,
                            CLR_DELAY: true,
                            FIX_COUNT: true
                        }).then(res => {
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.notify({
                                    title: "修正複丈案件",
                                    subtitle: id,
                                    type: "success",
                                    message: "修正成功!"
                                });
                            } else {
                                let msg = "回傳狀態碼不正確!【" + res.data.message + "】";
                                this.alert({
                                    title: "修正複丈案件失敗",
                                    subtitle: id,
                                    message: msg,
                                    type: "danger"
                                });
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    });

                }
            }
        },
        "lah-easycard-payment-check-item": {
            template: `<ul style="font-size: 0.9rem">
                <li v-for="(item, index) in data" class='easycard_item'>
                    日期: {{item["AA01"]}}, 電腦給號: {{item["AA04"]}}, 實收金額: {{item["AA28"]}}<b-badge v-if="!empty(item['AA104'])" variant="danger">, 作廢原因: {{item["AA104"]}}</b-badge>, 目前狀態: {{status(item["AA106"])}}
                    <button v-if="empty(item['AA104'])" @click="fix($event, item)" class="btn btn-sm btn-outline-success">修正</button>
                </li>
            </ul>`,
            props: ["data"],
            methods: {
                fix: function(e, item) {
                    let el = $(e.target);
                    let qday = item["AA01"], pc_number = item["AA04"], amount = item["AA28"];
                    let message = "確定要修正 日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料?";
                    showConfirm(message, () => {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "fix_easycard",
                            qday: qday,
                            pc_num: pc_number
                        }).then(res => {
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                el.closest("li").html("修正 日期: " + qday + ", 電腦給號: " + pc_number + " <strong class='text-success'>成功</strong>!");
                            } else {
                                throw new Error("回傳狀態碼不正確!【" + res.data.message + "】");
                            }
                            el.remove();
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    });
                },
                status: function(AA106) {
                    let status = "未知的狀態碼【" + AA106 + "】";
                    /*
                        1：扣款成功
                        2：扣款失敗
                        3：取消扣款
                        8：扣款異常交易
                        9：取消扣款異常交易
                    */
                    switch(AA106) {
                        case "1":
                            status = "扣款成功";
                            break;
                        case "2":
                            status = "扣款失敗";
                            break;
                        case "3":
                            status = "取消扣款";
                            break;
                        case "8":
                            status = "扣款異常交易";
                            break;
                        case "9":
                            status = "取消扣款異常交易";
                            break;
                        default:
                            break;
                    }
                    return status;
                }
            }
        },
        "lah-xcase-check-item": {
            template: `<ul style="font-size: 0.9rem">
                <li v-for="(item, index) in ids">
                    <a href='javascript:void(0)' class='reg_case_id' @click="window.vueApp.fetchRegCase">{{item}}</a>
                    <button class='fix_xcase_button btn btn-sm btn-outline-success' :data-id='item' @click.once="fix">修正</button>
                </li>
            </ul>`,
            props: ["ids"],
            methods: {
                fix: function(e) {
                    let id = $(e.target).data("id").replace(/[^a-zA-Z0-9]/g, "");
                    console.log("The problematic xcase id: "+id);
                    let li = $(e.target).closest("li");
                    this.isBusy = true;
                    $(e.target).remove();
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "fix_xcase",
                        id: id
                    }).then(res => {
                        let msg = `<strong class='text-success'>${id} 跨所註記修正完成!</strong>`;
                        if (res.data.status != XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            msg = `<span class='text-danger'>${id} 跨所註記修正失敗! (${res.data.status})</span>`;
                        }
                        this.notify({ message: msg, variant: "success" });
                        li.html(msg);
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            }
        }
    },
    data: () => ({
        date: ""
    }),
    computed: { },
    watch: { },
    methods: {
        popupEzPaymentHelp() {
            this.msgbox({
                title: "悠遊卡自動加值付款失敗回復 小幫手提示",
                body: `
                    <img src="assets/howto/easycard_payment_fix.jpg" class="img-responsive img-thumbnail" />
                    <ol>
                        <li>櫃台來電通知悠遊卡扣款成功但地政系統卻顯示扣款失敗，需跟櫃台要【電腦給號】</li>
                        <li>管理師處理方法：AA106為'2' OR '8'將AA106更正為'1'即可【AA01:事發日期、AA04:電腦給號】。<br />
                        UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01='1070720' AND AA04='0043405'
                        </li>
                    </ol>
                    <img src="assets/img/easycard_screenshot.jpg" class="img-responsive img-thumbnail" />
                `,
                size: "lg"
            });
        },
        popupXcaseHelp() {
            this.msgbox({
                title: "跨所註記遺失檢測 小幫手提示",
                body: `<div class="d-block">
                    <h5><span class="text-danger">※</span>通常發生的情況是案件內的權利人/義務人/代理人姓名內有罕字造成。</h5>
                    <h5><span class="text-danger">※</span>僅檢測一周內資料。</h5>
                    <p class="text-info">QUERY:</p>
                    &emsp;SELECT * <br />
                    &emsp;FROM SCRSMS <br />
                    &emsp;WHERE  <br />
                    &emsp;&emsp;RM07_1 >= '1080715' <br />
                    &emsp;&emsp;AND RM02 LIKE 'H%1' <br />
                    &emsp;&emsp;AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR RM101_1 is NULL) 
                    <br /><br />
                    <p class="text-success">FIX:</p>
                    &emsp;UPDATE MOICAS.CRSMS SET <br />
                    &emsp;&emsp;RM99 = 'Y', <br />
                    &emsp;&emsp;RM100 = '資料管轄所代碼', <br />
                    &emsp;&emsp;RM100_1 = '資料管轄所縣市代碼', <br />
                    &emsp;&emsp;RM101 = '收件所代碼', RM101_1 = '收件所縣市代碼' <br />
                    &emsp;WHERE RM01 = '收件年' AND RM02 = '收件字' AND RM03 = '收件號'
                </div>`,
                size: "lg"
            });
        },
        popupSurCaseHelp() {
            this.msgbox({
                title: "測量案件資料 小幫手提示",
                message: `<h5><span class="text-danger">※</span>注意：本功能會清除如下圖之欄位資料並將案件辦理情形改為【核定】，請確認後再執行。</h5>
                <img src="assets/howto/107-HB18-3490_測丈已結案案件辦理情形出現(逾期)延期複丈問題調整【參考】.jpg" class="img-responsive img-thumbnail"/>
                <h5><span class="text-danger">※</span> 問題原因說明</h5>
                <div>原因是 CMB0301 延期複丈功能，針對於有連件案件在做處理時，會自動根據MM24案件數，將後面的案件自動做延期複丈的更新。導致後續已結案的案件會被改成延期複丈的狀態 MM22='C' 就是 100、200、300、400為四連件，所以100的案件 MM24='4'，200、300、400 的 MM24='0' 延期複丈的問題再將100號做延期複丈的時候，會將200、300、400也做延期複丈的更新，所以如果400已經結案，100做延期複丈，那400號就會變成 MM22='C' MM23='A' MM24='4' 的異常狀態。</div>`,
                size: "lg"
            });
        },
        checkSurCase() {
            const h = this.$createElement;
            this.isBusy = true;
            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "sur-problem-check"
            }).then(res => {
                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                    let vnode = this.$createElement("lah-problem-surcases", { props: { ids: res.data.ids } });
                    this.msgbox({
                        title: "<i class='fas fa-exclamation-triangle text-danger'></i>&ensp;<strong class='text-info'>請查看下列案件</strong>",
                        body: vnode,
                        size: "md"
                    });
                } else if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    this.notify({
                        title: "檢測測量問題案件",
                        message: "<i class='fas fa-circle text-success'></i>&ensp;"+ res.data.message,
                        type: "success"
                    });
                } else {
                    this.alert({ title: "檢測測量問題案件", message: res.data.message, type: "danger" });
                }
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        },
        checkXcase() {
            const h = this.$createElement;
            this.isBusy = true;
            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "xcase-check"
            }).then(res => {
                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                    let vnode = h("lah-xcase-check-item", { props: { ids: res.data.case_ids } });
                    this.msgbox({
                        title: "<i class='fas fa-circle text-danger'></i>&ensp;<strong class='text-info'>請查看並修正下列案件</strong>",
                        body: vnode,
                        size: "md"
                    });
                } else if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    this.notify({
                        title: "檢測系統跨所註記遺失",
                        message: "<i class='fas fa-circle text-success'></i>&ensp;目前無跨所註記遺失問題",
                        type: "success"
                    });
                } else {
                    this.alert({ title: "檢測系統跨所註記遺失", message: res.data.message, type: "danger" });
                }
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        },
        checkEzPayment() {
            this.isBusy = true;
            const h = this.$createElement;
            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "ez-payment-check",
                qday: this.date
            }).then(res => {
                if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
                    this.notify({
                        title: "檢測悠遊卡自動加值付款失敗",
                        message: `<i class='fas fa-circle text-success mr-1'></i>${res.data.message}`,
                        type: "success"
                    });
                } else {
                    this.msgbox({
                        title: "<i class='fas fa-circle text-warning mr-1'></i><strong class='text-danger'>找到下列資料</strong>",
                        body: h("lah-easycard-payment-check-item", { props: { data: res.data.raw } }),
                        size: "md"
                    });
                }
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        },
    }
  });
} else {
  console.error("vue.js not ready ... lah-watchdog component can not be loaded.");
}