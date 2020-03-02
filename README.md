# Taoyuan Land Office Affairs Helper
A helper web app for land affairs in Taoyuan

1. To connect to Oracle 9i database, we need to put required dlls (oci8 from PECL) to c:\AppServ\php7\ext\.
2. Edit php.ini to enable extension oci8 (extension=php_oci8_11g.dll).
3. download oracle instant client and setup up its path to the system path setting.

中文：
1. 安裝 Apache/PHP/MySQL (以下以 AppServ windows x64 版本為例)
2. 解壓縮 php_oci8-2.2.0-7.3-ts-vc15-x64.zip 到 AppServ 裡的 PHP7 目錄下的 ext 目錄（c:\AppServ\php7\ext\）
3. 修改 php.ini （程式集\AppServ下可選擇編輯），啟動 oci8（若無 extension=php_oci8_11g 這一行，則自己加入）
4. 解壓縮 instantclient-basic-windows.x64-11.2.0.4.0.zip 到 C:\ (e.g. C:\instantclient_11_2）
   https://www.oracle.com/database/technologies/instant-client/winx64-64-downloads.html
5. 將 instantclient 剛解壓縮後的位址（e.g. C:\instantclient_11_2）加入系統的 PATH 變數
6. 將 www 目錄下檔案全部拷貝到 C:\AppServ\www 目錄底下
7. 重新啟動電腦
8. 開啟CHROME，網址列輸入 http://localhost/index.html 即可使用網站

* NO IE Support
