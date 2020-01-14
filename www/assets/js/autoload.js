//<![CDATA[
var loading_timeout_handle;
var refresh_time = 60000;
var now = new Date();
var today = (now.getFullYear() - 1911) + "-" +
    ("0" + (now.getMonth() + 1)).slice(-2) + "-" +
    ("0" + now.getDate()).slice(-2);
if (!landhb_svr) {
    landhb_svr = "220.1.35.123";
}
// IE ... disable cache globally
$.ajaxSetup({ cache: false });
	
function startRefresh() {
    var target = $("#table_container");
    if (target) {
        //toggleCoverSpinner(target, "ld-over-full-inverse");
        // prevent extra tooltip element occupied
        $(".tooltip").remove();
        toggleInsideSpinner("#current_time", "sm");
        target.load("allcases.php", "date=" + $("#date_input").val(), function() {
            adjustQueryTime();
            adjustTableContent();
            //toggleCoverSpinner(target, "ld-over-full-inverse");
            // only today that needs to update regularly
            if (today == $("#date_input").val()) {
                let hour = new Date().getHours();
                // only refresh within work hour
				if (hour > 7 && hour < 18) {
                    clearTimeout(loading_timeout_handle);
                    loading_timeout_handle = setTimeout(startRefresh, refresh_time);
                } else {
                    console.warn("Not office hour, stop refresh.");
                }
            } else {
                console.warn("Query date is not " + today + ", no auto table refresh enabled.");
            }
        });
    }
}

function adjustQueryTime() {
    var currentdate = new Date();
    var datetime = currentdate.getFullYear() + "-" +
        ("0" + (currentdate.getMonth() + 1)).slice(-2) + "-" +
        ("0" + currentdate.getDate()).slice(-2) + " " +
        ("0" + currentdate.getHours()).slice(-2) + ":" +
        ("0" + currentdate.getMinutes()).slice(-2) + ":" +
        ("0" + currentdate.getSeconds()).slice(-2);
    $("#current_time").html(datetime);
    if (addNotification) {
        addNotification({
            body: `更新時間：${datetime}`
        });
    }
}

function adjustTableContent() {
    //console.log("case table loaded, going to attach UI events.");
    // show/hide rows by filter condition
    $("#case_results tbody tr").hide();
    var state = $("#table_container").data("active");
    if (state == "red") {
        $("#case_results tbody tr.bg-danger").show();
    } else if (state == "yellow") {
        $("#case_results tbody tr.bg-warning").show();
    } else if (state == "green") {
        $("#case_results tbody tr.bg-success").show();
    } else if (state == "info") {
        $("#case_results tbody tr").show();
        $("#case_results tbody tr.bg-success").hide();
        $("#case_results tbody tr.bg-warning").hide();
        $("#case_results tbody tr.bg-danger").hide();
    } else {
        $("#case_results tbody tr").show();
    }
    // enable bootstrap tooltip for operator field
    //console.log($('[data-toggle="tooltip"]'));
    $('[data-toggle="tooltip"]').tooltip({
        delay: {
            show: 300,
            hide: 100
        }
    });
    // make table sortable
    makeAllSortable();
    // case xhr event
    $(".case.ajax").off("click").on("click", function(e) {
        var clicked_element = $(e.target).closest("td");
        $(".focused-element").removeClass("focused-element");
        clicked_element.addClass("focused-element");
        scrollToElement(clicked_element);
        xhrRegQueryCaseDialog(e);
    });
    // user info dialog event
    console.assert(addUserInfoEvent, "Can't find addUserInfoEvent function ... do you include global.js?")
    addUserInfoEvent();
}

$(document).ready(startRefresh);
//]]>
