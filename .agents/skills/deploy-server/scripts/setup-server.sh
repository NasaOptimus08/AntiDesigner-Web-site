#!/usr/bin/env bash
# setup-server.sh — превращает свежий Ubuntu-сервер в готовую площадку для сайтов
# «как у нас»: swap + Node LTS + PM2 + nginx + firewall + git + certbot.
#
# Запускать НА СЕРВЕРЕ от root:  bash setup-server.sh
# Скрипт идемпотентный — повторный запуск ничего не ломает.

set -euo pipefail

log() { printf "\n\033[1;36m==> %s\033[0m\n" "$1"; }

[ "$(id -u)" -eq 0 ] || { echo "Запускать от root."; exit 1; }
. /etc/os-release 2>/dev/null || true
case "${ID:-}" in ubuntu|debian) ;; *) echo "Рассчитано на Ubuntu/Debian."; ;; esac

export DEBIAN_FRONTEND=noninteractive

log "Обновляю систему"
apt-get update -y
apt-get upgrade -y

log "Базовые пакеты (git, curl, nginx, ufw, ca-certificates)"
apt-get install -y git curl ca-certificates ufw nginx

# --- SWAP (страховка памяти при сборке Next.js) ---
if ! swapon --show | grep -q .; then
  log "Создаю swap-файл 4 ГБ (память под сборку)"
  fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
else
  log "Swap уже есть — пропускаю"
fi

# --- Node.js LTS через NodeSource ---
if ! command -v node >/dev/null 2>&1; then
  log "Ставлю Node.js LTS (22.x)"
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
else
  log "Node уже стоит: $(node -v)"
fi

# --- PM2 (держит сайт запущенным, поднимает после перезагрузки) ---
if ! command -v pm2 >/dev/null 2>&1; then
  log "Ставлю PM2"
  npm install -g pm2
  pm2 startup systemd -u root --hp /root | tail -1 | bash || true
else
  log "PM2 уже стоит: $(pm2 -v)"
fi

# --- Firewall ---
log "Настраиваю firewall (SSH + HTTP + HTTPS)"
ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# --- Certbot для бесплатного SSL (пригодится, когда будет домен) ---
if ! command -v certbot >/dev/null 2>&1; then
  log "Ставлю certbot (для SSL, когда подключишь домен)"
  apt-get install -y certbot python3-certbot-nginx
fi

# --- Часовой пояс ---
timedatectl set-timezone Europe/Moscow 2>/dev/null || true

systemctl enable --now nginx >/dev/null 2>&1 || true

log "ГОТОВО. Версии:"
echo "  node : $(node -v 2>/dev/null)"
echo "  npm  : $(npm -v 2>/dev/null)"
echo "  pm2  : $(pm2 -v 2>/dev/null)"
echo "  nginx: $(nginx -v 2>&1)"
echo "Сервер готов принимать сайты."
