#!/bin/sh
set -eu

port="${PORT:-8080}"

# mod_php requires prefork. Ensure no other Apache MPM remains enabled.
a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1

sed -ri "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

for bucket in students notices achievements documents; do
    mkdir -p "/var/www/html/uploads/${bucket}"
done

if [ ! -f /var/www/html/uploads/.htaccess ]; then
    cp /opt/upload-protection/.htaccess /var/www/html/uploads/.htaccess
fi

chown -R www-data:www-data /var/www/html/uploads
chmod 755 /var/www/html/uploads /var/www/html/uploads/*

if [ "${AUTO_INIT_DB:-0}" = "1" ]; then
    attempt=1
    until php /var/www/html/scripts/init_database.php --if-empty; do
        if [ "$attempt" -ge 12 ]; then
            echo "Database initialization failed after ${attempt} attempts." >&2
            exit 1
        fi
        echo "Database is not ready yet; retrying in 5 seconds (${attempt}/12)."
        attempt=$((attempt + 1))
        sleep 5
    done
fi

exec "$@"
