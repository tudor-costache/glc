Add-Type -Assembly 'System.IO.Compression.FileSystem'
$root = Split-Path $MyInvocation.MyCommand.Path

# ── Plugin — great-lake-cleaners/ wrapper (WordPress requires folder name to match) ──
$src  = "$root\plugin-dev\great-lake-cleaners"
$dest = "$root\great-lake-cleaners-plugin.zip"
if (Test-Path $dest) { Remove-Item $dest }
$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')
$zip.CreateEntry('great-lake-cleaners/') | Out-Null
Get-ChildItem $src -Recurse -Directory | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length + 1).Replace('\', '/') + '/'
    $zip.CreateEntry("great-lake-cleaners/$rel") | Out-Null
}
Get-ChildItem $src -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "great-lake-cleaners/$rel", 'Optimal') | Out-Null
}
$zip.Dispose()
Write-Host "Plugin repacked -> great-lake-cleaners-plugin.zip"

# ── Theme — great-lake-cleaners-theme/ wrapper ────────────────────────────────────
$src  = "$root\theme-dev\great-lake-cleaners-theme"
$dest = "$root\great-lake-cleaners-theme.zip"
if (Test-Path $dest) { Remove-Item $dest }
$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')
$zip.CreateEntry('great-lake-cleaners-theme/') | Out-Null
Get-ChildItem $src -Recurse -Directory | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length + 1).Replace('\', '/') + '/'
    $zip.CreateEntry("great-lake-cleaners-theme/$rel") | Out-Null
}
Get-ChildItem $src -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "great-lake-cleaners-theme/$rel", 'Optimal') | Out-Null
}
$zip.Dispose()
Write-Host "Theme repacked  -> great-lake-cleaners-theme.zip"
