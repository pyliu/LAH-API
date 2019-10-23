//<![CDATA[
// 跨縣市主機
const landhb_svr = "220.1.35.123";

let trim = (text) => {
	if (isEmpty(text)) {
		return "";
	}
	return text.replace(/[^a-zA-Z0-9]/g, "");
}

let isEmpty = (variable) => {
	if (variable === undefined || $.trim(variable) == "") {
		return true;
	}
	
	if (typeof variable == "object" && variable.length == 0) {
		return true;
	}
	return false;
}

let addUserInfoEvent = () => {
	$(".user_tag").off("click");
	$(".user_tag").on("click", xhrQueryUserInfo);
}

let validateCaseInput = (year_id_selector, code_id_selector, number_id_selector, output_id_selector) => {
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

let showModal = (opts) => {
	let body = opts.body;
	let title = opts.title;
	let size = opts.size;	// sm, md, lg
	if (isEmpty(title)) {
		title = "... 請輸入指定標題 ...";
	}
	if (isEmpty(body)) {
		body = "... 請輸入指定內文 ...";
	}
	if (isEmpty(size)) {
		size = "md";
	}
	
	let modal_element = $("#bs_modal_template");
	
	// Try to use Vue.js
	window.modalApp.title = title;
	window.modalApp.body = body;
	window.modalApp.sizeClass = "modal-" + size;
	window.modalApp.optsClass = isEmpty(opts.class) ? "" : opts.class;

	modal_element.modal();
}

let closeModal = () => {
	$("#bs_modal_template").modal("hide");
}

let toggle = (selector) => {
	var el = $(selector);
	el.attr("disabled") ? el.attr("disabled", false) : el.attr("disabled", true);
	// also find cover container (https://loading.io)
	let container = el.closest("fieldset");
	if (container.length == 0) {
		// add bootstrap spinner
		if (el.is("button")) {
			let spans = el.find(".spinner-border,.sr-only");
			if (spans.length > 0) {
				spans.remove();
			} else {
				spans = jQuery.parseHTML('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span class="sr-only">Loading...</span>');
				el.prepend(spans);
			}
		}
		// for loading spinner, https://loading.io element
		/*if (el.is("button")) {
			// ex: <button class="ld-ext-left"><span class="ld ld-ring ld-cycle small"></span>查詢</button>
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
		}*/
	} else {
		// cover style opts: ld-over, ld-over-inverse, ld-over-full, ld-over-full-inverse
		let style = "ld-over-inverse";
		if (container.hasClass(style)) {
			container.removeClass(style);
			container.find(".auto-add-spinner").remove();
			container.removeClass("running");
		} else {
			container.addClass(style);
			container.addClass("running");
			// <!-- ld-ring + ld-spin, ld-pie + ld-heartbeat, ld-ball + ld-bounce, ld-square + ld-blur -->
			container.append(jQuery.parseHTML('<div class="ld ld-ball ld-bounce auto-add-spinner"></div>'));
		}
	}
}

let scrollToElement = (element) => {
	var pos = $(element).offset().top - 120;
	if (pos < 0) return;
	$("html, body").animate({
		scrollTop: pos
	}, 1000);
}

let setLoadingHTML = (selector) => {
	$(selector).html('<span class="spinner-border spinner-border-md" role="status" aria-hidden="true"></span><span class="sr-only">Loading...</span>');
}

let bindPressEnterEvent = (selector, callback_func) => {
	$(selector).on("keypress", function(e) {
		var keynum = (e.keyCode ? e.keyCode : e.which);
		if (keynum == '13') {
			callback_func.call(e.target, e);
		}
	});
}
/**
 * detect IE
 * returns version of IE or false, if browser is not Internet Explorer
 */
let detectIE = () => {
	var ua = window.navigator.userAgent;

	// Test values; Uncomment to check result …
	// IE 10
	// ua = 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)';
	// IE 11
	// ua = 'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko';
	// Edge 12 (Spartan)
	// ua = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36 Edge/12.0';
	// Edge 13
	// ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2486.0 Safari/537.36 Edge/13.10586';

	var msie = ua.indexOf('MSIE ');
	if (msie > 0) {
		// IE 10 or older => return version number
		return parseInt(ua.substring(msie + 5, ua.indexOf('.', msie)), 10);
	}

	var trident = ua.indexOf('Trident/');
	if (trident > 0) {
		// IE 11 => return version number
		var rv = ua.indexOf('rv:');
		return parseInt(ua.substring(rv + 3, ua.indexOf('.', rv)), 10);
	}

	var edge = ua.indexOf('Edge/');
	if (edge > 0) {
		// Edge (IE 12+) => return version number
		return parseInt(ua.substring(edge + 5, ua.indexOf('.', edge)), 10);
	}

	// other browser
	return false;
}

let initDatepicker = () => {
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
}

let initTooltip = () => {
	// tooltip enablement
	$('[data-toggle="tooltip"]').tooltip({
		delay: {
			show: 300,
			hide: 100
		}
	});
}

let initBlockquoteToggle = () => {
	// add responsive and thumbnail style to blockquote img
	$("blockquote img").addClass("img-responsive img-thumbnail");
	// control blockquote block for *_quote_button
	$("button[id*='_quote_button']").on("click", function(e) {
		var quote = $(e.target).next("blockquote"); // find DIRECT next element by selector
		quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
	});
}

let initWatchdog = () => {
	if (xhrCallWatchDog) {
		// automatic check every 15 minutes
		window.pyliuChkTimer = setInterval(function(e) {
			let now = new Date();
			let weekday = now.getDay();
			if (weekday != 0 && weekday != 6) {
				let hour = now.getHours();
				if (hour > 8 && hour < 17) {
					xhrCallWatchDog(e);
				}
			}
		}, 900000);	// 1000 * 60 * 15
		// reload page after 8 hours
		setTimeout(function(e) {
			window.location.reload(true);
		}, 28800000);	// 1000 * 60 * 60 * 8
	} else {
		console.warn("Watchdog disabled. (xhrCallWatchDog not defined)");
	}
}

let initModalUI = () => {
	// add modal element to show the popup html message
	let modal_element = $("#bs_modal_template");
	if (!modal_element.length) {
		modal_element = $(jQuery.parseHTML('<div class="modal fade" id="bs_modal_template" role="dialog"><div class="modal-dialog" v-bind:class="[sizeClass, optsClass]"><div class="modal-content"><div class="modal-header"><h4 class="modal-title"><span v-html="title"></span></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><p><span v-html="body"></span></p></div><!-- <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">關閉</button></div> --></div></div></div>'));
		$("body").append(modal_element);
		// Try to use Vue.js
		window.modalApp = new Vue({
			el: '#bs_modal_template',
			data: {
				body: 'Hello Vue!',
				title: 'Hello Vue!',
				sizeClass: 'modal-md',
				optsClass: ''
			}
		});
	}
}

$(document).ready(function(e) {
	// Block IE
	if (detectIE()) {
		document.getElementsByTagName("body")[0].innerHTML = '<h2 style="margin-top: 50px; text-align: center;" class="text-danger">請使用Chrome/Firefox瀏覽器</h2>';
	}

	initBlockquoteToggle();
	initTooltip();
	initDatepicker();
	initWatchdog();
	initModalUI();
});
//]]>
