$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$cashierPackageRoot = [IO.Path]::GetFullPath('build/cashier-workspace-2026-10-15')
$cashierZipPath = [IO.Path]::GetFullPath('build/hostinger-cashier-workspace-2026-10-15.zip')
$cashierPackageManifest = Get-Content writable/cashier-day-review/package-manifest.json -Raw | ConvertFrom-Json
$cashierZipStream = [IO.File]::Open($cashierZipPath, [IO.FileMode]::Create)
try {
    $cashierArchive = [IO.Compression.ZipArchive]::new($cashierZipStream,[IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($cashierEntry in $cashierPackageManifest) {
            $cashierSource = [IO.Path]::GetFullPath((Join-Path $cashierPackageRoot $cashierEntry.file))
            if (-not $cashierSource.StartsWith($cashierPackageRoot + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Package entry escaped stage directory' }
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($cashierArchive,$cashierSource,$cashierEntry.file,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally { $cashierArchive.Dispose() }
} finally { $cashierZipStream.Dispose() }
$cashierArchive = [IO.Compression.ZipFile]::OpenRead($cashierZipPath)
try {
    if ($cashierArchive.Entries.Count -ne $cashierPackageManifest.Count) { throw 'Package file count mismatch' }
    foreach ($cashierEntry in $cashierPackageManifest) {
        $cashierStoredEntry = $cashierArchive.GetEntry($cashierEntry.file)
        if (-not $cashierStoredEntry) { throw 'Missing package entry' }
        $cashierEntryStream = $cashierStoredEntry.Open()
        $cashierHasher = [Security.Cryptography.SHA256]::Create()
        try {
            $cashierStoredHash = [BitConverter]::ToString($cashierHasher.ComputeHash($cashierEntryStream)).Replace('-','').ToLowerInvariant()
            if ($cashierStoredHash -ne $cashierEntry.sha256) { throw 'Package content mismatch' }
        } finally { $cashierEntryStream.Dispose(); $cashierHasher.Dispose() }
    }
    if ($cashierArchive.GetEntry('app/Config/ReceiptInvoice.php')) { throw 'Live invoice config must be excluded' }
} finally { $cashierArchive.Dispose() }
Get-Item -LiteralPath $cashierZipPath | Select-Object Name,Length
Write-Output "All $($cashierPackageManifest.Count) package entries verified against current source hashes."
