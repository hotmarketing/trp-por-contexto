#!/bin/bash
# Corre las pruebas. PHP puro, sin WordPress ni dependencias.
set -euo pipefail
cd "$(dirname "$0")"
for t in test-*.php; do
    echo "== $t"
    php "$t"
done
