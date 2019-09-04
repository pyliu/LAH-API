<?php

require_once("./include/init.php");
require_once("./include/RegCaseData.class.php");

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

<!-- Custom styles for this template -->
<link href="assets/css/starter-template.css" rel="stylesheet">
<link href="assets/css/bootstrap-datepicker.standalone.min.css" rel="stylesheet">
<link href="assets/css/basic.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
</head>

<body id="html_body">

  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <img src="assets/img/tao.png" style="vertical-align: middle;" />　
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
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="assets/img/menu.png" width="32" /></a>
          <div class="dropdown-menu" aria-labelledby="dropdown01">
            <a class="dropdown-item" href="http://220.1.35.87/" target="_blank">內部知識網</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="http://www.zhongli-land.tycg.gov.tw/" target="_blank">地所首頁</a>
            <a class="dropdown-item" href="http://webitr.tycg.gov.tw:8080/WebITR/" target="_blank">差勤系統</a>
            <a class="dropdown-item" href="/index.html" target="_blank">繼承案件輕鬆審<span class="text-mute small">(beta)</span></a>
            <a class="dropdown-item" href="http://tycgcloud.tycg.gov.tw/" target="_blank">公務雲</a>
            <a class="dropdown-item" href="http://220.1.35.24/Web/ap06.asp" target="_blank">分機查詢</a>
            <a class="dropdown-item" href="http://220.1.35.42:9080/SMS98/" target="_blank">案件辦理情形通知系統（簡訊＆EMAIL）</a>
            <a class="dropdown-item" href="/shortcuts.html" target="_blank">各類WEB版應用黃頁</a>
          </div>
        </li>
      </ul>
      <form id="search_form" class="form-inline my-2 my-lg-0" onsubmit="return false">
        <label class="text-white" for="date_input">日期：</label>
        <input id="date_input" class="form-control mr-sm-2" type="text" placeholder="Search" aria-label="Search" value="<?php echo RegCaseData::toDate($qday); ?>" readonly />
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
      <div id="header_container" class="container-fluid fade header-fixed">
        <table class='text-center col-lg-12' border="1">
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
    <!-- Modal -->
    <div class="modal fade" id="ajax_modal" role="dialog">
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">案件詳情</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p>詳情顯示在這邊</p>
          </div>
		  <!--
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">關閉</button>
          </div>
		  -->
        </div>
      </div>
    </div>
  </section><!-- /section -->
  <!-- <canvas id = "balls_canvas" class"fade"></canvas> -->
  <small id="copyright" class="text-muted my-2 mx-3 fixed-bottom bg-white border rounded">
    <p id="copyright" class="text-center" style="cursor: pointer">
    <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!"><svg class="octicon octicon-mark-github v-align-middle" height="16" viewBox="0 0 16 16" version="1.1" width="16" aria-hidden="true"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path></svg></a>
      <strong data-placement='bottom' data-toggle="tooltip" title="CLICK for SURPRISE!">&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
    </p>
  </small>

  <!-- Bootstrap core JavaScript
  ================================================== -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <!-- <script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script> -->
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
  <script src="assets/js/ie10-viewport-bug-workaround.js"></script>
  <!-- sorting by table header script -->
  <script src="assets/js/table_sort.js"></script>
  <!-- load table content -->
  <script src="assets/js/autoload.js"></script>
  <!-- fixed table header -->
  <script src="assets/js/fixed_header.js"></script>
  <script src="assets/js/global.js"></script>
  <!-- bs datepicker -->
  <script src="assets/js/bootstrap-datepicker.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.zh-TW.min.js"></script>
  <script type="text/javascript">
    $(document).ready(function(e) {
      // unsupported IE detection
      if (window.attachEvent) {
        document.getElementById("main_content_section").innerHTML = '<h2 style="margin-top: 50px; text-align: center; color: red;">不支援舊版IE瀏覽器, 請使用Chrome/Firefox/IE11瀏覽器。</h2>';
      }

      // filter button event
      // filter button
      $("#red_btn").on("click", function(e) {
          var state = $("#table_container").data("active");
          if (state != "red") {
              $("#table_container").data("active", "red");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-danger").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-danger").length);
              }
          }
      });
    
      $("#yellow_btn").on("click", function(e) {
          var state = $("#table_container").data("active");
          if (state != "yellow") {
              $("#table_container").data("active", "yellow");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-warning").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-warning").length);
              }
          }
      });
    
      $("#green_btn").on("click", function(e) {
          var state = $("#table_container").data("active");
          if (state != "green") {
              $("#table_container").data("active", "green");
              $("#case_results tbody tr").hide();
              $("#case_results tbody tr.bg-success").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr.bg-success").length);
              }
          }
      });
    
      $("#info_btn").on("click", function(e) {
          var state = $("#table_container").data("active");
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
    
      $("#gray_btn").on("click", function(e) {
          var state = $("#table_container").data("active");
          if (state != "all") {
              $("#table_container").data("active", "all");
              $("#case_results tbody tr").show();
              if ($("#record_count")) {
                $("#record_count").text($("#case_results tbody tr").length);
              }
          }
      });
    
      // easter egg :)
      $("#copyright").on("click", function(e) {
          var script = document.createElement("script");
          script.src = "assets/js/balls.js";
          $("#copyright").after(script);
          $("#copyright").remove();
      });
    
      // form event
      $("#search_form").on("submit", function(e) {
        window.location = "index.php?date=" + $("#date_input").val();
      });
    
      $("#date_input").datepicker({
        daysOfWeekDisabled: "",
        language: "zh-TW",
        daysOfWeekHighlighted: "1,2,3,4,5",
        todayBtn: true,
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
            return (d.getFullYear() - 1911) + "-"
                  + ("0" + (d.getMonth()+1)).slice(-2) + "-"
                  + ("0" + d.getDate()).slice(-2);
          },
          toValue: function (date, format, language) {
            // initialize to now
            return new Date();
          }
        }
      }).on("changeDate", function(e) {
        window.location = "index.php?date=" + $("#date_input").val();
      });
    });
  </script>
</body>
</html>
