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
        // prevent extra tooltip element occupied
        $(".tooltip").remove();
        $("#current_time").html("<img src='assets/img/walking.gif' border='0' title='loading ...... ' width='25' height='25' />");
        target.load("allcases.php", "date=" + $("#date_input").val(), function() {
            adjustQueryTime();
            adjustTableContent();
            // only today that needs to update regularly
            if (today == $("#date_input").val()) {
                let hour = new Date().getHours();
                // only refresh within work hour
				if (hour > 7 && hour < 18) {
                    clearInterval(loading_timeout_handle);
                    loading_timeout_handle = setTimeout(startRefresh, refresh_time);
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
    // ajax event binding
    $(".case.ajax").on("click", function(e) {
		var clicked_element = $(e.target);
		
		var parentTag = $(e.target).parent().get( 0 ).tagName;
		if (parentTag != "TD") {
			clicked_element = $(e.target).parent().parent();
		}
		
        // remove additional characters for querying
        var id = clicked_element.text().replace(/[^a-zA-Z0-9]/g, "");
		
        $.ajax({
            url: "query_json_api.php",
            data: "type=reg_case&id=" + id,
            method: "POST",
            dataType: "json",
            success: function(jsonObj) {
                var html = jsonObj.跨所 == "Y" ? "<span class='bg-info text-white rounded p-1'>跨所案件 (" + jsonObj.資料收件所 + " => " + jsonObj.資料管轄所 + ")</span><br />" : "";
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
                // options for switching server
                //html += "<label for='cross_svr'><input type='radio' id='cross_svr' name='svr_opts' value='220.1.35.123' onclick='javascript:landhb_svr=\"220.1.35.123\"' /> 跨縣市主機</label> <br />";
                html += (jsonObj.結案已否 === undefined || $.trim(jsonObj.結案已否) == "") ? "<div class='text-danger'><strong>尚未結案！</strong></div>" : "";
                
                // http://220.1.35.34:9080/LandHB/CAS/CCD02/CCD0202.jsp?year=108&word=HB04&code=005001&sdlyn=N&RM90=
                html += "收件字號：" + "<a title='案件辦理情形 on " + landhb_svr + "' href='#' onclick='javascript:window.open(\"http://\"\+landhb_svr\+\":9080/LandHB/CAS/CCD02/CCD0202.jsp?year="+ jsonObj.raw["RM01"] +"&word="+ jsonObj.raw["RM02"] +"&code="+ jsonObj.raw["RM03"] +"&sdlyn=N&RM90=\")'>" + id + "</a>" + "<br/>";
                html += "收件時間：" + jsonObj.收件時間 + "<br/>";
				html += "登記原因：" + jsonObj.登記原因 + "<br/>";
                html += "限辦期限：" + jsonObj.限辦期限 + "<br/>";
                html += "作業人員：" + jsonObj.作業人員 + "<br/>";
                html += "辦理情形：" + jsonObj.辦理情形 + "<br/>";
				html += "區域：" + area + "【" + jsonObj.raw["RM10"] + "】<br/>";
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
                showModal({
                    body: html,
                    title: "案件詳情",
                    size: "lg"
                });
                scrollToElement(clicked_element);
                $(".focused-element").removeClass("focused-element");
                clicked_element.addClass("focused-element");
            },
            error: function() {
                alert("無法取得 " + id + " 資訊!");
            }
        });
    });
    // user info dialog event
    console.assert(addUserInfoEvent, "Can't find addUserInfoEvent function ... do you include global.js")
    addUserInfoEvent();
}

$(document).ready(startRefresh);
//]]>
