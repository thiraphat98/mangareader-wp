param([string]$ConfigPath = (Join-Path $PSScriptRoot 'watcher.json'))
$ErrorActionPreference = 'Stop'
$config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
$root = [IO.Path]::GetFullPath([string]$config.library).TrimEnd('\')
$uri = [string]$config.endpoint
$secret = [string]$config.secret
if ($secret.Length -lt 32) { throw 'Watcher secret is missing.' }
$logPath = Join-Path (Split-Path $ConfigPath) 'watcher.log'
function Log([string]$message) {
    if ((Test-Path -LiteralPath $logPath) -and (Get-Item -LiteralPath $logPath).Length -gt 2097152) {
        Move-Item -LiteralPath $logPath -Destination ($logPath + '.old') -Force
    }
    "[$(Get-Date -Format o)] $message" | Out-File -LiteralPath $logPath -Append -Encoding utf8
}
function ChapterPath([string]$path) {
    if (-not $path.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) { return $null }
    if (Test-Path -LiteralPath $path -PathType Container) {
        $folder = $path
    } else {
        if ([IO.Path]::GetExtension($path) -notmatch '^\.(jpg|jpeg|png|gif|webp|avif)$') { return $null }
        $folder = [IO.Path]::GetDirectoryName($path)
    }
    $relative = $folder.Substring($root.Length + 1).Replace('\','/')
    if (($relative.Split('/')).Count -lt 2 -or ($relative.Split('/')).Count -gt 5) { return $null }
    return $folder
}
function Manifest([string]$folder) {
    if (-not (Test-Path -LiteralPath $folder -PathType Container)) { return $null }
    $files = @(Get-ChildItem -LiteralPath $folder -File -ErrorAction Stop |
        Where-Object { $_.Extension -match '^\.(jpg|jpeg|png|gif|webp|avif)$' -and $_.Length -gt 0 } |
        Sort-Object Name)
    if ($files.Count -eq 0) { return $null }
    return ($files | ForEach-Object { "$($_.Name)|$($_.Length)|$($_.LastWriteTimeUtc.Ticks)" }) -join [char]10
}
function Send([string]$folder) {
    $relative = $folder.Substring($root.Length + 1).Replace('\','/')
    $stamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $payload = "$stamp" + [char]10 + $relative
    $hmac = [Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($secret))
    try { $sig = [BitConverter]::ToString($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))).Replace('-','').ToLowerInvariant() }
    finally { $hmac.Dispose() }
    $body = @{ path = $relative; timestamp = $stamp } | ConvertTo-Json -Compress
    return Invoke-RestMethod -Uri $uri -Method Post -ContentType 'application/json' -Headers @{ 'X-Manga-Signature' = $sig } -Body $body -TimeoutSec 45
}
$pending = @{}
$lastRequest = [DateTime]::MinValue
function Queue([string]$path) {
    $folder = ChapterPath $path
    if ($folder -and -not $pending.ContainsKey($folder)) { Log "Queued $folder"; $pending[$folder] = [DateTime]::UtcNow }
}
$watcher = [IO.FileSystemWatcher]::new($root)
$watcher.IncludeSubdirectories = $true
$watcher.InternalBufferSize = 65536
$watcher.NotifyFilter = [IO.NotifyFilters]'FileName, DirectoryName, LastWrite, Size, CreationTime'
foreach ($name in @('Created','Changed','Renamed')) {
    Register-ObjectEvent -InputObject $watcher -EventName $name -SourceIdentifier "MangaWatcher.$name" | Out-Null
}
Register-ObjectEvent -InputObject $watcher -EventName Error -SourceIdentifier 'MangaWatcher.Error' | Out-Null
$watcher.EnableRaisingEvents = $true
Log "Started watching $root"
try {
    while ($true) {
        foreach ($event in @(Get-Event | Where-Object { $_.SourceIdentifier -like 'MangaWatcher.*' })) {
            if ($event.SourceIdentifier -eq 'MangaWatcher.Error') {
                Log 'Filesystem event buffer error; restarting watcher.'
                throw 'Filesystem event buffer error.'
            }
            Queue ([string]$event.SourceEventArgs.FullPath)
            Remove-Event -EventIdentifier $event.EventIdentifier
        }
        foreach ($folder in @($pending.Keys)) {
            if ((([DateTime]::UtcNow - $pending[$folder]).TotalSeconds) -lt 3) { continue }
            if ((([DateTime]::UtcNow - $lastRequest).TotalSeconds) -lt 1) { break }
            $before = Manifest $folder
            if (-not $before) {
                $pending.Remove($folder)
                continue
            }
            Start-Sleep -Seconds 1
            $after = Manifest $folder
            if ($before -ne $after -or -not $after) {
                $pending[$folder] = [DateTime]::UtcNow
                continue
            }
            $lastRequest = [DateTime]::UtcNow
            try {
                $result = Send $folder
                Log "Synced $($folder.Substring($root.Length + 1)): created=$($result.created) repaired=$($result.repaired) more=$($result.more)"
                if ($result.more) { $pending[$folder] = [DateTime]::UtcNow.AddSeconds(-2) }
                else { $pending.Remove($folder) }
            } catch {
                Log "Retry $($folder.Substring($root.Length + 1)): $($_.Exception.Message)"
                $pending[$folder] = [DateTime]::UtcNow.AddSeconds(10)
            }
            break
        }
        Start-Sleep -Milliseconds 250
    }
} finally {
    $watcher.Dispose()
    Get-EventSubscriber | Where-Object { $_.SourceIdentifier -like 'MangaWatcher.*' } | Unregister-Event
}