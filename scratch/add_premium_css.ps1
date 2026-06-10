$files = @(
    'public\orders.php',
    'public\dashboard.php',
    'public\cart.php',
    'public\checkout.php',
    'public\marketplace_ce.php',
    'public\product_detail.php',
    'public\my_tickets.php',
    'public\tickets.php',
    'public\tasks.php',
    'public\wholesale.php',
    'public\account.php',
    'public\admin_supply.php',
    'public\analytics.php',
    'public\profile.php'
)

$base = 'c:\Users\ksgom\proyecto_Truper'
$count = 0

foreach ($f in $files) {
    $path = Join-Path $base $f
    $c = Get-Content $path -Raw -Encoding UTF8
    if ($c -notmatch 'premium\.css') {
        $newLine = '    <link rel="stylesheet" href="css/premium.css?v=1.0">'
        $c = $c -replace '(responsive-complete\.css[^"]*">)', ('$1' + "`r`n" + $newLine)
        Set-Content $path $c -Encoding UTF8 -NoNewline
        $count++
        Write-Host "Updated: $path"
    } else {
        Write-Host "Skip (already has premium.css): $f"
    }
}

Write-Host "Done. Updated $count files."
