if (Vue) {
    Vue.component("expaa-mgt", {
        template: `<fieldset>
            <legend>規費資料</legend>
            <b-container :class="['form-row']" fluid>
                <div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="inputGroup-fee_query_date">日期</span>
                    </div>
                    <b-form-input
                        v-model="date"
                        id="fee_query_date"
                        placeholder="民國年月日"
                        :state="isDateValid"
                        :class="['no-cache', 'bg-light', 'border', 'pl-2']"
                        size="sm"
                        plaintext
                        trim
                    >
                    </b-form-input>
                    <div class="input-group-prepend ml-1">
                        <span class="input-group-text" id="inputGroup-fee_query_number">電腦給號</span>
                    </div>
                    <b-form-input
                        v-model="number"
                        id="fee_query_number"
                        type="number"
                        placeholder="七碼電腦給號"
                        :state="isNumberValid"
                        size="sm"
                        max=9999999
                        min=1
                        trim
                        number
                    >
                    </b-form-input>
                    &ensp;
                    <button id="fee_query_button" class="btn btn-sm btn-outline-primary">查詢</button>
                    &ensp;
                    <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
                    &ensp;
                    <button id="expaa_add_obsolete_button" class="btn btn-sm btn-outline-danger" title="新增作廢假資料以利空白規費單作廢"><i class="fas fa-ban"></i></button>
                </div>
            </b-container>
        </fieldset>`,
        data: () => {
            return {
                date: "",
                number: "",
                expe: { // from MOIEXP.EXPE
                    "01": "土地法65條登記費",
                    "02": "土地法76條登記費",
                    "03": "土地法67條書狀費",
                    "04": "地籍謄本工本費",
                    "06": "檔案閱覽抄錄複製費",
                    "07": "閱覽費",
                    "08": "門牌查詢費",
                    "09": "複丈費及建物測量費",
                    "10": "地目變更勘查費",
                    "14": "電子謄本列印",
                    "18": "塑膠樁土地界標",
                    "19": "鋼釘土地界標(大)",
                    "30": "104年度登記罰鍰",
                    "31": "100年度登記罰鍰",
                    "32": "101年度登記罰鍰",
                    "33": "102年度登記罰鍰",
                    "34": "103年度登記罰鍰",
                    "35": "其他",
                    "36": "鋼釘土地界標(小)",
                    "37": "105年度登記罰鍰",
                    "38": "106年度登記罰鍰",
                    "39": "塑膠樁土地界標(大)",
                    "40": "107年度登記罰鍰",
                    "41": "108年度登記罰鍰",
                    "42": "土地法第76條登記費（跨縣市）",
                    "43": "書狀費（跨縣市）",
                    "44": "罰鍰（跨縣市）",
                    "45": "109年度登記罰鍰"
                }
            }
        },
        computed: {
            isDateValid: function() {
                if (this.date == '' || this.date == undefined) {
                    return null;
                } else if (this.date.length == 7) {
                    return true;
                }
                return false;
            },
            isNumberValid: function() {
                if (this.number == '' || this.number == undefined) {
                    return null;
                } else if (this.number.toString().length <= 7) {
                    return true;
                }
                return false;
            }
        },
        watch: {
            number: function(nVal, oVal) {
                if (this.number > 9999999) this.number = 9999999;
                else if (this.number < 1) this.number = 1;
            }
        },
        methods: {
            popup: function(e) {
                showModal({
                    title: "規費資料 小幫手提示",
                    body: `AA09 - 列印註記【1：已印，0：未印】<br />
                    AA100 - 付款方式<br />
                    <img src="assets/img/EXPAA_AA100_Update.jpg" class="img-responsive img-thumbnail my-1" /><br />
                    AA106 - 悠遊卡繳費扣款結果<br />
                    AA107 - 悠遊卡交易流水號<br />
                    <img src="assets/img/easycard_screenshot.jpg" class="img-responsive img-thumbnail my-1" />
                    AA28、AA39 - 規費資料集(EXPAA)中記載金額的兩個欄位<br />
                    AC29、AC30 - 規費項目資料集(EXPAC)中記載收費項目之金額<br />
                    <img src="assets/howto/EXPAA_EXPAC_AMOUNT_MOD.jpg" class="img-responsive img-thumbnail my-1" />
                    `,
                    size: "lg"
                });
            }
        },
        created: function() {
            var d = new Date();
            this.date = toTWDate(d);
            if (this.number > 9999999) this.number = 9999999;
            else if (this.number < 1) this.number = 1;
        },
        mounted: function() {
            if ($("#fee_query_date").datepicker) {
                $("#fee_query_date").datepicker({
                    daysOfWeekDisabled: "",
                    language: "zh-TW",
                    daysOfWeekHighlighted: "1,2,3,4,5",
                    todayHighlight: true,
                    autoclose: true,
                    format: {
                        toDisplay: (date, format, language) => toTWDate(new Date(date)),
                        toValue: (date, format, language) => new Date()
                    }
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... expaa-mgt component can not be loaded.");
}
