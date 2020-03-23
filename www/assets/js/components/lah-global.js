/**
 * Land-Affairs-Helper(lah) Vue global setup
 */
console.assert(typeof jQuery == "function", "jQuery is not loaded, did you include jquery.min.js in the page??");
console.assert(typeof CONFIG == "object", "CONFIG is not loaded, did you include global.js in the page??");
console.assert(typeof axios == "function", "axios is not loaded, did you include axios.min.js in the page??");
console.assert(typeof localforage == "object", "localforage is not loaded, did you include localforage.min.js in the page??");
console.assert(typeof Vuex == "object", "Vuex is not loaded, did you include vuex.js in the page??");
Vue.config.devtools = true;
/**
 * set axios defaults
 */
// PHP default uses QueryString as the parsing source but axios use json object instead
axios.defaults.transformRequest = [data => $.param(data)];
if (CONFIG.DEBUG_MODE) {
    // declare a request interceptor
    axios.interceptors.request.use(config => {
        // perform a task before the request is sent
        console.log(config);
        return config;
    }, error => {
        // handle the error
        return Promise.reject(error);
    });
    // declare a response interceptor
    axios.interceptors.response.use((response) => {
        // do something with the response data
        console.log(response);
        return response;
    }, error => {
        // handle the response error
        return Promise.reject(error);
    });
}
// add to all Vue instances
// https://vuejs.org/v2/cookbook/adding-instance-properties.html
Vue.prototype.$log = console.log.bind(console);
Vue.prototype.$error = console.error.bind(console);
Vue.prototype.$warn = console.warn.bind(console);
Vue.prototype.$assert = console.assert.bind(console);
Vue.prototype.$http = axios;
Vue.prototype.$lf = localforage || {};
// single source of truth
Vue.prototype.$store = (() => {
    if (typeof Vuex == "object") {
        return new Vuex.Store({
            state: {
                cache : new Map(),
                isAdmin: undefined,
                userNames: undefined,
                dayMilliseconds: 24 * 60 * 60 * 1000,
                dynaParams: {},
                errors: [],
                myip: undefined,
                myid: undefined,
                disableMSDBQuery: CONFIG.DISABLE_MSDB_QUERY
            },
            getters: {
                cache: state => state.cache,
                isAdmin: state => state.isAdmin,
                userNames: state => state.userNames,
                dayMilliseconds: state => state.dayMilliseconds,
                dynaParams: state => state.dynaParams,
                errors: state => state.errors,
                errorLen: state => state.errors.length,
                myip: state => state.myip,
                myid: state => state.myid,
                disableMSDBQuery: state => state.disableMSDBQuery
            },
            mutations: {
                cache(state, objPayload) {
                    for (var key in objPayload) {
                        if (objPayload[key] !== undefined && objPayload[key] !== '' && objPayload[key] !== null) {
                            state.cache.set(key, objPayload[key]);
                        }
                    }
                },
                isAdmin(state, flagPayload) {
                    state.isAdmin = flagPayload === true;
                },
                userNames(state, mappingPayload) {
                    state.userNames = mappingPayload || {};
                },
                dynaParams(state, objPayload) {
                    state.dynaParams = Object.assign({}, state.dynaParams, objPayload);
                },
                error(state, msgPayload) {
                    state.errors.push(msgPayload);
                },
                errorPop(state, dontCarePayload) {
                    state.error.pop();
                },
                myip(state, ipPayload) {
                    if (/^(?!0)(?!.*\.$)((1?\d?\d|25[0-5]|2[0-4]\d)(\.|$)){4}$/.test(ipPayload) || ipPayload == '::1') {
                        state.myip = ipPayload;
                    } else {
                        showAlert({
                            title: "發生錯誤",
                            subtitle: "Vuex, commit 'myip'",
                            message: `${ipPayload} 格式不正確！`,
                            type: "warning"
                        });
                    }
                },
                myid(state, idPayload) {
                    state.myid = idPayload;
                },
                disableMSDBQuery(state, flagPayload) {
                    state.disableMSDBQuery = flagPayload === true;
                }
            },
            actions: {
                async loadUserNames({ commit, state }) {
                    try {
                        let json, json_ts;
                        if (localforage) {
                            json = await localforage.getItem("userNames");
                            json_ts = await localforage.getItem("userNames_timestamp");
                        }
                        let current_ts = +new Date();
                        if (typeof json == "object" && current_ts - json_ts < state.dayMilliseconds) {
                            // within a day use the cached data
                            commit("userNames", json || {});
                        } else {
                            await axios.post(CONFIG.JSON_API_EP, {
                                type: 'user_mapping'
                            }).then(async res => {
                                let json = res.data.data;
                                if (localforage) {
                                    await localforage.setItem("userNames", json);
                                    await localforage.setItem("userNames_timestamp", +new Date()); // == new Date().getTime()
                                }
                                commit("userNames", json || {});
                            }).catch(err => {
                                console.error(err);
                                showAlert({
                                    title: '使用者對應表',
                                    message: err.message,
                                    type: 'danger'
                                });
                                commit("userNames", {});
                            });
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                async authenticate({ commit, state }) {
                    try {
                        const isAdmin = await localforage.getItem(`isAdmin`);
                        const set_ts = await localforage.getItem(`isAdmin_set_ts`);
                        const now_ts = +new Date();
                        // over 15 mins, re-authenticate ... otherwise skip the request
                        if (isAdmin === null || !Number.isInteger(set_ts) || now_ts - set_ts > 900000) {
                            await axios.post(CONFIG.JSON_API_EP, {
                                type: 'authentication'
                            }).then(res => {
                                commit("isAdmin", res.data.is_admin || false);
                                localforage.setItem(`isAdmin`, res.data.is_admin || false);
                            }).catch(err => {
                                console.error(err);
                                showAlert({
                                    title: '認證失敗',
                                    message: err.message,
                                    type: 'danger'
                                });
                                commit("isAdmin", false);
                            }).finally(() => {
                                localforage.setItem(`isAdmin_set_ts`, +new Date()); // == new Date().getTime()
                            });
                        } else {
                            commit("isAdmin", isAdmin);
                        }
                    } catch (err) {
                        console.error(err);
                    }
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
        "lah-fa-icon": {
            template: `<span><i :class="className"></i> <slot></slot></span>`,
            props: ["size", 'prefix', 'icon', 'variant', 'action'],
            computed: {
                className() {
                    let prefix = this.prefix || 'fas';
                    let icon = this.icon || 'exclamation-circle';
                    let variant = this.variant || 'transparent';
                    let ld_movement = this.action || '';
                    let size = '';
                    switch(this.size) {
                        case "xs": size = "fa-xs"; break;
                        case "sm": size = "fa-sm"; break;
                        case "lg": size = "fa-lg"; break;
                        default:
                            if (this.size && this.size[this.size.length - 1] === "x") {
                                size = `fa-${this.size}`;
                            }
                            break;
                    }
                    return `text-${variant} ${prefix} fa-${icon} ${size} ld ld-${ld_movement}`
                }
            }
        }
    },
    data: function() { return {
        isBusy: false,
        error: {}
    }},
    watch: {
        isBusy: function(flag) { flag ? this.busyOn(this.$el) : this.busyOff(this.$el) },
        error: function(nMsg, oMsg) {
            if (!this.empty(nMsg)) {
                // just in case the message array occupies too much memory
                if (this.gerrorLen > 30) {
                    this.$store.commit("errorPop");
                }
                this.$store.commit("error", {
                    message: nMsg.message || nMsg,
                    time: this.nowDatetime
                });
                showAlert({
                    title: "錯誤訊息",
                    subtitle: this.nowDatetime,
                    message: nMsg.message || nMsg,
                    type: "danger"
                });
                // console output
                this.$error(nMsg);
            }
        }
    },
    computed: {
        cache() { return this.$store.getters.cache; },
        isAdmin() {
            return this.$store.getters.isAdmin;
        },
        userNames() {
            if (this.$store.getters.userNames === undefined) {
                this.$store.dispatch("loadUserNames");
            }
            return this.$store.getters.userNames || {};
        },
        userIDs() { return this.reverseMapping(this.userNames || {}); },
        dayMilliseconds() { return this.$store.getters.dayMilliseconds; },
        settings() { return this.$store.getters.dynaParams; },
        gerror() { return this.$store.getters.errors[this.$store.getters.errors.length - 1]; },
        gerrorLen() { return this.$store.getters.errorLen; },
        gerrors() { return this.$store.getters.errors; },
        nowDatetime() {
            // e.g. 2020-03-14 11:35:23
            let now = new Date();
            return now.getFullYear() + "-" +
                ("0" + (now.getMonth() + 1)).slice(-2) + "-" +
                ("0" + now.getDate()).slice(-2) + " " +
                ("0" + now.getHours()).slice(-2) + ":" +
                ("0" + now.getMinutes()).slice(-2) + ":" +
                ("0" + now.getSeconds()).slice(-2);
        },
        myip() { return this.$store.getters.myip; },
        myid() { return this.$store.getters.myid; },
        disableMSDBQuery() { return this.$store.getters.disableMSDBQuery; },
    },
    methods: {
        setSetting: function(key, value) {
            let payload = {};
            payload[key] = value;
            this.$store.commit('dynaParams', payload);
        },
        getSetting: function(key) { return this.settings[key] },
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
        busyOn: function(el = "body", size = "") {
            this.toggleBusy({selector: el, forceOn: true, size: size});
            this.$emit("busyOn", this);
        },
        busyOff: function(el = "body") {
            this.toggleBusy({selector: el, forceOff: true});
            this.$emit("busyOff", this);
        },
        empty: function (variable) {
            if (variable === null || variable === undefined || variable === false) return true;
            if (typeof variable == "object" && variable.length == 0) return true;
            if (typeof variable == "array" && variable.length == 0) return true;
            if ($.trim(variable) == "") return true;
            return false;
        },
        setLocalCache: async function(key, val, expire_timeout = 0) {
            if (!localforage || this.empty(key) || this.empty(val)) return false;
            try {
                let item = {
                    key: key,
                    value: val,
                    timestamp: +new Date(),     // == new Date().getTime()
                    expire_ms: expire_timeout   // milliseconds
                };
                await localforage.setItem(key, item);
            } catch (err) {
                console.error(err);
                return false;
            }
            return true;
        },
        getLocalCache: async function(key) {
            if (!localforage || this.empty(key)) return false;
            try {
                const item = await localforage.getItem(key);
                if (this.empty(item)) return false;
                let ts = item.timestamp;
                let expire_time = item.expire_ms || 0;
                let now = +new Date();
                //console.log(`get ${key} value. (expire_time: ${expire_time}), now - ts == ${now - ts}`, item.value);
                if (expire_time != 0 && now - ts > expire_time) {
                    await localforage.removeItem(key);
                    //console.log(`${key} is removed. (expire_time: ${expire_time}), now - ts == ${now - ts}`);
                    return false;
                } else {
                    return item.value;
                }
            } catch (err) {
                console.error(err);
            }
            return false;
        },
        getLocalCacheExpireRemainingTime: async function(key) {
            if (!localforage || this.empty(key)) return false;
            try {
                const item = await localforage.getItem(key);
                if (this.empty(item)) return false;
                let ts = item.timestamp;
                let expire_time = item.expire_ms || 0;
                let now = +new Date();
                //console.log(`get ${key} value. (expire_time: ${expire_time}), now - ts == ${now - ts}`, item.value);
                if (expire_time == 0) {
                    return false;
                } else {
                    return expire_time - (now - ts);    // milliseconds
                }
            } catch (err) {
                console.error(err);
            }
            return false;
        },
        removeLocalCache: function(key) {
            if (!localforage || this.empty(key)) return false;
            try {
                localforage.removeItem(key);
            } catch (err) {
                console.error(err);
            }
            return true;
        }
    }
});

$(document).ready(() => {
    // dynamic add header/footer/alert
    let dynamic_comps = $.parseHTML(`<div id="global-dynamic-components">
        <lah-header ref="header"></lah-header>
        <lah-footer ref="footer"></lah-footer>
        <lah-alert ref="alert"></lah-alert>
    </div>`);
    let target = $("body section:first-child");
    if (target.length == 0) {
        $("body").prepend(dynamic_comps);
        window.dynaApp = new Vue({ el: "#global-dynamic-components" });
    } else {
        target.prepend(dynamic_comps);
    }
    
    // main app for whole page, use window.vueApp to get it
    window.vueApp = new Vue({
        el: target[0],  // jQuery always return array of elements
        data: {
            toastCounter: 0,
            openConfirm: false,
            confirmAnswer: false,
            transition: ANIMATED_TRANSITIONS[rand(ANIMATED_TRANSITIONS.length)],
            callbackQueue: []
        },
        methods: {
            // make simple, short popup notice message
            makeToast: function(message, opts = {}) {
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
                    subtitle: this.nowDatetime.split(" ")[1],
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
                if (id == "" || id == undefined || id == null) {
                    $('div.modal.show').each((idx, el) => {
                        this.removeModal(el.id);
                    });
                } else {
                    this.removeModal(id);
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
                    this.error = err;
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
            fetchRegCase: function(e) {
                // ajax event binding
                let clicked_element = $(e.target);
                // remove additional characters for querying
                let id = trim(clicked_element.text());
    
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        showAlert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                        return;
                    } else {
                        showModal({
                            message: this.$createElement("lah-reg-case-detail", {
                                props: {
                                    jsonObj: res.data.baked
                                }
                            }),
                            title: `登記案件詳情 ${id}`,
                            size: "lg"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                });
            },
            fetchUserInfo: function(e) {
                if (CONFIG.DISABLE_MSDB_QUERY) {
                    console.warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping vueApp.fetchUserInfo.");
                    return;
                }
                
                // find the most closest 
                let clicked_element = $($(e.target).closest(".user_tag"));
                let name = $.trim(clicked_element.data("name")) || '';
                let id = trim(clicked_element.data("id")) || '';
                let ip = $.trim(clicked_element.data("ip")) || '';
                if (this.empty(name) && this.empty(id) && this.empty(ip)) {
                    // fallback to find itself data-*
                    clicked_element = $(e.target);
                    name = $.trim(clicked_element.data("name")) || $.trim(clicked_element.text()) || '';
                    id = trim(clicked_element.data("id")) || '';
                    ip = $.trim(clicked_element.data("ip")) || '';
                }
                if (name) {
                    name = name.replace(/[\?A-Za-z0-9\+]/g, '');
                }
                if (this.empty(name) && this.empty(id) && this.empty(ip)) {
                    console.warn(id, name, ip, "所有參數都為空值，無法查詢使用者資訊！");
                    return false;
                }

                // use data-container(data-el) HTML attribute to specify the display container, empty will use the modal popup window instead.
                let el_selector = clicked_element.data("container") || clicked_element.data("el");
                if ($(el_selector).length > 0) {
                    // $(el_selector).html("").append(card.$el);
                    // addAnimatedCSS(card.$el, { name: "headShake", duration: "once-anim-cfg" });
                    let vue_el = $.parseHTML(`<div><lah-user-card id="${id}" name="${name}" ip="${ip}"></lah-user-card></div>`);
                    $(el_selector).html("").append(vue_el);
                    new Vue({
                        el: vue_el[0],
                        mounted() { addAnimatedCSS(this.$el, { name: "headShake", duration: "once-anim-cfg" }); }
                    });
                } else {
                    let card = this.$createElement("lah-user-card", { props: { id: id, name: name, ip: ip } });
                    showModal({
                        title: "使用者資訊",
                        body: card
                    });
                }
            },
            checkCaseUIData: function(data) {
                if (this.empty(data.year)) {
                    addNotification({
                        title: '案件輸入欄位檢測',
                        message: "案件【年】欄位為空白，請重新選擇！",
                        type: "warning",
                        toaster: "b-toaster-top-center"
                    });
                    return false;
                }
                if (this.empty(data.code)) {
                    addNotification({
                        title: '案件輸入欄位檢測',
                        message: "案件【字】欄位為空白，請重新選擇！",
                        type: "warning",
                        toaster: "b-toaster-top-center"
                    });
                    return false;
                }
                if (this.empty(data.num) || isNaN(data.num)) {
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
            initCache: async function() {
                let cached_el_selector = "input[type='text'], input[type='number'], select, textarea";
                this.$lf.iterate(function(value, key, iterationNumber) {
                    // Resulting key/value pair -- this callback
                    // will be executed for every item in the
                    // database.
                    let el = $("#"+key);
                    if (el.length > 0 && el.is(cached_el_selector)) {
                        el.val(value);
                    }
                }).then(() => {
                    //console.log('Iteration has completed');
                }).catch(err => {
                    // This code runs if there were any errors
                    this.error = err;
                });
                // for cache purpose
                let cacheIt = (el) => {
                    let this_text_input = $(el);
                    let val = this_text_input.val();
                    let ele_id = this_text_input.attr("id");
                    if (val === undefined || $.trim(val) == "") {
                        this.$lf.removeItem(ele_id).then(function() {
                            // Run this code once the key has been removed.
                        }).catch(err => {
                            // This code runs if there were any errors
                            this.error = err;
                        });
                    } else if (ele_id != undefined) {
                        this.$lf.setItem(ele_id, val);
                    }
                }
                window.pyliuCacheTimer = setInterval(function(e) {
                    $(cached_el_selector).each(function(index, el) {
                        if (!$(el).hasClass("no-cache")) {
                            cacheIt(el);
                        }
                    });
                }, 10000);
                $(cached_el_selector).on("blur", function(e) {
                    if (!$(e.target).hasClass("no-cache")) {
                        cacheIt(e.target);
                    }
                });
                // clear cached data after a week
                let st = await this.$lf.getItem("cache_st_timestamp");
                let current_ts = +new Date();
                if (st) {
                    if (current_ts - st > 24 * 60 * 60 * 1000 * 7) {
                        this.$lf.clear().then(() => {
                            console.warn("localforage clean the cached data because of a week passed.");
                        });
                    }
                } else {
                    this.$lf.setItem("cache_st_timestamp", +new Date());
                }
            }
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
            this.screensaver();
        },
        mounted() { this.initCache(); }
    });
});
