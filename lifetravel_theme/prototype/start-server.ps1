$Hso = New-Object Net.HttpListener
$Hso.Prefixes.Add("http://localhost:8000/")
$Hso.Start()

Write-Host "Serveur HTTP démarré sur http://localhost:8000/"
Write-Host "Ctrl+C pour arrêter le serveur"

$BasePath = $PWD.Path

while ($Hso.IsListening) {
    $HC = $Hso.GetContext()
    $HRq = $HC.Request
    $HRs = $HC.Response
    
    $RequestedFile = $HRq.Url.LocalPath.Substring(1)
    
    if ($RequestedFile -eq "") {
        $RequestedFile = "index.html"
    }
    
    $FilePath = Join-Path $BasePath $RequestedFile
    
    if (Test-Path $FilePath -PathType Leaf) {
        $ContentType = "text/plain"
        
        if ($FilePath -match "\.html$") {
            $ContentType = "text/html"
        } elseif ($FilePath -match "\.css$") {
            $ContentType = "text/css"
        } elseif ($FilePath -match "\.js$") {
            $ContentType = "application/javascript"
        } elseif ($FilePath -match "\.(jpg|jpeg)$") {
            $ContentType = "image/jpeg"
        } elseif ($FilePath -match "\.png$") {
            $ContentType = "image/png"
        } elseif ($FilePath -match "\.svg$") {
            $ContentType = "image/svg+xml"
        }
        
        $Buffer = [System.IO.File]::ReadAllBytes($FilePath)
        
        $HRs.ContentType = $ContentType
        $HRs.ContentLength64 = $Buffer.Length
        $HRs.OutputStream.Write($Buffer, 0, $Buffer.Length)
    } else {
        $HRs.StatusCode = 404
        $Buffer = [System.Text.Encoding]::UTF8.GetBytes("<h1>404 - Fichier non trouvé</h1>")
        $HRs.ContentLength64 = $Buffer.Length
        $HRs.OutputStream.Write($Buffer, 0, $Buffer.Length)
    }
    
    $HRs.Close()
}

$Hso.Stop()
