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
// axios.defaults.timeout = 10000;   // ms
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
                userNames: undefined,
                dynaParams: {},
                errors: [],
                myip: undefined,
                myid: undefined,
                myinfo: undefined,
                authority: {
                    isAdmin: undefined,
                    isSuper: undefined,
                    isChief: undefined,
                    isRAE: undefined,
                    isGA: undefined
                },
                disableMSDBQuery: CONFIG.DISABLE_MSDB_QUERY,
                disableOfficeHours: false,
                disableMockMode: true,
                xapMap: new Map([
                    ['220.1.33.71', {name: '地政局', code: 'H0', ip: '220.1.33.71'}],
                    ['220.1.34.161', {name: '桃園所', code: 'HA', ip: '220.1.34.161'}],
                    ['220.1.35.123', {name: '中壢所', code: 'HB', ip: '220.1.35.123'}],
                    ['220.1.36.45', {name: '大溪所', code: 'HC', ip: '220.1.36.45'}],
                    ['220.1.37.246', {name: '楊梅所', code: 'HD', ip: '220.1.37.246'}],
                    ['220.1.38.30', {name: '蘆竹所', code: 'HE', ip: '220.1.38.30'}],
                    ['220.1.39.57', {name: '八德所', code: 'HF', ip: '220.1.39.57'}],
                    ['220.1.40.33', {name: '平鎮所', code: 'HG', ip: '220.1.40.33'}],
                    ['220.1.41.20', {name: '龜山所', code: 'HH', ip: '220.1.41.20'}]
                ])
            },
            getters: {
                cache: state => state.cache,
                authority: state => state.authority,
                userNames: state => state.userNames,
                dynaParams: state => state.dynaParams,
                errors: state => state.errors,
                errorLen: state => state.errors.length,
                myip: state => state.myip,
                myid: state => state.myid,
                myinfo: state => state.myinfo,
                disableMSDBQuery: state => state.disableMSDBQuery,
                disableOfficeHours: state => state.disableOfficeHours,
                disableMockMode: state => state.disableMockMode,
                xapMap: state => state.xapMap
            },
            mutations: {
                cache(state, objPayload) {
                    for (var key in objPayload) {
                        if (objPayload[key] !== undefined && objPayload[key] !== '' && objPayload[key] !== null) {
                            state.cache.set(key, objPayload[key]);
                        }
                    }
                },
                authority(state, authPayload) {
                    state.authority = authPayload;
                    state.isAdmin = authPayload.isAdmin;
                    state.isChief = authPayload.isChief;
                    state.isSuper = authPayload.isSuper;
                },
                userNames(state, mappingPayload) {
                    state.userNames = mappingPayload || {};
                },
                dynaParams(state, objPayload) {
                    state.dynaParams = Object.assign({}, state.dynaParams, objPayload);
                },
                error(state, msgPayload) {
                    if (!Array.isArray(state.errors)) state.errors = [];
                    state.errors.push(msgPayload);
                },
                errorPop(state, dontCarePayload) {
                    state.error.pop();
                },
                myip(state, ipPayload) {
                    if (/^(?!0)(?!.*\.$)((1?\d?\d|25[0-5]|2[0-4]\d)(\.|$)){4}$/.test(ipPayload) || ipPayload == '::1') {
                        state.myip = ipPayload;
                    } else {
                        console.error({
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
                myinfo(state, infoPayload) {
                    state.myinfo = infoPayload;
                    state.myid = $.trim(infoPayload['id']) || undefined;
                },
                disableMSDBQuery(state, flagPayload) {
                    state.disableMSDBQuery = flagPayload === true;
                },
                disableOfficeHours(state, flagPayload) {
                    state.disableOfficeHours = flagPayload === true;
                },
                disableMockMode(state, flagPayload) {
                    state.disableMockMode = flagPayload === true;
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
                            await axios.post(CONFIG.API.JSON.USER, {
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
                                commit("userNames", {});
                            });
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                async authenticate({ commit, state }) {
                    try {
                        let authority = await localforage.getItem("authority");
                        let authority_ts = await localforage.getItem("authority_timestamp");
                        let current_ts = +new Date(); // == new Date().getTime()
                        if (typeof authority == "object" && current_ts - authority_ts < state.dayMilliseconds) {
                            commit("authority", authority || false);
                        } else {
                            await axios.post(CONFIG.API.JSON.USER, {
                                type: 'authentication'
                            }).then(res => {
                                console.log(res.data.message);
                                //console.log(res.data.authority);
                                authority = res.data.authority;
                                localforage.setItem(`authority`, authority || false);
                                commit("authority", authority || false);
                            }).catch(err => {
                                console.error(err);
                                commit("authority", authority || false);
                            }).finally(() => {
                                localforage.setItem(`authentication_set_ts`, current_ts);
                            });
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
    data: () => ({
        isBusy: false,
        busyIconSize: undefined,
        error: {},
        dayMilliseconds: 24 * 60 * 60 * 1000
    }),
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
                    time: this.now()
                });
                this.alert({
                    title: "錯誤訊息",
                    subtitle: this.now(),
                    message: nMsg.message || nMsg,
                    type: "danger"
                });
                // console output
                this.$error(nMsg);
            }
        }
    },
    computed: {
        cache() { return this.$store.getters.cache },
        isAdmin() { return this.$store.getters.authority.isAdmin },
        isChief() { return this.$store.getters.authority.isChief },
        isSuper() { return this.$store.getters.authority.isSuper },
        isRAE() { return this.$store.getters.authority.isRAE },
        isGA() { return this.$store.getters.authority.isGA },
        userNames() {
            // lazy loading
            if (this.$store.getters.userNames === undefined) {
                this.$store.dispatch("loadUserNames");
            }
            return this.$store.getters.userNames || {};
        },
        userIDs() { return this.reverseMapping(this.userNames || {}) },
        storeParams() { return this.$store.getters.dynaParams },
        gerror() { return this.$store.getters.errors[this.$store.getters.errors.length - 1] },
        gerrorLen() { return this.$store.getters.errorLen },
        gerrors() { return this.$store.getters.errors },
        myip() { return this.$store.getters.myip },
        myid() { return this.$store.getters.myid },
        myinfo() { return this.$store.getters.myinfo },
        authority() { return this.$store.getters.authority },
        myname() { return this.myinfo ? this.myinfo['name'] : '' },
        disableMSDBQuery() { return this.$store.getters.disableMSDBQuery },
        disableOfficeHours() { return this.$store.getters.disableOfficeHours },
        disableMockMode() { return this.$store.getters.disableMockMode },
        nowDate() { return this.now().split(' ')[0] },
        nowTime() { return this.now().split(' ')[1] },
        viewportRatio() { return ((window.innerWidth) * 1.08).toFixed(2) / (window.innerHeight - 85 - 20).toFixed(2) },
        xapMap() { return this.$store.getters.xapMap }
    },
    methods: {
        addToStoreParams: function(key, value) {
            let payload = {};
            payload[key] = value;
            this.$store.commit('dynaParams', payload);
        },
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
                        case "xs":
                            cover_el.addClass("fa-xs");
                            break;
                        case "sm":
                            cover_el.addClass("fa-sm");
                            break;
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
                            cover_el.addClass(`fa-${opts.size == undefined ? '2x' : opts.size}`);
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
        busyOn: function(el = "body") {
            this.toggleBusy({selector: el, forceOn: true, size: this.busyIconSize});
            this.$emit("busyOn", this);
        },
        busyOff: function(el = "body") {
            this.toggleBusy({selector: el, forceOff: true});
            this.$emit("busyOff", this);
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
        },
        getUrlParameter: name => {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        },
        empty: function (variable) {
            if (variable === null || variable === undefined || variable === false) return true;
            if (typeof variable == "object" && variable.length == 0) return true;
            if (typeof variable == "array" && variable.length == 0) return true;
            if ($.trim(variable) == "") return true;
            return false;
        },
        uuid: function() {
            var d = Date.now();
            if (typeof performance !== 'undefined' && typeof performance.now === 'function'){
                d += performance.now(); //use high-precision timer if available
            }
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (d + Math.random() * 16) % 16 | 0;
                d = Math.floor(d / 16);
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },
        rand: (range) => Math.floor(Math.random() * Math.floor(range || 100)),
        now: function() {
            // e.g. 2020-03-14 11:35:23
            let now = new Date();
            return now.getFullYear() + "-" +
                ("0" + (now.getMonth() + 1)).slice(-2) + "-" +
                ("0" + now.getDate()).slice(-2) + " " +
                ("0" + now.getHours()).slice(-2) + ":" +
                ("0" + now.getMinutes()).slice(-2) + ":" +
                ("0" + now.getSeconds()).slice(-2);
        },
        open: function(url, e) {
            let h = window.innerHeight - 160;
            this.$modal(`<iframe src="${url}" class="w-100" height="${h}" frameborder="0"></iframe>`, {
                title: e.target.title || `外部連結`,
                size: "xl",
                html: true,
                noCloseOnBackdrop: false
            });
        },
        openNewWindow: function(url, e) {
            let win = window.open(url, '_blank');
            win.focus();
        },
        popUsercard: function(id, name = '', ip = '') {
            let card = this.$createElement("lah-user-card", { props: { id: id, name: name, ip: ip } });
            this.$modal(card, { title: "使用者資訊" });
        },
        usercard: function(e) {
            // find the most closest element to get the data-* attrs
            let clicked_element = $($(e.target).closest(".user_tag,.lah-user-card,.usercard"));
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
                let vue_el = $.parseHTML(`<div><lah-user-card id="${id}" name="${name}" ip="${ip}"></lah-user-card></div>`);
                $(el_selector).html("").append(vue_el);
                new Vue({
                    el: vue_el[0],
                    mounted() { this.animated(this.$el, { name: "headShake", duration: "once-anim-cfg" }); }
                });
            } else {
                this.popUsercard(id, name, ip);
            }
        },
        animated: function(selector, opts) {
            const node = $(selector);
            if (node) {
                opts = Object.assign({
                    name: ANIMATED_PATTERNS[rand(ANIMATED_PATTERNS.length)],
                    duration: "once-anim-cfg"    // a css class to control speed
                }, opts);
                node.addClass(`animated ${opts.name} ${opts.duration}`);
                function handleAnimationEnd() {
                    if (typeof opts.callback === 'function') opts.callback.apply(this, arguments);
                    node.removeClass(`animated ${opts.name} ${opts.duration}`);
                    node.off('animationend');
                    // clear ld animation also
                    clearLDAnimation(selector);
                }
                node.on('animationend', handleAnimationEnd);
            }
            return node;
        },
        alert: function(opts) {
            if (typeof opts == "string") {
                opts = { message: opts }
            }
            if (!this.empty(opts.message)) {
                let alert = window.vueApp.$refs.alert || window.dynaApp.$refs.alert;
                alert.show(opts);
            }
        },
        notify: function(msg, opts) {
            // previous only use one object param
            if (typeof msg == "object") {
                let message = msg.body || msg.message;
                msg.variant = msg.type || "default";
                msg.autoHideDelay = msg.duration || msg.delay || 5000;
                this.$toast(message, msg);
            } else if (typeof msg == "string") {
                this.$toast(msg, opts);
            } else {
                this.alert({message: "notify 傳入參數有誤(請查看console)", type: "danger"});
                this.$error(msg, opts);
            }
        },
        msgbox: function(opts) {
            let body = opts.body || opts.message;
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

            this.$modal(body, {
                title: title,
                size: size,
                html: true,
                callback: callback,
                noCloseOnBackdrop: !opts.backdrop_close
            });
        },
        responseMessage(status_code) {
            switch(status_code) {
                case 0:
                    return `失敗【${XHR_STATUS_CODE.DEFAULT_FAIL}, DEFAULT_FAIL】`;
                case 1:
                    return `成功【${XHR_STATUS_CODE.SUCCESS_NORMAL}, SUCCESS_NORMAL】`;
                case 2:
                    return `成功(回傳多筆資料)【${XHR_STATUS_CODE.SUCCESS_WITH_MULTIPLE_RECORDS}, SUCCESS_WITH_MULTIPLE_RECORDS】`;
                case 3:
                    return `成功(無資料)【${XHR_STATUS_CODE.SUCCESS_WITH_NO_RECORD}, SUCCESS_WITH_NO_RECORD】`;
                case -1:
                    return `失敗(不支援)【${XHR_STATUS_CODE.UNSUPPORT_FAIL}, UNSUPPORT_FAIL】`;
                case -2:
                    return `失敗(本地端無資料)【${XHR_STATUS_CODE.FAIL_WITH_LOCAL_NO_RECORD}, FAIL_WITH_LOCAL_NO_RECORD】`;
                case -3:
                    return `失敗(非正確伺服主機)【${XHR_STATUS_CODE.FAIL_NOT_VALID_SERVER}, FAIL_NOT_VALID_SERVER】`;
                case -4:
                    return `失敗(遠端無資料)【${XHR_STATUS_CODE.FAIL_WITH_REMOTE_NO_RECORD}, FAIL_WITH_REMOTE_NO_RECORD】`;
                case -5:
                    return `授權失敗【${XHR_STATUS_CODE.FAIL_NO_AUTHORITY}, FAIL_NO_AUTHORITY】`;
                case -6:
                    return `JSON編碼失敗【${XHR_STATUS_CODE.FAIL_JSON_ENCODE}, FAIL_JSON_ENCODE】`;
                case -7:
                    return `找不到資料失敗【${XHR_STATUS_CODE.FAIL_NOT_FOUND}, FAIL_NOT_FOUND`;
                case -8:
                    return `讀取檔案失敗【${XHR_STATUS_CODE.FAIL_LOAD_ERROR}, FAIL_LOAD_ERROR`;
                case -9:
                    return `動作請求逾時【${XHR_STATUS_CODE.FAIL_TIMEOUT}, FAIL_TIMEOUT`;
                default:
                    return `不支援的狀態碼【${status_code}】`;
            }
        },
        isOfficeHours() {
            if (this.disableOfficeHours) return true;
            let now = new Date();
            if (now.getDay() === 0 || now.getDay() === 6) return false;
            return now.getHours() > 6 && now.getHours() < 19;
        },
        timeout(func, ms) { return setTimeout(func, ms) }
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
                    subtitle: this.now().split(" ")[1], // everytime is different, so not use computed var here
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
                    this.timeout(() => merged.callback.apply(that, arguments), 100);
                }
                this.toastCounter++;
            },
            showModal: function(id, duration) {
                let modal_content = $(`#${id} .modal-content`);
                modal_content.removeClass("hide");
                this.animated(modal_content, {
                    name: this.transition.in,
                    duration: duration || "once-anim-cfg",
                    callback: this.callbackQueue.pop()
                });
            },
            hideModal: function(id) {
                let body = $("body.modal-open");
                let count = parseInt(body.data("modal-open-count")) || 0;
                if (this.empty(id)) {
                    $('div.modal.show').each((idx, el) => {
                        this.removeModal(el.id);
                        body.data("modal-open-count", --count);
                    });
                } else {
                    this.removeModal(id);
                    body.data("modal-open-count", --count);
                }
                if (body.attr("style")) {
                    let removed_style = body.attr("style").replace(/((padding|margin)\-right:\s*\d*px;)/gi, '');
                    body.attr("style", removed_style);
                    body.removeAttr("data-padding-right");
                }
                // https://bootstrap-vue.js.org/docs/components/modal/#emitting-events-on-root
                // however ... below is useless Orz
                this.$root.$emit('bv::modal::hidden', id);
            },
            removeModal: function(id, duration) {
                if (!this.openConfirm) {
                    let modal_content = $(`#${id} .modal-content`);
                    this.animated(modal_content, {
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
                    noCloseOnBackdrop: false,
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
            confirmAdapter: function(message, callback) {
                this.confirm(message, {
                    callback: callback
                });
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
    
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        this.alert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                        return;
                    } else {
                        this.msgbox({
                            message: this.$createElement("lah-reg-case-detail", {
                                props: {
                                    bakedData: res.data.baked
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
            screensaver: function() {
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
                this.$lf.iterate((value, key, iterationNumber) => {
                    // Resulting key/value pair -- this callback
                    // will be executed for every item in the
                    // database.
                    try {
                        let el = $("#"+key);
                        if (el.length > 0 && el.is(cached_el_selector)) {
                            el.val(value);
                            // trigger native event to force bound model data to update
                            if (el[0].nodeName == 'INPUT' || el[0].nodeName == 'TEXTAREA') {
                                el[0].dispatchEvent(new Event('input'));
                            } else {
                                el[0].dispatchEvent(new Event('change'));
                            }
                        }
                    } catch(err) {
                        this.$lf.removeItem(key);
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
            },
            initMyInfo: function() {
                this.getLocalCache('myinfo').then(myinfo => {
                    if (myinfo) {
                        this.$store.commit("myinfo", myinfo);
                    } else {
                        this.$http.post(CONFIG.API.JSON.USER, {
                            type: 'my_info'
                        }).then(res => {
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                let myinfo = res.data.info;
                                this.setLocalCache('myinfo', myinfo, this.dayMilliseconds);   // cache query info result
                                this.$store.commit("myinfo", myinfo);
                            } else {
                                this.$warn(res.data.message);
                            }
                        }).catch(err => {
                            this.error = err;
                        });
                    }
                });
                this.getLocalCache('myip').then(myip => {
                    if (this.empty(myip)) {
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: 'ip'
                        }).then(res => {
                            this.setLocalCache('myip', res.data.ip, this.dayMilliseconds);
                            this.$store.commit('myip', res.data.ip);
                        }).catch(err => {
                            this.error = err;
                        });
                    } else {
                        this.$store.commit('myip', myip);
                    }
                });
            },
            initSystemSwitches: function() {
                axios.post(CONFIG.API.JSON.SWITCH, {
                    type: 'switch_mssql_flag'
                }).then(res => {
                    let enable_mssql = res.data.mssql_flag;
                    this.$store.commit("disableMSDBQuery", !enable_mssql);
                }).catch(err => {
                    this.$error = err;
                });

                axios.post(CONFIG.API.JSON.SWITCH, {
                    type: 'switch_office_hours_flag'
                }).then(res => {
                    let enable_office_hours = res.data.office_hours_flag;
                    this.$store.commit("disableOfficeHours", !enable_office_hours);
                }).catch(err => {
                    this.$error = err;
                });

                axios.post(CONFIG.API.JSON.SWITCH, {
                    type: 'switch_mock_flag'
                }).then(res => {
                    //console.log(res.data.authority);
                    let mock_on = res.data.mock_flag;
                    this.$store.commit("disableMockMode", !mock_on);
                }).catch(err => {
                    this.$error = err;
                }).finally(() => {
                    if (!this.disableMockMode) {
                        this.notify({
                            title: '模擬模式提醒',
                            message: '目前系統處於模擬模式下，所有資訊都會是從快取資料回復！',
                            type: 'info',
                            delay: 10000,
                            pos: 'bl'
                        });
                    }
                });
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
                    // add this the bv::modal::hidden will not trigger ... Orz
                    bvEvent.preventDefault();
                    this.hideModal(modalId);
                }
            });
            this.$root.$on('bv::modal::hidden', (bvEvent, modalId) => {
                //console.log('Modal is hidden', bvEvent, modalId)
            });
            this.screensaver();
            // shortcut to every Vue instance
            Vue.prototype.$confirm = this.confirmAdapter;
            Vue.prototype.$modal = this.modal;
            Vue.prototype.$toast = this.makeToast;
        },
        mounted() {
            this.initCache();
            this.initMyInfo();
            this.initSystemSwitches();
        }
    });
});
