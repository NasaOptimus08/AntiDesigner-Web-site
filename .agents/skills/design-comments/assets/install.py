#!/usr/bin/env python3
"""Подключает виджет дизайн-комментариев во все .html указанной папки.

Использование:
    python3 install.py <папка_с_дизайном> <базовый_url_путь> [версия]

Примеры:
    python3 install.py ./mysite /mysite            # -> <script src="/mysite/comments-widget.js?v=1" data-api="/mysite/api" defer>
    python3 install.py /var/www/site /site 3       # версию можно бампать при обновлении виджета

Что делает:
  * копирует comments-widget.js в папку дизайна (если рядом со скриптом);
  * вставляет тег <script ... defer> перед </body> в каждый .html (идемпотентно);
  * при повторном запуске с новой версией — просто бампает ?v=N.
Дальше: залей comments-server.js на сервер, запусти через PM2, добавь nginx location (см. SKILL.md).
"""
import sys, os, glob, shutil, re

def main():
    if len(sys.argv) < 3:
        print(__doc__); sys.exit(1)
    folder = sys.argv[1].rstrip('/')
    base = '/' + sys.argv[2].strip('/')
    ver = sys.argv[3] if len(sys.argv) > 3 else '1'
    if not os.path.isdir(folder):
        print('Нет такой папки:', folder); sys.exit(1)

    here = os.path.dirname(os.path.abspath(__file__))
    src_widget = os.path.join(here, 'comments-widget.js')
    dst_widget = os.path.join(folder, 'comments-widget.js')
    if os.path.abspath(src_widget) != os.path.abspath(dst_widget) and os.path.exists(src_widget):
        shutil.copy(src_widget, dst_widget); print('скопирован comments-widget.js ->', dst_widget)

    tag = '\n<script src="%s/comments-widget.js?v=%s" data-api="%s/api" defer></script>\n' % (base, ver, base)
    files = glob.glob(os.path.join(folder, '*.html'))
    for f in files:
        t = open(f, encoding='utf-8').read()
        if 'comments-widget.js' in t:
            t2 = re.sub(r'comments-widget\.js\?v=\d+', 'comments-widget.js?v=%s' % ver, t)
            if t2 != t:
                open(f, 'w', encoding='utf-8').write(t2); print('обновлена версия ->', os.path.basename(f))
            else:
                print('уже подключён      ->', os.path.basename(f))
            continue
        if '</body>' in t:
            t = t.replace('</body>', tag + '</body>', 1)
        else:
            t = t + tag
        open(f, 'w', encoding='utf-8').write(t); print('подключён          ->', os.path.basename(f))
    print('\nГотово. Страниц обработано:', len(files))
    print('API виджета ждёт по адресу: %s/api  (настрой nginx proxy_pass на comments-server.js)' % base)

if __name__ == '__main__':
    main()
