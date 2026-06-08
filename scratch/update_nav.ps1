$files = @(
    'wholesale.php','tickets.php','tasks.php','product_detail.php',
    'order_confirmation.php','orders.php','checkout.php','cashier.php',
    'cart.php','analytics.php','marketplace_ce.php','index.php','profile.php'
)

$updated = 0
foreach ($f in $files) {
    $path = "c:\Users\ksgom\proyecto_Truper\public\$f"
    if (-not (Test-Path $path)) { Write-Host "SKIP (not found): $f"; continue }

    $bytes = [System.IO.File]::ReadAllBytes($path)
    $content = [System.Text.Encoding]::UTF8.GetString($bytes)

    # Pattern: line with Mayoreo href, then next line with Perfil href
    $pattern = '([ \t]*<a href="wholesale\.php"[^>]*>Mayoreo</a>(\r?\n))([ \t]*)(<a href="profile\.php"[^>]*>Perfil</a>)'

    if (-not [regex]::IsMatch($content, $pattern)) {
        Write-Host "No match: $f"
        continue
    }

    $replacement = '$1$3<a href="account.php">Mi Cuenta</a>$2$3<a href="account.php#historyTab">Historial</a>$2$3$4'
    $newContent = [regex]::Replace($content, $pattern, $replacement)

    $newBytes = [System.Text.Encoding]::UTF8.GetBytes($newContent)
    [System.IO.File]::WriteAllBytes($path, $newBytes)
    Write-Host "Updated: $f"
    $updated++
}

Write-Host "Total updated: $updated"
