$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$taskRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$taskFiles = @(
    'app/Database/Migrations/2026_10_04_000010_add_stock_expiry_provenance.php',
    'app/Libraries/StockExpiryProvenance.php',
    'app/Libraries/ExpiryStockResolution.php',
    'app/Libraries/ProductExchange.php',
    'app/Models/BranchProductModel.php',
    'app/Models/SaleItemModel.php',
    'app/Controllers/Products.php',
    'app/Controllers/Admin/SaleCorrection.php',
    'app/Controllers/Cashier/SalesController.php',
    'app/Controllers/Cashier/ExchangeController.php',
    'app/Views/products/edit.php',
    'app/Views/admin/reports/expiry_action.php',
    'app/Views/cashier/sales/refund_partial.php',
    'app/Views/cashier/sales/exchange.php',
    'app/Views/admin/sale_correction/edit.php',
    'database/upgrades/2026-10-04-stock-expiry-safeguards.sql',
    'database/upgrades/2026-10-04-stock-expiry-safeguards.md'
)
$taskBuild = [System.IO.Path]::GetFullPath((Join-Path $taskRoot 'build'))
$taskZipPath = Join-Path $taskBuild 'hostinger-stock-expiry-safeguards-2026-10-04.zip'
$taskTemporary = Join-Path $taskBuild ('stock-expiry-' + [guid]::NewGuid().ToString('N') + '.zip')
if (-not $taskBuild.StartsWith($taskRoot + [System.IO.Path]::DirectorySeparatorChar)) { throw 'Invalid package directory' }
[System.IO.Directory]::CreateDirectory($taskBuild) | Out-Null
$taskManifest = New-Object 'System.Collections.Generic.List[string]'
$taskArchive = [System.IO.Compression.ZipFile]::Open($taskTemporary, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($taskFile in $taskFiles) {
        $taskSource = [System.IO.Path]::GetFullPath((Join-Path $taskRoot $taskFile))
        if (-not $taskSource.StartsWith($taskRoot + [System.IO.Path]::DirectorySeparatorChar)) { throw 'Invalid package source' }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($taskArchive, $taskSource, $taskFile, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        $taskHash = (Get-FileHash -LiteralPath $taskSource -Algorithm SHA256).Hash.ToLowerInvariant()
        $taskManifest.Add($taskHash + '  ' + $taskFile)
    }
    $taskEntry = $taskArchive.CreateEntry('SHA256SUMS.txt')
    $taskWriter = New-Object System.IO.StreamWriter($taskEntry.Open(), (New-Object System.Text.UTF8Encoding($false)))
    try { $taskWriter.Write(($taskManifest -join "`n") + "`n") } finally { $taskWriter.Dispose() }
} finally { $taskArchive.Dispose() }
$taskArchive = [System.IO.Compression.ZipFile]::OpenRead($taskTemporary)
try {
    if ($taskArchive.Entries.Count -ne $taskFiles.Count + 1) { throw 'Unexpected package contents' }
    foreach ($taskFile in $taskFiles) {
        $taskEntry = $taskArchive.GetEntry($taskFile)
        if (-not $taskEntry) { throw ('Missing package entry: ' + $taskFile) }
        $taskStream = $taskEntry.Open()
        $taskAlgorithm = [System.Security.Cryptography.SHA256]::Create()
        try { $taskHash = [System.BitConverter]::ToString($taskAlgorithm.ComputeHash($taskStream)).Replace('-', '') } finally { $taskAlgorithm.Dispose(); $taskStream.Dispose() }
        $taskSourceHash = (Get-FileHash -LiteralPath (Join-Path $taskRoot $taskFile) -Algorithm SHA256).Hash
        if ($taskHash -ne $taskSourceHash) { throw ('Package mismatch: ' + $taskFile) }
    }
} finally { $taskArchive.Dispose() }
Move-Item -LiteralPath $taskTemporary -Destination $taskZipPath -Force
[pscustomobject]@{ Package = $taskZipPath; Files = $taskFiles.Count; Entries = $taskFiles.Count + 1; Bytes = (Get-Item -LiteralPath $taskZipPath).Length; Verified = 'SHA256 matches every source file' } | ConvertTo-Json -Compress
