#!/bin/bash
# Запуск утилиты диктовки
cd "$(dirname "$0")"
exec ./.venv/bin/python dictate.py
