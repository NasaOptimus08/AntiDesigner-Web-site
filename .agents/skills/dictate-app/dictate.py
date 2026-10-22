#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Диктовка — системная голосовая печать для macOS.

Горячая клавиша -> запись микрофона -> Whisper (large-v3-turbo, локально, офлайн)
-> текст сам вставляется в активное окно (Telegram, Claude Code, редактор и т.д.).

Бесплатно, без лимитов, только русский. Запуск: ./run.sh
"""

import os
import subprocess
import sys
import tempfile
import threading
import time

from pynput import keyboard

# ============================ НАСТРОЙКИ ============================

# Модель Whisper (можно заменить на ggml-small.bin для скорости в ущерб качеству)
WHISPER_BIN = "/opt/homebrew/bin/whisper-cli"
MODEL = os.path.expanduser("~/.cache/whisper-models/ggml-large-v3-turbo.bin")

# Язык распознавания
LANGUAGE = "ru"

# Предпочтения микрофона по имени (первый найденный подключённый = победит).
# Порядок = приоритет. Если ни один не найден — берётся встроенный.
MIC_PREFERENCE = ["RODE", "МИКРОФОН MACBOOK", "MACBOOK", "IPHONE"]

# Как вставлять текст: "paste" (Cmd+V, быстро и надёжно) или "type" (посимвольно).
INSERT_MODE = "paste"

# Горячая клавиша (toggle: нажал — пишет, нажал ещё раз — распознаёт и вставляет).
# Формат pynput. Примеры: '<cmd>+<shift>+d', '<f17>', '<ctrl>+<alt>+space'
HOTKEY = "<cmd>+<shift>+d"

# Звуковые сигналы (нативные звуки macOS)
SOUND_START = "/System/Library/Sounds/Tink.aiff"
SOUND_STOP = "/System/Library/Sounds/Pop.aiff"
SOUND_DONE = "/System/Library/Sounds/Glass.aiff"
SOUND_ERR = "/System/Library/Sounds/Basso.aiff"

# Показывать macOS-уведомление с результатом
NOTIFY = True

# ==================================================================

TMPDIR = tempfile.mkdtemp(prefix="dictate_")
WAV = os.path.join(TMPDIR, "rec.wav")


def sound(path):
    try:
        subprocess.Popen(["afplay", path],
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception:
        pass


def notify(text, title="Диктовка"):
    if not NOTIFY:
        return
    text = text.replace('"', "'")[:220]
    try:
        subprocess.Popen(
            ["osascript", "-e",
             f'display notification "{text}" with title "{title}"'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception:
        pass


def find_mic_index():
    """Возвращает индекс avfoundation-устройства согласно MIC_PREFERENCE."""
    try:
        out = subprocess.run(
            ["ffmpeg", "-hide_banner", "-f", "avfoundation",
             "-list_devices", "true", "-i", ""],
            capture_output=True, text=True, timeout=10).stderr
    except Exception:
        return "0"

    devices = []  # (index, name_upper)
    in_audio = False
    for line in out.splitlines():
        if "AVFoundation audio devices" in line:
            in_audio = True
            continue
        if in_audio and "] [" in line:
            try:
                idx = line.split("] [")[1].split("]")[0]
                name = line.split("] ", 2)[-1].strip().upper()
                devices.append((idx, name))
            except Exception:
                pass

    for pref in MIC_PREFERENCE:
        for idx, name in devices:
            if pref in name:
                print(f"  🎙  Микрофон: [{idx}] {name.title()}")
                return idx
    if devices:
        idx, name = devices[0]
        print(f"  🎙  Микрофон (по умолчанию): [{idx}] {name.title()}")
        return idx
    return "0"


class Dictation:
    def __init__(self):
        self.recording = False
        self.proc = None
        self.mic = find_mic_index()
        self.lock = threading.Lock()

    def toggle(self):
        with self.lock:
            if self.recording:
                self._stop_and_transcribe()
            else:
                self._start()

    def _start(self):
        self.recording = True
        try:
            if os.path.exists(WAV):
                os.remove(WAV)
        except Exception:
            pass
        # Запись: моно, 16 кГц (как нужно whisper.cpp)
        self.proc = subprocess.Popen(
            ["ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
             "-f", "avfoundation", "-i", f":{self.mic}",
             "-ar", "16000", "-ac", "1", WAV],
            stdin=subprocess.PIPE,
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        sound(SOUND_START)
        print("● Запись... (нажми клавишу ещё раз, чтобы закончить)")

    def _stop_and_transcribe(self):
        self.recording = False
        sound(SOUND_STOP)
        print("… Распознаю")
        # Мягко завершаем ffmpeg (q), чтобы корректно закрыть WAV
        try:
            if self.proc and self.proc.poll() is None:
                try:
                    self.proc.communicate(input=b"q", timeout=2)
                except Exception:
                    self.proc.terminate()
                    self.proc.wait(timeout=3)
        except Exception:
            try:
                self.proc.kill()
            except Exception:
                pass

        if not os.path.exists(WAV) or os.path.getsize(WAV) < 2000:
            print("  (пусто — ничего не записалось)")
            sound(SOUND_ERR)
            notify("Ничего не записалось")
            return

        # Транскрибация в отдельном потоке, чтобы не блокировать слушатель клавиш
        threading.Thread(target=self._transcribe, daemon=True).start()

    def _transcribe(self):
        t0 = time.time()
        try:
            res = subprocess.run(
                [WHISPER_BIN, "-m", MODEL, "-f", WAV,
                 "-l", LANGUAGE, "-nt", "-np", "-otxt",
                 "-of", os.path.join(TMPDIR, "out")],
                capture_output=True, text=True, timeout=300)
        except Exception as e:
            print(f"  ошибка whisper: {e}")
            sound(SOUND_ERR)
            notify("Ошибка распознавания")
            return

        txt_path = os.path.join(TMPDIR, "out.txt")
        text = ""
        if os.path.exists(txt_path):
            with open(txt_path, encoding="utf-8") as f:
                text = f.read()
        if not text.strip():
            text = res.stdout

        text = clean(text)
        if not text:
            print("  (тишина)")
            sound(SOUND_ERR)
            notify("Речь не распознана")
            return

        dt = time.time() - t0
        print(f'  ✓ ({dt:.1f}с) → "{text}"')
        insert_text(text)
        sound(SOUND_DONE)
        notify(text)


def clean(text):
    # whisper.cpp иногда оставляет служебные метки/пустые скобки
    lines = [l.strip() for l in text.splitlines()]
    text = " ".join(l for l in lines if l)
    for junk in ["[BLANK_AUDIO]", "[ Пауза ]", "[música]", "(музыка)"]:
        text = text.replace(junk, "")
    return text.strip()


def insert_text(text):
    # Копируем в буфер и вставляем в активное окно.
    if INSERT_MODE == "type":
        try:
            subprocess.run(
                ["osascript", "-e",
                 f'tell application "System Events" to keystroke {applescript_str(text)}'],
                timeout=30)
            return
        except Exception:
            pass  # fallback на paste

    # paste: сохраняем старый буфер -> кладём текст -> Cmd+V -> восстанавливаем
    old = None
    try:
        old = subprocess.run(["pbpaste"], capture_output=True).stdout
    except Exception:
        pass
    p = subprocess.Popen(["pbcopy"], stdin=subprocess.PIPE)
    p.communicate(text.encode("utf-8"))
    time.sleep(0.12)
    subprocess.run(
        ["osascript", "-e",
         'tell application "System Events" to keystroke "v" using command down'])
    if old is not None:
        time.sleep(0.35)
        p = subprocess.Popen(["pbcopy"], stdin=subprocess.PIPE)
        p.communicate(old)


def applescript_str(text):
    return '"' + text.replace("\\", "\\\\").replace('"', '\\"') + '"'


def main():
    print("=" * 52)
    print("  🎤  Диктовка запущена")
    d = Dictation()
    print(f"  🔤  Модель: {os.path.basename(MODEL)}  ·  язык: {LANGUAGE}")
    print(f"  ⌨️   Горячая клавиша: {HOTKEY}")
    print(f"  📋  Вставка: {INSERT_MODE}")
    print("  ⏹  Выход: Ctrl+C в этом окне")
    print("=" * 52)
    notify("Готова. Жми " + HOTKEY, "Диктовка запущена")

    with keyboard.GlobalHotKeys({HOTKEY: d.toggle}) as h:
        h.join()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nВыход.")
        sys.exit(0)
