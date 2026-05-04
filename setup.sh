#!/bin/bash
set -e

echo "=== Referral Bunny Server Setup ==="

# ── System update ─────────────────────────────────────────────
apt-get update -qq && apt-get upgrade -y -qq

# ── nginx ─────────────────────────────────────────────────────
apt-get install -y nginx

# ── PHP 8.3 + extensions ──────────────────────────────────────
apt-get install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt-get update -qq
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
    php8.3-tokenizer php8.3-ctype php8.3-fileinfo \
    php8.3-openssl php8.3-intl php8.3-readline

# ── Composer ──────────────────────────────────────────────────
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── Node.js 20 ────────────────────────────────────────────────
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# ── Git ───────────────────────────────────────────────────────
apt-get install -y git

# ── Clone repo ────────────────────────────────────────────────
mkdir -p /var/www
cd /var/www
git clone https://github.com/PaulEspinas1989/referralbunny_Paul-sversion.git referral-bunny
cd /var/www/referral-bunny

# ── Environment file ──────────────────────────────────────────
cat > /var/www/referral-bunny/.env << 'ENVEOF'
APP_NAME="Referral Bunny"
APP_ENV=production
APP_KEY=base64:PKnf1qNZZwifiVmPfif3pUOnPgSFwtZiMKJ1hJO2U0I=
APP_DEBUG=false
APP_URL=http://103.3.62.77

DB_CONNECTION=pgsql
DB_HOST=aws-1-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.oqzxtfrmxlrdwkzcebgq
DB_PASSWORD=MoveUp2039**^^

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_LEVEL=error

BCRYPT_ROUNDS=12
FILESYSTEM_DISK=local
ENVEOF

# ── Install dependencies ──────────────────────────────────────
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --prefer-offline
npm run build

# ── Laravel setup ─────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link --force

# ── Permissions ───────────────────────────────────────────────
chown -R www-data:www-data /var/www/referral-bunny
chmod -R 755 /var/www/referral-bunny
chmod -R 775 /var/www/referral-bunny/storage
chmod -R 775 /var/www/referral-bunny/bootstrap/cache

# ── nginx config ──────────────────────────────────────────────
cat > /etc/nginx/sites-available/referral-bunny << 'NGINXEOF'
server {
    listen 80;
    server_name 103.3.62.77;
    root /var/www/referral-bunny/public;
    index index.php;

    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/referral-bunny /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── Auto-deploy script ────────────────────────────────────────
cat > /usr/local/bin/deploy-referral-bunny.sh << 'DEPLOYEOF'
#!/bin/bash
cd /var/www/referral-bunny
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --prefer-offline
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data /var/www/referral-bunny/storage
chown -R www-data:www-data /var/www/referral-bunny/bootstrap/cache
echo "Deployed at $(date)"
DEPLOYEOF
chmod +x /usr/local/bin/deploy-referral-bunny.sh

echo ""
echo "=== Setup Complete ==="
echo "Site is live at: http://103.3.62.77"
echo "To redeploy: run /usr/local/bin/deploy-referral-bunny.sh"
