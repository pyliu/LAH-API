if (Vue) {
    Vue.component("lah-case-input-group-ui", {
        template: `<div class="d-flex" v-b-popover.hover.bottom.d1000="preview">
            <b-input-group size="sm" append="年" @click="getMaxNumber" class="text-nowrap">
                <b-form-select
                    ref="year"
                    v-model="year"
                    :options="years"
                    @change="emitInput"
                    @change="getMaxNumber"
                    class="h-100"
                >
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇年份 --</b-form-select-option>
                    </template>
                </b-form-select>
            </b-input-group>
            <b-input-group size="sm" class="mx-1" append="字" class="text-nowrap mx-1">
                <b-form-select
                    ref="code"
                    v-model="code"
                    @change="emitInput"
                    @change="getMaxNumber"
                    class="h-100"
                >
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇案件字 --</b-form-select-option>
                    </template>
                    <optgroup v-for="obj in code_data" :label="obj.label" :class="codeBg(obj.label)" v-if="obj.options.length > 0">
                        <option v-for="item in obj.options" :value="item.replace(/[^A-Za-z0-9]/g, '')">{{item}}</option>
                    </optgroup>
                </b-form-select>
            </b-input-group>
            <b-input-group size="sm" append="號" class="text-nowrap">
                <b-form-input
                    ref="num"
                    v-model="num"
                    title="最多6個數字"
                    @input="emitInput"
                    @keyup.enter="$emit('enter', $event)"
                    type="number"
                    :step="num_step"
                    :min="num_min"
                    :max="num_max"
                    class="h-100"
                ></b-form-input>
            </b-input-group>
        </div>`,
        props: ["type", "prefix", 'value'],
        data: () => ({
            codes: {},
            code_data: [],
            years: [],
            year: "110",
            code: "",
            num: "",
            num_step: 10,
            num_min: 10,
            num_max: 999999,
            retry: 3
        }),
        computed: {
            ID() { return `${this.year}-${this.code}-${this.num.padStart(6, '0')}`},
            preview() { return `案件代碼預覽：${this.ID}` },
            code_cache_key() { return `code_data_${this.year}` },
            code_cache_key_permanent() { return `code_data_${this.year}_permanent` }
        },
        methods: {
            emitInput: function(e) {
                this.$emit('input', `${this.year}${this.code}${this.num}`);
            },
            getMaxNumber: function(e) {
                if (this.empty(this.year)) {
                    // this.$warn(`案件年不能為空值【${this.year}】`);
                } else if (this.empty(this.code)) {
                    // this.$warn(`案件字不能為空值【${this.code}】`);
                } else {
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        "type": "max",
                        "year": this.year,
                        "code": this.code
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            // update UI
                            this.num = res.data.max;
                            this.emitInput(e);
                        } else {
                            this.$warn(`無法取得最大號 ${this.year}-${this.code}`);
                        }
                    }).catch(err => {
                        this.error = err;
                    });
                }
            },
            newCustomEvent: (name, val, target) => {
                let evt = new CustomEvent(name, {
                    detail: val,
                    bubbles: true
                });
                Object.defineProperty(evt, 'target', {writable: false, value: target});
                return evt;
            },
            reloadCode() {
                this.getLocalCache(this.code_cache_key).then(items => {
                    if (items === false || !Array.isArray(items)) {
                        this.getDBCodeData();
                    } else {
                        this.restoreCodeData(items);
                    }
                });
            },
            getDBCodeData() {
                if (this.isBusy) return;
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: 'code_data',
                    year: this.year
                }).then(res => {
                    this.setLocalCache(this.code_cache_key, res.data.raw, 12 * 60 * 60 * 1000);  // cache for half day
                    if (!this.empty(res.data.raw)) {
                        // no expire time
                        this.setLocalCache(this.code_cache_key_permanent, res.data.raw, 0);
                    }
                    this.restoreCodeData(res.data.raw);
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            resetCodes() {
                this.codes = Object.assign({}, {
                    reg: {
                        HB: {
                            label: "登記案件-本所",
                            options: []
                        },
                        HXB1: {
                            label: "登記案件-本所收件(跨所)",
                            options: []
                        },
                        HBX1: {
                            label: "登記案件-他所收件(跨所)",
                            options: []
                        },
                        H2XX: {
                            label: "登記案件-本所收件(跨縣市)",
                            options: []
                        },
                        XXHB: {
                            label: "登記案件-他所收件(跨縣市)",
                            options: []
                        }
                    },
                    sur: {
                        HB: {
                            label: "測量案件",
                            options: []
                        }
                    },
                    prc: {
                        HB: {
                            label: "地價案件",
                            options: ["HB31 地價更正"]
                        }
                    }
                });
            },
            async restoreCodeData(items) {
                // ITEM欄位：YEAR, CODE, CODE_NAME, COUNT, CODE_TYPE
                // [109, HCB1, 壢溪登跨, 1213, reg.HXB1]
                if (!Array.isArray(items)) {
                    items = await this.getLocalCache(this.code_cache_key_permanent);
                }

                if (Array.isArray(items)) {
                    this.resetCodes();
                    items.forEach(item => {
                        let type = item['CODE_TYPE'].split('.');
                        // type => ['reg', 'HXB1']
                        
                        if (this.empty(item['CODE_NAME'])) return false;
                        if (this.empty(this.codes[type[0]])) return false;
                        if (this.empty(this.codes[type[0]][type[1]])) return false;

                        let combined = item['CODE'] + ` ${item['CODE_NAME']}`;  // 'HCB1 壢溪登跨'
                        let found = this.codes[type[0]][type[1]].options.find((i, idx, array) => { return i == combined });
                        if (!found) {
                            this.codes[type[0]][type[1]].options.push(combined);
                        }
                    });
                    //this.codes = Object.assign({}, this.codes);
                    this.arrangeCodeList();
                } else {
                    if (--this.retry > 0) {
                        this.timeout(() => {
                            this.reloadCode();
                        }, 200);
                    } else {
                        this.notify({
                            title: `案件字還原`,
                            subtitle: this.code_cache_key,
                            message: `無法讀取案件「字」資料`,
                            type: 'warning'
                        });
                        this.timeout(window.location.reload, 1000);
                    }
                }
            },
            arrangeCodeList() {
                this.code_data = [];
                switch(this.type) {
                    case "reg":
                        this.code_data.push(this.codes.reg.HB);
                        this.code_data.push(this.codes.reg.HXB1);
                        this.code_data.push(this.codes.reg.HBX1);
                        this.code_data.push(this.codes.reg.H2XX);
                        this.code_data.push(this.codes.reg.XXHB);
                        this.num_step = this.num_min = 10;
                        break;
                    case "sur":
                        this.code_data.push(this.codes.sur.HB);
                        this.num_step = this.num_min = 100;
                        this.num = "000100";
                        break;
                    case "sync":
                        this.code_data.push(this.codes.reg.HXB1);
                        break;
                    case "tmp":
                        this.code_data.push(this.codes.reg.HB);
                        this.code_data.push(this.codes.prc.HB);
                        this.code_data.push(this.codes.reg.HXB1);
                        this.code_data.push(this.codes.reg.HBX1);
                        this.code_data.push(this.codes.reg.H2XX);
                        this.code_data.push(this.codes.reg.XXHB);
                        this.num_step = this.num_min = 1;
                        break;
                    default:
                        this.code_data.push(this.codes.reg.HB);
                        this.code_data.push(this.codes.reg.HXB1);
                        this.code_data.push(this.codes.reg.HBX1);
                        this.code_data.push(this.codes.prc.HB);
                        this.code_data.push(this.codes.sur.HB);
                        this.code_data.push(this.codes.reg.H2XX);
                        this.code_data.push(this.codes.reg.XXHB);
                        break;
                }
            },
            codeBg(label) {
                let bg_css = '';
                switch (label) {
                    case '登記案件-本所收件(跨所)':
                        bg_css = 'bg-primary text-white';
                        break;
                    case '登記案件-他所收件(跨所)':
                        bg_css = 'bg-info text-white';
                        break;
                    case '登記案件-本所收件(跨縣市)':
                        bg_css = 'bg-success text-white';
                        break;
                    case '登記案件-他所收件(跨縣市)':
                        bg_css = 'bg-warning';
                        break;
                    case '測量案件':
                        bg_css = 'bg-dark text-white';
                        break;
                    case '地價案件':
                        bg_css = 'bg-secondary text-white';
                        break;
                    default:
                        break;
                }
                return bg_css;
            }
        },
        watch: {
            year: function(val) {
                let evt = this.newCustomEvent('year-updated', val, this.$refs.year.$el);
                this.$emit("year-updated", evt);
                this.reloadCode();
            },
            code: function(val) {
                this.num_step = val == "HB12" || val == "HB17" ? 100 : 10;
                let evt = this.newCustomEvent('code-updated', val, this.$refs.code.$el);
                this.$emit("code-updated", evt);
            },
            num: function(val) {
                let evt = this.newCustomEvent('num-updated', val, this.$refs.num.$el);
                this.$emit("num-updated", evt);
            }
        },
        created() {
            this.getLocalCache('case_input_years').then(years => {
                if (years !== false) {
                    this.years = years;
                } else {
                    // set year select options
                    var d = new Date();
                    this.year = (d.getFullYear() - 1911);
                    let len = this.year - 100;
                    for (let i = 0; i <= len; i++) {
                        this.years.push({value: 100 + i, text: 100 + i});
                    }
                    this.setLocalCache('case_input_years', this.years, 24 * 60 * 60 * 1000);  // cache for a day
                }
                this.reloadCode();
            });

        }
    });
} else {
    console.error("vue.js not ready ... lah-case-input-group-ui component can not be loaded.");
}
