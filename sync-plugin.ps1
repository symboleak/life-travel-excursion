# sync-plugin.ps1
param (
    [string]$sourceDir = "C:\Users\symbo\Documents\Projets\SiteVoyage\life-travel-excursion",
    [string]$targetDir = "C:\wamp64\www\life-travel\wp-content\plugins\life-travel-excursion"
)

# Vérifier que les répertoires existent
if (-not (Test-Path $sourceDir)) {
    Write-Error "Le répertoire source n'existe pas: $sourceDir"
    exit 1
}

# Créer le répertoire cible s'il n'existe pas
if (-not (Test-Path $targetDir)) {
    New-Item -ItemType Directory -Path $targetDir | Out-Null
    Write-Host "Création du répertoire cible: $targetDir"
}

# Supprimer tous les fichiers du répertoire cible
Get-ChildItem -Path $targetDir -Recurse | Remove-Item -Force -Recurse

# Copier tous les fichiers du répertoire source vers le répertoire cible
Copy-Item -Path "$sourceDir\*" -Destination $targetDir -Recurse -Force

Write-Host "Synchronisation terminée!"
Write-Host "Source: $sourceDir"
Write-Host "Cible: $targetDir"