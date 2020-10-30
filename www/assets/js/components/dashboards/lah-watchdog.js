if (Vue) {
  Vue.component('lah-watchdog', {
    template: `<b-card>
        <template v-slot:header>
            <div class="d-flex w-100 justify-content-between mb-0">
                <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="search"> 快速檢測</lah-fa-icon></h6>
            </div>
        </template>
        <b-button-group>
            <lah-button icon="cogs" variant="outline-primary" @click="checkXcase" title="檢測跨所註記遺失問題">跨所註記遺失</lah-button>
            <lah-button icon="question" variant="success" @click="popupXcaseHelp" title="檢測跨所註記遺失說明"></lah-button>
        </b-button-group>
        <b-button-group>
            <lah-button icon="cogs" variant="outline-primary" @click="checkEzPayment" title="檢測悠遊卡付款問題">悠遊卡付款問題</lah-button>
            <lah-button icon="question" variant="success" @click="popupEzPaymentHelp" title="檢測悠遊卡付款問題說明"></lah-button>
        </b-button-group>
    </b-card>`,
    components: {
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
        date: "",
        ad_date: "2020-10-30"
    }),
    computed: { },
    watch: { },
    methods: {
        convertTWDate(val) {
            let d = new Date(val);
            this.date = (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
            return val;
        },
        popupEzPaymentHelp() {
            this.msgbox({
                title: "悠遊卡自動加值付款失敗回復 小幫手提示",
                body: `
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
            // basic checking for tw date input
            let regex = /^\d{7}$/;
            if (!this.empty(this.date) && this.date.match(regex) == null) {
                showPopper("#easycard_query_day");
                return;
            }

            this.isBusy = true;
            const h = this.$createElement;

            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "easycard",
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