# 1. Construir el release sin activarlo

```
su - deploy
export REL=/var/www/ticketera/releases/$(date +%Y%m%d%H%M%S)

git clone --depth 1 --branch main git@github.com:westnet-dev/ticketera.git $REL
cd $REL
git rev-parse --short HEAD

rm -rf $REL/storage
ln -s /var/www/ticketera/shared/storage $REL/storage
ln -s /var/www/ticketera/shared/.env $REL/.env

composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules
```

Hasta acá current sigue apuntando al release viejo y la app anda normal.

# 2. Ver qué migraciones trae

```
php artisan migrate:status | grep -i pending
```

Y mirá el SQL exacto que se va a ejecutar, sin ejecutarlo:

```
php artisan migrate --pretend
```

Leé ese SQL. Es lo que decide el paso siguiente.

# 3. Clasificar las migraciones

Tipo Ejemplos ¿Rompe el código viejo?
Aditivas CREATE TABLE, agregar columna nullable, agregar índice No
Destructivas DROP COLUMN, renombrar, cambiar un enum, pasar a NOT NULL Sí
Importa porque entre que migrás y movés el symlink, el código viejo sigue atendiendo requests contra el esquema nuevo. Si la migración es aditiva, no pasa nada. Si es destructiva, esos segundos generan errores 500.

Tu repo genera destructivas seguido: shrink_role_enum_on_users_table, replace_closed_with_paused_and_cancelled_statuses y convert_tickets_priority_to_numeric_scale son todas de ese tipo.

# 4. Backup, siempre

🔴 Esto no es opcional cuando hay migraciones. Es el único camino de vuelta si una migración corrompe datos.

```
exit # como root
/usr/local/bin/ticketera-backup.sh
ls -lh /var/backups/ticketera/ | tail -3
```

Si todavía no creaste ese script, el dump a mano:

```
mysqldump --defaults-file=/root/.my.cnf --single-transaction --quick \
--routines --triggers ticketera | gzip > /var/backups/ticketera/pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz
```

Para una migración destructiva, sumá un snapshot del LXC desde el host Proxmox — te cubre base y archivos juntos:

```
pct snapshot 210 pre-deploy-$(date +%Y%m%d)
```

# 5. Migrar

Si son aditivas, directo:

```
su - deploy && cd $REL
php artisan migrate --force
```

Si hay destructivas, modo mantenimiento primero. Como storage es compartido entre releases, el down afecta también al release activo:

```
cd /var/www/ticketera/current
php artisan down --retry=60 --secret="$(openssl rand -hex 16)"
```

guardá el secret que imprime: te deja entrar a vos por /<secret> mientras dura

```
cd $REL
php artisan migrate --force
```

# 6. Cachés y activar

```
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chmod -R ug+rwX /var/www/ticketera/shared/storage $REL/bootstrap/cache
ln -sfn $REL /var/www/ticketera/current

exit # root
systemctl reload php8.4-fpm
sudo -u deploy php /var/www/ticketera/current/artisan queue:restart
```

`queue:restart` es obligatorio: el worker de colas tiene el código viejo cargado en memoria. Con esta orden termina después del job que esté procesando, y Supervisor lo vuelve a levantar sobre el `current` nuevo. Si no se corre, los mails siguen saliendo con el código del release anterior. Ver [Worker de colas](#worker-de-colas).

Si habías puesto mantenimiento, levantalo recién ahora:

```
sudo -u deploy php /var/www/ticketera/current/artisan up
```

Mientras la app está en mantenimiento el worker no procesa jobs. Los mails quedan en la cola y salen apenas corrés `up`.

# 7. Verificar

```
sudo -u deploy php /var/www/ticketera/current/artisan migrate:status | tail -5
curl -s -o /dev/null -w "%{http_code}\n" https://desarrollo.int.westnet.com.ar/login
tail -n 30 /var/www/ticketera/shared/storage/logs/laravel.log
supervisorctl status ticketera-worker:*
```

El worker tiene que estar en `RUNNING` con un uptime de pocos segundos: eso confirma que se reinició con el release nuevo.

# 8. Rollback

El symlink solo revierte el código:

```
ln -sfn /var/www/ticketera/releases/<anterior> /var/www/ticketera/current
systemctl reload php8.4-fpm
sudo -u deploy php /var/www/ticketera/current/artisan queue:restart
```

Si además hay que revertir el esquema, primero el esquema y después el código:

```
cd /var/www/ticketera/current
php artisan migrate:rollback --step=1 --force
```

Si lo que falla son los mails y hay que cortarlos ya, sin tocar el código, frená el worker. Los mails pendientes quedan guardados en la tabla `jobs`:

```
supervisorctl stop ticketera-worker:*
```

# Worker de colas

Los mails de los tickets se mandan en segundo plano, a través de la cola `database`. El proceso que la consume (`queue:work`) lo gestiona Supervisor: arranca con el contenedor y se vuelve a levantar solo si termina.

## Instalación (una sola vez, como root)

```
apt install supervisor
```

`/etc/supervisor/conf.d/ticketera-worker.conf`:

```ini
[program:ticketera-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ticketera/current/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/var/www/ticketera/current
user=deploy
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/ticketera/shared/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

```
supervisorctl reread
supervisorctl update
supervisorctl status ticketera-worker:*
```

- **Por qué apunta a `current`:** cada vez que el worker arranca, toma el release al que apunta el symlink en ese momento. Por eso alcanza con `queue:restart` en cada deploy y no hace falta tocar este archivo.
- **Por qué corre como `deploy`:** es el usuario que construye los releases. El worker escribe en `shared/storage`, igual que PHP-FPM, y para eso el paso 6 deja los permisos de grupo en `ug+rwX`.

## Verificar que procesa

```
sudo -u deploy php /var/www/ticketera/current/artisan tinker --execute 'Artisan::queue("inspire");'
sleep 5
tail -n 3 /var/www/ticketera/shared/storage/logs/worker.log
```

Tiene que aparecer `inspire ... RUNNING` y `inspire ... DONE`. No uses `dispatch(fn () => ...)` desde `tinker --execute`: las closures escritas ahí no se pueden serializar.

## Chequeo de rutina

```
supervisorctl status ticketera-worker:*
sudo -u deploy php /var/www/ticketera/current/artisan queue:failed
```

Si hay jobs fallidos (por ejemplo, porque el SMTP estuvo caído), revisá el error y reintentalos:

```
sudo -u deploy php /var/www/ticketera/current/artisan queue:retry all
```
