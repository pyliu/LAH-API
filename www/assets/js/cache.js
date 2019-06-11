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
    });
} else {
    alert("jQuery and localStorage are required to use cache.js!");
}
//]]>
