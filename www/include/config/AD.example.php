<?php
/**
 * AD 連線設定檔
 * 建議將此檔案加入 .gitignore，並建立一個 AD.example.php
 */
return [
    'AD_HOST'        => 'XXX.XXX.XXX.XXX',
    'AD_PORT'        => 389, // 一般 LDAP: 389, LDAPS: 636
    
    // Base DN: 搜尋的起始節點
    'BASE_DN'        => 'DC=example,DC=com', // 請修改為實際網域
    
    // 具有讀取權限的帳號 (Bind DN 或 user@domain)
    'QUERY_USER'     => 'reader@example.com', 
    
    // 密碼
    'QUERY_PASSWORD' => 'your_secure_password',
];