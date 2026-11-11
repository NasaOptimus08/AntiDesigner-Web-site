# AntiDesigner Telegram Client Assistant (VPS Package)

Автономный Telegram-ассистент на Telethon для квалификации входящих заявок, фильтрации спама/личных контактов и синхронизации сообщений с веб-панелью AntiDesigner Core.

## Быстрый запуск на VPS (Ubuntu / Debian):

1. **Распакуйте архив на сервере:**
   ```bash
   mkdir -p /opt/anti-tg-agent
   unzip antidesigner-tg-agent.zip -d /opt/anti-tg-agent
   cd /opt/anti-tg-agent
   ```

2. **Создайте виртуальное окружение и установите зависимости:**
   ```bash
   python3 -m venv venv
   source venv/bin/activate
   pip install -r requirements.txt
   ```

3. **Настройте переменные в файле `.env`:**
   ```bash
   cp .env.example .env
   nano .env
   ```

4. **Первый запуск для авторизации сессии Telegram:**
   ```bash
   python client_agent.py
   ```
   *Введите номер телефона и код подтверждения из Telegram.*

5. **Тестовый запуск без сессии (Mock Sandbox):**
   ```bash
   python client_agent.py --mock
   ```

6. **Установка как фоновый демон systemd:**
   ```bash
   cp anti-tg-agent.service /etc/systemd/system/
   systemctl daemon-reload
   systemctl enable anti-tg-agent
   systemctl start anti-tg-agent
   systemctl status anti-tg-agent
   ```

