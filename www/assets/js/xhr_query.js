//<![CDATA[
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
	
	if (showUserInfoFromCache(keyword, keyword)) {
		return;
	}
	
	axios.post(CONFIG.JSON_API_EP, {
		type: 'search_user',
		keyword: keyword
	}).then(res => {
		if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
			showUserInfoByRAW(res.data.raw[res.data.data_count - 1]);
		} else {
			addNotification({
				title: "搜尋使用者",
				message: res.data.message,
				type: "warning"
			});
			console.warn(res.data.message);
		}
	}).catch(ex => {
		console.error("xhrSearchUsers parsing failed", ex);
		showAlert({
			title: "搜尋使用者",
			message: ex.message,
			type: "danger"
		});
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
				<b-link href="get_user_img.php?name=${tdoc_raw["AP_USER_NAME"]}" target="_blank">
					<b-card-img
						src="get_user_img.php?name=${tdoc_raw["AP_USER_NAME"]}"
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

let xhrTest = () => {
	let form_body = new FormData();
	form_body.append("type", "reg_stats");
	form_body.append("year_month", "10812");

	axios.post(CONFIG.JSON_API_EP, {
		method: 'reg_stats',
		year_month: "10812"
	}).then(res => {
		console.log(res.data);
	}).catch(ex => {
		console.error("xhrTest parsing failed", ex);
		showAlert({ title: "測試XHR連線", message: ex.message, type: "danger"});
	});
}
//]]>
