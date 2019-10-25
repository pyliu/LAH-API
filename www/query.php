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
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/loading-btn.css">
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
          <a class="nav-link" href="/query.php">查詢＆報表</a>
        </li>
        <li class="nav-item mt-3">
          <a class="nav-link" href="/watch_dog.php">監控＆修正</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle hamburger" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="assets/img/menu.png" /></a>
          <div class="dropdown-menu" aria-labelledby="dropdown01">
            <a class="dropdown-item" href="http://220.1.35.87/" target="_blank">內部知識網</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="http://www.zhongli-land.tycg.gov.tw/" target="_blank">地所首頁</a>
            <a class="dropdown-item" href="http://webitr.tycg.gov.tw:8080/WebITR/" target="_blank">差勤系統</a>
            <a class="dropdown-item" href="/heir.html" target="_blank">繼承案件輕鬆審<span class="text-mute small">(beta)</span></a>
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
        <div class="col-6">
          <fieldset>
            <legend>登記案件查詢</legend>
            <select id="query_year" name="query_year" class="no-cache">
              <option>105</option>
              <option>106</option>
              <option>107</option>
              <option selected>108</option>
            </select>
            年
            <?php echo getCodeSelectHTML("query_code"); ?>
            字
            <input type="text" id="query_num" name="query_num" data-toggle='tooltip' data-content='請輸入案件號(最多6碼)' title='案件號' data-placement="bottom" />號
            <button id="query_button" @click="xhrRegQueryCase">登記</button>
            <button id="query_prc_button">地價</button>
            <!-- <div id="query_display"></div> -->
          </fieldset>
        </div>
        <div class="col-6">
          <fieldset>
            <legend>複丈案件查詢<small>(修正已結延期、修改連件數)</small></legend>
            <select id="sur_delay_case_fix_year" name="sur_delay_case_fix_year" class="no-cache">
              <option selected>108</option>
            </select>
            年
            <select id="sur_delay_case_fix_code" name="sur_delay_case_fix_code" data-trigger="manual" data-toggle="popover" data-content='請選擇案件字' title='案件字' data-placement="top">
              <option></option>
              <!--
              <option value="HB11">HB11 中地測數</option>
              <option value="HB14">HB14 中地測法</option>
              -->
              <option value="HB12">HB12 中地測丈</option>
              <option value="HB13">HB13 中地測建</option>
              <option value="HB17">HB17 中地法土</option>
              <option value="HB18">HB18 中地法建</option>
            </select>
            字
            <input type="text" id="sur_delay_case_fix_num" name="sur_delay_case_fix_num" data-trigger="manual" data-toggle="popover" data-content='請輸入案件號(最多6碼)' title='案件號' data-placement="top" />
            號
            <button id="sur_delay_case_fix_search_button">查詢</button>
            <button id="sur_delay_case_fix_quote_button">備註</button>
            <blockquote id="sur_delay_case_fix_quote" class="hide">
              <h5><span class="text-danger">※</span>注意：本功能會清除如下圖之欄位資料並將案件辦理情形改為【核定】，請確認後再執行。</h5>
              <img src="assets/howto/107-HB18-3490_測丈已結案案件辦理情形出現(逾期)延期複丈問題調整【參考】.jpg" />
              <h5><span class="text-danger">※</span> 問題原因說明</h5>
              <div>原因是 CMB0301 延期複丈功能，針對於有連件案件在做處理時，會自動根據MM24案件數，將後面的案件自動做延期複丈的更新。導致後續已結案的案件會被改成延期複丈的狀態 MM22='C' 就是 100、200、300、400為四連件，所以100的案件 MM24='4'，200、300、400 的 MM24='0' 延期複丈的問題再將100號做延期複丈的時候，會將200、300、400也做延期複丈的更新，所以如果400已經結案，100做延期複丈，那400號就會變成 MM22='C' MM23='A' MM24='4' 的異常狀態。</div>
            </blockquote>
            <!-- <div id="sur_delay_case_fix_display"></div> -->
          </fieldset>
        </div>
      </div>
      <div class="row">
        <div class="col-6">
          <fieldset>
            <legend>報表匯出</legend>
            <label for="preload_sql_select">預載查詢：</label>
            <select id="preload_sql_select">
              <optgroup label="==== 所內登記案件統計 ====">
                <option value="01_reg_case_monthly.sql">每月案件統計</option>
                <option value="11_reg_reason_query_monthly.sql">每月案件 by 登記原因</option>
                <option value="02_reg_remote_case_monthly.sql">每月遠途先審案件</option>
                <option value="03_reg_other_office_case_monthly.sql">每月跨所案件【本所代收】</option>
                <option value="04_reg_other_office_case_2_monthly.sql">每月跨所案件【非本所收件】</option>
                <option value="09_reg_other_office_case_3_monthly.sql">每月跨所子號案件【本所代收】</option>
                <option value="10_reg_reason_stats_monthly.sql">每月跨所各登記原因案件統計 by 收件所</option>
                <option value="07_reg_foreign_case_monthly.sql">每月權利人＆義務人為外國人案件</option>
                <option value="07_regf_foreign_case_monthly.sql">每月外國人地權登記統計</option>
                <option value="17_rega_case_stats_monthly.sql">每月土地建物登記統計檔</option>
                <option value="08_reg_workstation_case.sql">外站人員謄本核發量</option>
              </optgroup>
              <optgroup label="==== 所內其他統計 ====">
                <option value="16_sur_close_delay_case.sql">已結卻延期之複丈案件</option>
                <option value="14_sur_rain_delay_case.sql">因雨延期測量案件數</option>
                <option value="05_adm_area_size.sql">段小段面積統計</option>
                <option value="06_adm_area_blow_count.sql">段小段土地標示部筆數</option>
                <option value="12_prc_not_F_case.sql">未完成地價收件資料</option>
                <option value="13_log_court_cert.sql">法院謄本申請LOG檔查詢 BY 段、地建號</option>
                <option value="15_reg_land_stats.sql">某段之土地所有權人清冊資料</option>
                <option value="18_cross_county_crsms.sql">全國跨縣市收件資料(108年)</option>
              </optgroup>
              <optgroup label="==== 地籍資料 ====" class="bg-success text-white">
                <option value="txt_AI00301.sql">AI00301 - 土地標示部資料</option>
                <option value="txt_AI00401.sql">AI00401 - 土地所有權部資料</option>
                <option value="txt_AI00601_B.sql">AI00601 - 土地管理者資料</option>
                <option value="txt_AI00601_E.sql">AI00601 - 建物管理者資料</option>
                <option value="txt_AI00701.sql">AI00701 - 建物標示部資料</option>
                <option value="txt_AI00801.sql">AI00801 - 基地坐落資料</option>
                <option value="txt_AI00901.sql">AI00901 - 建物分層及附屬資料</option>
                <option value="txt_AI01001.sql">AI01001 - 主建物與共同使用部分資料</option>
                <option value="txt_AI01101.sql">AI01101 - 建物所有權部資料</option>
                <option value="txt_AI02901_B.sql">AI02901 - 土地各部別之其他登記事項列印</option>
                <option value="txt_AI02901_E.sql">AI02901 - 建物各部別之其他登記事項列印</option>
              </optgroup>
            </select>
            <textarea id="sql_csv_text" class="mw-100 w-100" style="height: 150px" placeholder="輸入SELECT SQL ..."></textarea>
            <button id="sql_export_button">匯出</button>
            <button id="sql_csv_quote_button">備註</button>
            <blockquote id="sql_report_blockquote" class="hide">
              <p>輸入SELECT SQL指令匯出查詢結果。</p>
              <img src="assets/img/csv_export_method.jpg" class="w-auto" />
            </blockquote>
          </fieldset>
        </div>
        <div class="col-6">
          <fieldset>
            <legend>使用者＆信差訊息</legend>
            <div class="float-clear">
              <label for="msg_who">
              　關鍵字：
              </label>
              <input type="text" id="msg_who" name="msg_who" placeholder="HB0541" value="HB054" title="ID、姓名、IP" />
              <button id="search_user_button">搜尋</button>
              <span id="filter_info" class="text-info">
                <?php
                  echo count($operators); 
                ?>筆
              </span>
            </div>
            <div>
              <label for="msg_title">訊息標題：</label>
              <input type="text" name="msg_title" id="msg_title" placeholder="訊息的標題" />
            </div>
            <div>
              <label for="msg_content">訊息內容：</label>
              <button id="msg_button" class="ld-ext-left"><span class="ld ld-ring ld-cycle loader-icon"></span>傳送訊息</button><br />
              <textarea id="msg_content" name="msg_content" class="w-100" placeholder="訊息內容(最多500字)"></textarea>
            </div>
            <div id="user_list">
            <?php
              foreach ($operators as $id => $name) {
                // prevent rare word issue
                $name = preg_replace("/[a-zA-Z?0-9+]+/", "", $name);
                echo "<div class='float-left m-2 user_tag hide' style='width: 150px' data-id='".$id."' data-name='".($name ?? "XXXXXX")."'>".$id.": ".($name ?? "XXXXXX")."</div>";
              }
            ?>
            </div>
          </fieldset>
        </div>
      </div>
      <div class="row">
        <div class="col-6">
          <!-- ld-over the loading covering container-->
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
        </div>
        <div class="col-6">
          <fieldset>
            <legend>記錄檔</legend>
            <input class="no-cache" id="log_date_text" name="log_date_text" type="text" title='輸入日期' value="<?php echo $today_ad; ?>" />
            <button id="log_button">下載</button>
            <button id="log_zip_button">壓縮</button>
            <button id="log_quote_button">備註</button>
            <blockquote id="log_blockquote" class="hide">
              <ol>
                <li>根據日期取得本伺服器之紀錄檔案。</li>
                <li>按壓縮按鈕可手動壓縮主機端LOG原始檔。</li>
              </ol>
            </blockquote>
          </fieldset>
        </div>
      </div>
      <div class="row">
        <div class="col-6">
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
        </div>
        <div class="col-6">
          <fieldset>
            <legend>地政局索取地籍資料</legend>
            <button id="export_txt_quote_button">打開說明</button>
            <blockquote id="export_txt_blockquote" class="hide">
              <span class="text-danger">※</span> 系統管理子系統/資料轉入轉出 (共14個txt檔案，地/建號範圍從 00000000 ~ 99999999) <br/>
              　- <small class="mt-2 mb-2"> 除下面標示為黃色部分須至地政系統產出並下載，其餘皆可於「報表匯出」區塊產出。</small> <br/>
              　AI001-10 <br/>
              　　AI00301 - 土地標示部 <br/>
              　　AI00401 - 土地所有權部 <br/>
              　　AI00601 - 管理者資料【土地、建物各做一次】 <br/>
              　　AI00701 - 建物標示部 <br/>
              　　AI00801 - 基地坐落 <br/>
              　　AI00901 - 建物分層及附屬 <br/>
              　　AI01001 - 主建物與共同使用部分 <br/>
              　AI011-20 <br/>
              　　AI01101 - 建物所有權部 <br/>
              　　<span class="text-warning">AI01901 - 土地各部別</span> <br/>
              　AI021-40 <br/>
              　　<span class="text-warning">AI02101 - 土地他項權利部</span> <br/>
              　　<span class="text-warning">AI02201 - 建物他項權利部</span> <br/>
              　　AI02901 - 各部別之其他登記事項【土地、建物各做一次】 <br/><br/>

              <span class="text-danger">※</span> 測量子系統/測量資料管理/資料輸出入 【請至地政系統WEB版產出】<br/>
              　地籍圖轉出(數值地籍) <br/>
              　　* 輸出DXF圖檔【含控制點】及 NEC重測輸出檔 <br/>
              　地籍圖轉出(圖解數化) <br/>
              　　* 同上兩種類皆輸出，並將【分幅管理者先接合】下選項皆勾選 <br/><br/>
                
              <span class="text-danger">※</span> 登記子系統/列印/清冊報表/土地建物地籍整理清冊【土地、建物各產一次存PDF，請至地政系統WEB版產出】 <br/>
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
  <script src="assets/js/global.js"></script>
  <script src="assets/js/xhr_query.js"></script>
  
  <script src="assets/js/cache.js"></script>
  <script src="assets/js/mark.jquery.min.js"></script>

  <script type="text/javascript">
    // place this variable in global to use this int for condition jufgement, e.g. 108
    let this_year = <?php echo $this_year; ?>;
    $(document).ready(e => {
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
      let prevVal = null;
      let total_ops = <?php echo count($operators); ?>;
      let filter_user = function(el) {
        let val = $(el).val();
        if (val == prevVal) {
          return;
        }
        // clean
        $(".user_tag").unmark(val, {
          "className": "highlight"
        }).addClass("hide");
        
        if (isEmpty(val)) {
          $(".user_tag").removeClass("hide");
          $("#filter_info").text(total_ops + "筆");
        } else {
          // Don't add 'g' because I only a line everytime.
          // If use 'g' flag regexp object will remember last found index, that will possibly case the subsequent test failure.
          val = val.replace("?", ""); // prevent out of memory
          let keyword = new RegExp(val, "i");
          $(".user_tag").each(function(idx, div) {
            if (keyword.test($(div).text())) {
              $(div).removeClass("hide");  
              // $("#msg_who").val($.trim(user_data[1]));
              $(div).mark(val, {
                "element" : "strong",
                "className": "highlight"
              });
            }
          });
          $("#filter_info").text((total_ops - $(".user_tag.hide").length) + "筆");
          if ((total_ops - $(".user_tag.hide").length) == 1) {
            let user_data = $(".user_tag:not(.hide)").text().split(":");
            $("#msg_who").val($.trim(user_data[1]));
          }
        }
        prevVal = val;
      };
      filter_user("#msg_who");

      let delayTimer = null;
      $("#msg_who").on("keyup", e => {
        clearTimeout(delayTimer);
        delayTimer = setTimeout(function() {
          filter_user(e.target);
        }, 1000);
      });

      // sql csv export
      $("#sql_export_button").on("click", e => {
        let selected = $("#preload_sql_select").val();
        selected.startsWith("txt_") ? xhrExportSQLTxt(e) : xhrExportSQLCsv(e);
      });
      $("#preload_sql_select").on("change", xhrLoadSQL);

      // log export
      $("#log_button").on("click", xhrExportLog);
      $("#log_date_text").datepicker({
        language: "zh-TW",
        daysOfWeekHighlighted: "1,2,3,4,5",
        clearBtn: true,
        todayHighlight: true,
        autoclose: true,
        format: "yyyy-mm-dd"
      });
      $("#log_zip_button").on("click", xhrZipLog);

      // SUR Case Query & Delay Case Fix
      $("#sur_delay_case_fix_code").on("change", xhrGetCaseLatestNum.bind({
        code_id: "sur_delay_case_fix_code",
        year_id: "sur_delay_case_fix_year",
        number_id: "sur_delay_case_fix_num",
        display_id: "sur_delay_case_fix_display"
      }));
      $("#sur_delay_case_fix_search_button").on("click", xhrGetSURCase);
      bindPressEnterEvent("#sur_delay_case_fix_num", xhrGetSURCase);

      // user info
      $(".user_tag").on("click", e => {
        let clicked_element = $(e.target);
        if (!clicked_element.hasClass("user_tag")) {
          console.warn("clicked element(" + clicked_element.prop("tagName") + ") doesn't have user_tag class ... find its closest parent");
          clicked_element = $(clicked_element.closest(".user_tag"));
        }
        let user_data = clicked_element.text().split(":");
        $("#msg_who").val($.trim(user_data[1]).replace(/[\?A-Za-z0-9\+]/g, ""));
        xhrQueryUserInfo(e);
      });

      // message
      $("#msg_button").on("click", xhrSendMessage);

      // search users
      $("#search_user_button").on("click", xhrSearchUsers);
      bindPressEnterEvent("#msg_who", xhrSearchUsers);
    });
  </script>
</body>
</html>
