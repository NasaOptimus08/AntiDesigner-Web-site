#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Диктовка — менюбар-приложение для macOS.

Значок 🎤 в строке меню + всплывающее окошко снизу экрана с анимацией звука.
Горячая клавиша ⌘⇧D (или клик по меню): говоришь -> Whisper (локально) ->
текст вставляется в активное окно. Клик по всплывающему окошку = вставить и
отправить (Enter) — удобно для чатов. Терминал не нужен.
"""

import json
import os
import random
import re
import subprocess
import tempfile
import threading
import time

import rumps

try:
    import Quartz
    QUARTZ_OK = True
except Exception:
    QUARTZ_OK = False

PB_TYPE = "public.utf8-plain-text"


def clip_get():
    try:
        from AppKit import NSPasteboard
        return NSPasteboard.generalPasteboard().stringForType_(PB_TYPE)
    except Exception:
        return None


def clip_set(text):
    try:
        from AppKit import NSPasteboard
        pb = NSPasteboard.generalPasteboard()
        pb.clearContents()
        pb.setString_forType_(text, PB_TYPE)
        return True
    except Exception as e:
        logline(f"clip_set err: {e}")
        return False

# ============================ НАСТРОЙКИ ============================
def _find_bin(name):
    # GUI-приложение не наследует PATH — ищем по явным путям Homebrew
    import shutil
    for p in (f"/opt/homebrew/bin/{name}", f"/usr/local/bin/{name}"):
        if os.path.exists(p):
            return p
    return shutil.which(name) or f"/opt/homebrew/bin/{name}"


FFMPEG = _find_bin("ffmpeg")
WHISPER_BIN = _find_bin("whisper-cli")
MODEL = os.path.expanduser("~/.cache/whisper-models/ggml-large-v3-turbo.bin")
LANGUAGE = "ru"
# Предпочтения реального микрофона по имени (если доступен — берётся он).
MIC_PREFERENCE = ["RODE", "МИКРОФОН MACBOOK", "MACBOOK", "IPHONE"]
# Виртуальные устройства, которые НИКОГДА не использовать для записи.
VIRTUAL_BLOCK = ["LOOM", "ZOOM", "BLACKHOLE", "AGGREGATE", "MULTI-OUTPUT",
                 "MULTIOUTPUT", "SOUNDFLOWER", "BACKGROUND MUSIC", "MATEVIEW"]
INSERT_MODE = "paste"          # "paste" (Cmd+V) или "type"
# Триггер: Option + <клавиша>. Toggle: нажал — пишет, нажал ещё раз — вставляет.
# KEYCODE — код физической клавиши (независимо от раскладки): 2=19, 1=18, 3=20,
# Q=12, W=13, E=14, R=15, пробел=49. Сейчас Option+2.
HOTKEY_KEYCODE = 19
HOTKEY_LABEL = "Option+2"

# Пауза в речи (мс), после которой начинается НОВЫЙ АБЗАЦ. ~1 сек = короткая
# заминка «на подумать». Меньше => чаще делит, больше => реже.
PAUSE_PARAGRAPH_MS = 1000

# Вырезать мычание/заминки («ааа», «эээ», «ммм», «э-э-э»). Мычание при этом
# «занимает» паузу — абзац на его месте НЕ ставится (можно тянуть звук, думая).
REMOVE_FILLERS = True

# Подсказка whisper (биасит распознавание в сторону этих слов/имён).
WHISPER_PROMPT = "Claude Code, Figma, Tilda."  # подсказка модели: впишите свои частые термины

# Автозамены: что whisper услышал неверно -> как надо. Регистронезависимо,
# по целым словам. Можно добавлять свои строки — левое коверкание, правое верно.
# Автозамены: как модель услышала -> как надо. Впишите свои термины парами.
REPLACEMENTS = {
    "клауд код": "Claude Code",
    "клод код": "Claude Code",
    "фигма": "Figma",
}

SOUND_START = "/System/Library/Sounds/Tink.aiff"
SOUND_STOP = "/System/Library/Sounds/Pop.aiff"
SOUND_DONE = "/System/Library/Sounds/Glass.aiff"
SOUND_ERR = "/System/Library/Sounds/Basso.aiff"

ICON_IDLE = "🎤"
ICON_REC = "🔴"
ICON_WORK = "⏳"
# ==================================================================

TMPDIR = tempfile.mkdtemp(prefix="dictate_")
WAV = os.path.join(TMPDIR, "rec.wav")
LOG = os.path.expanduser("~/dictate/app.log")
HISTORY = os.path.expanduser("~/dictate/history.txt")
HISTORY_DAYS = 5   # хранить архив расшифровок столько дней, старше — удалять


def save_history(text):
    """Дописать расшифровку в архив, удалив записи старше HISTORY_DAYS дней."""
    try:
        now = time.time()
        cutoff = now - HISTORY_DAYS * 86400
        kept = []
        if os.path.exists(HISTORY):
            with open(HISTORY, encoding="utf-8") as f:
                content = f.read()
            for stamp, body in re.findall(
                    r"\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\]\n(.*?)"
                    r"(?=\n\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\]|\Z)",
                    content, re.S):
                try:
                    t = time.mktime(time.strptime(stamp, "%Y-%m-%d %H:%M:%S"))
                except Exception:
                    t = now
                if t >= cutoff:
                    kept.append((stamp, body.rstrip("\n")))
        stamp = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(now))
        kept.append((stamp, text))
        with open(HISTORY, "w", encoding="utf-8") as f:
            for s, b in kept:
                f.write(f"[{s}]\n{b}\n\n")
    except Exception as e:
        logline(f"history err: {e}")


def logline(msg):
    try:
        with open(LOG, "a", encoding="utf-8") as f:
            f.write(msg + "\n")
    except Exception:
        pass


def ax_trusted(prompt=False):
    try:
        import HIServices as AX
    except Exception:
        try:
            import ApplicationServices as AX
        except Exception:
            return None
    try:
        if prompt:
            return bool(AX.AXIsProcessTrustedWithOptions(
                {"AXTrustedCheckOptionPrompt": True}))
        return bool(AX.AXIsProcessTrusted())
    except Exception:
        return None


def request_microphone():
    """Явно запросить доступ к микрофону — покажет системный запрос."""
    try:
        import AVFoundation
        AVFoundation.AVCaptureDevice.requestAccessForMediaType_completionHandler_(
            "soun", lambda granted: logline(f"микрофон разрешён={granted}"))
    except Exception as e:
        logline(f"запрос микрофона не удался: {e}")


def find_mic_spec():
    """Возвращает (spec, human_name). spec подставляется в ffmpeg как ':spec'.
    Реальный предпочтительный микрофон -> его индекс; иначе -> 'default'."""
    try:
        out = subprocess.run(
            [FFMPEG, "-hide_banner", "-f", "avfoundation",
             "-list_devices", "true", "-i", ""],
            capture_output=True, text=True, encoding="utf-8",
            errors="ignore", timeout=10).stderr
    except Exception:
        return "default", "системный по умолчанию"

    devices, in_audio = [], False
    for line in out.splitlines():
        if "AVFoundation audio devices" in line:
            in_audio = True
            continue
        if in_audio and "] [" in line:
            try:
                idx = line.split("] [")[1].split("]")[0]
                name = line.split("] ", 2)[-1].strip()
                devices.append((idx, name))
            except Exception:
                pass

    # 1) предпочтительный реальный микрофон
    for pref in MIC_PREFERENCE:
        for idx, name in devices:
            if pref in name.upper():
                return idx, name
    # 2) первый НЕ виртуальный
    for idx, name in devices:
        if not any(v in name.upper() for v in VIRTUAL_BLOCK):
            return idx, name
    # 3) системный по умолчанию (всегда реальный вход)
    return "default", "системный по умолчанию"


JUNK = ["[BLANK_AUDIO]", "[ Пауза ]", "[música]", "(музыка)",
        "Продолжение следует...", "Продолжение следует…",
        "Субтитры сделал DimaTorzok", "Субтитры делал DimaTorzok",
        "Редактор субтитров", "Спасибо за просмотр"]


def strip_junk(t):
    for j in JUNK:
        t = t.replace(j, "")
    return t.strip()


def clean(text):
    # для запасного пути (txt): склеить строки в один абзац
    lines = [l.strip() for l in text.splitlines()]
    return strip_junk(" ".join(l for l in lines if l))


# Галлюцинации whisper на тишине (титры/выходные субтитров) — человек их не диктует
HALLUC_RE = [
    re.compile(r"Редактор\w*\s+субтитров[^\n]*", re.I),
    re.compile(r"Корректор\s+[А-ЯЁ]\.[^\n]*", re.I),
    re.compile(r"Субтитр\w*\s+(?:сделал|делал|подготовил|редактировал|"
               r"добавил|предоставлены|создал)[^\n]*", re.I),
    re.compile(r"Продолжение\s+следует[.\s]*", re.I),
    re.compile(r"Субтитры\s+by[^\n]*", re.I),
]


def remove_hallucinations(text):
    for rx in HALLUC_RE:
        text = rx.sub("", text)
    text = re.sub(r"[ \t]+\n", "\n", text)      # хвостовые пробелы
    text = re.sub(r"\n{3,}", "\n\n", text)      # лишние пустые строки
    return text.strip()


def apply_replacements(text):
    """Автозамены под свою лексику (примеры в словаре REPLACEMENTS сверху)."""
    for wrong, right in REPLACEMENTS.items():
        text = re.sub(r"\b" + re.escape(wrong) + r"\b", right, text, flags=re.I)
    return text


def is_filler(word):
    """Мычание/заминка: 'ааа', 'эээ', 'ммм', 'э-э-э', 'эм', 'хм' и т.п."""
    c = strip_junk(word).lower().strip(" .,!?…-——«»\"'()")
    c = c.replace("-", "").replace(" ", "")
    if not c:
        return True
    # одна буква, повторённая 2+ раза: ааа/эээ/ммм/ооо/ыыы/ннн/ууу
    if len(c) >= 2 and len(set(c)) == 1 and c[0] in "аэоыумнхи":
        return True
    if c in ("эм", "эмм", "эмм", "хм", "гм", "мхм", "эх", "ээм"):
        return True
    return False


def assemble_from_json(json_path, txt_fallback):
    """Пословная сборка: абзацы по РЕАЛЬНЫМ паузам (тишине), мычание вырезается.
    Тайм-коды всех слов (включая мычание) держат «таймлайн», поэтому мычание,
    заполняющее паузу, не даёт разрыва абзаца, но само в текст не попадает."""
    try:
        with open(json_path, encoding="utf-8") as f:
            d = json.load(f)
        segs = d.get("transcription", [])
        parts, prev_to, pending_break = [], None, False
        for s in segs:
            raw = s.get("text", "")
            frm = s["offsets"]["from"]
            to = s["offsets"]["to"]
            # разрыв тишины считаем по ВСЕМ словам (мычание держит таймлайн)
            if prev_to is not None and frm - prev_to >= PAUSE_PARAGRAPH_MS:
                pending_break = True
            prev_to = to
            if not strip_junk(raw):
                continue
            if REMOVE_FILLERS and is_filler(raw):
                continue              # мычание в текст не попадает
            if pending_break and parts:
                parts.append("\n\n" + raw.lstrip())
            else:
                parts.append(raw)
            pending_break = False
        text = apply_replacements(remove_hallucinations("".join(parts).strip()))
        if text:
            return text
    except Exception as e:
        logline(f"assemble err: {e}")
    # запасной путь — плоский txt
    try:
        with open(txt_fallback, encoding="utf-8") as f:
            return clean(f.read())
    except Exception:
        return ""


def press_key(keycode, command=False):
    """Честное событие клавиатуры на уровне HID (принимают все приложения)."""
    if not QUARTZ_OK:
        return False
    try:
        src = Quartz.CGEventSourceCreate(
            Quartz.kCGEventSourceStateCombinedSessionState)
        down = Quartz.CGEventCreateKeyboardEvent(src, keycode, True)
        up = Quartz.CGEventCreateKeyboardEvent(src, keycode, False)
        if command:
            Quartz.CGEventSetFlags(down, Quartz.kCGEventFlagMaskCommand)
            Quartz.CGEventSetFlags(up, Quartz.kCGEventFlagMaskCommand)
        else:
            Quartz.CGEventSetFlags(down, 0)
            Quartz.CGEventSetFlags(up, 0)
        Quartz.CGEventPost(Quartz.kCGHIDEventTap, down)
        time.sleep(0.03)
        Quartz.CGEventPost(Quartz.kCGHIDEventTap, up)
        return True
    except Exception as e:
        logline(f"press_key err: {e}")
        return False


def insert_text(text, send=False):
    if INSERT_MODE == "type":
        try:
            subprocess.run(
                ["osascript", "-e",
                 'tell application "System Events" to keystroke ' +
                 '"' + text.replace("\\", "\\\\").replace('"', '\\"') + '"'],
                timeout=30)
        except Exception:
            pass
    else:
        old = clip_get()   # сохранить текущий буфер (нативно, без кодировочных проблем)
        clip_set(text)     # положить наш текст
        time.sleep(0.25)
        # Cmd+V честным HID-событием (V = keycode 9)
        if press_key(9, command=True):
            logline("вставка Cmd+V (HID) выполнена")
        else:
            subprocess.run(["osascript", "-e",
                            'tell application "System Events" to keystroke "v" using command down'])
            logline("вставка Cmd+V (osascript) выполнена")
        if send:
            time.sleep(0.25)
            press_key(36)  # Enter
        # восстановить буфер с запасом по времени, чтобы не перебить вставку
        if old is not None:
            time.sleep(1.2)
            clip_set(old)
        return


def sound(path):
    try:
        subprocess.Popen(["afplay", path],
                         stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except Exception:
        pass


# Глушить вывод звука (музыку) на время диктовки
MUTE_WHILE_RECORDING = True


def audio_is_muted():
    try:
        r = subprocess.run(
            ["osascript", "-e", "output muted of (get volume settings)"],
            capture_output=True, text=True, timeout=3)
        return "true" in r.stdout.lower()
    except Exception:
        return False


def set_output_muted(muted):
    try:
        subprocess.run(
            ["osascript", "-e",
             f"set volume output muted {'true' if muted else 'false'}"],
            timeout=3)
    except Exception:
        pass


# ====================== Всплывающее окошко (HUD) ======================
HUD = {"panel": None, "view": None}

try:
    import objc
    from AppKit import (NSPanel, NSColor, NSView, NSScreen, NSBezierPath,
                        NSFont, NSFontAttributeName, NSForegroundColorAttributeName,
                        NSString, NSEvent)
    from Foundation import NSMakeRect, NSMakePoint

    class WaveView(NSView):
        def initWithFrame_(self, frame):
            self = objc.super(WaveView, self).initWithFrame_(frame)
            if self is None:
                return None
            self.levels = [0.25] * 26
            self.status = "Слушаю…"
            self.hint = "✕ отмена · клик — отправить"
            self.onclick = None
            self.oncancel = None
            return self

        def acceptsFirstMouse_(self, e):
            return True

        def mouseDown_(self, event):
            try:
                p = self.convertPoint_fromView_(event.locationInWindow(), None)
                b = self.bounds()
                # ✕ в правом верхнем углу = отмена
                if (p.x >= b.size.width - 44 and p.y >= b.size.height - 44):
                    cb = getattr(self, "oncancel", None)
                else:
                    cb = getattr(self, "onclick", None)
                if cb:
                    cb()
            except Exception:
                pass

        def drawRect_(self, rect):
            b = self.bounds()
            w, h = b.size.width, b.size.height
            # фон-пилюля
            NSColor.colorWithCalibratedWhite_alpha_(0.10, 0.97).set()
            NSBezierPath.bezierPathWithRoundedRect_xRadius_yRadius_(b, 20, 20).fill()
            # заголовок
            tattr = {
                NSFontAttributeName: NSFont.systemFontOfSize_(14),
                NSForegroundColorAttributeName: NSColor.whiteColor(),
            }
            NSString.stringWithString_(self.status).drawAtPoint_withAttributes_(
                NSMakePoint(22, h - 30), tattr)
            # подсказка
            hattr = {
                NSFontAttributeName: NSFont.systemFontOfSize_(11),
                NSForegroundColorAttributeName:
                    NSColor.colorWithCalibratedWhite_alpha_(0.6, 1.0),
            }
            NSString.stringWithString_(self.hint).drawAtPoint_withAttributes_(
                NSMakePoint(22, 12), hattr)
            # ✕ отмена (правый верхний угол)
            xattr = {
                NSFontAttributeName: NSFont.systemFontOfSize_(15),
                NSForegroundColorAttributeName:
                    NSColor.colorWithCalibratedWhite_alpha_(0.85, 1.0),
            }
            NSString.stringWithString_("✕").drawAtPoint_withAttributes_(
                NSMakePoint(w - 28, h - 28), xattr)
            # звуковые полоски
            n = len(self.levels)
            bw, gap = 3.0, 3.0
            total = n * (bw + gap)
            x = w - total - 20
            cy = h / 2 + 4
            NSColor.colorWithCalibratedRed_green_blue_alpha_(0.30, 0.80, 1.0, 1.0).set()
            for lv in self.levels:
                bh = max(3.0, lv * (h * 0.5))
                r = NSMakeRect(x, cy - bh / 2, bw, bh)
                NSBezierPath.bezierPathWithRoundedRect_xRadius_yRadius_(r, 1.5, 1.5).fill()
                x += bw + gap

    def screen_with_mouse():
        try:
            loc = NSEvent.mouseLocation()
            for s in NSScreen.screens():
                f = s.frame()
                if (f.origin.x <= loc.x <= f.origin.x + f.size.width and
                        f.origin.y <= loc.y <= f.origin.y + f.size.height):
                    return s
        except Exception:
            pass
        return NSScreen.mainScreen()

    def hud_reposition():
        try:
            panel = HUD["panel"]
            if panel is None:
                return
            f = screen_with_mouse().visibleFrame()
            W, H = 380, 92
            x = f.origin.x + (f.size.width - W) / 2.0
            y = f.origin.y + 110
            panel.setFrameOrigin_(NSMakePoint(x, y))
        except Exception:
            pass

    def hud_build():
        if HUD["panel"] is not None:
            return
        W, H = 380, 92
        rect = NSMakeRect(0, 0, W, H)
        # borderless(0) | nonactivating(128), backing buffered(2)
        panel = NSPanel.alloc().initWithContentRect_styleMask_backing_defer_(
            rect, 128, 2, False)
        panel.setLevel_(25)               # статус-уровень (поверх окон)
        panel.setOpaque_(False)
        panel.setBackgroundColor_(NSColor.clearColor())
        panel.setHasShadow_(True)
        panel.setCollectionBehavior_(1 | 16)   # все Spaces + стационарно
        panel.setIgnoresMouseEvents_(False)
        view = WaveView.alloc().initWithFrame_(NSMakeRect(0, 0, W, H))
        panel.setContentView_(view)
        HUD["panel"], HUD["view"] = panel, view

    def hud_show(status, hint, onclick, oncancel=None):
        try:
            hud_build()
            hud_reposition()
            v = HUD["view"]
            v.status = status
            v.hint = hint
            v.onclick = onclick
            v.oncancel = oncancel
            HUD["panel"].orderFrontRegardless()
            v.setNeedsDisplay_(True)
        except Exception as e:
            logline(f"hud_show err: {e}")

    def hud_set(status, hint=None):
        try:
            v = HUD["view"]
            if v is None:
                return
            v.status = status
            if hint is not None:
                v.hint = hint
            v.setNeedsDisplay_(True)
        except Exception:
            pass

    def hud_hide():
        try:
            if HUD["panel"]:
                HUD["panel"].orderOut_(None)
        except Exception:
            pass

    def hud_animate():
        try:
            v = HUD["view"]
            if v is None:
                return
            lv = v.levels
            lv = [max(0.12, min(1.0, x + random.uniform(-0.5, 0.5))) for x in lv]
            v.levels = lv
            v.setNeedsDisplay_(True)
        except Exception:
            pass

except Exception as _e:  # AppKit недоступен — работаем без окошка
    def hud_show(*a, **k): pass
    def hud_set(*a, **k): pass
    def hud_hide(*a, **k): pass
    def hud_animate(*a, **k): pass


def on_main(func):
    """Выполнить в главном потоке (UI)."""
    try:
        from PyObjCTools import AppHelper
        AppHelper.callAfter(func)
    except Exception:
        func()


# ============================ Приложение ============================
class DictateApp(rumps.App):
    def __init__(self):
        super().__init__(ICON_IDLE, quit_button=None)
        self.recording = False
        self.send_after = False
        self.proc = None
        self.lock = threading.Lock()
        self.mic_spec, self.mic_name = "default", "…"

        self.item_toggle = rumps.MenuItem("● Начать запись  (Option+2)", callback=self.on_toggle)
        self.item_status = rumps.MenuItem("Запуск…")
        self.item_status.set_callback(None)
        self.item_mic = rumps.MenuItem("Микрофон: …")
        self.item_mic.set_callback(None)
        self.last_text = ""
        self.item_last = rumps.MenuItem("📋 Копировать последний текст",
                                        callback=self.on_copy_last)
        self.item_history = rumps.MenuItem("📜 История диктовок (5 дней)",
                                           callback=self.on_history)
        self.menu = [
            self.item_toggle, None,
            self.item_status, self.item_mic, self.item_last,
            self.item_history, None,
            rumps.MenuItem("Выход", callback=self.on_quit),
        ]

        self.tap = None
        self.tap_source = None
        self._tap_warned = False
        self._prev_muted = False

        logline(f"=== старт {time.strftime('%H:%M:%S')} ===")
        logline(f"AXIsProcessTrusted = {ax_trusted()}")

        # Запрос доступов (микрофон явно, доступ — с подсказкой)
        request_microphone()
        ax_trusted(prompt=True)

        self._last_trust = None
        self.timer = rumps.Timer(self._tick, 2)
        self.timer.start()
        self.anim = rumps.Timer(self._anim_tick, 0.09)
        self.anim.start()

    # ---------- таймеры ----------
    def _tick(self, _=None):
        t = ax_trusted()
        if t != self._last_trust:
            logline(f"[{time.strftime('%H:%M:%S')}] Универсальный доступ -> {t}")
            self._last_trust = t
        # Перехват клавиши поднимаем/опускаем по факту доступа — без перезапуска
        if t and self.tap is None:
            self._start_listener()
        elif not t and self.tap is not None:
            self._stop_listener()
        if self.recording:
            return
        if not t:
            self.item_status.title = "⚠️ Вкл. «Универсальный доступ»"
        elif self.tap is None:
            self.item_status.title = "⚠️ Вкл. «Мониторинг ввода» в Настройках"
        else:
            self.item_status.title = f"✅ Готов — {HOTKEY_LABEL}"

    def _anim_tick(self, _=None):
        if self.recording:
            hud_animate()

    # ---------- клавиша (Option+2 через event-tap с подавлением) ----------
    def _tap_callback(self, proxy, type_, event, refcon):
        try:
            if type_ in (Quartz.kCGEventTapDisabledByTimeout,
                         Quartz.kCGEventTapDisabledByUserInput):
                if self.tap:
                    Quartz.CGEventTapEnable(self.tap, True)
                return event
            if type_ == Quartz.kCGEventKeyDown:
                keycode = Quartz.CGEventGetIntegerValueField(
                    event, Quartz.kCGKeyboardEventKeycode)
                flags = Quartz.CGEventGetFlags(event)
                option = bool(flags & Quartz.kCGEventFlagMaskAlternate)
                command = bool(flags & Quartz.kCGEventFlagMaskCommand)
                control = bool(flags & Quartz.kCGEventFlagMaskControl)
                if keycode == HOTKEY_KEYCODE and option and not command and not control:
                    logline(f"[{time.strftime('%H:%M:%S')}] {HOTKEY_LABEL}")
                    threading.Thread(target=self.on_toggle, daemon=True).start()
                    return None  # подавляем — символ не напечатается
        except Exception as e:
            logline(f"tap cb err: {e}")
        return event

    def _start_listener(self):
        if not QUARTZ_OK:
            logline("Quartz недоступен — перехват не поднят")
            return
        try:
            # ТОЛЬКО keyDown. Служебные disabled-события приходят всегда,
            # их в маску добавлять нельзя (константы огромные -> overflow).
            mask = Quartz.CGEventMaskBit(Quartz.kCGEventKeyDown)
            self.tap = Quartz.CGEventTapCreate(
                Quartz.kCGSessionEventTap, Quartz.kCGHeadInsertEventTap,
                Quartz.kCGEventTapOptionDefault, mask, self._tap_callback, None)
            if not self.tap:
                if not self._tap_warned:
                    logline("tap не создан — нужен «Мониторинг ввода» (Input Monitoring)")
                    self._tap_warned = True
                self.tap = None
                return
            self._tap_warned = False
            self.tap_source = Quartz.CFMachPortCreateRunLoopSource(None, self.tap, 0)
            Quartz.CFRunLoopAddSource(
                Quartz.CFRunLoopGetCurrent(), self.tap_source,
                Quartz.kCFRunLoopCommonModes)
            Quartz.CGEventTapEnable(self.tap, True)
            logline(f"[{time.strftime('%H:%M:%S')}] перехват {HOTKEY_LABEL} запущен")
        except Exception as e:
            logline(f"tap err: {e}")
            self.tap = None

    def _stop_listener(self):
        try:
            if self.tap:
                Quartz.CGEventTapEnable(self.tap, False)
            if self.tap_source:
                Quartz.CFRunLoopRemoveSource(
                    Quartz.CFRunLoopGetCurrent(), self.tap_source,
                    Quartz.kCFRunLoopCommonModes)
        except Exception:
            pass
        self.tap = None
        self.tap_source = None

    # ---------- действия ----------
    def on_toggle(self, _=None):
        with self.lock:
            if self.recording:
                self._stop()
            else:
                self._start()

    def _hud_click(self):
        # клик по окошку = стоп + вставить + отправить
        with self.lock:
            if self.recording:
                self.send_after = True
                self._stop()

    def _hud_cancel(self):
        # клик по ✕ = бросить запись без расшифровки
        with self.lock:
            if self.recording:
                self._cancel()

    def _restore_audio(self):
        if MUTE_WHILE_RECORDING:
            set_output_muted(self._prev_muted)

    def _cancel(self):
        self.recording = False
        self.item_toggle.title = "● Начать запись  (Option+2)"
        self.title = ICON_IDLE
        self.item_status.title = "Отменено"
        logline(f"[{time.strftime('%H:%M:%S')}] запись отменена")
        try:
            if self.proc and self.proc.poll() is None:
                self.proc.kill()        # жёстко — не финализируем и не распознаём
        except Exception:
            pass
        self._restore_audio()
        sound(SOUND_STOP)
        on_main(hud_hide)

    def _start(self):
        # выбрать реальный микрофон (после выдачи доступа список появится)
        self.mic_spec, self.mic_name = find_mic_spec()
        self.item_mic.title = f"Микрофон: {self.mic_name}"
        self.recording = True
        self.send_after = False
        try:
            if os.path.exists(WAV):
                os.remove(WAV)
        except Exception:
            pass
        self.proc = subprocess.Popen(
            [FFMPEG, "-hide_banner", "-loglevel", "error", "-y",
             "-f", "avfoundation", "-i", f":{self.mic_spec}",
             "-ar", "16000", "-ac", "1", WAV],
            stdin=subprocess.PIPE,
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.item_toggle.title = "■ Остановить  (Option+2)"
        self.title = ICON_REC
        self.item_status.title = "● Запись…"
        sound(SOUND_START)
        if MUTE_WHILE_RECORDING:
            self._prev_muted = audio_is_muted()
            set_output_muted(True)      # заглушить музыку на время диктовки
        on_main(lambda: hud_show(
            "🎤 Слушаю…", "Option+2 — готово · ✕ — отмена",
            self._hud_click, self._hud_cancel))

    def _stop(self):
        self.recording = False
        self.item_toggle.title = "● Начать запись  (Option+2)"
        self.title = ICON_WORK
        self.item_status.title = "⏳ Распознаю…"
        sound(SOUND_STOP)
        on_main(lambda: hud_set("⏳ Распознаю…", " "))
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
            self.title = ICON_IDLE
            self.item_status.title = "Пусто — ничего не записалось"
            self._restore_audio()
            sound(SOUND_ERR)
            on_main(hud_hide)
            return
        threading.Thread(target=self._transcribe, daemon=True).start()

    def _transcribe(self):
        send = self.send_after
        try:
            cmd = [WHISPER_BIN, "-m", MODEL, "-f", WAV,
                   "-l", LANGUAGE, "-np", "-ml", "1", "-sow", "-oj", "-otxt",
                   "-of", os.path.join(TMPDIR, "out")]
            if WHISPER_PROMPT.strip():
                cmd += ["--prompt", WHISPER_PROMPT]
            subprocess.run(cmd, capture_output=True, text=True,
                           encoding="utf-8", errors="ignore", timeout=300)
        except Exception as e:
            logline(f"whisper err: {e}")
            self.title = ICON_IDLE
            self.item_status.title = "Ошибка распознавания"
            self._restore_audio()
            sound(SOUND_ERR)
            on_main(hud_hide)
            return

        # Собираем текст, разбивая на абзацы по паузам (JSON), txt — запасной
        text = assemble_from_json(
            os.path.join(TMPDIR, "out.json"),
            os.path.join(TMPDIR, "out.txt"))
        if not text:
            self.title = ICON_IDLE
            self.item_status.title = "Речь не распознана"
            self._restore_audio()
            sound(SOUND_ERR)
            on_main(hud_hide)
            return

        # запоминаем ДО вставки — чтобы текст не потерялся, даже если вставка мимо
        self.last_text = text
        save_history(text)      # архив на 5 дней
        flat = " ".join(text.split())
        preview = flat if len(flat) <= 45 else flat[:42] + "…"
        self.item_last.title = f"📋 Копировать: {preview}"

        on_main(hud_hide)
        insert_text(text, send=send)
        self._restore_audio()       # музыка возвращается после вставки/отправки
        self.title = ICON_IDLE
        self.item_status.title = "✅ Готов"
        sound(SOUND_DONE)

    def on_history(self, _=None):
        """Открыть архив расшифровок (последние 5 дней)."""
        try:
            if not os.path.exists(HISTORY):
                with open(HISTORY, "w", encoding="utf-8") as f:
                    f.write("История диктовок появится здесь после первой записи.\n")
            subprocess.Popen(["open", HISTORY])
        except Exception as e:
            logline(f"history open err: {e}")

    def on_copy_last(self, _=None):
        """Страховка: если вставка ушла мимо — копируем последний текст руками."""
        if not self.last_text:
            self.item_status.title = "Пока нечего копировать"
            return
        clip_set(self.last_text)
        logline(f"[{time.strftime('%H:%M:%S')}] последний текст скопирован "
                f"({len(self.last_text)} симв.)")
        self.item_status.title = "📋 Скопировано — вставь Cmd+V"
        sound(SOUND_DONE)

    def on_quit(self, _):
        self._restore_audio()
        self._stop_listener()
        try:
            if self.proc and self.proc.poll() is None:
                self.proc.kill()
        except Exception:
            pass
        rumps.quit_application()


if __name__ == "__main__":
    DictateApp().run()
