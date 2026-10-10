param([string]$ConfigPath = (Join-Path $PSScriptRoot 'watcher.json'))
$ErrorActionPreference = 'Stop'
$config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
$root = [IO.Path]::GetFullPath([string]$config.library).TrimEnd('\')
$uri = [string]$config.endpoint
$secret = [string]$config.secret
if ($secret.Length -lt 32) { throw 'Watcher secret is missing.' }
$stateDir = Split-Path $ConfigPath
$logPath = Join-Path $stateDir 'watcher.log'
$queuePath = Join-Path $stateDir 'watcher-queue.json'
$snapshotPath = Join-Path $stateDir 'watcher-snapshot.json'
$script:pending = @{}
$script:signatures = @{}
$script:folderCounts = @{}
$script:batchLimit = 1
$script:fastStreak = 0
$script:nextAllowed = [DateTime]::MinValue

$mutexName = 'Local\MangaReaderWatcher-' + [BitConverter]::ToString(
    [Security.Cryptography.MD5]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes([IO.Path]::GetFullPath($ConfigPath)))
).Replace('-','')
$mutex = [Threading.Mutex]::new($false, $mutexName)
if (-not $mutex.WaitOne(0)) { exit 0 }

function Log([string]$message) {
    if ((Test-Path -LiteralPath $logPath) -and (Get-Item -LiteralPath $logPath).Length -gt 2097152) {
        Move-Item -LiteralPath $logPath -Destination ($logPath + '.old') -Force
    }
    "[$(Get-Date -Format o)] $message" | Out-File -LiteralPath $logPath -Append -Encoding utf8
}
function AtomicWrite([string]$path, [string]$value) {
    $temp = $path + '.tmp'
    [IO.File]::WriteAllText($temp, $value, [Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $temp -Destination $path -Force
}
function Relative([string]$path) {
    if ($path -eq $root) { return '' }
    if (-not $path.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) { return $null }
    return $path.Substring($root.Length + 1).Replace('\','/')
}
function SaveQueue {
    $paths = @($script:pending.Keys | ForEach-Object { Relative $_ } | Where-Object { $_ })
    AtomicWrite $queuePath (ConvertTo-Json -InputObject $paths -Compress)
}
function SaveSnapshot {
    $value = @{ signatures = $script:signatures; counts = $script:folderCounts } | ConvertTo-Json -Depth 5 -Compress
    AtomicWrite $snapshotPath $value
}
function Manifest([string]$folder) {
    if (-not (Test-Path -LiteralPath $folder -PathType Container)) { return $null }
    $files = @(Get-ChildItem -LiteralPath $folder -File -ErrorAction Stop |
        Where-Object { $_.Extension -match '^\.(jpg|jpeg|png|gif|webp|avif)$' } | Sort-Object Name)
    if ($files.Count -eq 0 -or @($files | Where-Object { $_.Length -le 0 }).Count -gt 0) { return $null }
    $value = ($files | ForEach-Object { "$($_.Name)|$($_.Length)|$($_.LastWriteTimeUtc.Ticks)" }) -join [char]10
    $hash = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($value))).Replace('-','') }
    finally { $hash.Dispose() }
}
function ChapterPath([string]$path) {
    $relative = Relative $path
    if ($null -eq $relative) { return $null }
    if (Test-Path -LiteralPath $path -PathType Container) {
        $folder = $path
    } else {
        if ([IO.Path]::GetExtension($path) -notmatch '^\.(jpg|jpeg|png|gif|webp|avif)$') { return $null }
        $folder = [IO.Path]::GetDirectoryName($path)
    }
    $parts = (Relative $folder).Split('/')
    if ($parts.Count -lt 2 -or $parts.Count -gt 5 -or $parts[0].StartsWith('.')) { return $null }
    return $folder
}
function Queue([string]$folder, [bool]$save = $true) {
    if (-not $folder -or $script:pending.ContainsKey($folder)) { return }
    $script:pending[$folder] = @{ due = [DateTime]::UtcNow.AddSeconds(2); attempts = 0 }
    Log "Queued $((Relative $folder))"
    if ($save) { SaveQueue }
}
function QueueEventPath([string]$path) {
    $folder = ChapterPath $path
    if ($folder) { Queue $folder }
}
function Inventory {
    $found = @{}
    $counts = @{}
    $stack = New-Object System.Collections.Stack
    $stack.Push(@($root, 0))
    while ($stack.Count -gt 0) {
        $item = $stack.Pop()
        $folder = [string]$item[0]
        $depth = [int]$item[1]
        $relative = Relative $folder
        if ($null -eq $relative) { continue }
        $children = @(Get-ChildItem -LiteralPath $folder -Directory -ErrorAction Stop |
            Where-Object { -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -and -not $_.Name.StartsWith('.') })
        $countKey = if ($relative -eq '') { '.' } else { $relative }
        $counts[$countKey] = $children.Count
        if ($depth -ge 2) { $found[$relative] = Manifest $folder }
        if ($depth -lt 5) {
            foreach ($child in $children) { $stack.Push(@($child.FullName, $depth + 1)) }
        }
    }
    return @{ signatures = $found; counts = $counts }
}
function Reconcile([bool]$baseline = $false) {
    $current = Inventory
    foreach ($relative in @($current.signatures.Keys)) {
        if (-not $baseline -and $current.signatures[$relative] -and
            (!$script:signatures.ContainsKey($relative) -or
             $script:signatures[$relative] -ne $current.signatures[$relative])) {
            Queue (Join-Path $root ($relative.Replace('/', '\'))) $false
        }
    }
    SaveQueue
    $script:signatures = $current.signatures
    $script:folderCounts = $current.counts
    SaveSnapshot
}
function DiscoverSubtree([string]$folder) {
    if (-not (Test-Path -LiteralPath $folder -PathType Container)) { return }
    $stack = New-Object System.Collections.Stack
    $stack.Push($folder)
    while ($stack.Count -gt 0) {
        $current = [string]$stack.Pop()
        $relative = Relative $current
        if ($null -eq $relative) { continue }
        $depth = if ($relative) { $relative.Split('/').Count } else { 0 }
        if ($depth -ge 2 -and $depth -le 5 -and (Manifest $current)) { Queue $current }
        if ($depth -lt 5) {
            foreach ($child in @(Get-ChildItem -LiteralPath $current -Directory -ErrorAction SilentlyContinue |
                Where-Object { -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -and -not $_.Name.StartsWith('.') })) {
                $stack.Push($child.FullName)
            }
        }
    }
}
function RefreshParent([string]$parent) {
    $relative = Relative $parent
    if ($null -eq $relative -or -not (Test-Path -LiteralPath $parent -PathType Container)) { return }
    $children = @(Get-ChildItem -LiteralPath $parent -Directory -ErrorAction Stop |
        Where-Object { -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -and -not $_.Name.StartsWith('.') })
    $countKey = if ($relative -eq '') { '.' } else { $relative }
    $old = $script:folderCounts[$countKey]
    if ($null -ne $old -and [int]$old -eq $children.Count) { return }
    Log "Folder count changed $relative : $old -> $($children.Count)"
    $script:folderCounts[$countKey] = $children.Count
    foreach ($child in $children) {
        $childRelative = Relative $child.FullName
        if (-not $script:signatures.ContainsKey($childRelative)) { DiscoverSubtree $child.FullName }
    }
    SaveSnapshot
}
function Send([string]$folder) {
    $relative = Relative $folder
    $stamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $message = "$stamp" + [char]10 + $relative + [char]10 + $script:batchLimit
    $hmac = [Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($secret))
    try { $signature = [BitConverter]::ToString($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($message))).Replace('-','').ToLowerInvariant() }
    finally { $hmac.Dispose() }
    $body = @{ path = $relative; timestamp = $stamp; limit = $script:batchLimit } | ConvertTo-Json -Compress
    return Invoke-RestMethod -Uri $uri -Method Post -ContentType 'application/json' -Headers @{ 'X-Manga-Signature' = $signature } -Body $body -TimeoutSec 45
}
function HandleEvent($event) {
    if ($event.SourceIdentifier -eq 'MangaWatcher.Error') {
        Remove-Event -EventIdentifier $event.EventIdentifier
        throw 'Filesystem event buffer overflow.'
    }
    $path = [string]$event.SourceEventArgs.FullPath
    QueueEventPath $path
    if ($event.SourceIdentifier -ne 'MangaWatcher.Changed') {
        $parent = [IO.Path]::GetDirectoryName($path)
        if ($parent) { RefreshParent $parent }
        if ($event.SourceIdentifier -eq 'MangaWatcher.Renamed') {
            $oldPath = [string]$event.SourceEventArgs.OldFullPath
            $oldParent = [IO.Path]::GetDirectoryName($oldPath)
            if ($oldParent -and $oldParent -ne $parent) { RefreshParent $oldParent }
        }
    }
    Remove-Event -EventIdentifier $event.EventIdentifier
}
function DrainEvents {
    foreach ($event in @(Get-Event | Where-Object { $_.SourceIdentifier -like 'MangaWatcher.*' })) {
        HandleEvent $event
    }
}
function StartWatcher {
    $script:watcher = [IO.FileSystemWatcher]::new($root)
    $script:watcher.IncludeSubdirectories = $true
    $script:watcher.InternalBufferSize = 65536
    $script:watcher.NotifyFilter = [IO.NotifyFilters]'FileName, DirectoryName, LastWrite, Size, CreationTime'
    foreach ($name in @('Created','Changed','Deleted','Renamed','Error')) {
        Register-ObjectEvent -InputObject $script:watcher -EventName $name -SourceIdentifier "MangaWatcher.$name" | Out-Null
    }
    $script:watcher.EnableRaisingEvents = $true
}
function StopWatcher {
    if ($script:watcher) { $script:watcher.Dispose() }
    Get-EventSubscriber | Where-Object { $_.SourceIdentifier -like 'MangaWatcher.*' } | Unregister-Event
    Get-Event | Where-Object { $_.SourceIdentifier -like 'MangaWatcher.*' } | Remove-Event
}
try {
    if (Test-Path -LiteralPath $queuePath) {
        foreach ($relative in @(Get-Content -LiteralPath $queuePath -Raw | ConvertFrom-Json)) {
            $folder = Join-Path $root ([string]$relative).Replace('/', '\')
            if (ChapterPath $folder) { Queue $folder $false }
        }
    }
    $hadSnapshot = Test-Path -LiteralPath $snapshotPath
    if ($hadSnapshot) {
        $stored = Get-Content -LiteralPath $snapshotPath -Raw | ConvertFrom-Json
        foreach ($entry in $stored.signatures.PSObject.Properties) { $script:signatures[$entry.Name] = [string]$entry.Value }
        foreach ($entry in $stored.counts.PSObject.Properties) { $script:folderCounts[$entry.Name] = [int]$entry.Value }
    }
    StartWatcher
    Log "Started watching $root"
    Reconcile (-not $hadSnapshot)
    while ($true) {
        try {
            DrainEvents
            $ready = @($script:pending.Keys | Where-Object { $script:pending[$_].due -le [DateTime]::UtcNow })
            if ($ready.Count -eq 0 -or [DateTime]::UtcNow -lt $script:nextAllowed) {
                $wait = if ($script:pending.Count -eq 0) { 30 } else { 1 }
                $event = Wait-Event -Timeout $wait
                if ($event) { HandleEvent $event }
                continue
            }
            $folder = [string]$ready[0]
            $before = Manifest $folder
            if (-not $before) {
                $script:pending.Remove($folder)
                SaveQueue
                continue
            }
            Start-Sleep -Seconds 1
            DrainEvents
            $after = Manifest $folder
            if (-not $after -or $before -ne $after) {
                $script:pending[$folder].due = [DateTime]::UtcNow.AddSeconds(2)
                continue
            }
            $timer = [Diagnostics.Stopwatch]::StartNew()
            try {
                $result = Send $folder
                $timer.Stop()
                $duration = [Math]::Max([double]$result.duration_ms, $timer.Elapsed.TotalMilliseconds)
                if ($duration -lt 750 -and @($result.errors | Where-Object { $_ }).Count -eq 0) {
                    $script:fastStreak++
                    if ($script:fastStreak -ge 3 -and $script:batchLimit -lt 8) {
                        $script:batchLimit++
                        $script:fastStreak = 0
                    }
                } elseif ($duration -gt 2500) {
                    $script:batchLimit = [Math]::Max(1, [int][Math]::Floor($script:batchLimit / 2))
                    $script:fastStreak = 0
                }
                $script:nextAllowed = [DateTime]::UtcNow.AddMilliseconds([Math]::Min(2000, $duration * 0.2))
                Log "Synced $((Relative $folder)): created=$($result.created) repaired=$($result.repaired) more=$($result.more) duration_ms=$([int]$duration) batch=$script:batchLimit"
                $relative = Relative $folder
                $script:signatures[$relative] = $after
                SaveSnapshot
                if ($result.more) {
                    $script:pending[$folder].due = $script:nextAllowed
                    $script:pending[$folder].attempts = 0
                } else {
                    $script:pending.Remove($folder)
                    SaveQueue
                }
            } catch {
                $script:batchLimit = [Math]::Max(1, [int][Math]::Floor($script:batchLimit / 2))
                $script:fastStreak = 0
                $script:pending[$folder].attempts++
                $delay = [Math]::Min(60, [Math]::Pow(2, [Math]::Min(6, $script:pending[$folder].attempts)))
                $script:pending[$folder].due = [DateTime]::UtcNow.AddSeconds($delay)
                Log "Retry $((Relative $folder)) in $delay s: $($_.Exception.Message)"
            }
        } catch {
            Log "Recovering watcher: $($_.Exception.Message)"
            StopWatcher
            Start-Sleep -Seconds 2
            StartWatcher
            Reconcile
        }
    }
} finally {
    StopWatcher
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}