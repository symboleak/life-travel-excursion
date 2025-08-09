# Script de synchronisation des plugins
param (
    [Parameter(Mandatory = $true)]
    [ValidateSet("push", "pull")]
    [string]$Mode,
    
    [Parameter(Mandatory = $true)]
    [string]$Plugin,
    
    [switch]$ShowDetails,
    
    [switch]$Force
)

# Configuration
$sourceDir = "C:\Users\symbo\Documents\Projets\SiteVoyage"
$wpContentDir = "C:\wamp64\www\life-travel\wp-content"
$logDir = "$sourceDir\logs"
$maxWaitTime = 60 # Temps maximum d'attente pour une opération en secondes
$timeoutWarning = 30 # Temps avant avertissement

# Initialiser la variable globale pour le mode verbose
$global:VerboseOutput = $ShowDetails

# Définition des plugins et leurs chemins
$plugins = @{}

# Déclaration des chemins pour life-travel-excursion
$plugins.Add("life-travel-excursion", @{
    "source" = "$sourceDir\life-travel-excursion"
    "target" = "$wpContentDir\plugins\life-travel-excursion"
})

# Déclaration des chemins pour life-travel-payment-adapters
$plugins.Add("life-travel-payment-adapters", @{
    "source" = "$sourceDir\life-travel-payment-adapters"
    "target" = "$wpContentDir\plugins\life-travel-payment-adapters"
})

# Déclaration des chemins pour iwomipay-momo-woocommerce
$plugins.Add("iwomipay-momo-woocommerce", @{
    "source" = "$sourceDir\third-party-plugins\iwomipay-momo-woocommerce"
    "target" = "$wpContentDir\plugins\life-travel-excursion\payment-gateways\iwomipay-momo-woocommerce"
})

# Déclaration des chemins pour iwomipay-om-woocommerce
$plugins.Add("iwomipay-om-woocommerce", @{
    "source" = "$sourceDir\third-party-plugins\iwomipay-om-woocommerce"
    "target" = "$wpContentDir\plugins\life-travel-excursion\payment-gateways\iwomipay-om-woocommerce"
})

# Déclaration des chemins pour iwomipay-card-woocommerce
$plugins.Add("iwomipay-card-woocommerce", @{
    "source" = "$sourceDir\third-party-plugins\iwomipay-card-woocommerce"
    "target" = "$wpContentDir\plugins\life-travel-excursion\payment-gateways\iwomipay-card-woocommerce"
})

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
    
    if ($Type -eq "Info" -and (-not $global:VerboseOutput)) {
        # Ne pas afficher les infos en mode non-verbose
        return
    }
    
    Write-Host $logMessage -ForegroundColor $color
    
    # Journaliser dans un fichier
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    
    $logFile = "$logDir\sync-$(Get-Date -Format 'yyyyMMdd').log"
    Add-Content -Path $logFile -Value "$logMessage" -Encoding UTF8
}

# Fonction pour vérifier si un dossier existe, sinon le créer (utilisant un verbe approuvé)
function Test-DirectoryAndCreate {
    param (
        [string]$Path
    )
    
    if (-not (Test-Path $Path)) {
        try {
            New-Item -ItemType Directory -Path $Path -Force | Out-Null
            Write-Log "Dossier créé: $Path" -Type "Success"
            return $true
        } catch {
            Write-Log "Erreur lors de la création du dossier $Path : $($_.Exception.Message)" -Type "Error"
            return $false
        }
    }
    return $true
}

# Fonction pour vérifier si un processus est bloqué
function Test-OperationTimeout {
    param (
        [scriptblock]$Operation,
        [int]$TimeoutSeconds = 10,
        [string]$OperationName = "Opération"
    )
    
    $start = Get-Date
    $warningIssued = $false
    
    $job = Start-Job -ScriptBlock $Operation
    
    # Surveiller le job avec feedback
    while ($job.State -eq "Running") {
        $elapsed = (Get-Date) - $start
        
        # Afficher un avertissement si l'opération prend trop de temps
        if ($elapsed.TotalSeconds -gt $timeoutWarning -and -not $warningIssued) {
            Write-Log "$OperationName prend plus de temps que prévu ($timeoutWarning sec)..." -Type "Warning"
            $warningIssued = $true
        }
        
        # Vérifier si le timeout est dépassé
        if ($elapsed.TotalSeconds -gt $TimeoutSeconds) {
            Stop-Job -Job $job
            Remove-Job -Job $job -Force
            Write-Log "$OperationName a dépassé le délai d'attente de $TimeoutSeconds secondes" -Type "Error"
            return $false, $null
        }
        
        # Attente courte avant de vérifier à nouveau
        Start-Sleep -Milliseconds 500
    }
    
    # Récupérer le résultat
    $result = Receive-Job -Job $job
    Remove-Job -Job $job -Force
    
    return $true, $result
}

# Fonction pour synchroniser les fichiers avec gestion des erreurs
function Sync-Files {
    param (
        [string]$Source,
        [string]$Destination,
        [string]$PluginName,
        [string]$Direction
    )
    
    if (-not (Test-Path $Source)) {
        Write-Log "Le dossier source n'existe pas: $Source" -Type "Error"
        return $false
    }
    
    # S'assurer que le dossier de destination existe
    if (-not (Test-DirectoryAndCreate -Path (Split-Path -Parent $Destination))) {
        return $false
    }
    
    # Vérifier si le dossier de destination existe déjà
    if (Test-Path $Destination) {
        # Ne pas supprimer, mais créer un backup si nécessaire
        $backupDir = "$Destination.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        
        if ($Force -or $Mode -eq "push") {
            Write-Log "Création d'une sauvegarde avant synchronisation: $backupDir" -Type "Info"
            
            try {
                # Copie de sauvegarde (sans utiliser Test-OperationTimeout pour cette opération critique)
                Copy-Item -Path $Destination -Destination $backupDir -Recurse -Force -ErrorAction Stop
                Write-Log "Sauvegarde créée" -Type "Success"
            } catch {
                Write-Log "Erreur lors de la sauvegarde: $($_.Exception.Message)" -Type "Error"
                if (-not $Force) {
                    return $false
                }
            }
        }
    } else {
        # Créer le dossier de destination
        Ensure-DirectoryExists -Path $Destination
    }
    
    # Effectuer la synchronisation
    Write-Log "Synchronisation $Direction de $PluginName..." -Type "Info"
    
    try {
        # Pour les fichiers volumineux ou nombreux, utiliser robocopy qui est plus fiable
        $robocopyOptions = "/E /COPY:DAT /DCOPY:T /R:2 /W:5 /MT:4"
        
        if ($Verbose) {
            $robocopyOptions += " /V"
        }
        
        $sourceForRobocopy = $Source
        if (-not $sourceForRobocopy.EndsWith('\')) {
            $sourceForRobocopy += '\'
        }
        
        $destForRobocopy = $Destination
        if (-not $destForRobocopy.EndsWith('\')) {
            $destForRobocopy += '\'
        }
        
        $timeout, $result = Test-OperationTimeout -Operation { 
            & robocopy $sourceForRobocopy $destForRobocopy * $robocopyOptions
            # Robocopy a des codes de sortie spéciaux, 0-7 sont des succès
            if ($LASTEXITCODE -lt 8) { return $true } else { return $false }
        } -TimeoutSeconds $maxWaitTime -OperationName "Synchronisation de $PluginName"
        
        if (-not $timeout) {
            Write-Log "Timeout lors de la synchronisation de $PluginName" -Type "Error"
            return $false
        }
        
        if (-not $result) {
            Write-Log "Erreur pendant la synchronisation de $PluginName (code: $LASTEXITCODE)" -Type "Error"
            return $false
        }
        
        Write-Log "Synchronisation de $PluginName terminée avec succès" -Type "Success"
        return $true
    } catch {
        Write-Log "Erreur lors de la synchronisation de $PluginName : $($_.Exception.Message)" -Type "Error"
        return $false
    }
}

# Créer les dossiers de base s'ils n'existent pas
if (-not (Test-Path "$sourceDir\plugins")) {
    New-Item -ItemType Directory -Path "$sourceDir\plugins" -Force | Out-Null
}
if (-not (Test-Path "$sourceDir\third-party-plugins")) {
    New-Item -ItemType Directory -Path "$sourceDir\third-party-plugins" -Force | Out-Null
}

# Afficher un en-tête
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "    SYNCHRONISATION DES PLUGINS" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Mode: $Mode | Plugin: $Plugin" -ForegroundColor Yellow
Write-Host ""

# Sélectionner les plugins à synchroniser
$pluginsToSync = @{}

if ($Plugin -eq "all") {
    # Copier individuellement pour éviter les problèmes de référence
    foreach ($key in $plugins.Keys) {
        $pluginsToSync[$key] = $plugins[$key].Clone()
    }
} else {
    if ($plugins.ContainsKey($Plugin)) {
        $pluginsToSync[$Plugin] = $plugins[$Plugin].Clone()
    } else {
        Write-Log "Plugin non reconnu: $Plugin" -Type "Error"
        exit 1
    }
}

# Compteurs pour le résumé
$totalPlugins = $pluginsToSync.Count
$successCount = 0
$errorCount = 0

# Traiter chaque plugin
foreach ($pluginName in $pluginsToSync.Keys) {
    Write-Host "-----------------------------------------" -ForegroundColor DarkGray
    Write-Host "Plugin: $pluginName" -ForegroundColor Yellow
    
    $pluginInfo = $pluginsToSync[$pluginName]
    $source = $pluginInfo["source"]
    $target = $pluginInfo["target"]
    
    if ($Mode -eq "push") {
        # Source -> Target (développement vers WordPress)
        $success = Sync-Files -Source $source -Destination $target -PluginName $pluginName -Direction "vers WordPress"
    } else {
        # Target -> Source (WordPress vers développement)
        $success = Sync-Files -Source $target -Destination $source -PluginName $pluginName -Direction "depuis WordPress"
    }
    
    if ($success) {
        $successCount++
    } else {
        $errorCount++
    }
}

# Afficher un résumé
Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "    RÉSUMÉ DE LA SYNCHRONISATION" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Plugins traités: $totalPlugins" -ForegroundColor White
Write-Host "Succès: $successCount" -ForegroundColor Green
Write-Host "Erreurs: $errorCount" -ForegroundColor $(if ($errorCount -gt 0) { "Red" } else { "Green" })

# Journaliser le résumé
Write-Log "Synchronisation terminée - Plugins: $totalPlugins, Succès: $successCount, Erreurs: $errorCount" -Type $(if ($errorCount -gt 0) { "Warning" } else { "Success" })
