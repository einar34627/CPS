$path = "c:\xampp\htdocs\CPS\admin\admin_dashboard.php"
$lines = Get-Content $path
# Arrays are 0-indexed, line numbers are 1-indexed.
# Chunk 1: 5285 to 5437 -> Index 5284 to 5437 (exclusive) -> 5284 to 5436
# Chunk 2: 5562 to 5868 -> Index 5561 to 5868 (exclusive) -> 5561 to 5867

# We will construct the new content by taking parts we want to KEEP.
# Part 1: Start to 5284 (Lines 1 to 5284) -> Indices 0 to 5283
# Part 2: 5438 to 5561 (Lines 5438 to 5561) -> Indices 5437 to 5560
# Part 3: 5869 to End (Lines 5869 to End) -> Indices 5868 to End

$part1 = $lines[0..5283]
$part2 = $lines[5437..5560]
$part3 = $lines[5868..($lines.Count - 1)]

$newContent = $part1 + $part2 + $part3
$newContent | Set-Content $path -Encoding UTF8

Write-Host "Deleted ranges 5285-5437 and 5562-5868."
