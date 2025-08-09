# Script d'extraction des plugins de paiement
param (
    [switch]$Verbose
)

# Configuration
$sourceDir = "C:\Users\symbo\Documents\Projets\SiteVoyage"
$extractDir = "$sourceDir\third-party-plugins"
$backupDir = "$sourceDir\third-party-plugins-backup"
$maxWaitTime = 60 # Temps maximum d'attente pour une opération en secondes

# Fonction de journalisation
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
    
    if ($Type -eq "Info" -and (-not $Verbose)) {
        # Ne pas afficher les infos en mode non-verbose
        return
    }
    
    Write-Host $logMessage -ForegroundColor $color
}

# Fonction pour vérifier si un processus est bloqué
function Test-OperationTimeout {
    param (
        [scriptblock]$Operation,
        [int]$TimeoutSeconds = 10,
        [string]$ErrorMessage = "L'opération a dépassé le délai d'attente"
    )
    
    $job = Start-Job -ScriptBlock $Operation
    $completed = Wait-Job -Job $job -Timeout $TimeoutSeconds
    
    if (-not $completed) {
        Stop-Job -Job $job
        Remove-Job -Job $job -Force
        Write-Log $ErrorMessage -Type "Error"
        return $false
    } else {
        $result = Receive-Job -Job $job
        Remove-Job -Job $job
        return $true, $result
    }
}

# Créer les dossiers s'ils n'existent pas
if (-not (Test-Path $extractDir)) {
    New-Item -ItemType Directory -Path $extractDir -Force | Out-Null
    Write-Log "Dossier d'extraction créé: $extractDir" -Type "Info"
}

# Créer un dossier de sauvegarde
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Write-Log "Dossier de sauvegarde créé: $backupDir" -Type "Info"
}

# Afficher un en-tête
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "  EXTRACTION DES PLUGINS DE PAIEMENT" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Rechercher les fichiers ZIP
$zipFiles = Get-ChildItem -Path $sourceDir -Filter "iwomipay*.zip" -File

if ($zipFiles.Count -eq 0) {
    Write-Log "Aucun fichier ZIP IwomiPay trouvé dans $sourceDir" -Type "Warning"
    exit 0
}

Write-Log "Fichiers ZIP IwomiPay trouvés: $($zipFiles.Count)" -Type "Info"

# Traiter chaque ZIP
foreach ($zipFile in $zipFiles) {
    Write-Host "-----------------------------------------" -ForegroundColor DarkGray
    Write-Host "Traitement de: $($zipFile.Name)" -ForegroundColor Yellow
    
    # Déterminer le nom du dossier cible
    $targetName = [System.IO.Path]::GetFileNameWithoutExtension($zipFile.Name)
    $targetDir = Join-Path -Path $extractDir -ChildPath $targetName
    
    # Sauvegarder le dossier s'il existe déjà
    if (Test-Path $targetDir) {
        $backupTarget = Join-Path -Path $backupDir -ChildPath "$targetName-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        Write-Log "Sauvegarde du dossier existant: $targetDir -> $backupTarget" -Type "Info"
        
        $timeout = Test-OperationTimeout -Operation { 
            Copy-Item -Path $targetDir -Destination $backupTarget -Recurse -Force 
        } -TimeoutSeconds $maxWaitTime -ErrorMessage "Timeout lors de la sauvegarde du dossier $targetDir"
        
        if (-not $timeout[0]) {
            Write-Log "Sauvegarde ignorée, poursuite de l'extraction" -Type "Warning"
        }
    }
    
    # Extraire le ZIP
    Write-Log "Extraction de $($zipFile.Name) vers $targetDir" -Type "Info"
    
    try {
        # Créer/nettoyer le dossier cible
        if (Test-Path $targetDir) {
            # Ne pas supprimer le dossier existant, juste préparer pour l'extraction
            Write-Log "Le dossier cible existe déjà, extraction par dessus..." -Type "Info"
        } else {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        
        # Extraire l'archive
        Expand-Archive -Path $zipFile.FullName -DestinationPath $targetDir -Force
        Write-Log "Extraction réussie: $($zipFile.Name)" -Type "Success"
    } catch {
        Write-Log "Erreur lors de l'extraction: $($_.Exception.Message)" -Type "Error"
        continue
    }
}

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "            RÉSUMÉ" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Fichiers ZIP traités: $($zipFiles.Count)" -ForegroundColor White
Write-Host ""
Write-Host "Les plugins ont été extraits dans: $extractDir" -ForegroundColor Green
Write-Host "Des sauvegardes ont été créées dans: $backupDir" -ForegroundColor Green
Write-Host ""
Write-Host "Pour synchroniser ces plugins avec WordPress, utilisez:" -ForegroundColor Yellow
Write-Host ".\sync-plugins.ps1 -Mode push -Plugin all" -ForegroundColor Yellow
