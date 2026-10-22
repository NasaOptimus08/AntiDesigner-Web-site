#!/usr/bin/env bash
# publish-site.sh — публикует сайт на сервере: собирает, запускает под PM2 и
# настраивает nginx, чтобы сайт открывался снаружи по IP (или домену).
#
# Запускать НА СЕРВЕРЕ от root, ПОСЛЕ setup-server.sh.
# Использование:
#   bash publish-site.sh <имя> <путь-к-проекту> [порт] [домен]
# Пример:
#   bash publish-site.sh site /var/www/site 3000
#   bash publish-site.sh site /var/www/site 3000 example.ru
#
# Определяет тип Next.js-проекта:
#   - статический экспорт (output:'export') -> nginx отдаёт папку сборки напрямую;
#   - обычный Next -> next build + pm2 (next start) + nginx reverse-proxy.

set -euo pipefail
NAME="${1:?имя сайта, напр. site}"
DIR="${2:?путь к проекту, напр. /var/www/site}"
PORT="${3:-3000}"
DOMAIN="${4:-}"
SERVER_NAME="${DOMAIN:-_}"

log() { printf "\n\033[1;36m==> %s\033[0m\n" "$1"; }
cd "$DIR"

log "Устанавливаю зависимости"
if [ -f package-lock.json ]; then npm ci; else npm install; fi

# Статический экспорт?
STATIC_OUT=""
if grep -RqsE "output\s*:\s*['\"]export['\"]" next.config.* 2>/dev/null; then
  log "Проект — статический экспорт Next.js"
  npm run build
  if   [ -d "$DIR/out" ];   then STATIC_OUT="$DIR/out"
  elif [ -d "$DIR/dist" ];  then STATIC_OUT="$DIR/dist"
  fi
fi

if [ -n "$STATIC_OUT" ]; then
  log "nginx будет отдавать статику из $STATIC_OUT"
  cat > /etc/nginx/sites-available/$NAME <<NGINX
server {
    listen 80;
    server_name $SERVER_NAME;
    root $STATIC_OUT;
    index index.html;
    location / { try_files \$uri \$uri.html \$uri/ =404; }
    location ~* \.(jpg|jpeg|png|webp|avif|svg|woff2|mp4|css|js)$ {
        expires 30d; add_header Cache-Control "public, immutable";
    }
    gzip on;
    gzip_types text/css application/javascript image/svg+xml application/json;
}
NGINX
else
  log "Обычный Next.js: сборка + запуск под PM2 на порту $PORT"
  npm run build
  pm2 delete "$NAME" >/dev/null 2>&1 || true
  PORT=$PORT pm2 start npm --name "$NAME" -- start
  pm2 save
  cat > /etc/nginx/sites-available/$NAME <<NGINX
server {
    listen 80;
    server_name $SERVER_NAME;
    location / {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
}
NGINX
fi

ln -sf /etc/nginx/sites-available/$NAME /etc/nginx/sites-enabled/$NAME
rm -f /etc/nginx/sites-enabled/default
log "Проверяю и перезапускаю nginx"
nginx -t
systemctl reload nginx

IP=$(hostname -I | awk '{print $1}')
log "ГОТОВО"
echo "Сайт открывается по: http://${DOMAIN:-$IP}"
[ -n "$DOMAIN" ] && echo "Для https запусти:  certbot --nginx -d $DOMAIN"
