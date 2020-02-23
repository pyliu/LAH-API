if (Vue) {
    Vue.component("case-input-group-ui", {
        template: `<b-form-row>
            <b-input-group size="sm" class="col-3">
                <b-form-select v-model="year" :options="years" @change="uiUpdate" @change="getMaxNumber" :id="prefix+'_case_update_year'">
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇年份 --</b-form-select-option>
                    </template>
                </b-form-select>
                <b-input-group-append is-text>年</b-input-group-append>
            </b-input-group>
            <b-input-group size="sm" class="col">
                <b-form-select v-model="code" @change="uiUpdate" @change="getMaxNumber" :id="prefix+'_case_update_code'">
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇案件字 --</b-form-select-option>
                    </template>
                    <optgroup v-for="obj in code_data" :label="obj.label">
                        <option v-for="item in obj.options" :value="item.replace(/[^A-Za-z0-9]/g, '')">{{item}}</option>
                    </optgroup>
                </b-form-select>
                <b-input-group-append is-text>字</b-input-group-append>
            </b-input-group>
            <b-input-group size="sm" class="col-4">
                <b-form-input
                    v-model="num"
                    v-b-tooltip.hover="'最多6個數字'"
                    @input="uiUpdate"
                    @keyup.enter="$emit('enter', $event)"
                    type="number"
                    :step="num_step"
                    :min="num_min"
                    :max="num_max"
                    :id="prefix+'_case_update_num'"
                    :state="num >= num_min && num <= num_max"
                ></b-form-input>
                <b-input-group-append is-text>號</b-input-group-append>
            </b-input-group>
        </b-form-row>`,
        props: ["type", "prefix"],
        data: function(e) {
            return {
                local_reg_case_code: {
                    label: "本所",
                    options: ["HB04 壢登", "HB05 壢永", "HB06 壢速"]
                },
                remote_local_reg_case_code: {
                    label: "本所收件(跨所)",
                    options: ["HAB1 壢桃登跨", "HCB1 壢溪登跨", "HDB1 壢楊登跨", "HEB1 壢蘆登跨", "HFB1 壢德登跨", "HGB1 壢平登跨", "HHB1 壢山登跨"]
                },
                remote_remote_reg_case_code: {
                    label: "他所收件(跨所)",
                    options: ["HBA1 桃壢登跨", "HBC1 溪壢登跨", "HBD1 楊壢登跨", "HBE1 蘆壢登跨", "HBF1 德壢登跨", "HBG1 平壢登跨", "HBH1 山壢登跨"]
                },
                local_sur_case_code: {
                    label: "測量案件",
                    options: ["HB12 中地測丈", "HB13 中地測建", "HB17 中地法土", "HB18 中地法建"]
                },
                year: "",
                code: "",
                num: "",
                num_step: 10,
                num_min: 10,
                num_max: 999999,
                code_data: [],
                years: [],
                busy: false
            }
        },
        methods: {
            uiUpdate: function(e) {
                this.$emit("update", e, {
                    year: this.year,
                    code: this.code,
                    num: this.num
                });
            },
            getMaxNumber: function(e) {
                let year = this.year;
                let code = this.code;
                if (isEmpty(code) || isEmpty(year)) {
                    addNotification({message: "案件年或案件字為空白，無法取得案件目前最大號碼。", type: "warning"});
                    return;
                }
                this.busy = true;
                let that = this;
                this.$http.post(CONFIG.JSON_API_EP, {
                    "type": "max",
                    "year": year,
                    "code": code
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        // update UI
                        that.num = res.data.max;
                        that.uiUpdate(e);
                    } else {
                        addNotification({message: res.data.message, type: "warning"});
                    }
                    this.busy = false;
                }).catch(err => {
                    console.error("case-input-group-ui::getMaxNumber parsing failed", err);
                    showAlert({
                        title: "查詢最大號碼失敗",
                        subtitle: code,
                        message: err.message,
                        type: "danger"
                    });
                });
            },
            newCustomEvent: (name, val, target) => {
                let evt = new CustomEvent(name, {
                    detail: val,
                    bubbles: true
                });
                Object.defineProperty(evt, 'target', {writable: false, value: target});
                return evt;
            }
        },
        watch: {
            year: function(val) {
                let evt = this.newCustomEvent('year-updated', val, $(this.$el).find(`#${this.prefix}_case_update_year`)[0]);
                this.$emit("year-updated", evt);
            },
            code: function(val) {
                this.num_step = val == "HB12" || val == "HB17" ? 100 : 10;
                let evt = this.newCustomEvent('code-updated', val, $(this.$el).find(`#${this.prefix}_case_update_code`)[0]);
                this.$emit("code-updated", evt);
            },
            num: function(val) {
                let evt = this.newCustomEvent('num-updated', val, $(this.$el).find(`#${this.prefix}_case_update_num`)[0]);
                this.$emit("num-updated", evt);
            },
            busy: function(flag) {
                switch(flag) {
                    case true:
                        window.vueApp.busyOn(this.$el);
                        break;
                    default:
                        window.vueApp.busyOff(this.$el);
                }
            }
        },
        created: function() {
            // set year select options
            var d = new Date();
            this.year = (d.getFullYear() - 1911);
            let len = this.year - 105;
            for (let i = 0; i <= len; i++) {
                this.years.push({value: 105 + i, text: 105 + i});
            }
        },
        mounted: function(e) {
            switch(this.type) {
                case "reg":
                    this.code_data.push(this.local_reg_case_code);
                    this.code_data.push(this.remote_local_reg_case_code);
                    this.code_data.push(this.remote_remote_reg_case_code);
                    this.num_step = this.num_min = 10;
                    break;
                case "sur":
                    this.code_data.push(this.local_sur_case_code);
                    this.num_step = this.num_min = 100;
                    this.num = "000100";
                    break;
                case "sync":
                    this.code_data.push(this.remote_local_reg_case_code);
                    break;
                default:
                    this.code_data.push(this.local_reg_case_code);
                    this.code_data.push(this.remote_local_reg_case_code);
                    this.code_data.push(this.remote_remote_reg_case_code);
                    this.code_data.push(this.local_sur_case_code);
                    break;
            }
            // setup delay timer to allow cached data update to the input/select element
            let that = this;
            let mounted_el = $(this.$el);
            setTimeout(() => {
                that.year = mounted_el.find(`#${this.prefix}_case_update_year`).val();
                that.code = mounted_el.find(`#${this.prefix}_case_update_code`).val();
                that.num = mounted_el.find(`#${this.prefix}_case_update_num`).val();
                that.uiUpdate(e);
            }, 150);    // cache.js delay 100ms to wait Vue instance ready, so here delays 150ms
        }
    });
} else {
    console.error("vue.js not ready ... case-input-group-ui component can not be loaded.");
}
