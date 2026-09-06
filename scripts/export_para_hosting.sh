#!/bin/bash
# Export estandarizado de la BD emdb_academica para subir al hosting.
#
# Genera el dump ejecutando mysqldump dentro del contenedor Docker de
# desarrollo (servicio "db"), corrige la colación utf8mb4_0900_ai_ci
# (MySQL 8, incompatible con el MariaDB del hosting) a
# utf8mb4_unicode_ci, y deja el archivo listo en database/hosting_deploy/
# para importar vía phpMyAdmin (pruebas o producción).

set -euo pipefail

DB_NAME="emdb_academica"
OUT_DIR="database/hosting_deploy"

cd "$(dirname "$0")/.."

mkdir -p "${OUT_DIR}"

TIMESTAMP=$(TZ=America/Bogota date +"%Y-%m-%d_%H%M")
OUT_FILE="${OUT_DIR}/${TIMESTAMP}_${DB_NAME}_hosting.sql"

echo "Generando dump de '${DB_NAME}' desde el contenedor 'db'..."
docker compose exec -T db mysqldump -u root --routines --triggers --single-transaction "${DB_NAME}" > "${OUT_FILE}"

echo "Corrigiendo colación utf8mb4_0900_ai_ci -> utf8mb4_unicode_ci..."
sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' "${OUT_FILE}"

REMAINING=$(grep -c "utf8mb4_0900" "${OUT_FILE}" || true)
if [ "${REMAINING}" -gt 0 ]; then
    echo ""
    echo "AVISO: quedan ${REMAINING} aparicion(es) de 'utf8mb4_0900' sin reemplazar. Revisar manualmente:"
    grep -n "utf8mb4_0900" "${OUT_FILE}"
    echo ""
fi

FULL_PATH=$(realpath "${OUT_FILE}")
SIZE_KB=$(du -k "${OUT_FILE}" | cut -f1)

echo ""
echo "Dump generado:"
echo "  Archivo: ${FULL_PATH}"
echo "  Tamano:  ${SIZE_KB} KB"
echo ""
echo "Listo para importar en phpMyAdmin del hosting (pruebas o produccion)."
