//<![CDATA[
const CONFIG = {
    DISABLE_MSDB_QUERY: false,
    TEST_MODE: false
}
// 跨縣市主機
const landhb_svr = "220.1.35.123";
// the status code must be the same as server side response
const XHR_STATUS_CODE = {
    SUCCESS_NORMAL: 1,
    SUCCESS_WITH_MULTIPLE_RECORDS: 2,
    DEFAULT_FAIL: 0,
    UNSUPPORT_FAIL: -1,
    FAIL_WITH_LOCAL_NO_RECORD: -2,
    FAIL_NOT_VALID_SERVER: -3,
    FAIL_WITH_REMOTE_NO_RECORD: -4
}
const LOADING_PATTERNS = [
    "ld-heartbeat", "ld-beat", "ld-blink", "ld-bounce", "ld-bounceAlt", "ld-breath", "ld-wrench", "ld-surprise",
    "ld-clock", "ld-jump", "ld-hit", "ld-fade", "ld-flip", "ld-float", "ld-move-ltr", "ld-tremble", "ld-tick",
    "ld-move-rtl", "ld-move-ttb", "ld-move-btt", "ld-move-fade-ltr", "ld-move-fade-rtl", "ld-move-fade-ttb",
    "ld-move-fade-btt", "ld-dim", "ld-swing", "ld-wander", "ld-pulse", "ld-cycle", "ld-cycle-alt", "ld-damage",
    "ld-fade", "ld-flip", "ld-flip-h", "ld-flip-v", "ld-float", "ld-jelly", "ld-jelly-alt", "ld-jingle",
    "ld-measure", "ld-metronome", "ld-orbit", "ld-rubber-h", "ld-rubber-v", "ld-rush-btt", "ld-rush-ttb",
    "ld-rush-ltr", "ld-rush-rtl", "ld-shake-h", "ld-shake-v", "ld-shiver", "ld-skew", "ld-skew-alt", "ld-slide-btt",
    "ld-slide-ltr", "ld-slide-rtl", "ld-slide-ttb", "ld-smash", "ld-spin", "ld-spin-fast", "ld-squeeze",
    "ld-swim", "ld-swing", "ld-tick-alt", "ld-vortex", "ld-vortex-alt", "ld-wander-h", "ld-wander-v"
];
const LOADING_PREDEFINED = [
    "fa fa-snowflake ld-swim fa-2x",
    "ld-spinner ld-orbit fa-lg",
    "ld-pie ld-flip fa-2x",
    "fas fa-sync ld-spin fa-lg",
    "fas fa-spinner fa-spin fa-2x",
    "fas fa-radiation-alt ld-cycle fa-2x",
    "fas fa-radiation ld-spin-fast fa-2x",
    "fas fa-asterisk ld-spin fa-lg",
    "fas fa-bolt ld-bounce fa-2x",
    "fas fa-biking ld-move-ltr fa-2x",
    "fas fa-snowboarding ld-rush-ltr fa-2x",
    "fas fa-yin-yang fa-spin fa-2x",
    "fas fa-biohazard ld-metronome fa-2x",
    "fas fa-baseball-ball ld-bounce fa-2x",
    "fas fa-basketball-ball ld-beat fa-2x",
    "fas fa-stroopwafel ld-metronome fa-2x",
    "fas fa-fan ld-spin-fast fa-2x",
    "fas fa-cog ld-swing fa-2x",
    "fas fa-compact-disc ld-spin-fast fa-2x",
    "fas fa-crosshairs ld-swim fa-2x",
    "far fa-compass ld-tick fa-2x",
    "fas fa-compass fa-pulse fa-2x",
    "fas fa-anchor ld-swing fa-2x",
    "fas fa-fingerprint ld-damage fa-2x",
    "fab fa-angellist ld-metronome fa-2x"
]
const LOADING_SHAPES_COLOR = ["text-primary", "text-secondary", "text-danger", "text-info", "text-warning", "text-default", ""];

const ANIMATED_PATTERNS = ["bounce", "flash", "pulse", "rubberBand", "shake", "headShake", "swing", "tada", "wobble", "jello", "hinge"];
const ANIMATED_TRANSITIONS = [
    // rotate
    { in: "animated rotateIn", out: "animated rotateOut" },
    { in: "animated rotateInDownLeft", out: "animated rotateOutDownLeft" },
    { in: "animated rotateInDownRight", out: "animated rotateOutDownRight" },
    { in: "animated rotateInUpLeft", out: "animated rotateOutUpLeft" },
    { in: "animated rotateInUpRight", out: "animated rotateOutUpRight" },
    // bounce
    { in: "animated bounceIn", out: "animated bounceOut" },
    { in: "animated bounceInUp", out: "animated bounceOutDown" },
    { in: "animated bounceInDown", out: "animated bounceOutUp" },
    { in: "animated bounceInRight", out: "animated bounceOutLeft" },
    { in: "animated bounceInLeft", out: "animated bounceOutRight" },
    // fade
    { in: "animated fadeIn", out: "animated fadeOut" },
    { in: "animated fadeInDown", out: "animated fadeOutUp" },
    { in: "animated fadeInDownBig", out: "animated fadeOutUpBig" },
    { in: "animated fadeInLeft", out: "animated fadeOutRight" },
    { in: "animated fadeInLeftBig", out: "animated fadeOutRightBig" },
    { in: "animated fadeInRight", out: "animated fadeOutLeft" },
    { in: "animated fadeInRightBig", out: "animated fadeOutLeftBig" },
    { in: "animated fadeInUp", out: "animated fadeOutDown" },
    { in: "animated fadeInUpBig", out: "animated fadeOutDownBig" },
    // flip
    { in: "animated flipInX", out: "animated flipOutX" },
    { in: "animated flipInY", out: "animated flipOutY" },
    // lightspeed
    { in: "animated lightSpeedIn", out: "animated lightSpeedOut" },
    // roll
    { in: "animated rollIn", out: "animated rollOut" },
    // zoom
    { in: "animated zoomIn", out: "animated zoomOut" },
    { in: "animated zoomInDown", out: "animated zoomOutUp" },
    { in: "animated zoomInLeft", out: "animated zoomOutRight" },
    { in: "animated zoomInRight", out: "animated zoomOutLeft" },
    { in: "animated zoomInUp", out: "animated zoomOutDown" },
    // slide
    { in: "animated slideInDown", out: "animated slideOutUp" },
    { in: "animated slideInUp", out: "animated slideOutDown" },
    { in: "animated slideInLeft", out: "animated slideOutRight" },
    { in: "animated slideInRight", out: "animated slideOutLeft" }
];

let VueTransition = {
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
            this.animated_in = `animated rotateOut ${this.cfg_css}`;
        } else if (this.bounce) {
            this.animated_in = `animated bounceIn ${this.cfg_css}`;
            this.animated_in = `animated bounceOut ${this.cfg_css}`;
        } else if (this.zoom) {
            this.animated_in = `animated zoomIn ${this.cfg_css}`;
            this.animated_in = `animated zoomOut ${this.cfg_css}`;
        } else if (this.slide) {
            this.animated_in = `animated fadeIn ${this.cfg_css}`;
            this.animated_in = `animated fadeOut ${this.cfg_css}`;
        } else if (this.fade) {
            this.animated_in = `animated slideInDown ${this.cfg_css}`;
            this.animated_in = `animated slideOutUp ${this.cfg_css}`;
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
}

let asyncFetch = async function(url, opts) {
    opts = Object.assign({
        method: "POST",
        body: new FormData(),
        blob: false
    }, opts);
    let response = await fetch(url, opts);
    return opts.blob ? await response.blob() : await response.json();
}

let trim = text => {
    if (isEmpty(text)) {
        return "";
    }
    return text.replace(/[^a-zA-Z0-9]/g, "");
}

let isEmpty = variable => {
    if (variable === undefined || $.trim(variable) == "") {
        return true;
    }
    
    if (typeof variable == "object" && variable.length == 0) {
        return true;
    }
    return false;
}

let toTWDate = d => {
    return (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
}

let addUserInfoEvent = () => {
    $(".user_tag").off("click");
    $(".user_tag").on("click", xhrQueryUserInfo);
}

let checkCaseUIData = (data) => {
    let year = data.year;
    let code = data.code;
    let num = data.num;
    if (isEmpty(year)) {
        showAlert({
            message: "請重新選擇「年」欄位!",
            type: "danger"
        });
        return false;
    }
    if (isEmpty(code)) {
        showAlert({
            message: "請重新選擇「字」欄位!",
            type: "danger"
        });
        return false;
    }
    let number = num.replace(/\D/g, "");
    let offset = 6 - number.length;
    if (isEmpty(number) || isNaN(number) || offset < 0) {
        showAlert({
            message: `「號」格式有問題，請查明修正【目前：${num}】`,
            type: "danger"
        });
        return false;
    }
    return true;
}

let validateCaseInput = (year_id_selector, code_id_selector, number_id_selector, output_id_selector) => {
    var year = $(year_id_selector).val().replace(/\D/g, "");
    
    var code = $(code_id_selector).val();
    if (isEmpty(code)) {
        showPopper(code_id_selector);
        return false;
    }

    var number_element = $(number_id_selector);
    if (number_element.length) {
        var number = $(number_id_selector).val().replace(/\D/g, "");
        if (isEmpty(number) || isNaN(number)) {
            showPopper(number_id_selector);
            return false;
        }

        // make total number length is 6
        var offset = 6 - number.length;
        if (offset < 0) {
            // check element existence
            if ($(output_id_selector).length) {
                $(output_id_selector).html("<strong class='text-danger'>號的長度為6個數字！</strong>");
            } else {
                alert("號的長度為6個數字！");
            }
            number_element.focus();
            return false;
        } else if (offset > 0) {
            for (var i = 0; i < offset; i++) {
                number = "0" + number;
            }
            number_element.val(number);
        }
    }
    return true;
}

let showPopper = (selector, content, timeout) => {
    if (!isEmpty(content)) {
        $(selector).attr("data-content", content);
    }
    $(selector).popover('show');
    setTimeout(function() {
        $(selector).popover('hide');
    }, isEmpty(timeout) || isNaN(timeout) ? 2000 : timeout);
    scrollToElement(selector);
}

let addNotification = (msg, opts) => {
    // previous only use one object param
    if (typeof msg == "object") {
        let message = msg.body || msg.message;
        msg.variant = msg.type || "default";
        window.utilApp.makeToast(message, msg);
    } else if (typeof msg == "string") {
        window.utilApp.makeToast(msg, opts);
    } else {
        showAlert({message: "addNotification 傳入參數有誤(請查看console)", type: "danger"});
        console.error(msg, opts);
    }
}

let showAlert = opts => {
    if (typeof opts == "string") {
        opts = {message: opts}
    }
    if (!isEmpty(opts.message)) {
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

        // singleton inside :D
        initAlertUI();
        window.alertApp.show(opts);
    }
}

let showModal = opts => {
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

    window.utilApp.modal(body, {
        title: title,
        size: size,
        html: true,
        callback: callback,
        noCloseOnBackdrop: !opts.backdrop_close
    });
}

let showConfirm = (message, callback) => {
    window.utilApp.confirm(message, {
        callback: callback
    });
}

let closeModal = callback => {
    window.utilApp.hideModal();
    if (typeof callback == "function") {
        setTimeout(callback, 500);
    }
}

let rand = (range) => Math.floor(Math.random() * Math.floor(range || 100));

let randRGB = (opacity = 1.0) => {
    return `rgba(${rand(255)}, ${rand(255)}, ${rand(255)}, ${opacity})`;
}

let addLDAnimation = (selector, which) => {
    let el = clearLDAnimation(selector);
    if (el) {
        el.addClass("ld");
        if (!which) {
            el.each(function (idx, el) {
                if (!$(el).is("body")) {
                    $(el).addClass(LOADING_PATTERNS[rand(LOADING_PATTERNS.length)]);
                }
            });
        } else {
            el.addClass(which);
        }
    }
    return el;
}

let clearLDAnimation = (selector) => {
    return $(selector || "*").removeClass("ld").attr('class', function(i, c){
        return c ? c.replace(/(^|\s+)ld-\S+/g, '') : "";
    });
}

let addAnimatedCSS = function(selector, opts) {
    const node = $(selector);
    if (node) {
        opts = Object.assign({
            name: ANIMATED_PATTERNS[rand(ANIMATED_PATTERNS.length)],
            duration: "once-anim-cfg"    // a css class to control speed
        }, opts);
        node.addClass(`animated ${opts.name} ${opts.duration}`);
        function handleAnimationEnd() {
            node.removeClass(`animated ${opts.name} ${opts.duration}`);
            node.off('animationend');
            // clear ld animation also
            clearLDAnimation(selector);
            if (typeof opts.callback === 'function') opts.callback.apply(this, arguments);
        }
        node.on('animationend', handleAnimationEnd);
    }
    return node;
}

let toggle = selector => {
    var el = $(selector);
    el.attr("disabled") ? el.attr("disabled", false) : el.attr("disabled", true);
    // also find cover container
    let container = el.closest("fieldset, .modal-content");
    if (container.length == 0) {
        if (el.is("button")) {
            toggleInsideSpinner(el);
        }
    } else {
        toggleCoverSpinner(container);
    }
}

let toggleCoverSpinner = (selector, style = "ld-over") => {
    // cover style opts: ld-over, ld-over-inverse, ld-over-full, ld-over-full-inverse
    let container = $(selector);
    if (container.length > 0) {
        if (container.hasClass(style)) {
            container.removeClass(style);
            container.find(".auto-add-spinner").remove();
            container.removeClass("running");
        } else {
            container.addClass(style);
            container.addClass("running");

            // randomize loading.io css for fun
            let cover_el = $(jQuery.parseHTML('<div class="ld auto-add-spinner"></div>'));
            cover_el.addClass(LOADING_PREDEFINED[rand(LOADING_PREDEFINED.length)])		// predefined pattern
                    .addClass(LOADING_SHAPES_COLOR[rand(LOADING_SHAPES_COLOR.length)]);	// color
            container.append(cover_el);
        }
    }
}

let toggleInsideSpinner = (selector, size = "sm") => {
    let el = $(selector);
    if (el.length > 0) {
        // add bootstrap spinner
        let spans = el.find(".spinner-border,.sr-only");
        if (spans.length > 0) {
            spans.remove();
        } else {
            spans = jQuery.parseHTML('<span class="spinner-border spinner-border-' + size + '" role="status" aria-hidden="true"></span><span class="sr-only">Loading...</span>&ensp;');
            el.prepend(spans);
        }
        /*
        // loading.io spinner, https://loading.io element
        // ex: <button class="ld-ext-left"><span class="ld ld-ring ld-cycle small"></span> 查詢</button>
        // position opts: ld-ext-top, ld-ext-bottom, ld-ext-left, ld-ext-right
        if (el.hasClass("ld-ext-left")) {
            el.removeClass("ld-ext-left");
            el.find(".auto-add-spinner").remove();
            el.removeClass("running");
        } else {
            el.addClass("ld-ext-left");
            el.prepend(jQuery.parseHTML('<span class="ld ld-ring ld-cycle small auto-add-spinner"></span>'));
            el.addClass("running");
        }
        */
    }
}

let scrollToElement = element => {
    var pos = $(element).offset().top - 120;
    if (pos < 0) return;
    $("html, body").animate({
        scrollTop: pos
    }, 1000);
}

let bindPressEnterEvent = (selector, callback_func) => {
    $(selector).on("keypress", function(e) {
        var keynum = (e.keyCode ? e.keyCode : e.which);
        if (keynum == '13') {
            callback_func.call(e.target, e);
        }
    });
}

let initDatepicker = () => {
    // for any field that needs date picking purpose (add .date_picker to its class)
    /**
     * <script src="assets/js/bootstrap-datepicker.min.js"></script>
         * <script src="assets/js/bootstrap-datepicker.zh-TW.min.js"></script>
     */
    if ($(".date_picker").datepicker) {
        $(".date_picker").datepicker({
            daysOfWeekDisabled: "",
            language: "zh-TW",
            daysOfWeekHighlighted: "1,2,3,4,5",
            //todayBtn: true,
            todayHighlight: true,
            autoclose: true,
            format: {
                /*
                * Say our UI should display a week ahead,
                * but textbox should store the actual date.
                * This is useful if we need UI to select local dates,
                * but store in UTC
                */
                toDisplay: (date, format, language) => toTWDate(new Date(date)),
                toValue: (date, format, language) => new Date()
            }
        });
    }
}

let initTooltip = () => {
    // tooltip enablement
    $('[data-toggle="tooltip"]').tooltip({
        delay: {
            show: 300,
            hide: 100
        }
    });
}

let initBlockquoteModal = () => {
    // add responsive and thumbnail style to blockquote img
    $("blockquote img").addClass("img-responsive img-thumbnail");
    // control blockquote block for *_quote_button
    $("button[id*='_quote_button']").on("click", function(e) {
        let el = $(e.target);
        let quote = el.next("blockquote"); // find DIRECT next element by selector
        // fallback to get the one under fieldset 
        if (quote.length == 0) {
            let fs = $(el.closest("fieldset"));
            quote = fs.find("blockquote");
        }
        if (quote.length > 0) {
            //quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
            showModal({
                title: quote.data("title") + " 小幫手提示",
                body: quote.html(),
                size: "lg"
            });
        }
    });
}

let initAlertUI = () => {
    // add alert element to show the alert message
    if (!window.alertApp) {
        $("body").append($.parseHTML(`<div id="bs_alert_template">
            <my-transition
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
            </my-transition>
        </div>`));
        // Try to use Vue.js
        window.alertApp = new Vue({
            el: '#bs_alert_template',
            components: { "my-transition": VueTransition },
            data: {
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
            },
            methods: {
                mouseOver: function(e) {
                    if (window.alertApp.hide_timer_handle !== null) { clearTimeout(window.alertApp.hide_timer_handle); }
                    window.alertApp.disableProgress();
                },
                mouseOut: function(e) {
                    if (window.alertApp.autohide) {
                        window.alertApp.hide_timer_handle = setTimeout(() => {
                            window.alertApp.seen = false;
                            window.alertApp.hide_timer_handle = null;
                        }, window.alertApp.delay);
                        window.alertApp.enableProgress();
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
    }
}

let initUtilApp = () => {
    if (window.utilApp) { return; }
    // bootstrap-vue will add $bvToast and $bvModal to every vue instance, I will leverage it to show toast and modal window
    window.utilApp = new Vue({
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
                    hideHeaderClose: true,
                    noCloseOnBackdrop: true,
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
            fetchRegCase: function(e, enabled_userinfo = false) {
                // ajax event binding
                let clicked_element = $(e.target);
                // remove additional characters for querying
                let id = trim(clicked_element.text());

                let body = new FormData();
                body.append("type", "reg_case");
                body.append("id", id);

                asyncFetch("query_json_api.php", {
                    method: "POST",
                    body: body
                }).then(jsonObj => {
                    this.showRegCase(jsonObj, enabled_userinfo);
                }).catch(ex => {
                    console.error("window.utilApp.fetchRegCase parsing failed", ex);
                    showAlert({
                        title: "擷取登記案件",
                        subtitle: id,
                        message: ex.toString(),
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
                                enabled_userinfo: enabled_userinfo
                            }
                        }),
                        title: "登記案件詳情",
                        size: enabled_userinfo ? "xl" : "lg"
                    });
                }
            }
        }
    });
}

let sleep = () => {
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
}

let wakeup = () => {
    let container = $("body");
    if (container.hasClass("ld-over-full-inverse")) {
        container.removeClass("ld-over-full-inverse");
        container.find("#screensaver").remove();
        container.removeClass("running");
    }
    clearLDAnimation(".navbar i.fas");
}

let initScreensaver = () => {
    /**
     * detect page idle and add animation for fun
     */
    window.onload = resetTimer;
    window.onmousemove = resetTimer;
    window.onmousedown = resetTimer;  // catches touchscreen presses as well      
    window.ontouchstart = resetTimer; // catches touchscreen swipes as well 
    window.onclick = resetTimer;      // catches touchpad clicks as well
    window.onkeypress = resetTimer;   
    window.addEventListener('scroll', resetTimer, true); // improved; see comments
    let idle_timer;
    function resetTimer() {
        clearTimeout(idle_timer);
        idle_timer = setTimeout(() => {
            sleep();
            //addLDAnimation("button, i.fas.text-light");
            // addLDAnimation("i.fas.text-light", "ld-bounce");
        }, 300000);  // 5mins
        // clearLDAnimation("button, i.fas.text-light");
        wakeup();
    }
}

$(document).ready(e => {
    initUtilApp();
    initBlockquoteModal();
    initTooltip();
    initDatepicker();
    initScreensaver();
    
    // hide footer after 10s
    setTimeout(() => addAnimatedCSS("#copyright", {
        name: "animated slideOutDown",
        callback: () => { $("#copyright").hide() }
    }), 10000);

    // add pulse effect for the nav-item
    $(".nav-item").on("mouseenter", function(e) { addAnimatedCSS(this, {name: "pulse"}); });
});
//]]>
    