#!/usr/bin/env bash
# Despliegue a producción de RLabs RRHH (rama sumarh-brand).
# Uso, en el servidor:   cd /var/www/asistencia-rlabs && bash scripts/desplegar.sh
# Opcional:              PHP=/usr/local/php82/bin/php bash scripts/desplegar.sh
set -euo pipefail

RAMA="sumarh-brand"
PHP="${PHP:-php}"

cd "$(dirname "$0")/.."

echo "== 1) Estado antes de desplegar =="
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ERROR: hay cambios locales sin guardar en el servidor. Revísalos con 'git status' antes de desplegar."
  git status --short --untracked-files=no
  exit 1
fi
ANTES="$(git rev-parse --short HEAD)"
echo "Versión actual: $ANTES"

echo
echo "== 2) Traer los cambios =="
git fetch origin "$RAMA"
PENDIENTES="$(git rev-list --count HEAD..origin/$RAMA)"
if [ "$PENDIENTES" = "0" ]; then
  echo "No hay cambios nuevos. Se vuelven a limpiar cachés y reiniciar la cola de todos modos."
else
  echo "Cambios nuevos ($PENDIENTES):"
  git --no-pager log --oneline HEAD..origin/$RAMA
fi
git pull --ff-only origin "$RAMA"
DESPUES="$(git rev-parse --short HEAD)"

echo
echo "== 3) Dependencias (solo si cambió composer.lock) =="
if [ "$ANTES" != "$DESPUES" ] && git diff --name-only "$ANTES" "$DESPUES" | grep -q '^composer.lock$'; then
  composer install --no-dev --optimize-autoloader --no-interaction
else
  echo "composer.lock sin cambios: no se instala nada."
fi

echo
echo "== 4) Migraciones =="
$PHP artisan migrate --force

echo
echo "== 5) Cachés =="
$PHP artisan optimize:clear
$PHP artisan permission:cache-reset 2>/dev/null || true

echo
echo "== 6) Reiniciar los procesos de cola (si no, siguen con el código viejo) =="
$PHP artisan queue:restart

echo
echo "== 7) Comprobaciones =="
echo "Versión desplegada: $DESPUES   (anterior: $ANTES)"
$PHP artisan migrate:status | tail -n 3
echo "Procesos de cola activos:"
ps -eo pid,lstart,cmd | grep -E "queue:work" | grep -v grep || echo "  (ninguno: revisa que el supervisor o el cron los levante)"

echo
echo "Listo. Si algo falló, la versión anterior fue $ANTES (ver DESPLIEGUE.md > Volver atrás)."
