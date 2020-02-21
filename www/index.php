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
<link href="assets/css/starter-template.css" rel="stylesheet">
<link href="assets/css/bootstrap-vue.min.css" rel="stylesheet">
<link href="assets/css/bootstrap-datepicker.standalone.min.css" rel="stylesheet">
<link href="assets/css/basic.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
</head>

<body id="html_body">

  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <i class="my-auto fas fa-list-alt fa-2x text-light"></i>&ensp;
    <a class="navbar-brand" href="index.php">地政輔助系統 <span class="small">(α)</span></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExampleDefault">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item mt-3 active">
          <a class="nav-link" href="/index.php">登記案件追蹤</a>
        </li>
		    <li class="nav-item mt-3">
          <a class="nav-link" href="/query.php">查詢＆報表</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/watch_dog.php">監控＆修正</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/overdue_reg_cases.html">逾期案件</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/watchdog.html">檢視記錄檔</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="assets/img/menu.png" width="32" /></a>
          <div class="dropdown-menu" aria-labelledby="dropdown01">
            <a class="dropdown-item" href="http://220.1.35.87/" target="_blank">內部知識網</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="http://www.zhongli-land.tycg.gov.tw/" target="_blank">地所首頁</a>
            <a class="dropdown-item" href="http://webitr.tycg.gov.tw:8080/WebITR/" target="_blank">差勤系統</a>
            <a class="dropdown-item" href="/heir.html" target="_blank">繼承案件輕鬆審<span class="text-mute small">(β)</span></a>
            <a class="dropdown-item" href="http://tycgcloud.tycg.gov.tw/" target="_blank">公務雲</a>
            <a class="dropdown-item" href="http://220.1.35.24/Web/ap06.asp" target="_blank">分機查詢</a>
            <a class="dropdown-item" href="http://220.1.35.42:9080/SMS98/" target="_blank">案件辦理情形通知系統（簡訊＆EMAIL）</a>
            <a class="dropdown-item" href="/shortcuts.html" target="_blank">各類WEB版應用黃頁</a>
          </div>
        </li>
      </ul>
      <form id="search_form" class="form-inline my-2 my-lg-0" onsubmit="return false">
        <label class="text-white" for="date_input">日期：</label>
        <input id="date_input" class="date_picker form-control mr-sm-2" type="text" placeholder="Search" aria-label="Search" value="<?php echo RegCaseData::toDate($qday); ?>" readonly />
        <button class="btn btn-outline-success my-2 my-sm-0" type="submit">搜尋</button>
      </form>
    </div>
  </nav>
  <section id="main_content_section" class="mb-5">
    <div class="container-fluid">

      <div id="info_box" class="alert alert-info">
        <div class="filter-btn-group">
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
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <script src="assets/js/bootstrap-vue-icons.min.js"></script>
  <script src="assets/js/axios.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script src="assets/js/xhr_query.js"></script>

  <script src="assets/js/table_sort.js"></script>
  <script src="assets/js/fixed_header.js"></script>
  <script src="assets/js/autoload.js"></script>

  <script src="assets/js/components/case-reg-detail.js"></script>
  <script src="assets/js/components/lah-header.js"></script>
  <script src="assets/js/components/lah-footer.js"></script>
  
  <script type="text/javascript">
    $(document).ready(e => {
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
        window.location = "index.php?date=" + $("#date_input").val();
      });
      
      // datepicker auto attached via .date_picker class
      $("#date_input").on("changeDate", e => {
        window.location = "index.php?date=" + $("#date_input").val();
      });
    });
  </script>
</body>
</html>
