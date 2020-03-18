<?php

require_once("./include/init.php");
require_once("./include/authentication.php");
require_once("./include/RegCaseData.class.php");

$qday = $_REQUEST["date"];
$qday = preg_replace("/\D+/", "", $qday);
if (empty($qday) || !preg_match("/^[0-9]{7}$/i", $qday)) {
  $qday = $today; // 今天
}

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="description" content="For tracking taoyuan land registration cases">
<meta name="author" content="LIU, PANG-YU">
<title>桃園市中壢地政事務所</title>

<!-- Bootstrap core CSS -->
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/loading-btn.css">
<link href="assets/css/animate.css" rel="stylesheet">
<link href="assets/css/awesome-font.css" rel="stylesheet">
<!-- Custom styles for this template -->
<link href="assets/css/bootstrap-vue.min.css" rel="stylesheet">
<link href="assets/css/bootstrap-datepicker.standalone.min.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
<style>
table th {
  text-align: center;
  cursor: pointer;
  text-decoration: underline;
  background-color: black;
  color: white;
  font-weight: bold;
}

.filter-btn-group {
  float: right !important;
}

.tooltip > .tooltip-inner {
  border: 1px solid white;
  padding: 5px;
  font-size: 16px;
}

.header-fixed {
  position: fixed;
  left: 0;
  top: 78px;
}

.opacity {
  opacity: 0.65;
}

canvas {
  background: #FFF;
  display: block;
  margin: 0 auto;
}

#case_results a:link, #case_results a:visited {
  color: rgba(0, 0, 255, 0.842);
}

#case_results .bg-success a:link, #case_results .bg-danger a:link, #case_results .bg-success a:visited, #case_results .bg-danger a:visited {
  color: white;
}

#info_box {
  margin-top: 5px;
}
</style>
</head>

<body id="html_body">
  <section id="main_content_section" class="mb-5">
    <form id="search_form" class="form-inline mr-5 my-3" style="right: 15px; top: 0px;position: absolute; z-index: 9999;" onsubmit="return false">
      <label class="text-white" for="date_input">日期：</label>
      <input id="date_input" class="date_picker form-control mr-sm-2" type="text" placeholder="Search" aria-label="Search" value="<?php echo RegCaseData::toDate($qday); ?>" readonly />
      <button class="btn btn-outline-success my-2 my-sm-0" type="submit">搜尋</button>
    </form>
    <div class="container-fluid">
      <div id="info_box" class="alert alert-info">
        <div class="filter-btn-group float-right">
          <button id='gray_btn' class='btn btn-sm btn-default'>全部</button>
          <button id='info_btn' class='btn btn-sm btn-info'>正常</button>
          <button id='red_btn' class='btn btn-sm btn-danger'>逾期</button>
          <button id='yellow_btn' class='btn btn-sm btn-warning' data-toggle='tooltip' title='離限辦期限四小時內'>快逾期</button>
          <button id='green_btn' class='btn btn-sm btn-success'>結案</button>
        </div>
        <small>更新時間：<span id="current_time"></span></small>
      </div>
      <div id="table_container" class="table-responsive"></div>
      <!-- float table header -->
      <div id="header_container" class="container-fluid hide header-fixed">
        <table class='text-center col-12' border="1">
          <thead>
            <tr id="header_tr" class="header">
              <th id="float_th1">收件字號</th>
              <th id="float_th2">收件時間</th>
              <th id="float_th3">登記原因</th>
              <th id="float_th4">狀態</th>
              <th id="float_th5">收件人員</th>
              <th id="float_th6">作業人員</th>
              <th id="float_th7">初審人員</th>
              <th id="float_th8">複審人員</th>
              <th id="float_th9">准登人員</th>
              <th id="float_th10">登錄人員</th>
              <th id="float_th11">校對人員</th>
              <th id="float_th12">結案人員</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div><!-- /.container -->
  </section><!-- /section -->
  
  <!-- Bootstrap core JavaScript -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- bs datepicker -->
  <script src="assets/js/bootstrap-datepicker.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.zh-TW.min.js"></script>

  <script src="assets/js/vue.js"></script>
  <script src="assets/js/vuex.js"></script>
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <script src="assets/js/bootstrap-vue-icons.min.js"></script>
  <script src="assets/js/axios.min.js"></script>
  <script src="assets/js/localforage.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script src="assets/js/components/lah-vue.js"></script>
  <script src="assets/js/xhr_query.js"></script>

  <script src="assets/js/table_sort.js"></script>
  <script src="assets/js/autoload.js"></script>

  <script src="assets/js/components/case-reg-detail.js"></script>
  
  <script type="text/javascript">
    $(document).ready(e => {
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
                toDisplay: (date, format, language) => {
                  let d = new Date(date);
                  return (d.getFullYear() - 1911) + ("0" + (d.getMonth()+1)).slice(-2) + ("0" + d.getDate()).slice(-2);
                },
                toValue: (date, format, language) => new Date()
            }
        });
      }
      // filter button
      $("#red_btn").on("click", e => {
          let state = $("#table_container").data("active");
          if (state != "red") {
              $("#table_container").data("active", "red");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-danger").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-danger").length);
              }
          }
      });
    
      $("#yellow_btn").on("click", e => {
          let state = $("#table_container").data("active");
          if (state != "yellow") {
              $("#table_container").data("active", "yellow");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-warning").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-warning").length);
              }
          }
      });
    
      $("#green_btn").on("click", e => {
          let state = $("#table_container").data("active");
          if (state != "green") {
              $("#table_container").data("active", "green");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-success").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-success").length);
              }
          }
      });
    
      $("#info_btn").on("click", e => {
          let state = $("#table_container").data("active");
          if (state != "info") {
              $("#table_container").data("active", "info");
              $("#case_results tbody tr").show();
              $("#case_results tbody tr.bg-success").hide();
              $("#case_results tbody tr.bg-warning").hide();
              $("#case_results tbody tr.bg-danger").hide();
              if ($("#record_count")) {
                $("#record_count").text(
                  $("#case_results tbody tr").length - 
                  $("#case_results tbody tr.bg-success").length -
                  $("#case_results tbody tr.bg-warning").length -
                  $("#case_results tbody tr.bg-danger").length
                );
              }
          }
      });
    
      $("#gray_btn").on("click", e => {
          let state = $("#table_container").data("active");
          if (state != "all") {
              $("#table_container").data("active", "all");
              $("#case_results tbody tr").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr").length);
              }
          }
      });
    
      // easter egg :)
      $("#copyright").on("click", e => {
          let script = document.createElement("script");
          script.src = "assets/js/balls.js";
          $("#copyright").after(script);
          $("#copyright").remove();
      });
    
      // form event
      $("#search_form").on("submit", e => {
        window.location = "tracking.php?date=" + $("#date_input").val();
      });
      
      // datepicker auto attached via .date_picker class
      $("#date_input").on("changeDate", e => {
        window.location = "tracking.php?date=" + $("#date_input").val();
      });

    });
  </script>
</body>
</html>
