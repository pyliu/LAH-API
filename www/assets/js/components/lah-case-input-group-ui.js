if (Vue) {
    Vue.component("lah-case-input-group-ui", {
        template: `<div class="d-flex" v-b-popover.hover.focus.bottom.d1000="preview">
            <b-input-group size="sm" append="年" @click="getMaxNumber">
                <b-form-select
                    ref="year"
                    v-model="year"
                    :options="years"
                    @change="emitInput"
                    @change="getMaxNumber"
                    :state="!empty(year)"
                >
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇年份 --</b-form-select-option>
                    </template>
                </b-form-select>
            </b-input-group>
            <b-input-group size="sm" class="mx-1" append="字">
                <b-form-select
                    ref="code"
                    v-model="code"
                    @change="emitInput"
                    @change="getMaxNumber"
                    :state="!empty(code)"
                >
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇案件字 --</b-form-select-option>
                    </template>
                    <optgroup v-for="obj in code_data" :label="obj.label" :class="codeBg(obj.label)">
                        <option v-for="item in obj.options" :value="item.replace(/[^A-Za-z0-9]/g, '')">{{item}}</option>
                    </optgroup>
                </b-form-select>
            </b-input-group>
            <b-input-group size="sm" append="號">
                <b-form-input
                    ref="num"
                    v-model="num"
                    v-b-tooltip.hover="'最多6個數字'"
                    @input="emitInput"
                    @keyup.enter="$emit('enter', $event)"
                    type="number"
                    :step="num_step"
                    :min="num_min"
                    :max="num_max"
                    :state="num >= num_min && num <= num_max"
                ></b-form-input>
            </b-input-group>
        </div>`,
        props: ["type", "prefix", 'value'],
        data: () => ({
            codes: {
                reg: {
                    HB: {
                        label: "登記案件-本所",
                        options: ["HB04 壢登", "HB05 壢永", "HB06 壢速"]
                    },
                    HXB1: {
                        label: "登記案件-本所收件(跨所)",
                        options: ["HAB1 壢桃登跨", "HCB1 壢溪登跨", "HDB1 壢楊登跨", "HEB1 壢蘆登跨", "HFB1 壢德登跨", "HGB1 壢平登跨", "HHB1 壢山登跨"]
                    },
                    HBX1: {
                        label: "登記案件-他所收件(跨所)",
                        options: ["HBA1 桃壢登跨", "HBC1 溪壢登跨", "HBD1 楊壢登跨", "HBE1 蘆壢登跨", "HBF1 德壢登跨", "HBG1 平壢登跨", "HBH1 山壢登跨"]
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
                        options: ["HB12 中地測丈", "HB13 中地測建", "HB17 中地法土", "HB18 中地法建"]
                    }
                },
                prc: {
                    HB: {
                        label: "地價案件",
                        options: ["HB31 地價更正"]
                    }
                }
            },
            year: "109",
            code: "",
            num: "",
            num_step: 10,
            num_min: 10,
            num_max: 999999,
            code_data: [],
            years: []
        }),
        computed: {
            ID() { return `${this.year}-${this.code}-${this.num}`},
            preview() { return `目前案件代碼：${this.ID}` }
        },
        methods: {
            emitInput: function(e) {
                this.$emit('input', `${this.year}${this.code}${this.num}`);
            },
            getMaxNumber: function(e) {
                if (this.empty(this.year)) {
                    this.$warn(`案件年不能為空值【${this.year}】`);
                } else if (this.empty(this.code)) {
                    this.$warn(`案件字不能為空值【${this.code}】`);
                } else {
                    this.isBusy = true;
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
                            this.notify({message: res.data.message, type: "warning"});
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
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
            restoreCodesByJSON: function(json) {
                for (let city in json.raw['跨縣市本所收件']) {
                    for (let code in json.raw['跨縣市本所收件'][city]) {
                        let clean_name = json.raw['跨縣市本所收件'][city][code].replace('跨縣市（', '').replace('）', '');
                        this.codes.reg.H2XX.options.push(`${code} ${clean_name} (${city})`);
                    }
                }
                for (let city in json.raw['跨縣市他所收件']) {
                    for (let code in json.raw['跨縣市他所收件'][city]) {
                        let clean_name = json.raw['跨縣市他所收件'][city][code].replace('跨縣市（', '').replace('）', '');
                        this.codes.reg.XXHB.options.push(`${code} ${clean_name} (${city})`);
                    }
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
        created: async function() {
            this.getLocalCache('case_input_years').then(years => {
                if (years !== false) {
                    this.years = years;
                } else {
                    // set year select options
                    var d = new Date();
                    this.year = (d.getFullYear() - 1911);
                    let len = this.year - 105;
                    for (let i = 0; i <= len; i++) {
                        this.years.push({value: 105 + i, text: 105 + i});
                    }
                    this.setLocalCache('case_input_years', this.years, 24 * 60 * 60 * 1000);  // cache for a day
                }
            });

            this.getLocalCache('reg_code').then(json => {
                if (json !== false) {
                    this.restoreCodesByJSON(json);
                } else {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: 'reg_code'
                    }).then(res => {
                        this.restoreCodesByJSON(res.data);
                        this.setLocalCache('reg_code', res.data, 7 * 24 * 60 * 60 * 1000);  // cache for a week
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            });

        },
        mounted: function(e) {
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
            // setup delay timer to allow cached data update to the input/select element
            this.timeout(() => {
                this.year = this.$refs.year.$el.value;
                this.code = this.$refs.code.$el.value;
                this.num = this.$refs.num.$el.value;
                this.emitInput();
            }, 400);    // cached data write back
        }
    });
} else {
    console.error("vue.js not ready ... lah-case-input-group-ui component can not be loaded.");
}
