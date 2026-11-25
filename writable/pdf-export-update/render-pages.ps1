$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null=[Windows.Storage.StorageFile,Windows.Storage,ContentType=WindowsRuntime]
$null=[Windows.Data.Pdf.PdfDocument,Windows.Data.Pdf,ContentType=WindowsRuntime]
$null=[Windows.Data.Pdf.PdfPageRenderOptions,Windows.Data.Pdf,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.InMemoryRandomAccessStream,Windows.Storage.Streams,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.DataReader,Windows.Storage.Streams,ContentType=WindowsRuntime]
$operationMethod=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.IsGenericMethodDefinition -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
$actionMethod=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and -not $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction' } | Select-Object -First 1
function Complete-Operation($operation,[Type]$resultType) {
    $task=$operationMethod.MakeGenericMethod($resultType).Invoke($null,@($operation))
    return $task.GetAwaiter().GetResult()
}
$results=@()
foreach($state in (Get-Content -LiteralPath 'writable/pdf-export-update/manifest.json' -Raw | ConvertFrom-Json).name) {
    $source=(Resolve-Path -LiteralPath "tmp/pdfs/pdf-export-update/$state.pdf").ProviderPath
    $file=Complete-Operation ([Windows.Storage.StorageFile]::GetFileFromPathAsync($source)) ([Windows.Storage.StorageFile])
    $pdf=Complete-Operation ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
    for($index=0;$index -lt $pdf.PageCount;$index++) {
        # Give each page render an independent document handle.
        $pdf=Complete-Operation ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
        $page=$pdf.GetPage($index)
        $stream=New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
        $options=New-Object Windows.Data.Pdf.PdfPageRenderOptions
        $options.DestinationWidth=1100
        $task=$actionMethod.Invoke($null,@($page.RenderToStreamAsync($stream,$options)))
        $null=$task.GetAwaiter().GetResult()
        $stream.Seek(0)
        $reader=New-Object Windows.Storage.Streams.DataReader($stream)
        $null=Complete-Operation ($reader.LoadAsync([uint32]$stream.Size)) ([uint32])
        $bytes=New-Object byte[] ([int]$stream.Size)
        $reader.ReadBytes($bytes)
        $target=Join-Path (Split-Path -Parent $source) ("$state-page-"+($index+1)+'.png')
        [System.IO.File]::WriteAllBytes($target,$bytes)
        $page.Dispose()
    }
    $results+=@{state=$state;pages=$pdf.PageCount}
}
$results | ConvertTo-Json | Set-Content -LiteralPath 'writable/pdf-export-update/page-counts.json'
$results | ConvertTo-Json


