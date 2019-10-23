$(document).ready(e => {

    // one way to initialize all popovers on a page would be to select them by their data-toggle attribute:
    $(function () {
        $('[data-toggle="popover"]').popover({
            trigger: 'focus'
        });
    });

    $("#inheritance_form").on("submit", function(e) {
        e.stopPropagation();
        return false;
    });

    $("#GEN_btn").on("click", function(e) {
        disableAllButtons(true);
        if ($("#is_luzhu_table").is(":checked")) {
            window.open("inheritance_luzhu.html?" + $("#inheritance_form").serialize(), "_blank");
        } else {
            window.open("inheritance_taoyuan.html?" + $("#inheritance_form").serialize(), "_blank");
        }
        disableAllButtons(false);
    });

    clearAll();

    /*
      被繼承人審查 相關事件設定
    */

    // 點選「繼承登記類型」的事件處理
    $("input[name='heir_reg_type']").on("click", function(e) {
        clearAllRadioBoxes();
        clearAllCheckBoxes();
        $(this).prop("checked", true);
    });
    // 點選「死亡日期」的事件處理
    $("input[name='death_period']").on("click", function(e) {
        // hide all fieldsets first
        hideFieldsetsByElement($(this).parents("fieldset.layer2"));
        clearBoxesByElement($(this).parents("fieldset.layer2").find("fieldset.layer3"));
        // show up what we want to
        var element = $(this).val() == "jp" ? $("#death_period_jp_layer3") : $("#death_period_tw_layer3");
        activateElement(element);
        // clear all checked box by this element
        clearBoxesByElement(element);
    });
    // 點選日據時期之戶主/非戶主的事件處理
    $("input[name='house_owner']").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer3"));
        clearBoxesByElement($(this).parents("fieldset.layer3").find("fieldset.layer4"));
        var element = $(this).val() == "yes" ? $("#house_owner_yes_layer4") : $("#house_owner_no_layer4");
        activateElement(element);
        // clear all checked box by this element
        clearBoxesByElement(element);
    });
    // 點選 日據時期/戶主/選項 事件處理
    $("input[name='house_owner_yes_heir_seq']").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer4"));
        clearBoxesByElement($(this).parents("fieldset.layer4").find("fieldset.layer5"));
        activateElement("#house_owner_yes_layer4");
        var element = $("#house_owner_yes_heir_seq_one_layer5");
        $(this).val() == "1" ? activateElement(element) : deactivateElement(element);
    });
    // 點選 日據時期/戶主/第四選項 事件處理
    $("input[name='house_owner_yes_heir_seq_1_children_4']").on("click", function(e) {
        activateElement("#house_owner_yes_heir_seq_one_layer5");
        //var element = $("#house_owner_yes_heir_seq_1_children_fourth_layer6");
        //$(this).val() == "4" ? activateElement(element) : deactivateElement(element);
    });
    // 點選 日據時期/非戶主/選項 事件處理
    $("input[name='house_owner_no_heir_seq']").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer4"));
        clearBoxesByElement($(this).parents("fieldset.layer4").find("fieldset.layer5"));
        activateElement("#house_owner_no_layer4");
        var element = $("#house_owner_no_heir_seq_one_layer5");
        $(this).val() == "1" ? activateElement(element) : deactivateElement(element);
    });
    // 點選 日據時期/非戶主/第四選項 事件處理
    $("input[name='house_owner_no_heir_seq_1_children_4']").on("click", function(e) {
        activateElement("#house_owner_no_heir_seq_one_layer5");
        //var element = $("#house_owner_no_heir_seq_1_children_fourth_layer6");
        //$(this).val() == "4" ? activateElement(element) : deactivateElement(element);
    });
    // 點選 光復後/民法修正[前|後] 事件處理
    $("input[name='tw_death_period']").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer3"));
        clearBoxesByElement($(this).parents("fieldset.layer3").find("fieldset.layer4"));
        activateElement("#tw_death_period_layer4");
    });
    // 點選 光復後/民法修正[前|後]/配偶 事件處理
    $("input[name='tw_death_period_heir_spouse']").on("click", function(e) {
        var element = $("#tw_death_period_heir_spouse_yes_layer5");
        $(this).prop("checked") === true ? activateElement(element) : deactivateElement(element);
    });
    // 點選 光復後/民法修正[前|後]/順位選項 事件處理
    $("input[name='tw_death_period_heir_seq']").on("click", function(e) {
        // store spouse live checked or not
        var spouse_live_yes = $("#tw_death_period_heir_spouse_live_yes").prop("checked");
        var spouse_live_no = $("#tw_death_period_heir_spouse_live_no").prop("checked");

        // 控制 配偶 區塊顯示
        var element = $("#tw_death_period_heir_spouse_yes_layer5");
        if ($("#tw_death_period_heir_spouse").prop("checked") === true) {
            activateElement(element);
            if (spouse_live_yes) {
                $("#tw_death_period_heir_spouse_live_yes").prop("checked", true);
            }
            if (spouse_live_no) {
                $("#tw_death_period_heir_spouse_live_no").prop("checked", true);
            }
        } else {
            deactivateElement(element);
        }

        hideFieldsetsByElement($(this).parents("fieldset.layer4"));
        clearBoxesByElement($(this).parents("fieldset.layer4").find("fieldset.layer5"));

        var choosed = $(this).val();
        var activate_ele = choosed == "1" ? "#tw_death_period_heir_seq_one_layer5" : "#tw_death_period_layer4";
        if ($("#tw_death_period_heir_spouse").prop("checked") === true) {
            activateElement("#tw_death_period_heir_spouse_yes_layer5");
            if (spouse_live_yes) {
                $("#tw_death_period_heir_spouse_live_yes").prop("checked", true);
            }
            if (spouse_live_no) {
                $("#tw_death_period_heir_spouse_live_no").prop("checked", true);
            }
        }
        activateElement(activate_ele);
    });
    // 點選 光復後/民法修正[前|後]/第一順位選項 事件處理
    function handleClick(e) {
        // store spouse checked or not
        var spouse_live_yes = $("#tw_death_period_heir_spouse_live_yes").prop("checked");
        var spouse_live_no = $("#tw_death_period_heir_spouse_live_no").prop("checked");

        hideFieldsetsByElement($(this).parents("fieldset.layer4"));
        clearBoxesByElement("#tw_death_period_heir_seq_1_option_third_layer6");

        // 控制 配偶 區塊顯示
        var element = $("#tw_death_period_heir_spouse_yes_layer5");
        if ($("#tw_death_period_heir_spouse").prop("checked") === true) {
            activateElement(element);
            if (spouse_live_yes) {
                $("#tw_death_period_heir_spouse_live_yes").prop("checked", true);
            }
            if (spouse_live_no) {
                $("#tw_death_period_heir_spouse_live_no").prop("checked", true);
            }
        } else {
            deactivateElement(element);
        }
        
        // always 顯示本身區塊
        activateElement("#tw_death_period_heir_seq_one_layer5");
    }
    $("input[name='tw_death_period_heir_seq_1_option1']").on("click", handleClick);
    $("input[name='tw_death_period_heir_seq_1_option2']").on("click", handleClick);
    $("input[name='tw_death_period_heir_seq_1_option3']").on("click", handleClick);
    /*
      繼承人審查 相關事件設定
    */
    // 繼承人審查項目 第一層點擊事件
    $("input.heir_method_checkbox").on("click", function(e) {
        var layer1_fieldset = $(this).parents("fieldset.layer1");
        // hide all fieldsets first
        hideFieldsetsByElement(layer1_fieldset);
        // clear all checked box under layer1
        //clearBoxesByElement(layer1_fieldset.find("fieldset.layer2"));
        $("input.heir_method_checkbox").each(function() {
            var choosed = $(this).val();
            // show up what we want to
            var element = null;
            var clear_element = null;
            switch (choosed) {
                case "abandon":
                    element = $("#heir_method_abandon_layer2");
                    clear_element = $("#heir_method_abandon_layer2");
                    break;
                case "lost":
                    element = $("#heir_method_lost_layer2");
                    clear_element = $("#heir_method_lost_layer2");
                    break;
                case "domestic":
                    element = $("#heir_method_domestic_layer2");
                    clear_element = $("#heir_method_domestic_layer2");
                    break;
                case "foreign":
                    element = $("#heir_method_foreign_layer2");
                    clear_element = $("#heir_method_foreign_layer2");
                    break;
                case "china":
                    element = $("#heir_method_china_layer2");
                    clear_element = $("#heir_method_china_layer2");
                    break;
                case "subrogation":
                case "transfer":
                    break;
                default:
                    alert("Not Supported Option! (heir_method_checkbox, " + choosed + ")");
                    break;
            }
            if ($(this).is(":checked")) {
                activateElement(element);
            } else {
                clearBoxesByElement(clear_element);
            }
        });
    });
    // 本國人第五選項 事件處理
    $("input.heir_method_domestic_opt").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer2"));
        // clear sub layers checked value
        //clearBoxesByElement($(this).parents("fieldset.layer2").find("fieldset.layer3"));
        $("input.heir_method_domestic_opt").each(function() {
            var choosed = $(this).val();
            if (choosed == "5" && $(this).is(":checked")) {
                activateElement("#heir_method_domestic_opt5_layer3");
            } else {
                activateElement("#heir_method_domestic_layer2");
                if (choosed == "5") {
                    clearBoxesByElement("#heir_method_domestic_opt5_layer3");
                }
            }
        });

    });
    // 外國人繼承子選項 事件處理
    $("input.heir_method_foreign_opt").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer2"));
        $("input.heir_method_foreign_opt").each(function() {
            var choosed = $(this).val();
            var element = null;
            switch (choosed) {
                case "1":
                    element = "#heir_method_foreign_opt1_layer3";
                    break;
                case "2":
                    element = "#heir_method_foreign_opt2_layer3";
                    break;
                case "7":
                    element = "#heir_method_foreign_opt7_layer3";
                    break;
                default:
                    element = "#heir_method_foreign_layer2";
                    break;
            }
            if ($(this).is(":checked")) {
                activateElement(element);
            } else if (element != "#heir_method_foreign_layer2") {
                clearBoxesByElement(element);
            }
        });
    });
    // 外國人第一選項 事件處理
    $("input[name='heir_method_foreign_opt']").on("click", function(e) {
        hideFieldsetsByElement($(this).parents("fieldset.layer2"));

        // clear sub layers checked value
        clearBoxesByElement($(this).parents("fieldset.layer2").find("fieldset.layer3"));

        var choosed = $(this).val();
        switch (choosed) {
            case "1":
                activateElement("#heir_method_foreign_opt1_layer3");
                break;
            case "2":
                activateElement("#heir_method_foreign_opt2_layer3");
                break;
            case "7":
                activateElement("#heir_method_foreign_opt7_layer3");
                break;
            default:
                activateElement("#heir_method_foreign_layer2");
                break;
        }
    });
	// 切換 蘆竹/桃園 應付文件顯示
	$("#is_luzhu_table").on("click", switchResultDocs);
    /*
      結果區塊相關事件處理
    */
    $("#heir_other_doc").on("click", function(e) {
        $("#heir_other_doc_text").attr("disabled", !$(this).is(":checked"));
    });
});