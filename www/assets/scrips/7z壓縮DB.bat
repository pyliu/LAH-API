@echo off
del C:\AppServ\www\assets\db\db.7z /F
"c:\Program Files\7-Zip\7z" a C:\AppServ\www\assets\db\db.7z -p192168242 C:\AppServ\www\assets\db\*.db
