#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

WEB_HOST="http://localhost:8088"
DB_USER="truper_admin"
DB_NAME="truper_platform"

echo "Selecting up to 10 SKUs with gallery images..."
SKUS=( $(find public/images/products/gallery -mindepth 1 -maxdepth 1 -type d -printf '%f
' | head -n 10) )

if [ ${#SKUS[@]} -eq 0 ]; then
  echo "No SKU gallery directories found."; exit 1;
fi

report_file="smoke_images_report_$(date +%Y%m%d_%H%M%S).txt"
echo "Smoke test report: $report_file"
echo "Generated at: $(date)" > "$report_file"

for sku in "${SKUS[@]}"; do
  echo "\n== SKU: $sku ==" | tee -a "$report_file"
  files=( $(ls -1 public/images/products/gallery/$sku 2>/dev/null || true) )
  echo "Files on disk: ${#files[@]}" | tee -a "$report_file"
  for f in "${files[@]}"; do echo " - $f" | tee -a "$report_file"; done

  # DB checks
  prodCount=$(docker-compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM products WHERE sku = '$sku' AND COALESCE(image_url,'') NOT LIKE '%default-product.svg%';" | tr -d '[:space:]') || prodCount=0
  mpCount=$(docker-compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM marketplace_ce_products WHERE sku = '$sku' AND COALESCE(image_url,'') NOT LIKE '%default-product.svg%';" | tr -d '[:space:]') || mpCount=0
  echo "Products with custom image: $prodCount" | tee -a "$report_file"
  echo "Marketplace CE with custom image: $mpCount" | tee -a "$report_file"

  # HTTP check for first file
  if [ ${#files[@]} -gt 0 ]; then
    cover=${files[0]}
    imgpath="/images/products/gallery/$sku/$cover"
    status=$(curl -s -o /dev/null -w "%{http_code}" "$WEB_HOST$imgpath" || echo "000")
    echo "HTTP status for $imgpath : $status" | tee -a "$report_file"
  else
    echo "No gallery files to HTTP-check." | tee -a "$report_file"
  fi
done

echo "\nReport saved to $report_file"
cat "$report_file"
