#!/usr/bin/env bash
# deploy.sh — обновить сайт на сервере ОДНОЙ командой.
# Кладётся в корень проекта пользователя. Значения берёт из .deploy.env рядом.
#
# Использование:  bash deploy.sh
#
# .deploy.env (создаётся один раз при первом деплое):
#   SERVER_IP=123.45.67.89
#   REMOTE_DIR=/var/www/site
#   SITE_NAME=site
#   SSH_KEY=~/.ssh/id_ed25519
#   STATIC=0            # 1 — если проект статический (output:'export')

set -euo pipefail
cd "$(dirname "$0")"
[ -f .deploy.env ] || { echo "Нет .deploy.env рядом с deploy.sh"; exit 1; }
# shellcheck disable=SC1091
source .deploy.env
KEY="${SSH_KEY:-~/.ssh/id_ed25519}"
KEY="${KEY/#\~/$HOME}"

echo "==> Заливаю файлы на сервер ($SERVER_IP:$REMOTE_DIR)"
rsync -az --delete \
  --exclude node_modules --exclude .next --exclude .git --exclude out --exclude dist \
  -e "ssh -i $KEY" ./ "root@$SERVER_IP:$REMOTE_DIR/"

echo "==> Собираю и перезапускаю на сервере"
if [ "${STATIC:-0}" = "1" ]; then
  ssh -i "$KEY" "root@$SERVER_IP" "cd $REMOTE_DIR && (npm ci || npm install) && npm run build && systemctl reload nginx"
else
  ssh -i "$KEY" "root@$SERVER_IP" "cd $REMOTE_DIR && (npm ci || npm install) && npm run build && pm2 restart $SITE_NAME && pm2 save"
fi

echo "==> Готово. Обновления на сайте."
