# Script d'orchestration pour exécuter automatiquement toutes les tâches requises
# Ce script est conçu pour être exécuté en mode administrateur
# Encodage: UTF-8 avec BOM

param (
    [switch]$TestOnly,
    [switch]$SyncOnly,
    [switch]$GitOnly,
    [switch]$All,
    [switch]$Debug
)

# Configuration de l'encodage
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

# Pour Windows PowerShell 5.1, changer la page de code
$isPSCore = $PSVersionTable.PSEdition -eq "Core"
if (-not $isPSCore) {
    # Changer la page de code à UTF-8
    chcp 65001 >$null
}

# Activer le mode debug si demandé
if ($Debug) {
    $DebugPreference = "Continue"
    $VerbosePreference = "Continue"
    Write-Host "Mode DEBUG activé - Affichage détaillé" -ForegroundColor Yellow
}

# Fonction de journalisation - ajoutée pour corriger l'erreur "Write-Log is not recognized"
function Write-Log {
    param (
        [string]$Message,
        [ValidateSet("Info", "Warning", "Error", "Success")]
        [string]$Type = "Info"
    )
    
    $color = switch ($Type) {
        "Info" { "White" }
        "Warning" { "Yellow" }
        "Error" { "Red" }
        "Success" { "Green" }
    }
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    
    if ($Type -eq "Info" -and (-not $Debug)) {
        # Ne pas afficher les infos en mode non-debug
        return
    }
    
    Write-Host $logMessage -ForegroundColor $color
}

# Si aucun paramètre n'est spécifié, exécuter toutes les opérations par défaut
if (-not ($TestOnly -or $SyncOnly -or $GitOnly -or $All)) {
    $All = $true
    Write-Host "Aucun paramètre spécifié, exécution de toutes les opérations par défaut" -ForegroundColor Yellow
}

function Start-Process-And-Wait {
    param (
        [string]$FilePath,
        [string]$Arguments,
        [string]$Description,
        [int]$TimeoutSeconds = 300
    )
    
    Write-Host "Démarrage: $Description..." -ForegroundColor Cyan
    try {
        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = $FilePath
        $psi.Arguments = $Arguments
        $psi.UseShellExecute = $false
        $psi.RedirectStandardOutput = $true
        $psi.RedirectStandardError = $true
        $psi.CreateNoWindow = $false
        $psi.StandardOutputEncoding = [System.Text.Encoding]::UTF8
        $psi.StandardErrorEncoding = [System.Text.Encoding]::UTF8
        
        $process = [System.Diagnostics.Process]::Start($psi)
        Write-Host "  Processus démarré avec PID: $($process.Id)" -ForegroundColor DarkGray
        Write-Host "  Attente de la fin du processus..." -ForegroundColor DarkGray
        
        # Capturer la sortie en temps réel
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        
        # Attendre la fin du processus avec timeout
        if (!$process.WaitForExit($TimeoutSeconds * 1000)) {
            Write-Host "ERREUR: Timeout après $TimeoutSeconds secondes!" -ForegroundColor Red
            try { $process.Kill() } catch {}
            return $false
        }
        
        $stdout = $stdoutTask.Result
        $stderr = $stderrTask.Result
        
        # Afficher la sortie si nécessaire
        if ($stdout -and $stdout.Trim() -ne "") {
            Write-Host "--- SORTIE STANDARD ---" -ForegroundColor DarkCyan
            Write-Host "$stdout" -ForegroundColor DarkGray
            Write-Host "---------------------" -ForegroundColor DarkCyan
        }
        
        if ($stderr -and $stderr.Trim() -ne "") {
            Write-Host "--- ERREUR STANDARD ---" -ForegroundColor Yellow
            Write-Host "$stderr" -ForegroundColor Red
            Write-Host "----------------------" -ForegroundColor Yellow
        }
        
        $exitCode = $process.ExitCode
        Write-Host "  Code de sortie: $exitCode" -ForegroundColor $(if($exitCode -eq 0) { "Green" } else { "Red" })
        
        return ($exitCode -eq 0)
    }
    catch {
        Write-Host "ERREUR lors du démarrage du processus: $($_.Exception.Message)" -ForegroundColor Red
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
    Write-Host "Démarrage de la synchronisation du plugin principal life-travel-excursion..." -ForegroundColor Cyan
    $success1 = Start-Process-And-Wait -FilePath "powershell.exe" -Arguments "-ExecutionPolicy Bypass -File `"$syncPath`" -Mode push -Plugin life-travel-excursion -ShowDetails" -Description "Synchronisation de life-travel-excursion"
    
    if (-not $success1) {
        Write-Host "ERREUR: La synchronisation de life-travel-excursion a échoué!" -ForegroundColor Red
        return $false
    } else {
        Write-Host "La synchronisation de life-travel-excursion a réussi" -ForegroundColor Green
    }
    
    # Synchroniser payment-gateways séparément
    Write-Host "Démarrage de la synchronisation de payment-gateways..." -ForegroundColor Cyan
    $success2 = Start-Process-And-Wait -FilePath "powershell.exe" -Arguments "-ExecutionPolicy Bypass -File `"$syncPath`" -Mode push -Plugin payment-gateways -ShowDetails" -Description "Synchronisation de payment-gateways"
    
    if (-not $success2) {
        Write-Host "ERREUR: La synchronisation de payment-gateways a échoué!" -ForegroundColor Red
        return $false
    } else {
        Write-Host "La synchronisation de payment-gateways a réussi" -ForegroundColor Green
    }
    
    if ($success1 -and $success2) {
        Write-Host "Synchronisation complète réussie!" -ForegroundColor Green
        return $true
    } else {
        Write-Host "Certaines étapes de synchronisation ont échoué." -ForegroundColor Red
        return $false
    }
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
        
        # Vérifier s'il y a des modifications à committer
        $status = git status --porcelain
        
        if ($status) {
            # Créer un commit seulement s'il y a des modifications
            Write-Host "Création d'un commit..." -ForegroundColor Yellow
            git commit -m "$message"
            if ($LASTEXITCODE -ne 0) {
                Write-Host "Erreur lors de la création du commit" -ForegroundColor Red
                Pop-Location
                return $false
            }
        } else {
            Write-Host "Aucune modification à committer" -ForegroundColor Yellow
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

function Test-NoDoublons {
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
    
    # Obtenir la liste des fichiers/dossiers dans le dossier source (en excluant les fichiers IDE)
    $sourceItems = Get-ChildItem -Path $sourcePaymentGateways -Force | Where-Object { $_.Name -notlike ".*" }
    
    # Obtenir la liste des fichiers/dossiers dans le dossier cible
    $targetItems = Get-ChildItem -Path $targetPaymentGateways -Force | Where-Object { $_.Name -notlike ".*" }
    
    # Comparer les nombres d'éléments (uniquement les éléments pertinents)
    if ($sourceItems.Count -ne $targetItems.Count) {
        Write-Host "ATTENTION: Le nombre d'éléments pertinents diffère entre source ($($sourceItems.Count)) et cible ($($targetItems.Count))" -ForegroundColor Red
        
        # Identifier les différences
        $sourceNames = $sourceItems | ForEach-Object { $_.Name }
        $targetNames = $targetItems | ForEach-Object { $_.Name }
        
        $onlyInSource = $sourceNames | Where-Object { $_ -notin $targetNames }
        $onlyInTarget = $targetNames | Where-Object { $_ -notin $sourceNames }
        
        if ($onlyInSource.Count -gt 0) {
            Write-Host "Eléments uniquement dans la source: $($onlyInSource -join ', ')" -ForegroundColor Yellow
        }
        
        if ($onlyInTarget.Count -gt 0) {
            Write-Host "Eléments uniquement dans la cible: $($onlyInTarget -join ', ')" -ForegroundColor Yellow
        }
    } else {
        Write-Host "Nombre d'éléments pertinents identique entre source et cible: $($sourceItems.Count)" -ForegroundColor Green
    }
    
    # Vérifier si payment-gateways est dupliqué dans le plugin principal
    $mainPluginDir = "C:\wamp64\www\life-travel\wp-content\plugins\life-travel-excursion"
    
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
    
    $verifySuccess = Test-NoDoublons # Correction du nom de fonction (était Verify-NoDoublons)
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
