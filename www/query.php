<?php
require_once("./include/init.php");
require_once("./include/authentication.php");
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
<link rel="preload" href="assets/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/loading.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/loading-btn.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/animate.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/awesome-font.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/bootstrap-vue.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="assets/css/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<!-- Custom styles for this template -->
<link href="assets/css/bootstrap-datepicker.standalone.min.css" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
</head>

<body>

  <section class="hide">
    <div class="container-fluid" v-cloak>
      
      <div class="row">
        <div id="case-reg-search" class="col-6">
          <case-reg-search></case-reg-search>
        </div>
        <div id="case-sur-mgt" class="col-6">
          <case-sur-mgt></case-sur-mgt>
        </div>
      </div>
      <div class="row">
        <div id="case-query-by-pid" class="col-6">
          <case-query-by-pid></case-query-by-pid>
        </div>
        <div class="col-6">
          <fieldset>
            <legend>使用者</legend>
            <div class="float-clear">
            <div class="form-row">
              <div class="input-group input-group-sm col">
                <div class="input-group-prepend">
                  <span class="input-group-text" id="inputGroup-msg_who">關鍵字</span>
                </div>
                <input type="text" id="msg_who" name="msg_who" class="form-control" placeholder="HB0541" value="HB054" title="ID、姓名、IP" />
              </div>
              <div class="filter-btn-group col">
                <button id="search_user_button" class="btn btn-sm btn-outline-primary">搜尋</button>
                <span id="filter_info" class="text-muted small"><?php echo count($operators); ?>筆</span>
              </div>
            </div>
            <div id="user_list">
            <?php
              foreach ($operators as $id => $name) {
                // prevent rare word issue
                $name = preg_replace("/[a-zA-Z?0-9+]+/", "", $name);
                echo "<div class='float-left m-2 user_tag hide' style='font-size: .875rem;' data-id='".$id."' data-name='".($name ?? "XXXXXX")."'>".$id.": ".($name ?? "XXXXXX")."</div>";
              }
            ?>
            </div>
          </fieldset>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <lah-area-search></lah-area-search>
          <lah-report></lah-report>
        </div>

        <div class="col">
          <lah-user-message-form title="訊息快遞"></lah-user-message-form>
        </div>
        
      </div>
    </div>
  </section><!-- /section -->
  
  <!-- Bootstrap core JavaScript -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  
  <script src="assets/js/vue.js"></script>
  <script src="assets/js/vuex.js"></script>
  <script src="assets/js/bootstrap-vue.min.js"></script>
  <script src="assets/js/bootstrap-vue-icons.min.js"></script>
  <script src="assets/js/axios.min.js"></script>
  <script src="assets/js/localforage.min.js"></script>
  <script src="assets/js/global.js"></script>
  <script src="assets/js/components/lah-global.js"></script>
  <script src="assets/js/components/lah-components.js"></script>
  <script src="assets/js/Chart.min.js"></script>
  <script src="assets/js/xhr_query.js"></script>
  
  <script src="assets/js/mark.jquery.min.js"></script>
  
  <script src="assets/js/components/case-input-group-ui.js"></script>
  <script src="assets/js/components/case-sur-mgt.js"></script>
  <script src="assets/js/components/case-reg-search.js"></script>
  <script src="assets/js/components/case-query-by-pid.js"></script>

  <script type="text/javascript">
    let bindPressEnterEvent = (selector, callback_func) => {
        $(selector).on("keypress", function(e) {
            var keynum = (e.keyCode ? e.keyCode : e.which);
            if (keynum == '13') {
                callback_func.call(e.target, e);
            }
        });
    }

    // place this variable in global to use this int for condition jufgement, e.g. 108
    let this_year = <?php echo $this_year; ?>;
    $(document).ready(e => {
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

      // user info
      $(".user_tag").on("click", e => {
        let clicked_element = $(e.target);
        if (!clicked_element.hasClass("user_tag")) {
          clicked_element = $(clicked_element.closest(".user_tag"));
        }
        let user_data = clicked_element.text().split(":");
        $("#msg_who").val($.trim(user_data[1]).replace(/[\?A-Za-z0-9\+]/g, ""));
        vueApp.usercard(e);
      });

      // search users
      $("#search_user_button").on("click", xhrSearchUsers);
      bindPressEnterEvent("#msg_who", xhrSearchUsers);

          // add responsive and thumbnail style to blockquote img
          $("blockquote img").addClass("img-responsive img-thumbnail");
          // control blockquote block for *_quote_button
          $("button[id*='_quote_button']").on("click", function(e) {
              let el = $(e.target);
              let quote = el.next("blockquote"); // find DIRECT next element by selector
              // fallback to get the one under fieldset 
              if (quote.length == 0) {
                  let fs = $(el.closest("fieldset"));
                  quote = fs.find("blockquote");
              }
              if (quote.length > 0) {
                  //quote.hasClass("hide") ? quote.removeClass("hide") : quote.addClass("hide");
                  showModal({
                      title: quote.data("title") + " 小幫手提示",
                      body: quote.html(),
                      size: "lg"
                  });
              }
          });
    });
  </script>
</body>
</html>
