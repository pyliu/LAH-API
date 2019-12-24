<?php
require_once("./include/init.php");
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
<style type="text/css">
#contact a:link, a:visited, a:hover {
	color: gray;
}

#dropdown01 img {
  width: 32px;
  height: auto;
  vertical-align: middle;
}

.expac_item {
  margin-bottom: 5px;
}

blockquote img {
  /*width: 80%;*/
  display: block;
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
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="assets/img/menu.png" /></a>
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
        <div id="xcase-check" class="col">
          <xcase-check></xcase-check>
        </div>
        <div id="easycard-payment-check" class="col">
          <easycard-payment-check></easycard-payment-check>
        </div>
      </div>
      <div class="row">
        <div id="announcement-mgt" class="col-6">
          <announcement-mgt></announcement-mgt>
        </div>
        <div id="case-state-mgt" class="col-6">
          <case-state-mgt></case-state-mgt>
        </div>
      </div>
      <div class="row">
        <div id="case-temp-mgt" class="col-6">
          <case-temp-mgt></case-temp-mgt>
        </div>
        <div class="col-6">
          <fieldset>
            <legend>同步局端跨所案件資料</legend>
            <div class="message"><span class="text-danger">※</span>主機IP不在局端<span class="text-info">白名單</span>內將無法使用本功能，目前為<span class="text-danger"><?php echo $_SERVER["SERVER_ADDR"] ?></span>。</div>
            
            <div class="form-row">
              <div class="input-group input-group-sm col">
                <select id="sync_x_case_year" name="sync_x_case_year" class="form-control" aria-label="年" aria-describedby="inputGroup-sync_x_case_year" required>
                  <option>107</option>
                  <option selected>108</option>
                  <option>109</option>
                </select>
                <div class="input-group-append">
                  <span class="input-group-text" id="inputGroup-sync_x_case_year">年</span>
                </div>
              </div>
              <div class="input-group input-group-sm col">
                <select id="sync_x_case_code" name="sync_x_case_code" class="form-control"  data-trigger="manual" data-toggle="popover" data-content='請選擇案件字' title='案件字' data-placement="top" aria-label="年" aria-describedby="inputGroup-sync_x_case_code" required>
                  <option></option>
                  <option>HAB1 壢桃登跨</option>
                  <option>HCB1 壢溪登跨</option>
                  <option>HDB1 壢楊登跨</option>
                  <option>HEB1 壢蘆登跨</option>
                  <option>HFB1 壢德登跨</option>
                  <option>HGB1 壢平登跨</option>
                  <option>HHB1 壢山登跨</option>
              </select>
                <div class="input-group-append">
                  <span class="input-group-text" id="inputGroup-sync_x_case_code">字</span>
                </div>
              </div>
              <div class="input-group input-group-sm col">
                <input type="number" step="10" min="10" max="999999" id="sync_x_case_num" name="sync_x_case_num" class="form-control" aria-label="號" aria-describedby="inputGroup-sync_x_case_num" required data-trigger="manual" data-toggle="popover" data-content='請輸入案件號(最多6碼)' title='案件號' data-placement="top" />
                <div class="input-group-append">
                  <span class="input-group-text" id="inputGroup-sync_x_case_num">號</span>
                </div>
              </div>
              <div class="filter-btn-group col">
                <button id="sync_x_case_button" class="btn btn-sm btn-outline-primary">比對</button>
                <button id="sync_x_case_quote_button" class="btn btn-sm btn-outline-success">備註</button>
              </div>
            </div>
            
            <blockquote id="sync_x_case_quote" class="hide" data-title="同步局端跨所案件資料">
              <h6>將局端跨所資料同步回本所資料庫</h6>
              <div><span class="text-danger">※</span>新版跨縣市回寫機制會在每一分鐘時自動回寫，故局端資料有可能會比較慢更新。【2019-06-26】</div>
              <div><span class="text-danger">※</span>局端針對遠端連線同步異動資料庫有鎖IP，故<span class="text-danger">IP不在局端白名單內的主機將無法使用本功能</span>，目前主機IP為 <span class="text-warning"><?php echo $_SERVER["SERVER_ADDR"] ?></span> 。【2019-10-01】</div>
            </blockquote>
            <div id="sync_x_case_display" class="message"></div>
          </fieldset>
        </div>
      </div>
      <div class="row">
        <div class="col-6">
          <fieldset>
            <legend>規費資料集修正<small>(EXPAA)</small></legend>

            <div class="form-row">
              <div class="input-group input-group-sm col">
                <div class="input-group-prepend">
                  <span class="input-group-text" id="inputGroup-expaa_query_date">日期</span>
                </div>
                <input type="text" id="expaa_query_date" name="expaa_query_date" class="form-control date_picker no-cache" placeholder="1081107" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數民國日期，如「1080426」。" data-placement="bottom" value="<?php echo $today; ?>" />
                <button id="expaa_query_date_button" class="btn btn-sm btn-outline-primary">查詢</button>
              </div>
              <div class="input-group input-group-sm col">
                <div class="input-group-prepend">
                  <span class="input-group-text" id="inputGroup-expaa_query_number">給號</span>
                </div>
                <input type="number" max="9999999" min="1" id="expaa_query_number" name="expaa_query_number" class="form-control" placeholder="0006574" data-toggle="popover" data-content="需輸入7位數電腦給號，如「0021131」。" data-placement="bottom" />
                <button id="expaa_query_num_button" class="btn btn-sm btn-outline-secondary" title="針對電腦給號查詢">查詢</button>
              </div>
              <div class="filter-btn-group col">
                <!-- <button id="expaa_query_date_button" class="btn btn-sm btn-outline-primary">查詢</button> -->
                <button id="expaa_add_obsolete_button" class="btn btn-sm btn-outline-danger" title="新增作廢假資料以利空白規費單作廢">作廢</button>
                <button id="expaa_quote_button" class="btn btn-sm btn-outline-success">備註</button>
              </div>
            </div>

            <blockquote id="expaa_quote" class="hide" data-title="規費資料集修正">
              AA09 - 列印註記【1：已印，0：未印】<br />
              AA100 - 付款方式<br />
              <img src="assets/img/EXPAA_AA100_Update.jpg" /><br />
              AA106 - 悠遊卡繳費扣款結果<br />
              AA107 - 悠遊卡交易流水號<br />
              <img src="assets/img/easycard_screenshot.jpg" />
            </blockquote>
            <div id="expaa_query_display" class="message"></div>
          </fieldset>
        </div>
        <div class="col-6">
          <fieldset>
            <legend>規費收費項目修正<small>(EXPAC)</small></legend>

            <div class="form-row">
              <div class="input-group input-group-sm col">
                <div class="input-group-prepend">
                  <span class="input-group-text" id="inputGroup-expac_query_year">規費年度</span>
                </div>
                <select id="expac_query_year" name="expac_query_year" class="form-control no-cache">
                  <option>107</option>
                  <option selected>108</option>
                  <option selected>109</option>
                </select>
              </div>
              <div class="input-group input-group-sm col">
                <div class="input-group-prepend">
                  <span class="input-group-text" id="inputGroup-expac_query_number">電腦給號</span>
                </div>
                <input type="number" min="1" max="9999999" id="expac_query_number" name="expac_query_number" class="form-control" placeholder="0021131" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數電腦給號，如「0021131」。" data-placement="bottom" />
              </div>
              <div class="filter-btn-group col">
                <button id="expac_query_button" class="btn btn-sm btn-outline-primary">查詢</button>
                <button id="expac_quote_button" class="btn btn-sm btn-outline-success">備註</button>
              </div>
            </div>
            <div id="expac_query_display" class="message"></div>
            <blockquote id="expac_quote" class="hide" data-title="規費收費項目修正">
              <img src="assets/img/correct_payment_screenshot.jpg" />
              -- 規費收費項目<br/>
              SELECT t.AC25 AS "規費年度",<br/>
                    t.AC04 AS "電腦給號",<br/>
                    t.AC16 AS "收件年",<br/>
                    t.AC17 AS "收件字",<br/>
                    t.AC18 AS "收件號",<br/>
                    t.AC20 AS "收件項目代碼",<br/>
                    p.e21  AS "收費項目名稱",<br/>
                    t.AC29 AS "應收金額",<br/>
                    t.AC30 AS "實收金額"<br/>
              FROM MOIEXP.EXPAC t<br/>
              LEFT JOIN MOIEXP.EXPE p<br/>
                  ON p.E20 = t.AC20<br/>
              WHERE t.AC04 = '0021131' AND t.AC25 = '108'
            </blockquote>
          </fieldset>
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
  <script src="assets/js/jquery-3.4.1.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- bs datepicker -->
  <script src="assets/js/bootstrap-datepicker.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.zh-TW.min.js"></script>
  <!-- Promise library -->
  <script src="assets/js/polyfill.min.js"></script>
  <!-- fetch library -->
  <script src="assets/js/fetch.min.js"></script>

  <script src="assets/js/vue.js"></script>
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script src="assets/js/xhr_query.js"></script>
  
  <script src="assets/js/cache.js"></script>
  <script src="assets/js/FileSaver.min.js"></script>

  <script src="assets/js/components/xcase-check.js"></script>
  <script src="assets/js/components/easycard-payment-check.js"></script>
  <script src="assets/js/components/announcement-mgt.js"></script>
  <script src="assets/js/components/case-input-group-ui.js"></script>
  <script src="assets/js/components/case-state-mgt.js"></script>
  <script src="assets/js/components/case-temp-mgt.js"></script>

  <script type="text/javascript">
    $(document).ready(e => {
      if (detectIE()) {
        document.getElementById("main_content_section").innerHTML = '<h2 style="margin-top: 50px; text-align: center; color: red;">不支援IE瀏覽器, 請使用Chrome/Firefox瀏覽器。</h2>';
        return;
      }

      window.xCaseCheckVue = new Vue({el: "#xcase-check"});
      window.ezCardPaymentCheckVue = new Vue({el: "#easycard-payment-check"});
      window.announcementMgtVue = new Vue({el: "#announcement-mgt"});
      window.caseStateMgtVue = new Vue({el: "#case-state-mgt"});
      window.caseTempMgtVue = new Vue({el: "#case-temp-mgt"});

      // query EXPAC items event
      $("#expac_query_button").on("click", xhrGetExpacItems);
      bindPressEnterEvent("#expac_query_number", xhrGetExpacItems);
      
      // query EXPAA data event
      $("#expaa_query_num_button").on("click", xhrGetExpaaData);
      $("#expaa_query_date_button").on("click", e => {
        $("#expaa_query_number").val("");
        xhrGetExpaaData(e);
      });
      // for query by date, so we need to clear #expaa_query_number value first
      bindPressEnterEvent("#expaa_query_date", e => { $("#expaa_query_number").val(""); });
      bindPressEnterEvent("input[id*=expaa_query_", xhrGetExpaaData);
      // obselete event
      $("#expaa_add_obsolete_button").on("click", xhrQueryObsoleteFees);
      
      // check diff xcase 
      $("#sync_x_case_button").on("click", xhrCompareXCase);
      bindPressEnterEvent("#sync_x_case_num", xhrCompareXCase);
      $("#sync_x_case_code").on("change", xhrGetCaseLatestNum.bind({
        code_id: "sync_x_case_code",
        year_id: "sync_x_case_year",
        number_id: "sync_x_case_num"
      }));
    });
  </script>
</body>
</html>
