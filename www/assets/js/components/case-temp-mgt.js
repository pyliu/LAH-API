if (Vue) {
    Vue.component("case-temp-mgt", {
        template: `<fieldset>
            <legend>案件暫存檔清除</legend>
            <div class="form-row">
            <div class="col-9">
                <case-input-group-ui @update="handleUpdate" @enter="query" type="reg"></case-input-group-ui>
            </div>
            <div class="filter-btn-group col-3">
                <button @click="query" class="btn btn-sm btn-outline-primary">查詢</button>
                <button @click="popup" class="btn btn-sm btn-outline-success">備註</button>
            </div>
            </div>
        </fieldset>`,
        data: () => {
            return {
                year: "108",
                code: "HB04",
                num: "000010",
                dialog: null
            }
        },
        methods: {
            handleUpdate: function(e, data) {
                this.year = data.year;
                this.code = data.code;
                this.num = data.num;
            },
            query: function(e) {
                let data = {year: this.year, code: this.code, num: this.num};
                if (!checkCaseUIData(data)) {
                    addNotification({message: `輸入資料格式有誤，無法查詢 ${data.year}-${data.code}-${data.num}`, type: "warning"});
                    return false;
                }
            
                let year = data.year.replace(/\D/g, "");
                let code = data.code;
                let number = data.num.replace(/\D/g, "");
                let that = this;

                toggle(e.target);
            
                let form_body = new FormData();
                form_body.append("type", "query_temp_data");
                form_body.append("year", year);
                form_body.append("code", code);
                form_body.append("number", number);
                fetch("query_json_api.php", {
                    method: 'POST',
                    body: form_body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
                }).then(jsonObj => {

                    toggle(e.target);

                    console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "查詢暫存資料回傳狀態碼有問題【" + jsonObj.status + "】");
                    
                    let html = "";
                    // jsonObj.raw structure: 0 - Table, 1 - all raw data, 2 - SQL
                    for (let i = 0; i < jsonObj.data_count; i++) {
                        // check if there is no temp data in the array
                        if(jsonObj.raw[i][1].length == 0) {
                            continue;
                        }
                        html += "● " + jsonObj.raw[i][0] + ": <span class='text-danger'>" + jsonObj.raw[i][1].length + "</span> "
                        // use saveAs to download backup SQL file
                        if (saveAs) {
                            let filename_prefix = year + "-" + code + "-" + number;
                            // Prepare INS SQL text for BACKUP
                            let INS_SQL = "";
                            for (let y = 0; y < jsonObj.raw[i][1].length; y++) {
                                let this_row = jsonObj.raw[i][1][y];
                                let fields = [];
                                let values = [];
                                for (let key in this_row) {
                                    fields.push(key);
                                    values.push(isEmpty(this_row[key]) ? "null" : "'" + this_row[key] + "'");
                                }
                                INS_SQL += "insert into " + jsonObj.raw[i][0] + " (" + fields.join(",") + ")";
                                INS_SQL += " values (" + values.join(",") + ");\n";
                            }
                            html += "　<small><button data-filename='" + filename_prefix + "-" + jsonObj.raw[i][0] + "' class='backup_tbl_temp_data'>備份</button>"
                                    + "<span class='hide ins_sql'>" + INS_SQL + "</span> "
                                    + " <button data-tbl='" + jsonObj.raw[i][0] + "' class='clean_tbl_temp_data'>清除</button></small>";
                        }
                        html += "<br />　<small>－　" + jsonObj.raw[i][2] + "</small> <br />";
                    }
            
                    
                    if (isEmpty(html)) {
                        addNotification({
                            message: "案件 " + year + "-" + code + "-" + number + " 查無暫存資料",
                            type: "warning"
                        });
                        return;
                    }

                    html += "<button id='temp_backup_button' class='mt-2' data-trigger='manual' data-toggle='popover' data-placement='bottom'>全部備份</button> <button class='mt-2' id='temp_clr_button'>全部清除</button>";
                    
                    showModal({
                        body: html,
                        title: year + "-" + code + "-" + number + " 案件暫存檔統計",
                        size: "lg",
                        callback: () => {
                            $("#temp_clr_button").off("click").on("click", that.fix.bind({
                                year: year,
                                code: code,
                                number: number,
                                table: ""
                            }));
            
                            showPopper("#temp_backup_button", "請「備份後」再選擇清除", 5000);
                            
                            $("#temp_backup_button").off("click").on("click", (e) => {
                                toggle(e.target);
                                let filename = year + "-" + code + "-" + number + "-TEMP-DATA";
                                // any kind of extension (.txt,.cpp,.cs,.bat)
                                filename += ".sql";
                                let all_content = "";
                                $(".ins_sql").each((index, hidden_span) => {
                                    all_content += $(hidden_span).text();
                                });
                                let blob = new Blob([all_content], {
                                    type: "text/plain;charset=utf-8"
                                });
                                saveAs(blob, filename);
                                $(e.target).remove();
                            });
                            // attach backup event to the buttons
                            $(".backup_tbl_temp_data").off("click").on("click", e => {
                                let filename = $(e.target).data("filename");
                                // any kind of extension (.txt,.cpp,.cs,.bat)
                                filename += ".sql";
                                let hidden_data = $(e.target).next("span"); // find DIRECT next span of the clicked button
                                let content = hidden_data.text();
                                let blob = new Blob([content], {
                                    type: "text/plain;charset=utf-8"
                                });
                                saveAs(blob, filename);
                            });
                            // attach clean event to the buttons
                            $(".clean_tbl_temp_data").off("click").on("click", e => {
                                let table_name = $(e.target).data("tbl");
                                that.fix.call({
                                    year: year,
                                    code: code,
                                    number: number,
                                    table: table_name
                                }, e);
                            });
                        }
                    });
                }).catch(ex => {
                    console.error("case-temp-mgt::query parsing failed", ex);
                    showAlert({ message: "XHR連線查詢有問題!!【" + ex + "】", type: "danger" });
                });
            },
            fix: function(e) {
                let bindArgsObj = this;

                let msg = "確定要清除案件 " + bindArgsObj.year + "-" + bindArgsObj.code + "-" + bindArgsObj.number + " 全部暫存檔?\n ★ 警告：無法復原，除非你有備份!!";
                if (!isEmpty(bindArgsObj.table)) {
                    msg = "確定要清除案件 " + bindArgsObj.year + "-" + bindArgsObj.code + "-" + bindArgsObj.number + " " + bindArgsObj.table + " 表格的暫存檔?\n ★ 警告：無法復原，除非你有備份!!";
                }

                if(!confirm(msg)) {
                    return;
                }

                $(e.target).remove();

                let form_body = new FormData();
                form_body.append("type", "clear_temp_data");
                form_body.append("year", bindArgsObj.year);
                form_body.append("code", bindArgsObj.code);
                form_body.append("number", bindArgsObj.number);
                form_body.append("table", bindArgsObj.table);
                fetch("query_json_api.php", {
                    method: 'POST',
                    body: form_body
                }).then(response => {
                    if (response.status != 200) {
                        throw new Error("XHR連線異常，回應非200");
                    }
                    return response.json();
                }).then(jsonObj => {
                    console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, "清除暫存資料回傳狀態碼有問題【" + jsonObj.status + "】");
                    addNotification({
                        message: "暫存檔已清除完成。<p>" + bindArgsObj.year + "-" + bindArgsObj.code + "-" + bindArgsObj.number + (bindArgsObj.table ? " 表格：" + bindArgsObj.table : "") + "</p>",
                    });
                    if (!bindArgsObj.table) {
                        // means click clean all button
                        closeModal();
                    }
                }).catch(ex => {
                    console.error("case-temp-mgt::fix parsing failed", ex);
                    showAlert({
                        message: "case-temp-mgt::fix XHR連線查詢有問題!!【" + ex + "】",
                        type: "danger"
                    });
                });
            },
            popup: () => {
                showModal({
                    title: "案件暫存檔清除 小幫手提示",
                    body: `<h6 class="text-info">檢查下列的表格</h6>
                    <ul>
                      <!-- // 登記 -->
                      <li>"MOICAT.RALID" => "A"   // 土地標示部</li>
                      <li>"MOICAT.RBLOW" => "B"   // 土地所有權部</li>
                      <li>"MOICAT.RCLOR" => "C"   // 他項權利部</li>
                      <li>"MOICAT.RDBID" => "D"   // 建物標示部</li>
                      <li>"MOICAT.REBOW" => "E"   // 建物所有權部</li>
                      <li>"MOICAT.RLNID" => "L"   // 人檔</li>
                      <li>"MOICAT.RRLSQ" => "R"   // 權利標的</li>
                      <li>"MOICAT.RGALL" => "G"   // 其他登記事項</li>
                      <li>"MOICAT.RMNGR" => "M"   // 管理者</li>
                      <li>"MOICAT.RTOGH" => "T"   // 他項權利檔</li>
                      <li>"MOICAT.RHD10" => "H"   // 基地坐落／地上建物</li>
                      <li class="text-danger">"MOICAT.RINDX" => "II"  // 案件異動索引【不會清除】</li>
                      <li>"MOICAT.RINXD" => "ID"</li>
                      <li>"MOICAT.RINXR" => "IR"</li>
                      <li>"MOICAT.RINXR_EN" => "IRE"</li>
                      <li>"MOICAT.RJD14" => "J"</li>
                      <li>"MOICAT.ROD31" => "O"</li>
                      <li>"MOICAT.RPARK" => "P"</li>
                      <li>"MOICAT.RPRCE" => "PB"</li>
                      <li>"MOICAT.RSCNR" => "SR"</li>
                      <li>"MOICAT.RSCNR_EN" => "SRE"</li>
                      <li>"MOICAT.RVBLOW" => "VB"</li>
                      <li>"MOICAT.RVCLOR" => "VC"</li>
                      <li>"MOICAT.RVGALL" => "VG"</li>
                      <li>"MOICAT.RVMNGR" => "VM"</li>
                      <li>"MOICAT.RVPON" => "VP"  // 重測/重劃暫存</li>
                      <li>"MOICAT.RVRLSQ" => "VR"</li>
                      <li>"MOICAT.RXIDD04" => "ID"</li>
                      <li>"MOICAT.RXLND" => "XL"</li>
                      <li>"MOICAT.RXPRI" => "XP"</li>
                      <li>"MOICAT.RXSEQ" => "XS"</li>
                      <li>"MOICAT.B2104" => "BR"</li>
                      <li>"MOICAT.B2118" => "BR"</li>
                      <li>"MOICAT.BGALL" => "G"</li>
                      <li>"MOICAT.BHD10" => "H"</li>
                      <li>"MOICAT.BJD14" => "J"</li>
                      <li>"MOICAT.BMNGR" => "M"</li>
                      <li>"MOICAT.BOD31" => "O"</li>
                      <li>"MOICAT.BPARK" => "P"</li>
                      <li>"MOICAT.BRA26" => "C"</li>
                      <li>"MOICAT.BRLSQ" => "R"</li>
                      <li>"MOICAT.BXPRI" => "XP"</li>
                      <li>"MOICAT.DGALL" => "G"</li>
                      <!-- // 地價 -->
                      <li>"MOIPRT.PPRCE" => "MA"</li>
                      <li>"MOIPRT.PGALL" => "GG"</li>
                      <li>"MOIPRT.PBLOW" => "LA"</li>
                      <li>"MOIPRT.PALID" => "KA"</li>
                      <li>"MOIPRT.PNLPO" => "NA"</li>
                      <li>"MOIPRT.PBLNV" => "BA"</li>
                      <li>"MOIPRT.PCLPR" => "CA"</li>
                      <li>"MOIPRT.PFOLP" => "FA"</li>
                      <li>"MOIPRT.PGOBP" => "GA"</li>
                      <li>"MOIPRT.PAPRC" => "AA"</li>
                      <li>"MOIPRT.PEOPR" => "EA"</li>
                      <li>"MOIPRT.POA11" => "OA"</li>
                      <li>"MOIPRT.PGOBPN" => "GA"</li>
                      <!--<li>"MOIPRC.PKCLS" => "KK"</li>-->
                      <li>"MOIPRT.PPRCE" => "MA"</li>
                      <li>"MOIPRT.P76SCRN" => "SS"</li>
                      <li>"MOIPRT.P21T01" => "TA"</li>
                      <li>"MOIPRT.P76ALID" => "AS"</li>
                      <li>"MOIPRT.P76BLOW" => "BS"</li>
                      <li>"MOIPRT.P76CRED" => "BS"</li>
                      <li>"MOIPRT.P76INDX" => "II"</li>
                      <li>"MOIPRT.P76PRCE" => "UP"</li>
                      <li>"MOIPRT.P76SCRN" => "SS"</li>
                      <li>"MOIPRT.PAE0301" => "MA"</li>
                      <li>"MOIPRT.PB010" => "TP"</li>
                      <li>"MOIPRT.PB014" => "TB"</li>
                      <li>"MOIPRT.PB015" => "TB"</li>
                      <li>"MOIPRT.PB016" => "TB"</li>
                      <li>"MOIPRT.PHIND" => "II"</li>
                      <li>"MOIPRT.PNLPO" => "NA"</li>
                      <li>"MOIPRT.POA11" => "OA"</li>
                    </ul>`,
                    size: "lg"
                });
            }
        }
    });
} else {
    console.error("vue.js not ready ... case-temp-mgt component can not be loaded.");
}
