//<![CDATA[
let showPrcCaseDetail = (jsonObj) => {
	if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
		showAlert({
			message: "查無地價案件資料",
			type: "warning"
		});
		return;
	} else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
		throw new Error("查詢失敗：" + jsonObj.message);
	}
	let html = "<p>" + jsonObj.html + "</p>";
	let modal_size = "lg";
	showModal({
		body: html,
		title: "地價案件詳情",
		size: modal_size,
		callback: () => { $(".prc_case_serial").off("click").on("click", window.utilApp.fetchRegCase); }
	});
}

let xhrGetSectionRALIDCount = e => {
	let el = $(e.target);
	toggle(el);
	let text = $("#data_query_text").val();
	let xhr = $.ajax({
		url: CONFIG.JSON_API_EP,
		data: "type=ralid&text="+text,
		method: "POST",
		dataType: "json",
		success: jsonObj => {
			toggle(el);
			let count = jsonObj.data_count;
			let html = "";
			for (let i=0; i<count; i++) {
				if (isNaN(jsonObj.raw[i]["段代碼"])) {
					continue;
				}
				let this_count = parseInt(jsonObj.raw[i]["土地標示部筆數"]);
				this_count = this_count < 1000 ? 1000 : this_count;
				let blow = jsonObj.raw[i]["土地標示部筆數"].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
				let size = 0, size_o = 0;
				if (jsonObj.raw[i]["面積"]) {
					size = jsonObj.raw[i]["面積"].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
					size_o = (jsonObj.raw[i]["面積"] * 3025 / 10000).toFixed(2);
					size_o = size_o.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
				}
				html += "【<span class='text-info'>" + jsonObj.raw[i]["段代碼"]  + "</span>】" + jsonObj.raw[i]["段名稱"] + "：土地標示部 <span class='text-primary'>" + blow + "</span> 筆【面積：" + size + " &#x33A1; | " + size_o + " 坪】 <br />";
			}
			$("#data_query_result").html(html);
		},
		error: obj => {
			toggle(el);
		}
	});
}

/*
	行政課來文查詢某統編是否有申請案件 ....
*/
let xhrGetCasesByID = e => {
	let el = $(e.target);
	toggle(".id_query_grp");
	let text = $("#id_query_text").val();

	let finish_count = 0;

	toggleInsideSpinner("#id_query_crsms_result");
	toggleInsideSpinner("#id_query_cmsms_result");

	let xhr_crsms = $.ajax({
		url: CONFIG.JSON_API_EP,
		data: "type=crsms&id="+text,
		method: "POST",
		dataType: "json",
		success: jsonObj => {
			let count = jsonObj.data_count;
			if (count == 0) {
				$("#id_query_crsms_result").html("本所登記案件資料庫查無統編「"+text+"」收件資料。");
			} else {
				let html = "<p>登記案件：";
				for (let i=0; i<count; i++) {
					html += "<div class='reg_case_id'>" + jsonObj.raw[i]["RM01"] + "-" + jsonObj.raw[i]["RM02"]  + "-" + jsonObj.raw[i]["RM03"] + "</div>";
				}
				html += "</p>";
				$("#id_query_crsms_result").html(html);
				// make click case id tr can bring up the detail dialog 【use reg_case_id css class as identifier to bind event】
				addAnimatedCSS(".reg_case_id", {
					name: "flash"
				}).off("click").on("click", window.utilApp.fetchRegCase);
				$(".reg_case_id").attr("title", "點我取得更多資訊！");
			}
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		},
		error: obj => {
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		}
	});
	let xhr_cmsms = $.ajax({
		url: CONFIG.JSON_API_EP,
		data: "type=cmsms&id="+text,
		method: "POST",
		dataType: "json",
		success: jsonObj => {
			let count = jsonObj.data_count;
			if (count == 0) {
				$("#id_query_cmsms_result").html("本所測量案件資料庫查無統編「"+text+"」收件資料。");
			} else {
				let html = "<p>測量案件：";
				for (let i=0; i<count; i++) {
					html += "<div>" + jsonObj.raw[i]["MM01"] + "-" + jsonObj.raw[i]["MM02"]  + "-" + jsonObj.raw[i]["MM03"] + "</div>";
				}
				html += "</p>";
				$("#id_query_cmsms_result").html(html);
			}
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		},
		error: obj => {
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		}
	});
}

let xhrLoadSQL = e => {
	let val = $("#preload_sql_select").val();

	if (isEmpty(val)) {
		$("#sql_csv_text").val("");
		return;
	}

	toggle(e.target);

	let body = new FormData();
	body.append("type", "load_select_sql");
	body.append("file_name", val);
	fetch("load_file_api.php", {
		method: 'POST',
			body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			$("#sql_csv_text").val(jsonObj.data);
			toggle(e.target);
		} else {
			throw new Error("讀取異常，jsonObj.status非為1");
		}
	}).catch(ex => {
		console.error("xhrLoadSQL parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
};

let xhrExportSQLCsv = e => {
	let body = new FormData();
	body.append("type", "file_sql_csv");
	xhrExportSQLReport(e, body);
};

let xhrExportSQLTxt = e => {
	let body = new FormData();
	body.append("type", "file_sql_txt");
	xhrExportSQLReport(e, body);
};

let xhrExportSQLReport = (e, form_body) => {
	let text = $("#preload_sql_select option:selected").text();
	form_body.append("sql", $("#sql_csv_text").val());
	toggle(e.target);
	fetch("export_file_api.php", {
		method: 'POST',
		body: form_body
	}).then(response => {
		return response.blob();
	}).then(blob => {
		let d = new Date();
		let url = window.URL.createObjectURL(blob);
		let a = document.createElement('a');
		a.href = url;
		a.download = text + (form_body.get("type") == "file_sql_txt" ? ".txt" : ".csv");
		document.body.appendChild(a); // we need to append the element to the dom -> otherwise it will not work in firefox
		a.click();    
		a.remove();  //afterwards we remove the element again
		// release object in memory
		window.URL.revokeObjectURL(url);
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrExportSQLReport parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
};

let xhrExportLog = e => {
	let date = $("#log_date_text").val();
	let form_body = new FormData();
	form_body.append("type", "file_log");
	form_body.append("date", date);
	toggle(e.target);
	fetch("export_file_api.php", {
		method: 'POST',
		body: form_body
	}).then(response => {
		return response.blob();
	}).then(blob => {
		let d = new Date();
		let url = window.URL.createObjectURL(blob);
		let a = document.createElement('a');
		a.href = url;
		a.download = date + ".log";
		document.body.appendChild(a); // we need to append the element to the dom -> otherwise it will not work in firefox
		a.click();    
		a.remove();  //afterwards we remove the element again
		// release object in memory
		window.URL.revokeObjectURL(url);
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrExportLog parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
};

let xhrZipLog = e => {
	let form_body = new FormData();
	form_body.append("type", "zip_log");
	toggle(e.target);
	fetch(CONFIG.JSON_API_EP, {
		method: 'POST',
		body: form_body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + jsonObj.message + "】");
		addNotification({
			message: "<strong class='text-success'>壓縮完成</strong>"
		});
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrZipLog parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
}

let xhrUpdateRegCaseCol = function(arguments) {
	if ($(arguments.el).length > 0) {
		// remove the button
		$(arguments.el).remove();
	}
	let body = new FormData();
	body.append("type", "reg_upd_col");
	body.append("rm01", arguments.rm01);
	body.append("rm02", arguments.rm02);
	body.append("rm03", arguments.rm03);
	body.append("col", arguments.col);
	body.append("val", arguments.val);
	fetch(CONFIG.JSON_API_EP, {
		method: "POST",
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `更新案件「${arguments.col}」欄位回傳狀態碼有問題【${jsonObj.status}】`);
		addNotification({message: `「${arguments.col}」更新完成`, variant: "success"});
	}).catch(ex => {
		console.error("xhrUpdateRegCaseCol parsing failed", ex);
		showAlert({
			message: `<strong class='text-danger'>更新欄位「${arguments.col}」失敗</strong><p>${arguments.rm01}, ${arguments.rm02}, ${arguments.rm03}, ${arguments.val}</p>`,
			type: "danger"
		});
	});
}

let xhrSearchUsers = e => {
	if (CONFIG.DISABLE_MSDB_QUERY) {
		console.warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping xhrSearchUsers.");
		return;
	}
	let keyword = $.trim($("#msg_who").val().replace(/\?/g, ""));
	if (isEmpty(keyword)) {
		console.warn("Keyword field should not be empty.");
		return;
	}
	
	let form_body = new FormData();
	form_body.append("type", "search_user");
	form_body.append("keyword", keyword);

	if (showUserInfoFromCache(keyword, keyword)) {
		return;
	}
	
	fetch(CONFIG.JSON_API_EP, {
		method: 'POST',
		body: form_body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			showUserInfoByRAW(jsonObj.raw[jsonObj.data_count - 1]);
		} else {
			showAlert({
				message: jsonObj.message,
				type: "warning"
			});
			console.warn(jsonObj.message);
		}
	}).catch(ex => {
		console.error("xhrSearchUsers parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
}

let showUserInfoFromCache = (id, name, el_selector = undefined) => {
	// reduce user query traffic
	if (localStorage) {
		let json_str = localStorage[id] || localStorage[name];
		if (!isEmpty(json_str)) {
			console.log(`cache hit ${id}:${name}, user info from localStorage.`);
			let jsonObj = JSON.parse(json_str);
			let latest = jsonObj.data_count - 1;
			showUserInfoByRAW(jsonObj.raw[latest], el_selector);
			return true;
		}
	}
	return false;
}

let showUserInfoByRAW = (tdoc_raw, selector = undefined) => {
	let year = 31536000000;
	let now = new Date();
	let age = "";
	let birth = tdoc_raw["AP_BIRTH"];
	let birth_regex = /^\d{3}\/\d{2}\/\d{2}$/;
	if (birth.match(birth_regex)) {
		birth = (parseInt(birth.substring(0, 3)) + 1911) + birth.substring(3);
		let temp = Date.parse(birth);
		if (temp) {
			let born = new Date(temp);
			let badge_age = ((now - born) / year).toFixed(1);
			if (badge_age < 30) {
				age += " <b-badge variant='success' pill>";
			} else if (badge_age < 40) {
				age += " <b-badge variant='primary' pill>";
			} else if (badge_age < 50) {
				age += " <b-badge variant='warning' pill>";
			} else if (badge_age < 60) {
				age += " <b-badge variant='danger' pill>";
			} else {
				age += " <b-badge variant='dark' pill>";
			}
			age += badge_age + "歲</b-badge>"
		}
	}

	let on_board_date = "";
	if(!isEmpty(tdoc_raw["AP_ON_DATE"])) {
		on_board_date = tdoc_raw["AP_ON_DATE"].date ? tdoc_raw["AP_ON_DATE"].date.split(" ")[0] :　tdoc_raw["AP_ON_DATE"];
		let temp = Date.parse(on_board_date.replace('/-/g', "/"));
		if (temp) {
			let on = new Date(temp);
			if (tdoc_raw["AP_OFF_JOB"] == "Y") {
				let off_board_date = tdoc_raw["AP_OFF_DATE"];
				off_board_date = (parseInt(off_board_date.substring(0, 3)) + 1911) + off_board_date.substring(3);
				temp = Date.parse(off_board_date.replace('/-/g', "/"));
				if (temp) {
					// replace now Date to off board date
					now = new Date(temp);
				}
			}
			let work_age = ((now - on) / year).toFixed(1);
			if (work_age < 5) {
				on_board_date += " <b-badge variant='success'>";
			} else if (work_age < 10) {
				on_board_date += " <b-badge variant='primary'>";
			} else if (work_age < 20) {
				on_board_date += " <b-badge variant='warning'>";
			} else {
				on_board_date += " <b-badge variant='danger'>";
			}
			on_board_date +=  work_age + "年</b-badge>";
		}
	}
	let vue_card_text = tdoc_raw["AP_OFF_JOB"] == "N" ? "" : "<p class='text-danger'>已離職【" + tdoc_raw["AP_OFF_DATE"] + "】</p>";
	vue_card_text += "ID：" + tdoc_raw["DocUserID"] + "<br />"
		+ "電腦：" + tdoc_raw["AP_PCIP"] + "<br />"
		+ "生日：" + tdoc_raw["AP_BIRTH"] + age + "<br />"
		+ "單位：" + tdoc_raw["AP_UNIT_NAME"] + "<br />"
		+ "工作：" + tdoc_raw["AP_WORK"] + "<br />"
		+ "學歷：" + tdoc_raw["AP_HI_SCHOOL"] + "<br />"
		+ "考試：" + tdoc_raw["AP_TEST"] + "<br />"
		+ "手機：" + tdoc_raw["AP_SEL"] + "<br />"
		+ "到職：" + on_board_date + "<br />"
		;
	let vue_html = `
		<div id="user_info_app">
			<b-card class="overflow-hidden bg-light" style="max-width: 540px; font-size: 0.9rem;" title="${tdoc_raw["AP_USER_NAME"]}" sub-title="${tdoc_raw["AP_JOB"]}">
				<b-link href="get_pho_img.php?name=${tdoc_raw["AP_USER_NAME"]}" target="_blank">
					<b-card-img
						src="get_pho_img.php?name=${tdoc_raw["AP_USER_NAME"]}"
						alt="${tdoc_raw["AP_USER_NAME"]}"
						class="img-thumbnail float-right ml-2"
						style="max-width: 220px"
					></b-card-img>
				</b-link>
				<b-card-text>${vue_card_text}</b-card-text>
			</b-card>
		</div>
	`;

	if ($(selector).length > 0) {
		$(selector).html(vue_html);
		new Vue({
			el: "#user_info_app",
			components: [ "b-card", "b-link", "b-badge" ]
		});
		addAnimatedCSS(selector, { name: "pulse", duration: "once-anim-cfg" });
	} else {
		showModal({
			title: "使用者資訊",
			body: vue_html,
			size: "md",
			callback: () => {
				new Vue({
					el: "#user_info_app",
					components: [ "b-card", "b-link", "b-badge" ]
				});
			}
		});
	}
}

let xhrQueryUserInfo = e => {
	if (CONFIG.DISABLE_MSDB_QUERY) {
		console.warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping xhrQueryUSerInfo.");
		return;
	}

	let clicked_element = $(e.target);
	if (!clicked_element.hasClass("user_tag")) {
		clicked_element = $(clicked_element.closest(".user_tag"));
	}

	let name = $.trim(clicked_element.data("name"));
	if (name) {
		name = name.replace(/[\?A-Za-z0-9\+]/g, "");
	}
	let id = trim(clicked_element.data("id"));
	// use data-el HTML attribute to specify the display container, empty will use the modal popup window instead.
	let el_selector = clicked_element.data("display-selector");

	if (isEmpty(name) && isEmpty(id)) {
		console.warn("Require query params are all empty, skip xhr querying. (add attr to the element => data-id=" + id + ", data-name=" + name + ")");
		return;
	}

	// reduce user query traffic
	if (showUserInfoFromCache(id, name, el_selector)) {
		return;
	}
	
	let form_body = new FormData();
	form_body.append("type", "user_info");
	form_body.append("name", name);
	form_body.append("id", id);

	asyncFetch(CONFIG.JSON_API_EP, {
		method: 'POST',
		body: form_body
	}).then(jsonObj => {
		if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			let latest = jsonObj.data_count - 1;
			showUserInfoByRAW(jsonObj.raw[latest], el_selector);
			// cache to local storage
			if (localStorage) {
				let json_str = JSON.stringify(jsonObj);
				if (!isEmpty(id)) { localStorage[id] = json_str; }
				if (!isEmpty(name)) { localStorage[name] = json_str; }
			}
		} else {
			addNotification({ message: `找不到 ${name} ${id} 資料`, type: "warning" });
		}
	}).catch(ex => {
		console.error("xhrQueryUserInfo parsing failed", ex);
		showAlert({ title: "查詢使用者資訊", message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger" });
	});
}

let xhrSendMessage = e => {
	if (CONFIG.DISABLE_MSDB_QUERY) {
		console.warn("CONFIG.DISABLE_MSDB_QUERY is true, skipping xhrSendMessage.");
		return;
	}
	let title = $("#msg_title").val();
	let content = $("#msg_content").val().replace(/\n/g, "\r\n");	// Messenger client is Windows app, so I need to replace \n to \r\n
	let who = $("#msg_who").val();

	if (!confirm("確認要送 「" + title + "」 給 「" + who + "」？\n\n" + content)) {
		return false;
	}

	if(isEmpty(title) || isEmpty(content) || isEmpty(who)) {
		console.warn("Require query params are empty, skip xhr querying. (" + title + ", " + content + ")");
		showAlert({
			message: "<span class='text-danger'>標題或是內容為空白。</span>",
			type: "warning"
		});
		return;
	}

	if (content.length > 1000) {
		console.warn("Content should not exceed 1000 chars, skip xhr querying. (" + content.length + ")");
		showAlert({
			message: "<span class='text-danger'>內容不能超過1000個字元。</span><p>" + content + "</p>",
			type: "warning"
		});
		return;
	}

	let clicked_element = $(e.target);
	toggle(clicked_element);

	let form_body = new FormData();
	form_body.append("type", "send_message");
	form_body.append("title", title);
	form_body.append("content", content);
	form_body.append("who", who);


	asyncFetch(CONFIG.JSON_API_EP, {
		body: form_body
	}).then(jsonObj => {
		console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "回傳之json object status異常【" + jsonObj.message + "】");
		addNotification({
			title: "傳送訊息",
			message: jsonObj.message
		});
		toggle(clicked_element);
	}).catch(ex => {
		console.error("xhrSendMessage parsing failed", ex);
		showAlert({ title: "傳送訊息", message: `XHR連線查詢有問題!!【${ex}】`, type: "danger" });
	});
}

let xhrTest = () => {
	let form_body = new FormData();
	form_body.append("type", "reg_stats");
	form_body.append("year_month", "10812");

	fetch(CONFIG.JSON_API_EP, {
		method: 'POST',
		body: form_body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		console.log(jsonObj);
	}).catch(ex => {
		console.error("xhrTest parsing failed", ex);
		showAlert({ title: "測試XHR連線", message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
	});
}
//]]>
