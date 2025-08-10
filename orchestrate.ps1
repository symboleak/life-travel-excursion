# Script d'orchestration pour exécuter automatiquement toutes les tâches requises
# Ce script est conçu pour être exécuté en mode administrateur
param (
    [switch]$TestOnly,
    [switch]$SyncOnly,
    [switch]$GitOnly,
    [switch]$All
)

function Start-Process-And-Wait {
    param (
        [string]$FilePath,
        [string]$Arguments,
        [string]$Description
    )
    
    Write-Host "Démarrage: $Description..." -ForegroundColor Cyan
    
    # Démarrer le processus
    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -PassThru -NoNewWindow
    
    Write-Host "  Processus démarré avec PID: $($process.Id)" -ForegroundColor Yellow
    Write-Host "  Attente de la fin du processus..." -ForegroundColor Yellow
    
    # Attendre que le processus se termine
    $process.WaitForExit()
    
    # Vérifier le code de sortie
    if ($process.ExitCode -eq 0) {
        Write-Host "  Terminé avec succès: $Description" -ForegroundColor Green
        return $true
    } else {
        Write-Host "  Erreur lors de l'exécution de: $Description (Code: $($process.ExitCode))" -ForegroundColor Red
        return $false
    }
}

function Test-AdminPrivilege {
    $currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    return $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-Sync {
    Write-Host "`n=== SIMULATION DE SYNCHRONISATION ===" -ForegroundColor Magenta
    
    $testSyncPath = "$PSScriptRoot\test-sync.ps1"
    if (-not (Test-Path $testSyncPath)) {
        Write-Host "Le script test-sync.ps1 n'existe pas à l'emplacement: $testSyncPath" -ForegroundColor Red
        return $false
    }
    
    $success = Start-Process-And-Wait -FilePath "powershell.exe" -Arguments "-ExecutionPolicy Bypass -File `"$testSyncPath`" -ShowDetails" -Description "Test de synchronisation"
    return $success
}

function Sync-Files {
    Write-Host "`n=== SYNCHRONISATION RÉELLE ===" -ForegroundColor Magenta
    
    $syncPath = "$PSScriptRoot\sync-plugins.ps1"
    if (-not (Test-Path $syncPath)) {
        Write-Host "Le script sync-plugins.ps1 n'existe pas à l'emplacement: $syncPath" -ForegroundColor Red
        return $false
    }
    
    # Synchroniser le plugin principal en excluant payment-gateways
    $success1 = Start-Process-And-Wait -FilePath "powershell.exe" -Arguments "-ExecutionPolicy Bypass -File `"$syncPath`" -Mode push -Plugin life-travel-excursion" -Description "Synchronisation de life-travel-excursion"
    
    # Synchroniser payment-gateways séparément
    $success2 = Start-Process-And-Wait -FilePath "powershell.exe" -Arguments "-ExecutionPolicy Bypass -File `"$syncPath`" -Mode push -Plugin payment-gateways" -Description "Synchronisation de payment-gateways"
    
    return ($success1 -and $success2)
}

function Update-Git {
    Write-Host "`n=== MISE À JOUR GIT ===" -ForegroundColor Magenta
    
    $message = "Mise à jour automatique: correction des doublons payment-gateways et des erreurs IDE"
    
    # Se positionner dans le répertoire du projet
    Push-Location $PSScriptRoot
    
    try {
        # Ajouter les fichiers modifiés
        Write-Host "Ajout des fichiers modifiés..." -ForegroundColor Yellow
        git add sync-plugins.ps1 test-sync.ps1 run-test-sync.bat run-sync.bat orchestrate.ps1 life-travel-excursion/payment-gateways/.phpstorm.meta.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Erreur lors de l'ajout des fichiers" -ForegroundColor Red
            Pop-Location
            return $false
        }
        
        # Créer un commit
        Write-Host "Création d'un commit..." -ForegroundColor Yellow
        git commit -m "$message"
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Erreur lors de la création du commit" -ForegroundColor Red
            Pop-Location
            return $false
        }
        
        # Pousser les modifications
        Write-Host "Envoi des modifications vers le dépôt distant..." -ForegroundColor Yellow
        git push
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Erreur lors de l'envoi des modifications" -ForegroundColor Red
            Pop-Location
            return $false
        }
        
        Write-Host "Mise à jour Git terminée avec succès" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "Erreur lors de la mise à jour Git: $_" -ForegroundColor Red
        return $false
    }
    finally {
        # Revenir au répertoire original
        Pop-Location
    }
}

function Verify-NoDoublons {
    Write-Host "`n=== VÉRIFICATION DES DOUBLONS ===" -ForegroundColor Magenta
    
    $sourcePaymentGateways = "$PSScriptRoot\life-travel-excursion\payment-gateways"
    $targetPaymentGateways = "C:\wamp64\www\life-travel\wp-content\plugins\life-travel-excursion\payment-gateways"
    
    Write-Host "Vérification de la structure des dossiers de passerelles de paiement..." -ForegroundColor Yellow
    
    # Vérifier que le dossier source existe
    if (-not (Test-Path $sourcePaymentGateways)) {
        Write-Host "Le dossier source $sourcePaymentGateways n'existe pas!" -ForegroundColor Red
        return $false
    }
    
    # Vérifier que le dossier cible existe
    if (-not (Test-Path $targetPaymentGateways)) {
        Write-Host "Le dossier cible $targetPaymentGateways n'existe pas!" -ForegroundColor Red
        return $false
    }
    
    # Obtenir la liste des fichiers/dossiers dans le dossier source
    $sourceItems = Get-ChildItem -Path $sourcePaymentGateways -Force
    
    # Obtenir la liste des fichiers/dossiers dans le dossier cible
    $targetItems = Get-ChildItem -Path $targetPaymentGateways -Force
    
    # Comparer les nombres d'éléments
    if ($sourceItems.Count -ne $targetItems.Count) {
        Write-Host "ATTENTION: Le nombre d'éléments diffère entre source ($($sourceItems.Count)) et cible ($($targetItems.Count))" -ForegroundColor Red
    } else {
        Write-Host "Nombre d'éléments identique entre source et cible: $($sourceItems.Count)" -ForegroundColor Green
    }
    
    # Vérifier si payment-gateways est dupliqué dans le plugin principal
    $mainPluginDir = "C:\wamp64\www\life-travel\wp-content\plugins\life-travel-excursion"
    $mainPluginItems = Get-ChildItem -Path $mainPluginDir -Force | Where-Object { $_.Name -ne "payment-gateways" }
    
    # Vérifier si payment-gateways existe dans le plugin principal
    $paymentGatewaysExists = Test-Path "$mainPluginDir\payment-gateways"
    
    if ($paymentGatewaysExists) {
        # Maintenant vérifier si payment-gateways existe aussi comme plugin séparé
        $separatePaymentGatewaysExists = Test-Path "C:\wamp64\www\life-travel\wp-content\plugins\payment-gateways"
        
        if ($separatePaymentGatewaysExists) {
            Write-Host "PROBLÈME DÉTECTÉ: payment-gateways existe à la fois dans le plugin principal et comme plugin séparé!" -ForegroundColor Red
            return $false
        } else {
            Write-Host "payment-gateways existe uniquement dans le plugin principal (normal)" -ForegroundColor Green
            return $true
        }
    } else {
        Write-Host "payment-gateways n'existe pas dans le plugin principal (anormal)" -ForegroundColor Red
        return $false
    }
}

# Vérifier les privilèges d'administrateur
if (-not (Test-AdminPrivilege)) {
    Write-Host "Ce script nécessite des privilèges administrateur pour fonctionner correctement." -ForegroundColor Red
    Write-Host "Veuillez l'exécuter en tant qu'administrateur." -ForegroundColor Red
    exit 1
}

# Exécuter les actions selon les paramètres
$overallSuccess = $true

if ($TestOnly -or $All) {
    $testSuccess = Test-Sync
    $overallSuccess = $overallSuccess -and $testSuccess
    if (-not $testSuccess) { Write-Host "Test de synchronisation échoué!" -ForegroundColor Red }
}

if ($SyncOnly -or $All) {
    $syncSuccess = Sync-Files
    $overallSuccess = $overallSuccess -and $syncSuccess
    if (-not $syncSuccess) { Write-Host "Synchronisation échouée!" -ForegroundColor Red }
    
    $verifySuccess = Verify-NoDoublons
    $overallSuccess = $overallSuccess -and $verifySuccess
    if (-not $verifySuccess) { Write-Host "Vérification des doublons échouée!" -ForegroundColor Red }
}

if ($GitOnly -or $All) {
    $gitSuccess = Update-Git
    $overallSuccess = $overallSuccess -and $gitSuccess
    if (-not $gitSuccess) { Write-Host "Mise à jour Git échouée!" -ForegroundColor Red }
}

# Afficher le résultat global
Write-Host "`n========================================" -ForegroundColor Cyan
if ($overallSuccess) {
    Write-Host "TOUTES LES OPÉRATIONS ONT RÉUSSI" -ForegroundColor Green
} else {
    Write-Host "CERTAINES OPÉRATIONS ONT ÉCHOUÉ" -ForegroundColor Red
}
Write-Host "========================================" -ForegroundColor Cyan

# Attendre avant de fermer
Write-Host "`nAppuyez sur une touche pour fermer..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
