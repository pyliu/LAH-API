if (Vue) {
    Vue.component("case-temp-mgt", {
        template: `<fieldset>
            <legend>暫存檔查詢</legend>
            <b-row class="mb-2">
                <b-col>
                    <case-input-group-ui @update="handleUpdate" @enter="query" type="reg" prefix="case_temp"></case-input-group-ui>
                </b-col>
            </b-row>
            <b-row no-gutters>
                <b-col>
                    <b-button block pill @click="query" variant="outline-primary" size="sm"><i class="fas fa-search"></i> 查詢</b-button>
                </b-col>
                &ensp;
                <b-col>
                    <b-button block pill @click="popup" variant="outline-success" size="sm"><i class="far fa-comment"></i> 備註</b-button>
                </b-col>
            </b-row>
        </fieldset>`,
        data: () => {
            return {
                year: "109",
                code: "HB04",
                num: "000010"
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
                    addNotification({
                        title: "清除暫存檔",
                        message: `輸入資料格式有誤，無法查詢 ${data.year}-${data.code}-${data.num}`,
                        type: "warning"
                    });
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
                asyncFetch("query_json_api.php", {
                    body: form_body
                }).then(jsonObj => {

                    toggle(e.target);

                    console.assert(jsonObj.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `查詢暫存資料回傳狀態碼有問題【${jsonObj.status}】`);
                    
                    // jsonObj.raw structure: 0 - Table, 1 - all raw data, 2 - SQL
                    let filtered = jsonObj.raw.filter(function(item, index, array) {
                        return item[1].length > 0;
                    });

                    if (filtered.length == 0) {
                        addNotification({
                            title: "清除暫存檔",
                            message: "案件 " + year + "-" + code + "-" + number + " 查無暫存資料",
                            type: "warning"
                        });
                        return;
                    }

                    let html = "";
                    filtered.forEach(function(item, i, array) {
                        html += `<button type="button" class="btn btn-sm btn-primary active tmp_tbl_btn" data-sql-id="sql_${i}" data-tbl="${item[0]}">
                                    ${item[0]} <span class="badge badge-light">${item[1].length} <span class="sr-only">暫存檔數量</span></span>
                                </button>`;
                        // use saveAs to download backup SQL file
                        if (saveAs) {
                            let filename_prefix = `${year}-${code}-${number}`;
                            // Prepare INS SQL text for BACKUP
                            let INS_SQL = "";
                            for (let y = 0; y < item[1].length; y++) {
                                let this_row = item[1][y];
                                let fields = [];
                                let values = [];
                                for (let key in this_row) {
                                    fields.push(key);
                                    values.push(isEmpty(this_row[key]) ? "null" : `'${this_row[key]}'`);
                                }
                                INS_SQL += `insert into ${item[0]} (${fields.join(",")})`;
                                INS_SQL += ` values (${values.join(",")});\n`;
                            }
                            html += "<small>"
                                 + `　<button id='backup_temp_btn_${i}' data-clean-btn-id='clean_temp_btn_${i}' data-filename='${filename_prefix}-${item[0]}' class='backup_tbl_temp_data btn btn-sm btn-outline-primary'>備份</button>`
                                 + `<span class='hide ins_sql' id="sql_${i}">${INS_SQL}</span> `
                                 + ` <button id='clean_temp_btn_${i}' data-tbl='${item[0]}' data-backup-btn-id='backup_temp_btn_${i}' class='clean_tbl_temp_data btn btn-sm btn-outline-danger' ${item[0] == "MOICAT.RINDX" ? "disabled": ""}>清除</button>`
                                 + "</small>";
                        }
                        html += `<br />&emsp;<small>－&emsp;${item[2]}</small> <br />`;
                    });
                    
                    html += `
                        <button id='temp_backup_button' data-clean-btn-id='temp_clean_button' class='mt-2 btn btn-sm btn-outline-primary' data-trigger='manual' data-toggle='popover' data-placement='bottom'>全部備份</button>
                        <button id='temp_clean_button' data-backup-btn-id='temp_backup_button' class='mt-2 btn btn-sm btn-outline-danger' id='temp_clr_button'>全部清除</button>
                    `;
                    
                    showModal({
                        body: html,
                        title: year + "-" + code + "-" + number + " 案件暫存檔統計",
                        size: "lg",
                        callback: () => {
                            showPopper("#temp_backup_button", "請「備份後」再選擇清除", 5000);
                            
                            $("#temp_clean_button").off("click").on("click", e => that.fix({
                                year: year,
                                code: code,
                                number: number,
                                table: "",
                                target: e.target,
                                clean_all: true
                            }));
            
                            $("#temp_backup_button").off("click").on("click", (e) => {
                                toggle(e.target);
                                let filename = year + "-" + code + "-" + number + "-TEMP-DATA";
                                let clean_btn_id = $(e.target).data("clean-btn-id");
                                // attach clicked flag to the clean button
                                $(`#${clean_btn_id}`).data("backup_flag", true);
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
                                let clean_btn_id = $(e.target).data("clean-btn-id");
                                // attach clicked flag to the clean button
                                $(`#${clean_btn_id}`).data("backup_flag", true);
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
                            $(".clean_tbl_temp_data").off("click").on("click", e => that.fix({
                                    year: year,
                                    code: code,
                                    number: number,
                                    table: $(e.target).data("tbl"),
                                    target: e.target,
                                    clean_all: false
                                })
                            );
                            $(".tmp_tbl_btn").off("click").on("click", that.showSQL);
                        }
                    });
                }).catch(ex => {
                    console.error("case-temp-mgt::query parsing failed", ex);
                    showAlert({ message: "case-temp-mgt::query XHR連線查詢有問題!!【" + ex + "】", type: "danger" });
                });
            },
            showSQL: function(e) {
                let btn = $(e.target);
                let sql_span = $(`#${btn.data("sql-id")}`);
                showModal({
                    title: `INSERT SQL of ${btn.data("tbl")}`,
                    message: sql_span.html().replace(/\n/g, "<br /><br />"),
                    size: "xl"
                });
            },
            fix: function(data) {
                let backup_flag = $(data.target).data("backup_flag");
                if (backup_flag !== true) {
                    addNotification({
                        title: "清除暫存檔",
                        subtitle: `${data.year}-${data.code}-${data.number}`,
                        message: "清除前請先備份!",
                        type: "warning"
                    });
                    addAnimatedCSS(`#${$(data.target).data("backup-btn-id")}`, { name: "tada" });
                    return;
                }
                let msg = "<h6><strong class='text-danger'>★警告★</strong>：無法復原請先備份!!</h6>清除案件 " + data.year + "-" + data.code + "-" + data.number + (data.clean_all ? " 全部暫存檔?" : " " + data.table + " 表格的暫存檔?");
                showConfirm(msg, () => {
                    $(data.target).remove();
                    let form_body = new FormData();
                    form_body.append("type", "clear_temp_data");
                    form_body.append("year", data.year);
                    form_body.append("code", data.code);
                    form_body.append("number", data.number);
                    form_body.append("table", data.table);
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
                            title: "清除暫存檔",
                            message: "已清除完成。<p>" + data.year + "-" + data.code + "-" + data.number + (data.table ? " 表格：" + data.table : "") + "</p>",
                            type: "success"
                        });
                        if (data.clean_all) {
                            closeModal();
                        }
                    }).catch(ex => {
                        console.error("case-temp-mgt::fix parsing failed", ex);
                        showAlert({
                            message: "case-temp-mgt::fix XHR連線查詢有問題!!【" + ex + "】",
                            type: "danger"
                        });
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
