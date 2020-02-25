/**
 * Land-Affairs-Helper(lah) Vue custom components
 */
/**
 * set axios defaults
 */
// PHP default uses QueryString as the parsing source but axios use json object instead
axios.defaults.transformRequest = [data => $.param(data)];
// add to all Vue instances
// https://vuejs.org/v2/cookbook/adding-instance-properties.html
Vue.prototype.$http = axios;
Vue.prototype.$gstore = (() => {
    if (typeof Vuex == "object") {
        return new Vuex.Store({
            state: {
                cache : {},
                isAdmin: false,
                userNames: null
            },
            getters: {
                cache: state => state.cache,
                isAdmin: state => state.isAdmin,
                userNames: state => state.userNames,
                userIDs: state => {
                    let reverseMapping = o => Object.keys(o).reduce((r, k) => Object.assign(r, { [o[k]]: (r[o[k]] || []).concat(k) }), {});
                    return reverseMapping(state.userNames);
                }
            },
            mutations: {
                cache(state, objPayload) {
                    state.cache = Object.assign(state.cache, objPayload);
                },
                isAdmin(state, flagPayload) {
                    state.isAdmin = flagPayload === true;
                },
                userNames(state, mappingPayload) {
                    state.userNames = mappingPayload || {};
                }
            }
        });
    }
    return {};
})();

// inject to all Vue instances
Vue.mixin({
    components: {
        "lah-transition": {
            template: `<transition
                :enter-active-class="animated_in"
                :leave-active-class="animated_out"
                :duration="duration"
                :mode="mode"
                :appear="appear"
                @enter="enter"
                @leave="leave"
                @after-enter="afterEnter"
                @after-leave="afterLeave"
            >
                <slot>轉場內容會顯示在這邊</slot>
            </transition>`,
            props: {
                appear: Boolean,
                fade: Boolean,
                slide: Boolean,
                slideDown: Boolean,
                slideUp: Boolean,
                zoom: Boolean,
                bounce: Boolean,
                rotate: Boolean
            },
            data: function() {
                return {
                    animated_in: "animated fadeIn once-anim-cfg",
                    animated_out: "animated fadeOut once-anim-cfg",
                    animated_opts: ANIMATED_TRANSITIONS,
                    duration: 400,   // or {enter: 400, leave: 800}
                    mode: "out-in",  // out-in, in-out
                    cfg_css: "once-anim-cfg"
                }
            },
            created() {
                if (this.rotate) {
                    this.animated_in = `animated rotateIn ${this.cfg_css}`;
                    this.animated_out = `animated rotateOut ${this.cfg_css}`;
                } else if (this.bounce) {
                    this.animated_in = `animated bounceIn ${this.cfg_css}`;
                    this.animated_out = `animated bounceOut ${this.cfg_css}`;
                } else if (this.zoom) {
                    this.animated_in = `animated zoomIn ${this.cfg_css}`;
                    this.animated_out = `animated zoomOut ${this.cfg_css}`;
                } else if (this.fade) {
                    this.animated_in = `animated fadeIn ${this.cfg_css}`;
                    this.animated_out = `animated fadeOut ${this.cfg_css}`;
                } else if (this.slideDown || this.slide) {
                    this.animated_in = `animated slideInDown ${this.cfg_css}`;
                    this.animated_out = `animated slideOutUp ${this.cfg_css}`;
                } else if (this.slideUp) {
                    this.animated_in = `animated slideInUp ${this.cfg_css}`;
                    this.animated_out = `animated slideOutDown ${this.cfg_css}`;
                } else {
                    this.randAnimation();
                }
            },
            methods: {
                enter: function(e) { this.$emit("enter", e); },
                leave: function(e) { this.$emit("leave", e); },
                afterEnter: function(e) { this.$emit("after-enter", e); },
                afterLeave: function(e) { this.$emit("after-leave", e); },
                rand: (range) => Math.floor(Math.random() * Math.floor(range || 100)),
                randAnimation: function() {
                    if (this.animated_opts) {
                        let count = this.animated_opts.length;
                        let this_time = this.animated_opts[this.rand(count)];
                        this.animated_in = `${this_time.in} ${this.cfg_css}`;
                        this.animated_out = `${this_time.out} ${this.cfg_css}`;
                    }
                }
            }
        },
        "lah-ban": {
            template: `<i class="text-danger fas fa-ban ld ld-breath" :class="[size]"></i>`,
            props: ["size"],
            data: function() { return { size: "" } },
            created() {
                switch(this.size) {
                    case "xs": this.size = "fa-xs"; break;
                    case "sm": this.size = "fa-sm"; break;
                    case "lg": this.size = "fa-lg"; break;
                    default:
                        if (this.size[this.size.length - 1] === "x") {
                            this.size = `fa-${this.size}`;
                        } else {
                            this.size = ""
                        }
                        break;
                }
            }
        }
    },
    data: function() { return {
        isBusy: false
    }},
    watch: {
        isBusy: function(flag) { flag ? this.busyOn(this.$el) : this.busyOff(this.$el); }
    },
    methods: {
        busy: (opts = {}) => {
            opts = Object.assign({
                selector: "body",
                style: "ld-over",   // ld-over, ld-over-inverse, ld-over-full, ld-over-full-inverse
                forceOff: false,
                forceOn: false
            }, opts);
            let container = $(opts.selector);
            if (container.length > 0) {
                let removeSpinner = function() {
                    container.removeClass(opts.style);
                    container.find(".auto-add-spinner").remove();
                    container.removeClass("running");
                }
                let addSpinner = function() {
                    container.addClass(opts.style);
                    container.addClass("running");
        
                    // randomize loading.io css for fun
                    let cover_el = $(jQuery.parseHTML('<div class="ld auto-add-spinner"></div>'));
                    cover_el.addClass(LOADING_PREDEFINED[rand(LOADING_PREDEFINED.length)])		// predefined pattern
                            .addClass(LOADING_SHAPES_COLOR[rand(LOADING_SHAPES_COLOR.length)]);	// color
                    switch(opts.size) {
                        case "md":
                            cover_el.addClass("fa-3x");
                            break;
                        case "lg":
                            cover_el.addClass("fa-5x");
                            break;
                        case "xl":
                            cover_el.addClass("fa-10x");
                            break;
                        default:
                            break;
                    }
                    container.append(cover_el);
                }
                if (opts.forceOff) {
                    removeSpinner();
                    return;
                }
                if (opts.forceOn) {
                    removeSpinner();
                    addSpinner();
                    return;
                }
                if (container.hasClass(opts.style)) {
                    removeSpinner();
                } else {
                    addSpinner();
                }
            }
        },
        busyOn: function(el = "body", size = "") { this.busy({selector: el, forceOn: true, size: size}) },
        busyOff: function(el = "body") { this.busy({selector: el, forceOff: true}) }
    }
});

Vue.component("lah-header", {
    template: `<lah-transition slide-down appear>
        <nav v-if="show" class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
            <i class="my-auto fas fa-2x text-light" :class="icon"></i>&ensp;
            <a class="navbar-brand my-auto" :href="location.href">{{leading}} <span class="small">(β)</span></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        
            <div class="collapse navbar-collapse" id="navbarsExampleDefault">
                <lah-transition appear>
                    <ul class="navbar-nav mr-auto">
                        <li v-for="link in links" :class="['nav-item', 'my-auto', active(link.url)]" v-show="link.need_admin ? $gstore.getters.isAdmin : true">
                            <a class="nav-link" :href="Array.isArray(link.url) ? link.url[0] : link.url">{{link.text}}</a>
                        </li>
                    </ul>
                </lah-transition>
            </div>
        </nav>
    </lah-transition>`,
    data: () => { return {
        show: true,
        icon: "fa-question",
        leading: "",
        links: [{
            text: "儀錶板",
            url: ["index.html", "/"],
            icon: "fa-columns",
            need_admin: true
        }, {
            text: "案件追蹤",
            url: "index.php",
            icon: "fa-list-alt",
            need_admin: true
        }, {
            text: "資料查詢",
            url: "query.php",
            icon: "fa-file-alt",
            need_admin: true
        }, {
            text: "監控修正",
            url: "watchdog.php",
            icon: "fa-user-secret",
            need_admin: true
        }, {
            text: "逾期案件",
            url: "overdue_reg_cases.html",
            icon: "fa-th-list",
            need_admin: false
        }, {
            text: "記錄檔",
            url: "tasklog.html",
            icon: "fa-dog",
            need_admin: true
        }, {
            text: "測試頁",
            url: "test.html",
            icon: "fa-charging-station",
            need_admin: true
        }]
    }},
    methods: {
        active: function(url) {
            return location.href.indexOf(url) > 0 ? 'active' : '';
        },
        setHeader: function(link) {
            let that = this;
            if (Array.isArray(link.url)) {
                link.url.forEach((this_url, idx) => {
                    if (location.href.indexOf(this_url) > 0) {
                        that.icon = link.icon;
                        that.leading = link.text;
                    }
                });
            } else if (location.href.indexOf(link.url) > 0) {
                that.icon = link.icon;
                that.leading = link.text;
            }
        }
    },
    mounted() {
        this.links.forEach(this.setHeader);
        // add pulse effect for the nav-item
        $(".nav-item").on("mouseenter", function(e) { addAnimatedCSS(this, {name: "pulse"}); });
    }
});

Vue.component("lah-footer", {
    template: `<lah-transition slide-up appear>
        <p v-if="show" :class="classes">
            <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!">
                <i class="fab fa-github fa-lg text-dark"></i>
            </a>
            <strong><i class="far fa-copyright"></i> <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
            <a href="https://vuejs.org/" target="_blank" title="Learn Vue JS!">
                <i class="text-success fab fa-vuejs fa-lg"></i>
            </a>
        </p>
    </lah-transition>`,
    data: function() {
        return {
            show: true,
            leave_time: 10000,
            classes: ['text-muted', 'fixed-bottom', 'my-2', 'mx-3', 'bg-white', 'border', 'rounded', 'text-center', 'p-2', 'small']
        }
    },
    mounted() {
        let that = this;
        setTimeout(() => that.show = false, this.leave_time);
    }
});

$(document).ready(() => {
    // dynamic add header/footer
    $("body").prepend($.parseHTML(`<div id="lah-header"><lah-header ref="header"></lah-header></div>`));
    window.vueLahHeader = new Vue({ el: "#lah-header" });
    $("body").append($.parseHTML(`<div id="lah-footer"><lah-footer ref="footer"></lah-footer></div>`));
    window.vueLahFooter = new Vue({ el: "#lah-footer" });
});
