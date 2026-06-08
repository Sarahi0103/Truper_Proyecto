$files = @(
    'dashboard.php','wholesale.php','tickets.php','tasks.php','product_detail.php',
    'order_confirmation.php','orders.php','checkout.php','cashier.php',
    'cart.php','analytics.php','marketplace_ce.php','index.php','profile.php',
    'account.php'
)

$updated = 0
foreach ($f in $files) {
    $path = "c:\Users\ksgom\proyecto_Truper\public\$f"
    if (-not (Test-Path $path)) { Write-Host "SKIP: $f"; continue }

    $bytes = [System.IO.File]::ReadAllBytes($path)
    $content = [System.Text.Encoding]::UTF8.GetString($bytes)

    # Remove the Mi Cuenta line (relative path)
    $pattern1 = '[ \t]*<a href="account\.php">Mi Cuenta</a>\r?\n'
    # Remove the Mi Cuenta line (absolute path)
    $pattern2 = '[ \t]*<a href="/account\.php">Mi Cuenta</a>\r?\n'

    $newContent = [regex]::Replace($content, $pattern1, '')
    $newContent = [regex]::Replace($newContent, $pattern2, '')

    if ($newContent -ne $content) {
        $newBytes = [System.Text.Encoding]::UTF8.GetBytes($newContent)
        [System.IO.File]::WriteAllBytes($path, $newBytes)
        Write-Host "Updated: $f"
        $updated++
    } else {
        Write-Host "No change: $f"
    }
}

Write-Host "Total updated: $updated"
