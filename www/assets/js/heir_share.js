function isEmpty(variable) {
    if (variable === undefined || $.trim(variable) == "") {
        return true;
    }
    return false;
}

// other custom scripts start here
$(document).ready((e) => {
    window.vueApp = new Vue({
        el: "#app",
        data: {
            wizard: {
                s0: {
                    title: "步驟1，選擇事實發生區間",
                    seen: true,
                    value: "",
                    is76after: false
                },
                s01: {  // 光復前
                    title: "步驟2，家產 OR 私產？",
                    seen: false,
                    value: ""
                },
                s02: {   // 光復後
                    title: "步驟2，輸入各項目人數",
                    seen: false,
                    value: ""
                }
            },
            prev_step: {},
            now_step: {},
            VueOK: true,
            debug: ""
        },
        methods: {
            next: function(e) {
                switch(this.now_step) {
                    case this.wizard.s0:
                        console.log("next: Now on S0");
                        break;
                    case this.wizard.s01:
                        console.log("next: Now on S01");
                        break;
                    default:
                        break;
                }
            },
            prev: function(e) {
                this.debug = `PREV Clicked ${e.target.tagName}`;
                if (this.now_step !== this.wizard.s0 && this.prev_step.seen !== undefined) {
                    this.prev_step.seen = true;
                    this.now_step.seen = false;
                    this.now_step = this.prev_step;
                } 
            },
            s0ValueSelected: function(e) {
                switch(this.wizard.s0.value) {
                    case "before":
                        this.wizard.s0.seen = false;
                        this.wizard.s01.seen = true;
                        this.now_step = this.wizard.s01;
                        this.prev_step = this.wizard.s0;
                        console.log("S0: 光復前 selected");
                        break;
                    case "after":
                        console.log("S0: 光復後 selected");
                        console.log(`S0: 民國74年6月5日以後: ${this.wizard.s0.is76after}`);
                        break;
                    default:
                        console.error(`Not supported: ${this.wizard.s0.value}.`);
                }
                this.next.call(this, e);
            },
            s01ValueSelected: function(e) {
                switch(this.wizard.s01.value) {
                    case "public":
                        console.log(`S01: 家產 ${this.wizard.s01.value} selected`);
                        break;
                    case "private":
                        console.log(`S01: 私產 ${this.wizard.s01.value} selected`);
                        break;
                    default:
                        console.error(`Not supported: ${this.wizard.s01.value}.`);
                }
                this.next.call(this, e);
            }
        },
        mounted: function() {  // like jQuery ready
            $("#VueOK").toggleClass("d-none");
            this.now_step = this.wizard.s0;
        }
    });
});
