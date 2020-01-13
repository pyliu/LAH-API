if (Vue) {
    Vue.component("case-input-group-ui", {
        template: `<b-form-row>
            <b-input-group size="sm" class="col-3">
                <b-input-group-append is-text>年</b-input-group-append>
                <b-form-select v-model="year" :options="years" @change="uiUpdate" @change="getMaxNumber" :id="prefix+'_case_update_year'">
                    <template v-slot:first>
                        <b-form-select-option :value="null" disabled>-- 請選擇年分 --</b-form-select-option>
                    </template>
                </b-form-select>
            </b-input-group>
            <b-input-group size="sm" class="col">
                <select v-model="code" @change="uiUpdate" @change="getMaxNumber" :id="prefix+'_case_update_code'" class="form-control w-100 h-100" data-trigger="manual" data-toggle="popover" data-content="請選擇案件字" title="案件字" data-placement="top" aria-label="字" :aria-describedby="'inputGroup-'+prefix+'_case_update_code'" required>
                    <option disabled>---- 請選擇案件字 ----</option>    
                    <optgroup v-for="obj in code_data" :label="obj.label">
                        <option v-for="item in obj.options" :value="item.replace(/[^A-Za-z0-9]/g, '')">{{item}}</option>
                    </optgroup>
                </select>
                <div class="input-group-append">
                    <span class="input-group-text" :id="'inputGroup-'+prefix+'_case_update_code'">字</span>
                </div>
            </b-input-group>
            <b-input-group size="sm" class="col-4">
                <input v-model="num" @input="uiUpdate" @keyup.enter="$emit('enter', $event)" type="number" :step="num_step" :min="num_min" max="999999" :id="prefix+'_case_update_num'" class="form-control w-100 h-100" aria-label="號" :aria-describedby="'inputGroup-'+prefix+'_case_update_num'" required data-trigger="manual" data-toggle="popover" data-content='案件號(最多6碼)' title='案件號' data-placement="top" />
                <div class="input-group-append">
                    <span class="input-group-text" :id="'inputGroup-'+prefix+'_case_update_num'">號</span>
                </div>
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
                code_data: [],
                years: []
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
                    addNotification({message: "案件年或案件字為空白，無法取得案件目前最大號碼。"});
                    return;
                }

                let body = new FormData();
                body.append("type", "max");
                body.append("year", year);
                body.append("code", code);
                let that = this;
                fetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(response => {
                    return response.json();
                }).then(jsonObj => {
                    if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        addNotification({
                            body: year + "年 " + code + " 最新案件號為 " + jsonObj.max
                        });
                        // update UI
                        that.num = jsonObj.max;
                        that.uiUpdate(e);
                    } else {
                        showAlert({message: jsonObj.message, type: "danger"});
                    }
                }).catch(ex => {
                    console.error("case-input-group-ui::getMaxNumber parsing failed", ex);
                    showAlert({message: "查詢最大號碼失敗~【" + code + "】", type: "danger"});
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
                let evt = this.newCustomEvent('code-updated', val, $(this.$el).find(`#${this.prefix}_case_update_year`)[0]);
                this.$emit("year-updated", evt);
            },
            code: function(val) {
                let evt = this.newCustomEvent('code-updated', val, $(this.$el).find(`#${this.prefix}_case_update_code`)[0]);
                this.$emit("code-updated", evt);
            },
            num: function(val) {
                let evt = this.newCustomEvent('code-updated', val, $(this.$el).find(`#${this.prefix}_case_update_num`)[0]);
                this.$emit("num-updated", evt);
            },
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
                this.year = mounted_el.find(`#${this.prefix}_case_update_year`).val();
                this.code = mounted_el.find(`#${this.prefix}_case_update_code`).val();
                this.num = mounted_el.find(`#${this.prefix}_case_update_num`).val();
                that.uiUpdate(e);
            }, 150);    // cache.js delay 100ms to wait Vue instance ready, so here delays 150ms
        }
    });
} else {
    console.error("vue.js not ready ... case-input-group-ui component can not be loaded.");
}
