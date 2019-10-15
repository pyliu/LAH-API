//<![CDATA[
// 跨縣市主機
var landhb_svr = "220.1.35.123";

var trim = function(text) {
	if (isEmpty(text)) {
		return "";
	}
	return text.replace(/[^a-zA-Z0-9]/g, "");
}

var isEmpty = function(variable) {
	if (variable === undefined || $.trim(variable) == "") {
		return true;
	}
	
	if (typeof variable == "object" && variable.length == 0) {
		return true;
	}
	return false;
}

var addUserInfoEvent = function() {
	$(".user_tag").off("click");
	$(".user_tag").on("click", xhrQueryUserInfo);
}

var validateCaseInput = function(year_id_selector, code_id_selector, number_id_selector, output_id_selector) {
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

var showPopper = function(selector, content, timeout) {
	if (!isEmpty(content)) {
		$(selector).attr("data-content", content);
	}
	$(selector).popover('show');
	setTimeout(function() {
		$(selector).popover('hide');
	}, isEmpty(timeout) || isNaN(timeout) ? 2000 : timeout);
	scrollToElement(selector);
}

function showModal(body, title) {
	if (isEmpty(title)) {
		title = "案件詳情";
	}
    $("#ajax_modal .modal-title").html(title);
	$("#ajax_modal .modal-body p").html(body);
	$("#ajax_modal").modal();
}

function closeModal() {
	$("#ajax_modal").modal("hide");
}

var toggle = function(selector) {
	var el = $(selector);
	el.attr("disabled") ? el.attr("disabled", false) : el.attr("disabled", true);
}

var scrollToElement = function (element) {
	var pos = $(element).offset().top - 120;
	if (pos < 0) return;
	$("html, body").animate({
		scrollTop: pos
	}, 1000);
}

var setLoadingHTML = function(selector) {
	$(selector).html("<img src='assets/img/walking.gif' border='0' title='loading ...... ' width='25' height='25' />");
}

var bindPressEnterEvent = function(selector, callback_func) {
	$(selector).on("keypress", function(e) {
		var keynum = (e.keyCode ? e.keyCode : e.which);
		if (keynum == '13') {
		callback_func.call(e.target, e);
		}
	});
}

$(document).ready(function(e) {
	// add responsive and thumbnail style to blockquote img
	$("blockquote img").addClass("img-responsive img-thumbnail");
	// control blockquote block for *_quote_button
	$("button[id*='_quote_button']").on("click", function(e) {
		var quote = $(e.target).next("blockquote"); // find DIRECT next element by selector
		quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
	});
	
	// tooltip enablement
	$('[data-toggle="tooltip"]').tooltip({
		delay: {
			show: 300,
			hide: 100
		}
	});
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
				toDisplay: function (date, format, language) {
				var d = new Date(date);
				return (d.getFullYear() - 1911)
						+ ("0" + (d.getMonth()+1)).slice(-2)
						+ ("0" + d.getDate()).slice(-2);
				},
				toValue: function (date, format, language) {
				// initialize to now
				return new Date();
				}
			}
		});
	}
	// Enable watchdog
	if (xhrCallWatchDog) {
		// automatic check every 15 minutes
		window.pyliuChkTimer = setInterval(function(e) {
			let now = new Date();
			let weekday = now.getDay();
			if (weekday != 0 && weekday != 6) {
				let hour = now.getHours();
				if (hour > 7 && hour < 18) {
					xhrCallWatchDog(e);
				}
			}
		}, 900000);	// 1000 * 60 * 15
		console.log("Watchdog Enabled.");
	}
	// reload page after 8 hours
	setTimeout(function(e) {
		window.location.reload(true);
	}, 28800000);	// 1000 * 60 * 60 * 8
});
//]]>
