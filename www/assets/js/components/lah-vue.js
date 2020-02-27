/**
 * Land-Affairs-Helper(lah) Vue custom components
 */
Vue.config.devtools = true;
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
                userNames: null,
                initialized: false
            },
            getters: {
                initialized: state => state.initialized,
                cache: state => state.cache,
                isAdmin: state => state.isAdmin,
                userNames: state => state.userNames
            },
            mutations: {
                initialized(state, flagPayload) {
                    state.initialized = flagPayload === true;
                },
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
        isBusy: function(flag) { flag ? this.busyOn(this.$el) : this.busyOff(this.$el) }
    },
    computed: {
        cache() { return this.$gstore.getters.cache; },
        isAdmin() { return this.$gstore.getters.isAdmin; },
        userNames() { return this.$gstore.getters.userNames; },
        userIDs() { return this.reverseMapping(this.userNames || {}); },
        initialized() { return this.$gstore.getters.initialized; }
    },
    methods: {
        reverseMapping: o => Object.keys(o).reduce((r, k) => Object.assign(r, { [o[k]]: (r[o[k]] || []).concat(k) }), {}),
        toggleBusy: (opts = {}) => {
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
        busyOn: function(el = "body", size = "") { this.toggleBusy({selector: el, forceOn: true, size: size}) },
        busyOff: function(el = "body") { this.toggleBusy({selector: el, forceOff: true}) }
    }
});

Vue.component("lah-alert", {
    template: `<div id="bs_alert_template">
        <lah-transition
            @enter="enter"
            @leave="leave"
            @after-enter="afterEnter"
            @after-leave="afterLeave"
        >
            <div v-show="seen" class="alert alert-dismissible alert-fixed shadow" :class="type" role="alert" @mouseover="mouseOver" @mouseout="mouseOut">
                <div v-show="title != '' && typeof title == 'string'" class="d-flex w-100 justify-content-between">
                    <h6 v-html="title"></h6>
                    <span v-if="subtitle != ''" v-html="subtitle" style="font-size: .75rem"></span>
                    <span style="font-size: .75rem">{{remaining_secs}}s</span>
                </div>
                <hr v-show="title != '' && typeof title == 'string'" class="mt-0 mb-1">
                <p v-html="message" style="font-size: .9rem"></p>
                <button type="button" class="close" @click="seen = false">
                    <span aria-hidden="true">&times;</span>
                </button>
                <b-progress height="3px" :max="delay" :variant="bar_variant" :value="remaining_delay"></b-progress>
            </div>
        </lah-transition>
    </div>`,
    data: function() { return {
        title: "",
        subtitle: "",
        message: 'Hello Alert Vue!',
        type: 'alert-warning',
        seen: false,
        hide_timer_handle: null,
        progress_timer_handle: null,
        progress_counter: 1,
        autohide: true,
        delay: 10000,
        anim_delay: 400,
        remaining_delay: 10000,
        remaining_secs: 10,
        remaining_percent: 100,
        bar_variant: "light"
    }},
    methods: {
        mouseOver: function(e) {
            if (this.hide_timer_handle !== null) { clearTimeout(this.hide_timer_handle); }
            this.disableProgress();
        },
        mouseOut: function(e) {
            if (this.autohide) {
                this.hide_timer_handle = setTimeout(() => {
                    this.seen = false;
                    this.hide_timer_handle = null;
                }, this.delay);
                this.enableProgress();
            }
        },
        enableProgress: function() {
            this.disableProgress();
            let that = this;
            let total_remaining_secs = that.delay / 1000;
            this.progress_timer_handle = setInterval(function() {
                that.remaining_delay -= 200;
                let now_percent = ++that.progress_counter / (that.delay / 200.0);
                that.remaining_percent = (100 - Math.round(now_percent * 100));
                if (that.remaining_percent > 50) {
                } else if (that.remaining_percent > 25) {
                    that.bar_variant = "warning";
                } else {
                    that.bar_variant = "danger";
                }
                that.remaining_secs = total_remaining_secs - Math.floor(total_remaining_secs * now_percent);
            }, 200);
        },
        disableProgress: function() {
            clearTimeout(this.progress_timer_handle);
            this.progress_counter = 1;
            this.remaining_delay = this.delay;
            this.remaining_secs = this.delay / 1000;
            this.remaining_percent = 100;
            this.bar_variant = "light";
        },
        show: function(opts) {
            if (this.seen) {
                this.seen = false;
                // the slide up animation is 0.4s
                setTimeout(() => this.setData(opts), this.anim_delay);
            } else {
                this.setData(opts);
            }
        },
        setData: function(opts) {
            // normal usage, you want to attach event to the element in the alert window
            if (typeof opts.callback == "function") {
                setTimeout(opts.callback, this.anim_delay);
            }
            switch (opts.type) {
                case "danger":
                case "red":
                    opts.type = "alert-danger";
                    break;
                case "warning":
                case "yellow":
                    opts.type = "alert-warning";
                    break;
                case "success":
                case "green":
                    opts.type = "alert-success";
                    break;
                case "dark":
                    opts.type = "alert-dark";
                    break;
                case "info":
                    opts.type = "alert-info";
                    break;
                case "primary":
                    opts.type = "alert-primary";
                    break;
                case "secondary":
                    opts.type = "alert-secondary";
                    break;
                default:
                    opts.type = "alert-light";
                    break;
            }
            this.title = opts.title || "";
            this.subtitle = opts.subtitle || "";
            this.autohide = opts.autohide || true;
            this.message = opts.message;
            this.type = opts.type;
            this.seen = true;
        },
        randAnimation: function() {
            if (this.animated_opts) {
                let count = this.animated_opts.length;
                let this_time = this.animated_opts[rand(count)];
                this.animated_in = `${this_time.in} once-anim-cfg`;
                this.animated_out = `${this_time.out} once-anim-cfg`;
            }
        },
        enter: function() { },
        leave: function() { /*this.randAnimation();*/ },
        afterEnter: function() {
            // close alert after 15 secs (default)
            if (this.autohide) {
                let that = this;
                if (this.hide_timer_handle !== null) { clearTimeout(this.hide_timer_handle); }
                this.hide_timer_handle = setTimeout(() => {
                    that.seen = false;
                    that.hide_timer_handle = null;
                }, this.delay);
                this.enableProgress();
            }
        },
        afterLeave: function() {
            this.disableProgress();
        }
    },
    created: function() {
        this.randAnimation();
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
                        <li v-for="link in links" :class="['nav-item', 'my-auto', active(link.url)]" v-show="link.need_admin ? isAdmin : true">
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
            icon: "fa-th-large",
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
    // dynamic add header/footer/alert
    let dynamic_comps = $.parseHTML(`<div id="global-dynamic-components">
        <lah-header ref="header"></lah-header>
        <lah-footer ref="footer"></lah-footer>
        <lah-alert ref="alert"></lah-alert>
    </div>`);
    let target = $("#main_content_section");
    if (target.length == 0) {
        $("body").prepend(dynamic_comps);
        window.dynaApp = new Vue({ el: "#global-dynamic-components" });
    } else {
        target.prepend(dynamic_comps);
    }
    
    // main app for whole page, use window.vueApp to get it
    window.vueApp = new Vue({
        el: "#main_content_section",
        data: {
            toastCounter: 0,
            openConfirm: false,
            confirmAnswer: false,
            transition: ANIMATED_TRANSITIONS[rand(ANIMATED_TRANSITIONS.length)],
            callbackQueue: []
        },
        created: function(e) {
            this.$root.$on('bv::modal::show', (bvEvent, modalId) => {
                //console.log('Modal is about to be shown', bvEvent, modalId)
            });
            this.$root.$on('bv::modal::shown', (bvEvent, modalId) => {
                //console.log('Modal is shown', bvEvent, modalId)
                if (!this.openConfirm) {
                    this.showModal(modalId);
                }
            });
            this.$root.$on('bv::modal::hide', (bvEvent, modalId) => {
                //console.log('Modal is about to hide', bvEvent, modalId)
                // animation will break confirm Promise, so skip it
                if (this.openConfirm) {
                    this.openConfirm = false;
                } else {
                    bvEvent.preventDefault();
                    this.hideModal(modalId);
                }
            });
            this.$root.$on('bv::modal::hidden', (bvEvent, modalId) => {
                //console.log('Modal is hidden', bvEvent, modalId)
            });
            
            console.assert(this.$gstore, "Vuex store is not ready, did you include vuex.js in the page??");
            if (!this.initialized) {
                this.$gstore.commit("initialized", true);
                this.screensaver();
                this.authenticate();
                this.loadUserNames();
            }
        },
        methods: {
            // make simple, short popup notice message
            makeToast: function(message, opts = {}) {
                // for sub-title
                var currentdate = new Date();
                var datetime = ("0" + currentdate.getHours()).slice(-2) + ":" +
                    ("0" + currentdate.getMinutes()).slice(-2) + ":" +
                    ("0" + currentdate.getSeconds()).slice(-2);
                // position adapter
                switch(opts.pos) {
                    case "tr":
                        opts.toaster = "b-toaster-top-right";
                        break;
                    case "tl":
                        opts.toaster = "b-toaster-top-left";
                        break;
                    case "br":
                        opts.toaster = "b-toaster-bottom-right";
                        break;
                    case "bl":
                        opts.toaster = "b-toaster-bottom-left";
                        break;
                    case "tc":
                        opts.toaster = "b-toaster-top-center";
                        break;
                    case "tf":
                        opts.toaster = "b-toaster-top-full";
                        break;
                    case "bc":
                        opts.toaster = "b-toaster-bottom-center";
                        break;
                    case "bf":
                        opts.toaster = "b-toaster-bottom-full";
                        break;
                    default:
                        opts.toaster = "b-toaster-bottom-right";
                }
                // merge default setting
                let merged = Object.assign({
                    title: "通知",
                    subtitle: datetime,
                    href: "",
                    noAutoHide: false,
                    autoHideDelay: 5000,
                    solid: true,
                    toaster: "b-toaster-bottom-right",
                    appendToast: true,
                    variant: "default"
                }, opts);
                // Use a shorter name for this.$createElement
                const h = this.$createElement
                // Create the title
                let vNodesTitle = h(
                    'div',
                    { class: ['d-flex', 'flex-grow-1', 'align-items-baseline', 'mr-2'] },
                    [
                    h('strong', { class: 'mr-2' }, merged.title),
                    h('small', { class: 'ml-auto text-italics' }, merged.subtitle)
                    ]
                );
                // Pass the VNodes as an array for title
                merged.title = [vNodesTitle];
                // use vNode for HTML content
                const msgVNode = h('div', { domProps: { innerHTML: message } });
                this.$bvToast.toast([msgVNode], merged);
    
                if (typeof merged.callback === 'function') {
                    let that = this;
                    setTimeout(() => merged.callback.apply(that, arguments), 100);
                }
                this.toastCounter++;
            },
            showModal: function(id, duration) {
                let modal_content = $(`#${id} .modal-content`);
                modal_content.removeClass("hide");
                addAnimatedCSS(modal_content, {
                    name: this.transition.in,
                    duration: duration || "once-anim-cfg",
                    callback: this.callbackQueue.pop()
                });
            },
            hideModal: function(id) {
                let that = this;
                if (id == "" || id == undefined || id == null) {
                    $('div.modal.show').each(function(idx, el) {
                        that.removeModal(el.id);
                    });
                } else {
                    that.removeModal(id);
                }
            },
            removeModal: function(id, duration) {
                if (!this.openConfirm) {
                    let modal_content = $(`#${id} .modal-content`);
                    addAnimatedCSS(modal_content, {
                        name: this.transition.out,
                        duration: duration || "once-anim-cfg",
                        callback: () => {
                            $(`#${id}___BV_modal_outer_`).remove();
                            $(".popover").remove();
                        }
                    });
                }
            },
            modal: function(message, opts) {
                let merged = Object.assign({
                    title: '訊息',
                    size: 'md',
                    buttonSize: 'sm',
                    okVariant: 'outline-secondary',
                    okTitle: '關閉',
                    hideHeaderClose: false,
                    centered: true,
                    scrollable: true,
                    hideFooter: true,
                    noCloseOnBackdrop: true,
                    contentClass: "shadow hide", // add hide class to .modal-content then use Animated.css for animation show up
                    html: false
                }, opts);
                // use d-none to hide footer
                merged.footerClass = merged.hideFooter ? "d-none" : "p-2";
                if (merged.html) {
                    merged.titleHtml = merged.title;
                    merged.title = undefined;
                    if (typeof message == "object") {
                        // assume the message is VNode
                        this.$bvModal.msgBoxOk([message], merged);
                    } else {
                        const h = this.$createElement;
                        const msgVNode = h('div', { domProps: { innerHTML: message } });
                        this.$bvModal.msgBoxOk([msgVNode], merged);
                    }
                    // to initialize Vue component purpose
                    if (merged.callback && typeof merged.callback == "function") {
                        this.callbackQueue.push(merged.callback);
                    }
                } else {
                    this.$bvModal.msgBoxOk(message, merged);
                }
            },
            confirm: function(message, opts) {
                this.confirmAnswer = false;
                this.openConfirm = true;
                let merged = Object.assign({
                    title: '請確認',
                    size: 'sm',
                    buttonSize: 'sm',
                    okVariant: 'outline-success',
                    okTitle: '確定',
                    cancelVariant: 'secondary',
                    cancelTitle: '取消',
                    footerClass: 'p-2',
                    hideHeaderClose: false,
                    noCloseOnBackdrop: false,
                    centered: true,
                    contentClass: "shadow"
                }, opts);
                // use HTML content
                const h = this.$createElement;
                const msgVNode = h('div', { domProps: { innerHTML: message } });
                this.$bvModal.msgBoxConfirm([msgVNode], merged)
                .then(value => {
                    this.confirmAnswer = value;
                    if (this.confirmAnswer && merged.callback && typeof merged.callback == "function") {
                        merged.callback.apply(this, arguments);
                    }
                }).catch(err => {
                    console.error(err);
                });
            },
            open: function(url, e) {
                let h = window.innerHeight - 160;
                this.modal(`<iframe src="${url}" class="w-100" height="${h}" frameborder="0"></iframe>`, {
                    title: e.target.title || `外部連結`,
                    size: "xl",
                    html: true,
                    noCloseOnBackdrop: false
                });
            },
            download: function(url, data) {
                let params = Object.assign({
                    filename: "you_need_to_specify_filename.xxx"
                }, data || {});
                this.$http.post(url, params, {
                    responseType: 'blob'    // important
                }).then((response) => {
                    const url = window.URL.createObjectURL(new Blob([response.data]));
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', params.filename);
                    document.body.appendChild(link);
                    link.click();
                    //afterwards we remove the element again
                    link.remove();
                    // release object in memory
                    window.URL.revokeObjectURL(url);
                });                  
            },
            fetch: async function(url, opts) {
                opts = Object.assign({
                    method: "POST",
                    body: new FormData(),
                    blob: false
                }, opts);
                let response = await fetch(url, opts);
                return opts.blob ? await response.blob() : await response.json();
            },
            fetchRegCase: function(e, enabled_userinfo = false) {
                // ajax event binding
                let clicked_element = $(e.target);
                // remove additional characters for querying
                let id = trim(clicked_element.text());
    
                let that = this;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    that.showRegCase(res.data, enabled_userinfo);
                }).catch(err => {
                    console.error("window.vueApp.fetchRegCase parsing failed", err);
                    showAlert({
                        title: "擷取登記案件",
                        subtitle: id,
                        message: err.message,
                        type: "danger"
                    });
                });
            },
            showRegCase: function(jsonObj, enabled_userinfo = false) {
                if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL || jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                    showAlert({title: "顯示登記案件詳情", message: jsonObj.message, type: "danger"});
                    return;
                } else {
                    showModal({
                        message: this.$createElement("case-reg-detail", {
                            props: {
                                jsonObj: jsonObj,
                                enabled_userinfo: this.isAdmin
                            }
                        }),
                        title: "登記案件詳情",
                        size: this.isAdmin ? "xl" : "lg"
                    });
                }
            },
            fetchUserInfo: function(e) {
                if (CONFIG.DISABLE_MSDB_QUERY) {
                    console.warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping vueApp.fetchUserInfo.");
                    return;
                }
            
                let clicked_element = $(e.target);
                if (!clicked_element.hasClass("user_tag")) {
                    clicked_element = $(clicked_element.closest(".user_tag"));
                }
                // retrieve name/id from the data-* attributes
                let name = $.trim(clicked_element.data("name"));
                if (name) {
                    name = name.replace(/[\?A-Za-z0-9\+]/g, "");
                }
                let id = trim(clicked_element.data("id"));
                if (isEmpty(name) && isEmpty(id)) {
                    console.warn("Require query params are all empty, skip dynamic user info querying. (add attr to the element => data-id=" + id + ", data-name=" + name + ")");
                    return;
                }
            
                // use data-el HTML attribute to specify the display container, empty will use the modal popup window instead.
                let el_selector = clicked_element.data("display-selector");
            
                // reduce user query traffic
                if (this.cachedUserInfo(id, name, el_selector)) {
                    return;
                }
                
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "user_info",
                    name: name,
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        let latest = res.data.data_count - 1;
                        this.showUserInfoFromRAW(res.data.raw[latest], el_selector);
                        // cache to global store
                        let json_str = JSON.stringify(res.data);
                        let payload = {};
                        if (!isEmpty(id)) { payload[id] = json_str; }
                        if (!isEmpty(name)) { payload[name] = json_str; }
                        this.$gstore.commit('cache', payload);
                    } else {
                        addNotification({ message: `找不到 '${name} ${id}' 資料` });
                    }
                }).catch(err => {
                    console.error("window.vueApp.fetchUserInfo parsing failed", err.toJSON());
                    showAlert({ title: "查詢使用者資訊", message: err.message, type: "danger" });
                });
            },
            cachedUserInfo: function (id, name, selector) {
                // reduce user query traffic
                let json_str = this.cache[id] || this.cache[name];
                if (!isEmpty(json_str)) {
                    console.log(`cache hit ${id}:${name} in store.`);
                    let jsonObj = JSON.parse(json_str);
                    let latest = jsonObj.data_count - 1;
                    this.showUserInfoFromRAW(jsonObj.raw[latest], selector);
                    return true;
                }
                return false;
            },
            showUserInfoFromRAW: function(tdoc_raw, selector = undefined) {
                let year = 31536000000;
                let now = new Date();
                let age = "";
                let birth = tdoc_raw["AP_BIRTH"];
                let birth_regex = /^\d{3}\/\d{2}\/\d{2}$/;
                if (birth.match(birth_regex)) {
                    birth = (parseInt(birth.substring(0, 3)) + 1911) + birth.substring(3);
                    let temp = Date.parse(birth);
                    if (temp) {
                        let born = new Date(temp);
                        let badge_age = ((now - born) / year).toFixed(1);
                        if (badge_age < 30) {
                            age += " <b-badge variant='success' pill>";
                        } else if (badge_age < 40) {
                            age += " <b-badge variant='primary' pill>";
                        } else if (badge_age < 50) {
                            age += " <b-badge variant='warning' pill>";
                        } else if (badge_age < 60) {
                            age += " <b-badge variant='danger' pill>";
                        } else {
                            age += " <b-badge variant='dark' pill>";
                        }
                        age += badge_age + "歲</b-badge>"
                    }
                }
    
                let on_board_date = "";
                if(!isEmpty(tdoc_raw["AP_ON_DATE"])) {
                    on_board_date = tdoc_raw["AP_ON_DATE"].date ? tdoc_raw["AP_ON_DATE"].date.split(" ")[0] :　tdoc_raw["AP_ON_DATE"];
                    let temp = Date.parse(on_board_date.replace('/-/g', "/"));
                    if (temp) {
                        let on = new Date(temp);
                        if (tdoc_raw["AP_OFF_JOB"] == "Y") {
                            let off_board_date = tdoc_raw["AP_OFF_DATE"];
                            off_board_date = (parseInt(off_board_date.substring(0, 3)) + 1911) + off_board_date.substring(3);
                            temp = Date.parse(off_board_date.replace('/-/g', "/"));
                            if (temp) {
                                // replace now Date to off board date
                                now = new Date(temp);
                            }
                        }
                        let work_age = ((now - on) / year).toFixed(1);
                        if (work_age < 5) {
                            on_board_date += " <b-badge variant='success'>";
                        } else if (work_age < 10) {
                            on_board_date += " <b-badge variant='primary'>";
                        } else if (work_age < 20) {
                            on_board_date += " <b-badge variant='warning'>";
                        } else {
                            on_board_date += " <b-badge variant='danger'>";
                        }
                        on_board_date +=  work_age + "年</b-badge>";
                    }
                }
                let vue_card_text = tdoc_raw["AP_OFF_JOB"] == "N" ? "" : "<p class='text-danger'>已離職【" + tdoc_raw["AP_OFF_DATE"] + "】</p>";
                vue_card_text += "ID：" + tdoc_raw["DocUserID"] + "<br />"
                    + "電腦：" + tdoc_raw["AP_PCIP"] + "<br />"
                    + "生日：" + tdoc_raw["AP_BIRTH"] + age + "<br />"
                    + "單位：" + tdoc_raw["AP_UNIT_NAME"] + "<br />"
                    + "工作：" + tdoc_raw["AP_WORK"] + "<br />"
                    + "學歷：" + tdoc_raw["AP_HI_SCHOOL"] + "<br />"
                    + "考試：" + tdoc_raw["AP_TEST"] + "<br />"
                    + "手機：" + tdoc_raw["AP_SEL"] + "<br />"
                    + "到職：" + on_board_date + "<br />"
                    ;
                let vue_html = `
                    <div id="user_info_app">
                        <b-card class="overflow-hidden bg-light" style="max-width: 540px; font-size: 0.9rem;" title="${tdoc_raw["AP_USER_NAME"]}" sub-title="${tdoc_raw["AP_JOB"]}">
                            <b-link href="get_pho_img.php?name=${tdoc_raw["AP_USER_NAME"]}" target="_blank">
                                <b-card-img
                                    src="get_pho_img.php?name=${tdoc_raw["AP_USER_NAME"]}"
                                    alt="${tdoc_raw["AP_USER_NAME"]}"
                                    class="img-thumbnail float-right ml-2"
                                    style="max-width: 220px"
                                ></b-card-img>
                            </b-link>
                            <b-card-text>${vue_card_text}</b-card-text>
                        </b-card>
                    </div>
                `;
    
                if ($(selector).length > 0) {
                    $(selector).html(vue_html);
                    Vue.nextTick(() =>
                        new Vue({
                            el: "#user_info_app",
                            components: [ "b-card", "b-link", "b-badge" ],
                            mounted() {
                                addAnimatedCSS(selector, { name: "pulse", duration: "once-anim-cfg" });
                            }
                        })
                    );
                } else {
                    showModal({
                        title: "使用者資訊",
                        body: vue_html,
                        size: "md",
                        callback: () => {
                            Vue.nextTick(() => new Vue({
                                el: "#user_info_app",
                                components: [ "b-card", "b-link", "b-badge" ]
                            }));
                        }
                    });
                }
            },
            checkCaseUIData: function(data) {
                if (isEmpty(data.year)) {
                    addNotification({
                        title: '案件輸入欄位檢測',
                        message: "案件【年】欄位為空白，請重新選擇！",
                        type: "warning",
                        toaster: "b-toaster-top-center"
                    });
                    return false;
                }
                if (isEmpty(data.code)) {
                    addNotification({
                        title: '案件輸入欄位檢測',
                        message: "案件【字】欄位為空白，請重新選擇！",
                        type: "warning",
                        toaster: "b-toaster-top-center"
                    });
                    return false;
                }
                if (isEmpty(data.num) || isNaN(data.num)) {
                    addNotification({
                        title: '案件輸入欄位檢測',
                        message: "案件【號】欄位格式錯誤，請重新輸入！",
                        type: "warning",
                        toaster: "b-toaster-top-center"
                    });
                    return false;
                }
                return true;
            },
            screensaver: () => {
                if (CONFIG.SCREENSAVER) {
                    let idle_timer;
                    function wakeup() {
                        let container = $("body");
                        if (container.hasClass("ld-over-full-inverse")) {
                            container.removeClass("ld-over-full-inverse");
                            container.find("#screensaver").remove();
                            container.removeClass("running");
                        }
                        clearLDAnimation(".navbar i.fas");
                    }
                    function resetTimer() {
                        clearTimeout(idle_timer);
                        idle_timer = setTimeout(() => {
                            wakeup();
                            let container = $("body");
                            // cover style opts: ld-over, ld-over-inverse, ld-over-full, ld-over-full-inverse
                            let style = "ld-over-full-inverse";
                            container.addClass(style);
                            container.addClass("running");
                            let cover_el = $(jQuery.parseHTML('<div id="screensaver" class="ld auto-add-spinner"></div>'));
                            let patterns = [
                                "fas fa-bolt ld-bounce", "fas fa-bed ld-swim", "fas fa-biking ld-move-ltr",
                                "fas fa-biohazard ld-metronome", "fas fa-snowboarding ld-rush-ltr", "fas fa-anchor ld-swing",
                                "fas fa-fingerprint ld-damage", "fab fa-angellist ld-metronome"
                            ];
                            cover_el.addClass(patterns[rand(patterns.length)])
                                    .addClass(LOADING_SHAPES_COLOR[rand(LOADING_SHAPES_COLOR.length)])
                                    .addClass("fa-10x");
                            container.append(cover_el);
                            addLDAnimation(".navbar i.fas", "ld-bounce");
                        }, CONFIG.SCREENSAVER_TIMER);  // 5mins
                        wakeup();
                    }
                    window.onload = resetTimer;
                    window.onmousemove = resetTimer;
                    window.onmousedown = resetTimer;  // catches touchscreen presses as well      
                    window.ontouchstart = resetTimer; // catches touchscreen swipes as well 
                    window.onclick = resetTimer;      // catches touchpad clicks as well
                    window.onkeypress = resetTimer;   
                    window.addEventListener('scroll', resetTimer, true); // improved; see comments
                }
            },
            authenticate: function() {
                // check authority
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: 'authentication'
                }).then(res => {
                    this.$gstore.commit("isAdmin", res.data.is_admin || false);
                    //console.log("isAdmin: ", this.isAdmin);
                }).catch(err => {
                    console.error(err);
                    showAlert({
                        title: '認證失敗',
                        message: err.message,
                        type: 'danger'
                    });
                });
            },
            loadUserNames: function() {
                let json_str = localStorage.getItem("userNames");
                let json_ts = +localStorage.getItem("userNames_timestamp");
                let current_ts = +new Date();
                if (typeof json_str == "string" && current_ts - json_ts < 86400000) {
                    // within a day use the cached data
                    this.$gstore.commit("userNames", JSON.parse(json_str) || {});
                } else {
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: 'user_mapping'
                    }).then(res => {
                        let json = res.data.data;
                        this.$gstore.commit("userNames", json || {});
                        if (localStorage) {
                            localStorage.setItem("userNames", JSON.stringify(json));
                            localStorage.setItem("userNames_timestamp", +new Date()); // == new Date().getTime()
                        }
                        //console.log("userNames: ", res.data.data_count);
                    }).catch(err => {
                        console.error(err);
                        showAlert({
                            title: '使用者對應表',
                            message: err.message,
                            type: 'danger'
                        });
                    });
                }
            }
        }
    });
});
