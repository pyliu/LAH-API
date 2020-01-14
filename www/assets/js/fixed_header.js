var scroll_handle = null;
var header_el = $("#header_container");
var float_ths = [
    $("#float_th1"),
    $("#float_th2"),
    $("#float_th3"),
    $("#float_th4"),
    $("#float_th5"),
    $("#float_th6"),
    $("#float_th7"),
    $("#float_th8"),
    $("#float_th9"),
    $("#float_th10"),
    $("#float_th11"),
    $("#float_th12")
];
if (header_el) {
    $(window).scroll(function(e) {
        clearTimeout(scroll_handle);
        scroll_handle = setTimeout(function() {
            if ($(window).scrollTop() > 120) {
                if (header_el.hasClass("hide")) {
                    header_el.removeClass("hide");
                    // adjust column width
                    var fixed_ths = [
                        $("#fixed_th1"),
                        $("#fixed_th2"),
                        $("#fixed_th3"),
                        $("#fixed_th4"),
                        $("#fixed_th5"),
                        $("#fixed_th6"),
                        $("#fixed_th7"),
                        $("#fixed_th8"),
                        $("#fixed_th9"),
                        $("#fixed_th10"),
                        $("#fixed_th11"),
                        $("#fixed_th12")
                    ];
                    fixed_ths.forEach(function(el, index) {
                        //console.log(el.attr("id") + ":" + index);
                        if (el) {
                            float_ths[index].width(el.width());
                        }
                    });
                    header_el.addClass("opacity");
                }
            } else {
                if (!header_el.hasClass("hide")) {
                    header_el.addClass("hide");
                    header_el.removeClass("opacity");
                }
            }
        }, 100);
    });
}
