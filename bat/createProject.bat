@echo off
echo.
echo Project ID - A unique id string for your project. ie. 'bbfunc3'. It is recommended to use your machine name.
echo.
set /p projectId="Enter Project ID: " %=%

echo.
echo.
echo Display Title - A title for your project. ie. 'BBFUNC3'. It is recommended to use your machine name.
echo.
set /p displayTitle="Enter Display Title: " %=%

echo.
echo.
echo Priority (optional) - Project priority. Developers should use priority 50. Func rigs use priority 100.
echo.
set /p priority="Enter Priority: " %=%

echo.
echo.
echo Password
echo.
set /p password="Enter Password: " %=%

echo.
echo.
C:\wamp\bin\php\php5.3.13\php.exe %~dp0\..\scripts\manageProject.php --create --id="%projectId%" --display-title="%displayTitle%" --priority="%priority%" --password="%password%"
echo.

pause