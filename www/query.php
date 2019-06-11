<?php
require_once("./include/init.php");
require_once("./include/OraDB.class.php");
$operators = OraDB::getDBUserList();
ksort($operators);
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

fieldset fieldset {
  border: 2px solid #04c;
  border-bottom: 0;
  border-left: 0;
  border-right: 0;
  border-radius: 0;
}

fieldset fieldset legend {
  font-size: 18px;
}
</style>
</head>

<body>

  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <img src="assets/img/tao.png" style="vertical-align: middle;" />　
    <a class="navbar-brand" href="query.php">地政輔助系統 <span class="small">(α)</span></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExampleDefault">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item mt-3">
          <a class="nav-link" href="/index.php">登記案件追蹤</a>
        </li>
		<li class="nav-item mt-3 active">
          <a class="nav-link" href="/query.php">業務小幫手</a>
        </li>
        <li class="nav-item mt-3">
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
        <legend>登記案件查詢</legend>
        <select id="query_year" name="query_year">
          <option>105</option>
          <option>106</option>
          <option>107</option>
          <option selected>108</option>
        </select>
        年
        <select id="query_code" name="query_code">
		      <option></option>
          <option>HB04 壢登    </option>
          <option>HB05 壢永    </option>
          <option>HB06 壢速    </option>
          <option>HAB1 壢桃登跨</option>
          <option>HCB1 壢溪登跨</option>
          <option>HDB1 壢楊登跨</option>
          <option>HEB1 壢蘆登跨</option>
          <option>HFB1 壢德登跨</option>
          <option>HGB1 壢平登跨</option>
          <option>HHB1 壢山登跨</option>
          <option>HBA1 桃壢登跨</option>
          <option>HBC1 溪壢登跨</option>
          <option>HBD1 楊壢登跨</option>
          <option>HBE1 蘆壢登跨</option>
          <option>HBF1 德壢登跨</option>
          <option>HBG1 平壢登跨</option>
          <option>HBH1 山壢登跨</option>
        </select>
        字
        <input type="text" id="query_num" name="query_num" data-toggle='tooltip' title='輸入案件號' />號
        <button id="query_button">查詢</button>
        <div id="query_display"></div>
      </fieldset>
      <fieldset>
        <legend>中壢轄區各段土地標示部筆數＆面積查詢</legend>
        <a href="http://220.1.35.24/%E8%B3%87%E8%A8%8A/webinfo2/%E4%B8%8B%E8%BC%89%E5%8D%80%E9%99%84%E4%BB%B6/%E6%A1%83%E5%9C%92%E5%B8%82%E5%9C%9F%E5%9C%B0%E5%9F%BA%E6%9C%AC%E8%B3%87%E6%96%99%E5%BA%AB%E9%9B%BB%E5%AD%90%E8%B3%87%E6%96%99%E6%94%B6%E8%B2%BB%E6%A8%99%E6%BA%96.pdf" target="_blank">電子資料申請收費標準</a>
        <a href="assets/files/土地基本資料庫電子資料流通申請表.doc">電子資料申請書</a> <br />
        <input id="data_query_text" name="data_query_text" type="text" data-toggle='tooltip' title='輸入關鍵字或是段代碼' />
        <button id="data_query_button">查詢</button>
        <button id="data_quote_button">備註</button>
        <blockquote id="data_blockquote" class="hide">
          -- 段小段筆數＆面積計算 (RALID 登記－土地標示部) <br/>
          SELECT t.AA48 as "段代碼", <br/>
              m.KCNT as "段名稱", <br/>
              SUM(t.AA10) as "面積", <br/>
              COUNT(t.AA10) as "筆數" <br/>
          FROM MOICAD.RALID t <br/>
          LEFT JOIN MOIADM.RKEYN m ON (m.KCDE_1 = '48' and m.KCDE_2 = t.AA48) <br/>
          --WHERE t.AA48 = '%【輸入數字】'<br/>
          --WHERE m.KCNT = '%【輸入文字】%'<br/>
          GROUP BY t.AA48, m.KCNT;
        </blockquote>
        <div id="data_query_result"></div>
      </fieldset>
      <fieldset>
        <legend>法院來函查統編</legend>
        <input id="id_query_text" name="id_query_text" type="text" class="id_query_grp" data-toggle='tooltip' title='輸入統編' />
        <button id="id_query_button" class="id_query_grp">查詢</button>
        <button id="id_quote_button">備註</button>
        <blockquote id="id_sql" class="hide">
          -- 【法院來函查統編】MOICAS_CRSMS 土地登記案件查詢-權利人+義務人+代理人+複代 <br/>
          SELECT t.* <br/>
            FROM MOICAS.CRSMS t <br/>
          WHERE t.RM18 = 'H221350201' <br/>
              OR t.RM21 = 'H221350201' <br/>
              OR t.RM24 = 'H221350201' <br/>
              OR t.RM25 = 'H221350201'; <br/>
          <br/>
          -- 【法院來函查統編】MOICAS_CMSMS 測量案件資料查詢-申請人+代理人+複代 <br/>
          SELECT t.* <br/>
            FROM MOICAS.CMSMS t <br/>
          WHERE t.MM13 = 'H221350201' <br/>
              OR t.MM17_1 = 'H221350201' <br/>
              OR t.MM17_2 = 'H221350201';
        </blockquote>
        <div id="id_query_crsms_result"></div>
        <div id="id_query_cmsms_result"></div>
      </fieldset>
      <fieldset>
        <legend>CSV報表匯出</legend>
        <fieldset>
          <legend>遠途先審</legend>
          <label for="remote_case_month">指定年月</label>
          <input name="remote_case_month" id="remote_case_month" title='輸入檢測' data-trigger="manual" data-toggle="popover" data-placement="top" class="remote_cases_export_action_grp" />
          <button id="remote_case_csv_button" class="remote_cases_export_action_grp">匯出</button>
          <button id="remote_case_quote_button">備註</button>
          <blockquote id="remote_case_blockquote" class="hide">
            -- 每月遠途先審明細查詢 <br/>
            SELECT <br/>
            　　　t.RM01 AS "收件年", <br/>
            　　　t.RM02 AS "收件字", <br/>
            　　　t.RM03 AS "收件號", <br/>
            　　　t.RM09 AS "登記原因代碼", <br/>
            　　　w.KCNT AS "登記原因", <br/>
            　　　t.RM07_1 AS "收件日期", <br/>
            　　　u.LIDN AS "權利人統編", <br/>
            　　　u.LNAM AS "權利人名稱", <br/>
            　　　u.LADR AS "權利人地址", <br/>
            　　　v.AB01 AS "代理人統編", <br/>
            　　　v.AB02 AS "代理人名稱", <br/>
            　　　v.AB03 AS "代理人地址", <br/>
            　　　t.RM13 AS "筆數", <br/>
            　　　t.RM16 AS "棟數" <br/>
            FROM MOICAS.CRSMS t <br/>
            　　　LEFT JOIN MOICAD.RLNID u ON t.RM18 = u.LIDN  -- 權利人 <br/>
            　　　LEFT JOIN MOICAS.CABRP v ON t.RM24 = v.AB01  -- 代理人 <br/>
            　　　LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因 <br/>
            WHERE  <br/>
            　　　-- t.RM02 = 'HB06' AND  <br/>
            　　　t.RM07_1 LIKE '10803%' AND  <br/>
            　　　(u.LADR NOT LIKE '%桃園市%' AND u.LADR NOT LIKE '%桃園縣%') AND  <br/>
            　　　(v.AB03 NOT LIKE '%桃園市%' AND v.AB03 NOT LIKE '%桃園縣%')
          </blockquote>
        </fieldset>
        <fieldset>
          <legend>SQL</legend>
          <textarea id="sql_csv_text" class="mw-100 w-100" style="height: 150px">SELECT * FROM SCRSMS WHERE RM09 IN ('CU', 'CW') AND RM30 IN ('F', 'Z') AND (RM07_1 BETWEEN '1080101' AND '1080531')</textarea>
          <button id="sql_csv_text_button">匯出</button>
          <button id="sql_csv_quote_button">備註</button>
          <blockquote id="XXX_blockquote" class="hide">
            輸入SELECT SQL指令匯出查詢結果。
          </blockquote>
        </fieldset>
      </fieldset>
      <fieldset>
        <legend>使用者對應表</legend>
        <div class="float-clear"><input type="text" id="filter_input" name="filter_input" value="HB" /> <span id="filter_info" class="text-info">
        <?php
          echo count($operators); 
        ?>筆</span></div>
        <?php
          foreach ($operators as $id => $name) {
            //echo $id.": ".($name == false ? "無此人!" : $name)."</br>";
            echo "<div class='float-left m-2 user_tag' style='width: 200px'>".$id.": ".($name == false ? "無此人!" : $name)."</div>";
          }
		    ?>
      </fieldset>
    </div>
  </section><!-- /section -->
  <small id="copyright" class="text-muted fixed-bottom my-2 mx-3 bg-white border rounded"><p id="copyright" class="text-center my-2"><strong>&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong></p></small>
  <!-- Bootstrap core JavaScript
  ================================================== -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <!-- <script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script> -->
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
  <script src="assets/js/ie10-viewport-bug-workaround.js"></script>
  <script src="assets/js/global.js"></script>
  <!-- xhr js -->
  <script src="assets/js/xhr_query.js"></script>
  <!-- for mark highlight -->
  <script src="assets/js/mark.jquery.min.js"></script>
  <!-- cache reload -->
  <script src="assets/js/cache.js"></script>
  <script type="text/javascript">
    // place this variable in global to use this int for condition jufgement, e.g. 108
    var this_year = <?php echo $this_year; ?>;
    $(document).ready(function(e) {
      // unsupported IE detection
      if (window.attachEvent) {
        document.getElementById("main_content_section").innerHTML = '<h2 style="margin-top: 50px; text-align: center; color: red;">不支援舊版IE瀏覽器, 請使用Chrome/Firefox/IE11瀏覽器。</h2>';
        return;
      }
      // 選擇【字】的事件
      $("#query_code").on("change", xhrGetCaseLatestNum.bind({
        code_id: "query_code",
        year_id: "query_year",
        number_id: "query_num",
        display_id: "query_display"
      }));
      // 查詢按鍵
      $("#query_button").on("click", xhrQueryCase);
      // 號
      bindPressEnterEvent("#query_num", xhrQueryCase);

      // query section data event
      $("#data_query_button").on("click", xhrGetSectionRALIDCount);
      bindPressEnterEvent("#data_query_text", xhrGetSectionRALIDCount);

      // query case by id event
      $("#id_query_button").on("click", xhrGetCasesByID);
      bindPressEnterEvent("#id_query_text", xhrGetCasesByID);

      /**
       * For User Mapping
       */
      var prevVal = null;
      var filter_user = function(el) {
        var val = $(el).val();
        if (val == prevVal) {
          return;
        }
        var keyword = new RegExp(val, "ig");
        $(".user_tag").unmark(val, {
          "className": "highlight"
        });
        if (isEmpty(val)) {
          $(".user_tag").removeClass("hide");
          $("#filter_info").text("<?php echo count($operators); ?>筆");
        } else {
          $(".user_tag").each(function(idx, div) {
            keyword.test($(div).text()) ? $(div).removeClass("hide") : $(div).addClass("hide");
          }).mark(val, {
            "element" : "strong",
            "className": "highlight"
          });
          $("#filter_info").text($(".user_tag").length - $(".user_tag.hide").length + "筆");
        }
        prevVal = val;
      };

      filter_user("#filter_input");

      var delayTimer = null;
      $("#filter_input").on("keyup", function(e) {
        clearTimeout(delayTimer);
        delayTimer = setTimeout(function() {
          filter_user(e.target);
        }, 1000);
      });

      // remote case csv export
      $("#remote_case_csv_button").on("click", xhrExportRemoteCases);
      bindPressEnterEvent("#remote_case_month", xhrExportRemoteCases);

      // sql csv export
      $("#sql_csv_text_button").on("click", xhrExportSQLCsv);
    });
  </script>
</body>
</html>
