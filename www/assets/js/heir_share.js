function clearTextFields() {
    // find all input fields and clear their value
    $("input[type='text']").each(function(e) {
        $(this).val("");
    });
}

function clearAllRadioBoxes() {
    // find all radio boxes and clear their checked prop
    $("input[type='radio']").each(function(e) {
        $(this).prop("checked", false);
    });
}

function clearAllCheckBoxes() {
    // find all radio boxes and clear their checked prop
    $("input[type='checkbox']").each(function(e) {
        if ($(this).attr("name") == "is_luzhu_table") return;
        $(this).prop("checked", false);
    });
}

function clearBoxesByElement(element) {
    $(element).find("input[type='radio']").each(function(e) {
        if (!$(this).hasClass("skip")) {
            $(this).prop("checked", false);
        }
    });
    $(element).find("input[type='checkbox']").each(function(e) {
        if (!$(this).hasClass("skip")) {
            $(this).prop("checked", false);
        }
    });
}

function clearAll() {
    clearTextFields();
    clearAllRadioBoxes();
    clearAllCheckBoxes();
    hideAllFieldset();
    $("#NEXT_btn").addClass("hide");
}

function hideAllFieldset() {
    // hide all fieldset except .fix
    $("fieldset").each(function(e) {
        if (!$(this).hasClass("fix")) {
            deactivateElement(this);
        }
        $(this).removeClass("table-warning");
    });
}

function hideFieldsetsByElement(element) {
    $(element).find("fieldset").each(function(e) {
        if (!$(this).hasClass("fix")) {
            deactivateElement(this);
        }
    });
    // always hide result fieldset
    deactivateElement("#layer1_result");
}

function activateElement(element) {
    $(element).removeClass("hide");
    $(".table-warning").each(function(e) {
        $(this).removeClass("table-warning");
    });
    $(element).addClass("table-warning");
}

function deactivateElement(element) {
    $(element).addClass("hide");
    $(element).removeClass("table-warning");
}

function scrollToElement(element) {
    var pos = $(element).offset().top - 100;
    if (pos < 0) return;
    $("html, body").animate({
        scrollTop: pos
    }, 1000);
}

function disableAllButtons(flag) {
    $("button").each(function() {
        var ele = $(this);
        ele.prop("disabled", flag);
    });
}

function isEmpty(variable) {
    if (variable === undefined || $.trim(variable) == "") {
        return true;
    }
    return false;
}

function showPopper(selector) {
    $(selector).popover('show');
    setTimeout(function() {
        $(selector).popover('hide');
    }, 2000);
    scrollToElement(selector);
}

// IE detection
function getBrowserType() { 
    var userAgent = navigator.userAgent; // 取得瀏覽器的userAgent字符串 
    var isOpera = userAgent.indexOf("Opera") > -1; // 判斷是否Opera瀏覽器 
    // var isIE = userAgent.indexOf("compatible") > -1 && userAgent.indexOf("MSIE") > -1 && !isOpera; //判斷是否IE瀏覽器 
    var isIE=window.ActiveXObject || "ActiveXObject" in window
    // var isEdge = userAgent.indexOf("Windows NT 6.1; Trident/7.0;") > -1 && !isIE; // 判斷是否IE的Edge瀏覽器 
    var isEdge = userAgent.indexOf("Edge") > -1; // 判斷是否IE的Edge瀏覽器
    var isFF = userAgent.indexOf("Firefox") > -1; // 判斷是否Firefox瀏覽器 
    var isSafari = userAgent.indexOf("Safari") > -1 && userAgent.indexOf("Chrome") == -1; // 判斷是否Safari瀏覽器 
    var isChrome = userAgent.indexOf("Chrome") > -1 && userAgent.indexOf("Safari") > -1&&!isEdge; // 判斷Chrome瀏覽器 
    
    if (isIE) { 
        var reIE = new RegExp("MSIE (\\d+\\.\\d+);"); 
        reIE.test(userAgent); 
        var fIEVersion = parseFloat(RegExp["$1"]); 
        if(userAgent.indexOf('MSIE 6.0')!=-1) {
            return "IE6";
        } else if (fIEVersion == 7) {
            return "IE7";
        } else if (fIEVersion == 8) {
            return "IE8";
        } else if (fIEVersion == 9) {
            return "IE9";
        } else if (fIEVersion == 10) {
            return "IE10";
        } else if (userAgent.toLowerCase().match(/rv:([\d.]+)\) like gecko/)) { 
            return "IE11";
        } else {
            // IE version is too low.
            return "0";
        }
    }
        
    if (isFF) { return "FF"; } 
    if (isOpera) { return "Opera"; } 
    if (isSafari) { return "Safari"; } 
    if (isChrome) { return "Chrome"; } 
    if (isEdge) { return "Edge"; } 
}

// other custom scripts start here
$(document).ready(() => {
    let wizard_steps = {
        s1: "步驟一，選擇事實發生區間"
    };
    window.vueApp = new Vue({
        el: "#app",
        data: {
            factBound: "2",
            step: wizard_steps.s1
        }
    });
});
