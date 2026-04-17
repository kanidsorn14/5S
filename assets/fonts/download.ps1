$css = Get-Content "z:\5s\assets\fonts\sarabun.css" -Raw
$matches = [regex]::Matches($css, 'url\((.*?)\)')
foreach ($match in $matches) {
    if ($match.Groups.Count -gt 1) {
        $url = $match.Groups[1].Value
        $filename = Split-Path $url -Leaf
        Invoke-WebRequest -Uri $url -OutFile "z:\5s\assets\fonts\$filename"
        $css = $css.Replace($url, $filename)
    }
}
Set-Content "z:\5s\assets\fonts\sarabun.css" -Value $css
Write-Output "Done"
