$(document).ready(function(e) {
    $("#preview").on("click", function(e) {
        var current_opts = $("#inheritance_form").serializeArray();
        var json = {};
        $.each(current_opts, function() {
            json[$.trim(this.name)] = $.trim(this.value);
        });
        console.log(json);
    });
    /*
    $("#preview").on("click", function(e) {

        var current_opts = $("#inheritance_form").serializeArray();
        var json = {};
        $.each(current_opts, function() {
            json[$.trim(this.name)] = $.trim(this.value);
        });
        console.log(json);
        var html = "";
        var tree_data = [];
        // #0 收件資訊
        if (!isEmpty(json["serial"]) && !isEmpty(json["heir"])) {
            tree_data.push({
                text: "#0 收件資訊",
                nodes: [{
                    text: "收件年期字號："+ json["serial"]
                }, {
                    text: "被繼承人姓名：" + json["heir"]
                }]
            });
        }

        if (!isEmpty(json["heir_reg_type"])) {
            var text = "";
            switch (json["heir_reg_type"]) {
                case "type_0_law_heir":
                    text += "法定繼承";
                    break;
                case "type_1_split_heir":
                    text += "分割繼承";
                    break;
                case "type_2_share_heir":
                    text += "共同共有繼承";
                    break;
                case "type_3_modify":
                    text += "名義更正";
                    break;
                case "type_4_will_heir":
                    text += "遺囑繼承";
                    break;
                case "type_5_judge_heir":
                    text += "判決繼承";
                    break;
                case "type_6_nobody_heir":
                    text += "無人承認繼承";
                    break;    
            }
            tree_data.push({
                text: "#1 繼承登記類型",
                nodes: [{
                    text: text
                }]
            });
        }

        if (!isEmpty(json["death_period"])) {
            function getNodes(var_name) {
                var nodes = [];
                if (var_name == "death_period") {
                    if (!isEmpty(json["house_owner"])) {
                        if (json["house_owner"] == "yes") {
                            nodes.push({
                                text: "戶主",
                                nodes: getNodes("house_owner")
                            });
                        } else {
                            nodes.push({
                                text: "非戶主",
                                nodes: getNodes("house_owner")
                            });
                        }
                    }
                }
                return nodes;
            }
            tree_data.push({
                text: "#2 被繼承人審查項目",
                nodes: [{
                    text: json["death_period"] == "jp" ? "日據時期(民國34年10月24日前死亡)" : "光復後(民國34年10月25日後死亡)",
                    nodes: getNodes("death_period")
                }]
            });
        }

        $("#tree").treeview({data: tree_data});

    });
    */
});