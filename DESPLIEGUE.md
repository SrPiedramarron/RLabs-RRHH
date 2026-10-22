# Despliegue a producción — RLabs RRHH

Servidor: `/var/www/asistencia-rlabs` · rama: `sumarh-brand` · base de datos MySQL · colas en la base de datos (`QUEUE_CONNECTION=database`).

## Procedimiento normal (siempre en este orden)

```bash
cd /var/www/asistencia-rlabs
bash scripts/desplegar.sh
```

El script hace, en orden:

1. **Revisa que no haya cambios locales** sin guardar en el servidor; si los hay, se detiene.
2. **Trae los cambios** (`git fetch` + `git pull --ff-only`) y muestra qué commits entran.
3. **Instala dependencias solo si cambió `composer.lock`**.
4. **Corre las migraciones** (`php artisan migrate --force`).
5. **Limpia cachés** (`optimize:clear`, caché de permisos).
6. **Reinicia las colas** (`php artisan queue:restart`). *Este paso es el que se suele olvidar:* los procesos de cola guardan el código en memoria y, sin reinicio, siguen calculando con la versión anterior (así se produjeron las horas ordinarias de más en octubre de 2026).
7. **Comprueba**: versión desplegada, estado de migraciones y procesos de cola activos.

Si el servidor usa otra versión de PHP para la web que para la consola, indícala: `PHP=/usr/local/php82/bin/php bash scripts/desplegar.sh`.

## Antes de desplegar (cambios con migraciones o con datos)

- **Copia de seguridad de la base de datos**, sobre todo si el cambio trae migraciones:
  ```bash
  mysqldump -u USUARIO -p NOMBRE_BD | gzip > ~/respaldo_$(date +%Y%m%d_%H%M).sql.gz
  ```
- Evita desplegar durante el **cierre de planilla**, los **pagos de quincena** o la **hora de marcación** de la mañana (08:30–09:30).
- Si el cambio agrega un **comando de carga de datos** (por ejemplo `rrhh:cargar-...`), corre primero la simulación (sin `--aplicar`) y revisa la tabla antes de aplicar.

## Después de desplegar

1. Abre el panel (`/admin`) y entra a la pantalla que cambió.
2. Revisa el log si algo falla: `tail -n 50 storage/logs/laravel.log`.
3. Comprueba la revisión de cruces entre empresas: `php artisan rrhh:auditar-cruces-empresa` (debe decir «Sin cruces de datos entre empresas»).
4. Si cambió un cálculo de asistencia (horas, tardanzas), **reprocesa el rango afectado** con el código nuevo y vuelve a generar los reportes:
   ```bash
   php artisan tinker --execute='echo app(App\Services\AttendanceProcessor::class)->reprocesarRango("AAAA-MM-DD","AAAA-MM-DD")." día(s) reprocesados\n";'
   ```
   (No toca registros corregidos a mano.)

## Procesos de cola: cómo deben correr

- Todos con límite de tiempo, para que se reinicien solos y no queden con código viejo:
  `php artisan queue:work --sleep=3 --tries=3 --timeout=600 --max-time=3600`
- Debe haber un supervisor (o cron) que los vuelva a levantar al terminar.
- Ver los activos: `ps -eo pid,lstart,cmd | grep queue:work | grep -v grep`

## Volver atrás

El script imprime la versión anterior (`ANTES`). Para volver:

```bash
git reset --hard VERSION_ANTERIOR   # cuidado: descarta lo que no esté en esa versión
php artisan optimize:clear && php artisan queue:restart
```

Las **migraciones no se revierten solas**: si una migración ya corrió, no la deshagas sin revisar su método `down()`; muchas son «aditivas» (agregan columnas o permisos) y no estorban a la versión anterior. Ante la duda, restaura la copia de la base de datos.

## Demo (VPS aparte)

El demo **no** se actualiza con `git pull` (no es un clon). Se sube un paquete con `git archive` y se reconstruye ahí; el reseteo nocturno (03:00 Lima) ejecuta `migrate:fresh` y siembra los datos de nuevo.
