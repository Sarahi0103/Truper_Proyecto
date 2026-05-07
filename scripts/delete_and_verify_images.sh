#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

WEB_HOST="http://localhost:8088"
DB_USER="truper_admin"
DB_NAME="truper_platform"

SKUS=( $(find public/images/products/gallery -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | head -n 10) )
if [ ${#SKUS[@]} -eq 0 ]; then
  echo "No SKU gallery directories found."; exit 1;
fi

report_file="delete_verify_report_$(date +%Y%m%d_%H%M%S).txt"
echo "Delete+Verify report: $report_file"
echo "Generated at: $(date)" > "$report_file"

echo "Running delete-and-verify for ${#SKUS[@]} SKUs..." | tee -a "$report_file"

for sku in "${SKUS[@]}"; do
  echo "\n== SKU: $sku ==" | tee -a "$report_file"
  files=( $(ls -1 public/images/products/gallery/$sku 2>/dev/null || true) )
  if [ ${#files[@]} -eq 0 ]; then
    echo "No files found for SKU $sku, skipping." | tee -a "$report_file"
    continue
  fi
  cover=${files[0]}
  rel="images/products/gallery/$sku/$cover"

  echo "Selected file: $cover" | tee -a "$report_file"

  echo "Invoking delete CLI for $sku $rel" | tee -a "$report_file"
  docker-compose run --rm web php scripts/delete_image_cli.php "$sku" "$rel" 2>&1 | tee -a "$report_file"

  # Check disk
  if [ -f public/images/products/gallery/$sku/$cover ]; then
    echo "DISK: still exists: public/images/products/gallery/$sku/$cover" | tee -a "$report_file"
  else
    echo "DISK: removed: public/images/products/gallery/$sku/$cover" | tee -a "$report_file"
  fi
  if [ -f public/images/products/by_code/$sku/$cover ]; then
    echo "DISK LEGACY: still exists: public/images/products/by_code/$sku/$cover" | tee -a "$report_file"
  else
    echo "DISK LEGACY: removed: public/images/products/by_code/$sku/$cover" | tee -a "$report_file"
  fi

  # DB checks
  prodCount=$(docker-compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM products WHERE sku = '$sku' AND COALESCE(image_url,'') NOT LIKE '%default-product.svg%';" | tr -d '[:space:]' ) || prodCount=0
  mpCount=$(docker-compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM marketplace_ce_products WHERE sku = '$sku' AND COALESCE(image_url,'') NOT LIKE '%default-product.svg%';" | tr -d '[:space:]' ) || mpCount=0
  echo "DB: products with custom image: $prodCount" | tee -a "$report_file"
  echo "DB: marketplace with custom image: $mpCount" | tee -a "$report_file"

  # HTTP check for image
  status=$(curl -s -o /dev/null -w "%{http_code}" "$WEB_HOST/$rel" || echo "000")
  echo "HTTP status for /$rel : $status" | tee -a "$report_file"

done

echo "\nReport saved to $report_file"
cat "$report_file"
