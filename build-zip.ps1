Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$ErrorActionPreference = 'Stop'

$root       = $PSScriptRoot
$pluginSlug = 'ai-email-helper'
$version    = '0.2.0'
$zipName    = "$pluginSlug-$version.zip"
$zipPath    = Join-Path $root $zipName

# Files/dirs to exclude from the package (dev-only artifacts).
$excludeDirs  = @('.git')
$excludeFiles = @('.gitignore', 'build-zip.ps1', $zipName)

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$files = Get-ChildItem -Path $root -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring($root.Length).TrimStart('\')
    $topSegment = $rel.Split('\')[0]
    ($excludeDirs -notcontains $topSegment) -and ($excludeFiles -notcontains $rel) -and ($_.Extension -ne '.zip')
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($f in $files) {
        $rel   = $f.FullName.Substring($root.Length).TrimStart('\').Replace('\', '/')
        $entry = "$pluginSlug/$rel"
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        Write-Host "  + $entry"
    }
}
finally {
    $zip.Dispose()
}

Write-Host ""
Write-Host "Created $zipName ($([math]::Round((Get-Item $zipPath).Length / 1KB, 1)) KB) with $($files.Count) files."
