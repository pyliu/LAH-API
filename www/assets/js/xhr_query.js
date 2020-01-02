//<![CDATA[

let xhrGetCaseLatestNum = function(e) {
	let code_select = $("#"+this.code_id);
	let code_val = code_select.val();
	if (isEmpty(code_val)) {
		return;
	}
	
	let year = $("#"+this.year_id).val().replace(/\D/g, "");
	let code = trim(code_val);

	let body = new FormData();
	body.append("type", "max");
	body.append("year", year);
	body.append("code", code);
	
	let number = $("#"+this.number_id);

	toggle(code_select);
	toggle(number);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			addNotification({
				message: "目前 " + code_val + " 最新案件號為 " + jsonObj.max
			});
			number.val(jsonObj.max);
		} else if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
			showAlert({message: jsonObj.message, type: "danger"});
		} else {
			showAlert({message: code_val, type: "danger"});
		}
		toggle(code_select);
		toggle(number);
	}).catch(ex => {
		console.error("xhrGetCaseLatestNum parsing failed", ex);
		showAlert({message: "查詢最大號碼失敗~【" + code_val + "】", type: "danger"});
	});
}

let showRegCaseDetail = (jsonObj) => {
	let html = "<p>" + jsonObj.tr_html + "</p>";
	if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
		showAlert({message: jsonObj.message, type: "danger"});
		return;
	} else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
		throw new Error("查詢失敗：" + jsonObj.message);
	} else {
		let area = "其他(" + jsonObj.資料管轄所 + "區)";
		let rm10 = jsonObj.raw.RM10 ? jsonObj.raw.RM10 : "XX";
		switch (rm10) {
			case "03":
				area = "中壢區";
				break;
			case "12":
				area = "觀音區";
				break;
			default:
				break;
		}

		html += "<div class='row'>";
		html += "<div class='col-4'>";

		html += (jsonObj.跨所 == "Y" ? "<span class='bg-info text-white rounded p-1'>跨所案件 (" + jsonObj.資料收件所 + " => " + jsonObj.資料管轄所 + ")</span><br />" : "");
		
		// http://220.1.35.34:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=108&word=HB04&code=005001&sdlyn=N&RM90=
		html += "收件字號：" + "<a title='案件辦理情形 on " + landhb_svr + "' href='#' onclick='javascript:window.open(\"http://\"\+landhb_svr\+\":9080/LandHB/CAS/CCD02/CCD0202.jsp?year="+ jsonObj.raw["RM01"] +"&word="+ jsonObj.raw["RM02"] +"&code="+ jsonObj.raw["RM03"] +"&sdlyn=N&RM90=\")'>" + jsonObj.收件字號 + "</a> <br />";
		
		// options for switching server
		//html += "<label for='cross_svr'><input type='radio' id='cross_svr' name='svr_opts' value='220.1.35.123' onclick='javascript:landhb_svr=\"220.1.35.123\"' /> 跨縣市主機</label> <br />";

		html += isEmpty(jsonObj.結案已否) ? "<div class='text-danger'><strong>尚未結案！</strong></div>" : "";

		html += "收件時間：" + jsonObj.收件時間 + "<br/>";
		html += "限辦期限：" + jsonObj.限辦期限 + "<br/>";
		html += "作業人員：<span id='the_incase_operator_span' class='user_tag' data-display-selector='#in_modal_display' data-id='" + jsonObj.作業人員ID + "' data-name='" + jsonObj.作業人員 + "'>" + jsonObj.作業人員 + "</span><br/>";
		html += "辦理情形：" + jsonObj.辦理情形 + "<br/>";
		html += "登記原因：" + jsonObj.登記原因 + "<br/>";
		html += "區域：" + area + "【" + jsonObj.raw.RM10 + "】<br/>";
		html += "段小段：" + jsonObj.段小段 + "【" + jsonObj.段代碼 + "】<br/>";
		html += "地號：" + jsonObj.地號 + "<br/>";
		html += "建號：" + jsonObj.建號 + "<br/>";
		html += "件數：" + jsonObj.件數 + "<br/>";
		html += "登記處理註記：" + jsonObj.登記處理註記 + "<br/>";
		html += "地價處理註記：" + jsonObj.地價處理註記 + "<br/>";
		html += "權利人統編：" + jsonObj.權利人統編 + "<br/>";
		html += "權利人姓名：" + jsonObj.權利人姓名 + "<br/>";
		html += "義務人統編：" + jsonObj.義務人統編 + "<br/>";
		html += "義務人姓名：" + jsonObj.義務人姓名 + "<br/>";
		html += "義務人人數：" + jsonObj.義務人人數 + "<br/>";
		html += "代理人統編：" + jsonObj.代理人統編 + "<br/>";
		html += "代理人姓名：" + jsonObj.代理人姓名 + "<br/>";
		html += "手機號碼：" + jsonObj.手機號碼;

		html += "</div>";
		html += "<div id='in_modal_display' class='col-8'></div>";
		html += "</div>";
	}
	
	showModal({
		body: html,
		title: "登記案件詳情",
		size: "lg",
		callback: () => {
			addUserInfoEvent();
			// load current operator user info
			$("#the_incase_operator_span").trigger("click");
		}
	});
}

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
		callback: () => { $(".prc_case_serial").off("click").on("click", xhrRegQueryCaseDialog); }
	});
}

let xhrRegQueryCaseDialog = e => {
	// ajax event binding
	let clicked_element = $(e.target);
	// remove additional characters for querying
	let id = trim(clicked_element.text());

	let body = new FormData();
	body.append("type", "reg_case");
	body.append("id", id);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		showRegCaseDetail(jsonObj);
	}).catch(ex => {
		console.error("xhrRegQueryCaseDialog parsing failed", ex);
	});
}

let xhrRegQueryCase = e => {
	if (!validateCaseInput("#query_year", "#query_code", "#query_num", "#query_display")) {
		return false;
	}
	let year = $("#query_year").val().replace(/\D/g, "");
	let code = $("#query_code").val();
	let number = $("#query_num").val().replace(/\D/g, "");
	// prepare post params
	let id = trim(year + code + number);
	let body = new FormData();
	body.append("type", "reg_case");
	body.append("id", id);
	
	toggle(e.target);

	fetch("query_json_api.php", {
		method: "POST",
		//headers: { "Content-Type": "application/json" },
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		showRegCaseDetail(jsonObj);
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrRegQueryCase parsing failed", ex);
		showAlert({message: "無法取得 " + id + " 資訊!【" + ex + "】", type: "danger"});
	});
}

let xhrPrcQueryCase = e => {
	if (!validateCaseInput("#query_year", "#query_code", "#query_num", "#query_display")) {
		return false;
	}
	let year = $("#query_year").val().replace(/\D/g, "");
	let code = $("#query_code").val();
	let number = $("#query_num").val().replace(/\D/g, "");
	// prepare post params
	let id = trim(year + code + number);
	let body = new FormData();
	body.append("type", "prc_case");
	body.append("id", id);
	
	toggle(e.target);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		showPrcCaseDetail(jsonObj);
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrPrcQueryCase parsing failed", ex);
		showAlert({message: "無法取得 " + id + " 資訊!【" + ex + "】", type: "danger"});
	});
}

let xhrCallWatchDog = e => {
	let body = new FormData();
	body.append("type", "watchdog");
	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		// normal success jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL
		if (jsonObj.status != XHR_STATUS_CODE.SUCCESS_NORMAL) {
			console.error(jsonObj.message);
			// stop interval timer
			clearTimeout(window.pyliuChkTimer);
			console.info("停止全域WATCHDOG定時器。");
		}
	}).catch(ex => {
		console.error("xhrCallWatchDog parsing failed", ex);
	});
}

let xhrGetSectionRALIDCount = e => {
	let el = $(e.target);
	toggle(el);
	let text = $("#data_query_text").val();
	let xhr = $.ajax({
		url: "query_json_api.php",
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
		url: "query_json_api.php",
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
				$(".reg_case_id").off("click").on("click", xhrRegQueryCaseDialog);
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
		url: "query_json_api.php",
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

let xhrGetExpacItems = function(e) {
	let number = $("#expac_query_number").val().replace(/\D/g, "");
	// only allow number
	if (isEmpty(number) || isNaN(number)) {
		showPopper("#expac_query_number");
		return;
	}

	// should be the query button (#expac_query_button)
	toggle("#expac_query_button");
	// clean display area before querying
	$("#expac_query_display").html("");

	let body = new FormData();
	body.append("type", "expac");
	body.append("year", $("#expac_query_year").val());
	body.append("num", number);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
			showAlert({message: "找不到規費收費項目資料！【年度：" + $("#expac_query_year").val() + ", 電腦給號：" + number + "】", type: "warning"});
		} else {
			let html = "<div><strong class='text-danger'>找到下列資料：</strong></div>";
			for (let i = 0; i < jsonObj.data_count; i++) {
				html += "<div class='expac_item'>"
					 + "<a href='javascript:void(0)' class='reg_case_id'>" + jsonObj.raw[i]["AC16"] + "-" + jsonObj.raw[i]["AC17"] + "-" + jsonObj.raw[i]["AC18"] + "</a>"
					 + " 規費年度: " + jsonObj.raw[i]["AC25"]
					 + ", 電腦給號: " + jsonObj.raw[i]["AC04"]
					 + ", 實收金額: " + jsonObj.raw[i]["AC30"]
					 + `<div class='form-row form-inline'>
					 		<div class='input-group input-group-sm col'>
						 		<select class='form-control' id='modify_expac_item_${i}'>${getExpacItemOptions(jsonObj.raw[i]["AC20"])}</select>
							</div>
							<div class='filter-btn-group col'>
								<button class='btn btn-sm btn-outline-primary' id='modify_expac_item_${i}_btn' onclick='xhrModifyExpacItem("${jsonObj.raw[i]["AC25"]}", "${jsonObj.raw[i]["AC04"]}", "${jsonObj.raw[i]["AC20"]}", "${jsonObj.raw[i]["AC30"]}", "modify_expac_item_${i}")'>修改</button>
								<span id='modify_expac_item_${i}_msg'></span>
							</div>
						</div>`;
				html += "</div>";
			}
			$("#expac_query_display").html(html);
			$(".reg_case_id").off("click").on("click", xhrRegQueryCaseDialog);
		}
		toggle("#expac_query_button");
	}).catch(ex => {
		console.error("xhrGetExpacItems parsing failed", ex);
		showAlert({message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger"});
	});
}

let getExpacItemOptions = selected_ac20 => {
	return  "<option " + (selected_ac20 == "01" ? "selected" : "") + " value='01'>【01】土地法65條登記費</option>"
			+ "<option " + (selected_ac20 == "02" ? "selected" : "") + " value='02'>【02】土地法76條登記費</option>"
			+ "<option " + (selected_ac20 == "03" ? "selected" : "") + " value='03'>【03】土地法67條書狀費</option>"
			+ "<option " + (selected_ac20 == "04" ? "selected" : "") + " value='04'>【04】地籍謄本工本費</option>"
			+ "<option " + (selected_ac20 == "06" ? "selected" : "") + " value='06'>【06】檔案閱覽抄錄複製費</option>"
			+ "<option " + (selected_ac20 == "07" ? "selected" : "") + " value='07'>【07】閱覽費</option>"
			+ "<option " + (selected_ac20 == "08" ? "selected" : "") + " value='08'>【08】門牌查詢費</option>"
			+ "<option " + (selected_ac20 == "09" ? "selected" : "") + " value='09'>【09】複丈費及建物測量費</option>"
			+ "<option " + (selected_ac20 == "10" ? "selected" : "") + " value='10'>【10】地目變更勘查費</option>"
			+ "<option " + (selected_ac20 == "14" ? "selected" : "") + " value='14'>【14】電子謄本列印</option>"
			+ "<option " + (selected_ac20 == "18" ? "selected" : "") + " value='18'>【18】塑膠樁土地界標</option>"
			+ "<option " + (selected_ac20 == "19" ? "selected" : "") + " value='19'>【19】鋼釘土地界標(大)</option>"
			+ "<option " + (selected_ac20 == "30" ? "selected" : "") + " value='30'>【30】104年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "31" ? "selected" : "") + " value='31'>【31】100年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "32" ? "selected" : "") + " value='32'>【32】101年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "33" ? "selected" : "") + " value='33'>【33】102年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "34" ? "selected" : "") + " value='34'>【34】103年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "35" ? "selected" : "") + " value='35'>【35】其他</option>"
			+ "<option " + (selected_ac20 == "36" ? "selected" : "") + " value='36'>【36】鋼釘土地界標(小)</option>"
			+ "<option " + (selected_ac20 == "37" ? "selected" : "") + " value='37'>【37】105年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "38" ? "selected" : "") + " value='38'>【38】106年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "39" ? "selected" : "") + " value='39'>【39】塑膠樁土地界標(大)</option>"
			+ "<option " + (selected_ac20 == "40" ? "selected" : "") + " value='40'>【40】107年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "41" ? "selected" : "") + " value='41'>【41】108年度登記罰鍰</option>"
			+ "<option " + (selected_ac20 == "42" ? "selected" : "") + " value='42'>【42】土地法第76條登記費(跨縣市)</option>"
			+ "<option " + (selected_ac20 == "43" ? "selected" : "") + " value='43'>【43】書狀費（跨縣市）</option>"
			+ "<option " + (selected_ac20 == "44" ? "selected" : "") + " value='44'>【44】罰鍰（跨縣市）</option>"
			+ "<option " + (selected_ac20 == "45" ? "selected" : "") + " value='45'>【45】109年度登記罰鍰</option>";
}

let xhrModifyExpacItem = (year_ac25, num_ac04, now_code_ac20, amount_ac30, select_id) => {
	let this_select = $("#" + select_id);
	if (this_select && this_select.val() != now_code_ac20) {
		let body = new FormData();
		body.append("type", "mod_expac");
		body.append("year", year_ac25);
		body.append("num", num_ac04);
		body.append("code", this_select.val());
		body.append("amount", amount_ac30);

		$("#" + select_id + "_btn").remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(jsonObj => {
			if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
				$("#" + select_id + "_msg").html("<span class='text-success'>修改完成!</span>");
			} else {
				$("#" + select_id + "_msg").html("<span class='text-danger'>修改失敗!</span>");
			}
		}).catch(ex => {
			console.error("xhrGetExpacItems parsing failed", ex);
			$("#" + select_id + "_msg").html("<span class='text-danger'>" + ex + "</span>");
		});
	}
}

let xhrGetExpaaData = function(e) {
	// basic checking for tw date input
	let regex = /^\d{7}$/;
	let txt = $("#expaa_query_date").val();
	if (txt.match(regex) == null) {
		showPopper("#expaa_query_date");
		return;
	}
	
	let number = $.trim($("#expaa_query_number").val().replace(/\D/g, ""));
	if (!isEmpty(number)) {
		// basic checking for number input
		if (isNaN(number)) {
			showPopper("#expaa_query_number");
			return;
		}
	}

	toggle("[id*=expaa_query_]");

	let body = new FormData();
	body.append("type", "expaa");
	body.append("qday", txt);
	body.append("num", number);
	body.append("list_mode", $(e.target).attr("id") == "expaa_query_date_button");
	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		// only has one record
		if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			let html = "<div class='text-info'>規費資料：</div>";
			html += "<ul>";
			for (let key in jsonObj.raw) {
				html += "<li>";
				html += key + "：";
				if (key == "列印註記") {
					html += "<div class='form-row form-inline'>"
						  + "<div class='input-group input-group-sm col-3'>"
						  + "<select id='exapp_print_select' class='form-control'>"
						  + "<option value='0'" + (jsonObj.raw[key] == 0 ? "selected" : "") + ">【0】未印</option>"
						  + "<option value='1'" + (jsonObj.raw[key] == 1 ? "selected" : "") + ">【1】已印</option>"
						  + "</select> "
						  + "</div>"
						  + `<div class='filter-btn-group col'>
						  		<button id='exapp_print_button' class='btn btn-sm btn-outline-primary'>修改</button>
								<span id='exapp_print_status'></span>
							</div>`
						  + "</div>";
				} else if (key == "繳費方式代碼") {
					html += "<div class='form-row form-inline'>"
						  + "<div class='input-group input-group-sm col-3'>"
						  + "<select id='exapp_method_select' class='form-control'>"
						  + getExpaaAA100Options(jsonObj.raw[key])
						  + "</select> "
						  + "</div>"
						  + `<div class='filter-btn-group col'>
								  <button id='exapp_method_button' class='btn btn-sm btn-outline-primary'>修改</button>
								  <span id='exapp_method_status'></span>
							</div>`
						  + "</div>";
				} else if (key == "悠遊卡繳費扣款結果") {
					html += jsonObj.raw[key];
					//  無作廢原因才可進行修正
					if (isEmpty(jsonObj.raw["作廢原因"]) && jsonObj.raw[key] != 1) {
						html += "&ensp;<button class='btn btn-sm btn-outline-danger' id='fix_exapp_easycard_payment_btn" + "' onclick='xhrFixEasycardPayment(\"" + jsonObj.raw["開單日期"] + "\", \"" + jsonObj.raw["電腦給號"] + "\", \"" + jsonObj.raw["實收總金額"] + "\", \"fix_exapp_easycard_payment_btn" + "\")'>修正為扣款成功</button>";
					}
				} else {
					// others just show info
					html += jsonObj.raw[key];
				}
				html += "</li>";
			};
			html += "</ul>";
			$("#expaa_query_display").html(html);
			// attach event handler for the buttons
			$("#exapp_print_button").off("click").on("click", xhrUpdateExpaaAA09.bind({
				date: $("#expaa_query_date").val(),
				number: $("#expaa_query_number").val(),
				select_id: "exapp_print_select"
			}));
			$("#exapp_method_button").off("click").on("click", xhrUpdateExpaaAA100.bind({
				date: $("#expaa_query_date").val(),
				number: $("#expaa_query_number").val(),
				select_id: "exapp_method_select"
			}));
		} else if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_WITH_MULTIPLE_RECORDS) {
			// has many records
			let html = "<div>" 
					+ "<span class='block-secondary'>現金</span> "
					+ "<span class='block-primary'>悠遊卡</span> "
					+ "<span class='block-warning'>信用卡</span> "
					+ "<span class='block-danger'>行動支付</span> "
					+ "<span class='block-dark'>其他方式</span> "
					+ "</div>";
			html += "<div class='text-success'>" + jsonObj.message + "</div>";
			for (let i = 0; i < jsonObj.data_count; i++) {
				html += "<a href='javascript:void(0)' class='float-left mr-2 mb-2 expaa_a_aa04 "
					+ getAA04DisplayCss(jsonObj.raw[i])
					+ " "
					+ (jsonObj.raw[i]["AA09"] == 1 ? "text-secondary" : "text-danger font-weight-bold")
					+ " ' title='"
					+ getExpaaTooltip(jsonObj.raw[i])
					+ "'>"
					+ jsonObj.raw[i]["AA04"]
					+ "</a>";
			}
			showModal({
				body: html,
				title: "搜尋規費列表",
				size: "lg",
				callback: () => {
					$("a.expaa_a_aa04").off("click").on("click", e => {
						let pc_num = $(e.target).text();
						$("#expaa_query_number").val(pc_num);
						$("#expac_query_number").val(pc_num);
						xhrGetExpaaData.call(null, [e]);
						xhrGetExpacItems.call(null, [e]);
						closeModal();
					});
				}
			});
		} else {
			showAlert({message: `找不到規費資料【日期：${txt}, 電腦給號：${number}】`, type: "warning"});
		}
		toggle("[id*=expaa_query_]");
	}).catch(ex => {
		console.error("xhrGetExpaaData parsing failed", ex);
		showAlert({message: ex.toString(), type: "danger"});
	});
}

let xhrFixEasycardPayment = (qday, pc_number, amount, btn_id) => {
	let message = "確定要修正 日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料?";
	showConfirm(message, () => {
		let el = $("#"+btn_id);
		toggle(el);

		let body = new FormData();
		body.append("type", "fix_easycard");
		body.append("qday", qday);
		body.append("pc_num", pc_number);

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(jsonObj => {
			if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
				showModal({
					title: "悠遊卡自動加值扣款失敗修正",
					body: "日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料修正成功!",
					size: "md"
				});
				toggle(el);
				// 移除BTN及該筆
				el.remove();
				el.closest(".easycard_item").remove();
			} else {
				throw new Error("回傳狀態碼不正確!【" + jsonObj.message + "】");
			}
		}).catch(ex => {
			console.error("xhrFixEasycardPayment parsing failed", ex);
			showAlert({message: `xhrFixEasycardPayment parsing failed. ${ex.toString()}`, type: "danger"});
		});
	});
}

let getAA04DisplayCss = row => {
	let css = "block-dark";
	switch (row["AA100"]) {
		case "01":	// 現金
			css = "block-secondary";
			break;
		case "02":	// 支票
		case "03":	// 匯票
		case "04":	// iBon
		case "05":	// ATM
		case "07":	// 其他匯款
			break;
		case "06":	// 悠遊卡
			css = "block-primary";
			break;
		case "08":	// 信用卡
			css = "block-warning";
			break;
		case "09":	// 行動支付
			css = "block-danger";
			break;
		default:
			css = "block-dark";
			break;
	}
	return css;
}

let getExpaaTooltip = row => {
	let title = row["AA09"] == 1 ? "規費收據已印" : "規費收據未印";
	switch (row["AA100"]) {
		case "01":
			title += ", 現金";
			break;
		case "02":
			title += ", 支票";
			break;
		case "03":
			title += ", 匯票";
			break;
		case "04":
			title += ", iBon";
			break;
		case "05":
			title += ", ATM";
			break;
		case "06":
			title += ", 悠遊卡";
			break;
		case "07":
			title += ", 其他匯款";
			break;
		case "08":
			title += ", 信用卡";
			break;
		case "09":
			title += ", 行動支付";
			break;
		default:
			title += ", 不支援的繳款方式代碼";
			break;
	}
	title += "【" + row["AA100"] + "】";
	return title;
}

let getExpaaAA100Options = selected_aa100 => {
	return  "<option " + (selected_aa100 == "01" ? "selected" : "") + " value='01'>【01】現金</option>"
			+ "<option " + (selected_aa100 == "02" ? "selected" : "") + " value='02'>【02】支票</option>"
			+ "<option " + (selected_aa100 == "03" ? "selected" : "") + " value='03'>【03】匯票</option>"
			+ "<option " + (selected_aa100 == "04" ? "selected" : "") + " value='04'>【04】iBon</option>"
			+ "<option " + (selected_aa100 == "05" ? "selected" : "") + " value='05'>【05】ATM</option>"
			+ "<option " + (selected_aa100 == "06" ? "selected" : "") + " value='06'>【06】悠遊卡</option>"
			+ "<option " + (selected_aa100 == "07" ? "selected" : "") + " value='07'>【07】其他匯款</option>"
			+ "<option " + (selected_aa100 == "08" ? "selected" : "") + " value='08'>【08】信用卡</option>"
			+ "<option " + (selected_aa100 == "09" ? "selected" : "") + " value='09'>【09】行動支付</option>";
}

let xhrUpdateExpaaAA09 = function(e) {
	if (confirm("確定要修改列印註記？")) {
		let bindObj = this;
		let body = new FormData();
		body.append("type", "expaa_AA09_update");
		body.append("date", bindObj.date);
		body.append("number", bindObj.number);
		body.append("update_value", $("#"+bindObj.select_id).val());

		$(e.target).remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(jsonObj => {
			$("#exapp_print_status").html("<span class='text-" + ((jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) ? "success" : "danger") + "'>" + jsonObj.message + "</span>");
			addNotification({
				message: "<span class='text-" + ((jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) ? "success" : "danger") + "'>" + jsonObj.message + "</span>"
			});
		});
	}
}

let xhrUpdateExpaaAA100 = function(e) {
	if (confirm("確定要規費付款方式？")) {
		let bindObj = this;
		let body = new FormData();
		body.append("type", "expaa_AA100_update");
		body.append("date", bindObj.date);
		body.append("number", bindObj.number);
		body.append("update_value", $("#"+bindObj.select_id).val());

		$(e.target).remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(jsonObj => {
			$("#exapp_method_status").html("<span class='text-" + ((jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) ? "success" : "danger") + "'>" + jsonObj.message + "</span>");
			addNotification({
				message: "<span class='text-" + ((jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) ? "success" : "danger") + "'>" + jsonObj.message + "</span>"
			});
		});
	}
}

let xhrQueryObsoleteFees = e => {
	// query first then do the creation
	let body = new FormData();
	body.append("type", "get_dummy_ob_fees");

	toggle(e.target);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		toggle(e.target);
		var now = new Date();
		let last_pc_number = jsonObj.raw ? jsonObj.raw[0]["AA04"] : 0;
		let today = (now.getFullYear() - 1911) +
			("0" + (now.getMonth() + 1)).slice(-2) +
			("0" + now.getDate()).slice(-2);

		html = `<small>下一筆假資料：<br />
			※ 電腦給號：${++last_pc_number} <br />
			※ 日期：${today} <br />
			<div class="form-row">
				<div class="input-group input-group-sm col-3">
					<div class="input-group-prepend">
						<span class="input-group-text bg-danger text-white" id="inputGroup-operator">作業人員</span>
					</div>
					<input id="dummy_operator" type="text" placeholder="HB1128" class="form-control" aria-label="作業人員" aria-describedby="inputGroup-operator" required>
				</div>
				<div class="input-group input-group-sm col-3">
					<div class="input-group-prepend">
						<span class="input-group-text bg-danger text-white" id="inputGroup-fee-number">收據號碼</span>
					</div>
					<input id="dummy_fee_number" type="text" placeholder="AB00099480" class="form-control" aria-label="收據號碼" aria-describedby="inputGroup-fee-number" required>
				</div>
				<div class="input-group input-group-sm col">
					<div class="input-group-prepend">
						<span class="input-group-text bg-danger text-white" id="inputGroup-obsolete-reason">作廢原因</span>
					</div>
					<input id="dummy_obsolete_reason" type="text" placeholder="空白單據作廢" class="form-control" aria-label="作廢原因" aria-describedby="inputGroup-obsolete-reason" required>
				</div>
				<div class="btn-group-sm col-1" role="group">
					<button class="btn btn-outline-primary" id="add_dummy_expaa_btn">新增</button>
				</div>
			</div>`;

		html += `<hr>目前系統中(${now.getFullYear() - 1911}年度)的假資料有 ${jsonObj.data_count} 筆：<br />`;
		html += `<table class="table text-center">
			<tr>
				<th>日期</th>
				<th>電腦給號</th>
				<th>收據編號</th>
				<th>作廢原因</th>
				<th>作業人員</th>
			</tr>
		`;
		for (let i = 0; i <jsonObj.data_count; i++) {
			html += `
			<tr>
				<td>${jsonObj.raw[i]["AA01"]}</td>
				<td>${jsonObj.raw[i]["AA04"]}</td>
				<td>${jsonObj.raw[i]["AA05"]}</td>
				<td>${jsonObj.raw[i]["AA104"]}</td>
				<td><span data-id="${jsonObj.raw[i]["AA39"]}" class="user_tag">${jsonObj.raw[i]["AA39"]}</span></td>
			</tr>`;
		}
		html += `</table></small>`;
		
		showModal({
			title: "規費作廢假資料",
			body: html,
			size: "lg",
			callback: () => {
				$("#add_dummy_expaa_btn").off("click").on("click", xhrAddDummyObsoleteFeesData.bind({
					pc_number: last_pc_number,
					today: today
				}));
				addUserInfoEvent();
			}
		});
	}).catch(ex => {
		console.error("xhrQueryObsoleteFees parsing failed", ex);
		showAlert({
			title: "查詢作廢規費回應不正常",
			message: ex.message,
			type: "danger"
		});
	});
}

let xhrAddDummyObsoleteFeesData = function(e) {
	let operator = $("#dummy_operator").val().replace(/[^A-Za-z0-9]/g, "");
	let fee_number = $("#dummy_fee_number").val().replace(/[^A-Za-z0-9]/g, "");
	let reason = $("#dummy_obsolete_reason").val().replace(/[\'\"]/g, "");

	if (isEmpty(operator) || isEmpty(fee_number) || isEmpty(reason)) {
		showAlert({
			message: "需求欄位有問題，請檢查！",
			type: "danger"
		});
		return false;
	}
	
	if (!confirm("確定要新增一個新的假資料？")) {
		return false;
	}


	let args = this;
	let body = new FormData();
	body.append("type", "add_dummy_ob_fees");
	body.append("today", args.today);
	body.append("pc_number", args.pc_number);
	body.append("operator", operator);
	body.append("fee_number",fee_number);
	body.append("reason", reason);

	$(e.target).remove();

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		closeModal(() => {
			showModal({
				title: "新增假規費資料",
				body: jsonObj.message,
				size: "md"
			});
		});
	}).catch(ex => {
		console.error("xhrAddDummyObsoleteFeesData parsing failed", ex);
		showAlert({
			message: ex.message,
			type: "danger"
		});
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
	fetch("query_json_api.php", {
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
	fetch("query_json_api.php", {
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

let xhrGetSURCase = function(e) {
	if (!validateCaseInput("#sur_delay_case_fix_year", "#sur_delay_case_fix_code", "#sur_delay_case_fix_num", "#sur_delay_case_fix_display")) {
		return false;
	}
	let year = $("#sur_delay_case_fix_year").val().replace(/\D/g, "");
	let code = $("#sur_delay_case_fix_code").val();
	let number = $("#sur_delay_case_fix_num").val().replace(/\D/g, "");
	// prepare post params
	let id = trim(year + code + number);
	let body = new FormData();
	body.append("type", "sur_case");
	body.append("id", id);
	
	toggle(e.target);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(response => {
		return response.json();
	}).then(jsonObj => {
		showSURCaseDetail(jsonObj);
		toggle(e.target);
	}).catch(ex => {
		console.error("xhrGetSURCase parsing failed", ex);
		$("#sur_delay_case_fix_display").html("<strong class='text-danger'>無法取得 " + id + " 資訊!【" + ex + "】</strong>");
	});
}

let showSURCaseDetail = jsonObj => {
	if (jsonObj.status == XHR_STATUS_CODE.DEFAULT_FAIL) {
		if (!jsonObj.data_count == 0) {
			showAlert({
				message: "查無測量案件資料",
				type: "warning"
			});
			return;
		}
		let html = "收件字號：" + "<a title='案件辦理情形 on " + landhb_svr + "' href='#' onclick='javascript:window.open(\"http://\"\+landhb_svr\+\":9080/LandHB/Dispatcher?REQ=CMC0202&GRP=CAS&MM01="+ jsonObj.raw["MM01"] +"&MM02="+ jsonObj.raw["MM02"] +"&MM03="+ jsonObj.raw["MM03"] +"&RM90=\")'>" + jsonObj.收件字號 + "</a> </br>";
		html += "收件時間：" + jsonObj.收件時間 + " <br/>";
		html += "收件人員：" + jsonObj.收件人員 + " <br/>";
		html += "　連件數：<input type='text' id='mm24_upd_text' value='" + jsonObj.raw["MM24"] + "' /> <button id='mm24_upd_btn' data-table='SCMSMS' data-case-id='" + jsonObj.收件字號 + "' data-origin-value='" + jsonObj.raw["MM24"] + "' data-column='MM24' data-input-id='mm24_upd_text' data-title=' " + jsonObj.raw["MM01"] + "-" + jsonObj.raw["MM02"] + "-" + jsonObj.raw["MM03"] + " 連件數'>更新</button><br/>";
		html += "申請事由：" + jsonObj.raw["MM06"] + "：" + jsonObj.申請事由 + " <br/>";
		html += "　段小段：" + jsonObj.raw["MM08"] + " <br/>";
		html += "　　地號：" + (isEmpty(jsonObj.raw["MM09"]) ? "" : jsonObj.地號) + " <br/>";
		html += "　　建號：" + (isEmpty(jsonObj.raw["MM10"]) ? "" : jsonObj.建號) + " <br/>";
		html += "<span class='text-info'>辦理情形</span>：" + jsonObj.辦理情形 + " <br/>";
		html += "結案狀態：" + jsonObj.結案狀態 + " <br/>";
		html += "<span class='text-info'>延期原因</span>：" + jsonObj.延期原因 + " <br/>";
		html += "<span class='text-info'>延期時間</span>：" + jsonObj.延期時間 + " <br/>";
		if (jsonObj.結案已否 && jsonObj.raw["MM22"] == "C") {
			html += '<h6 class="mt-2 mb-2"><span class="text-danger">※</span> ' + "發現 " + jsonObj.收件字號 + " 已「結案」但辦理情形為「延期複丈」!" + '</h6>';
			html += "<button id='sur_delay_case_fix_button' class='text-danger' data-trigger='manual' data-toggle='popover' data-content='需勾選右邊其中一個選項才能進行修正' title='錯誤訊息' data-placement='top'>修正</button> ";
			html += "<label for='sur_delay_case_fix_set_D'><input id='sur_delay_case_fix_set_D' type='checkbox' checked /> 辦理情形改為核定</label> ";
			html += "<label for='sur_delay_case_fix_clear_delay_datetime'><input id='sur_delay_case_fix_clear_delay_datetime' type='checkbox' checked /> 清除延期時間</label> ";
		}
		showModal({
			title: "測量案件查詢",
			body: html,
			size: "md",
			callback: function() {
				$("#sur_delay_case_fix_button").off("click").one("click", xhrFixSurDelayCase.bind(jsonObj.收件字號));
				$("#mm24_upd_btn").off("click").one("click", e => {
					// input validation
					let number = $("#mm24_upd_text").val().replace(/\D/g, "");
					$("#mm24_upd_text").val(number);
					xhrUpdateCaseColumnData(e);
				});
				addUserInfoEvent();
			}
		});
	} else if (jsonObj.status == XHR_STATUS_CODE.UNSUPPORT_FAIL) {
		throw new Error("查詢失敗：" + jsonObj.message);
	}
}

let xhrFixSurDelayCase = function(e) {
	let is_checked_upd_mm22 = $("#sur_delay_case_fix_set_D").is(":checked");
	let is_checked_clr_delay = $("#sur_delay_case_fix_clear_delay_datetime").is(":checked");
	if (!is_checked_clr_delay && !is_checked_upd_mm22) {
		showPopper("#sur_delay_case_fix_button");
		return;
	}
	if (confirm("確定要修正本案件?")) {
		$(e.target).remove();
		let id = this;
		//fix_sur_delay_case
		let body = new FormData();
		body.append("type", "fix_sur_delay_case");
		body.append("id", id);
		body.append("UPD_MM22", is_checked_upd_mm22);
		body.append("CLR_DELAY", is_checked_clr_delay);
		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			return response.json();
		}).then(jsonObj => {
			if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
				addNotification({
					message: id + " 複丈案件修正成功!"
				});
			} else {
				showAlert({
					message: jsonObj.message,
					type: "danger"
				});
				throw new Error("回傳狀態碼不正確!【" + jsonObj.message + "】");
			}
		}).catch(ex => {
			console.error("xhrFixSurDelayCase parsing failed", ex);
			showAlert({message: "修正 " + id + " 失敗!【" + ex.toString() + "】", type: "danger"});
		});
	}
}

let xhrUpdateCaseColumnData = e => {
	/**
	 * add various data attrs in the button tag
	 */
	let the_btn = $(e.target);
	let origin_val = the_btn.data("origin-value");
	let upd_val = $("#"+the_btn.data("input-id")).val();
	let title = the_btn.data("title");
	if (origin_val != upd_val && confirm("確定要修改 " + title + " 為「" + upd_val + "」？")) {
		let id = the_btn.data("case-id");
		let column = the_btn.data("column");
		let table = the_btn.data("table");
		let body = new FormData();
		body.append("type", "upd_case_column");
		body.append("id", id);
		body.append("table", table);
		body.append("column", column);
		body.append("value", upd_val);

		the_btn.remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(response => {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(jsonObj => {
			if (jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
				addNotification({
					message: title + "更新成功"
				});
			} else {
				showAlert({
					message: jsonObj.message,
					type: "danger"
				});
			}
		}).catch(ex => {
			console.error("xhrUpdateCaseColumnData parsing failed", ex);
			showModal({
				body: ex.toString(),
				title: "更新欄位失敗",
				size: "md"
			});
		});
	}
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
	
	fetch("query_json_api.php", {
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
		on_board_date = tdoc_raw["AP_ON_DATE"].date.split(" ")[0];
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
		addAnimatedCSS(selector, { name: "pulse" });
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

	fetch("query_json_api.php", {
		method: 'POST',
		body: form_body
	}).then(response => {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(jsonObj => {
		let html = jsonObj.message;
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
			console.warn(jsonObj.message);
		}
	}).catch(ex => {
		console.error("xhrQueryUserInfo parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
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


	fetch("query_json_api.php", {
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
			message: jsonObj.message
		});
		toggle(clicked_element);
	}).catch(ex => {
		console.error("xhrSendMessage parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
}
//]]>
