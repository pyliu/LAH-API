if (Vue) {
    Vue.component("case-reg-detail", {
        template: `<div>
            <p v-html="jsonObj.tr_html"></p>
            <b-row>
                <b-col cols="4">
                    <span v-show="jsonObj.跨所 == 'Y'" class='bg-info text-white rounded p-1'>跨所案件 ({{jsonObj.資料收件所}} => {{jsonObj.資料管轄所}})</span><br />
                    收件字號：<a
                        :title="'案件辦理情形 on ' + ap_server"
                        :href="ap_url"
                        target="_blank"
                    >{{jsonObj.收件字號}}</a> <br />
                    <div v-show="is_close" class='text-danger'><strong>尚未結案！</strong></div>
                    收件時間：{{jsonObj.收件時間}} <br/>
                    測量案件：{{jsonObj.測量案件}} <br/>
                    限辦期限：{{jsonObj.限辦期限}} <br/>
                    作業人員：<span id='the_incase_operator_span' class='user_tag' data-display-selector='#in_modal_display' :data-id="jsonObj.作業人員ID" :data-name="jsonObj.作業人員">{{jsonObj.作業人員}}</span> <br/>
                    辦理情形：{{jsonObj.辦理情形}} <br/>
                    登記原因：{{jsonObj.登記原因}} <br/>
                    區域：{{area}}【{{jsonObj.raw.RM10}}】 <br/>
                    段小段：{{jsonObj.段小段}}【{{jsonObj.段代碼}}】 <br/>
                    地號：{{jsonObj.地號}} <br/>
                    建號：{{jsonObj.建號}} <br/>
                    件數：{{jsonObj.件數}} <br/>
                    登記處理註記：{{jsonObj.登記處理註記}} <br/>
                    地價處理註記：{{jsonObj.地價處理註記}} <br/>
                    權利人統編：{{jsonObj.權利人統編}} <br/>
                    權利人姓名：{{jsonObj.權利人姓名}} <br/>
                    義務人統編：{{jsonObj.義務人統編}} <br/>
                    h義務人姓名：{{jsonObj.義務人姓名}} <br/>
                    義務人人數：{{jsonObj.義務人人數}} <br/>
                    代理人統編：{{jsonObj.代理人統編}} <br/>
                    代理人姓名：{{jsonObj.代理人姓名}} <br/>
                    手機號碼：{{jsonObj.手機號碼}}
                </b-col>
                <b-col id="in_modal_display" cols="8">
                </b-col>
            </b-row>
        </div>`,
        props: ["jsonObj", "enabled_userinfo"],
        data: () => {
            return {
                area: "",
                rm10: null,
                ap_server: "220.1.35.123",
                ap_url: "",
                is_close: false
            }
        },
        created: function(e) {
            this.rm10 = this.jsonObj.raw.RM10 ? this.jsonObj.raw.RM10 : "XX";
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
            this.ap_url = `http://${this.ap_server}:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=${this.jsonObj.raw["RM01"]}&word=${this.jsonObj.raw["RM02"]}&code=${this.jsonObj.raw["RM03"]}&sdlyn=N&RM90=`;
            this.is_close = !isEmpty(this.jsonObj.結案已否);
        },
        mounted: function(e) {
            if (this.enabled_userinfo) {
                addUserInfoEvent();
                //load current operator user info
                $("#the_incase_operator_span").trigger("click");
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-reg-detail component can not be loaded.");
}
