@echo off
echo Execution du script de synchronisation...
powershell -ExecutionPolicy Bypass -File "%~dp0\sync-plugins.ps1" -Mode push -Plugin life-travel-excursion
powershell -ExecutionPolicy Bypass -File "%~dp0\sync-plugins.ps1" -Mode push -Plugin payment-gateways
echo.
echo Synchronisation terminee!
echo Appuyez sur une touche pour fermer cette fenetre...
pause > nul
