<?php require_once("./include/init.php"); ?>
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
<link href="assets/css/basic.css" rel="stylesheet">
<link href="assets/css/main.css" rel="stylesheet">
<style type="text/css">
#dropdown01 img {
  width: 32px;
  height: auto;
  vertical-align: middle;
}
</style>
</head>

<body>

  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <i class="my-auto fas fa-user-secret fa-2x text-light"></i>&ensp;
    <a class="navbar-brand" href="watch_dog.php">地政輔助系統 <span class="small">(α)</span></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExampleDefault">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item mt-3">
          <a class="nav-link" href="/index.php">登記案件追蹤</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/query.php">查詢＆報表</a>
        </li>
        <li class="nav-item mt-3 active">
          <a class="nav-link" href="/watch_dog.php">監控＆修正</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/overdue_reg_cases.html">逾期案件</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/watchdog.html">檢視記錄檔</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="assets/img/menu.png" />
          </a>
          <div class="dropdown-menu" aria-labelledby="dropdown01">
            <a class="dropdown-item" href="http://220.1.35.87/" target="_blank">內部知識網</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="http://www.zhongli-land.tycg.gov.tw/" target="_blank">地所首頁</a>
            <a class="dropdown-item" href="http://webitr.tycg.gov.tw:8080/WebITR/" target="_blank">差勤系統</a>
            <a class="dropdown-item" href="/heir_share.html" target="_blank">繼承案件應繼分<span class="text-mute small">(α)</span></a>
            <a class="dropdown-item" href="/heir.html" target="_blank">繼承案件輕鬆審<span class="text-mute small">(β)</span></a>
            <a class="dropdown-item" href="http://tycgcloud.tycg.gov.tw/" target="_blank">公務雲</a>
            <a class="dropdown-item" href="http://220.1.35.24/Web/ap06.asp" target="_blank">分機查詢</a>
            <a class="dropdown-item" href="http://220.1.35.42:9080/SMS98/" target="_blank">案件辦理情形通知系統（簡訊＆EMAIL）</a>
            <a class="dropdown-item" href="/shortcuts.html" target="_blank">各類WEB版應用黃頁</a>
          </div>
        </li>
      </ul>
    </div>
  </nav>
  <section id="main_content_section" class="mb-5">
    <div class="container-fluid">
      <div class="row">
        <div id="case-state-mgt" class="col-6">
          <case-state-mgt></case-state-mgt>
        </div>
        <div id="case-sync-mgt"  class="col-6">
          <case-sync-mgt></case-sync-mgt>
        </div>
      </div>
      <div class="row">
        <div id="case-temp-mgt" class="col-6">
          <case-temp-mgt></case-temp-mgt>
        </div>
        <div id="announcement-mgt" class="col-6">
          <announcement-mgt></announcement-mgt>
        </div>
      </div>
      <div class="row">
        <div id="fee-query-board" class="col-6">
          <fee-query-board></fee-query-board>
        </div>
        <div id="xcase-check" class="col">
          <xcase-check></xcase-check>
        </div>
        <div id="easycard-payment-check" class="col">
          <easycard-payment-check></easycard-payment-check>
        </div>
      </div>
    </div>
  </section><!-- /section -->
  <small id="copyright" class="text-muted fixed-bottom my-2 mx-3 bg-white border rounded">
    <p id="copyright" class="text-center my-2">
    <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!"><svg class="octicon octicon-mark-github v-align-middle" height="16" viewBox="0 0 16 16" version="1.1" width="16" aria-hidden="true"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path></svg></a>
      <strong>&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
    </p>
  </small>

  <!-- Bootstrap core JavaScript -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- Vue -->
  <script src="assets/js/vue.js"></script>
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <!-- Custom -->
  <script src="assets/js/global.js"></script>
  <script src="assets/js/xhr_query.js"></script>
  <script src="assets/js/cache.js"></script>
  <script src="assets/js/FileSaver.min.js"></script>
  <!-- Vue components -->
  <script src="assets/js/components/case-reg-detail.js"></script>
  <script src="assets/js/components/xcase-check.js"></script>
  <script src="assets/js/components/easycard-payment-check.js"></script>
  <script src="assets/js/components/announcement-mgt.js"></script>
  <script src="assets/js/components/case-input-group-ui.js"></script>
  <script src="assets/js/components/case-state-mgt.js"></script>
  <script src="assets/js/components/case-temp-mgt.js"></script>
  <script src="assets/js/components/case-sync-mgt.js"></script>
  <script src="assets/js/components/fee-query-board.js"></script>
  <!-- Vue Chart Components -->
  <script src="assets/js/Chart.min.js"></script>
  <script src="assets/js/vue-chartjs.min.js"></script>
  <script src="assets/js/components/chart-bar.js"></script>

  <script type="text/javascript">
    $(document).ready(e => {
      window.xCaseCheckVue = new Vue({el: "#xcase-check"});
      window.ezCardPaymentCheckVue = new Vue({el: "#easycard-payment-check"});
      window.announcementMgtVue = new Vue({el: "#announcement-mgt"});
      window.caseStateMgtVue = new Vue({el: "#case-state-mgt"});
      window.caseTempMgtVue = new Vue({el: "#case-temp-mgt"});
      window.caseSyncMgtVue = new Vue({el: "#case-sync-mgt"});
      window.feeQueryBoardVue = new Vue({el: "#fee-query-board"});
      
    });
  </script>
</body>
</html>
