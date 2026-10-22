"""
Сборка самодостаточного .app:  ./.venv/bin/python setup.py py2app
Результат: dist/Диктовка.app  (macOS видит его как единое приложение → Accessibility работает)
"""
from setuptools import setup

APP = ["dictate_app.py"]
OPTIONS = {
    "argv_emulation": False,
    "iconfile": "AppIcon.icns",
    "plist": {
        "CFBundleName": "Диктовка",
        "CFBundleDisplayName": "Диктовка",
        "CFBundleIdentifier": "ru.deeem.dictate",
        "CFBundleShortVersionString": "1.0",
        "LSUIElement": True,  # только строка меню, без дока
        "NSMicrophoneUsageDescription": "Для записи речи и распознавания в текст.",
    },
    "packages": ["rumps"],
    "includes": ["AVFoundation", "Quartz", "ApplicationServices", "HIServices", "PyObjCTools", "PyObjCTools.AppHelper"],
}

setup(
    app=APP,
    name="Диктовка",
    options={"py2app": OPTIONS},
    setup_requires=["py2app"],
)
