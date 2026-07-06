#Requires -RunAsAdministrator
# Festplatten- und Absturz-Diagnose — als Administrator ausführen.
$out = Join-Path $PSScriptRoot 'disk-diagnose-report.txt'
$lines = @()
$lines += "=== DG Disk Diagnose $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ==="
$lines += ''

$lines += '--- Physical Disks ---'
Get-PhysicalDisk | Format-Table FriendlyName, MediaType, HealthStatus, OperationalStatus, Size -AutoSize | Out-String | ForEach-Object { $lines += $_ }

$lines += '--- Volumes ---'
Get-Volume | Where-Object { $_.DriveLetter } | Format-Table DriveLetter, FileSystemLabel, HealthStatus, SizeRemaining, Size -AutoSize | Out-String | ForEach-Object { $lines += $_ }

$lines += '--- chkdsk C: /scan ---'
$chk = cmd /c "chkdsk C: /scan" 2>&1
$lines += ($chk | Out-String)

$lines += '--- BitLocker C: ---'
$bde = cmd /c "manage-bde -status C:" 2>&1
$lines += ($bde | Out-String)

$lines += '--- SSD Reliability (SN740) ---'
try {
    $disk = Get-PhysicalDisk | Where-Object { $_.FriendlyName -like '*SN740*' } | Select-Object -First 1
    if ($disk) {
        Get-StorageReliabilityCounter -PhysicalDisk $disk | Format-List * | Out-String | ForEach-Object { $lines += $_ }
    }
} catch {
    $lines += $_.Exception.Message
}

$lines += '--- Recent unexpected shutdowns (Event 41) ---'
Get-WinEvent -FilterHashtable @{ LogName = 'System'; Id = 41 } -MaxEvents 5 -ErrorAction SilentlyContinue |
    ForEach-Object { $lines += "$($_.TimeCreated): $($_.Message -replace '\s+', ' ')" }

$lines += '--- Minidumps ---'
$dumpDir = 'C:\Windows\Minidump'
if (Test-Path $dumpDir) {
    Get-ChildItem $dumpDir -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending |
        Select-Object -First 5 Name, LastWriteTime, Length | Format-Table -AutoSize | Out-String | ForEach-Object { $lines += $_ }
} else {
    $lines += 'Minidump-Ordner nicht gefunden.'
}

$text = $lines -join "`r`n"
Set-Content -Path $out -Value $text -Encoding UTF8
Write-Host "Report gespeichert: $out"
Write-Host $text
