//<![CDATA[
const CONFIG = {
	DISABLE_MSDB_QUERY: false,
	TEST_MODE: false
}
// 跨縣市主機
const landhb_svr = "220.1.35.123";
// the status code must be the same as server side response
const XHR_STATUS_CODE = {
	SUCCESS_NORMAL: 1,
    SUCCESS_WITH_MULTIPLE_RECORDS: 2,
	DEFAULT_FAIL: 0,
	UNSUPPORT_FAIL: -1,
    FAIL_WITH_LOCAL_NO_RECORD: -2,
	FAIL_NOT_VALID_SERVER: -3,
	FAIL_WITH_REMOTE_NO_RECORD: -4
}

let trim = text => {
	if (isEmpty(text)) {
		return "";
	}
	return text.replace(/[^a-zA-Z0-9]/g, "");
}

let isEmpty = variable => {
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

let checkCaseUIData = (data) => {
	let year = data.year;
	let code = data.code;
	let num = data.num;
	if (isEmpty(year)) {
		showAlert({
			message: "請重新選擇「年」欄位!",
			type: "danger"
		});
		return false;
	}
	if (isEmpty(code)) {
		showAlert({
			message: "請重新選擇「字」欄位!",
			type: "danger"
		});
		return false;
	}
	let number = num.replace(/\D/g, "");
	let offset = 6 - number.length;
	if (isEmpty(number) || isNaN(number) || offset < 0) {
		showAlert({
			message: `「號」格式有問題，請查明修正【${num}】`,
			type: "danger"
		});
		return false;
	}
	return true;
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

let addNotification = opts => {
	let tmp = document.createElement("div");
	if (typeof opts == "object") {
		let msg = opts.body || opts.message;
		opts.variant = opts.type || "default";
		tmp.innerHTML = msg;
		window.utilApp.makeToast(tmp.textContent || tmp.innerText || "無法轉譯訊息文字", opts);
	} else {
		tmp.innerHTML = opts;
		window.utilApp.makeToast(tmp.textContent || tmp.innerText || "無法轉譯訊息文字");
	}
}

let showAlert = opts => {
	let msg = opts.message;
	if (!isEmpty(msg)) {
		let type = "alert-warning";
		switch (opts.type) {
			case "danger":
			case "red":
				type = "alert-danger";
				break;
			case "warning":
			case "yellow":
				type = "alert-warning";
				break;
			case "success":
			case "green":
				type = "alert-success";
				break;
			default:
				type = "alert-info";
				break;
		}
		
		// singleton inside :D
		initAlertUI();
		
		// normal usage, you want to attach event to the element in the alert window
		if (typeof opts.callback == "function") {
			setTimeout(opts.callback, 250);
		}

		window.alertApp.autohide = opts.autohide || true;
		window.alertApp.message = msg;
		window.alertApp.type = type;
		window.alertApp.seen = true;
	}
}

let showModal = opts => {
	let body = opts.body;
	let title = opts.title;
	let size = opts.size;	// sm, md, lg, xl
	let callback = opts.callback;
	if (isEmpty(title)) {
		title = "... 請輸入指定標題 ...";
	}
	if (isEmpty(body)) {
		body = "... 請輸入指定內文 ...";
	}
	if (isEmpty(size)) {
		size = "md";
	}
	window.utilApp.modal(body, {
		title: title,
		size: size,
		html: true,
		callback: callback
	});
}

let closeModal = callback => {
	window.utilApp.hideModal();
	if (typeof callback == "function") {
		setTimeout(callback, 100);
	}
}

let toggle = selector => {
	var el = $(selector);
	el.attr("disabled") ? el.attr("disabled", false) : el.attr("disabled", true);
	// also find cover container
	let container = el.closest("fieldset");
	if (container.length == 0) {
		if (el.is("button")) {
			toggleInsideSpinner(el);
		}
	} else {
		toggleCoverSpinner(container);
	}
}

let toggleCoverSpinner = (selector, style = "ld-over-inverse") => {
	// cover style opts: ld-over, ld-over-inverse, ld-over-full, ld-over-full-inverse
	let container = $(selector);
	if (container.length > 0) {
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

let toggleInsideSpinner = (selector, size = "sm") => {
	let el = $(selector);
	if (el.length > 0) {
		// add bootstrap spinner
		let spans = el.find(".spinner-border,.sr-only");
		if (spans.length > 0) {
			spans.remove();
		} else {
			spans = jQuery.parseHTML('<span class="spinner-border spinner-border-' + size + '" role="status" aria-hidden="true"></span><span class="sr-only">Loading...</span>');
			el.prepend(spans);
		}
		/*
		// loading.io spinner, https://loading.io element
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
		*/
	}
}

let scrollToElement = element => {
	var pos = $(element).offset().top - 120;
	if (pos < 0) return;
	$("html, body").animate({
		scrollTop: pos
	}, 1000);
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

let initBlockquoteModal = () => {
	// add responsive and thumbnail style to blockquote img
	$("blockquote img").addClass("img-responsive img-thumbnail");
	// control blockquote block for *_quote_button
	$("button[id*='_quote_button']").on("click", function(e) {
		let el = $(e.target);
		let quote = el.next("blockquote"); // find DIRECT next element by selector
		// fallback to get the one under fieldset 
		if (quote.length == 0) {
			let fs = $(el.closest("fieldset"));
			quote = fs.find("blockquote");
		}
		if (quote.length > 0) {
			//quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
			showModal({
				title: quote.data("title") + " 小幫手提示",
				body: quote.html(),
				size: "lg"
			});
		}
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

let initAlertUI = () => {
	// add alert element to show the alert message
	if (!window.alertApp) {
		$("body").append($.parseHTML(`<div id="bs_alert_template">
			<transition
				name="bounce"
				enter-active-class="animated bounceInDown"
				leave-active-class="animated bounceOutUp"
				@enter="enter"
				@leave="leave"
				@after-enter="afterEnter"
				@after-leave="afterLeave"
			>
				<div v-show="seen" class="alert alert-dismissible alert-fixed shadow" :class="type" role="alert" @mouseover="mouseOver" @mouseout="mouseOut">
					<p v-html="message" style="font-size: .9rem"></p>
					<button type="button" class="close" @click="seen = false">
						<span aria-hidden="true">&times;</span>
					</button>
					<div class="progress mt-1" style="height:.2rem">
						<div class="progress-bar bg-light" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
					</div>
				</div>
			</transition>
		</div>`));
		// Try to use Vue.js
		window.alertApp = new Vue({
			el: '#bs_alert_template',
			data: {
				message: 'Hello Alert Vue!',
				type: 'alert-warning',
				seen: false,
				hide_timer_handle: null,
				progress_timer_handle: null,
				progress_counter: 1,
				autohide: true,
				delay: 15000
			},
			methods: {
				mouseOver: function(e) {
					if (window.alertApp.hide_timer_handle !== null) { clearTimeout(window.alertApp.hide_timer_handle); }
					window.alertApp.disableProgress();
				},
				mouseOut: function(e) {
					if (window.alertApp.autohide) {
						window.alertApp.hide_timer_handle = setTimeout(() => {
							window.alertApp.seen = false;
							window.alertApp.hide_timer_handle = null;
						}, window.alertApp.delay);
						window.alertApp.enableProgress();
					}
				},
				enableProgress: function() {
					this.disableProgress();
					//console.log("enableProgress!");
					let that = this;
					this.progress_timer_handle = setInterval(function() {
						let p = (100 - Math.round(((++that.progress_counter) / (that.delay / 200.0)) * 100));
						let wp = p < 0 ? "0%" : `${p}%`;
						$("#bs_alert_template .progress .progress-bar").css("width", wp);
					}, 200);
				},
				disableProgress: function() {
					//console.log("disableProgress!");
					clearTimeout(this.progress_timer_handle);
					$("#bs_alert_template .progress .progress-bar").css("width", "100%");
					this.progress_counter = 1;
				},
				enter: function() { },
				leave: function() { },
				afterEnter: function() {
					// close alert after 15 secs (default)
					if (this.autohide) {
						let that = this;
						if (this.hide_timer_handle !== null) { clearTimeout(this.hide_timer_handle); }
						this.hide_timer_handle = setTimeout(() => {
							that.seen = false;
							that.hide_timer_handle = null;
						}, this.delay);
						this.enableProgress();
					}
				},
				afterLeave: function() {
					this.disableProgress();
				}
			}
		});
	}
}

let initUtilApp = () => {
	if (window.utilApp) { return; }
	// bootstrap-vue will add $bvToast and $bvModal to every vue instance, I will leverage it to show toast and modal window
	window.utilApp = new Vue({
		data: {
			toastCounter: 0,
			confirmAnswer: false
		},
		methods: {
			// make simple, short popup notice message
			makeToast: function(message, opts = {}) {
				// for sub-title
				var currentdate = new Date();
				var datetime = ("0" + currentdate.getHours()).slice(-2) + ":" +
					("0" + currentdate.getMinutes()).slice(-2) + ":" +
					("0" + currentdate.getSeconds()).slice(-2);
				let merged = Object.assign({
					title: "通知",
					subtitle: datetime,
					href: "",
					noAutoHide: false,
					autoHideDelay: 5000,
					solid: true,
					toaster: "b-toaster-bottom-right",
					appendToast: true,
					variant: "default",
					successSpinner: false
				}, opts);
				// Use a shorter name for this.$createElement
				const h = this.$createElement
				// Create the title
				let vNodesTitle = h(
				  'div',
				  { class: ['d-flex', 'flex-grow-1', 'align-items-baseline', 'mr-2'] },
				  [
					h('strong', { class: 'mr-2' }, merged.title),
					h('small', { class: 'ml-auto text-italics' }, merged.subtitle)
				  ]
				);
				// Pass the VNodes as an array for title
				merged.title = [vNodesTitle];

				if (merged.successSpinner) {
					// Create the message
					const vNodesMsg = h(
						'p',
						{ class: ['mb-0'] },
						[
							h('b-spinner', { props: { type: 'grow', small: true, variant: 'success' } }),
							//h('strong', {}, 'toast'),
							message
						]
					)
					this.$bvToast.toast([vNodesMsg], merged);
				} else {
					this.$bvToast.toast(message, merged);
				}

				this.toastCounter++;
			},
			showModal: function(id) {
				$(`#${id}`).slideDown(function() {
					this.$bvModal.show(id);
				});
			},
			hideModal: function(id = "") {
				let that = this;
				if (id == "" || id == undefined) {
					$('div.modal.show').each(function(idx, el) {
						$(el).hide(400, function() {
							that.$bvModal.hide(el.id);
						});
					});
				} else {
					$(`#${id}`).hide(400, function() {
						that.$bvModal.hide(id);
					});
				}
			},
			modal: function(message, opts) {
				let merged = Object.assign({
					title: '訊息',
					size: 'md',
					buttonSize: 'sm',
					okVariant: 'outline-secondary',
					okTitle: '關閉',
					hideHeaderClose: false,
					centered: true,
                	scrollable: true,
                	hideFooter: true,
                	noCloseOnBackdrop: true,
					contentClass: "shadow hide", // add hide class to .modal-content then use slideDown to show up
					html: false
				}, opts);
				// use d-none to hide footer
				merged.footerClass = merged.hideFooter ? "d-none" : "p-2";
				if (merged.html) {
					merged.titleHtml = merged.title;
					merged.title = undefined;
					if (typeof message == "array") {
						// assume the message is VNode array
						this.$bvModal.msgBoxOk(message, merged);
					} else {
						const h = this.$createElement;
						const msgVNode = h('div', { domProps: { innerHTML: message } });
						this.$bvModal.msgBoxOk([msgVNode], merged);
					}
					// to initialize Vue component purpose
					if (merged.callback && typeof merged.callback == "function") {
						setTimeout(merged.callback, 50);
					}
				} else {
					this.$bvModal.msgBoxOk(message, merged);
				}
				// since the modal fade effect broken ... use jQuery instead
				setTimeout(() => $('div.modal.show .modal-content').slideDown(400), 50);
				
			},
			confirm: function(message, opts) {
				this.confirmAnswer = false;
				let merged = Object.assign({
					title: '請確認',
					size: 'sm',
					buttonSize: 'sm',
					okVariant: 'outline-success',
					okTitle: '確定',
					cancelVariant: 'secondary',
					cancelTitle: '取消',
					footerClass: 'p-2',
					hideHeaderClose: true,
					noCloseOnBackdrop: true,
					centered: true
				}, opts);
				this.$bvModal.msgBoxConfirm(message, merged)
				.then(value => {
					this.confirmAnswer = value;
					if (this.confirmAnswer && merged.callback && typeof merged.callback == "function") {
						merged.callback();
					}
				}).catch(err => {
					console.error(err);
				});
			}
		},
		mounted: function(e) {
			this.$root.$on('bv::modal::show', (bvEvent, modalId) => {
				console.log('Modal is about to be shown', bvEvent, modalId)
			});
		}
	});
}

$(document).ready(e => {
	// Block IE
	if (detectIE()) {
		document.getElementsByTagName("body")[0].innerHTML = '<h2 style="margin-top: 50px; text-align: center;" class="text-danger">請使用Chrome/Firefox瀏覽器</h2>';
	}
	initBlockquoteModal();
	initTooltip();
	initDatepicker();
	initWatchdog();
	initUtilApp();
});
//]]>
