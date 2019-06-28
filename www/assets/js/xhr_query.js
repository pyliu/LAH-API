//<![CDATA[

var xhrGetCaseLatestNum = function(e) {
	var code_select = $("#"+this.code_id);
	var code_val = code_select.val();
	if (isEmpty(code_val)) {
		return;
	}
	
	var year = $("#"+this.year_id).val().replace(/\D/g, "");
	var code = trim(code_val);

	var body = new FormData();
	body.append("type", "max");
	body.append("year", year);
	body.append("code", code);
	
	var display = $("#"+this.display_id);
	var number = $("#"+this.number_id);

	toggle(code_select);
	toggle(number);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		return response.json();
	}).then(function(jsonObj) {
		if (jsonObj.status == 1) {
			display.html("目前 " + code_val + " 最新案件號為 " + jsonObj.max);
			number.val(jsonObj.max);
		} else if (jsonObj.status == 0) {
			display.html("<strong class='text-danger'>" + jsonObj.message + "</strong>");
		} else {
			display.html("<strong class='text-danger'>" + code_val + "</strong>");
		}
		toggle(code_select);
		toggle(number);
	}).catch(function(ex) {
		console.log("xhrGetCaseLatestNum parsing failed", ex);
	  	display.html("<strong class='text-danger'>" + "查詢最大號碼失敗~【" + code_val + "】" + "</strong>");
	});
};

var showRegCaseDetail = function(jsonObj, use_modal) {
	var html = "<p>" + jsonObj.tr_html + "</p>";
	if (jsonObj.status == 0) {
		html = "<strong class='text-danger'>" + jsonObj.message + "</strong>";
	} else if (jsonObj.status == -1) {
		throw new Error("查詢失敗：" + jsonObj.message);
	} else {
		var area = "其他(" + jsonObj.資料管轄所 + "區)";
		var rm10 = jsonObj.raw.RM10 ? jsonObj.raw.RM10 : "XX";
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

		html += (jsonObj.跨所 == "Y" ? "<span class='bg-info text-white rounded p-1'>跨所案件 (" + jsonObj.資料收件所 + " => " + jsonObj.資料管轄所 + ")</span><br />" : "");
		
		// http://220.1.35.34:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=108&word=HB04&code=005001&sdlyn=N&RM90=
		html += "收件字號：" + "<a title='案件辦理情形 on " + landhb_svr + "' href='#' onclick='javascript:window.open(\"http://\"\+landhb_svr\+\":9080/LandHB/CAS/CCD02/CCD0202.jsp?year="+ jsonObj.raw["RM01"] +"&word="+ jsonObj.raw["RM02"] +"&code="+ jsonObj.raw["RM03"] +"&sdlyn=N&RM90=\")'>" + jsonObj.收件字號 + "</a> ";
		
		// options for switching server
		//html += "<label for='cross_svr'><input type='radio' id='cross_svr' name='svr_opts' value='220.1.35.123' onclick='javascript:landhb_svr=\"220.1.35.123\"' /> 跨縣市主機</label> <br />";

		html += isEmpty(jsonObj.結案已否) ? "<div class='text-danger'><strong>尚未結案！</strong></div>" : "";

		html += "收件時間：" + jsonObj.收件時間 + "<br/>";
		html += "限辦期限：" + jsonObj.限辦期限 + "<br/>";
		html += "作業人員：" + jsonObj.作業人員 + "<br/>";
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
		html += "手機號碼：" + jsonObj.手機號碼 + "<br/>";
	}
	
	if (use_modal) {
		$("#ajax_modal .modal-body p").html(html);
		$("#ajax_modal").modal();
	} else {
		$("#query_display").html(html);
	}
}

var showPrcCaseDetail = function(jsonObj, use_modal) {
	var html = "<p>" + jsonObj.html + "</p>";
	if (jsonObj.status == 0) {
		html = "<strong class='text-danger'>" + jsonObj.message + "</strong>";
	} else if (jsonObj.status == -1) {
		throw new Error("查詢失敗：" + jsonObj.message);
	}
	if (use_modal) {
		$("#ajax_modal .modal-body p").html(html);
		$("#ajax_modal").modal();
	} else {
		$("#query_display").html(html);
		$(".prc_case_serial").on("click", xhrRegQueryCaseDialog);
	}
}

var xhrRegQueryCaseDialog = function(e) {
	// ajax event binding
	var clicked_element = $(e.target);
	// remove additional characters for querying
	var id = trim(clicked_element.text());

	var body = new FormData();
	body.append("type", "reg_case");
	body.append("id", id);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		showRegCaseDetail(jsonObj, true);
	}).catch(function(ex) {
		console.error("xhrRegQueryCaseDialog parsing failed", ex);
	});
}

var xhrRegQueryCase = function(e) {
	var year = $("#query_year").val().replace(/\D/g, "");
	var code = $("#query_code").val();
	var number = $("#query_num").val().replace(/\D/g, "");
	// make total number length is 6
	var offset = 6 - number.length;
	if (offset < 0) {
		$("#query_display").html("<strong class='text-danger'>號的長度不能超過6個數字!</strong>");
		$("#query_num").focus();
		return false;
	} else if (offset > 0) {
		for (var i = 0; i < offset; i++) {
			number = "0" + number;
		}
	}
	
	$("#query_num").val(number);

	// prepare post params
	var id = trim(year + code + number);
	var body = new FormData();
	body.append("type", "reg_case");
	body.append("id", id);
	
	setLoadingHTML("#query_display");

	fetch("query_json_api.php", {
		method: "POST",
		//headers: { "Content-Type": "application/json" },
		body: body
	}).then(function(response) {
		return response.json();
	}).then(function(jsonObj) {
		showRegCaseDetail(jsonObj);
	}).catch(function(ex) {
		console.error("xhrRegQueryCase parsing failed", ex);
		$("#query_display").html("<strong class='text-danger'>無法取得 " + id + " 資訊!【" + ex + "】</strong>");
	});
}

var xhrPrcQueryCase = function(e) {
	var year = $("#query_year").val().replace(/\D/g, "");
	var code = $("#query_code").val();
	var number = $("#query_num").val().replace(/\D/g, "");
	// make total number length is 6
	var offset = 6 - number.length;
	if (offset < 0) {
		$("#query_display").html("<strong class='text-danger'>號的長度不能超過6個數字!</strong>");
		$("#query_num").focus();
		return false;
	} else if (offset > 0) {
		for (var i = 0; i < offset; i++) {
			number = "0" + number;
		}
	}
	
	$("#query_num").val(number);

	// prepare post params
	var id = trim(year + code + number);
	var body = new FormData();
	body.append("type", "prc_case");
	body.append("id", id);
	
	setLoadingHTML("#query_display");

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		return response.json();
	}).then(function(jsonObj) {
		showPrcCaseDetail(jsonObj);
	}).catch(function(ex) {
		console.error("xhrPrcQueryCase parsing failed", ex);
		$("#query_display").html("<strong class='text-danger'>無法取得 " + id + " 資訊!【" + ex + "】</strong>");
	});
}

var xhrCheckProblematicXCase = function(e) {
  setLoadingHTML("#cross_case_check_query_display");
	toggle("#cross_case_check_query_button");
	
	var body = new FormData();
	body.append("type", "x");

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		return response.json();
	}).then(function(jsonObj) {
		if (jsonObj.status == 1) {
			var html = "<div class='mt-1'><span class='rounded-circle bg-danger'> 　 </span> <strong class='text-danger'>找到下列資料：</strong></div>";
			html += "<a href='javascript:void(0)' class='query_case_dialog'>" + jsonObj.收件字號 + "</a> ";
			html += "<button id='fix_xcase_button'>修正</button> ";
			html += "<span id='fix_xcase_button_msg'></span>";
			$("#cross_case_check_query_display").html(html);
			$(".query_case_dialog").on("click", xhrRegQueryCaseDialog);
			$("#fix_xcase_button").on("click", xhrFixProblematicXCase.bind(jsonObj.收件字號));
		} else if (jsonObj.status == 0) {
			var now = new Date();
			$("#cross_case_check_query_display").html("<div class='mt-1'><span class='rounded-circle bg-success'> 　 </span> 目前一切良好！【" + now.toLocaleString() + "】</div>");
		}
		toggle("#cross_case_check_query_button");
	}).catch(function(ex) {
		console.log("xhrCheckProblematicXCase parsing failed", ex);
	  $("#cross_case_check_query_display").html("<strong class='text-danger'>XHR連線查詢有問題!!【" + ex + "】</strong>");
	});
};

var xhrFixProblematicXCase = function(e) {
	var id = trim(this);
	console.log("The problematic xcase id: "+id);

	var body = new FormData();
	body.append("type", "fix_xcase");
	body.append("id", id);

	$("#fix_xcase_button").remove();

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		if (jsonObj.status == 1) {
			$("#fix_xcase_button_msg").html("<span class='text-success'>跨所註記修正完成!</span>");
		} else {
			$("#fix_xcase_button_msg").html("<span class='text-danger'>跨所註記修正失敗!</span>");
		}
	}).catch(function(ex) {
		console.error("xhrFixProblematicXCase parsing failed", ex);
		$("#cross_case_check_query_display").html("<span class='text-danger'>" + ex + "</span>");
	});


}

var xhrGetSectionRALIDCount = function(e) {
	var el = $(e.target);
	toggle(el);
	var text = $("#data_query_text").val();
	var xhr = $.ajax({
		url: "query_json_api.php",
		data: "type=ralid&text="+text,
		method: "POST",
		dataType: "json",
		success: function(jsonObj) {
			toggle(el);
			var count = jsonObj.data_count;
			var html = "";
			for (var i=0; i<count; i++) {
				var this_count = parseInt(jsonObj.raw[i]["土地標示部筆數"]);
				this_count = this_count < 1000 ? 1000 : this_count;
				var dollar = this_count.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
				var blow = jsonObj.raw[i]["土地標示部筆數"].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
				var size = 0;
				if (jsonObj.raw[i]["面積"]) {
					size = jsonObj.raw[i]["面積"].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
				}
				html += "【<span class='text-info'>" + jsonObj.raw[i]["段代碼"]  + "</span>】" + jsonObj.raw[i]["段名稱"] + "：土地標示部 <span class='text-primary'>" + blow + "</span> 筆【面積：" + size + "】。";
				html += " (應收費 NTD " + dollar + " 元整) <br />";
			}
			$("#data_query_result").html(html);
		},
		error: function(obj) {
			toggle(el);
		}
	});
}

/*
	行政課來文查詢某統編是否有申請案件 ....
*/
var xhrGetCasesByID = function(e) {
	var el = $(e.target);
	toggle(".id_query_grp");
	var text = $("#id_query_text").val();

	var finish_count = 0;

	var xhr_crsms = $.ajax({
		url: "query_json_api.php",
		data: "type=crsms&id="+text,
		method: "POST",
		dataType: "json",
		success: function(jsonObj) {
			var count = jsonObj.data_count;
			if (count == 0) {
				$("#id_query_crsms_result").html("本所登記案件資料庫查無統編「"+text+"」收件資料。");
			} else {
				var html = "<p>登記案件：";
				for (var i=0; i<count; i++) {
					html += "<div>" + jsonObj.raw[i]["RM01"] + "-" + jsonObj.raw[i]["RM02"]  + "-" + jsonObj.raw[i]["RM03"] + "</div>";
				}
				html += "</p>";
				$("#id_query_crsms_result").html(html);
			}
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		},
		error: function(obj) {
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		}
	});
	var xhr_cmsms = $.ajax({
		url: "query_json_api.php",
		data: "type=cmsms&id="+text,
		method: "POST",
		dataType: "json",
		success: function(jsonObj) {
			var count = jsonObj.data_count;
			if (count == 0) {
				$("#id_query_cmsms_result").html("本所測量案件資料庫查無統編「"+text+"」收件資料。");
			} else {
				var html = "<p>測量案件：";
				for (var i=0; i<count; i++) {
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
		error: function(obj) {
			finish_count++;
			if (finish_count >= 2) {
				toggle(".id_query_grp");
			}
		}
	});
}

var getEasycardPaymentStatus = function(code) {
	var status = "未知的狀態碼【" + code + "】";
	/*
		1：扣款成功
		2：扣款失敗
		3：取消扣款
		8：扣款異常交易
		9：取消扣款異常交易
	*/
	switch(code) {
		case "1":
			status = "扣款成功";
			break;
		case "2":
			status = "扣款失敗";
			break;
		case "3":
			status = "取消扣款";
			break;
		case "8":
			status = "扣款異常交易";
			break;
		case "9":
			status = "取消扣款異常交易";
			break;
		default:
			break;
	}
	return status;
}

var xhrFixEasycardPayment = function(qday, pc_number, amount, btn_id) {
	var message = "確定要修正 日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料?";
	if (confirm(message)) {
		var el = $("#"+btn_id);
		toggle(el);

		var body = new FormData();
		body.append("type", "fix_easycard");
		body.append("qday", qday);
		body.append("pc_num", pc_number);

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			if (jsonObj.status == 1) {
				alert("日期: " + qday + ", 電腦給號: " + pc_number + ", 金額: " + amount + " 悠遊卡付款資料修正成功!");
				// 移除BTN及該筆
				el.remove();
				el.closest(".easycard_item").remove();
			} else {
				throw new Error("回傳狀態碼不正確!【" + jsonObj.message + "】");
			}
		}).catch(function(ex) {
			console.error("xhrFixEasycardPayment parsing failed", ex);
		});
	}
}

var xhrEasycardPaymentQuery = function(e) {
	// basic checking for tw date input
	var regex = /^\d{7}$/;
	var txt = $("#easycard_query_day").val();
	//console.log(txt);
	if (!isEmpty(txt) && txt.match(regex) == null) {
		showPopper("#easycard_query_day");
		return;
	}

	toggle(".easycard_query");

	var body = new FormData();
	body.append("type", "easycard");
	body.append("qday", txt);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		if (jsonObj.status == 0) {
			$("#easycard_query_display").html("<span class='rounded-circle bg-success'> 　 </span> " + jsonObj.message);
		} else {
			var html = "<div><span class='rounded-circle bg-warning'> 　 </span> <strong class='text-danger'>找到下列資料：</strong></div>";
			for (var i = 0; i < jsonObj.data_count; i++) {
				html += "<div class='easycard_item'>日期: " + jsonObj.raw[i]["AA01"]
					 + ", 電腦給號: " + jsonObj.raw[i]["AA04"]
					 + ", 實收金額: " + jsonObj.raw[i]["AA28"]
					 + ", 作廢原因: " + jsonObj.raw[i]["AA104"]
					 + ", 目前狀態: " + getEasycardPaymentStatus(jsonObj.raw[i]["AA106"])
					 + "【" + jsonObj.raw[i]["AA106"] + "】 ";
				//  無作廢原因才可進行修正
				if (isEmpty(jsonObj.raw[i]["AA104"])) {
					html += "<button id='fix_easycard_payment_" + i + "' onclick='xhrFixEasycardPayment(\"" + jsonObj.raw[i]["AA01"] + "\", \"" + jsonObj.raw[i]["AA04"] + "\", \"" + jsonObj.raw[i]["AA28"] + "\", \"fix_easycard_payment_" + i + "\")'>修正</button>";
				}
				html += "</div>";
			}
			$("#easycard_query_display").html(html);
		}
		toggle(".easycard_query");
	}).catch(function(ex) {
		console.error("xhrEasycardPaymentQuery parsing failed", ex);
		$("#easycard_query_display").html("<strong class='text-danger'>XHR連線查詢有問題!!【" + ex + "】</strong>");
	});
}

var xhrGetExpacItems = function(e) {
	var year = $("#expac_query_year").val().replace(/\D/g, "");
	var number = $("#expac_query_number").val().replace(/\D/g, "");
	
	// basic checking for tw date input
	var regex = /^\d{3}$/;
	if (isEmpty(year) || year.match(regex) == null) {
		showPopper("#expac_query_year");
		return;
	}
	if (isEmpty(number) || isNaN(number)) {
		showPopper("#expac_query_number");
		return;
	}

	// make total pc number length is 7
	var offset = 7 - number.length;
	if (offset < 0) {
		showPopper("#expac_query_number");
		return;
	} else if (offset > 0) {
		for (var i = 0; i < offset; i++) {
			number = "0" + number;
		}
	}

	$("#expac_query_number").val(number);

	// should be the query button (#expac_query_button)
	toggle("#expac_query_button");

	var body = new FormData();
	body.append("type", "expac");
	body.append("year", year);
	body.append("num", number);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		if (jsonObj.status == 0) {
			$("#expac_query_display").html("找不到規費收費項目資料！【電腦給號：" + number + "】");
		} else {
			var html = "<div><strong class='text-danger'>找到下列資料：</strong></div>";
			for (var i = 0; i < jsonObj.data_count; i++) {
				html += "<div class='expac_item'>"
					 + "<a href='javascript:void(0)' class='query_case_dialog'>" + jsonObj.raw[i]["AC16"] + "-" + jsonObj.raw[i]["AC17"] + "-" + jsonObj.raw[i]["AC18"] + "</a>"
					 + " 規費年度: " + jsonObj.raw[i]["AC25"]
					 + ", 電腦給號: " + jsonObj.raw[i]["AC04"]
					 + ", 實收金額: " + jsonObj.raw[i]["AC30"]
					 + ", <select id='modify_expac_item_" + i + "'>" + getExpacItemOptions(jsonObj.raw[i]["AC20"]) + "</select>";
				// modify button
				html += " <button id='modify_expac_item_" + i + "_btn' onclick='xhrModifyExpacItem(\"" + jsonObj.raw[i]["AC25"] + "\", \"" + jsonObj.raw[i]["AC04"] + "\", \"" + jsonObj.raw[i]["AC20"] + "\", \"" + jsonObj.raw[i]["AC30"] + "\", \"modify_expac_item_" + i + "\")'>修改</button>";
				html += " <span id='modify_expac_item_" + i + "_msg'></span>";
				html += "</div>";
			}
			$("#expac_query_display").html(html);
			$(".query_case_dialog").on("click", xhrRegQueryCaseDialog);
		}
		toggle("#expac_query_button");
	}).catch(function(ex) {
		console.error("xhrGetExpacItems parsing failed", ex);
		toggle("#expac_query_button");
		$("#expac_query_display").html("<strong class='text-danger'>XHR連線查詢有問題!!【" + ex + "】</strong>");
	});
}

var getExpacItemOptions = function(selected_ac20) {
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
			+ "<option " + (selected_ac20 == "41" ? "selected" : "") + " value='41'>【41】108年度登記罰鍰</option>";
}

var xhrModifyExpacItem = function(year_ac25, num_ac04, now_code_ac20, amount_ac30, select_id) {
	var this_select = $("#" + select_id);
	if (this_select && this_select.val() != now_code_ac20) {
		var body = new FormData();
		body.append("type", "mod_expac");
		body.append("year", year_ac25);
		body.append("num", num_ac04);
		body.append("code", this_select.val());
		body.append("amount", amount_ac30);

		$("#" + select_id + "_btn").remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			if (jsonObj.status == 1) {
				$("#" + select_id + "_msg").html("<span class='text-success'>修改完成!</span>");
			} else {
				$("#" + select_id + "_msg").html("<span class='text-danger'>修改失敗!</span>");
			}
		}).catch(function(ex) {
			console.error("xhrGetExpacItems parsing failed", ex);
			$("#" + select_id + "_msg").html("<span class='text-danger'>" + ex + "</span>");
		});
	}
}

var xhrCompareXCase = function(e) {
	var year = $("#sync_x_case_year").val().replace(/\D/g, "");
	var code = trim($("#sync_x_case_code").val());
	var number = $("#sync_x_case_num").val().replace(/\D/g, "");
	
	if (isEmpty(code)) {
		showPopper("#sync_x_case_code");
		return;
	}

	// basic checking for number input
	if (isEmpty(number) || isNaN(number)) {
		showPopper("#sync_x_case_num");
		return;
	}

	// make total number length is 6
	var offset = 6 - number.length;
	if (offset < 0) {
		showPopper("#sync_x_case_num");
		return;
	} else if (offset > 0) {
		for (var i = 0; i < offset; i++) {
			number = "0" + number;
		}
	}

	$("#sync_x_case_num").val(number);

	// toggle button disable attr
	toggle("#sync_x_case_button");

	// prepare post params
	var id = trim(year + code + number);
	var body = new FormData();
	body.append("type", "diff_xcase");
	body.append("id", id);

	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		var html = "<div>案件詳情：<a href='javascript:void(0)' id='sync_x_case_serial'>" + year + "-" + code + "-" + number + "</a><div>";
		if (jsonObj.status == 1) {
			html += "<span class='rounded-circle bg-warning'> 　 </span> 請參考下列資訊： <button id='sync_x_case_confirm_button'>同步全部資料</button>";
			html += "<table border='1' class='table-hover text-center mt-1'>";
			html += "<tr><th>欄位名稱</th><th>欄位代碼</th><th>局端</th><th>本所</th><th>同步按鈕</th></tr>";
			for (var key in jsonObj.raw) {
				html += "<tr>";
				html += "<td>" + jsonObj.raw[key]["TEXT"] + "</td>";
				html += "<td>" + jsonObj.raw[key]["COLUMN"] + "</td>";
				html += "<td class='text-danger'>" + jsonObj.raw[key]["REMOTE"] + "</td>";
				html += "<td class='text-info'>" + jsonObj.raw[key]["LOCAL"] + "</td>";
				html += "<td><button data-column='" + jsonObj.raw[key]["COLUMN"] + "' class='sync_column_button'>同步" + jsonObj.raw[key]["COLUMN"] + "</button></td>";
				html += "</tr>";
			};
			html += "</table>";
			$("#sync_x_case_display").html(html);
			$("#sync_x_case_confirm_button").on("click", xhrSyncXCase.bind(id));
			$(".sync_column_button").on("click", xhrSyncXCaseColumn.bind(id));
		} else if (jsonObj.status == -2) {
			html += "<div><span class='rounded-circle bg-warning'> 　 </span> " + jsonObj.message + " <button id='inst_x_case_confirm_button'>新增本地端資料</button></div>"
			$("#sync_x_case_display").html(html);
			$("#inst_x_case_confirm_button").on("click", xhrInsertXCase.bind(id));
		} else {
			html += "<div><span class='rounded-circle bg-success'> 　 </span> " + jsonObj.message + "</div>"
			$("#sync_x_case_display").html(html);
		}
		$("#sync_x_case_serial").on("click", xhrRegQueryCaseDialog);
		toggle("#sync_x_case_button");
	}).catch(function(ex) {
		console.error("xhrCompareXCase parsing failed", ex);
		$("#sync_x_case_display").html("<span class='text-danger'>" + ex + "</span>");
	});
}

var xhrInsertXCase = function(e) {
	if (confirm("確定要拉回局端資料新增於本所資料庫(CRSMS)？")) {
		// this binded as case id
		var id = this;
		var body = new FormData();
		body.append("type", "inst_xcase");
		body.append("id", id);
		$("#inst_x_case_confirm_button").remove();
		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			if (jsonObj.status == 1) {
				$("#sync_x_case_display").html("<span class='text-success'>" + id + " 新增成功！</span>");
			} else {
				$("#sync_x_case_display").html("<span class='text-danger'>" + jsonObj.message + "</span>");
			}
		}).catch(function(ex) {
			console.error("xhrInsertXCase parsing failed", ex);
			$("#sync_x_case_display").html("<span class='text-danger'>" + ex + "</span>");
		});
	}
}

var xhrSyncXCase = function(e) {
	if (confirm("確定要拉回局端資料覆蓋本所資料庫？")) {
		// this binded as case id
		var id = this;
		var body = new FormData();
		body.append("type", "sync_xcase");
		body.append("id", id);
		$("#sync_x_case_confirm_button").remove();
		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			if (jsonObj.status == 1) {
				$("#sync_x_case_display").html("<span class='text-success'>" + id + " 同步成功！</span>");
			} else {
				$("#sync_x_case_display").html("<span class='text-danger'>" + jsonObj.message + "</span>");
			}
		}).catch(function(ex) {
			console.error("xhrSyncXCase parsing failed", ex);
			$("#sync_x_case_display").html("<span class='text-danger'>" + ex + "</span>");
		});
	}
}

var xhrSyncXCaseColumn = function(e) {
	var the_btn = $(e.target);
	if (confirm("確定要同步" + the_btn.attr("data-column") + "？")) {
		// this binded as case id
		var id = this;
		var body = new FormData();
		body.append("type", "sync_xcase_column");
		body.append("id", id);
		body.append("column", the_btn.attr("data-column"));
		
		var td = the_btn.parent();
		the_btn.remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			if (jsonObj.status == 1) {
				td.html("<span class='text-success'>" + the_btn.attr("data-column") + " 同步成功！</span>");
			} else {
				td.html("<span class='text-danger'>" + jsonObj.message + "</span>");
			}

		}).catch(function(ex) {
			console.error("xhrSyncXCaseColumn parsing failed", ex);
			td.html("<span class='text-danger'>" + ex + "</span>");
		});
	}
}

var xhrGetExpaaData = function(e) {
	// basic checking for tw date input
	var regex = /^\d{7}$/;
	var txt = $("#expaa_query_date").val();
	if (txt.match(regex) == null) {
		showPopper("#expaa_query_date");
		return;
	}
	
	var number = $.trim($("#expaa_query_number").val().replace(/\D/g, ""));
	if (!isEmpty(number)) {
		// basic checking for number input
		if (isNaN(number)) {
			showPopper("#expaa_query_number");
			return;
		}

		// make total number length is 7
		var offset = 7 - number.length;
		if (offset < 0) {
			showPopper("#expaa_query_number");
			return;
		} else if (offset > 0) {
			for (var i = 0; i < offset; i++) {
				number = "0" + number;
			}
		}
		$("#expaa_query_number").val(number);
	}

	toggle("[id*=expaa_query_]");

	var body = new FormData();
	body.append("type", "expaa");
	body.append("qday", txt);
	body.append("num", number);
	body.append("list_mode", $(e.target).attr("id") == "expaa_query_date_button");
	fetch("query_json_api.php", {
		method: "POST",
		body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function(jsonObj) {
		// only has one record
		if (jsonObj.status == 1) {
			var html = "<div class='text-info'>規費資料：</div>";
			html += "<ul>";
			for (var key in jsonObj.raw) {
				html += "<li>";
				html += key + "：";
				if (key == "列印註記") {
					html += "<select id='exapp_print_select'>"
						  + "<option value='0'" + (jsonObj.raw[key] == 0 ? "selected" : "") + ">【0】未印</option>"
						  + "<option value='1'" + (jsonObj.raw[key] == 1 ? "selected" : "") + ">【1】已印</option>"
						  + "</select> "
						  + "<button id='exapp_print_button'>修改</button>"
						  + "<span id='exapp_print_status'></span>";
				} else if (key == "繳費方式代碼") {
					html += "<select id='exapp_method_select'>"
						  + getExpaaAA100Options(jsonObj.raw[key])
						  + "</select> "
						  + "<button id='exapp_method_button'>修改</button>"
						  + "<span id='exapp_method_status'></span>";
				} else if (key == "悠遊卡繳費扣款結果") {
					html += getEasycardPaymentStatus(jsonObj.raw[key]) + "【" + jsonObj.raw[key] + "】";
					//  無作廢原因才可進行修正
					if (isEmpty(jsonObj.raw["作廢原因"]) && jsonObj.raw[key] != 1) {
						html += "<button id='fix_exapp_easycard_payment_btn" + "' onclick='xhrFixEasycardPayment(\"" + jsonObj.raw["開單日期"] + "\", \"" + jsonObj.raw["電腦給號"] + "\", \"" + jsonObj.raw["實收總金額"] + "\", \"fix_exapp_easycard_payment_btn" + "\")'>修正為扣款成功</button>";
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
			$("#exapp_print_button").on("click", xhrUpdateExpaaAA09.bind({
				date: $("#expaa_query_date").val(),
				number: $("#expaa_query_number").val(),
				select_id: "exapp_print_select"
			}));
			$("#exapp_method_button").on("click", xhrUpdateExpaaAA100.bind({
				date: $("#expaa_query_date").val(),
				number: $("#expaa_query_number").val(),
				select_id: "exapp_method_select"
			}));
		} else if (jsonObj.status == 2) {
			// has many records
			var html = "<div>" 
					+ "<span class='block-secondary'>現金</span> "
					+ "<span class='block-primary'>悠遊卡</span> "
					+ "<span class='block-warning'>信用卡</span> "
					+ "<span class='block-danger'>行動支付</span> "
					+ "<span class='block-dark'>其他方式</span> "
					+ "</div>";
			html += "<div class='text-success'>" + jsonObj.message + "</div>";
			for (var i = 0; i < jsonObj.data_count; i++) {
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
			$("#expaa_query_display").html(html);
			$("a.expaa_a_aa04").on("click", function(e) {
				var pc_num = $(e.target).text();
				$("#expaa_query_number").val(pc_num);
				$("#expac_query_number").val(pc_num);
				xhrGetExpaaData.call(null, [e]);
			});
		} else {
			$("#expaa_query_display").html("<span class='text-danger'>" + jsonObj.message.replace(", ", "") + "</span>");
		}
		toggle("[id*=expaa_query_]");
	}).catch(function(ex) {
		console.error("xhrGetExpaaData parsing failed", ex);
		$("#expaa_query_display").html("<span class='text-danger'>" + ex + "</span>");
	});
}

var getAA04DisplayCss = function(row) {
	var css = "block-dark";
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

var getExpaaTooltip = function(row) {
	var title = row["AA09"] == 1 ? "規費收據已印" : "規費收據未印";
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

var getExpaaAA100Options = function(selected_aa100) {
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

var xhrUpdateExpaaAA09 = function(e) {
	if (confirm("確定要修改列印註記？")) {
		var bindObj = this;
		var body = new FormData();
		body.append("type", "expaa_AA09_update");
		body.append("date", bindObj.date);
		body.append("number", bindObj.number);
		body.append("update_value", $("#"+bindObj.select_id).val());

		$(e.target).remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			$("#exapp_print_status").html("<span class='text-" + ((jsonObj.status == 1) ? "success" : "danger") + "'>" + jsonObj.message + "</span>");
		});
	}
}

var xhrUpdateExpaaAA100 = function(e) {
	if (confirm("確定要規費付款方式？")) {
		var bindObj = this;
		var body = new FormData();
		body.append("type", "expaa_AA100_update");
		body.append("date", bindObj.date);
		body.append("number", bindObj.number);
		body.append("update_value", $("#"+bindObj.select_id).val());

		$(e.target).remove();

		fetch("query_json_api.php", {
			method: "POST",
			body: body
		}).then(function(response) {
			if (response.status != 200) {
				throw new Error("XHR連線異常，回應非200");
			}
			return response.json();
		}).then(function(jsonObj) {
			$("#exapp_method_status").html("<span class='text-" + ((jsonObj.status == 1) ? "success" : "danger") + "'>" + jsonObj.message + "</span>");
		});
	}
}

var xhrLoadSQL = function(e) {
	var val = $("#preload_sql_select").val();

	if (isEmpty(val)) {
		$("#sql_csv_text").val("");
		return;
	}

	toggle(e.target);

	var body = new FormData();
	body.append("type", "load_select_sql");
	body.append("file_name", val);
	fetch("load_file_api.php", {
		method: 'POST',
			body: body
	}).then(function(response) {
		if (response.status != 200) {
			throw new Error("XHR連線異常，回應非200");
		}
		return response.json();
	}).then(function (jsonObj) {
		if (jsonObj.status == 1) {
			$("#sql_csv_text").val(jsonObj.data);
			toggle(e.target);
		} else {
			throw new Error("讀取異常，jsonObj.status非為1");
		}
	}).catch(function(ex) {
		console.error("xhrLoadSQL parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
};

var xhrExportSQLCsv = function(e) {
	var val = $("#sql_csv_text").val();

	toggle(e.target);

	var body = new FormData();
	body.append("type", "file_sql_csv");
	body.append("sql", val);
	fetch("export_file_api.php", {
		method: 'POST',
			body: body
	}).then(function(response) {
		return response.blob();
	}).then(function (blob) {
		var d = new Date();
		var url = window.URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = (d.getFullYear() - 1911)
			+ ("0" + (d.getMonth()+1)).slice(-2)
			+ ("0" + d.getDate()).slice(-2) + ".csv";
		document.body.appendChild(a); // we need to append the element to the dom -> otherwise it will not work in firefox
		a.click();    
		a.remove();  //afterwards we remove the element again
		// release object in memory
		window.URL.revokeObjectURL(url);
		toggle(e.target);
	}).catch(function(ex) {
		console.error("xhrExportSQLCsv parsing failed", ex);
		alert("XHR連線查詢有問題!!【" + ex + "】");
	});
};
//]]>
