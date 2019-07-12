<?php
require_once("./include/init.php");
$operators = GetDBUserMapping();
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
        <button id="query_button">登記收件資料查詢</button>
        <button id="query_prc_button">地價收件資料查詢</button>
        <div id="query_display"></div>
      </fieldset>
      <fieldset>
        <legend>轄區各段土地標示部筆數＆面積查詢</legend>
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
        <legend>報表匯出</legend>
        <label for="preload_sql_select">預載查詢：</label>
        <select id="preload_sql_select">
          <option value="">=== 所內統計 ===</option>
          <option value="01_reg_case_monthly.sql">每月登記案件</option>
          <option value="11_reg_reason_query_monthly.sql">每月登記案件 by 登記原因</option>
          <option value="02_reg_remote_case_monthly.sql">每月遠途先審案件</option>
          <option value="03_reg_other_office_case_monthly.sql">每月跨所案件【本所代收】</option>
          <option value="04_reg_other_office_case_2_monthly.sql">每月跨所案件【非本所收件】</option>
          <option value="09_reg_other_office_case_3_monthly.sql">每月跨所子號案件【本所代收】</option>
          <option value="10_reg_reason_stats_monthly.sql">每月跨所各登記原因案件統計 by 收件所</option>
          <option value="07_reg_foreign_case_monthly.sql">每月權利人＆義務人為外國人案件</option>
          <option value="08_reg_workstation_case.sql">外站人員謄本核發量</option>
          <option value="14_sur_rain_delay_case.sql">因雨延期測量案件數</option>
          <option value="05_adm_area_size.sql">段小段面積統計</option>
          <option value="06_adm_area_blow_count.sql">段小段土地標示部筆數</option>
          <option value="12_prc_not_F_case.sql">未完成地價收件資料</option>
          <option value="13_log_court_cert.sql">法院謄本申請LOG檔查詢 BY 段、地建號</option>
          <option value="">=== 地籍資料 ====</option>
          <option value="txt_AI00601_B.sql">AI00601 - 土地管理者資料</option>
          <option value="txt_AI00601_E.sql">AI00601 - 建物管理者資料</option>
          <option value="txt_AI00701.sql">AI00701 - 建物標示部資料</option>
          <option value="txt_AI00801.sql">AI00801 - 基地坐落資料</option>
          <option value="txt_AI00901.sql">AI00901 - 建物分層及附屬資料</option>
          <option value="txt_AI01001.sql">AI01001 - 主建物與共同使用部分資料</option>
        </select>
        <textarea id="sql_csv_text" class="mw-100 w-100" style="height: 150px">Input SELECT SQL here ... </textarea>
        <button id="sql_csv_text_button">匯出CSV</button>
        <button id="sql_txt_text_button">匯出TXT</button>
        <button id="sql_csv_quote_button">備註</button>
        <blockquote id="sql_report_blockquote" class="hide">
          <p>輸入SELECT SQL指令匯出查詢結果。</p>
          <img src="assets/img/csv_export_method.jpg" class="w-auto" />
        </blockquote>
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
  <small id="copyright" class="text-muted fixed-bottom my-2 mx-3 bg-white border rounded">
    <p id="copyright" class="text-center my-2">
    <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!"><svg class="octicon octicon-mark-github v-align-middle" height="16" viewBox="0 0 16 16" version="1.1" width="16" aria-hidden="true"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path></svg></a>
      <strong>&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
    </p>
  </small>

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
      // 登記查詢按鍵
      $("#query_button").on("click", xhrRegQueryCase);
      // 號
      bindPressEnterEvent("#query_num", xhrRegQueryCase);
      // 地價查詢按鍵
      $("#query_prc_button").on("click", xhrPrcQueryCase);

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

      // sql csv export
      $("#sql_csv_text_button").on("click", xhrExportSQLCsv);
      $("#sql_txt_text_button").on("click", xhrExportSQLTxt);
      $("#preload_sql_select").on("change", xhrLoadSQL);
    });
  </script>
</body>
</html>
