#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
import sys
import zipfile
import sqlite3

output_file = sys.argv[1] if len(sys.argv) > 1 else "antidesigner-tg-agent.zip"
agent_dir = os.path.dirname(os.path.abspath(__file__))
project_dir = os.path.dirname(agent_dir)
db_path = os.path.join(project_dir, "core-data", "database.sqlite")

# Read current config from sqlite
cfg = {
    "api_id": "2847192",
    "api_hash": "8f7e6d5c4b3a2109fedcba9876543210",
    "delay_min": 3,
    "delay_max": 7,
    "blacklist": "+79990001122,Мама,Брат,Музыка"
}

if os.path.exists(db_path):
    try:
        conn = sqlite3.connect(db_path)
        cur = conn.cursor()
        row = cur.execute("SELECT api_id, api_hash, typing_delay_min, typing_delay_max, blacklist_phones FROM tg_assistant_config LIMIT 1").fetchone()
        if row:
            cfg["api_id"] = row[0] or cfg["api_id"]
            cfg["api_hash"] = row[1] or cfg["api_hash"]
            cfg["delay_min"] = row[2] or cfg["delay_min"]
            cfg["delay_max"] = row[3] or cfg["delay_max"]
            cfg["blacklist"] = row[4] or cfg["blacklist"]
        conn.close()
    except Exception:
        pass

env_content = f"""# AntiDesigner Telegram Assistant VPS Configuration
TG_API_ID={cfg['api_id']}
TG_API_HASH={cfg['api_hash']}
TG_PHONE=
SYNC_ENDPOINT=http://localhost:8000/lk-admin/api/sync_chat.php
DELAY_MIN={cfg['delay_min']}
DELAY_MAX={cfg['delay_max']}
BLACKLIST_PHONES={cfg['blacklist']}
"""

with zipfile.ZipFile(output_file, "w", zipfile.ZIP_DEFLATED) as zf:
    for f in ["client_agent.py", "requirements.txt", "anti-tg-agent.service", "README.md"]:
        f_path = os.path.join(agent_dir, f)
        if os.path.exists(f_path):
            zf.write(f_path, f)
    zf.writestr(".env", env_content)

print(f"Package created: {output_file} ({os.path.getsize(output_file)} bytes)")

