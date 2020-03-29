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
    });

    Vue.component("lah-fa-icon", {
        template: `<span><i :id="id" :class="className"></i> <slot></slot></span>`,
        props: ["size", 'prefix', 'icon', 'variant', 'action', 'id'],
        computed: {
            className() {
                let prefix = this.prefix || 'fas';
                let icon = this.icon || 'exclamation-circle';
                let variant = this.variant || '';
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
                let total_remaining_secs = this.delay / 1000;
                this.progress_timer_handle = setInterval(() => {
                    this.remaining_delay -= 200;
                    let now_percent = ++this.progress_counter / (this.delay / 200.0);
                    this.remaining_percent = (100 - Math.round(now_percent * 100));
                    if (this.remaining_percent > 50) {
                    } else if (this.remaining_percent > 25) {
                        this.bar_variant = "warning";
                    } else {
                        this.bar_variant = "danger";
                    }
                    this.remaining_secs = total_remaining_secs - Math.floor(total_remaining_secs * now_percent);
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
                    if (this.hide_timer_handle !== null) { clearTimeout(this.hide_timer_handle); }
                    this.hide_timer_handle = setTimeout(() => {
                        this.seen = false;
                        this.hide_timer_handle = null;
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
        template: `<lah-transition slide-down>
            <b-navbar v-if="show" toggleable="md" type="dark" variant="dark" class="mb-3" fixed="top">
                <lah-fa-icon size="2x" variant="light" class="mr-2" :icon="icon"></lah-fa-icon>
                <b-navbar-brand :href="location.href" v-html="leading"></b-navbar-brand>
                <b-navbar-toggle target="nav-collapse"></b-navbar-toggle>
                <b-collapse id="nav-collapse" is-nav>
                    <lah-transition appear>
                        <b-navbar-nav>
                            <b-nav-item 
                                v-for="link in links"
                                v-show="link.need_admin ? isAdmin || false : true"
                                :href="Array.isArray(link.url) ? link.url[0] : link.url"
                            >
                                <b-nav-text v-html="link.text" :class="activeCss(link)"></b-nav-text>
                            </b-nav-item>
                        </b-navbar-nav>
                    </lah-transition>
                    <b-navbar-nav class="ml-auto">
                        <b-avatar variant="light" id="header-user-icon" size="2.25rem" :src="avatar"></b-avatar>
                        <b-popover target="header-user-icon" triggers="hover focus" placement="bottomleft" delay="350">
                            <lah-user-card :ip="myip" @not-found="userNotFound" @found="userFound" class="mb-1" title="我的名片"></lah-user-card>
                            <lah-user-message :ip="myip" count="5" title="最新信差訊息" tabs="true" tabsEnd="true"></lah-user-message>
                        </b-popover>
                    </b-navbar-nav>
                </b-collapse>
            </b-navbar>
        </lah-transition>`,
        data: () => { return {
            show: true,
            icon: "question",
            leading: "Unknown",
            active: undefined,
            avatar: undefined,
            links: [{
                text: "今日案件",
                url: ["index.html", "/"],
                icon: "briefcase",
                need_admin: false
            }, {
                text: "資料查詢",
                url: "query.php",
                icon: "file-alt",
                need_admin: true
            }, {
                text: "監控修正",
                url: "watchdog.html",
                icon: "user-secret",
                need_admin: true
            }, {
                text: "逾期案件",
                url: "overdue_reg_cases.html",
                icon: "calendar-alt",
                need_admin: false
            }, {
                text: "信差訊息",
                url: "message.html",
                icon: "comments",
                need_admin: false
            }, {
                text: "繼承應繼分",
                url: "heir_share.html",
                icon: "chart-pie",
                need_admin: false
            }, {
                text: "記錄瀏覽",
                url: "tasklog.html",
                icon: "dog",
                need_admin: true
            }, {
                text: `管理儀錶板`,
                url: "dashboard.html",
                icon: "cubes",
                need_admin: true
            }, {
                text: "測試頁",
                url: "test.html",
                icon: "charging-station",
                need_admin: true
            }]
        }},
        computed: {
            enableUserCardPopover() { return !this.empty(this.myip) },
            url() {
                let page_url = new URL(location.href).pathname.substring(1);
                if (this.empty(page_url)) {
                    return 'index.html';
                }
                return page_url;
            }
        },
        methods: {
            activeCss: function(link) {
                let ret = "";
                if (Array.isArray(link.url)) {
                    for (let i = 0; i < link.url.length; i++) {
                        ret = this.css(link.url[i]);
                        if (!this.empty(ret)) break;
                    }
                } else {
                    ret = this.css(link.url);
                }
                // store detected active link
                if (!this.empty(ret)) { this.active = link }
                
                return ret;
            },
            css: function(url) {
                if (this.url == url) {
                    return "font-weight-bold text-white";
                }
                return "";
            },
            setLeading: function(link) {
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
                }
            },
            userNotFound: function(input) {
                this.$store.commit('myip', null);
                console.warn(`找不到 ${input} 的使用者資訊，無法顯示目前使用者的卡片。`);
            },
            userFound: function(name) {
                this.avatar = `get_pho_img.php?name=${name}`;
                this.setLocalCache('avatar_src_url', this.avatar, 7 * 86400000);
            },
            checkAuthority: async function() {
                if (this.isAdmin === undefined) {
                    await this.$store.dispatch('authenticate');
                }
                if (!this.active || (this.active.need_admin && !this.isAdmin)) {
                    $('body').html("<h3 class='text-center m-5 font-weight-bold'><a href='javascript:history.back()' class='text-danger'>限制存取區域，請返回上一頁！</a></h3>");
                }
                $("body section:first-child").removeClass("hide");
            }
        },
        async created() {
            try {
                let myip = await this.getLocalCache('myip');
                if (this.empty(myip)) {
                    await this.$http.post(CONFIG.JSON_API_EP, {
                        type: 'ip'
                    }).then(res => {
                        myip = res.data.ip || null;
                        this.setLocalCache('myip', myip, 86400000); // expired after a day
                    }).catch(err => {
                        this.error = err;
                    });
                }
                this.$store.commit('myip', myip);
                // restore avatar photo url
                this.avatar = await this.getLocalCache('avatar_src_url');
            } catch (err) {
                console.error(err);
            }
        },
        mounted() {
            this.links.forEach(this.setLeading);
            // add pulse effect for the nav-item
            $(".nav-item").on("mouseenter", function(e) { addAnimatedCSS(this, {name: "pulse"}); });
            this.checkAuthority();
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
        data: function() { return {
            show: true,
            leave_time: 10000,
            classes: ['text-muted', 'fixed-bottom', 'my-2', 'mx-3', 'bg-white', 'border', 'rounded', 'text-center', 'p-2', 'small']
        } },
        mounted() {
            setTimeout(() => this.show = false, this.leave_time);
        }
    });

    Vue.component("lah-user-card", {
        template: `<div>
            <h6 v-show="!empty(title)"><i class="fas fa-user-circle"></i> {{title}}</h6>
            <b-card no-body v-if="useTab">
                <b-tabs card align="center" small>
                    <b-tab v-for="(user_data, idx) in user_rows" :title="user_data['AP_USER_NAME']" :active="idx == 0">
                        <b-card-title>{{user_data['AP_USER_NAME']}}</b-card-title>
                        <b-card-sub-title>{{user_data['AP_JOB']}}</b-card-sub-title>
                        <b-link @click="openPhoto(user_data)">
                            <b-card-img
                                :src="photoUrl(user_data)"
                                :alt="user_data['AP_USER_NAME']"
                                class="img-thumbnail float-right mx-auto ml-2"
                                style="max-width: 220px"
                            ></b-card-img>
                        </b-link>
                        <lah-user-description :user_data="user_data"></lah-user-description>
                    </b-tab>
                </b-tabs>
            </b-card>
            <b-card-group deck v-else-if="useCard">
                <b-card
                    v-for="user_data in user_rows"
                    class="overflow-hidden bg-light"
                    style="max-width: 480px;"
                    :title="user_data['AP_USER_NAME']"
                    :sub-title="user_data['AP_JOB']"
                >
                    <b-link @click="openPhoto(user_data)">
                        <b-card-img
                            :src="photoUrl(user_data)"
                            :alt="user_data['AP_USER_NAME']"
                            class="img-thumbnail float-right mx-auto ml-2"
                            style="max-width: 220px"
                        ></b-card-img>
                    </b-link>
                    <lah-user-description :user_data="user_data"></lah-user-description>
                </b-card>
            </b-card-group>
            <lah-fa-icon icon="exclamation-circle" size="lg" variant="danger" v-else class="my-2">找不到使用者「{{name || id || ip}}」！</lah-fa-icon>
        </div>`,
        components: {
            "lah-user-description": {
                template: `<b-card-text class="small">
                    <lah-fa-icon icon="ban" variant="danger" action="breath" v-if="isLeft" class='mx-auto'> 已離職【{{user_data["AP_OFF_DATE"]}}】</lah-fa-icon>
                    <div>ID：{{user_data["DocUserID"]}}</div>
                    <div v-if="isAdmin">電腦：{{user_data["AP_PCIP"]}}</div>
                    <div v-if="isAdmin">生日：{{user_data["AP_BIRTH"]}} <b-badge v-show="birthAge !== false" :variant="birthAgeVariant" pill>{{birthAge}}歲</b-badge></div>
                    <div>單位：{{user_data["AP_UNIT_NAME"]}}</div>
                    <div>工作：{{user_data["AP_WORK"]}}</div>
                    <div v-if="isAdmin">學歷：{{user_data["AP_HI_SCHOOL"]}}</div>
                    <div v-if="isAdmin">考試：{{user_data["AP_TEST"]}}</div>
                    <div v-if="isAdmin">手機：{{user_data["AP_SEL"]}}</div>
                    <div v-if="isAdmin">到職：{{user_data["AP_ON_DATE"]}} <b-badge v-show="workAge !== false" :variant="workAgeVariant" pill>{{workAge}}年</b-badge></div>
                </b-card-text>`,
                props: ['user_data'],
                data: function() { return {
                    now: new Date(),
                    year: 31536000000
                } },
                computed: {
                    isLeft: function () {
                        return this.user_data['AP_OFF_JOB'] == 'Y';
                    },
                    birthAgeVariant: function() {
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
                    birthAge: function() {
                        let birth = this.user_data["AP_BIRTH"];
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
                    workAge: function() {
                        let AP_ON_DATE = this.user_data["AP_ON_DATE"];
                        let AP_OFF_JOB = this.user_data["AP_OFF_JOB"];
                        let AP_OFF_DATE = this.user_data["AP_OFF_DATE"];
            
                        if(AP_ON_DATE != undefined && AP_ON_DATE != null) {
                            AP_ON_DATE = AP_ON_DATE.date ? AP_ON_DATE.date.split(" ")[0] :　AP_ON_DATE;
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
                    workAgeVariant: function() {
                        let work_age = this.workAge;
                        if (work_age < 5) {
                            return 'success';
                        } else if (work_age < 10) {
                            return 'primary';
                        } else if (work_age < 20) {
                            return 'warning';
                        }
                        return 'danger';
                    }
                },
                methods: {
                    toTWDate: function(ad_date) {
                        tw_date = ad_date.replace('/-/g', "/");
                        // detect if it is AD date
                        if (tw_date.match(/^\d{4}\/\d{2}\/\d{2}$/)) {
                            // to TW date
                            tw_date = (parseInt(tw_date.substring(0, 4)) - 1911) + tw_date.substring(4);
                        }
                        return tw_date;
                    },
                    toADDate: function(tw_date) {
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
        props: ['id', 'name', 'ip', 'title'],
        data: function() { return {
            user_rows: null
        } },
        computed: {
            useTab: function() { return !this.disableMSDBQuery && this.user_rows !== null && this.user_rows !== undefined && this.user_rows.length > 1; },
            useCard: function() { return !this.disableMSDBQuery && this.user_rows !== null && this.user_rows !== undefined && this.user_rows.length > 0; },
            notFound: function() { return `找不到使用者 「${this.name || this.id || this.ip || this.myip}」`; },
            foundName: function() { return this.user_rows[0]["AP_USER_NAME"] }
        },
        methods: {
            photoUrl: function (user_data) {
                return `get_pho_img.php?name=${user_data['AP_USER_NAME']}`;
            },
            openPhoto: function(user_data) {
                // get_pho_img.php?name=${user_data['AP_USER_NAME']}
                //<b-img thumbnail fluid src="https://picsum.photos/250/250/?image=54" alt="Image 1"></b-img>
                showModal({
                    title: `${user_data['AP_USER_NAME']}照片`,
                    message: this.$createElement("div", {
                        domProps: {
                            className: "text-center"
                        }
                    }, [this.$createElement("b-img", {
                        props: {fluid: true, src: `get_pho_img.php?name=${user_data['AP_USER_NAME']}`, alt: user_data['AP_USER_NAME'], thumbnail: true}
                    })]),
                    size: "lg",
                    backdrop_close: true
                });
            },
            cacheUserRows: function() {
                let payload = {};
                // basically cache for one day in localforage
                if (!this.empty(this.id)) { payload[this.id] = this.user_rows; this.setLocalCache(this.id, this.user_rows, this.dayMilliseconds); }
                if (!this.empty(this.name)) { payload[this.name] = this.user_rows; this.setLocalCache(this.name, this.user_rows, this.dayMilliseconds); }
                if (!this.empty(this.ip)) { payload[this.ip] = this.user_rows; this.setLocalCache(this.ip, this.user_rows, this.dayMilliseconds); }
                this.$store.commit('cache', payload);
                if (this.user_rows['AP_PCIP'] == this.myip) {
                    this.$store.commit('myid', this.user_rows['DocUserID']);
                }
            },
            restoreUserRows: async function() {
                try {
                    // find in $store(in-memory)
                    let user_rows = this.cache.get(this.id) || this.cache.get(this.name) || this.cache.get(this.ip);
                    if (this.empty(user_rows)) {
                        // find in localforage
                        user_rows = await this.getLocalCache(this.id) || await this.getLocalCache(this.name) || await this.getLocalCache(this.ip);
                        if (this.empty(user_rows)) {
                            return false;
                        } else {
                            // also put back to $store
                            let payload = {};
                            if (!this.empty(this.id)) { payload[this.id] = user_rows; }
                            if (!this.empty(this.name)) { payload[this.name] = user_rows; }
                            if (!this.empty(this.ip)) { payload[this.ip] = user_rows; }
                            this.$store.commit('cache', payload);
                        }
                    }
                    this.user_rows = user_rows || null;
                    if (this.user_rows && this.user_rows['AP_PCIP'] == this.myip) {
                        this.$store.commit('myid', this.user_rows['DocUserID']);
                    }
                    this.$emit('found', this.foundName);
                } catch (err) {
                    console.error(err);
                }
                return this.user_rows !== null;
            }
        },
        async created() {
            if (!this.disableMSDBQuery) {
                const succeed_cached = await this.restoreUserRows();
                if (!succeed_cached) {
                    if (!(this.name || this.id || this.ip)) this.ip = this.myip || await this.getLocalCache('myip');
                    this.$http.post(CONFIG.JSON_API_EP, {
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
        }
    });

    Vue.component('lah-user-message', {
        template: `<div>
            <h6 v-show="!empty(title)"><lah-fa-icon icon="angle-double-right" variant="dark"></lah-fa-icon> {{title}} <b-form-spinbutton v-if="enable_spinbutton" v-model="count" min="1" size="sm" inline></b-form-spinbutton></h6>
            <b-card-group ref="group" v-if="ready" :columns="columns" :deck="!columns">
                <b-card no-body v-if="useTabs">
                    <b-tabs card :end="endTabs" :pills="endTabs" align="center" small>
                        <b-tab v-for="(message, index) in raws" :title="index+1">
                            <b-card-title title-tag="h6">
                                <lah-fa-icon v-if="message['done'] == 1" icon="eye" variant="muted" title="已看過"></lah-fa-icon>
                                <lah-fa-icon v-else icon="eye-slash" title="還沒看過！"></lah-fa-icon>
                                {{message['xname']}}
                            </b-card-title>
                            <b-card-sub-title sub-title-tag="small"><div class="text-right">{{message['sendtime']['date'].substring(0, 19)}}</div></b-card-sub-title>
                            <b-card-text v-html="format(message['xcontent'])" class="small"></b-card-text>
                        </b-tab>
                    </b-tabs>
                </b-card>
                <b-card v-else
                    v-for="(message, index) in raws"
                    class="overflow-hidden bg-light"
                    :border-variant="border(index)"
                >
                    <b-card-title title-tag="h5">
                        <lah-fa-icon v-if="index == 0" icon="eye"></lah-fa-icon>
                        <lah-fa-icon v-else-if="index == 1" icon="eye" variant="primary" prefix="far"></lah-fa-icon>
                        <strong v-else>
                            <lah-fa-icon v-if="message['done'] != 1" icon="eye-slash" title="還沒看過！"></lah-fa-icon>
                            {{index+1}}.
                        </strong>
                        {{message['xname']}}
                    </b-card-title>
                    <b-card-sub-title sub-title-tag="small"><div class="text-right">{{message['sendtime']['date'].substring(0, 19)}}</div></b-card-sub-title>
                    <b-card-text v-html="format(message['xcontent'])" class="small"></b-card-text>
                </b-card>
            </b-card-group>
            <lah-fa-icon variant="danger" icon="exclamation-circle" size="lg" v-else>{{notFound}}</lah-fa-icon>
        </div>`,
        props: ['id', 'name', 'ip', 'count', 'title', 'spinbutton', 'tabs', 'tabsEnd', 'noCache'],
        data: () => { return {
            raws: undefined,
            urlPattern: /((http|https|ftp):\/\/[\w?=&.\/-;#~%-]+(?![\w\s?&.\/;#~%"=-]*>))/ig,
            idPattern: /^HB\d{4}$/i
        } },
        watch: {
            count: function(nVal, oVal) { this.load() },
            id: function(nVal, oVal) {
                if (this.idPattern.test(nVal)) {
                    this.load();
                }
            }
        },
        computed: {
            ready: function() { return !this.empty(this.raws) },
            notFound: function() { return `「${this.name || this.id || this.ip || this.myip}」找不到信差訊息！` },
            columns: function() { return !this.useTabs && this.count > 3 },
            enable_spinbutton: function() { return !this.empty(this.spinbutton) },
            useTabs: function() { return !this.empty(this.tabs) },
            endTabs: function() { return !this.empty(this.tabsEnd)},
            cache_key: function() { return `${this.id||this.name||this.ip||this.myip}-messeages` }
        },
        methods: {
            format: function(content) {
                return content
                    .replace(this.urlPattern, "<a href='$1' target='_blank' title='點擊前往'>$1</a>")
                    .replace(/\r\n/g,"<br />");
            },
            border: function(index) { return index == 0 ? 'danger' : index == 1 ? 'primary' : '' },
            load: async function(force = false) {
                if (!this.disableMSDBQuery) {
                    try {
                        if (!this.empty(this.noCache) || force) await this.removeLocalCache(this.cache_key);
                        const raws = await this.getLocalCache(this.cache_key);
                        if (raws !== false && raws.length == this.count) {
                            this.raws = raws;
                        } else if (raws !== false && raws.length >= this.count) {
                            this.raws = raws.slice(0, this.count);
                        } else {
                            this.$http.post(CONFIG.JSON_API_EP, {
                                type: "user_message",
                                id: this.id,
                                name: this.name,
                                ip: this.ip || this.myip,
                                count: this.count
                            }).then(res => {
                                if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                    this.raws = res.data.raw
                                    this.setLocalCache(this.cache_key, this.raws, 60000);   // 1 min
                                } else {
                                    addNotification({
                                        title: "查詢信差訊息",
                                        message: res.data.message,
                                        type: "warning"
                                    });
                                }
                            }).catch(err => {
                                this.error = err;
                            });
                        }
                    } catch(err) {
                        this.error = err;
                    }
                }
            }
        },
        created() {
            this.count = this.count || 1;
            this.load();
        },
        mounted() { }
    });

    let regCaseMixin = {
        props: {
            bakedData: { type: Object, default: undefined},
            id: { type: String, default: "" } // the id format should be '109HB04001234'
        },
        computed: {
            year() { return this.bakedData ? this.bakedData["RM01"] : this.id.substring(0, 3) },
            code() { return this.bakedData ? this.bakedData["RM02"] : this.id.substring(3, 7) },
            number() { return this.bakedData ? this.bakedData["RM03"] : this.id.substring(7) },
            ready() { return !this.empty(this.bakedData) },
            storeBakedData() { return this.storeParams['RegBakedData'] }
        },
        watch: {
            bakedData: function(nObj, oObj) {
                this.addToStoreParams('RegBakedData', nObj);
            }
        },
        async created() {
            if (this.bakedData === undefined) {
                this.isBusy = true;
                await this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: `${this.year}${this.code}${this.number}`
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        showAlert({title: "擷取登記案件失敗", message: res.data.message, type: "warning"});
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
                <div v-if="wip" class="filter-btn-group col-auto">
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
                <div v-if="wip" class="filter-btn-group col-auto">
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
                <div v-if="wip" class="filter-btn-group col-auto">
                    <b-button @click="updateRM42" size="sm" variant="outline-primary"><lah-fa-icon icon="edit"> 更新</lah-fa-icon></b-button>
                </div>
            </div>
            <p v-if="showProgress" class="mt-2"><lah-reg-table type="sm" :bakedData="[bakedData]" :no-caption="true" class="small"></lah-reg-table></p>
        </div>`,
        props: ['progress'],
        data: () => { return {
            rm30_orig: "",
            rm39_orig: "",
            rm42_orig: "",
            sync_rm30_1: true,
            rm30_map: [
                { value: 'A', text: 'A: 初審' },
                { value: 'B', text: 'B: 複審' },
                { value: 'C', text: 'C: 公告' },
                { value: 'I', text: 'I: 補正' },
                { value: 'R', text: 'R: 登錄' },
                { value: 'C', text: 'C: 校對' },
                { value: 'U', text: 'U: 異動完成' },
                { value: 'F', text: 'F: 結案' },
                { value: 'X', text: 'X: 補正初核' },
                { value: 'Y', text: 'Y: 駁回初核' },
                { value: 'J', text: 'J: 撤回初核' },
                { value: 'K', text: 'K: 撤回' },
                { value: 'Z', text: 'Z: 歸檔' },
                { value: 'N', text: 'N: 駁回' },
                { value: 'L', text: 'L: 公告初核' },
                { value: 'E', text: 'E: 請示' },
                { value: 'D', text: 'D: 展期' },
            ],
            rm39_map: [
                { value: 'B', text: 'B: 登錄開始' },
                { value: 'R', text: 'R: 登錄完成' },
                { value: 'C', text: 'C: 校對開始' },
                { value: 'D', text: 'D: 校對完成' },
                { value: 'S', text: 'S: 異動開始' },
                { value: 'F', text: 'F: 異動完成' },
                { value: 'G', text: 'G: 異動有誤' },
                { value: 'P', text: 'P: 競合暫停' },
            ],
            rm42_map: [
                { value: '0', text: '0: 登記移案' },
                { value: 'B', text: 'B: 登錄中' },
                { value: 'R', text: 'R: 登錄完成' },
                { value: 'C', text: 'C: 校對中' },
                { value: 'D', text: 'D: 校對完成' },
                { value: 'E', text: 'E: 登錄有誤' },
                { value: 'S', text: 'S: 異動開始' },
                { value: 'F', text: 'F: 異動完成' },
                { value: 'G', text: 'G: 異動有誤' }
            ],
            fields: [
                {key: "收件字號", sortable: true},
                {key: "登記原因", sortable: true},
                {key: "辦理情形", sortable: true},
                {key: "作業人員", sortable: true},
                {key: "登記處理註記", label: "登記註記", sortable: true},
                {key: "地價處理註記", label: "地價註記", sortable: true},
                {key: "預定結案日期", label:"期限", sortable: true}
            ]
        } },
        computed: {
            showProgress() { return !this.empty(this.progress) },
            attachEvent() { return this.showProgress },
            wip() { return this.empty(this.bakedData["RM31"]) },
            rm30() { return this.bakedData["RM30"] || "" },
            rm39() { return this.bakedData["RM39"] || "" },
            rm42() { return this.bakedData["RM42"] || "" }
        },
        methods: {
            updateRegCaseCol: function(arguments) {
                if ($(arguments.el).length > 0) {
                    // remove the button
                    $(arguments.el).remove();
                }
                this.isBusy = true;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_upd_col",
                    rm01: arguments.rm01,
                    rm02: arguments.rm02,
                    rm03: arguments.rm03,
                    col: arguments.col,
                    val: arguments.val
                }).then(res => {
                    console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `更新案件「${arguments.col}」欄位回傳狀態碼有問題【${res.data.status}】`);
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        addNotification({title: "更新案件欄位", message: `「${arguments.col}」更新完成`, variant: "success"});
                    } else {
                        addNotification({title: "更新案件欄位", message: `「${arguments.col}」更新失敗【${res.data.status}】`, variant: "warning"});
                    }
                }).catch(err => {
                    this.error = err;
                }).finally(() => {
                    this.isBusy = false;
                });
            },
            updateRM30: function(e) {
                if (this.rm30 == this.rm30_orig) {
                    addNotification({title: "更新案件辦理情形",  message: "案件辦理情形沒變動", type: "warning"});
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
            updateRM39: function(e) {
                if (this.rm39 == this.rm39_orig) {
                    addNotification({title: "更新登記處理註記", message: "登記處理註記沒變動", type: "warning"});
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
            updateRM42: function(e) {
                if (this.rm42 == this.rm42_orig) {
                    addNotification({title: "更新地價處理註記", message: "地價處理註記沒變動", type: "warning"});
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
                addAnimatedCSS(".reg_case_id", { name: "flash" }).off("click").on("click", window.vueApp.fetchRegCase);
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
                            <b-link variant="muted" @click.stop="window.vueApp.open(case_data_url, $event)" :title="'收件資料 on ' + ap_server"><lah-fa-icon icon="external-link-alt" variant="primary"></lah-fa-icon></b-link>
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
                                    <a :title="'收件資料 on ' + ap_server" href="javascript:void(0)" @click="window.vueApp.open(case_data_url, $event)">
                                        {{bakedData.收件字號}}
                                    </a> <br/>
                                    收件時間：{{bakedData.收件時間}} <br/>
                                    測量案件：{{bakedData.測量案件}} <br/>
                                    限辦期限：<span v-html="bakedData.限辦期限"></span> <br/>
                                    作業人員：<span class='user_tag'>{{bakedData.作業人員}}</span> <br/>
                                    辦理情形：{{bakedData.辦理情形}} <br/>
                                    登記原因：{{bakedData.登記原因}} <br/>
                                    區域：{{area}}【{{bakedData.RM10}}】 <br/>
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
                            <b-link variant="muted" @click.stop="window.vueApp.open(case_status_url, $event)" :title="'案件辦理情形 on ' + ap_server"><lah-fa-icon icon="external-link-alt" variant="primary"></lah-fa-icon></b-link>
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
                            <lah-fa-icon icon="chart-line"> <strong>案件時間線</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-timeline ref="timeline" :baked-data="bakedData"></lah-reg-case-temp-mgt>
                    </b-tab>
                    <b-tab v-if="isAdmin" lazy>
                        <template v-slot:title>
                            <lah-fa-icon icon="database"> <strong>狀態管理</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-state-mgt :baked-data="bakedData"></lah-reg-case-state-mgt>
                    </b-tab>
                    <b-tab v-if="isAdmin" lazy>
                        <template v-slot:title>
                            <lah-fa-icon icon="buffer" prefix="fab"> <strong>暫存檔管理</strong></lah-fa-icon>
                        </template>
                        <lah-reg-case-temp-mgt :baked-data="bakedData"></lah-reg-case-temp-mgt>
                    </b-tab>
                </b-tabs>
            </b-card>
        </div>`,
        props: ['tabsEnd'],
        data: () => {
            return {
                area: "",
                rm10: null,
                ap_server: "220.1.35.123",
                case_status_url: "",
                case_data_url: "",
                is_ongoing: false,
                show_op_card: true
            }
        },
        computed: {
            tabsAtEnd() { return !this.empty(this.tabsEnd) }
        },
        methods: {
            handleNotFound: function(input) { this.show_op_card = false }
        },
        mounted() {
            this.rm10 = this.bakedData.RM10 ? this.bakedData.RM10 : "XX";
            switch (this.rm10) {
                case "03":
                    this.area = "中壢區";
                    break;
                case "12":
                    this.area = "觀音區";
                    break;
                default:
                    this.area = "其他(" + this.bakedData.資料管轄所 + "區)";
                    break;
            }
            this.case_status_url = `http://${this.ap_server}:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=${this.year}&word=${this.code}&code=${this.number}&sdlyn=N&RM90=`;
            this.case_data_url = `http://${this.ap_server}:9080/LandHB/CAS/CCD01/CCD0103.jsp?rm01=${this.year}&rm02=${this.code}&rm03=${this.number}`
            this.is_ongoing = this.empty(this.bakedData.結案已否);
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
            <lah-fa-icon v-else icon="exclamation-circle" variant="success" size="lg"> 無暫存檔。</lah-fa-icon>
        </div>`,
        data: function() { return {
            filtered: null,
            cleanAllBackupFlag: false,
            backupFlags: []
        }},
        computed: {
            found() { return !this.empty(this.filtered) },
            prefix() { return `${this.year}-${this.code}-${this.number}` }
        },
        methods: {
            title: function (item) {
                return item[0] == "MOICAT.RINDX" || item[0] == "MOIPRT.PHIND" ? "重要案件索引，無法刪除！" : "";
            },
            download: function(content, filename) {
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
            backupAll: function(e) {
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
            cleanAll: function(e) {
                if (this.cleanAllBackupFlag !== true) {
                    showAlert({
                        title: "清除全部暫存檔",
                        subtitle: `${this.year}-${this.code}-${this.number}`,
                        message: "請先備份！",
                        type: "warning"
                    });
                    addAnimatedCSS("#backup_temp_btn_all", { name: "tada" });
                    return;
                }
                let msg = "<h6><strong class='text-danger'>★警告★</strong>：無法復原請先備份!!</h6>清除案件 " + this.year + "-" + this.code + "-" + this.number + " 全部暫存檔?";
                showConfirm(msg, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: 'clear_temp_data',
                        year: this.year,
                        code: this.code,
                        number: this.number,
                        table: ''
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除暫存資料回傳狀態碼有問題【" + res.data.status + "】");
                        addNotification({
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
            backup: function(item, idx, e) {
                this.isBusy = true;
                let filename = `${this.prefix}-${item[0]}-TEMP-DATA.sql`;
                this.download(this.getInsSQL(item), filename);
                this.backupFlags[idx] = true;
                $(e.target).attr("disabled", this.backupFlags[idx]);
                this.isBusy = false;
            },
            clean: function(item, idx, e) {
                let table = item[0];
                if (this.backupFlags[idx] !== true) {
                    showAlert({
                        title: `清除 ${table} 暫存檔`,
                        subtitle: `${this.year}-${this.code}-${this.number}`,
                        message: `請先備份 ${table} ！`,
                        type: "warning"
                    });
                    addAnimatedCSS(`#backup_temp_btn_${idx}`, { name: "tada" });
                    return;
                }
                let msg = "<h6><strong class='text-danger'>★警告★</strong>：無法復原請先備份!!</h6>清除案件 " + this.year + "-" + this.code + "-" + this.number + " " + table + " 暫存檔?";
                showConfirm(msg, () => {
                    this.isBusy = true;
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: 'clear_temp_data',
                        year: this.year,
                        code: this.code,
                        number: this.number,
                        table: table
                    }).then(res => {
                        this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除暫存資料回傳狀態碼有問題【" + res.data.status + "】");
                        addNotification({
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
            showSQL: function(item) {
                showModal({
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
            popup: () => {
                showModal({
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
        },
        mounted() {
            this.isBusy = true;
            this.$http.post(CONFIG.JSON_API_EP, {
                type: "query_temp_data",
                year: this.year,
                code: this.code,
                number: this.number
            }).then(res => {
                this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `查詢暫存資料回傳狀態碼有問題【${res.data.status}】`);
                
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
        template: `<div><lah-chart type="line" label="案件時間線" :items="items"></lah-chart></div>`,
        data: function() { return {
            items: []
        } },
        computed: {
            border() { return this.ready ? '' : 'danger' }
        },
        watch: {
            bakedData: function(nData, oData) { this.prepareItems() }
        },
        methods: {
            prepareItems: function() {
                let items = [];
                items.push(['初審耗時',  this.bakedData['初審耗時']]);
                items.push(['複審耗時',  this.bakedData['複審耗時']]);
                items.push(['准登耗時',  this.bakedData['准登耗時']]);
                this.items = items;
            }
        },
        created() { this.prepareItems() }
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
                :busy="!bakedData"
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
                    <a href="javascript:void(0)" @click="userinfo(item['初審人員'], item['RM45'])" v-b-popover.top.hover.focus="passedTime(item['初審耗時'])">{{item["初審人員"]}}</a>
                </template>
                <template v-slot:cell(複審人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['複審人員'], item['RM47'])" v-b-popover.top.hover.focus="passedTime(item['複審耗時'])">{{item["複審人員"]}}</a>
                </template>
                <template v-slot:cell(收件人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['收件人員'], item['RM96'])">{{item["收件人員"]}}</a>
                </template>
                <template v-slot:cell(作業人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['作業人員'], item['RM30_1'])">{{item["作業人員"]}}</a>
                </template>
                <template v-slot:cell(准登人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['准登人員'], item['RM63'])" v-b-popover.top.hover.focus="passedTime(item['准登耗時'])">{{item["准登人員"]}}</a>
                </template>
                <template v-slot:cell(登記人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['登記人員'], item['RM55'])">{{item["登記人員"]}}</a>
                </template>
                <template v-slot:cell(校對人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['校對人員'], item['RM57'])">{{item["校對人員"]}}</a>
                </template>
                <template v-slot:cell(結案人員)="{ item }">
                    <a href="javascript:void(0)" @click="userinfo(item['結案人員'], item['RM59'])">{{item["結案人員"]}}</a>
                </template>
            </b-table>
        </lah-transition>`,
        props: ['bakedData', 'maxHeight', 'type', 'fields', 'mute', 'noCaption', 'color', 'icon', 'iconVariant'],
        data: () => { return {
            transProps: { name: 'rollIn' }
        } },
        computed: {
            tblFields: function() {
                if (!this.empty(this.fields)) return this.fields;
                switch(this.type) {
                    case "md":
                        return [
                            {key: "收件字號", sortable: this.sort},
                            {key: "登記原因", sortable: this.sort},
                            {key: "辦理情形", sortable: this.sort},
                            {key: "初審人員", sortable: this.sort},
                            {key: "作業人員", sortable: this.sort},
                            {key: "收件時間", sortable: this.sort},
                            {key: "限辦時間", sortable: this.sort}
                            //{key: "預定結案日期", label:"限辦期限", sortable: this.sort}
                        ];
                    case "lg":
                        return [
                            {key: "收件字號", sortable: this.sort},
                            {key: "收件日期", sortable: this.sort},
                            {key: "登記原因", sortable: this.sort},
                            {key: "辦理情形", sortable: this.sort},
                            {key: "收件人員", label: "收件", sortable: this.sort},
                            {key: "作業人員", label: "作業", sortable: this.sort},
                            {key: "初審人員", label: "初審", sortable: this.sort},
                            {key: "複審人員", label: "複審", sortable: this.sort},
                            {key: "准登人員", label: "准登", sortable: this.sort},
                            {key: "登記人員", label: "登簿", sortable: this.sort},
                            {key: "校對人員", label: "校對", sortable: this.sort},
                            {key: "結案人員", label: "結案", sortable: this.sort}
                        ];
                    case "xl":
                        return [
                            //{key: "燈號", sortable: this.sort},
                            {key: "序號", sortable: !this.sort},
                            {key: "收件字號", sortable: this.sort},
                            {key: "收件時間", sortable: this.sort},
                            {key: "限辦時間", label: "限辦", sortable: this.sort},
                            {key: "登記原因", sortable: this.sort},
                            {key: "辦理情形", sortable: this.sort},
                            {key: "收件人員", label: "收件", sortable: this.sort},
                            {key: "作業人員", label: "作業", sortable: this.sort},
                            {key: "初審人員", label: "初審", sortable: this.sort},
                            {key: "複審人員", label: "複審", sortable: this.sort},
                            {key: "准登人員", label: "准登", sortable: this.sort},
                            {key: "登記人員", label: "登簿", sortable: this.sort},
                            {key: "校對人員", label: "校對", sortable: this.sort},
                            {key: "結案人員", label: "結案", sortable: this.sort}
                            //{key: "結案狀態", label: "狀態", sortable: this.sort}
                        ];
                    case "flow":
                        return [
                            {key: "辦理情形", sortable: this.sort},
                            {key: "收件人員", label: "收件", sortable: this.sort},
                            {key: "作業人員", label: "作業", sortable: this.sort},
                            {key: "初審人員", label: "初審", sortable: this.sort},
                            {key: "複審人員", label: "複審", sortable: this.sort},
                            {key: "准登人員", label: "准登", sortable: this.sort},
                            {key: "登記人員", label: "登簿", sortable: this.sort},
                            {key: "校對人員", label: "校對", sortable: this.sort},
                            {key: "結案人員", label: "結案", sortable: this.sort}
                        ];
                    default:
                        return [
                            {key: "RM01", label: "收件字號", sortable: this.sort},
                            {key: "RM07_1", label: "收件日期", sortable: this.sort},
                            {key: "RM09", label: "登記原因", sortable: this.sort},
                            {key: "辦理情形", sortable: this.sort},
                        ];
                }
            },
            count() { return this.bakedData ? this.bakedData.length : 0 },
            caption() {
                if (this.mute || this.noCaption) return false;
                return this.bakedData ? '登記案件找到 ' + this.count + '件' : '讀取中';
                
            },
            sticky() { return this.maxHeight ? this.count > 0 ? true : false : false },
            style() {
                const parsed = parseInt(this.maxHeight);
                return isNaN(parsed) ? '' : `max-height: ${parsed}px`;
            },
            showIcon() { return !this.empty(this.icon) },
            sort() { return this.empty(this.mute) }
        },
        methods: {
            fetch(data) {
                let id = `${data["RM01"]}${data["RM02"]}${data["RM03"]}`;
                this.$http.post(CONFIG.JSON_API_EP, {
                    type: "reg_case",
                    id: id
                }).then(res => {
                    if (res.data.status == XHR_STATUS_CODE.DEFAULT_FAIL || res.data.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
                        showAlert({title: "顯示登記案件詳情", message: res.data.message, type: "warning"});
                    } else {
                        showModal({
                            message: this.$createElement("lah-reg-case-detail", { props: { bakedData: res.data.baked } }),
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
                showModal({
                    title: `${name} 使用者資訊${this.empty(id) ? '' : ` (${id})`}`,
                    message: this.$createElement('lah-user-card', { props: { id: id, name: name }})
                });
            },
            bakedContent(row) {
                return row.item[row.field.label];
            },
            reason(row) {
                return row.item["RM09"] + " : " + (this.empty(row.item["登記原因"]) ? row.item["RM09_CHT"] : row.item["登記原因"]);
            },
            trClass(item, type) {
                if(item && type == 'row') return this.color ? item["紅綠燈背景CSS"] : `filter-${item["燈號"]}`;
            },
            passedTime(time_duration_secs) {
                if (isNaN(time_duration_secs)) return false;
                if (time_duration_secs < 60) return "耗時 " + time_duration_secs + " 秒";
                return "耗時 " + Number.parseFloat(time_duration_secs / 60).toFixed(2) + " 分鐘";
            }
        },
        created() { this.type = this.type || '' },
        mounted() { }
    });

    // need to include Chart.min.js (chart.js) first.
    Vue.component("lah-chart", {
        template: `<div><canvas class="w-100">圖形初始化失敗</canvas></div>`,
        props: {
            type: { type: String, default: 'bar'},
            label: { type: String, default: '統計圖表'},
            items: { type: Array, default: [] }
        },
        data: function () { return {
            inst: null,
            chartData: null
        } },
        watch: {
            type: function (val) { setTimeout(this.buildChart, 0) },
            chartData: function(newObj) { setTimeout(this.buildChart, 0)  },
            items: function(newItems) { this.setData(newItems) }
        },
        methods: {
            resetData: function() {
                this.chartData = {
                    labels:[],
                    legend: {
                        display: true
                    },
                    datasets:[{
                        label: this.label,
                        backgroundColor:[],
                        data: [],
                        borderColor:`rgb(22, 22, 22)`,
                        order: 1,
                        opacity: 0.6,
                        snapGaps: true
                    }]
                };
            },
            setData: function(items) {
                this.resetData();
                let opacity = this.chartData.datasets[0].opacity;
                items.forEach(item => {
                    this.chartData.labels.push(item[0]);            // first element is label
                    this.chartData.datasets[0].data.push(item[1]);  // second element is data count
                    // randoom color for this item
                    this.chartData.datasets[0].backgroundColor.push(`rgb(${this.rand(255)}, ${this.rand(255)}, ${this.rand(255)}, ${opacity})`);
                });
            },
            buildChart: function (opts = {}, ) {
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
                switch(this.type) {
                    case "pie":
                    case "polarArea":
                    case "doughnut":
                        // put legend to the right for some chart type
                        opts.legend = {
                            display: true,
                            position: opts.legend_pos || 'right'
                        };
                        break;
                    case "radar":
                        break;
                    default:
                        opts.scales = {
                            yAxes: [{
                                display: true,
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        };
                }
                // use chart.js directly
                let ctx = this.$el.childNodes[0];
                let that = this;
                this.inst = new Chart(ctx, {
                    type: this.type,
                    data: this.chartData,
                    options: Object.assign({
                        tooltips: {
                            callbacks: {
                                label: function (tooltipItem, data) {
                                    // add percent ratio to the label
                                    let dataset = data.datasets[tooltipItem.datasetIndex];
                                    let sum = dataset.data.reduce(function (previousValue, currentValue, currentIndex, array) {
                                        return previousValue + currentValue;
                                    });
                                    let currentValue = dataset.data[tooltipItem.index];
                                    let percent = Math.round(((currentValue / sum) * 100));
                                    return ` ${data.labels[tooltipItem.index]} : ${currentValue} [${percent}%]`;
                                }
                            }
                        },
                        title: { display: false, text: "自訂標題", position: "bottom" },
                        onClick: function(e) {
                            let payload = {};
                            payload["point"] = that.inst.getElementAtEvent(e)[0];
                            if (payload["point"]) {
                                payload["label"] = that.inst.data.labels[payload["point"]._index];
                                payload["value"] = that.inst.data.datasets[payload["point"]._datasetIndex].data[payload["point"]._index];
                            }
                            // parent uses a handle function to catch the event, e.g. catchClick(e, payload) { ... }
                            that.$emit("click", e, payload);
                        }
                    }, opts)
                });
            },
            toBase64Image: function() { return this.inst.toBase64Image() },
            downloadBase64PNG: function(filename = "download.png") {
                const link = document.createElement('a');
                link.href = this.toBase64Image();
                link.setAttribute("download", filename);
                document.body.appendChild(link);
                link.click();
                //afterwards we remove the element again
                link.remove();
            }
        },
        created() {
            this.setData(this.items);
            setTimeout(this.buildChart, 0);
        }
    });

} else {
    console.error("vue.js not ready ... lah-xxxxxxxx components can not be loaded.");
}
