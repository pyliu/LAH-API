if (Vue) {
    /**
     * Land-Affairs-Helper(lah) Vue components
     */
    Vue.component("lah-transition", {
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
        data: () => ({
            animated_in: "animated fadeIn once-anim-cfg",
            animated_out: "animated fadeOut once-anim-cfg",
            animated_opts: ANIMATED_TRANSITIONS,
            duration: 400, // or {enter: 400, leave: 800}
            mode: "out-in", // out-in, in-out
            cfg_css: "once-anim-cfg"
        }),
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
            enter: function (e) {
                this.$emit("enter", e);
            },
            leave: function (e) {
                this.$emit("leave", e);
            },
            afterEnter: function (e) {
                this.$emit("after-enter", e);
            },
            afterLeave: function (e) {
                this.$emit("after-leave", e);
            },
            rand: (range) => Math.floor(Math.random() * Math.floor(range || 100)),
            randAnimation: function () {
                if (this.animated_opts) {
                    let count = this.animated_opts.length;
                    let this_time = this.animated_opts[this.rand(count)];
                    this.animated_in = `${this_time.in} ${this.cfg_css}`;
                    this.animated_out = `${this_time.out} ${this.cfg_css}`;
                }
            }
        }
    });

    Vue.component("lah-fa-icon", {
        template: `<span class="align-middle my-auto">
            <span v-if="append"><slot></slot> <i :id="id" :class="className"></i></span>
            <span v-else><i :id="id" :class="className"></i> <slot></slot></span>
        </span>`,
        props: {
            size: { type: String, default: '' },
            prefix: { type: String, default: 'fas' },
            icon: { type: String, default: 'exclamation-circle' },
            variant: { type: String, default: '' },
            action: { type: String, default: '' },
            append: { type: Boolean, default: false },
            id: { type: String, default: '' },
            align: { type: String, default: '' }
        },
        computed: {
            className() {
                let prefix = this.prefix || 'fas';
                let icon = this.icon || 'exclamation-circle';
                let variant = this.variant || '';
                let ld_movement = this.action || '';
                let size = '';
                switch (this.size) {
                    case "xs":
                        size = "fa-xs";
                        break;
                    case "sm":
                        size = "fa-sm";
                        break;
                    case "lg":
                        size = "fa-lg";
                        break;
                    default:
                        if (this.size && this.size[this.size.length - 1] === "x") {
                            size = `fa-${this.size}`;
                        }
                        break;
                }
                return `text-${variant} ${prefix} fa-${icon} ${size} ld ld-${ld_movement}`
            }
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
                    <b-progress height="3px" :max="delay_ms" :variant="bar_variant" :value="remaining_delay"></b-progress>
                </div>
            </lah-transition>
        </div>`,
        data: () => ({
            title: "",
            subtitle: "",
            message: 'Hello Alert Vue!',
            type: 'alert-warning',
            seen: false,
            hide_timer_handle: null,
            progress_timer_handle: null,
            progress_counter: 1,
            autohide: true,
            delay_ms: 10000,
            anim_delay: 400,
            remaining_delay: 10000,
            remaining_secs: 10,
            remaining_percent: 100,
            bar_variant: "light"
        }),
        methods: {
            mouseOver: function (e) {
                if (this.hide_timer_handle !== null) {
                    clearTimeout(this.hide_timer_handle);
                }
                this.disableProgress();
            },
            mouseOut: function (e) {
                if (this.autohide) {
                    this.hide_timer_handle = this.timeout(() => {
                        this.seen = false;
                        this.hide_timer_handle = null;
                    }, this.delay_ms);
                    this.enableProgress();
                }
            },
            enableProgress: function () {
                this.disableProgress();
                let total_remaining_secs = this.delay_ms / 1000;
                this.progress_timer_handle = setInterval(() => {
                    this.remaining_delay -= 200;
                    let now_percent = ++this.progress_counter / (this.delay_ms / 200.0);
                    this.remaining_percent = (100 - Math.round(now_percent * 100));
                    if (this.remaining_percent > 50) {} else if (this.remaining_percent > 25) {
                        this.bar_variant = "warning";
                    } else {
                        this.bar_variant = "danger";
                    }
                    this.remaining_secs = total_remaining_secs - Math.floor(total_remaining_secs * now_percent);
                }, 200);
            },
            disableProgress: function () {
                clearTimeout(this.progress_timer_handle);
                this.progress_counter = 1;
                this.remaining_delay = this.delay_ms;
                this.remaining_secs = this.delay_ms / 1000;
                this.remaining_percent = 100;
                this.bar_variant = "light";
            },
            show: function (opts) {
                if (this.seen) {
                    this.seen = false;
                    // the slide up animation is 0.4s
                    this.timeout(() => this.setData(opts), this.anim_delay);
                } else {
                    this.setData(opts);
                }
            },
            setData: function (opts) {
                // normal usage, you want to attach event to the element in the alert window
                if (typeof opts.callback == "function") {
                    this.timeout(opts.callback, this.anim_delay);
                }
                switch (opts.type || opts.variant) {
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
                this.delay_ms = opts.delay_ms || opts.delay || opts.timeout || 10000;
                this.type = opts.type;
                this.seen = true;
            },
            randAnimation: function () {
                if (this.animated_opts) {
                    let count = this.animated_opts.length;
                    let this_time = this.animated_opts[rand(count)];
                    this.animated_in = `${this_time.in} once-anim-cfg`;
                    this.animated_out = `${this_time.out} once-anim-cfg`;
                }
            },
            enter: function () {},
            leave: function () {
                /*this.randAnimation();*/ },
            afterEnter: function () {
                // close alert after 15 secs (default)
                if (this.autohide) {
                    if (this.hide_timer_handle !== null) {
                        clearTimeout(this.hide_timer_handle);
                    }
                    this.hide_timer_handle = this.timeout(() => {
                        this.seen = false;
                        this.hide_timer_handle = null;
                    }, this.delay_ms);
                    this.enableProgress();
                }
            },
            afterLeave: function () {
                this.disableProgress();
            }
        },
        created: function () {
            this.randAnimation();
        }
    });

    Vue.component("lah-header", {
        template: `<lah-transition slide-down>
            <b-navbar toggleable="lg" type="dark" variant="dark" class="mb-3 shadow" fixed="top" :style="bgStyle">
                <lah-fa-icon size="2x" variant="light" class="mr-2" :icon="icon"></lah-fa-icon>
                <b-navbar-brand :href="location.href" v-html="leading"></b-navbar-brand>
                <b-navbar-toggle target="nav-collapse"></b-navbar-toggle>
                <b-collapse id="nav-collapse" is-nav>
                    <lah-transition appear>
                        <b-navbar-nav>
                            <b-nav-item
                                v-for="(link, index) in links"
                                v-if="link.need_admin ? isAdmin || false : true"
                                @mouseenter="animate($event)"
                                :href="Array.isArray(link.url) ? link.url[0] : link.url"
                            >
                                <lah-fa-icon :icon="link.icon" :class="activeCss(link)">{{link.text}}</lah-fa-icon>
                                <lah-fa-icon icon="caret-down" :id="'lah-header-nav-'+index" v-if="hasSubMenu(link)" @click="event.preventDefault()"></lah-fa-icon>
                                <b-popover v-if="hasSubMenu(link)" :target="'lah-header-nav-'+index" triggers="hover focus click" placement="bottom" delay="100" offset="-50">
                                    <div v-for="(clink, cindex) in link.children" class="m-2" @mouseenter="animate($event)"><a class="text-decoration-none text-primary" :href="Array.isArray(clink.url) ? clink.url[0] : clink.url"><lah-fa-icon :icon="clink.icon">{{clink.text}}</lah-fa-icon></a></div>
                                    <!--<template v-slot:title><lah-fa-icon icon="angle-double-down"><span class="font-weight-bold s-95 align-top text-muted">其他連結</span></lah-fa-icon></template>-->
                                </b-popover>
                            </b-nav-item>
                        </b-navbar-nav>
                    </lah-transition>
                    <b-navbar-nav @click="location.href='message.html'" class="ml-auto mr-2" style="cursor: pointer;" :title="avatar_badge+'則未讀訊息'">
                        <b-avatar v-if="showBadge" icon="people-fill" variant="light" :badge="avatar_badge" badge-variant="primary" id="header-user-icon" size="2.8rem" :src="avatar_src"></b-avatar>
                        <b-avatar v-else icon="people-fill" variant="light" id="header-user-icon" size="2.8rem" :src="avatar_src"></b-avatar>
                        <b-popover ref="fun" target="header-user-icon" placement="left" :show="fri_noon">
                            <i class="far fa-laugh-wink fa-lg ld ld-swing"></i> 快放假了~離下班只剩 {{left_hours}} 小時
                        </b-popover>
                        <b-popover target="header-user-icon" triggers="hover focus" placement="bottom" delay="350">
                            <lah-user-message-history ref="message" :ip="myip" count=5 title="最新訊息" class="mb-2" :tabs="true" :tabs-end="true"></lah-user-message-history>
                            <lah-user-ext class="mb-2"></lah-user-ext>
                            <lah-user-message-reservation class="mb-2"></lah-user-message-reservation>
                            <b-button block @click.stop="clearCache" variant="outline-secondary" size="sm"><lah-fa-icon icon="broom"> 清除快取資料</lah-fa-icon></b-button>
                        </b-popover>
                    </b-navbar-nav>
                </b-collapse>
            </b-navbar>
        </lah-transition>`,
        data: () => ({
            svr_info: undefined,
            fri_noon: false,
            icon: "question",
            leading: "Unknown",
            active: undefined,
            avatar_badge: false
        }),
        computed: {
            showBadge() {
                return this.avatar_badge > 0
            },
            enableUserCardPopover() {
                return !this.empty(this.myip)
            },
            url() {
                let page_url = new URL(location.href).pathname.substring(1);
                if (this.empty(page_url)) {
                    return 'index.html';
                }
                return page_url;
            },
            bgStyle() {
                let day_of_week = new Date().getDay();
                switch (day_of_week) {
                    case 1:
                        return 'background-color: #343a40 !important;'; // dark
                    case 2:
                        return 'background-color: #565658 !important;';
                    case 3:
                        return 'background-color: #646366 !important;';
                    case 4:
                        return 'background-color: #707073 !important;';
                    case 5:
                    default:
                        return 'background-color: #28a745 !important;'; // green
                }
            },
            avatar_src() {
                return this.empty(this.myname) ? 'get_user_img.php?name=not_found' : `get_user_img.php?name=${this.myname}_avatar`
            },
            left_hours() {
                let hours = new Date().getHours();
                return 17 - hours;
            },
            server_ip() { return this.empty(this.svr_info) ? '127.0.0.1' : this.svr_info.ips[0]; },
            links() {
                return [{
                    text: `系管看板`,
                    url: "dashboard.html",
                    icon: "cubes",
                    need_admin: true,
                    children: [{
                        text: `使用者管理`,
                        url: `http://${location.host}:8080/admin/users`,
                        icon: "users",
                        need_admin: true
                    }, {
                        text: "每日檢核表",
                        url: "checklist.html",
                        icon: "check-double",
                        need_admin: true
                    }, {
                        text: "記錄瀏覽",
                        url: "tasklog.html",
                        icon: "paw",
                        need_admin: true
                    }, {
                        text: `測試`,
                        url: "test.html",
                        icon: "tools",
                        need_admin: true
                    }, {
                        text: `測試PHP`,
                        url: "debug.php",
                        icon: "tools",
                        need_admin: true
                    }]
                }, {
                    text: "監控看板",
                    url: "monitor.html",
                    icon: "desktop",
                    need_admin: true,
                    children: [{
                        text: "跨所AP看板",
                        url: "monitor_cross_ap.html",
                        icon: "server",
                        need_admin: true
                    }, {
                        text: "AP連線數看板",
                        url: "monitor_ap_conn.html",
                        icon: "user-friends",
                        need_admin: true
                    }, {
                        text: "同步異動監控",
                        url: "monitor_lxhweb.html",
                        icon: "tv",
                        need_admin: true
                    }]
                }, {
                    text: "今日案件",
                    url: ["index.html", "/"],
                    icon: "briefcase",
                    need_admin: false
                }, {
                    text: "逾期案件",
                    url: "overdue_reg_cases.html",
                    icon: "calendar-alt",
                    need_admin: false,
                    children: []
                }, {
                    text: `統計看板`,
                    url: "stats.html",
                    icon: "laptop-house",
                    need_admin: false
                }, {
                    text: "信差歷史訊息",
                    url: "message.html",
                    icon: "comments",
                    need_admin: false
                },{
                    text: "體溫紀錄",
                    url: "temperature.html",
                    icon: "head-side-mask",
                    need_admin: false
                }, {
                    text: `業務小幫手`,
                    url: "helper.html",
                    icon: "hands-helping",
                    need_admin: false,
                    children: [/*{
                        text: `組織圖`,
                        url: "org.html",
                        icon: "sitemap",
                        need_admin: false
                    }, */{
                        text: `航空城`,
                        url: "project/aerotropolis/index.html",
                        icon: "plane-departure",
                        need_admin: true
                    }, {
                        text: "繼承應繼分",
                        url: "heir_share.html",
                        icon: "chart-pie",
                        need_admin: false
                    }, {
                        text: "使用者查詢",
                        url: "user.html",
                        icon: "users",
                        need_admin: false
                    }]
                }];
            }
        },
        methods: {
            activeCss: function (link) {
                let ret = "";
                if (Array.isArray(link.url)) {
                    for (let i = 0; i < link.url.length; i++) {
                        ret = this.css(link.url[i]);
                        if (!this.empty(ret)) break;
                    }
                } else {
                    ret = this.css(link.url);
                }
                if (this.empty(ret)) {
                    // recursive checking
                    if (Array.isArray(link.children)) {
                        link.children.forEach(child => this.activeCss(child));
                    }
                } else {
                    // store detected active link
                    this.active = link
                }

                return ret;
            },
            css: function (url) {
                if (this.url == url) {
                    return "font-weight-bold text-white";
                }
                return "";
            },
            setLeading: function (link) {
                if (Array.isArray(link.url)) {
                    link.url.forEach((this_url, idx) => {
                        if (this.url == this_url) {
                            this.icon = link.icon;
                            this.leading = link.text;
                        }
                    });
                } else if (this.url == link.url) {
                    this.icon = link.icon;
                    this.leading = link.text;
                } else if (Array.isArray(link.children)) {
                    // recursive down to check children
                    link.children.forEach(child => this.setLeading(child));
                }
            },
            animate(e) {
                // add pulse effect for the links
                this.animated(e.target, this.fri_noon ? {} : {
                    name: "pulse"
                });
            },
            hasSubMenu(link) {
                return !this.empty(link.children)
            },
            userNotFound: function (input) {
                this.$store.commit('myip', null);
                this.$warn(`找不到 ${input} 的使用者資訊，無法顯示目前使用者的卡片。`);
            },
            userFound: function (name) {

            },
            checkAuthority: async function () {
                if (this.isAdmin === undefined) {
                    this.$log('[lah-header] Checking authority ... ');
                    await this.$store.dispatch('authenticate');
                    this.$log('[lah-header]', this.authority);
                }
                if (!this.active || (this.active.need_admin && !this.isAdmin)) {
                    $('body').html("<h3 class='text-center m-5 font-weight-bold'><a href='javascript:history.back()' class='text-danger'>限制存取區域，請返回上一頁！</a></h3>");
                }
                $("body section:first-child").removeClass("hide");
            },
            clearCache: function () {
                this.$confirm(`清除全部暫存資料？`, () => {
                    this.$lf.clear().then(() => {
                        this.notify({
                            title: "清除快取",
                            message: "已清除快取紀錄，請重新整理頁面。",
                            type: "success"
                        });
                    });
                });
            },
            setUnreadMessageCount: function () {
                if (!this.disableMSDBQuery) {
                    this.$http.post(CONFIG.API.JSON.MSSQL, {
                        type: 'user_unread_message',
                        ip: this.myip,
                        timeout: 5000
                    }).then(res => {
                        this.avatar_badge = res.data.data_count || false;
                        this.$root.$emit(CONFIG.LAH_ROOT_EVENT ? CONFIG.LAH_ROOT_EVENT.MESSAGE_UNREAD : 'lah::message::unread', {
                            count: res.data.data_count,
                            ip: this.myip
                        });
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {

                    });
                }
            },
            clearCache: function () {
                this.$lf.clear().then(() => {
                    this.notify({
                        title: '清除快取',
                        message: '快取資料已清除，請重新整理頁面。',
                        type: "success"
                    })
                })
            },
            setServerInfo() {
                this.getLocalCache('server-info').then((json) => {
                    if (json === false) {
                        this.isBusy = true
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: 'svr'
                        }).then((res) => {
                            this.svr_info = res.data;
                            this.setLocalCache('server-info', res.data, 86400000) // cache for a day
                        }).catch((err) => {
                            this.$error(err)
                        }).finally(() => {
                            this.isBusy = false
                        })
                    } else {
                        this.svr_info = json;
                    }
                })
            }
        },
        created() {
            this.setServerInfo();
            let day_of_week = new Date().getDay();
            let hours = new Date().getHours();
            this.fri_noon = day_of_week == 5 && hours < 17 && hours > 12;
            // delay 5s to get the unread message
            this.timeout(() => this.setUnreadMessageCount(), 5000);
        },
        mounted() {
            this.links.forEach(this.setLeading);
            this.checkAuthority();
        }
    });

    Vue.component("lah-footer", {
        template: `<lah-transition slide-up appear>
            <p v-if="show" :class="classes">
                <span>
                    <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!">
                        <i class="fab fa-github fa-lg text-dark"></i>
                    </a>
                    <strong><i class="far fa-copyright"></i> <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
                    <a href="https://vuejs.org/" target="_blank" title="Learn Vue JS!">
                        <i class="text-success fab fa-vuejs fa-lg"></i>
                    </a>
                </span>
            </p>
        </lah-transition>`,
        data: () => ({
            show: true,
            leave_time: 10000,
            classes: ['text-muted', 'fixed-bottom', 'my-2', 'mx-3', 'bg-white', 'border', 'rounded', 'text-center', 'p-2', 'small', 'shadow-xl']
        }),
        mounted() {
            this.timeout(() => this.show = false, this.leave_time);
        }
    });

    Vue.component("lah-cache-mgt", {
        template: `<b-card no-body>
            <template v-slot:header>
                <div class="d-flex w-100 justify-content-between mb-0">
                    <h6 class="my-auto"><lah-fa-icon icon="hand-sparkles"></lah-fa-icon> 清除快取資料</h6>
                    <b-form-checkbox inline v-model="enable" switch class="my-auto">顯示細節</b-form-checkbox>
                    <b-button @click="clear" size="sm" variant="danger">清除 <b-badge variant="light" pill>{{count}}</b-badge></b-button>
                </div>
            </template>
            <b-list-group v-if="enable" class="small" style="max-height: 300px; overflow: auto;">
                <transition-group name="list" style="z-index: 0 !important;">
                    <b-list-group-item button v-for="(item, idx) in all" :key="item.key" v-b-popover.focus="JSON.stringify(item.val)">
                        <b-button-close @click="del(item.key, idx)" style="font-size: 1rem; color: red;"></b-button-close>
                        <div class="truncate font-weight-bold">{{item.key}}</div>
                    </b-list-group-item>
                </transition-group>
            </b-list-group>
        </b-card>`,
        data: () => ({
            all: [],
            enable: false
        }),
        watch: {
            enable(val) {
                this.all = [];
                if (val) {
                    this.isBusy = true;
                    this.$lf.keys().then(keys => {
                        keys.forEach(async key => {
                            const val = await this.$lf.getItem(key);
                            this.all.push({
                                key: key,
                                val: val
                            });
                        });
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            }
        },
        computed: {
            count() {
                return this.all ? this.all.length : 0
            }
        },
        methods: {
            async get(key) {
                let valObj = await this.$lf.getItem(key);
                return typeof valObj == 'object' ? valObj.value : valObj;
            },
            del(key, idx) {
                this.$confirm(`清除 ${key} 快取紀錄？`, () => {
                    this.$lf.removeItem(key).then(() => {
                        for (let i = 0; i < this.all.length; i++) {
                            if (this.all[i].key == key) {
                                this.all.splice(i, 1);
                                return true;
                            }
                        }
                    });
                });
            },
            clear() {
                this.$confirm(`清除所有快取紀錄？`, () => {
                    this.$lf.clear().then(() => {
                        this.all = [];
                        this.notify({
                            title: '清除快取資料',
                            message: '已完成，請重新整理頁面。',
                            type: 'success'
                        });
                    });
                });
            }
        }
    });

    Vue.component("lah-button", {
        template: `<b-button
            :variant="variant"
            :size="size"
            :pill="pill"
            :block="block"
            :pressed="pressed"
            :class="noBorder ? 'border-0' : ''"
            :href="href"
            @mouseenter="mouseenter"
            @mouseleave="mouseleave"
            @blur="mouseleave"
            @click="emitClick($event)"
        >
            <lah-fa-icon :id="icon_id" :icon="icon" :prefix="fa_icon_prefix">
                <slot></slot>
                <b-badge v-if="!empty(badgeText)" :variant="badgeVariant" :pill="badgePill">{{badgeText}}</b-badge>
            </lah-fa-icon>
      </b-button>`,
        props: {
            href: { type: String, default: ''},
            noBorder: { type: Boolean, default: false },
            variant: {
                type: String,
                default: 'outline-primary'
            },
            size: {
                type: String,
                default: 'sm'
            },
            icon: {
                type: String,
                default: 'question'
            },
            regular: {
                type: Boolean,
                default: false
            },
            brand: {
                type: Boolean,
                default: false
            },
            action: {
                type: String,
                default: undefined
            },
            click: {
                type: Function,
                default: console.log
            },
            pill: {
                type: Boolean,
                default: false
            },
            block: {
                type: Boolean,
                default: false
            },
            pressed: {
                type: Boolean,
                default: false
            },
            badgeText: { type: String, default: '' },
            badgeVariant: { type: String, default: 'light' },
            badgePill: { type: Boolean, default: true }
        },
        data: () => ({
            icon_id: 'xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx'
        }),
        watch: {},
        computed: {
            fa_icon_prefix() {
                return this.brand ? 'fab' : this.regular ? 'far' : 'fas';
            }
        },
        methods: {
            emitClick(evt) {
                this.$emit('click', this.click);
                evt.stopPropagation();
            },
            mouseenter() {
                /**
                 *  Land-Affairs-Helper"ld-heartbeat", "ld-beat", "ld-blink", "ld-bounce", "ld-bounceAlt", "ld-breath", "ld-wrench", "ld-surprise",
                    "ld-clock", "ld-jump", "ld-hit", "ld-fade", "ld-flip", "ld-float", "ld-move-ltr", "ld-tremble", "ld-tick",
                    "ld-move-rtl", "ld-move-ttb", "ld-move-btt", "ld-move-fade-ltr", "ld-move-fade-rtl", "ld-move-fade-ttb",
                    "ld-move-fade-btt", "ld-dim", "ld-swing", "ld-wander", "ld-pulse", "ld-cycle", "ld-cycle-alt", "ld-damage",
                    "ld-fade", "ld-flip", "ld-flip-h", "ld-flip-v", "ld-float", "ld-jelly", "ld-jelly-alt", "ld-jingle",
                    "ld-measure", "ld-metronome", "ld-orbit", "ld-rubber-h", "ld-rubber-v", "ld-rush-btt", "ld-rush-ttb",
                    "ld-rush-ltr", "ld-rush-rtl", "ld-shake-h", "ld-shake-v", "ld-shiver", "ld-skew", "ld-skew-alt", "ld-slide-btt",
                    "ld-slide-ltr", "ld-slide-rtl", "ld-slide-ttb", "ld-smash", "ld-spin", "ld-spin-fast", "ld-squeeze",
                    "ld-swim", "ld-swing", "ld-tick-alt", "ld-vortex", "ld-vortex-alt", "ld-wander-h", "ld-wander-v"
                 */
                let movement = this.action ? `ld-${this.action}` : 'ld-jump';
                // movement is "undefined" will be random effect
                addLDAnimation(`#${this.icon_id}`, movement);
            },
            mouseleave() {
                clearLDAnimation(`#${this.icon_id}`)
            }
        },
        created() {
            this.icon_id = this.uuid()
        }
    });

    // need to include chart.min.js (chart.js) first.
    Vue.component("lah-chart", {
        template: `<b-card no-body class="border-0">
            <canvas :id="id">圖形初始化失敗</canvas>
        </b-card>`,
        props: {
            type: {
                type: String,
                default: 'bar'
            },
            label: {
                type: String,
                default: '統計圖表'
            },
            opacity: {
                type: Number,
                default: 0.6
            },
            items: {
                type: Array,
                default: []
            },
            tooltip: {
                type: Function,
                default: function (entry) {
                    // add percent ratio to the label
                    let sum = entry.dataset.data.reduce(function (previousValue, currentValue, currentIndex, array) {
                        return previousValue + currentValue;
                    });
                    let currentVal = entry.dataset.data[entry.dataIndex];
                    let percent = Math.round(((currentVal / sum) * 100));
                    if (isNaN(percent)) return ` ${entry.label} : ${currentVal}`;
                    return ` ${entry.label} : ${currentVal} [${percent}%]`;
                }
            },
            bgColor: {
                type: Function,
                default: function (dataset_item, opacity) {
                    return `rgb(${this.rand(255)}, ${this.rand(255)}, ${this.rand(255)}, ${opacity})`;
                }
            },
            borderColor: {
                type: String,
                default: `rgb(22, 22, 22)`
            },
            yAxes: {
                type: Boolean,
                default: true
            },
            xAxes: {
                type: Boolean,
                default: true
            },
            legend: {
                type: Boolean,
                default: true
            },
            beginAtZero: {
                type: Boolean,
                default: true
            },
            title: {
                type: String,
                default: ''
            },
            titlePos: {
                type: String,
                default: 'top'
            },
            aspectRatio: { type: Number, default: 2}
        },
        data: () => ({
            id: null,
            inst: null,
            chartData: null,
            update_timer: null,
            resize_timer: null
        }),
        computed: {
            style() { return `max-height: ${window.innerHeight - 185}px; max-width: ${window.innerWidth * 0.75}px;` }
        },
        watch: {
            type: function (val) {
                this.timeout(this.buildChart, 0)
            },
            chartData: function (newObj) {
                this.timeout(this.buildChart, 0)
            },
            items: function (newItems) {
                this.setData(newItems)
            }
        },
        methods: {
            update: function () {
                clearTimeout(this.update_timer);
                this.update_timer = this.timeout(() => {
                    if (this.inst) this.inst.update();
                }, 100);
            },
            resetData: function () {
                this.chartData = {
                    labels: [],
                    legend: {
                        display: true
                    },
                    datasets: [{
                        label: this.label,
                        backgroundColor: [],
                        data: [],
                        borderColor: this.borderColor,
                        order: 1,
                        opacity: this.opacity,
                        snapGaps: true,
                        borderWidth: 1
                    }]
                };
            },
            setData: function (items) {
                this.resetData();
                let opacity = this.chartData.datasets[0].opacity;
                items.forEach(item => {
                    this.chartData.labels.push(item[0]); // first element is label
                    this.chartData.datasets[0].data.push(item[1]); // second element is data count
                    // randoom color for this item
                    this.chartData.datasets[0].backgroundColor.push(this.bgColor(item, opacity));
                });
                this.timeout(this.buildChart, 0);
            },
            changeValue(label, value) {
                let found_idx = undefined;
                this.chartData.labels.find((olabel, idx, array) => {
                    if (olabel == label) found_idx = idx;
                    return olabel == label;
                })
                if (found_idx !== undefined) {
                    this.chartData.datasets[0].data[found_idx] = value;
                    // also update background color as well
                    this.chartData.datasets[0].backgroundColor[found_idx] = this.bgColor([label, value], 0.6);
                    // redraw the chart
                    Vue.nextTick(this.update);
                } else {
                    this.$warn(`lah-chart: Not found "${label}" in dataset, the ${value} will not be updated.`, this.chartData);
                }
            },
            buildChart: function (opts = { plugins: {} }) {
                if (this.inst) {
                    // reset the chart
                    this.inst.destroy();
                    this.inst = null;
                }
                // keep only one dataset inside
                if (this.chartData.datasets.length > 1) {
                    this.chartData.datasets = this.chartData.datasets.slice(0, 1);
                }
                this.chartData.datasets[0].label = this.label;
                switch (this.type) {
                    case "pie":
                    case "polarArea":
                    case "doughnut":
                        // put legend to the right for some chart type
                        opts.plugins.legend = {
                            display: this.legend,
                            position: opts.legend_pos || 'right'
                        };
                        break;
                    case "radar":
                        break;
                    default:
                        opts.scales = {
                            yAxes: {
                                display: this.yAxes,
                                beginAtZero: this.beginAtZero
                            },
                            xAxes: {
                                display: this.xAxes
                            }
                        };
                }
                // update title
                opts.plugins.title = {
                    display: !this.empty(this.title),
                    text: this.title,
                    position: this.titlePos
                };
                // use chart.js directly
                // let ctx = this.$el.childNodes[0];
                let ctx = $(`#${this.id}`);
                let that = this;
                this.inst = new Chart(ctx, {
                    type: this.type,
                    data: this.chartData,
                    options: Object.assign({
                        showTooltips: true,
                        responsive: true, 
                        maintainAspectRatio: true,
                        aspectRatio: that.aspectRatio,
                        elements: {
                            point: { pointStyle: 'circle', radius: 4, hoverRadius: 6, borderWidth: 1, hoverBorderWidth: 2 },
                            line: { tension: this.type === 'line' ? 0.35 : 0.1, fill: true, stepped: false }
                        },
                        tooltips: {
                            callbacks: {
                                label: this.tooltip
                            }
                        },
                        onClick: function (e) {
                            let payload = {};
                            /**
                             * getElementAtEvent is replaced with chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false)
                             * getElementsAtEvent is replaced with chart.getElementsAtEventForMode(e, 'index', { intersect: true }, false)
                             * getElementsAtXAxis is replaced with chart.getElementsAtEventForMode(e, 'index', { intersect: false }, false)
                             * getDatasetAtEvent is replaced with chart.getElementsAtEventForMode(e, 'dataset', { intersect: true }, false)
                             */
                            let element = that.inst.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
                            if (!that.empty(element)) {
                                payload["point"] = element[0];
                                if (payload["point"]) {
                                    // point e.g. {element: e, datasetIndex: 0, index: 14}
                                    let idx = payload["point"].index;
                                    let dataset_idx = payload["point"].datasetIndex; // only one dataset, it should be always be 0
                                    payload["label"] = that.inst.data.labels[idx];
                                    payload["value"] = that.inst.data.datasets[dataset_idx].data[idx];
                                }
                                // parent uses a handle function to catch the event, e.g. catchClick(e, payload) { ... }
                                that.$emit("click", e, payload);
                            }
                        }
                    }, opts)
                });
                // sometimes the char doesn't show up properly ... so add this fix to update it
                this.timeout(this.update, 400);
            },
            toBase64Image: function () {
                return this.inst.toBase64Image()
            },
            downloadBase64PNG: function (filename = "download.png") {
                const link = document.createElement('a');
                link.href = this.toBase64Image();
                link.setAttribute("download", filename);
                document.body.appendChild(link);
                link.click();
                //afterwards we remove the element again
                link.remove();
            }
        },
        created() { this.id = this.uuid() },
        mounted() {
            this.setData(this.items);
            // this.style = `max-height: ${window.innerHeight - 185}px; max-width: ${window.innerWidth - 20}px;`;
            // window.addEventListener("resize", e => {
            //     clearTimeout(this.resize_timer);
            //     this.resize_timer = this.timeout(() => {
            //         this.style = `max-height: ${window.innerHeight - 185}px; max-width: ${window.innerWidth - 20}px;`;
            //     }, 250);
            // });
        }
    });

    Vue.component("lah-user-id-input", {
        template: `<b-input-group :size="size">
            <b-input-group-prepend is-text>
                <div v-if="validate" class="my-auto"><b-avatar @click="usercard" button variant="success" size="1.2rem" :src="avatar_src" :data-id="ID" :data-name="name" class="usercard" :title="ID"></b-avatar> {{name}}</div>
                <lah-fa-icon v-else icon="user" prefix="far"> 使用者代碼</la-fa-icon>
            </b-input-group-prepend>
            <b-form-input
                ref="lah_user_id"
                v-model="id"
                placeholder="HBXXXX"
                class="no-cache"
                @input="$emit('input', id)"
                :state="validate"
            ></b-form-input>
        </b-input-group>`,
        props: ['value', 'size', 'validator', 'onlyOnBoard'],
        data: () => ({
            id: undefined,
            onboard_users: undefined
        }),
        watch: {
            value(val) {
                this.id = val
            },
            id(val) {
                this.id = this.empty(val) ? '' : val.toString().replace(/[^a-zA-Z0-9]/g, "")
            }
        },
        computed: {
            userNameMap() {
                return this.only_onboard ? this.onboard_user_names : this.userNames
            },
            ID() {
                return this.id ? this.id.toUpperCase() : null
            },
            name() {
                return this.userNameMap[this.ID] || null
            },
            validate() {
                return this.empty(this.id) ? null : this.validator ? this.validator : this.def_validator
            },
            def_validator() {
                return /*(/^HB\d{4}$/i).test(this.ID) || */ !this.empty(this.name)
            },
            avatar_src() {
                return `get_user_img.php?name=${this.name || 'not_found'}_avatar`
            },
            only_onboard() {
                return this.onlyOnBoard === true
            },
            onboard_user_names() {
                let names = {};
                if (this.onboard_users !== undefined) {
                    this.onboard_users.forEach(user => {
                        names[$.trim(user['id'])] = $.trim(user['name']);
                    });
                }
                return names;
            }
        },
        created() {
            if (this.only_onboard) {
                this.getLocalCache('onboard_users').then(cached => {
                    if (cached === false) {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.USER, {
                            type: 'on_board_users'
                        }).then(res => {
                            this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得在職使用者資料回傳值有誤【${res.data.status}】`)
                            this.onboard_users = res.data.raw;
                            this.setLocalCache('onboard_users', this.onboard_users, 24 * 60 * 60 * 1000); // 1 day
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    } else {
                        this.onboard_users = cached;
                    }
                });
            }
        },
        mounted() {
            this.id = this.value;
        }
    });

    Vue.component("lah-user-card", {
        template: `<div>
            <h6 v-show="!empty(title)"><i class="fas fa-user-circle"></i> {{title}}</h6>
            <b-card no-body v-if="found">
                <b-tabs card :end="useEndTabs" :pills="useEndTabs" :small="useEndTabs" fill>
                    <b-tab v-for="(user_data, idx) in user_rows" :title="user_data['id']" :active="idx == 0" class="clearfix">
                        <template v-slot:title>
                            <lah-fa-icon icon="id-card"> {{user_data['id']}}</lah-fa-icon>
                        </template>
                        <b-card-title>
                            <b-avatar button size="3rem" :src="photoUrl(user_data)" variant="light" @click="openPhoto(user_data)" v-if="useAvatar"></b-avatar>
                            {{user_data['name']}}
                        </b-card-title>
                        <b-card-sub-title>{{user_data['title']}}</b-card-sub-title>
                        <b-link @click="openPhoto(user_data)" v-if="!useAvatar">
                            <b-card-img
                                :src="photoUrl(user_data)"
                                :alt="user_data['name']"
                                class="img-thumbnail float-right mx-auto ml-2 shadow-xl"
                                style="max-width: 220px"
                            ></b-card-img>
                        </b-link>
                        <lah-user-description :user_data="user_data"></lah-user-description>
                    </b-tab>
                    <b-tab v-if="foundCount == 1 && !foundLeft && message_ui">
                        <template v-slot:title>
                            <lah-fa-icon icon="comment-dots" prefix="far"> 傳送信差</lah-fa-icon>
                        </template>
                        <lah-user-message-form :ID="ID" :NAME="foundName" no-body></lah-user-message-form>
                    </b-tab>
                </b-tabs>
            </b-card>
            <lah-fa-icon icon="exclamation-circle" size="lg" variant="danger" class="my-2" v-else>找不到使用者「{{name || id || ip}}」！</lah-fa-icon>
        </div>`,
        components: {
            "lah-user-description": {
                template: `<b-card-text class="small">
                    <lah-fa-icon icon="ban" variant="danger" action="breath" v-if="isLeft" class='mx-auto'> 已離職【{{user_data["offboard_date"]}}】</lah-fa-icon>
                    <div>ID：{{user_data["id"]}}</div>
                    <div v-if="isAdmin">電腦：{{user_data["ip"]}}</div>
                    <div>分機：{{user_data["ext"]}}</div>
                    <div v-if="isAdmin">生日：{{user_data["birthday"]}} <b-badge v-show="birthAge !== false" :variant="birthAgeVariant" pill>{{birthAge}}歲</b-badge></div>
                    <div>單位：{{user_data["unit"]}}</div>
                    <div>工作：{{user_data["work"]}}</div>
                    <div v-if="isAdmin">學歷：{{user_data["education"]}}</div>
                    <div v-if="isAdmin">考試：{{user_data["exam"]}}</div>
                    <div v-if="isAdmin">手機：{{user_data["cell"]}}</div>
                    <div v-if="isAdmin">到職：{{user_data["onboard_date"]}} <b-badge v-show="workAge !== false" :variant="workAgeVariant" pill>{{workAge}}年</b-badge></div>
                    <lah-user-ext v-if="me" class="mt-2 w-50"></lah-user-ext>
                </b-card-text>`,
                props: ['user_data'],
                data: () => ({
                    now: new Date(),
                    year: 31536000000
                }),
                computed: {
                    isLeft: function () {
                        return !this.empty(this.user_data['offboard_date']);
                    },
                    birthAgeVariant: function () {
                        let badge_age = this.birthAge;
                        if (badge_age < 30) {
                            return "success";
                        } else if (badge_age < 40) {
                            return "primary";
                        } else if (badge_age < 50) {
                            return "warning";
                        } else if (badge_age < 60) {
                            return "danger";
                        }
                        return "dark";
                    },
                    birthAge: function () {
                        let birth = this.user_data["birthday"];
                        if (birth) {
                            birth = this.toADDate(birth);
                            let temp = Date.parse(birth);
                            if (temp) {
                                let born = new Date(temp);
                                return ((this.now - born) / this.year).toFixed(1);
                            }
                        }
                        return false;
                    },
                    workAge: function () {
                        let AP_ON_DATE = this.user_data["onboard_date"];
                        let AP_OFF_JOB = this.isLeft ? 'Y' : 'N';
                        let AP_OFF_DATE = this.user_data["offboard_date"];

                        if (AP_ON_DATE != undefined && AP_ON_DATE != null) {
                            AP_ON_DATE = AP_ON_DATE.date ? AP_ON_DATE.date.split(" ")[0] : AP_ON_DATE;
                            AP_ON_DATE = this.toADDate(AP_ON_DATE);
                            let temp = Date.parse(AP_ON_DATE);
                            if (temp) {
                                let on = new Date(temp);
                                let now = this.now;
                                if (AP_OFF_JOB == "Y") {
                                    AP_OFF_DATE = this.toADDate(AP_OFF_DATE);
                                    temp = Date.parse(AP_OFF_DATE);
                                    if (temp) {
                                        // replace now Date to off board date
                                        now = new Date(temp);
                                    }
                                }
                                return ((now - on) / this.year).toFixed(1);
                            }
                        }
                        return false;
                    },
                    workAgeVariant: function () {
                        let work_age = this.workAge;
                        if (work_age < 5) {
                            return 'success';
                        } else if (work_age < 10) {
                            return 'primary';
                        } else if (work_age < 20) {
                            return 'warning';
                        }
                        return 'danger';
                    },
                    me() { return this.myinfo ? this.user_data['id'] == this.myinfo['id'] : false }
                },
                methods: {
                    toTWDate: function (ad_date) {
                        tw_date = ad_date.replace('/-/g', "/");
                        // detect if it is AD date
                        if (tw_date.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
                            // to TW date
                            tw_date = (parseInt(tw_date.substring(0, 4)) - 1911) + tw_date.substring(4);
                        }
                        return tw_date;
                    },
                    toADDate: function (tw_date) {
                        let ad_date = tw_date.replace('/-/g', "/");
                        // detect if it is TW date
                        if (ad_date.match(/^\d{3}\/\d{2}\/\d{2}$/)) {
                            // to AD date
                            ad_date = (parseInt(ad_date.substring(0, 3)) + 1911) + ad_date.substring(3);
                        }
                        return ad_date;
                    }
                }
            }
        },
        props: ['id', 'name', 'ip', 'title', 'avatar', 'inUserRows', 'noMessage'],
        data: () => ({
            user_rows: null,
            msg_title: '',
            msg_content: ''
        }),
        computed: {
            found: function () {
                return this.user_rows !== null && this.user_rows !== undefined
            },
            notFound: function () {
                return `找不到使用者 「${this.name || this.id || this.ip || this.myip}」`;
            },
            foundName: function () {
                return this.user_rows[0]["name"]
            },
            foundCount: function () {
                return this.user_rows.length
            },
            foundLeft: function () {
                return this.user_rows[0]["offboard_date"] != ''
            },
            useAvatar: function () {
                return !this.empty(this.avatar)
            },
            ID: function () {
                return this.user_rows ? this.user_rows[0]['id'] : null
            },
            useEndTabs() {
                return this.inUserRows ? this.inUserRows.length > 1 : false
            },
            message_ui() { return this.empty(this.noMessage) }
        },
        methods: {
            photoUrl: function (user_data) {
                if (this.useAvatar) {
                    return `get_user_img.php?name=${user_data['name']}_avatar`;
                }
                return `get_user_img.php?name=${user_data['name']}`;
            },
            openPhoto: function (user_data) {
                // get_user_img.php?name=${user_data['name']}
                //<b-img thumbnail fluid src="https://picsum.photos/250/250/?image=54" alt="Image 1"></b-img>
                this.msgbox({
                    title: `${user_data['name']}照片`,
                    message: this.$createElement("div", {
                        domProps: {
                            className: "text-center"
                        }
                    }, [this.$createElement("b-img", {
                        props: {
                            fluid: true,
                            src: `get_user_img.php?name=${user_data['name']}`,
                            alt: user_data['name'],
                            thumbnail: true
                        }
                    })]),
                    size: "lg",
                    backdrop_close: true
                });
            },
            cacheUserRows: function () {
                let payload = {};
                // basically cache for one day in localforage
                if (!this.empty(this.id)) {
                    payload[this.id] = this.user_rows;
                    this.setLocalCache(this.id, this.user_rows, this.dayMilliseconds);
                }
                if (!this.empty(this.name)) {
                    payload[this.name] = this.user_rows;
                    this.setLocalCache(this.name, this.user_rows, this.dayMilliseconds);
                }
                if (!this.empty(this.ip)) {
                    payload[this.ip] = this.user_rows;
                    this.setLocalCache(this.ip, this.user_rows, this.dayMilliseconds);
                }
                this.$store.commit('cache', payload);
                if (this.user_rows['ip'] == this.myip) {
                    this.$store.commit('myid', this.user_rows['id']);
                }
            },
            restoreUserRows: async function () {
                try {
                    // find in $store(in-memory)
                    let user_rows = this.cache.get(this.id) || this.cache.get(this.name) || this.cache.get(this.ip) || this.inUserRows;
                    if (this.empty(user_rows)) {
                        // find in localforage
                        user_rows = await this.getLocalCache(this.id) || await this.getLocalCache(this.name) || await this.getLocalCache(this.ip);
                        if (this.empty(user_rows)) {
                            return false;
                        } else {
                            // also put back to $store
                            let payload = {};
                            if (!this.empty(this.id)) {
                                payload[this.id] = user_rows;
                            }
                            if (!this.empty(this.name)) {
                                payload[this.name] = user_rows;
                            }
                            if (!this.empty(this.ip)) {
                                payload[this.ip] = user_rows;
                            }
                            this.$store.commit('cache', payload);
                        }
                    }
                    this.user_rows = user_rows || null;
                    if (this.user_rows && this.user_rows['ip'] == this.myip) {
                        this.$store.commit('myid', this.user_rows['id']);
                    }
                    this.$emit('found', this.foundName);
                } catch (err) {
                    console.error(err);
                }
                return this.user_rows !== null;
            }
        },
        async created() {
            const succeed_cached = await this.restoreUserRows();
            if (!succeed_cached) {
                if (!(this.name || this.id || this.ip)) this.ip = this.myip || await this.getLocalCache('myip');
                this.$http.post(CONFIG.API.JSON.USER, {
                    type: "user_info",
                    name: $.trim(this.name),
                    id: $.trim(this.id),
                    ip: $.trim(this.ip)
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.user_rows = res.data.raw;
                        this.cacheUserRows();
                        this.$emit('found', this.foundName);
                    } else {
                        console.warn(`找不到 '${this.name || this.id || this.ip}' 資料`);
                        this.$emit('notFound', this.name || this.id || this.ip);
                    }
                }).catch(err => {
                    this.error = err;
                    this.$emit('error', this.name || this.id || this.ip);
                });
            }
        }
    });

    Vue.component('lah-user-message-history', {
        template: `<div>
            <h6 v-show="!empty(title)"><lah-fa-icon icon="angle-double-right" variant="dark"></lah-fa-icon> {{title}} <b-form-spinbutton v-if="enable_spinbutton" v-model="count" min="1" size="sm" inline></b-form-spinbutton></h6>
            <b-card-group ref="group" v-if="ready" :columns="columns" :deck="!columns">
                <b-card no-body v-if="useTabs">
                    <b-tabs card :end="endTabs" :pills="endTabs" align="center" small>
                        <b-tab v-for="(message, index) in raws" :title="index+1">
                            <b-card-title title-tag="h6">
                                <lah-fa-icon v-if="message['done'] == 1" icon="eye" variant="muted" title="已看過"></lah-fa-icon>
                                <lah-fa-icon v-else icon="eye-slash" title="還沒看過！"></lah-fa-icon>
                                <strong class="align-middle">{{message['xname']}}</strong>
                            </b-card-title>
                            <b-card-sub-title sub-title-tag="small"><div class="text-right">{{message['sendtime']['date'].substring(0, 19)}}</div></b-card-sub-title>
                            <b-card-text v-html="format(message['xcontent'])" class="small"></b-card-text>
                        </b-tab>
                    </b-tabs>
                </b-card>
                <transition-group name="list" mode="out-in" v-else>
                    <b-card
                        v-for="(message, index) in raws"
                        class="overflow-hidden bg-light shadow-xl"
                        :border-variant="border(index)"
                        :key="'card_'+index"
                    >
                        <b-card-title title-tag="h6">
                            <strong class="align-middle">
                                <lah-fa-icon v-if="raws[index]['done'] != 1" icon="angle-double-right" variant="danger" action="wander"></lah-fa-icon>
                                <span :class="index < 3 ? 'text-danger h4 font-weight-bold' : ''">{{index+1}}</span>. 
                                {{message['xname']}}
                            </strong>
                            <span v-if="showCtlBtn(message['sendtime']['date'].substring(0, 19))">
                                <b-btn v-if="raws[index]['done'] != 1" size="sm" variant="outline-primary" @click.stop="read(message['sn'], index)" title="設為已讀" class="border-0"> <lah-fa-icon icon="eye-slash"></lah-fa-icon> </b-btn>
                                <b-btn v-else size="sm" variant="outline-secondary" @click.stop="unread(message['sn'], index)" title="設為未讀" class="border-0"> <lah-fa-icon :id="message['sn']" icon="eye"></lah-fa-icon> </b-btn>
                            </span>
                            <b-button-close v-if="showDeleteBtn(message)" @click="del(message['sn'])" title="刪除這個訊息"></b-button-close>
                        </b-card-title>
                        <b-card-sub-title sub-title-tag="small">
                            <div class="float-right">
                                <ul>
                                    <li>通知時間：{{message['sendtime']['date'].substring(0, 19)}}</li>
                                    <li>停止通知：{{message['enddate']['date'].substring(0, 19)}}</li>
                                    <li>傳送人員：{{message['sender']}}:{{message['sendCname']}}</li>
                                </ul>
                            </div>
                        </b-card-sub-title>
                        <b-card-text v-html="format(message['xcontent'])" class="small mt-2 clearfix"></b-card-text>
                    </b-card>
                </transition-group>
            </b-card-group>
            <lah-fa-icon variant="danger" icon="exclamation-circle" size="lg" v-else>{{notFound}}</lah-fa-icon>
        </div>`,
        props: ['id', 'name', 'ip', 'count', 'title', 'spinbutton', 'tabs', 'tabsEnd', 'noCache'],
        data: () => ({
            raws: undefined,
            urlPattern: /((http|https|ftp):\/\/[\w?=&.\/-;#~%-]+(?![\w\s?&.\/;#~%"=-]*>))/ig
        }),
        watch: {
            count: function (nVal, oVal) {
                this.load()
            }
        },
        computed: {
            ready: function () {
                return !this.empty(this.raws)
            },
            notFound: function () {
                return `「${this.name || this.id || this.ip || this.myip}」找不到信差訊息！`
            },
            columns: function () {
                return !this.useTabs && this.enable_spinbutton
            },
            enable_spinbutton: function () {
                return !this.empty(this.spinbutton)
            },
            useTabs: function () {
                return !this.empty(this.tabs)
            },
            endTabs: function () {
                return !this.empty(this.tabsEnd)
            },
            cache_prefix: function () {
                return this.id || this.name || this.ip || this.myip
            },
            cache_key: function () {
                return `${this.cache_prefix}-messeages`
            }
        },
        methods: {
            format: function (content) {
                return content
                    .replace(this.urlPattern, "<a href='$1' target='_blank' title='點擊前往'>$1</a>")
                    .replace(/\r\n/g, "<br />");
            },
            border: function (index) {
                return this.raws[index]['done'] == 0 ? 'danger' : 'secondary'
            },
            load: async function (force = false) {
                if (!this.disableMSDBQuery) {
                    if (this.isBusy) return;
                    try {
                        if (!this.empty(this.noCache) || force) await this.removeLocalCache(this.cache_key);
                        this.getLocalCache(this.cache_key).then(raws => {
                            if (raws !== false && raws.length == this.count) {
                                this.raws = raws;
                            } else if (raws !== false && raws.length >= this.count) {
                                this.raws = raws.slice(0, this.count);
                            } else {
                                this.isBusy = true;
                                this.$http.post(CONFIG.API.JSON.MSSQL, {
                                    type: "user_message",
                                    id: this.id,
                                    name: this.name,
                                    ip: this.ip || this.myip,
                                    count: this.count,
                                    timeout: 3000
                                }).then(res => {
                                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                        this.raws = res.data.raw
                                        this.setLocalCache(this.cache_key, this.raws, 60000); // 1 min
                                    } else {
                                        this.notify({
                                            title: "查詢信差訊息",
                                            message: res.data.message,
                                            type: "warning"
                                        });
                                    }
                                }).catch(err => {
                                    this.error = err;
                                }).finally(() => this.isBusy = false);
                            }
                        });
                    } catch (err) {
                        this.error = err;
                    }
                }
            },
            read(sn, idx) {
                if (!this.disableMSDBQuery) {
                    this.$http.post(CONFIG.API.JSON.MSSQL, {
                        type: "set_read_user_message",
                        sn: sn
                    }).then(res => {
                        this.notify({
                            title: "設定已讀取",
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? 'success' : 'warning'
                        });
                        this.raws[idx]['done'] = XHR_STATUS_CODE.SUCCESS_NORMAL ? 1 : 0;
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {

                    });
                }
            },
            unread(sn, idx) {
                if (!this.disableMSDBQuery) {
                    this.$http.post(CONFIG.API.JSON.MSSQL, {
                        type: "set_unread_user_message",
                        sn: sn
                    }).then(res => {
                        this.notify({
                            title: "設定未讀取",
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? 'success' : 'warning'
                        });
                        this.raws[idx]['done'] = XHR_STATUS_CODE.SUCCESS_NORMAL ? 0 : 1;
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {

                    });
                }
            },
            del(sn) {
                if (!this.disableMSDBQuery) {
                    this.$confirm('此動作無法復原，確定刪除本則訊息？', () => {
                        this.$http.post(CONFIG.API.JSON.MSSQL, {
                            type: "del_user_message",
                            sn: sn
                        }).then(res => {
                            this.notify({
                                title: "刪除訊息",
                                message: res.data.message,
                                type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? 'success' : 'warning'
                            });
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.load(true);
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {

                        });
                    });
                }
            },
            showCtlBtn(snd_time) {
                const date1 = +new Date();
                const date2 = +new Date(snd_time.replace(' ', 'T'));
                return date1 - date2 > 0;
            },
            showDeleteBtn(message) {
                return $.trim(message['sender']) == $.trim(this.myid) && message['done'] == 0
            }
        },
        created() {
            let parsed = parseInt(this.count);
            this.count = isNaN(parsed) ? 3 : parsed;
            this.$root.$on(CONFIG.LAH_ROOT_EVENT.MESSAGE_UNREAD, payload => {
                if (payload.count > 0) {
                    this.notify({
                        message: `您有 ${payload.count} 則未讀訊息。`,
                        type: "warning"
                    })
                }
            });
            this.load();
        }
    });

    Vue.component('lah-user-message-reservation', {
        components: {
            'lah-user-message-reservation-ui': {
                template: `<div>
                    <b-input-group class="mb-1">
                        <b-input-group-text>
                            時間
                        </b-input-group-text>
                        <b-timepicker
                            hide-header
                            no-close-button
                            reset-value="17:00:00"
                            v-model="sendtime"
                            title="預約時間"
                            label-close-button="確定"
                            label-reset-button="預設值"
                            size="sm"
                            dropdown
                            @shown="shown"
                            :state="valid_sendtime"
                            :hour12="false"
                        />
                    </b-input-group>
                    <b-input-group>
                        <b-input-group-text>
                            訊息
                        </b-input-group-text>
                        <b-form-input
                            v-model="message"
                            type="text"
                            placeholder="... 訊息 ..."
                            :state="!empty(message)"
                            inline
                            @keyup.enter="send"
                            class="no-cache my-auto"
                        />
                        <template #append>
                            <lah-button icon="paper-plane" prefix="far" size="sm" title="送出" variant="outline-primary" @click="send" :disabled="disabled_send">確定</lah-button>
                        </template>
                    </b-input-group>
                </div>`,
                data: () => ({
                    message: '',
                    sendtime: '17:00:00',
                    sendtime_ms: +new Date(),
                    buffer_ms: 5 * 60 * 1000,
                    nowDate: new Date()
                }),
                watch: {
                    sendtime(nVal, oVal) {
                        this.sendtime_ms = +new Date(this.ad_date + "T" + nVal);
                        this.message = `${nVal} 提醒我`;
                        this.title = `預計 ${this.droptime} 忽略本則訊息`;
                    }
                },
                computed: {
                    ad_date() {
                        return this.nowDate.getFullYear() + "-" + ("0" + (this.nowDate.getMonth() + 1)).slice(-2) + "-" + ("0" + this.nowDate.getDate()).slice(-2)
                    },
                    droptime() {
                        let dropdate = new Date(this.sendtime_ms + this.buffer_ms);
                        return ("0" + dropdate.getHours()).slice(-2) + ":" +
                            ("0" + dropdate.getMinutes()).slice(-2) + ":" +
                            ("0" + dropdate.getSeconds()).slice(-2);
                    },
                    valid_sendtime() { return  this.sendtime_ms > this.nowDate.getTime() },
                    disabled_send() { return !this.valid_sendtime || this.empty(this.message) }
                },
                methods: {
                    send() {
                        if (this.disableMSDBQuery) {
                            this.$warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping lah-user-message-reservation::send.");
                            return;
                        }
                        if (this.empty(this.message)) {
                            this.notify({
                                message: '請輸入訊息',
                                type: "warning"
                            })
                            return;
                        }
                        if (this.nowDate.getTime() >= this.sendtime_ms) {
                            this.notify({
                                message: '請選擇現在之後的時間',
                                type: "warning"
                            })
                            return;
                        }
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.MSSQL, {
                            type: "send_message",
                            title: this.message,
                            content: this.message,
                            who: this.myid,
                            send_time: this.sendtime,
                            end_time: this.droptime
                        }).then(res => {
                            this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + res.data.message + "】");
                            this.title = '';
                            this.notify({
                                title: "傳送訊息",
                                message: res.data.message
                            });
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                            this.message = '';
                        });
                    },
                    shown() {
                        this.nowDate = new Date();
                        let choosed_ms = +new Date(this.ad_date + "T" + this.sendtime);
                        let now_ms = this.nowDate.getTime() + this.buffer_ms; // add 5 mins buffer
                        if (now_ms >= choosed_ms) {
                            let an_hour_later = new Date(now_ms + 10 * 60 * 1000);
                            this.sendtime = ("0" + an_hour_later.getHours()).slice(-2) + ":" +
                                ("0" + an_hour_later.getMinutes()).slice(-2) + ":" +
                                ("0" + an_hour_later.getSeconds()).slice(-2);
                        }
                    }
                }
            }
        },
        template: `<div v-if="!empty(myid)">
            <h6 v-if="heading"><lah-fa-icon icon="angle-double-right" variant="dark"></lah-fa-icon> 信差提醒鬧鐘</h6>
            <b-card :no-body="heading" :class="border">
                <template v-slot:header v-if="!heading">
                    <div class="d-flex w-100 justify-content-between mb-0">
                        <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="bell" prefix="far">{{label}}</lah-fa-icon></h6>
                        <lah-button icon="history" variant="link" href="message.html" class="text-decoration-none">歷史資料</lah-button>
                        <lah-button icon="question" @click="help" size="sm" variant="outline-success" no-border></lah-button>
                    </div>
                </template>
                <lah-user-message-reservation-ui></lah-user-message-reservation-ui>
            </b-card>
        </div>`,
        props: {
            heading: { type: Boolean, default: true },
            label: { type: String, default: '' }
        },
        computed: {
            border() { return this.heading ? 'border-0' : '' }
        },
        methods: {
            help() {
                this.msgbox({
                    title: "信差通知說明",
                    body: `<p>可依選取時間發送訊息給自己，以達到提醒的功能。</p><ul><li>選取預計發送時間</li><li>輸入訊息</li><li>傳送鍵送出</li></ul>`,
                    size: "md"
                });
            },
        }
    });

    Vue.component('lah-user-ext', {
        template: `<b-card no-body class="border-0" v-if="myinfo">
            <h6 v-if="heading" class="mb-2"><lah-fa-icon icon="angle-double-right" variant="dark"> 我的分機</lah-fa-icon></h6>
            <b-input-group size="sm" prepend="分機">
                <b-form-input
                    v-model="myinfo['ext']"
                    type="number"
                    @keyup.enter="update"
                    :state="validate"
                    class="no-cache"
                ></b-form-input>
                <template v-slot:append>
                    <lah-button icon="edit" variant="outline-primary" @click="update" title="更新" :disabled="!validate" v-show="need_update"></lah-button>
                </template>
            </b-input-group>
        </b-card>`,
        props: {
            heading: {
                type: Boolean,
                default: true
            }
        },
        data: () => ({
            orig_ext: 503
        }),
        watch: {
            myinfo(val) { this.orig_ext = val['ext'] }
        },
        computed: {
            validate() { return this.myinfo ? (this.myinfo['ext'] > 99 && this.myinfo['ext'] < 700) : false },
            need_update() { return this.orig_ext != this.myinfo['ext'] }
        },
        methods: {
            update() {
                if (this.validate) {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.USER, {
                        type: "upd_ext",
                        id: this.myinfo['id'],
                        ext: this.myinfo['ext']
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + res.data.message + "】");
                        this.notify({
                            title: "更新分機號碼",
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? 'success' : 'danger'
                        });
                        // clear local cache after success updated
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            this.orig_ext = this.myinfo['ext'];
                            // remove frontend cache for refreshing as well
                            this.removeLocalCache('myinfo');
                            this.removeLocalCache('lah-org-chart');
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                        this.update_doc();
                    });
                }
            },
            update_doc() {
                if (!this.disableMSDBQuery) {
                    this.isBusy = true;
                    // also update old db database
                    this.$http.post(CONFIG.API.JSON.MSSQL, {
                        type: "upd_ext_doc",
                        id: this.myinfo['id'],
                        ext: this.myinfo['ext']
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + res.data.message + "】");
                        this.notify({
                            title: "更新doc資料庫分機號碼",
                            message: res.data.message,
                            type: res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL ? 'success' : 'danger'
                        });
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                }
            }
        }
    });

    Vue.component("lah-sur-case-dialog", {
        template: `<div>
            收件字號：<a title="案件辦理情形 on ${CONFIG.AP_SVR}" href="javascript:void(0)" @click="openNewWindow('http://' + CONFIG.AP_SVR + ':9080/LandHB/Dispatcher?REQ=CMC0202&GRP=CAS&MM01=' + json.raw['MM01'] + '&MM02=' + json.raw['MM02'] + '&MM03=' + json.raw['MM03'] + '&RM90=', $event)">{{json.收件字號}}</a> </br>
            收件時間：{{json.收件時間}} <br/>
            收件人員：<span v-html="json.收件人員"></span> <br/>
            <b-form-row class="w-100">
                <b-col cols="4">
                    <b-input-group size="sm">
                        <b-input-group-prepend is-text>連件數</b-input-group-prepend>
                        <b-form-input
                            v-model="count"
                            id='mm24_upd_text'
                            type="number"
                            min="0"
                            inline
                        ></b-form-input>
                    </b-input-group>
                </b-col>
                <b-col>
                    <b-button
                        v-if="isAdmin"
                        id='mm24_upd_btn'
                        size="sm"
                        variant="outline-primary"
                        @click="update"
                        :disabled="orig_count == count"
                    >更新</b-button>
                </b-col>
            </b-form-row>
            申請事由：{{json.raw["MM06"]}}：{{json.申請事由}} <br/>
            　段小段：{{json.raw["MM08"]}} <br/>
            　　地號：{{empty(json.raw["MM09"]) ? "" : this.json.地號}} <br/>
            　　建號：{{empty(json.raw["MM10"]) ? "" : this.json.建號}} <br/>
            <span class='text-info'>辦理情形</span>：{{json.辦理情形}} <br/>
            結案狀態：{{json.結案狀態}} <br/>
            <span class='text-info'>延期原因</span>：{{json.延期原因}} <br/>
            <span class='text-info'>延期時間</span>：{{json.延期時間}} <br/>
            <div v-if="json.結案已否 && json.raw['MM22'] == 'C'">
                <h6 class="mt-2 mb-2"><span class="text-danger">※</span> 發現 {{json.收件字號}} 已「結案」但辦理情形為「延期複丈」!</h6>
                <div v-if="isAdmin">
                    <b-button
                        variant="outline-danger"
                        id="sur_delay_case_fix_button"
                        data-trigger="manual"
                        data-toggle="popover"
                        data-content="需勾選右邊其中一個選項才能進行修正"
                        title="錯誤訊息"
                        data-placement="top"
                        size="sm"
                        @click="fix"
                    >修正</b-button>
                    <b-form-checkbox
                        id='sur_delay_case_fix_set_D'
                        v-model="setD"
                        size="sm"
                        inline
                    >辦理情形改為核定</b-form-checkbox>
                    <b-form-checkbox
                        id='sur_delay_case_fix_clear_delay_datetime'
                        type='checkbox'
                        v-model="clearDatetime"
                        size="sm"
                        inline
                    >清除延期時間</b-form-checkbox>
                    <b-form-checkbox
                        id='sur_delay_case_fix_count'
                        type='checkbox'
                        v-model="fixCount"
                        size="sm"
                        inline
                    >連件數設為1</b-form-checkbox>
                    <b-popover
                        :disabled.sync="disabled_popover"
                        target="sur_delay_case_fix_button"
                        title="錯誤提示"
                        ref="popover"
                        placement="top"
                    >
                        需勾選右邊其中一個選項才能進行修正
                    </b-popover>
                </div>
            </div>
            <div v-if="debug">{{setD}}, {{clearDatetime}}, {{count}}</div>
        </div>`,
        props: ["json"],
        data: () => {
            return {
                id: "",
                setD: true,
                clearDatetime: true,
                fixCount: false,
                count: 0,
                orig_count: 0,
                disabled_popover: true,
                debug: false
            }
        },
        methods: {
            update: function(e) {
                /**
                 * add various data attrs in the button tag
                 */
                let title = this.json.raw['MM01'] + '-' + this.json.raw['MM02'] + '-' + this.json.raw['MM03'] + '連件數';
                if (this.orig_count != this.count) {
                    showConfirm("確定要修改 " + title + " 為「" + this.count + "」？", () => {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "upd_case_column",
                            id: this.id,
                            table: "SCMSMS",
                            column: "MM24",
                            value: this.count
                        }).then(res => {
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.notify({
                                    title: "更新連件數",
                                    subtitle: this.id,
                                    message: title + "更新為「" + this.count + "」更新成功",
                                    type: "success"
                                });
                                this.orig_count = this.count;
                            } else {
                                this.notify({
                                    title: "更新連件數",
                                    subtitle: this.id,
                                    message: res.data.message,
                                    type: "danger"
                                });
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    });
                } else {
                    this.notify("連件數未變更，不需更新。");
                }
            },
            fix: function(e) {
                if (!this.setD && !this.clearDatetime && !this.fixCount) {
                    this.disabled_popover = false;
                    return;
                }
                this.disabled_popover = true;
                let id = this.id;
                let upd_mm22 = this.setD;
                let clr_delay = this.clearDatetime;
                let fix_count = this.fixCount;
                showConfirm("確定要修正本案件?", () => {
                    this.isBusy = true;
                    //fix_sur_delay_case
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: "fix_sur_delay_case",
                        id: id,
                        UPD_MM22: upd_mm22,
                        CLR_DELAY: clr_delay,
                        FIX_COUNT: fix_count
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            this.notify({
                                title: "修正複丈案件",
                                subtitle: id,
                                type: "success",
                                message: "修正成功!"
                            });
                            // update the data will affect UI
                            this.json.raw['MM22'] = 'D';
                        } else {
                            let msg = "回傳狀態碼不正確!【" + res.data.message + "】";
                            this.alert({
                                title: "修正複丈案件失敗",
                                subtitle: id,
                                message: msg,
                                type: "danger"
                            });
                        }
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            }
        },
        created: function() {
            this.orig_count = this.count = this.json.raw["MM24"];
            this.id = `${this.json.raw['MM01']}${this.json.raw['MM02']}${this.json.raw['MM03']}`;
        }
    });

    let regCaseMixin = {
        props: {
            bakedData: {
                type: Object,
                default: undefined
            },
            id: {
                type: String,
                default: ""
            } // the id format should be '109HB04001234'
        },
        computed: {
            year() {
                return this.bakedData ? this.bakedData["RM01"] : this.id.substring(0, 3)
            },
            code() {
                return this.bakedData ? this.bakedData["RM02"] : this.id.substring(3, 7)
            },
            number() {
                return this.bakedData ? this.bakedData["RM03"] : this.id.substring(7)
            },
            ready() {
                return !this.empty(this.bakedData)
            },
            storeBakedData() {
                return this.storeParams['RegBakedData']
            }
        },
        watch: {
            bakedData: function (nObj, oObj) {
                this.addToStoreParams('RegBakedData', nObj);
            }
        },
        created() {
            if (this.bakedData === undefined) {
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "reg_case",
                    id: `${this.year}${this.code}${this.number}`
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        this.alert({
                            title: "擷取登記案件失敗",
                            message: res.data.message,
                            type: "warning"
                        });
                    } else {
                        this.bakedData = res.data.baked;
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            }
        }
    };

    Vue.component('lah-reg-case-state-mgt', {
        mixins: [regCaseMixin],
        template: `<div>
            <div class="form-row mt-1">
                <b-input-group size="sm" class="col">
                    <b-input-group-prepend is-text>案件辦理情形</b-input-group-prepend>
                    <b-form-select v-model="bakedData['RM30']" :options="rm30_map" class="h-100">
                        <template v-slot:first>
                            <b-form-select-option :value="null" disabled>-- 請選擇狀態 --</b-form-select-option>
                        </template>
                    </b-form-select>
                </b-input-group>
                <b-input-group v-if="wip" size="sm" class="col-3">
                    <b-form-checkbox v-model="sync_rm30_1" name="reg_case_RM30_1_checkbox" switch class="my-auto">
                        <small>同步作業人員</small>
                    </b-form-checkbox>
                </b-input-group>
                <div class="filter-btn-group col-auto">
                    <b-button @click="updateRM30" size="sm" variant="outline-primary"><lah-fa-icon icon="edit"> 更新</lah-fa-icon></b-button>
                </div>
            </div>
            <div class="form-row mt-1">
                <b-input-group size="sm" class="col">
                    <b-input-group-prepend is-text>登記處理註記</b-input-group-prepend>
                    <b-form-select v-model="bakedData['RM39']" :options="rm39_map">
                        <template v-slot:first>
                            <b-form-select-option value="">-- 無狀態 --</b-form-select-option>
                        </template>
                    </b-form-select>
                </b-input-group>
                <div class="filter-btn-group col-auto">
                    <b-button @click="updateRM39" size="sm" variant="outline-primary"><lah-fa-icon icon="edit"> 更新</lah-fa-icon></b-button>
                </div>
            </div>
            <div class="form-row mt-1">
                <b-input-group size="sm" class="col">
                    <b-input-group-prepend is-text>地價處理註記</b-input-group-prepend>
                    <b-form-select v-model="bakedData['RM42']" :options="rm42_map">
                        <template v-slot:first>
                            <b-form-select-option value="">-- 無狀態 --</b-form-select-option>
                        </template>
                    </b-form-select>
                </b-input-group>
                <div class="filter-btn-group col-auto">
                    <b-button @click="updateRM42" size="sm" variant="outline-primary"><lah-fa-icon icon="edit"> 更新</lah-fa-icon></b-button>
                </div>
            </div>
            <p v-if="showProgress" class="mt-2"><lah-reg-table type="sm" :bakedData="[bakedData]" :no-caption="true" class="small"></lah-reg-table></p>
        </div>`,
        props: ['progress'],
        data: () => ({
            rm30_orig: "",
            rm39_orig: "",
            rm42_orig: "",
            sync_rm30_1: true,
            rm30_map: [{
                    value: 'A',
                    text: 'A: 初審'
                },
                {
                    value: 'B',
                    text: 'B: 複審'
                },
                {
                    value: 'H',
                    text: 'H: 公告'
                },
                {
                    value: 'I',
                    text: 'I: 補正'
                },
                {
                    value: 'R',
                    text: 'R: 登錄'
                },
                {
                    value: 'C',
                    text: 'C: 校對'
                },
                {
                    value: 'U',
                    text: 'U: 異動完成'
                },
                {
                    value: 'F',
                    text: 'F: 結案'
                },
                {
                    value: 'X',
                    text: 'X: 補正初核'
                },
                {
                    value: 'Y',
                    text: 'Y: 駁回初核'
                },
                {
                    value: 'J',
                    text: 'J: 撤回初核'
                },
                {
                    value: 'K',
                    text: 'K: 撤回'
                },
                {
                    value: 'Z',
                    text: 'Z: 歸檔'
                },
                {
                    value: 'N',
                    text: 'N: 駁回'
                },
                {
                    value: 'L',
                    text: 'L: 公告初核'
                },
                {
                    value: 'E',
                    text: 'E: 請示'
                },
                {
                    value: 'D',
                    text: 'D: 展期'
                },
            ],
            rm39_map: [{
                    value: 'B',
                    text: 'B: 登錄開始'
                },
                {
                    value: 'R',
                    text: 'R: 登錄完成'
                },
                {
                    value: 'C',
                    text: 'C: 校對開始'
                },
                {
                    value: 'D',
                    text: 'D: 校對完成'
                },
                {
                    value: 'S',
                    text: 'S: 異動開始'
                },
                {
                    value: 'F',
                    text: 'F: 異動完成'
                },
                {
                    value: 'G',
                    text: 'G: 異動有誤'
                },
                {
                    value: 'P',
                    text: 'P: 競合暫停'
                },
            ],
            rm42_map: [{
                    value: '0',
                    text: '0: 登記移案'
                },
                {
                    value: 'B',
                    text: 'B: 登錄中'
                },
                {
                    value: 'R',
                    text: 'R: 登錄完成'
                },
                {
                    value: 'C',
                    text: 'C: 校對中'
                },
                {
                    value: 'D',
                    text: 'D: 校對完成'
                },
                {
                    value: 'E',
                    text: 'E: 登錄有誤'
                },
                {
                    value: 'S',
                    text: 'S: 異動開始'
                },
                {
                    value: 'F',
                    text: 'F: 異動完成'
                },
                {
                    value: 'G',
                    text: 'G: 異動有誤'
                }
            ],
            fields: [{
                    key: "收件字號",
                    sortable: true
                },
                {
                    key: "登記原因",
                    sortable: true
                },
                {
                    key: "辦理情形",
                    sortable: true
                },
                {
                    key: "作業人員",
                    sortable: true
                },
                {
                    key: "登記處理註記",
                    label: "登記註記",
                    sortable: true
                },
                {
                    key: "地價處理註記",
                    label: "地價註記",
                    sortable: true
                },
                {
                    key: "預定結案日期",
                    label: "期限",
                    sortable: true
                }
            ]
        }),
        computed: {
            showProgress() {
                return !this.empty(this.progress)
            },
            attachEvent() {
                return this.showProgress
            },
            wip() {
                return this.empty(this.bakedData["RM31"])
            },
            rm30() {
                return this.bakedData["RM30"] || ""
            },
            rm39() {
                return this.bakedData["RM39"] || ""
            },
            rm42() {
                return this.bakedData["RM42"] || ""
            }
        },
        methods: {
            updateRegCaseCol: function (arguments) {
                if ($(arguments.el).length > 0) {
                    // remove the button
                    $(arguments.el).remove();
                }
                this.isBusy = true;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "reg_upd_col",
                    rm01: arguments.rm01,
                    rm02: arguments.rm02,
                    rm03: arguments.rm03,
                    col: arguments.col,
                    val: arguments.val
                }).then(res => {
                    console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `更新案件「${arguments.col}」欄位回傳狀態碼有問題【${res.data.status}】`);
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        this.notify({
                            title: "更新案件欄位",
                            message: `「${arguments.col}」更新完成`,
                            variant: "success"
                        });
                    } else {
                        this.notify({
                            title: "更新案件欄位",
                            message: `「${arguments.col}」更新失敗【${res.data.status}】`,
                            variant: "warning"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            updateRM30: function (e) {
                if (this.rm30 == this.rm30_orig) {
                    this.notify({
                        title: "更新案件辦理情形",
                        message: "案件辦理情形沒變動",
                        type: "warning"
                    });
                    return;
                }
                window.vueApp.confirm(`您確定要更新辦理情形為「${this.rm30}」?`, {
                    title: '請確認更新案件辦理情形',
                    callback: () => {
                        this.updateRegCaseCol({
                            rm01: this.year,
                            rm02: this.code,
                            rm03: this.number,
                            col: "RM30",
                            val: this.rm30
                        });

                        this.rm30_orig = this.bakedData["RM30"] || "";

                        if (this.sync_rm30_1) {
                            /**
                             * RM45 - 初審 A
                             * RM47 - 複審 B
                             * RM55 - 登錄 R
                             * RM57 - 校對 C
                             * RM59 - 結案 F
                             * RM82 - 請示 E
                             * RM88 - 展期 D
                             * RM93 - 撤回 K
                             * RM91_4 - 歸檔 Z
                             */
                            let rm30_1 = "";
                            switch (this.rm30) {
                                case "A":
                                    rm30_1 = this.bakedData["RM45"];
                                    break;
                                case "B":
                                    rm30_1 = this.bakedData["RM47"];
                                    break;
                                case "R":
                                    rm30_1 = this.bakedData["RM55"];
                                    break;
                                case "C":
                                    rm30_1 = this.bakedData["RM57"];
                                    break;
                                case "F":
                                    rm30_1 = this.bakedData["RM59"];
                                    break;
                                case "E":
                                    rm30_1 = this.bakedData["RM82"];
                                    break;
                                case "D":
                                    rm30_1 = this.bakedData["RM88"];
                                    break;
                                case "K":
                                    rm30_1 = this.bakedData["RM93"];
                                    break;
                                case "Z":
                                    rm30_1 = this.bakedData["RM91_4"];
                                    break;
                                default:
                                    rm30_1 = "XXXXXXXX";
                                    break;
                            }
                            this.updateRegCaseCol({
                                rm01: this.year,
                                rm02: this.code,
                                rm03: this.number,
                                col: "RM30_1",
                                val: this.empty(rm30_1) ? "XXXXXXXX" : rm30_1
                            });
                        }
                    }
                });
            },
            updateRM39: function (e) {
                if (this.rm39 == this.rm39_orig) {
                    this.notify({
                        title: "更新登記處理註記",
                        message: "登記處理註記沒變動",
                        type: "warning"
                    });
                    return;
                }
                window.vueApp.confirm(`您確定要更新登記處理註記為「${this.rm39}」?`, {
                    title: '請確認更新登記處理註記',
                    callback: () => {
                        this.updateRegCaseCol({
                            rm01: this.year,
                            rm02: this.code,
                            rm03: this.number,
                            col: "RM39",
                            val: this.rm39
                        });
                        this.rm39_orig = this.bakedData["RM39"] || "";
                    }
                });
            },
            updateRM42: function (e) {
                if (this.rm42 == this.rm42_orig) {
                    this.notify({
                        title: "更新地價處理註記",
                        message: "地價處理註記沒變動",
                        type: "warning"
                    });
                    return;
                }
                window.vueApp.confirm(`您確定要更新地價處理註記為「${this.rm42}」?`, {
                    title: '請確認更新地價處理註記',
                    callback: () => {
                        this.updateRegCaseCol({
                            rm01: this.year,
                            rm02: this.code,
                            rm03: this.number,
                            col: "RM42",
                            val: this.rm42
                        });
                        this.rm42_orig = this.bakedData["RM42"] || "";
                    }
                });
            }
        },
        created() {
            this.rm30_orig = this.bakedData["RM30"] || "";
            this.rm39_orig = this.bakedData["RM39"] || "";
            this.rm42_orig = this.bakedData["RM42"] || "";
        },
        mounted() {
            if (this.attachEvent) {
                addUserInfoEvent();
                this.animated(".reg_case_id", {
                    name: "flash"
                }).off("click").on("click", window.vueApp.fetchRegCase);
            }
        }
    });

    Vue.component("lah-reg-case-detail", {
        mixins: [regCaseMixin],
        template: `<div>
            <lah-reg-table :bakedData="[bakedData]" type="flow" :mute="true"></lah-reg-table>
            <b-card no-body>
                <b-tabs card :end="tabsAtEnd" :pills="tabsAtEnd">
                    <b-tab>
                        <template v-slot:title>
                            <strong>收件資料</strong>
                            <b-link variant="muted" @click.stop="openNewWindow(case_data_url, $event)" :title="'收件資料 on ' + ap_server"><lah-fa-icon icon="external-link-alt" variant="primary"></lah-fa-icon></b-link>
                        </template>
                        <b-card-body>
                            <b-form-row class="mb-1">
                                <b-col>    
                                    <lah-transition appear>
                                        <div v-show="show_op_card" class="mr-1 float-right" style="width:400px">
                                            <lah-fa-icon icon="user" variant="dark" prefix="far"> 作業人員</lah-fa-icon>
                                            <lah-user-card @not-found="handleNotFound" :id="bakedData.RM30_1"></lah-user-card>
                                        </div>
                                    </lah-transition>
                                    <div v-if="bakedData.跨所 == 'Y'"><span class='bg-info text-white rounded p-1'>跨所案件 ({{bakedData.資料收件所}} => {{bakedData.資料管轄所}})</span></div>
                                    收件字號：
                                    <a :title="'收件資料 on ' + ap_server" href="javascript:void(0)" @click="openNewWindow(case_data_url, $event)">
                                        {{bakedData.收件字號}}
                                    </a> <br/>
                                    收件時間：{{bakedData.收件時間}} <br/>
                                    測量案件：{{bakedData.測量案件}} <br/>
                                    限辦期限：<span v-html="bakedData.限辦期限"></span> <br/>
                                    作業人員：<span class='user_tag'>{{bakedData.作業人員}}</span> <br/>
                                    辦理情形：{{bakedData.辦理情形}} <br/>
                                    登記原因：{{bakedData.登記原因}} <br/>
                                    區域：{{bakedData.區名稱}}【{{bakedData.RM10}}】 <br/>
                                    段小段：{{bakedData.段小段}}【{{bakedData.段代碼}}】 <br/>
                                    地號：{{bakedData.地號}} <br/>
                                    建號：{{bakedData.建號}} <br/>
                                    件數：{{bakedData.件數}} <br/>
                                    登記處理註記：{{bakedData.登記處理註記}} <br/>
                                    地價處理註記：{{bakedData.地價處理註記}} <br/>
                                    手機號碼：{{bakedData.手機號碼}}
                                </b-col>
                            </b-form-row>
                        </b-card-body>
                    </b-tab>
                    <b-tab>
                        <template v-slot:title>
                            <strong>辦理情形</strong>
                            <b-link variant="muted" @click.stop="openNewWindow(case_status_url, $event)" :title="'案件辦理情形 on ' + ap_server"><lah-fa-icon icon="external-link-alt" variant="primary"></lah-fa-icon></b-link>
                        </template>
                        <b-card-body>
                            <b-list-group flush compact>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col :title="bakedData.預定結案日期">預定結案：<span v-html="bakedData.限辦期限"></span></b-col>
                                        <b-col :title="bakedData.結案與否">
                                            結案與否：
                                            <span v-if="is_ongoing" class='text-danger'><strong>尚未結案！</strong></span>
                                            <span v-else class='text-success'><strong>已結案</strong></span>
                                        </b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.代理人統編)">
                                    <b-form-row>
                                        <b-col>代理人統編：{{bakedData.代理人統編}}</b-col>
                                        <b-col>代理人姓名：{{bakedData.代理人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.權利人統編)">
                                    <b-form-row>
                                        <b-col>權利人統編：{{bakedData.權利人統編}}</b-col>
                                        <b-col>權利人姓名：{{bakedData.權利人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.義務人統編)">
                                    <b-form-row>
                                        <b-col>義務人統編：{{bakedData.義務人統編}}</b-col>
                                        <b-col>義務人姓名：{{bakedData.義務人姓名}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col>登記原因：{{bakedData.登記原因}}</b-col>
                                        <b-col>辦理情形：<span :class="bakedData.紅綠燈背景CSS">{{bakedData.辦理情形}}</span></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item>
                                    <b-form-row>
                                        <b-col>收件人員：<span class='user_tag'  :data-id="bakedData.收件人員ID" :data-name="bakedData.收件人員">{{bakedData.收件人員}}</span></b-col>
                                        <b-col>收件時間：{{bakedData.收件時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.移轉課長)">
                                    <b-form-row>
                                        <b-col>移轉課長：<span class='user_tag' >{{bakedData.移轉課長}}</span></b-col>
                                        <b-col>移轉課長時間：{{bakedData.移轉課長時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.移轉秘書)">
                                    <b-form-row>
                                        <b-col>移轉秘書：<span class='user_tag' >{{bakedData.移轉秘書}}</span></b-col>
                                        <b-col>移轉秘書時間：{{bakedData.移轉秘書時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.初審人員)">
                                    <b-form-row>
                                        <b-col>初審人員：<span class='user_tag' >{{bakedData.初審人員}}</span></b-col>
                                        <b-col>初審時間：{{bakedData.初審時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.複審人員)">
                                    <b-form-row>
                                        <b-col>複審人員：<span class='user_tag' >{{bakedData.複審人員}}</span></b-col>
                                        <b-col>複審時間：{{bakedData.複審時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.駁回日期)">
                                    <b-form-row>
                                        <b-col>駁回日期：{{bakedData.駁回日期}}</b-col>
                                        <b-col></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.公告日期)">
                                    <b-form-row>
                                        <b-col>公告日期：{{bakedData.公告日期}}</b-col>
                                        <b-col>公告到期：{{bakedData.公告期滿日期}} 天數：{{bakedData.公告天數}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.通知補正日期)">
                                    <b-form-row>
                                        <b-col>通知補正：{{bakedData.通知補正日期}}</b-col>
                                        <b-col>補正期滿：{{bakedData.補正期滿日期}} 天數：{{bakedData.補正期限}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.補正日期)">
                                    <b-form-row>
                                        <b-col>補正日期：{{bakedData.補正日期}}</b-col>
                                        <b-col></b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.請示人員)">
                                    <b-form-row>
                                        <b-col>請示人員：<span class='user_tag' >{{bakedData.請示人員}}</span></b-col>
                                        <b-col>請示時間：{{bakedData.請示時間}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.展期人員)">
                                    <b-form-row>
                                        <b-col>展期人員：<span class='user_tag' >{{bakedData.展期人員}}</span></b-col>
                                        <b-col>展期日期：{{bakedData.展期日期}} 天數：{{bakedData.展期天數}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.准登人員)">
                                    <b-form-row>
                                        <b-col>准登人員：<span class='user_tag' >{{bakedData.准登人員}}</span></b-col>
                                        <b-col>准登日期：{{bakedData.准登日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.登錄人員)">
                                    <b-form-row>
                                        <b-col>登錄人員：<span class='user_tag' >{{bakedData.登錄人員}}</span></b-col>
                                        <b-col>登錄日期：{{bakedData.登錄日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.校對人員)">
                                    <b-form-row>
                                        <b-col>校對人員：<span class='user_tag' >{{bakedData.校對人員}}</span></b-col>
                                        <b-col>校對日期：{{bakedData.校對日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                                <b-list-group-item v-if="!empty(bakedData.結案人員)">
                                    <b-form-row>
                                        <b-col>結案人員：<span class='user_tag' >{{bakedData.結案人員}}</span></b-col>
                                        <b-col>結案日期：{{bakedData.結案日期}}</b-col>
                                    </b-form-row>
                                </b-list-group-item>
                            </b-list-group>
                        </b-card-body>
                    </b-tab>
                    <b-tab lazy>
                        <template v-slot:title>
                            <lah-fa-icon icon="chart-line" class="text-success"> <strong>案件時間線</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-timeline ref="timeline" :baked-data="bakedData"></lah-reg-case-temp-mgt>
                    </b-tab>
                    <b-tab v-if="isAdmin" lazy>
                        <template v-slot:title>
                            <lah-fa-icon icon="database" class="text-secondary"> <strong>狀態管理</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-state-mgt :baked-data="bakedData"></lah-reg-case-state-mgt>
                    </b-tab>
                    <b-tab v-if="isAdmin" lazy>
                        <template v-slot:title>
                            <lah-fa-icon icon="buffer" prefix="fab" class="text-secondary"> <strong>暫存檔管理</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-temp-mgt :baked-data="bakedData"></lah-reg-case-temp-mgt>
                    </b-tab>
                </b-tabs>
            </b-card>
        </div>`,
        props: ['tabsEnd'],
        data: () => ({
            rm10: null,
            ap_server: "220.1.35.123",
            show_op_card: true
        }),
        computed: {
            tabsAtEnd() {
                return !this.empty(this.tabsEnd)
            },
            is_ongoing() {
                return this.empty(this.bakedData.結案代碼)
            },
            case_status_url() {
                return `http://${this.ap_server}:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=${this.year}&word=${this.code}&code=${this.number}&sdlyn=N&RM90=`
            },
            case_data_url() {
                return `http://${this.ap_server}:9080/LandHB/CAS/CCD01/CCD0103.jsp?rm01=${this.year}&rm02=${this.code}&rm03=${this.number}`
            }

        },
        methods: {
            handleNotFound: function (input) {
                this.show_op_card = false
            }
        },
        mounted() {
            this.rm10 = this.bakedData.RM10 ? this.bakedData.RM10 : "XX";
        },
        mounted() {
            addUserInfoEvent();
        }
    });

    Vue.component('lah-reg-case-temp-mgt', {
        mixins: [regCaseMixin],
        template: `<div>
            <div v-if="found">
                <div v-for="(item, idx) in filtered">
                    <h6 v-if="idx == 0" class="font-weight-bold text-center">請檢查下列暫存檔資訊，必要時請刪除</h6>
                    <b-button @click="showSQL(item)" size="sm" variant="warning">
                        {{item[0]}}表格 <span class="badge badge-light">{{item[1].length}} <span class="sr-only">暫存檔數量</span></span>
                    </b-button>
                    <small>
                        <b-button
                            :id="'backup_temp_btn_' + idx"
                            size="sm"
                            variant="outline-primary"
                            @click="backup(item, idx, $event)"
                        >備份</b-button>
                        <b-button
                            v-if="item[0] != 'MOICAT.RINDX' && item[0] != 'MOIPRT.PHIND'"
                            :title="title(item)"
                            size="sm"
                            variant="outline-danger"
                            @click="clean(item, idx, $event)"
                        >清除</b-button>
                    </small>
                    <br />&emsp;<small>－&emsp;{{item[2]}}</small>
                </div>
                <hr />
                <div class="text-center">
                    <b-button id="backup_temp_btn_all" @click="backupAll" variant="outline-primary" size="sm">全部備份</b-button>
                    <b-button @click="cleanAll" variant="danger" size="sm">全部清除</b-button>
                    <b-button @click="popup" variant="outline-success" size="sm"><lah-fa-icon icon="question"> 說明</lah-fa-icon></b-button>
                </div>
            </div>
            <lah-fa-icon v-if="not_found" icon="exclamation-circle" variant="success" size="lg"> 無暫存檔。</lah-fa-icon>
            <lah-fa-icon v-if="loading" action="spin" icon="spinner" size="lg"> 讀取中</lah-fa-icon>
        </div>`,
        data: () => ({
            filtered: null,
            cleanAllBackupFlag: false,
            backupFlags: []
        }),
        computed: {
            found() {
                return !this.empty(this.filtered)
            },
            not_found() {
                return Array.isArray(this.filtered) && this.empty(this.filtered)
            },
            loading() {
                return this.filtered === null
            },
            prefix() {
                return `${this.year}-${this.code}-${this.number}`
            }
        },
        methods: {
            title: function (item) {
                return item[0] == "MOICAT.RINDX" || item[0] == "MOIPRT.PHIND" ? "重要案件索引，無法刪除！" : "";
            },
            download: function (content, filename) {
                const url = window.URL.createObjectURL(new Blob([content]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                //afterwards we remove the element again
                link.remove();
                // release object in memory
                window.URL.revokeObjectURL(url);
            },
            backupAll: function (e) {
                this.isBusy = true;
                let filename = this.year + "-" + this.code + "-" + this.number + "-TEMP-DATA.sql";
                let all_content = "";
                this.filtered.forEach((item, idx) => {
                    all_content += this.getInsSQL(item);
                });
                this.download(all_content, filename);
                this.cleanAllBackupFlag = true;
                this.isBusy = false;
            },
            cleanAll: function (e) {
                if (this.cleanAllBackupFlag !== true) {
                    this.alert({
                        title: "清除全部暫存資料",
                        subtitle: `${this.year}-${this.code}-${this.number}`,
                        message: "請先備份！",
                        type: "warning"
                    });
                    this.animated("#backup_temp_btn_all", {
                        name: "tada"
                    });
                    return;
                }
                let msg = "<h6><strong class='text-danger'>★警告★</strong>：無法復原請先備份!!</h6>清除案件 " + this.year + "-" + this.code + "-" + this.number + " 全部暫存檔?";
                showConfirm(msg, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: 'clear_temp_data',
                        year: this.year,
                        code: this.code,
                        number: this.number,
                        table: ''
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除暫存資料回傳狀態碼有問題【" + res.data.status + "】");
                        this.notify({
                            title: "清除暫存檔",
                            message: "已清除完成。<p>" + this.year + "-" + this.code + "-" + this.number + "</p>",
                            type: "success"
                        });
                        $(e.target).remove();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            backup: function (item, idx, e) {
                this.isBusy = true;
                let filename = `${this.prefix}-${item[0]}-TEMP-DATA.sql`;
                this.download(this.getInsSQL(item), filename);
                this.backupFlags[idx] = true;
                $(e.target).attr("disabled", this.backupFlags[idx]);
                this.isBusy = false;
            },
            clean: function (item, idx, e) {
                let table = item[0];
                if (this.backupFlags[idx] !== true) {
                    this.alert({
                        title: `清除 ${table} 暫存檔`,
                        subtitle: `${this.year}-${this.code}-${this.number}`,
                        message: `請先備份 ${table} ！`,
                        type: "warning"
                    });
                    this.animated(`#backup_temp_btn_${idx}`, {
                        name: "tada"
                    });
                    return;
                }
                let msg = "<h6><strong class='text-danger'>★警告★</strong>：無法復原請先備份!!</h6>清除案件 " + this.year + "-" + this.code + "-" + this.number + " " + table + " 暫存檔?";
                showConfirm(msg, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.API.JSON.QUERY, {
                        type: 'clear_temp_data',
                        year: this.year,
                        code: this.code,
                        number: this.number,
                        table: table
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除暫存資料回傳狀態碼有問題【" + res.data.status + "】");
                        this.notify({
                            title: `清除 ${table} 暫存檔`,
                            subtitle: this.year + "-" + this.code + "-" + this.number,
                            message: "已清除完成。",
                            type: "success"
                        });
                        $(e.target).remove();
                    }).catch(err => {
                        this.error = err;
                    }).finally(() => {
                        this.isBusy = false;
                    });
                });
            },
            showSQL: function (item) {
                this.msgbox({
                    title: `INSERT SQL of ${item[0]}`,
                    message: this.getInsSQL(item).replace(/\n/g, "<br /><br />"),
                    size: "xl"
                });
            },
            getInsSQL: function (item) {
                let INS_SQL = "";
                for (let y = 0; y < item[1].length; y++) {
                    let this_row = item[1][y];
                    let fields = [];
                    let values = [];
                    for (let key in this_row) {
                        fields.push(key);
                        values.push(this.empty(this_row[key]) ? "null" : `'${this_row[key]}'`);
                    }
                    INS_SQL += `insert into ${item[0]} (${fields.join(",")})`;
                    INS_SQL += ` values (${values.join(",")});\n`;
                }
                return INS_SQL;
            },
            popup: function () {
                this.msgbox({
                    title: "案件暫存檔清除 小幫手提示",
                    body: `<h6 class="text-info">檢查下列的表格</h6>
                    <ul>
                      <!-- // 登記 -->
                      <li>"MOICAT.RALID" => "A"   // 土地標示部</li>
                      <li>"MOICAT.RBLOW" => "B"   // 土地所有權部</li>
                      <li>"MOICAT.RCLOR" => "C"   // 他項權利部</li>
                      <li>"MOICAT.RDBID" => "D"   // 建物標示部</li>
                      <li>"MOICAT.REBOW" => "E"   // 建物所有權部</li>
                      <li>"MOICAT.RLNID" => "L"   // 人檔</li>
                      <li>"MOICAT.RRLSQ" => "R"   // 權利標的</li>
                      <li>"MOICAT.RGALL" => "G"   // 其他登記事項</li>
                      <li>"MOICAT.RMNGR" => "M"   // 管理者</li>
                      <li>"MOICAT.RTOGH" => "T"   // 他項權利檔</li>
                      <li>"MOICAT.RHD10" => "H"   // 基地坐落／地上建物</li>
                      <li class="text-danger">"MOICAT.RINDX" => "II"  // 案件異動索引【不會清除】</li>
                      <li>"MOICAT.RINXD" => "ID"</li>
                      <li>"MOICAT.RINXR" => "IR"</li>
                      <li>"MOICAT.RINXR_EN" => "IRE"</li>
                      <li>"MOICAT.RJD14" => "J"</li>
                      <li>"MOICAT.ROD31" => "O"</li>
                      <li>"MOICAT.RPARK" => "P"</li>
                      <li>"MOICAT.RPRCE" => "PB"</li>
                      <li>"MOICAT.RSCNR" => "SR"</li>
                      <li>"MOICAT.RSCNR_EN" => "SRE"</li>
                      <li>"MOICAT.RVBLOW" => "VB"</li>
                      <li>"MOICAT.RVCLOR" => "VC"</li>
                      <li>"MOICAT.RVGALL" => "VG"</li>
                      <li>"MOICAT.RVMNGR" => "VM"</li>
                      <li>"MOICAT.RVPON" => "VP"  // 重測/重劃暫存</li>
                      <li>"MOICAT.RVRLSQ" => "VR"</li>
                      <li>"MOICAT.RXIDD04" => "ID"</li>
                      <li>"MOICAT.RXLND" => "XL"</li>
                      <li>"MOICAT.RXPRI" => "XP"</li>
                      <li>"MOICAT.RXSEQ" => "XS"</li>
                      <li>"MOICAT.B2104" => "BR"</li>
                      <li>"MOICAT.B2118" => "BR"</li>
                      <li>"MOICAT.BGALL" => "G"</li>
                      <li>"MOICAT.BHD10" => "H"</li>
                      <li>"MOICAT.BJD14" => "J"</li>
                      <li>"MOICAT.BMNGR" => "M"</li>
                      <li>"MOICAT.BOD31" => "O"</li>
                      <li>"MOICAT.BPARK" => "P"</li>
                      <li>"MOICAT.BRA26" => "C"</li>
                      <li>"MOICAT.BRLSQ" => "R"</li>
                      <li>"MOICAT.BXPRI" => "XP"</li>
                      <li>"MOICAT.DGALL" => "G"</li>
                      <!-- // 地價 -->
                      <li>"MOIPRT.PPRCE" => "MA"</li>
                      <li>"MOIPRT.PGALL" => "GG"</li>
                      <li>"MOIPRT.PBLOW" => "LA"</li>
                      <li>"MOIPRT.PALID" => "KA"</li>
                      <li>"MOIPRT.PNLPO" => "NA"</li>
                      <li>"MOIPRT.PBLNV" => "BA"</li>
                      <li>"MOIPRT.PCLPR" => "CA"</li>
                      <li>"MOIPRT.PFOLP" => "FA"</li>
                      <li>"MOIPRT.PGOBP" => "GA"</li>
                      <li>"MOIPRT.PAPRC" => "AA"</li>
                      <li>"MOIPRT.PEOPR" => "EA"</li>
                      <li>"MOIPRT.POA11" => "OA"</li>
                      <li>"MOIPRT.PGOBPN" => "GA"</li>
                      <!--<li>"MOIPRC.PKCLS" => "KK"</li>-->
                      <li>"MOIPRT.PPRCE" => "MA"</li>
                      <li>"MOIPRT.P76SCRN" => "SS"</li>
                      <li>"MOIPRT.P21T01" => "TA"</li>
                      <li>"MOIPRT.P76ALID" => "AS"</li>
                      <li>"MOIPRT.P76BLOW" => "BS"</li>
                      <li>"MOIPRT.P76CRED" => "BS"</li>
                      <li>"MOIPRT.P76INDX" => "II"</li>
                      <li>"MOIPRT.P76PRCE" => "UP"</li>
                      <li>"MOIPRT.P76SCRN" => "SS"</li>
                      <li>"MOIPRT.PAE0301" => "MA"</li>
                      <li>"MOIPRT.PB010" => "TP"</li>
                      <li>"MOIPRT.PB014" => "TB"</li>
                      <li>"MOIPRT.PB015" => "TB"</li>
                      <li>"MOIPRT.PB016" => "TB"</li>
                      <li class="text-danger">"MOIPRT.PHIND" => "II"  // 案件異動索引【不會清除】</li>
                      <li>"MOIPRT.PNLPO" => "NA"</li>
                      <li>"MOIPRT.POA11" => "OA"</li>
                    </ul>`,
                    size: "lg"
                });
            }
        },
        created() {
            this.busyIconSize = "1x";
            this.isBusy = true;
            this.$http.post(CONFIG.API.JSON.QUERY, {
                type: "query_temp_data",
                year: this.year,
                code: this.code,
                number: this.number
            }).then(res => {

                this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `查詢暫存資料回傳狀態碼有問題【${res.data.status}】`);

                this.filtered = [];
                // res.data.raw structure: 0 - Table, 1 - all raw data, 2 - SQL
                this.filtered = res.data.raw.filter((item, index, array) => {
                    return item[1].length > 0;
                });

                // initialize backup flag array for backup detection
                this.backupFlags = Array(this.filtered.length).fill(false);
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        }
    });

    Vue.component('lah-reg-case-timeline', {
        mixins: [regCaseMixin],
        template: `<div class="clearfix">
            <div class="text-justify clearfix">
                <span class="align-middle">{{title}}</span>
                <b-button-group size="sm" class="float-right">
                    <lah-button variant="primary" @click="chartType = 'bar'" icon="chart-bar"></lah-button>
                    <lah-button variant="secondary" @click="chartType = 'pie'" icon="chart-pie"></lah-button>
                    <lah-button variant="success" @click="chartType = 'line'" icon="chart-line"></lah-button>
                    <lah-button variant="warning" @click="chartType = 'polarArea'" icon="chart-area"></lah-button>
                    <lah-button variant="info" @click="chartType = 'doughnut'" icon="edge" brand></lah-button>
                    <lah-button variant="dark" @click="chartType = 'radar'" icon="broadcast-tower"></lah-button>
                </b-button-group>
            </div>
            <lah-chart :type="chartType" label="案件時間線" :items="items" :tooltip="tooltip"></lah-chart>
        </div>`,
        data: () => ({
            items: [],
            chartType: 'line'
        }),
        computed: {
            border() {
                return this.ready ? '' : 'danger'
            },
            title() {
                return this.ready ? this.bakedData.收件字號 : ''
            }
        },
        watch: {
            bakedData: function (nData, oData) {
                this.prepareItems()
            }
        },
        methods: {
            prepareItems: function () {
                if (this.ready) {
                    let items = [];
                    Object.keys(this.bakedData.ELAPSED_TIME).forEach(key => {
                        let mins = parseFloat(this.bakedData.ELAPSED_TIME[key] / 60).toFixed(2);
                        items.push([key, mins]);
                    });
                    this.items = items;
                } else {
                    this.$warn("lah-reg-case-timeline: backedData is not ready ... retry after 200ms later");
                    this.timeout(this.prepareItems, 200);
                }
            },
            tooltip(data) {
                let currentValue = data.dataset.data[data.dataIndex];
                return ` ${data.label} : ${currentValue} 分鐘`;
            }
        },
        mounted() {
            this.prepareItems()
        }
    });

    Vue.component('lah-reg-table', {
        template: `<lah-transition appear slide-down>
            <b-table
                ref="reg_case_tbl"
                :responsive="'sm'"
                :striped="true"
                :hover="true"
                :bordered="true"
                :borderless="false"
                :outlined="false"
                :small="true"
                :dark="false"
                :fixed="false"
                :foot-clone="false"
                :no-border-collapse="true"
                :head-variant="'dark'"
                :table-variant="false"

                :sticky-header="sticky"
                :caption="caption"
                :items="bakedData"
                :fields="tblFields"
                :style="style"
                :busy="busy"
                :tbody-tr-class="trClass"
                :tbody-transition-props="transProps"
                primary-key="收件字號"

                class="text-center"
                caption-top
            >
                <template v-slot:table-busy>
                    <b-spinner class="align-middle" variant="danger" type="grow" small label="讀取中..."></b-spinner>
                </template>

                <template v-slot:cell(RM01)="row">
                    <div class="text-left" v-b-popover.hover.focus.d400="{content: row.item['結案狀態'], variant: row.item['燈號']}">
                        <lah-fa-icon :icon="icon" :variant="iconVariant" v-if="showIcon"></lah-fa-icon>
                        <span v-if="mute">{{bakedContent(row)}}</span>
                        <a v-else href="javascript:void(0)" @click="fetch(row.item)">{{bakedContent(row)}}</a>
                    </div>
                </template>
                <template v-slot:cell(收件字號)="row">
                    <div class="text-left" v-b-popover.hover.focus.d400="{content: row.item['結案狀態'], variant: row.item['燈號']}">
                        <lah-fa-icon :icon="icon" :variant="iconVariant" v-if="showIcon"></lah-fa-icon>
                        <span v-if="mute">{{bakedContent(row)}}</span>
                        <a v-else href="javascript:void(0)" @click="fetch(row.item)">{{row.item['收件字號']}}</a>
                    </div>
                </template>

                <template v-slot:cell(序號)="row">
                    {{row.index + 1}}
                </template>

                <template v-slot:cell(燈號)="row">
                    <lah-fa-icon icon="circle" :variant="row.item['燈號']"></lah-fa-icon>
                </template>

                <template v-slot:cell(限辦時間)="row">
                    <lah-fa-icon icon="circle" :variant="row.item['燈號']" v-b-popover.hover.focus.d400="{content: row.item['預定結案日期'], variant: row.item['燈號']}" class="text-nowrap"> {{row.value}}</lah-fa-icon>
                </template>

                <template v-slot:cell(RM07_1)="row">
                    <span v-b-popover.hover.focus.d400="row.item['收件時間']">{{row.item["RM07_1"]}}</span>
                </template>
                
                <template v-slot:cell(登記原因)="row">
                    {{reason(row)}}
                </template>
                <template v-slot:cell(RM09)="row">
                    {{reason(row)}}
                </template>

                <template v-slot:cell(初審人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['初審人員'], item['RM45'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['初審'])">{{item["初審人員"]}}</a>
                </template>
                <template v-slot:cell(複審人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['複審人員'], item['RM47'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['複審'])">{{item["複審人員"]}}</a>
                </template>
                <template v-slot:cell(收件人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['收件人員'], item['RM96'])">{{item["收件人員"]}}</a>
                </template>
                <template v-slot:cell(作業人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['作業人員'], item['RM30_1'])">{{item["作業人員"]}}</a>
                </template>
                <template v-slot:cell(准登人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['准登人員'], item['RM63'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['准登'])">{{item["准登人員"]}}</a>
                </template>
                <template v-slot:cell(登錄人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['登錄人員'], item['RM55'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['登簿'])">{{item["登錄人員"]}}</a>
                </template>
                <template v-slot:cell(校對人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['校對人員'], item['RM57'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['校對'])">{{item["校對人員"]}}</a>
                </template>
                <template v-slot:cell(結案人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['結案人員'], item['RM59'])" v-b-popover.top.hover.focus.html="passedTime(item, item.ELAPSED_TIME['結案'])">{{item["結案人員"]}}</a>
                </template>
            </b-table>
        </lah-transition>`,
        props: ['bakedData', 'maxHeight', 'type', 'fields', 'mute', 'noCaption', 'color', 'icon', 'iconVariant', 'busy'],
        data: () => ({
            transProps: {
                name: 'rollIn'
            }
        }),
        computed: {
            tblFields: function () {
                if (!this.empty(this.fields)) return this.fields;
                switch (this.type) {
                    case "md":
                        return [{
                                key: "收件字號",
                                sortable: this.sort
                            },
                            {
                                key: "登記原因",
                                sortable: this.sort
                            },
                            {
                                key: "辦理情形",
                                sortable: this.sort
                            },
                            {
                                key: "初審人員",
                                sortable: this.sort
                            },
                            {
                                key: "作業人員",
                                sortable: this.sort
                            },
                            {
                                key: "收件時間",
                                sortable: this.sort
                            },
                            {
                                key: "限辦時間",
                                sortable: this.sort
                            }
                            //{key: "預定結案日期", label:"限辦期限", sortable: this.sort}
                        ];
                    case "lg":
                        return [{
                                key: "收件字號",
                                sortable: this.sort
                            },
                            {
                                key: "收件日期",
                                sortable: this.sort
                            },
                            {
                                key: "登記原因",
                                sortable: this.sort
                            },
                            {
                                key: "辦理情形",
                                sortable: this.sort
                            },
                            {
                                key: "收件人員",
                                label: "收件",
                                sortable: this.sort
                            },
                            {
                                key: "作業人員",
                                label: "作業",
                                sortable: this.sort
                            },
                            {
                                key: "初審人員",
                                label: "初審",
                                sortable: this.sort
                            },
                            {
                                key: "複審人員",
                                label: "複審",
                                sortable: this.sort
                            },
                            {
                                key: "准登人員",
                                label: "准登",
                                sortable: this.sort
                            },
                            {
                                key: "登錄人員",
                                label: "登簿",
                                sortable: this.sort
                            },
                            {
                                key: "校對人員",
                                label: "校對",
                                sortable: this.sort
                            },
                            {
                                key: "結案人員",
                                label: "結案",
                                sortable: this.sort
                            }
                        ];
                    case "xl":
                        return [
                            //{key: "燈號", sortable: this.sort},
                            {
                                key: "序號",
                                sortable: !this.sort
                            },
                            {
                                key: "收件字號",
                                sortable: this.sort
                            },
                            {
                                key: "收件時間",
                                sortable: this.sort
                            },
                            {
                                key: "限辦時間",
                                label: "限辦",
                                sortable: this.sort
                            },
                            {
                                key: "登記原因",
                                sortable: this.sort
                            },
                            {
                                key: "辦理情形",
                                sortable: this.sort
                            },
                            {
                                key: "收件人員",
                                label: "收件",
                                sortable: this.sort
                            },
                            {
                                key: "作業人員",
                                label: "作業",
                                sortable: this.sort
                            },
                            {
                                key: "初審人員",
                                label: "初審",
                                sortable: this.sort
                            },
                            {
                                key: "複審人員",
                                label: "複審",
                                sortable: this.sort
                            },
                            {
                                key: "准登人員",
                                label: "准登",
                                sortable: this.sort
                            },
                            {
                                key: "登錄人員",
                                label: "登簿",
                                sortable: this.sort
                            },
                            {
                                key: "校對人員",
                                label: "校對",
                                sortable: this.sort
                            },
                            {
                                key: "結案人員",
                                label: "結案",
                                sortable: this.sort
                            }
                            //{key: "結案狀態", label: "狀態", sortable: this.sort}
                        ];
                    case "flow":
                        return [{
                                key: "辦理情形",
                                sortable: this.sort
                            },
                            {
                                key: "收件人員",
                                label: "收件",
                                sortable: this.sort
                            },
                            {
                                key: "作業人員",
                                label: "作業",
                                sortable: this.sort
                            },
                            {
                                key: "初審人員",
                                label: "初審",
                                sortable: this.sort
                            },
                            {
                                key: "複審人員",
                                label: "複審",
                                sortable: this.sort
                            },
                            {
                                key: "准登人員",
                                label: "准登",
                                sortable: this.sort
                            },
                            {
                                key: "登錄人員",
                                label: "登簿",
                                sortable: this.sort
                            },
                            {
                                key: "校對人員",
                                label: "校對",
                                sortable: this.sort
                            },
                            {
                                key: "結案人員",
                                label: "結案",
                                sortable: this.sort
                            }
                        ];
                    default:
                        return [{
                                key: "RM01",
                                label: "收件字號",
                                sortable: this.sort
                            },
                            {
                                key: "RM07_1",
                                label: "收件日期",
                                sortable: this.sort
                            },
                            {
                                key: "RM09",
                                label: "登記原因",
                                sortable: this.sort
                            },
                            {
                                key: "辦理情形",
                                sortable: this.sort
                            },
                        ];
                }
            },
            count() {
                return this.bakedData ? this.bakedData.length : 0
            },
            caption() {
                if (this.mute || this.noCaption) return false;
                return this.busy ? '讀取中' : '登記案件找到 ' + this.count + '件';

            },
            sticky() {
                return this.maxHeight ? this.count > 0 ? true : false : false
            },
            style() {
                const parsed = parseInt(this.maxHeight);
                return isNaN(parsed) ? '' : `max-height: ${parsed}px`;
            },
            showIcon() {
                return !this.empty(this.icon)
            },
            sort() {
                return this.empty(this.mute)
            }
        },
        methods: {
            fetch(data) {
                let id = `${data["RM01"]}${data["RM02"]}${data["RM03"]}`;
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        this.alert({
                            title: "顯示登記案件詳情",
                            message: res.data.message,
                            type: "warning"
                        });
                    } else {
                        this.msgbox({
                            message: this.$createElement("lah-reg-case-detail", {
                                props: {
                                    bakedData: res.data.baked
                                }
                            }),
                            title: `登記案件詳情 ${data["RM01"]}-${data["RM02"]}-${data["RM03"]}`,
                            size: "lg"
                        });
                    }
                }).catch(err => {
                    this.error = err;
                });
            },
            userinfo(name, id = '') {
                if (name == 'XXXXXXXX') return;
                this.msgbox({
                    title: `${name} 使用者資訊${this.empty(id) ? '' : ` (${id})`}`,
                    message: this.$createElement('lah-user-card', {
                        props: {
                            id: id,
                            name: name
                        }
                    })
                });
            },
            bakedContent(row) {
                return row.item[row.field.label];
            },
            reason(row) {
                return row.item["RM09"] + " : " + (this.empty(row.item["登記原因"]) ? row.item["RM09_CHT"] : row.item["登記原因"]);
            },
            trClass(item, type) {
                if (item && type == 'row') return this.color ? item["紅綠燈背景CSS"] : `filter-${item["燈號"]}`;
            },
            passedTime(item, time_duration_secs) {
                if (isNaN(time_duration_secs) || this.empty(time_duration_secs)) return false;
                if (time_duration_secs == '0' && this.empty(item['結案代碼'])) {
                    return '<i class="fa fa-tools ld ld-clock"></i> 作業中';
                }
                if (time_duration_secs < 60) return "耗時 " + time_duration_secs + " 秒";
                if (time_duration_secs < 3600) return "耗時 " + Number.parseFloat(time_duration_secs / 60).toFixed(2) + " 分鐘";
                return "耗時 " + Number.parseFloat(time_duration_secs / 60 / 60).toFixed(2) + " 小時";
            }
        },
        created() {
            this.type = this.type || ''
        }
    });

} else {
    console.error("vue.js not ready ... lah-* relative components can not be loaded.");
}