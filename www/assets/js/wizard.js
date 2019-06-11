var wizard_step = 0;
$(document).ready(function(e) {
    // step 0 ... start
    activateElement("#layer1_input_case");
    $("#no0_btn_next").on("click", function(e) {
        if (isEmpty($("#serial").val())) {
            $("#serial").focus();
            return;
        }
        if (isEmpty($("#heir").val())) {
            $("#heir").focus();
            return;
        }
        // hide step0 panel
        deactivateElement("#layer1_input_case");
        deactivateElement("#no0_btn_grp");
        // bring up step1 panel
        activateElement("#no1_btn_grp");
        activateElement("#layer1_select_type");
    });

    // step 1 buttons events
    $("#no1_btn_prev").on("click", function(e) {
        // bring up step0 panel
        activateElement("#no0_btn_grp");
        activateElement("#layer1_input_case");
        // hide step1 panel
        deactivateElement("#no1_btn_grp");
        deactivateElement("#layer1_select_type");
    });
    $("#no1_btn_next").on("click", function(e) {
        // collapse step 2 panel
        hideFieldsetsByElement("#layer1_target_check_items");
        // check if user selected a option
        if ($("input:radio[name=heir_reg_type]").is(":checked") === false) {
            showPopper('#heir_reg_type_legend');
            activateElement("#layer1_select_type");
            scrollToElement("#layer1_select_type");
            return;
        }

        // hide step1 panel
        deactivateElement("#no1_btn_grp");
        deactivateElement("#layer1_select_type");
        // bring up step2 panel
        activateElement("#no2_btn_grp");
        activateElement("#layer1_target_check_items");
        // focus on the initial fieldset
        activateElement("#layer1_target_check_items .layer2");
    });

    // step 2 buttons events
    $("#no2_btn_prev").on("click", function(e) {
        // bring up step1 panel
        activateElement("#no1_btn_grp");
        activateElement("#layer1_select_type");
        // hide step2 panel
        deactivateElement("#no2_btn_grp");
        deactivateElement("#layer1_target_check_items");
    });
    $("#no2_btn_next").on("click", function(e) {
        
        // 檢查死亡日期是否勾選
        if ($("input:radio[name=death_period]").is(":checked") === false) {
            showPopper("#death_period_legend");
            return;
        }
        // 已勾選日據時期死亡
        if ($("input:radio[id=jp]").is(":checked") === true) {
            // 檢查是否勾選被繼承人身分
            if ($("input:radio[name=house_owner]").is(":checked") === false) {
                showPopper("#house_owner_legend");
                return;
            } else {
                if ($("input:radio[id=house_owner_yes]").is(":checked") === true) {
                    // 已勾選戶主
                    if ($("input:radio[name=house_owner_yes_heir_seq]").is(":checked") === false) {
                        showPopper("#house_owner_yes_heir_seq_legend");
                        return;
                    }
                } else {
                    // 已勾選非戶主
                    if ($("input:radio[name=house_owner_no_heir_seq]").is(":checked") === false) {
                        showPopper("#house_owner_no_heir_seq_legend");
                        return;
                    }
                }
            }

        }
        // 已勾選光復後死亡，檢查是否勾選確切時間
        if ($("input:radio[id=tw]").is(":checked") === true) {
            if ($("input:radio[name=tw_death_period]").is(":checked") === false) {
                showPopper("#tw_death_period_legend");
                return;
            }
                       
            // legend tw_death_period_heir_spouse_live_legend
            if ($("input:checkbox[name=tw_death_period_heir_spouse]").is(":checked") === true) {
                if ($("input:radio[name=tw_death_period_heir_spouse_live]").is(":checked") === false) {
                    showPopper("#tw_death_period_heir_spouse_live_legend");
                    return;
                }
            } else if ($("input:radio[name=tw_death_period_heir_seq]").is(":checked") === false) {
                // tw_death_period_heir_seq, 沒選擇配偶就要檢查各項option
                showPopper("#tw_death_period_heir_seq_legend");
                return;
            }
        }

        // hide step2 panel
        deactivateElement("#no2_btn_grp");
        deactivateElement("#layer1_target_check_items");
        // bring up step3 panel
        activateElement("#no3_btn_grp");
        activateElement("#layer1_heir_check_items");
    });

    // step 3 buttons events
    $("#no3_btn_prev").on("click", function(e) {
        // bring up step2 panel
        activateElement("#no2_btn_grp");
        activateElement("#layer1_target_check_items");
        // hide step3 panel
        deactivateElement("#no3_btn_grp");
        deactivateElement("#layer1_heir_check_items");
    });
    $("#no3_btn_next").on("click", function(e) {
        
        // #3 繼承人審查項目 全無勾選檢查
        if ($("input:checkbox[class=heir_method_checkbox]").is(":checked") === false) {
            showPopper("#heir_method_legend");
            return;
        }

        // 繼承權拋棄 檢查
        if ($("input:checkbox[name=heir_method_abandon]").is(":checked") === true &&
            $("input:radio[name=heir_method_abandon_yn]").is(":checked") === false
        ) {
            showPopper("#heir_method_abandon_yn_legend");
            return;
        }

        // 繼承權喪失 檢查
        if ($("input:checkbox[name=heir_method_lost]").is(":checked") === true &&
            $("input:radio[name=heir_method_lost_yn]").is(":checked") === false
        ) {
            showPopper("#heir_method_lost_yn_legend");
            return;
        }

        // 平等互惠(土地法第18條) 檢查
        if ($("input:checkbox[name=heir_method_foreign_opt1]").is(":checked") === true &&
            $("input:radio[name=heir_method_foreign_opt1_yn]").is(":checked") === false
        ) {
            showPopper("#heir_method_foreign_opt1_yn_legend");
            return;
        }

        // 繼承土地法第17條第1項土地 檢查
        if ($("input:checkbox[name=heir_method_foreign_opt2]").is(":checked") === true &&
            $("input:radio[name=heir_method_foreign_opt2_yn]").is(":checked") === false
        ) {
            showPopper("#heir_method_foreign_opt2_yn_legend");
            return;
        }

        // hide step3 panel
        deactivateElement("#no3_btn_grp");
        deactivateElement("#layer1_heir_check_items");
        // bring up step4 panel
        activateElement("#no4_btn_grp");
        activateElement("#layer1_result");
		// switch docs display
		switchResultDocs();
    });

    // step 4 buttons events
    $("#no4_btn_prev").on("click", function(e) {
        // bring up step3 panel
        activateElement("#no3_btn_grp");
        activateElement("#layer1_heir_check_items");
        // hide step4 panel
        deactivateElement("#no4_btn_grp");
        deactivateElement("#layer1_result");
    });
});