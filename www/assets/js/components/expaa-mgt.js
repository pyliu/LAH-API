if (Vue) {
    Vue.component("expaa-mgt", {
        template: `<fieldset>
            <legend>規費資料</legend>
            <b-container :class="['form-row']">
                <div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                    <span class="input-group-text" id="inputGroup-expaa_query_date">日期</span>
                    </div>
                    <b-form-input v-model="date" id="expaa_query_date" placeholder="Enter the $$" :state="isDateValid" size="sm" :class="['form-control', 'date_picker', 'no-cache']"></b-form-input>
                    <button id="expaa_query_date_button" class="btn btn-sm btn-outline-primary">查詢</button>
                </div>
                <div class="input-group input-group-sm col">
                    <div class="input-group-prepend">
                    <span class="input-group-text" id="inputGroup-expaa_query_number">給號</span>
                    </div>
                    <input type="number" max="9999999" min="1" id="expaa_query_number" name="expaa_query_number" class="form-control" placeholder="0006574" data-toggle="popover" data-content="需輸入7位數電腦給號，如「0021131」。" data-placement="bottom" />
                    <button id="expaa_query_num_button" class="btn btn-sm btn-outline-secondary" title="針對電腦給號查詢">查詢</button>
                </div>
                <div class="filter-btn-group col">
                    <!-- <button id="expaa_query_date_button" class="btn btn-sm btn-outline-primary">查詢</button> -->
                    <button id="expaa_add_obsolete_button" class="btn btn-sm btn-outline-danger" title="新增作廢假資料以利空白規費單作廢">作廢</button>
                    <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
                </div>
            </b-container>
        </fieldset>`,
        data: () => {
            return {
                isDateValid: null,
                date: "1081230"
            }
        },
        watch: {
            date: function(val) {
                if (val == '' || val == undefined) {
                    this.isDateValid = null;
                } else if (val.length == 7) {
                    this.isDateValid = true;
                } else {
                    this.isDateValid = false;
                }
                console.log(this.date);
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
                    <img src="assets/img/easycard_screenshot.jpg" class="img-responsive img-thumbnail my-1" />`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... expaa-mgt component can not be loaded.");
}
