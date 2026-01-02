<?php
/**
 * AD 連線設定檔
 * 複製此檔案修改必要內容並改名為 AD.php 以達成 AD 相關服務操作
 */
return [
    'AD_HOST'        => 'XXX.XXX.XXX.XXX',
    'AD_PORT'        => 389, // 一般 LDAP: 389, LDAPS: 636
    
    // Base DN: 搜尋的起始節點
    'BASE_DN'        => 'DC=example,DC=com', // 請修改為實際網域
    
    // 具有管理權限的帳號 (Bind DN 或 user@domain)
    'QUERY_USER'     => 'reader@example.com', 
    
    // 密碼
    'QUERY_PASSWORD' => 'your_secure_password',
];