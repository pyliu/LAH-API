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

<!-- Custom styles for this template -->
<link href="assets/css/starter-template.css" rel="stylesheet">
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
    <img src="assets/img/tao.png" style="vertical-align: middle;" />　
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
          <a class="nav-link" href="/query.php">業務小幫手</a>
        </li>
        <li class="nav-item mt-3 active">
          <a class="nav-link" href="/watch_dog.php">地政看門狗</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="assets/img/menu.png" /></a>
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
    </div>
  </nav>
  <section id="main_content_section" class="mb-5">
    <div class="container-fluid">
      <fieldset>
        <legend>跨所註記遺失檢測＆修正(一周內)</legend>
        <button id="cross_case_check_query_button" data-toggle='tooltip' title='系統每10分鐘自動檢查'>立刻檢查</button>
        <button id="cross_case_check_quote_button">備註</button>
        <blockquote id="cross_case_check_quote" class="hide">
          QUERY: <br />
          SELECT * <br />
            FROM SCRSMS <br />
          WHERE RM07_1 >= '1080415' <br />
            AND RM02 LIKE 'H%1' <br />
            AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR RM101_1 is NULL) 
          <br /><br />
          FIX: <br />
          UPDATE MOICAS.CRSMS SET RM99 = 'Y', RM100 = '資料管轄所代碼', RM100_1 = '資料管轄所縣市代碼', RM101 = '收件所代碼', RM101_1 = '收件所縣市代碼' <br />
			    WHERE RM01 = '收件年' AND RM02 = '收件字' AND RM03 = '收件號'
        </blockquote>
        <div id="cross_case_check_query_display"></div>
      </fieldset>
      <fieldset>
        <legend>規費收費項目(EXPAC)修正</legend>
        <label for="expac_query_year" data-toggle='tooltip' title='欄位:AC25'>規費年度：</label>
        <input type="text" id="expac_query_year" name="expac_query_year" data-trigger="manual" data-toggle="popover" data-content="需輸入3位數民國年分，如「108」。" data-placement="bottom" value="108" />
        <label for="expac_query_number" data-toggle='tooltip' title='欄位:AC04'>電腦給號：</label>
        <input type="text" id="expac_query_number" name="expac_query_number" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數電腦給號，如「0021131」。" data-placement="bottom" />
        <button id="expac_query_button">查詢</button>
        <button id="expac_quote_button">備註</button>
        <blockquote id="expac_quote" class="hide">
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
        <div id="expac_query_display"></div>
      </fieldset>
      <fieldset>
        <legend>規費資料集(EXPAA)修正</legend>
        <label for="expaa_query_date" data-toggle='tooltip' title='欄位:AA01'>　　日期：</label>
        <input type="text" id="expaa_query_date" class="date_picker" name="expaa_query_date" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數民國日期，如「1080426」。" data-placement="bottom" value="<?php echo $today; ?>" />
        <button id="expaa_query_date_button">查詢</button><br />
        <label for="expaa_query_number" data-toggle='tooltip' title='欄位:AA04'>電腦給號：</label>
        <input type="text" id="expaa_query_number" name="expaa_query_number" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數電腦給號，如「0021131」。" data-placement="bottom" />
        <button id="expaa_query_button">查詢</button>
        <button id="expaa_quote_button">備註</button>
        <blockquote id="expaa_quote" class="hide">
          AA09 - 列印註記【1：已印，0：未印】<br />
          AA100 - 付款方式<br />
          <img src="assets/img/EXPAA_AA100_Update.jpg" /><br />
          AA106 - 悠遊卡繳費扣款結果<br />
          AA107 - 悠遊卡交易流水號<br />
          <img src="assets/img/easycard_screenshot.jpg" />
        </blockquote>
        <div id="expaa_query_display"></div>
      </fieldset>
      <fieldset>
        <legend>悠遊卡自動加值付款失敗回復</legend>
        <label for="easycard_query_day" data-toggle='tooltip' title='輸入查詢日期'>日期：</label>
        <input type="text" id="easycard_query_day" name="easycard_query_day" class="easycard_query date_picker" data-trigger="manual" data-toggle="popover" data-content="需輸入7位數民國日期，如「1080321」。" data-placement="bottom" value="<?php echo $today; ?>" />
        <button id="easycard_query_button" class="easycard_query">查詢</button>
        <button id="easycard_quote_button">備註</button>
        <blockquote id="easycard_quote" class="hide">
          <ol>
            <li>櫃台來電通知悠遊卡扣款成功但地政系統卻顯示扣款失敗，需跟櫃台要【電腦給號】</li>
            <li>管理師處理方法：AA106為'2' OR '8'將AA106更正為'1'即可【AA01:事發日期、AA04:電腦給號】。<br />
              UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01='1070720' AND AA04='0043405'
            </li>
          </ol>
          <img src="assets/img/easycard_screenshot.jpg" />
        </blockquote>
        <div id="easycard_query_display"></div>
      </fieldset>
      <fieldset>
        <legend>同步局端跨所案件資料</legend>
        <select id="sync_x_case_year" name="sync_x_case_year">
          <option selected>108</option>
        </select>
        年
        <select id="sync_x_case_code" name="sync_x_case_code" data-trigger="manual" data-toggle="popover" data-content='請選擇案件字' title='案件字' data-placement="top">
		      <option></option>
          <option>HAB1 壢桃登跨</option>
          <option>HCB1 壢溪登跨</option>
          <option>HDB1 壢楊登跨</option>
          <option>HEB1 壢蘆登跨</option>
          <option>HFB1 壢德登跨</option>
          <option>HGB1 壢平登跨</option>
          <option>HHB1 壢山登跨</option>
        </select>
        字
        <input type="text" id="sync_x_case_num" name="sync_x_case_num" data-trigger="manual" data-toggle="popover" data-content='請輸入案件號【最大6位數】' title='案件號' data-placement="top" />
        號
        <button id="sync_x_case_button">比對</button>
        <button id="sync_x_case_quote_button">備註</button>
        <blockquote id="sync_x_case_quote" class="hide">
          將局端跨所資料同步回本所資料庫
        </blockquote>
        <div id="sync_x_case_display"></div>
      </fieldset>
    </div>
  </section><!-- /section -->
  <small id="copyright" class="text-muted fixed-bottom my-2 mx-3 bg-white border rounded"><p id="copyright" class="text-center my-2"><strong>&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong></p></small>

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
        <!-- <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">關閉</button>
        </div> -->
      </div>
    </div>
  </div>

  <!-- Bootstrap core JavaScript
  ================================================== -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <!-- <script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script> -->
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
  <script src="assets/js/ie10-viewport-bug-workaround.js"></script>
  <!-- Promise library -->
  <script src="assets/js/polyfill.min.js"></script>
  <!-- fetch library -->
  <script src="assets/js/fetch.min.js"></script>
  <script src="assets/js/global.js"></script>
  <!-- xhr js -->
  <script src="assets/js/xhr_query.js"></script>
  <!-- bs datepicker -->
  <script src="assets/js/bootstrap-datepicker.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.zh-TW.min.js"></script>
  <!-- cache reload -->
  <script src="assets/js/cache.js"></script>
  <script type="text/javascript">
    $(document).ready(function(e) {
      // unsupported IE detection
      if (window.attachEvent) {
        document.getElementById("main_content_section").innerHTML = '<h2 style="margin-top: 50px; text-align: center; color: red;">不支援舊版IE瀏覽器, 請使用Chrome/Firefox/IE11瀏覽器。</h2>';
        return;
      }
      
      // 跨所註記檢測
      $("#cross_case_check_query_button").on("click", xhrCheckProblematicXCase);
      // automatic check every 10 minutes
      window.pyliuChkTimer = setInterval(xhrCheckProblematicXCase, 600000);

      // query section data event
      $("#easycard_query_button").on("click", xhrEasycardPaymentQuery);
      bindPressEnterEvent("#easycard_query_day", xhrEasycardPaymentQuery);

      // query EXPAC items event
      $("#expac_query_button").on("click", xhrGetExpacItems);
      bindPressEnterEvent("#expac_query_number", xhrGetExpacItems);
      
      // query EXPAA data event
      $("#expaa_query_button").on("click", xhrGetExpaaData);
      $("#expaa_query_date_button").on("click", function(e) {
        $("#expaa_query_number").val("");
        xhrGetExpaaData(e);
      });
      // for query by date, so we need to clear #expaa_query_number value first
      bindPressEnterEvent("#expaa_query_date", function(e) { $("#expaa_query_number").val(""); });
      bindPressEnterEvent("input[id*=expaa_query_", xhrGetExpaaData);
      
      // check diff xcase 
      $("#sync_x_case_button").on("click", xhrCompareXCase);
      bindPressEnterEvent("#sync_x_case_num", xhrCompareXCase);
      $("#sync_x_case_code").on("change", xhrGetCaseLatestNum.bind({
        code_id: "sync_x_case_code",
        year_id: "sync_x_case_year",
        number_id: "sync_x_case_num",
        display_id: "sync_x_case_display"
      }));
    });
  </script>
</body>
</html>
