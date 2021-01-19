//<![CDATA[
const CONFIG = {
    DISABLE_MSDB_QUERY: false,
    DEBUG_MODE: false,
    AP_SVR: "220.1.35.123",
    SCREENSAVER: false,
    SCREENSAVER_TIMER: 15 * 60 * 1000,
    PING: {
        BLACK: 49,
        DANGER: 29,
        WARNING: 14
    },
    API: {
        XLSX: {
            LANDING: "api/xlsx/landing.php"
        },
        JSON: {
            QUERY: "api/query_json_api.php",
            STATS: "api/stats_json_api.php",
            SWITCH: "api/switch_json_api.php",
            USER: "api/user_json_api.php",
            MSSQL: "api/mssql_json_api.php",
            LXHWEB: "api/lxhweb_json_api.php"
        },
        FILE: {
            LOAD: "api/load_file_api.php",
            EXPORT: "api/export_file_api.php",
            XLSX: "api/export_xlsx_api.php",
            TXT: "api/export_tmp_txt.php",
            CSV: "api/export_tmp_csv.php",
            DATA: "api/export_txt_data.php"
        },
        MOCK:{}
    },
    LAH_ROOT_EVENT: {
        MESSAGE_UNREAD: 'lah::message::unread'
    }
}
// the status code must be the same as server side response
const XHR_STATUS_CODE = {
    SUCCESS_WITH_NO_RECORD: 3,
    SUCCESS_WITH_MULTIPLE_RECORDS: 2,
    SUCCESS_NORMAL: 1,
    DEFAULT_FAIL: 0,
    UNSUPPORT_FAIL: -1,
    FAIL_WITH_LOCAL_NO_RECORD: -2,
    FAIL_NOT_VALID_SERVER: -3,
    FAIL_WITH_REMOTE_NO_RECORD: -4,
    FAIL_NO_AUTHORITY: -5,
    FAIL_JSON_ENCODE: -6,
    FAIL_NOT_FOUND: -7,
    FAIL_LOAD_ERROR: -8,
    FAIL_TIMEOUT: -9
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
    "fa fa-snowflake ld-swim",
    "ld-spinner ld-orbit",
    "ld-pie ld-flip",
    "fas fa-sync ld-spin",
    "fas fa-spinner fa-spin",
    "fas fa-radiation-alt ld-cycle",
    "fas fa-radiation ld-spin-fast",
    "fas fa-asterisk ld-spin",
    "fas fa-bolt ld-bounce",
    "fas fa-biking ld-move-ltr",
    "fas fa-snowboarding ld-rush-ltr",
    "fas fa-yin-yang fa-spin",
    "fas fa-biohazard ld-metronome",
    "fas fa-baseball-ball ld-bounce",
    "fas fa-basketball-ball ld-beat",
    "fas fa-stroopwafel ld-metronome",
    "fas fa-fan ld-spin-fast",
    "fas fa-cog ld-swing",
    "fas fa-compact-disc ld-spin-fast",
    "fas fa-crosshairs ld-swim",
    "far fa-compass ld-tick",
    "fas fa-compass fa-pulse",
    "fas fa-anchor ld-swing",
    "fas fa-fingerprint ld-damage",
    "fab fa-angellist ld-metronome"
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

let asyncFetch = async function(url, opts) {
    if (!window.vueApp) {
        console.error('vueApp is not ready!');
        return false;
    }
    return window.vueApp.fetch(url, opts);
}

let trim = text => {
    if (isEmpty(text)) {
        return "";
    }
    return text.toString().replace(/[^a-zA-Z0-9]/g, "");
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

let addUserInfoEvent = (classname = 'user_tag') => {
    $(`.${classname}`).off("click");
    $(`.${classname}`).on("click", vueApp.usercard);
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
        window.vueApp.makeToast(message, msg);
    } else if (typeof msg == "string") {
        window.vueApp.makeToast(msg, opts);
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
        let alert = window.vueApp.$refs.alert || window.dynaApp.$refs.alert;
        alert.show(opts);
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

    window.vueApp.modal(body, {
        title: title,
        size: size,
        html: true,
        callback: callback,
        noCloseOnBackdrop: !opts.backdrop_close
    });
}

let showConfirm = (message, callback) => {
    window.vueApp.confirm(message, {
        callback: callback
    });
}

let closeModal = callback => {
    window.vueApp.hideModal();
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
        // not under fieldset/modal popup
        if (el.length > 0) {
            // add bootstrap spinner
            let spans = el.find(".spinner-border,.sr-only");
            if (spans.length > 0) {
                spans.remove();
            } else {
                spans = jQuery.parseHTML('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span><span class="sr-only">Loading...</span>');
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
    } else {
        window.vueApp.toggleBusy({selector: container});
    }
}

let scrollToElement = element => {
    var pos = $(element).offset().top - 120;
    if (pos < 0) return;
    $("html, body").animate({
        scrollTop: pos
    }, 1000);
}

let refreshTomorrow8AM = (min_sec = '00:00') => {
    // refresh the page at tomorrow 08:00
    var now = new Date();
    var today = now.getFullYear() + "-" +
        ("0" + (now.getMonth() + 1)).slice(-2) + "-" +
        ("0" + now.getDate()).slice(-2);
    let tomorrow_8_xx_am = new Date(`${today} 08:${min_sec}`);
    tomorrow_8_xx_am.setDate(new Date().getDate()+1);
    let milliseconds = tomorrow_8_xx_am - now;
    setTimeout(() => {
      window.location.reload();
    }, milliseconds);
    console.log(`Refresh the page after ${Number.parseFloat(milliseconds / 1000 / 60 / 60).toFixed(2)} hrs. (${tomorrow_8_xx_am})`);
}

let resizeReload = () => {
    let global_resize_timer = null;
    $(window).resize(function() {
        clearTimeout(global_resize_timer);
        global_resize_timer = setTimeout(() => window.location.reload(), 1000);
    }); 
}

//]]>