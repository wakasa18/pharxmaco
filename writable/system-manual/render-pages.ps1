param([string]$SourcePath='',[string]$OutputDir='tmp/pdfs/system-manual',[string]$Pages='all',[int]$Width=1100)
$ErrorActionPreference='Stop'
if (-not $SourcePath) { $SourcePath = (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'manifest.json') -Raw | ConvertFrom-Json).pdf }
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null=[Windows.Storage.StorageFile,Windows.Storage,ContentType=WindowsRuntime]
$null=[Windows.Data.Pdf.PdfDocument,Windows.Data.Pdf,ContentType=WindowsRuntime]
$null=[Windows.Data.Pdf.PdfPageRenderOptions,Windows.Data.Pdf,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.InMemoryRandomAccessStream,Windows.Storage.Streams,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.DataReader,Windows.Storage.Streams,ContentType=WindowsRuntime]
$operationMethod=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.IsGenericMethodDefinition -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
$actionMethod=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and -not $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction' } | Select-Object -First 1
function Complete-Operation($operation,[Type]$resultType) { $task=$operationMethod.MakeGenericMethod($resultType).Invoke($null,@($operation)); return $task.GetAwaiter().GetResult() }
$source=(Resolve-Path -LiteralPath $SourcePath).ProviderPath
$null=New-Item -ItemType Directory -Path $OutputDir -Force
$destination=(Resolve-Path -LiteralPath $OutputDir).ProviderPath
$file=Complete-Operation ([Windows.Storage.StorageFile]::GetFileFromPathAsync($source)) ([Windows.Storage.StorageFile])
$pdf=Complete-Operation ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
$total=$pdf.PageCount
$selected=if($Pages -eq 'all'){1..$total}else{$Pages.Split(',') | ForEach-Object {[int]$_}}
foreach($number in $selected) {
    if($number -lt 1 -or $number -gt $total){throw 'Invalid page number.'}
    $pdf=Complete-Operation ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
    $page=$pdf.GetPage($number-1)
    $stream=New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
    $options=New-Object Windows.Data.Pdf.PdfPageRenderOptions
    $options.DestinationWidth=$Width
    $task=$actionMethod.Invoke($null,@($page.RenderToStreamAsync($stream,$options)))
    $null=$task.GetAwaiter().GetResult()
    $stream.Seek(0)
    $reader=New-Object Windows.Storage.Streams.DataReader($stream)
    $null=Complete-Operation ($reader.LoadAsync([uint32]$stream.Size)) ([uint32])
    $bytes=New-Object byte[] ([int]$stream.Size)
    $reader.ReadBytes($bytes)
    [System.IO.File]::WriteAllBytes((Join-Path $destination ("page-"+$number.ToString('00')+'.png')),$bytes)
    $page.Dispose();$reader.Dispose();$stream.Dispose()
}
@{source=$source;pages=$total;rendered=@($selected).Count;width=$Width} | ConvertTo-Json
