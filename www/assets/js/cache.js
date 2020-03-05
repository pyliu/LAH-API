//<![CDATA[
if (jQuery && localforage) {
    // restore cached data from localStorage
    $(document).ready(function(e) {
        setTimeout(function() {
            let cached_el_selector = "input[type='text'], input[type='number'], select, textarea";
            localforage.iterate(function(value, key, iterationNumber) {
                // Resulting key/value pair -- this callback
                // will be executed for every item in the
                // database.
                let el = $("#"+key);
                if (el.length > 0 && el.is(cached_el_selector)) {
                   el.val(value);
                }
            }).then(function() {
                //console.log('Iteration has completed');
            }).catch(function(err) {
                // This code runs if there were any errors
                console.error(err);
            });

            // for cache purpose
            let cacheIt = function(el) {
                let this_text_input = $(el);
                let val = this_text_input.val();
                let ele_id = this_text_input.attr("id");
                if (val === undefined || $.trim(val) == "") {
                    localforage.removeItem(ele_id).then(function() {
                        // Run this code once the key has been removed.
                    }).catch(function(err) {
                        // This code runs if there were any errors
                        console.error(err);
                    });
                } else if (ele_id != undefined) {
                    localforage.setItem(ele_id, val);
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
        }, 100);
    });
} else {
    console.error("jQuery and localforage are required to use cache.js!");
}
//]]>
