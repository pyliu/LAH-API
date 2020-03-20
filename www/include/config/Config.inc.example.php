<?php
const SYSTEM_CONFIG = array(
    // Only allow adm IP to access
    "ADM_IPS" => array(
        "xxx.xxx.xxx.xxx",
        "::1"
    ),
    "USER_PHOTO_FOLDER" => '\\\\xxx.xxx.xxx.xxx\\Pho\\',
    "MOCK_MODE" => false,
    // bureau
    "ORA_DB_L3HWEB" => "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=xxx.xxx.xxx.xxx)(PORT=xxxx)))(CONNECT_DATA=(SERVICE_NAME=XXXXX)))",
    // test svr
    "ORA_DB_TWEB" => "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=xxx.xxx.xxx.xxx)(PORT=xxxx)))(CONNECT_DATA=(SERVICE_NAME=XXXXX)))",
    // main db
    "ORA_DB_MAIN" => "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=xxx.xxx.xxx.xxx)(PORT=xxxx)))(CONNECT_DATA=(SERVICE_NAME=XXXXX)))",
    "ORA_DB_USER" => "xxxxxx",
    "ORA_DB_PASS" => "xxxxxx",
    // for message
    "MS_DB_SVR" => "xxx.xxx.xxx.xxx",
    "MS_DB_DATABASE" => "xxxxxx",
    "MS_DB_UID" => "xxxxxx",
    "MS_DB_PWD" => "xxxxxx",
    "MS_DB_CHARSET" => "xxxxxx",
    // for internal user data
    "MS_TDOC_DB_SVR" => "xxx.xxx.xxx.xxx",
    "MS_TDOC_DB_DATABASE" => "xxxxxx",
    "MS_TDOC_DB_UID" => "xxxxxx",
    "MS_TDOC_DB_PWD" => "xxxxxx",
    "MS_TDOC_DB_CHARSET" => "xxxxxx"
);
?>
