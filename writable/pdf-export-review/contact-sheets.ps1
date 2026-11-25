$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Drawing
$reviewRoot=(Resolve-Path -LiteralPath 'tmp/pdfs/pdf-export-review').ProviderPath
$manifest=Get-Content -LiteralPath 'writable/pdf-export-review/manifest.json' -Raw | ConvertFrom-Json
$pages=@()
foreach($entry in $manifest) {
    for($page=1; $page -le $entry.pages; $page++) { $pages+=($entry.name+'-page-'+$page+'.png') }
}
$font=New-Object System.Drawing.Font('Arial',12)
for($start=0; $start -lt $pages.Count; $start+=4) {
    $bitmap=New-Object System.Drawing.Bitmap(1480,1100)
    $drawing=[System.Drawing.Graphics]::FromImage($bitmap)
    $drawing.Clear([System.Drawing.Color]::LightGray)
    for($offset=0; $offset -lt 4 -and ($start+$offset) -lt $pages.Count; $offset++) {
        $name=$pages[$start+$offset]
        $source=[System.Drawing.Image]::FromFile((Join-Path $reviewRoot $name))
        $x=740*($offset%2); $y=550*[Math]::Floor($offset/2)
        $drawing.DrawString($name,$font,[System.Drawing.Brushes]::Black,[float]$x,[float]$y)
        $drawing.DrawImage($source,[int]$x,[int]($y+25),740,523)
        $source.Dispose()
    }
    $target=Join-Path $reviewRoot ('contact-'+(1+[Math]::Floor($start/4))+'.png')
    $bitmap.Save($target,[System.Drawing.Imaging.ImageFormat]::Png)
    $drawing.Dispose(); $bitmap.Dispose()
}
$font.Dispose()
Write-Output ('Rendered '+$pages.Count+' pages into '+[Math]::Ceiling($pages.Count/4)+' contact sheets.')
