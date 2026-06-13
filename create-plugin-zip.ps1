<#
    Builds a WordPress-installable ZIP of this plugin.

    - Output: plugin/{slug}-{version}.zip
    - The archive contains a single top-level "{slug}/" folder (forward-slash
      paths), so extraction on Linux/macOS yields one clean plugin folder.
    - Version is read from the plugin header in {slug}.php.

    Run: pwsh ./create-plugin-zip.ps1  (or via the /zip-plugin command)
#>

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$slug = 'bricks-static'

# --- Version from the plugin header --------------------------------------
$mainFile = Join-Path $root "$slug.php"
$verMatch = Select-String -Path $mainFile -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1
$version  = if ($verMatch) { $verMatch.Matches[0].Groups[1].Value.Trim() } else { '0.0.0' }
Write-Host "Packaging $slug v$version"

# --- Output dir (clean old zips) -----------------------------------------
$outDir = Join-Path $root 'plugin'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
Get-ChildItem -Path $outDir -Filter '*.zip' -ErrorAction SilentlyContinue | Remove-Item -Force

# --- Staging dir: temp/{slug}/... ----------------------------------------
$staging = Join-Path ([System.IO.Path]::GetTempPath()) ('bs-zip-' + [System.Guid]::NewGuid().ToString('N'))
$dest    = Join-Path $staging $slug
New-Item -ItemType Directory -Force -Path $dest | Out-Null

function Test-Excluded([string]$rel) {
    $parts = $rel -split '[\\/]'
    foreach ($p in $parts) {
        # Hidden (.git, .claude, .gitignore, …) — but KEEP Vite's ".vite" build
        # manifest dir, which PHP reads at runtime to enqueue the bundles.
        if ($p -like '.*' -and $p -ne '.vite') { return $true }
        if ($p -like 'src-*')     { return $true }   # src-svelte etc.
        if ($p -like 'svelte-*')  { return $true }   # svelte-* anywhere in path
        if ($p -ieq 'node_modules') { return $true }
        if ($p -ieq 'plugin')     { return $true }   # the output dir itself
    }
    $leaf = $parts[-1]
    switch -Wildcard ($leaf) {
        '*.md'                 { return $true }
        '*.log'                { return $true }
        'vite.config.*'        { return $true }
        'tsconfig*'            { return $true }
        'svelte.config.*'      { return $true }
        'package.json'         { return $true }
        'package-lock.json'    { return $true }
        'composer.lock'        { return $true }
        'phpcs.xml'            { return $true }
        'phpunit.xml'          { return $true }
        'create-plugin-zip.ps1'{ return $true }
    }
    return $false
}

# --- Copy included files, preserving structure ---------------------------
$count = 0
foreach ($f in Get-ChildItem -Path $root -Recurse -File) {
    $rel = $f.FullName.Substring($root.Length).TrimStart('\', '/')
    if (Test-Excluded $rel) { continue }
    $target = Join-Path $dest $rel
    $tdir   = Split-Path $target -Parent
    if (-not (Test-Path $tdir)) { New-Item -ItemType Directory -Force -Path $tdir | Out-Null }
    Copy-Item -Path $f.FullName -Destination $target -Force
    $count++
}
Write-Host "Staged $count files."

# --- Zip the staging dir (includeBaseDirectory = false → root is {slug}/) -
$zipPath = Join-Path $outDir "$slug-$version.zip"
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $staging, $zipPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $false
)

Remove-Item -Path $staging -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "Created $zipPath"
