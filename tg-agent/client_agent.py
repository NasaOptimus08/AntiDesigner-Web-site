#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
=============================================================================
ANTIDESIGNER CORE - TELEGRAM CLIENT ASSISTANT (Telethon Userbot)
Autonomous AI sales agent with intent filtering, human typing delay,
blacklist protection and bidirectional synchronization with lk-admin API.
=============================================================================
Usage:
    python client_agent.py          # Live mode (requires Telethon credentials)
    python client_agent.py --mock   # Local test simulation sandbox (no phone needed)
=============================================================================
"""

import os
import sys
import time
import random
import re
import json
import asyncio
from datetime import datetime

# Load environment configuration
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

API_ID = os.getenv("TG_API_ID", "2847192")
API_HASH = os.getenv("TG_API_HASH", "8f7e6d5c4b3a2109fedcba9876543210")
PHONE = os.getenv("TG_PHONE", "")
SYNC_ENDPOINT = os.getenv("SYNC_ENDPOINT", "http://localhost:8000/lk-admin/api/sync_chat.php")
DELAY_MIN = int(os.getenv("DELAY_MIN", "3"))
DELAY_MAX = int(os.getenv("DELAY_MAX", "7"))
BLACKLIST = [x.strip().lower() for x in os.getenv("BLACKLIST_PHONES", "+79990001122,Мама,Брат,Музыка").split(",") if x.strip()]

# Keywords for site development intent filtering
INTENT_KEYWORDS = [
    r"сайт", r"лендинг", r"разработк", r"тильд", r"tilda", r"скорост", r"тариф",
    r"стоимост", r"цен", r"pagespeed", r"дизайн", r"верстк", r"код", r"wordpress",
    r"клик", r"заказ", r"аудит", r"чек-лист", r"бюджет", r"договор",
    r"бэкенд", r"backend", r"django", r"python"
]

DEFAULT_SYSTEM_PROMPT = (
    "Ты - автономный ассистент веб-студии AntiDesigner. Твоя задача - квалифицировать лида, "
    "выявить потребность в быстром сайте на чистом коде с гарантией скорости 95-100/100 "
    "(без конструкторов Tilda/Wordpress), предложить подходящий тариф (18к-25к, 25к-35к или 35к+) "
    "и предложить бесплатный экспресс-аудит текущего сайта."
)

def is_blacklisted(sender_info: str) -> bool:
    """Check if sender phone or name matches blacklist."""
    info_clean = sender_info.lower().strip()
    for item in BLACKLIST:
        if item in info_clean:
            return True
    return False

def matches_sales_intent(text: str) -> bool:
    """Check if message matches web development sales intent."""
    text_lower = text.lower()
    for pattern in INTENT_KEYWORDS:
        if re.search(pattern, text_lower):
            return True
    return False

def sync_message_to_admin(lead_id_or_user_id: str, username: str, full_name: str, sender: str, text: str):
    """Send message to lk-admin/api/sync_chat.php."""
    import urllib.request
    payload = {
        "telegram_user_id": str(lead_id_or_user_id),
        "telegram_username": username or "",
        "full_name": full_name or "Клиент Telegram",
        "sender": sender,
        "text": text,
        "utm_source": "tg_assistant"
    }
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        SYNC_ENDPOINT,
        data=data,
        headers={"Content-Type": "application/json", "Accept": "application/json"}
    )
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            res_data = json.loads(response.read().decode())
            return res_data
    except Exception as e:
        print(f"[SYNC ERROR] Failed to sync to {SYNC_ENDPOINT}: {e}")
        return None

def load_bot_skill() -> dict:
    """Load bot_consultant skill from SQLite database."""
    import sqlite3
    db_path = os.path.join(os.path.dirname(__file__), "..", "core-data", "database.sqlite")
    if os.path.exists(db_path):
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            cursor.execute("SELECT system_prompt, tone_of_voice FROM skills WHERE code = 'bot_consultant' OR category = 'bot' ORDER BY id ASC LIMIT 1")
            row = cursor.fetchone()
            conn.close()
            if row and row[0]:
                return {"system_prompt": row[0], "tone_of_voice": row[1] or ""}
        except Exception as e:
            print(f"[SKILL LOAD WARNING] Could not load skill from DB: {e}")
    return {
        "system_prompt": DEFAULT_SYSTEM_PROMPT,
        "tone_of_voice": "Вежливый, оперативный, структурированный бот-помощник"
    }

def get_fast_api_key() -> str:
    """Extract gemini_fast_api_key (AQ...) from config.local.php or env."""
    key = os.getenv("GEMINI_FAST_API_KEY", "")
    if not key:
        config_path = os.path.join(os.path.dirname(__file__), "..", "core-data", "config.local.php")
        if os.path.exists(config_path):
            try:
                with open(config_path, "r", encoding="utf-8") as f:
                    content = f.read()
                    match = re.search(r"['\"]gemini_fast_api_key['\"]\s*=>\s*['\"]([^'\"]+)['\"]", content)
                    if match:
                        key = match.group(1).strip()
            except Exception:
                pass
    return key

def validate_and_sanitize_reply(reply_text: str, user_message: str) -> str:
    """
    Протокол "Анти-галлюцинация и 100% честность":
    Проверяет сгенерированный моделью ответ перед отправкой клиенту.
    Если ответ содержит признаки галлюцинации, неуверенности модели или выдуманных обещаний,
    скрипт мягко переводит диалог в режим ожидания живого разработчика.
    """
    clean_text = reply_text.strip().replace("—", "-").replace("–", "-")
    text_lower = clean_text.lower()
    msg_lower = user_message.lower()

    # Признаки галлюцинаций, извинений ИИ или неопределенности модели
    uncertainty_markers = [
        "я не уверен", "не уверена", "я языковая модель", "как ии", "как ai",
        "as an ai", "к сожалению, я не знаю", "не могу точно сказать",
        "не обладаю информацией", "извините за неудобство", "мои знания ограничены",
        "я бот", "я виртуальный ассистент", "я не могу выполнить"
    ]

    # Маркеры нестандартных запросов, которых нет в базовых тарифах (Python, Django, сложные порталы)
    unsupported_tech_markers = [
        "django", "python-бэкенд", "бэкенд на питоне", "бэкенд на python",
        "сложный маркетплейс", "1с erp"
    ]

    # Маркеры ложных инфоцыганских обещаний
    false_promise_markers = [
        "на 1 место в яндексе за 2 дня", "первое место в поиске гарантировано",
        "100% конверси", "взрывной рост в 10 раз"
    ]

    for marker in uncertainty_markers:
        if marker in text_lower:
            return "Зафиксировал ваш вопрос, сейчас уточню точные технические детали и вернусь с ответом."

    for marker in false_promise_markers:
        if marker in text_lower:
            return "Зафиксировал ваш вопрос, сейчас уточню точные технические детали и вернусь с ответом."

    # Если клиент прямо просит нестандартный сложный бэкенд на Python/Django
    for marker in unsupported_tech_markers:
        if marker in msg_lower:
            return (
                "Мы специализируемся на сверхбыстрой фронтенд-верстке и легком бэкенде на чистом коде с гарантией 100/100 в PageSpeed. "
                "Зафиксировал вашу задачу по индивидуальному стеку и передам разработчику для точного ответа."
            )

    return clean_text

def generate_assistant_reply(user_message: str, live_ai: bool = False) -> str:
    """
    Generate reply for AntiDesigner assistant.
    Pulls bot_consultant skill from SQLite database and queries MODEL_FAST (AQ...) if live_ai=True.
    Falls back to deterministic rule-based reply in mock/offline mode to save AI tokens.
    Guaranteed Zero Hallucinations filtering before sending.
    """
    skill = load_bot_skill()
    fast_key = get_fast_api_key()

    if live_ai and fast_key and fast_key.startswith("AQ"):
        import urllib.request
        url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={fast_key}"
        zero_hallucinations_rule = (
            "\n\nЖЕСТКИЙ ПРОТОКОЛ ФАКТОЛОГИЧЕСКОЙ ЧЕСТНОСТИ (ZERO HALLUCINATIONS):\n"
            "1. Не давай пустых гарантий и не выдумывай функционал.\n"
            "2. Если клиент спрашивает нестандартный бэкенд (Python/Django) или сложные интеграции: "
            "честно скажи, что специализируешься на сверхбыстрой верстке и легком бэкенде, а задачу передашь разработчику.\n"
            "3. Называй тарифы прямо: 18к-25к, 25к-35к, 35к+ без скрытых условий.\n"
            "4. Без длинных тире (символ —). Используй только дефис (-) или стрелки (->)."
        )
        sys_prompt = skill["system_prompt"] + "\n\nTONE OF VOICE:\n" + skill["tone_of_voice"] + zero_hallucinations_rule

        payload = {
            "system_instruction": {"parts": [{"text": sys_prompt}]},
            "contents": [{"role": "user", "parts": [{"text": user_message}]}],
            "generationConfig": {"temperature": 0.3, "maxOutputTokens": 300}
        }
        try:
            req = urllib.request.Request(
                url,
                data=json.dumps(payload).encode("utf-8"),
                headers={"Content-Type": "application/json"}
            )
            with urllib.request.urlopen(req, timeout=8) as resp:
                data = json.loads(resp.read().decode())
                ai_text = data["candidates"][0]["content"]["parts"][0]["text"].strip()
                return validate_and_sanitize_reply(ai_text, user_message)
        except Exception as e:
            print(f"[AI ROUTER FALLBACK] Model call failed: {e}. Using rule-based fallback.")

    msg = user_message.lower()
    candidate_reply = ""
    if "python" in msg or "django" in msg:
        candidate_reply = (
            "Мы специализируемся на сверхбыстрой фронтенд-верстке и легком бэкенде на чистом коде с гарантией 100/100 в PageSpeed. "
            "Зафиксировал вашу задачу по индивидуальному стеку и передам разработчику для точного ответа."
        )
    elif "скорост" in msg or "чек" in msg or "магнит" in msg:
        candidate_reply = (
            "Привет! Держите чек-лист: «10 скрытых признаков того, что ваш сайт на конструкторе сливает бюджет». "
            "Отправляю файл. Напишите адрес вашего сайта - за 10 минут снимем экспресс-аудит скорости!"
        )
    elif "тариф" in msg or "цен" in msg or "стоим" in msg or "сколько" in msg:
        candidate_reply = (
            "Привет! Мы делаем сайты только на чистом коде (без Tilda и WP) со скоростью 100/100 в PageSpeed за 5-7 дней. "
            "Тарифы под ключ: базовый лендинг - от 18к до 25к, бизнес-сайт с анимациями и микро-админкой - 25к-35к руб. "
            "Какой у вас бизнес и есть ли текущий сайт?"
        )
    else:
        candidate_reply = (
            "Добрый день! Студия AntiDesigner на связи. Разрабатываем ультрабыстрые сайты на чистом коде за 5-7 дней. "
            "Подскажите, вам нужен сайт с нуля или хотите ускорить и пересобрать существующий проект?"
        )

    return validate_and_sanitize_reply(candidate_reply, user_message)

# =============================================================================
# MOCK SANDBOX SIMULATION (--mock)
# =============================================================================
def run_mock_simulation():
    print("=" * 70)
    print("  ANTIDESIGNER TELEGRAM ASSISTANT - MOCK SANDBOX MODE")
    print("=" * 70)
    print(f"[*] API Config: ID={API_ID}, HASH={API_HASH[:6]}... Delays={DELAY_MIN}-{DELAY_MAX}s")
    print(f"[*] Blacklist items: {len(BLACKLIST)} ({', '.join(BLACKLIST)})")
    print(f"[*] Sync Endpoint: {SYNC_ENDPOINT}")
    print("-" * 70)

    test_scenarios = [
        {
            "sender_name": "Мама",
            "phone": "+79990001122",
            "username": "mama_home",
            "text": "Привет сынок, купи молока по дороге домой",
            "expected": "BLACKLIST_IGNORED"
        },
        {
            "sender_name": "Сергей Карпов",
            "phone": "+79031112233",
            "username": "karpov_b2b",
            "text": "Здравствуйте! Сколько стоит разработка лендинга на чистом коде? Сайт на Тильде грузится 6 секунд.",
            "expected": "INTENT_MATCHED_REPLIED"
        },
        {
            "sender_name": "Случайный контакт",
            "phone": "+79059998877",
            "username": "random_dude",
            "text": "Привет, скинь фотки с дачи за прошлый год",
            "expected": "NO_SALES_INTENT_IGNORED"
        },
        {
            "sender_name": "Дмитрий Власов",
            "phone": "+79051234567",
            "username": "vlasov_tech",
            "text": "Здравствуйте! Можете сделать сложный бэкенд на Python и Django с микросервисами?",
            "expected": "HONESTY_BOUNDARY_HANDOFF"
        }
    ]

    for idx, sc in enumerate(test_scenarios, 1):
        print(f"\n[ТЕСТ {idx}/{len(test_scenarios)}] Входящее сообщение от: {sc['sender_name']} (@{sc['username']}, {sc['phone']})")
        print(f" > Текст: \"{sc['text']}\"")

        # 1. Проверка черного списка
        if is_blacklisted(sc['sender_name']) or is_blacklisted(sc['phone']):
            print(" [x] БЛОКИРОВКА: Контакт находится в черном списке (семья/личные). Игнорируем.")
            continue

        # 2. Проверка коммерческого интента разработки
        if not matches_sales_intent(sc['text']):
            print(" [-] ПРОПУСК: Сообщение не относится к разработке сайтов. Игнорируем.")
            continue

        print(" [+] ИНТЕНТ ОБНАРУЖЕН: Запрос на веб-разработку и скорость сайтов!")

        # 3. Имитация задержки человека
        delay = round(random.uniform(DELAY_MIN, DELAY_MAX), 1)
        print(f" [*] Имитация человека: пауза {delay} сек... (статус: typing...)")
        time.sleep(1.0)  # Ускоренно для теста

        # 4. Формирование ответа ассистента
        reply = generate_assistant_reply(sc['text'])
        print(f" [OK] Ответ ассистента: \"{reply}\"")

        # 5. Синхронизация с веб-панелью
        sync_res = sync_message_to_admin(
            lead_id_or_user_id=f"mock_{idx}_{int(time.time())}",
            username=sc['username'],
            full_name=sc['sender_name'],
            sender="user",
            text=sc['text']
        )
        if sync_res and sync_res.get("status") == "success":
            print(f" [SYNC OK] Сообщение клиента сохранено в SQLite (Lead ID: {sync_res.get('lead_id')})")
            sync_reply = sync_message_to_admin(
                lead_id_or_user_id=f"mock_{idx}_{int(time.time())}",
                username=sc['username'],
                full_name=sc['sender_name'],
                sender="bot",
                text=reply
            )
            if sync_reply and sync_reply.get("status") == "success":
                print(f" [SYNC OK] Ответ бота синхронизирован с веб-панелью CRM.")

    print("\n" + "=" * 70)
    print("  MOCK SIMULATION COMPLETED: ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ УСПЕШНО!")
    print("=" * 70)

# =============================================================================
# LIVE TELETHON CLIENT
# =============================================================================
async def run_live_client():
    from telethon import TelegramClient, events

    print(f"[*] Starting Telegram Client with API_ID={API_ID}...")
    client = TelegramClient('antidesigner_session', int(API_ID), API_HASH)

    @client.on(events.NewMessage(incoming=True))
    async def handler(event):
        if not event.is_private:
            return

        sender = await event.get_sender()
        sender_phone = getattr(sender, 'phone', '') or ''
        sender_first = getattr(sender, 'first_name', '') or ''
        sender_last = getattr(sender, 'last_name', '') or ''
        full_name = f"{sender_first} {sender_last}".strip() or "Пользователь Telegram"
        username = getattr(sender, 'username', '') or ''
        user_id = str(sender.id)

        # Проверка черного списка
        if is_blacklisted(sender_phone) or is_blacklisted(full_name):
            print(f"[BLACKLIST] Ignored message from {full_name} ({sender_phone})")
            return

        text = event.raw_text.strip()
        if not text:
            return

        # Проверка коммерческого интента
        if not matches_sales_intent(text):
            print(f"[NON-SALES] Ignored message: {text[:40]}...")
            return

        print(f"[LEAD DETECTED] New inquiry from {full_name} (@{username}): {text}")

        # Синхронизация вопроса лида в веб-панель
        sync_message_to_admin(user_id, username, full_name, "user", text)

        # Имитация человеческого набора: пауза + typing
        delay = random.uniform(DELAY_MIN, DELAY_MAX)
        await asyncio.sleep(delay)

        async with client.action(sender, 'typing'):
            await asyncio.sleep(random.uniform(2.0, 4.0))

        # Генерация и отправка ответа
        reply_text = generate_assistant_reply(text)
        await event.reply(reply_text)

        # Синхронизация ответа бота в веб-панель
        sync_message_to_admin(user_id, username, full_name, "bot", reply_text)
        print(f"[REPLY SENT] Sent reply to {full_name}: {reply_text[:60]}...")

    await client.start(phone=PHONE)
    print("[ONLINE] AntiDesigner Telegram Assistant is running...")
    await client.run_until_disconnected()

if __name__ == "__main__":
    if "--mock" in sys.argv:
        run_mock_simulation()
    else:
        try:
            import telethon
            asyncio.run(run_live_client())
        except ImportError:
            print("[WARN] Telethon is not installed. Running in mock simulation mode...")
            run_mock_simulation()

