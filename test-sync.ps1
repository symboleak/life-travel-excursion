# Script de test pour vérifier les exclusions
# Ce script simule la synchronisation sans copier les fichiers
# Encodage: UTF-8 avec BOM

param (
    [Parameter(Mandatory = $false)]
    [switch]$ShowDetails
)

# Forcer l'encodage de sortie en UTF-8 pour éviter les problèmes d'affichage
$OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# Vérifier la version PowerShell et adapter en conséquence
$isPSCore = $PSVersionTable.PSEdition -eq "Core"
if (-not $isPSCore) {
    # Pour Windows PowerShell 5.1, changer la page de code
    chcp 65001 >$null  # Change la page de code à UTF-8
}

# Chemins de base
$sourceDir = "C:\Users\symbo\Documents\Projets\SiteVoyage"
$wpContentDir = "C:\wamp64\www\life-travel\wp-content"

# Initialiser la variable globale pour le mode verbose
$global:VerboseOutput = $ShowDetails

# Définition des plugins et leurs chemins
$plugins = @{}

# Déclaration des chemins pour life-travel-excursion (exclut payment-gateways pour éviter la duplication)
$plugins.Add("life-travel-excursion", @{
    "source" = "$sourceDir\life-travel-excursion"
    "target" = "$wpContentDir\plugins\life-travel-excursion"
    "excludes" = @("payment-gateways")  # Exclusion du dossier payment-gateways
})

# Déclaration des chemins pour payment-gateways (source depuis SiteVoyage)
$plugins.Add("payment-gateways", @{
    "source" = "$sourceDir\life-travel-excursion\payment-gateways"
    "target" = "$wpContentDir\plugins\life-travel-excursion\payment-gateways"
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
    
    Write-Host $logMessage -ForegroundColor $color
}

# Fonction pour tester la simulation de synchronisation
function Test-SyncSimulation {
    param (
        [string]$Source,
        [string]$Destination,
        [string]$PluginName,
        [array]$Excludes = @()
    )
    
    Write-Host "==== Test de synchronisation pour: $PluginName ====" -ForegroundColor Cyan
    Write-Host "Source: $Source" -ForegroundColor Yellow
    Write-Host "Destination: $Destination" -ForegroundColor Yellow
    
    if ($Excludes -and $Excludes.Count -gt 0) {
        Write-Host "Exclusions: $($Excludes -join ', ')" -ForegroundColor Magenta
        
        # Construire les paramètres d'exclusion pour robocopy
        $excludeParams = $Excludes | ForEach-Object { "/XD `"$Source\$_`"" }
        Write-Host "Paramètres d'exclusion: $($excludeParams -join ' ')" -ForegroundColor Gray
        
        # Simuler une commande robocopy avec whatif
        Write-Host "Simulation de la commande robocopy:" -ForegroundColor Green
        Write-Host "robocopy `"$Source`" `"$Destination`" * /E /L $($excludeParams -join ' ')" -ForegroundColor Gray
    } else {
        Write-Host "Aucune exclusion spécifiée" -ForegroundColor Magenta
        
        # Simuler une commande robocopy avec whatif
        Write-Host "Simulation de la commande robocopy:" -ForegroundColor Green
        Write-Host "robocopy `"$Source`" `"$Destination`" * /E /L" -ForegroundColor Gray
    }
    
    # Test réel avec /L (List only mode)
    Write-Host "`nVérification des dossiers qui seraient copiés:" -ForegroundColor Cyan
    if ($Excludes -and $Excludes.Count -gt 0) {
        $excludeParams = $Excludes | ForEach-Object { "/XD `"$Source\$_`"" }
        
        # Exécuter robocopy en mode liste uniquement pour voir ce qui serait copié
        Write-Host "Analyse des fichiers/dossiers à la racine du plugin:" -ForegroundColor Yellow
        $rootFolders = Get-ChildItem -Path $Source -Directory | Select-Object -ExpandProperty Name
        foreach($folder in $rootFolders) {
            if ($Excludes -contains $folder) {
                Write-Host "  - $folder [EXCLU]" -ForegroundColor Red
            } else {
                Write-Host "  - $folder" -ForegroundColor Green
            }
        }
    } else {
        # Exécuter robocopy en mode liste uniquement pour voir ce qui serait copié
        Write-Host "Analyse des fichiers/dossiers à la racine du plugin:" -ForegroundColor Yellow
        $rootFolders = Get-ChildItem -Path $Source -Directory | Select-Object -ExpandProperty Name
        foreach($folder in $rootFolders) {
            Write-Host "  - $folder" -ForegroundColor Green
        }
    }
    
    Write-Host "`n" -ForegroundColor White
}

# Traiter chaque plugin
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "    TEST DE SIMULATION SYNC" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan

foreach ($pluginName in $plugins.Keys) {
    $pluginInfo = $plugins[$pluginName]
    $source = $pluginInfo["source"]
    $target = $pluginInfo["target"]
    
    # Récupérer les exclusions si définies
    $excludes = @()
    if ($pluginInfo.ContainsKey("excludes")) {
        $excludes = $pluginInfo["excludes"]
    }
    
    # Tester la simulation de synchronisation
    Test-SyncSimulation -Source $source -Destination $target -PluginName $pluginName -Excludes $excludes
}

# Fermer le script test-sync.ps1 avec un message clair et un code de retour approprié
if ($hasErrors) {
    Write-Host "`n======================================" -ForegroundColor Red
    Write-Host "   TEST TERMINÉ AVEC ERREURS" -ForegroundColor Red
    Write-Host "======================================" -ForegroundColor Red
    exit 1  # Code de sortie d'échec
} else {
    Write-Host "`n======================================" -ForegroundColor Green
    Write-Host "   TEST TERMINÉ AVEC SUCCÈS" -ForegroundColor Green
    Write-Host "======================================" -ForegroundColor Green
    exit 0  # Code de sortie de succès
}
