//<![CDATA[
if (jQuery && localStorage) {
    // restore cached data from localStorage
    $(document).ready(function(e) {
        for (var key in localStorage) {
            if (key == "length" || key == "key" || key == "getItem" || key == "setItem" || key == "removeItem" || key == "clear") {
                continue;
            }
            /*
            var regex = /\_select$/;
            if (key.match(regex)) {
                console.log("restoring #"+key, "selected value to " + localStorage[key]);
                $("#"+key).val(localStorage[key]);
            } else {*/
                console.log("restoring #"+key, "element value to " + localStorage[key]);
                $("#"+key).val(localStorage[key]);
            //}
        }

        // for cache purpose
        var cacheIt = function(el) {
            var this_text_input = $(el);
            var val = this_text_input.val();
            var ele_id = this_text_input.attr("id");
            if (val === undefined || $.trim(val) == "") {
                localStorage.removeItem(ele_id);
            } else {
                localStorage[ele_id] = val;
            }
        }
        window.pyliuCacheTimer = setInterval(function(e) {
            $("input[type='text'], select, textarea").each(function(index, el) {
                if (!$(el).hasClass("no-cache")) {
                    cacheIt(el);
                }
            });
        }, 10000);
        $("input[type='text'], select, textarea").on("blur", function(e) {
            if (!$(e.target).hasClass("no-cache")) {
                cacheIt(e.target);
            }
        });
    });
} else {
    alert("jQuery and localStorage are required to use cache.js!");
}
//]]>
