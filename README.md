# Taoyuan Land Office Affairs Helper
A helper web app for land affairs in Taoyuan

1. To connect to Oracle 9i database, we need to put required dlls (oci8 from PECL) to c:\AppServ\php5\ext\.
2. Edit php.ini to enable extension oci8 (extension=php_oci8_11g.dll).
3. download oracle instant client and setup up its path to the system path setting.

中文：(以下使用administrator權限安裝設定，所需檔案皆在 WAMP 目錄下)
1. 執行 appserv-win32-8.6.0.exe 安裝 Apache/PHP/MySQL 到本機
2. 解壓縮 php_oci8-2.0.12-5.6-ts-vc11-x86.zip 到AppServ裡的PHP5目錄下的ext目錄（c:\AppServ\php5\ext\）
3. 修改 php.ini （程式集\AppServ下可選擇編輯），啟動 oci8（若無 extension=php_oci8_11g.dll 這一行，則自己加入）
4. 解壓縮 instantclient-basic-nt-11.2.0.4.0.zip 到C:\ (e.g. C:\instantclient_11_2）
   http://download.oracle.com/otn/nt/instantclient/11204/instantclient-basic-nt-11.2.0.4.0.zip
5. 將instantclient剛解壓縮後的位址（e.g. C:\instantclient_11_2）加入系統的PATH變數
6. 將 www 目錄下檔案全部拷貝到 C:\AppServ\www 目錄底下
7. 重新啟動電腦
8. 開啟CHROME，網址列輸入 http://localhost/index.php 即可使用網站
