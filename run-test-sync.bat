@echo off
echo Execution du script de test de synchronisation...
powershell -ExecutionPolicy Bypass -File "%~dp0\test-sync.ps1" -ShowDetails
echo.
echo Test termine!
echo Appuyez sur une touche pour fermer cette fenetre...
pause > nul
