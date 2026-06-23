<?php

class DGXLandCaseParser
{
    // ⚠️ 直連 Ollama 的 OpenAI 相容端點
    private $apiUrl  = 'http://192.168.13.195:11434/v1/chat/completions';
    private $model   = 'gemma3:latest'; 
    private $timeout = 60; 

    /**
     * 取得系統提示詞 (已移除肥大的字典對照表，減輕模型負擔)
     */
    private function getSystemPrompt()
    {
        $promptPath = __DIR__ . '/../assets/prompts/landcasenum_parser.md';
        Logger::getInstance()->info("DGXLandCaseParser: 開始讀取系統提示詞檔案 [{$promptPath}]");
        
        $content = @file_get_contents($promptPath);
        
        if ($content === false || trim($content) === '') {
            $defaultPrompt = '你是一個專門解析「地政系統案件編號」的 API 後端解析引擎。請僅以純 JSON 格式輸出解析結果。';
            Logger::getInstance()->info("DGXLandCaseParser: 讀取失敗或檔案為空，使用預設提示詞");
            return $defaultPrompt;
        }

        // 清除 BOM 與轉碼
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }

        Logger::getInstance()->info("DGXLandCaseParser: 成功讀取系統提示詞，長度為 " . mb_strlen($content) . " 字元。");
        return (string) $content;
    }

    /**
     * 獲取完整的地政代碼對照表 (由 map.md 轉換而來)
     */
    private function getRegMap()
    {
        return array(
            // 本所與轄內跨所
            'HA81' => '桃資登', 'HA82' => '桃資總', 'HA85' => '桃資速', 'HA87' => '桃資標', 'HAX2' => '桃園跨',
            'HAA1' => '桃桃登跨', 'HBA1' => '桃壢登跨', 'HFA1' => '桃德登跨', 'HCA1' => '桃溪登跨',
            'HGA1' => '桃平登跨', 'HDA1' => '桃楊登跨', 'HEA1' => '桃蘆登跨', 'HHA1' => '桃山登跨',
            'HAB1' => '壢桃登跨', 'HAG1' => '平桃登跨', 'HAE1' => '蘆桃登跨', 'HAH1' => '山桃登跨', 
            'HAF1' => '德桃登跨', 'HAD1' => '楊桃登跨', 'HAC1' => '溪桃登跨',
            
            // 台北市
            'H1AA' => '跨縣市(桃園古亭)', 'H1AB' => '跨縣市(桃園建成)', 'H1AC' => '跨縣市(桃園中山)', 'H1AD' => '跨縣市(桃園松山)', 'H1AE' => '跨縣市(桃園士林)', 'H1AF' => '跨縣市(桃園大安)',
            'A1HA' => '跨縣市(古亭桃園)', 'A2HA' => '跨縣市(建成桃園)', 'A3HA' => '跨縣市(中山桃園)', 'A4HA' => '跨縣市(松山桃園)', 'A5HA' => '跨縣市(士林桃園)', 'A6HA' => '跨縣市(大安桃園)',
            
            // 台中市
            'H1BA' => '跨縣市(桃園中山)', 'H1BB' => '跨縣市(桃園中正)', 'H1BC' => '跨縣市(桃園中興)', 'H1BD' => '跨縣市(桃園豐原)', 'H1BE' => '跨縣市(桃園大甲)', 'H1BF' => '跨縣市(桃園清水)', 'H1BG' => '跨縣市(桃園東勢)', 'H1BH' => '跨縣市(桃園雅潭)', 'H1BI' => '跨縣市(桃園大里)', 'H1BJ' => '跨縣市(桃園太平)', 'H1BK' => '跨縣市(桃園龍井)',
            'B1HA' => '跨縣市(中山桃園)', 'B2HA' => '跨縣市(中正桃園)', 'B3HA' => '跨縣市(中興桃園)', 'B4HA' => '跨縣市(豐原桃園)', 'B5HA' => '跨縣市(大甲桃園)', 'B6HA' => '跨縣市(清水桃園)', 'B7HA' => '跨縣市(東勢桃園)', 'B8HA' => '跨縣市(雅潭桃園)', 'B9HA' => '跨縣市(大里桃園)', 'BZHA' => '跨縣市(太平桃園)', 'BYHA' => '跨縣市(龍井桃園)',
            
            // 基隆市
            'H1CD' => '跨縣市(桃園基隆)', 'C3HA' => '跨縣市(基隆桃園)',
            
            // 新北市
            'H1FA' => '跨縣市(桃園板橋)', 'H1FB' => '跨縣市(桃園新莊)', 'H1FC' => '跨縣市(桃園新店)', 'H1FD' => '跨縣市(桃園汐止)', 'H1FE' => '跨縣市(桃園淡水)', 'H1FF' => '跨縣市(桃園瑞芳)', 'H1FG' => '跨縣市(桃園三重)', 'H1FH' => '跨縣市(桃園中和)', 'H1FI' => '跨縣市(桃園樹林)',
            'F1HA' => '跨縣市(板橋桃園)', 'F2HA' => '跨縣市(新莊桃園)', 'F3HA' => '跨縣市(新店桃園)', 'F4HA' => '跨縣市(汐止桃園)', 'F5HA' => '跨縣市(淡水桃園)', 'F6HA' => '跨縣市(瑞芳桃園)', 'F7HA' => '跨縣市(三重桃園)', 'F8HA' => '跨縣市(中和桃園)', 'F9HA' => '跨縣市(樹林桃園)',
            
            // 台南市
            'H1DA' => '跨縣市(桃園台南)', 'H1DB' => '跨縣市(桃園安南)', 'H1DC' => '跨縣市(桃園東南)', 'H1DD' => '跨縣市(桃園鹽水)', 'H1DE' => '跨縣市(桃園白河)', 'H1DF' => '跨縣市(桃園麻豆)', 'H1DG' => '跨縣市(桃園佳里)', 'H1DH' => '跨縣市(桃園新化)', 'H1DI' => '跨縣市(桃園歸仁)', 'H1DJ' => '跨縣市(桃園玉井)', 'H1DK' => '跨縣市(桃園永康)',
            'D1HA' => '跨縣市(台南桃園)', 'D2HA' => '跨縣市(安南桃園)', 'D3HA' => '跨縣市(東南桃園)', 'D4HA' => '跨縣市(鹽水桃園)', 'D5HA' => '跨縣市(白河桃園)', 'D6HA' => '跨縣市(麻豆桃園)', 'D7HA' => '跨縣市(佳里桃園)', 'D8HA' => '跨縣市(新化桃園)', 'D9HA' => '跨縣市(歸仁桃園)', 'DZHA' => '跨縣市(玉井桃園)', 'DYHA' => '跨縣市(永康桃園)',
            
            // 高雄市
            'H1EA' => '跨縣市(桃園鹽埕)', 'H1EB' => '跨縣市(桃園新興)', 'H1EC' => '跨縣市(桃園前鎮)', 'H1ED' => '跨縣市(桃園三民)', 'H1EE' => '跨縣市(桃園楠梓)', 'H1EF' => '跨縣市(桃園岡山)', 'H1EG' => '跨縣市(桃園鳳山)', 'H1EH' => '跨縣市(桃園旗山)', 'H1EI' => '跨縣市(桃園仁武)', 'H1EJ' => '跨縣市(桃園路竹)', 'H1EK' => '跨縣市(桃園美濃)', 'H1EL' => '跨縣市(桃園大寮)',
            'E1HA' => '跨縣市(鹽埕桃園)', 'E2HA' => '跨縣市(新興桃園)', 'E3HA' => '跨縣市(前鎮桃園)', 'E4HA' => '跨縣市(三民桃園)', 'E5HA' => '跨縣市(楠梓桃園)', 'E6HA' => '跨縣市(岡山桃園)', 'E7HA' => '跨縣市(鳳山桃園)', 'E8HA' => '跨縣市(旗山桃園)', 'E9HA' => '跨縣市(仁武桃園)', 'EZHA' => '跨縣市(路竹桃園)', 'EYHA' => '跨縣市(美濃桃園)', 'EXHA' => '跨縣市(大寮桃園)',
            
            // 宜蘭縣
            'H1GA' => '跨縣市(桃園羅東)', 'H1GB' => '跨縣市(桃園宜蘭)',
            'G1HA' => '跨縣市(羅東桃園)', 'G2HA' => '跨縣市(宜蘭桃園)',
            
            // 新竹縣/市
            'H1JB' => '跨縣市(桃園竹北)', 'H1JC' => '跨縣市(桃園竹東)', 'H1JD' => '跨縣市(桃園新湖)',
            'J1HA' => '跨縣市(竹北桃園)', 'J2HA' => '跨縣市(竹東桃園)', 'J3HA' => '跨縣市(新湖桃園)',
            'H1OA' => '跨縣市(桃園新竹市)', 'O1HA' => '跨縣市(新竹市桃園)',
            
            // 苗栗縣
            'H1KA' => '跨縣市(桃園大湖)', 'H1KB' => '跨縣市(桃園苗栗)', 'H1KC' => '跨縣市(桃園通霄)', 'H1KD' => '跨縣市(桃園竹南)', 'H1KE' => '跨縣市(桃園銅鑼)', 'H1KF' => '跨縣市(桃園頭份)',
            'K1HA' => '跨縣市(大湖桃園)', 'K2HA' => '跨縣市(苗栗桃園)', 'K3HA' => '跨縣市(通霄桃園)', 'K4HA' => '跨縣市(竹南桃園)', 'K5HA' => '跨縣市(銅鑼桃園)', 'K6HA' => '跨縣市(頭份桃園)',
            
            // 南投縣
            'H1MA' => '跨縣市(桃園南投)', 'H1MB' => '跨縣市(桃園草屯)', 'H1MC' => '跨縣市(桃園埔里)', 'H1MD' => '跨縣市(桃園竹山)', 'H1ME' => '跨縣市(桃園水里)',
            'M1HA' => '跨縣市(南投桃園)', 'M2HA' => '跨縣市(草屯桃園)', 'M3HA' => '跨縣市(埔里桃園)', 'M4HA' => '跨縣市(竹山桃園)', 'M5HA' => '跨縣市(水里桃園)',
            
            // 彰化縣
            'H1NA' => '跨縣市(桃園彰化)', 'H1NB' => '跨縣市(桃園和美)', 'H1NC' => '跨縣市(桃園鹿港)', 'H1ND' => '跨縣市(桃園員林)', 'H1NE' => '跨縣市(桃園田中)', 'H1NF' => '跨縣市(桃園北斗)', 'H1NG' => '跨縣市(桃園二林)', 'H1NH' => '跨縣市(桃園溪湖)',
            'N1HA' => '跨縣市(彰化桃園)', 'N2HA' => '跨縣市(和美桃園)', 'N3HA' => '跨縣市(鹿港桃園)', 'N4HA' => '跨縣市(員林桃園)', 'N5HA' => '跨縣市(田中桃園)', 'N6HA' => '跨縣市(北斗桃園)', 'N7HA' => '跨縣市(二林桃園)', 'N8HA' => '跨縣市(溪湖桃園)',
            
            // 雲林縣
            'H1PA' => '跨縣市(桃園斗六)', 'H1PB' => '跨縣市(桃園斗南)', 'H1PC' => '跨縣市(桃園西螺)', 'H1PD' => '跨縣市(桃園虎尾)', 'H1PE' => '跨縣市(桃園北港)', 'H1PF' => '跨縣市(桃園台西)',
            'P1HA' => '跨縣市(斗六桃園)', 'P2HA' => '跨縣市(斗南桃園)', 'P3HA' => '跨縣市(西螺桃園)', 'P4HA' => '跨縣市(虎尾桃園)', 'P5HA' => '跨縣市(北港桃園)', 'P6HA' => '跨縣市(台西桃園)',
            
            // 嘉義縣市
            'H1QB' => '跨縣市(桃園朴子)', 'H1QC' => '跨縣市(桃園大林)', 'H1QD' => '跨縣市(桃園水上)', 'H1QE' => '跨縣市(桃園竹崎)',
            'Q1HA' => '跨縣市(朴子桃園)', 'Q2HA' => '跨縣市(大林桃園)', 'Q3HA' => '跨縣市(水上桃園)', 'Q4HA' => '跨縣市(竹崎桃園)',
            'H1IA' => '跨縣市(桃園嘉義市)', 'I1HA' => '跨縣市(嘉義市桃園)',
            
            // 屏東縣
            'H1TA' => '跨縣市(桃園屏東)', 'H1TB' => '跨縣市(桃園里港)', 'H1TC' => '跨縣市(桃園潮州)', 'H1TD' => '跨縣市(桃園東港)', 'H1TE' => '跨縣市(桃園恆春)', 'H1TF' => '跨縣市(桃園枋寮)',
            'T1HA' => '跨縣市(屏東桃園)', 'T2HA' => '跨縣市(里港桃園)', 'T3HA' => '跨縣市(潮州桃園)', 'T4HA' => '跨縣市(東港桃園)', 'T5HA' => '跨縣市(恆春桃園)', 'T6HA' => '跨縣市(枋寮桃園)',
            
            // 花東及離島
            'H1UA' => '跨縣市(桃園花蓮)', 'H1UB' => '跨縣市(桃園鳳林)', 'H1UC' => '跨縣市(桃園玉里)',
            'U1HA' => '跨縣市(花蓮桃園)', 'U2HA' => '跨縣市(鳳林桃園)', 'U3HA' => '跨縣市(玉里桃園)',
            'H1VA' => '跨縣市(桃園台東)', 'H1VB' => '跨縣市(桃園成功)', 'H1VC' => '跨縣市(桃園關山)', 'H1VD' => '跨縣市(桃園太麻里)',
            'V1HA' => '跨縣市(台東桃園)', 'V2HA' => '跨縣市(成功桃園)', 'V3HA' => '跨縣市(關山桃園)', 'V4HA' => '跨縣市(太麻里桃園)',
            'H1WA' => '跨縣市(桃園金門)', 'H1XA' => '跨縣市(桃園澎湖)', 'H1ZA' => '跨縣市(桃園連江)',
            'X1HA' => '跨縣市(澎湖桃園)', 'Z1HA' => '跨縣市(連江桃園)'
        );
    }

    /**
     * ⚠️ 新增：預處理輸入，把使用者的「中文描述」直接無縫替換成「4 碼代號」
     */
    private function preprocessInput($input)
    {
        $map = $this->getRegMap();
        $reverseMap = array();
        
        // 建立反向對照表： 中文描述 -> 代碼
        foreach ($map as $code => $desc) {
            $reverseMap[$desc] = $code;
            
            // 如果是跨縣市，使用者可能只打「水上桃園」或「桃園古亭」，同樣支援翻譯
            if (preg_match('/跨縣市\((.+)\)/u', $desc, $matches)) {
                $reverseMap[$matches[1]] = $code;
            }
        }
        
        // 為了避免短詞 (例如單一的 桃園) 干擾，以陣列 Key 的長度由長到短排序
        uksort($reverseMap, function($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });

        // 執行無損替換
        $processed = strtr($input, $reverseMap);
        
        if ($input !== $processed) {
            Logger::getInstance()->info("DGXLandCaseParser: 輸入預處理 (口語轉代碼)\n原輸入: [{$input}]\n處理後: [{$processed}]");
        }
        
        return $processed;
    }

    /**
     * 透過 PHP 精確為模型解析出的代碼補上中文描述
     */
    private function enrichResults($results)
    {
        $map = $this->getRegMap();

        foreach ($results as &$item) {
            $code = strtoupper(trim(isset($item['case_word']) ? $item['case_word'] : ''));
            if (!empty($code)) {
                if (isset($map[$code])) {
                    $item['case_word_desc'] = $map[$code];
                } else {
                    $prefix = substr($code, 0, 2);
                    $item['case_word_desc'] = ($prefix === 'HA' || $prefix === 'H1') ? '未定義代碼(桃園)' : '未定義代碼';
                }
            }
        }
        return $results;
    }

    public function parse($input)
    {
        Logger::getInstance()->info("DGXLandCaseParser: 收到解析請求: [{$input}]");

        // ⚠️ 關鍵：呼叫 LLM 之前，將「跨縣市(水上桃園)」變成「Q3HA」
        $processedInput = $this->preprocessInput($input);

        $payloadData = array(
            'model'    => (string) $this->model,
            'stream'   => false,
            // 給予 Ollama 相容選項，將 Context 放大，防止截斷崩潰
            'options'  => array(
                'temperature' => 0.0,
                'num_ctx'     => 8192
            ),
            'messages' => array(
                array(
                    'role' => 'system', 
                    'content' => $this->getSystemPrompt()
                ),
                array(
                    'role' => 'user', 
                    'content' => $processedInput
                )
            ),
            'response_format' => array(
                'type' => 'json_object'
            )
        );

        $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $errMsg = 'PHP 內部 JSON 編碼失敗：' . json_last_error_msg();
            Logger::getInstance()->error("DGXLandCaseParser: {$errMsg}");
            return array('success' => false, 'error' => $errMsg);
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json'
            )
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Logger::getInstance()->error("DGXLandCaseParser: cURL 連線失敗：{$curlErr}");
            return array('success' => false, 'error' => 'cURL 連線失敗：' . $curlErr);
        }

        if ($httpCode !== 200) {
            return array(
                'success' => false, 
                'error' => 'API 回應錯誤，狀態碼：' . $httpCode, 
                'raw_response' => json_decode($response, true) ?: $response
            );
        }

        $apiResult = json_decode($response, true);
        
        if (isset($apiResult['choices'][0]['message']['content'])) {
            $modelOutput = trim($apiResult['choices'][0]['message']['content']);
            
            // 強化 JSON 擷取正則，避免被模型附加思考過程干擾
            $modelOutput = preg_replace('/^```json\s*/i', '', $modelOutput);
            $modelOutput = preg_replace('/^```\s*/i',     '', $modelOutput);
            $modelOutput = preg_replace('/```$/m',        '', $modelOutput);
            $modelOutput = trim($modelOutput);

            // 暴力擷取最外層的大括號
            $start = strpos($modelOutput, '{');
            $end   = strrpos($modelOutput, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $modelOutput = substr($modelOutput, $start, $end - $start + 1);
            }
            
            $parsedJson = json_decode($modelOutput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                
                // ✅ PHP 後端補齊查表，確保絕對精確
                if (!empty($parsedJson['results'])) {
                    $parsedJson['results'] = $this->enrichResults($parsedJson['results']);
                }

                Logger::getInstance()->info("DGXLandCaseParser: 解析成功。輸出: \n" . print_r($parsedJson, true));
                return $parsedJson;
            }
            
            Logger::getInstance()->error("DGXLandCaseParser: 無法解碼為標準 JSON: \n{$modelOutput}");
            return array('success' => false, 'error' => '模型內容無法解碼為標準 JSON', 'raw_output' => $modelOutput);
        }

        Logger::getInstance()->error("DGXLandCaseParser: 回傳結構異常: \n" . print_r($apiResult, true));
        return array('success' => false, 'error' => '回傳結構異常', 'raw' => $apiResult);
    }
}