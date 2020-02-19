<?php
require_once("./include/init.php");
require_once("./include/authentication.php");
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

  <!-- Bootstrap core JavaScript -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- Vue -->
  <script src="assets/js/vue.js"></script>
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <script src="assets/js/bootstrap-vue-icons.min.js"></script>
  <!-- Custom -->
  <script src="assets/js/axios.min.js"></script>
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
  <script src="assets/js/components/lah-footer.js"></script>
  <!-- Vue Chart Components -->
  <script src="assets/js/Chart.min.js"></script>

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
