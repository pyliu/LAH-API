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
                s0: {   // 34年10月24日以前
                    title: "步驟1，選擇事實發生區間",
                    seen: true,
                    value: "",
                    children: {
                        s1_1: {
                            title: "步驟1-1，家產繼承",
                            seen: false,
                            value: ""
                        },
                        s1_2: {
                            title: "步驟1-2，私產繼承",
                            seen: false,
                            value: ""
                        }
                    }
                },
                s1: {

                },
                s2: {   // 74年6月4日以前

                },
                s3: {   // 74年6月5日以後

                }
            },
            prev_step: {},
            now_step: {},
            VueOK: true,
            debug: ""
        },
        methods: {
            next: function(e) {
                this.debug = `NEXT triggered. ${e.target.tagName}`;
            },
            prev: function(e) {
                this.debug = `PREV Clicked ${e.target.tagName}`;
                if (this.prev_step.seen !== undefined) {
                    this.prev_step.seen = true;
                    this.now_step.seen = false;
                    this.now_step = this.prev_step;
                } 
            },
            s1ValueSelected: function(e) {
                switch(this.wizard.s1.value) {
                    case "0":
                        this.wizard.s0.seen = false;
                        this.wizard.s1.children.s1_1.seen = true;
                        this.now_step = this.wizard.s1.children.s1_1;
                        this.prev_step = this.wizard.s1;
                        break;
                    default:
                        console.error(`Wrong value selected ${this.wizard.s1.value}.`);
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
