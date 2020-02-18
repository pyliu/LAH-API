if (Vue) {
    Vue.component("case-query-id", {
        template: `<fieldset>
            <legend>法院來函查統編</legend>
            <b-form-row>
                <b-input-group size="sm" class="col-8">
                    <b-form-select v-model="year" :options="years" @change="uiUpdate" @change="getMaxNumber" :id="prefix+'_case_update_year'">
                        <template v-slot:first>
                            <b-form-select-option :value="null" disabled>-- 請選擇年份 --</b-form-select-option>
                        </template>
                    </b-form-select>
                    <b-input-group-append is-text>年</b-input-group-append>
                </b-input-group>
                <b-col><b-button pill block @click="check" size="sm" variant="outline-primary"><i class="fas fa-cogs"></i> 檢測</b-button></b-col>
                <b-col><b-button pill block @click="showNote" size="sm" variant="outline-success" class="col"><i class="far fa-comment"></i> 備註</b-button></b-col>
            </b-form-row>
        </fieldset>`,
        methods: {
            showNote: function(e) {
                showModal({
                    title: "跨所註記遺失檢測 小幫手提示",
                    body: `<div class="d-block">
                        -- 【法院來函查統編】MOICAS_CRSMS 土地登記案件查詢-權利人+義務人+代理人+複代 <br/>
                        SELECT t.* <br/>
                        &emsp;FROM MOICAS.CRSMS t <br/>
                        WHERE t.RM18 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM21 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM24 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.RM25 = 'H221350201'; <br/>
                        <br/>
                        -- 【法院來函查統編】MOICAS_CMSMS 測量案件資料查詢-申請人+代理人+複代 <br/>
                        SELECT t.* <br/>
                        &emsp;FROM MOICAS.CMSMS t <br/>
                        WHERE t.MM13 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.MM17_1 = 'H221350201' <br/>
                        &emsp;&emsp;OR t.MM17_2 = 'H221350201';
                    </div>`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-query-id component can not be loaded.");
}
