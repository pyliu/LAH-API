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

// other custom scripts start here
$(document).ready(() => {
    let wizard_steps = {
        s1: "步驟一，選擇事實發生區間"
    };
    window.vueApp = new Vue({
        el: "#app",
        data: {
            factBound: "2",
            step: wizard_steps.s1,
            VueOK: true
        }
    });
    $("#VueOK").show();
});
