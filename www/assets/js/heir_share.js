function isEmpty(variable) {
    if (variable === undefined || $.trim(variable) == "") {
        return true;
    }
    return false;
}

let showModal = opts => {
	let body = opts.body;
	let title = opts.title;
	let size = opts.size;	// sm, md, lg, xl
	let callback = opts.callback;
	if (isEmpty(title)) {
		title = "... 請輸入指定標題 ...";
	}
	if (isEmpty(body)) {
		body = "... 請輸入指定內文 ...";
	}
	if (isEmpty(size)) {
		size = "md";
	}
	
    let modal_element = $("#bs_modal_template");
    if (modal_element.length == 0) {
        initModalUI();
        modal_element = $("#bs_modal_template");
    }
	
	// Try to use Vue.js
	window.modalApp.title = title;
	window.modalApp.body = body;
	window.modalApp.sizeClass = "modal-" + size;
	window.modalApp.optsClass = opts.class || "";

	if (typeof callback == "function") {
		modal_element.one('shown.bs.modal', callback);
	}

	// backdrop: 'static' => not close by clicking outside dialog
	modal_element.modal({backdrop: 'static'});
}

let closeModal = callback => {
	if (typeof callback == "function") {
		$("#bs_modal_template").one('hidden.bs.modal', callback);
	}
	$("#bs_modal_template").modal("hide");
}

let initModalUI = () => {
	// add modal element to show the popup html message
	if ($("#bs_modal_template").length == 0) {
		$("body").append($.parseHTML(`
			<div class="modal fade" id="bs_modal_template" tabindex="-1" role="dialog" aria-labelledby="bs_modal_template" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" v-bind:class="sizeClass" role="document">
					<div class="modal-content">
						<com-header :in-title="title"></com-header>
						<com-body :in-body="body" :in-opts-class="optsClass"></com-body>
						<com-footer></com-footer>
					</div>
				</div>
			</div>
		`));
		// Try to use Vue.js
		window.modalApp = new Vue({
			el: '#bs_modal_template',
			data: {
				body: 'Hello Vue!',
				title: 'Hello Vue!',
				sizeClass: 'modal-md',
				optsClass: ''
			},
			components: {
				"com-header": {
					props: ["inTitle"],
					template: `<div class="modal-header">
						<h4 class="modal-title"><span v-html="inTitle"></span></h4>
						<button type="button" class="close" data-dismiss="modal">&times;</button>
					</div>`
				},
				"com-body": {
					props: ["inOptsClass", "inBody"],
					template: `<div class="modal-body" :class="inOptsClass">
					<p><span v-html="inBody"></span></p>
				</div>`
				},
				"com-footer": {
					template: `<div class="modal-footer">
						<button type="button" class="btn btn-light" data-dismiss="modal">關閉</button>
					</div>`
				}
			}
		});
	}
}

// other custom scripts start here
$(document).ready((e) => {
    window.vueApp = new Vue({
        el: "#app",
        data: {
            wizard: {
                s0: {
                    title: "步驟1，選擇事實發生區間",
                    legend: "被繼承人死亡時間",
                    seen: true,
                    value: ""
                },
                s1: {  // 光復前
                    title: "步驟2，繼承財產分類",
                    legend: "被繼承財產種類",
                    seen: false,
                    value: "",
                    public: { count: 0 },
                    private: {
                        child: 0,
                        spouse: 0,
                        parent: 0,
                        household: 0
                    }
                },
                s2: {   // 光復後
                    title: "步驟2，選擇輸入繼承人數",
                    legend: "TODO",
                    seen: false,
                    value: ""
                }
            },
            heir_denominator: 1,
            prev_step: {},
            now_step: {},
            breadcrumb: [],
            VueOK: true,
            debug: true
        },
        computed: {

        },
        components: {
            "counter-input": {
                props: [ "max", "min" ],
                data: function() {
                    return {
                        // parent use v-model syntax to get the value changed count
                        count: 0
                    }
                },
                template: `<span class="qty">
                    <span class="minus bg-dark" @click="minusClick">-</span>
                    <input type="number" class="count" value="0" :value="count" readonly>
                    <span class="plus bg-dark" @click="plusClick">+</span>
                </span>`,
                methods: {
                    minusClick: function(e) {
                        if (this.min) {
                            if (this.count > this.min) { this.count--; }
                        } else {
                            // default limit the min to 0
                            if (this.count > 0) { this.count--; }
                        }
                        
                        // To emit input event to parent => v-model will use the event to update the value watched. 
                        this.$emit('input', this.count);
                    },
                    plusClick: function(e) {
                        if (this.max) {
                            if (this.count < this.max) { this.count++; }
                        } else {
                            this.count++;
                        }
                        this.$emit('input', this.count);
                    }
                },
            }
        },
        methods: {
            next: function(e) {
                this.debug = `next triggered ${e.target.tagName}`;
                switch(this.now_step) {
                    case this.wizard.s0:
                        console.log("next: Now on S0");
                        this.s0ValueSelected.call(this, e);
                        break;
                    case this.wizard.s1:
                        console.log("next: Now on s1");
                        this.s1ValueSelected.call(this, e);
                        break;
                    default:
                        break;
                }
            },
            prev: function(e) {
                this.debug = `prev triggered ${e.target.tagName}`;
                if (this.breadcrumb.length > 2) {
                    this.prev_step = this.breadcrumb.pop();
                    this.now_step = this.breadcrumb[this.breadcrumb.length - 1];
                    this.prev_step.seen = false;
                    this.now_step.seen = true;
                } 
            },
            filter: function(e) {
                let val = e.target.value.replace(/[^0-9]/g, "");    // remove non-digit chars
                val = val.replace(/^0+/, '');   // remove leading zero
                this.heir_denominator = val || 1;
            },
            s0ValueSelected: function(e) {
                this.prev_step = this.breadcrumb[this.breadcrumb.length - 1];
                switch(this.wizard.s0.value) {
                    case -1:
                        this.now_step = this.wizard.s1;
                        this.now_step.legend = "光復前【民國34年10月24日以前】";
                        break;
                    case 0:
                    case 1:
                        this.now_step = this.wizard.s2;
                        this.now_step.legend = '光復後【民國74年6月' + (this.wizard.s0.value == 1 ? "5日以後】" : "4日以前】");
                        break;
                    default:
                        console.error(`Not supported: ${this.wizard.s0.value}.`);
                        showModal({
                            title: this.now_step.title,
                            body: "請選擇事實發生區間！",
                            size: "md"
                        });
                        return;
                }
                // hide all steps first
                for (let step in this.wizard) {
                    this.wizard[step].seen = false;
                }
                this.now_step.seen = true;
                this.breadcrumb.push(this.now_step);
            },
            s1ValueSelected: function(e) {
                switch(this.wizard.s1.value) {
                    case "public":
                        console.log(`s1: 家產 ${this.wizard.s1.value} selected`);
                        break;
                    case "private":
                        console.log(`s1: 私產 ${this.wizard.s1.value} selected`);
                        break;
                    default:
                        console.error(`Not supported: ${this.wizard.s1.value}.`);
                        showModal({
                            title: this.now_step.title,
                            body: "請選擇【家產】或【私產】！",
                            size: "md"
                        });
                        return;
                }
            }
        },
        mounted: function() {  // like jQuery ready
            $("#VueOK").toggleClass("d-none");
            this.now_step = this.wizard.s0;
            this.breadcrumb.push({
                legend: "/　"
            });
            this.breadcrumb.push(this.wizard.s0);
        }
    });
});
