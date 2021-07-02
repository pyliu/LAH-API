● 全部皆是 x64 版本

● 假設 Apache24 安裝於 D:\DEV\Apache24

● 假設 PHP7.4 安裝於 D:\DEV\php7

● 假設 ORACLE instantclient 安裝於 D:\DEV\instantclient_11_2

● 務必增加下面路徑去系統的 PATH 環境變數

    1. D:\DEV\Apache24\bin
    2. D:\DEV\php7
    3. D:\DEV\instantclient_11_2

● 修改 D:\DEV\php7\php.ini

    1. 設定 extension_dir = "D:/DEV/php7/ext"
    2. 開啟下面幾個 extension

		extension=curl
		extension=fileinfo
		extension=gd2
		extension=intl
		extension=mbstring
		extension=mysqli
		extension=openssl
		extension=pdo_mysql
		extension=pdo_sqlite
		extension=sockets
		extension=sqlite3
		extension=xmlrpc
		extension=xsl
		; For Oracle9i (自己加)
		extension=php_oci8_11g
		; For MSSQL  (自己加)
		extension=php_sqlsrv_73_ts
		
    3. 複製 PHP7.4 x64 thread safe 版本之 php_oci8_11g.dll 及 php_sqlsrv_74_ts.dll 到 D:/DEV/php7/ext 下

● 修改 D:\DEV\Apache24\conf\httpd.conf

> 最上面加入，下面五行設定值以啟動PHP

    AddHandler application/x-httpd-php .php
    AddType application/x-httpd-php .php .html
    AddType application/x-httpd-php-source .phps
    LoadModule php7_module "d:/DEV/php7/php7apache2_4.dll"
    PHPIniDir "d:/DEV/php7"

> ServerRoot "d:/DEV/Apache24" # 改成安裝的目錄
> 移除下面這一行最前面的 #

    LoadModule headers_module modules/mod_headers.so

> 在 DocumentRoot 設定下面的 <Directory/> 區塊中加入下面設定，以允許 CORS 存取

    Header set Access-Control-Allow-Origin "*"

● 安裝 Apache24 為 Windows 服務，用管理者權限開啟命令提示字元，接著輸入

>httpd.exe -k install


● 可將 D:\DEV\Apache24\bin\ApacheMonitor.exe 設定於開機時啟動方便管理
