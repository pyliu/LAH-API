//<![CDATA[
if (jQuery && localStorage) {
    // restore cached data from localStorage
    $(document).ready(function(e) {
        let cached_el_selector = "input[type='text'], select, textarea";
        for (let key in localStorage) {
            if (key == "length" || key == "key" || key == "getItem" || key == "setItem" || key == "removeItem" || key == "clear" || key == "" || key == undefined) {
                continue;
            }
            let el = $("#"+key);
            if (el.length > 0 && el.is(cached_el_selector)) {
                console.log(`Found #${key}! Set element value to ${localStorage[key]}`);
                el.val(localStorage[key]);
            } else {
                // remove non input type cached data
                localStorage.removeItem(key);
            }
        }

        // for cache purpose
        let cacheIt = function(el) {
            let this_text_input = $(el);
            let val = this_text_input.val();
            let ele_id = this_text_input.attr("id");
            if (val === undefined || $.trim(val) == "") {
                localStorage.removeItem(ele_id);
            } else {
                localStorage[ele_id] = val;
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
    });
} else {
    console.error("jQuery and localStorage are required to use cache.js!");
}
//]]>
