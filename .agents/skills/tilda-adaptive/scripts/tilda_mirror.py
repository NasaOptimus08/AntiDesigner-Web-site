#!/usr/bin/env python3
# Зеркало Tilda 1:1 (v3). Ловит ВСЕ tildacdn-URL в HTML/CSS, выводит полный оригинал
# из resize-плейсхолдеров, скачивает, безопасно локализует (без «голых» доменов),
# вырезает Tilda-превью-плашку, внедряет форс-загрузку lazy-картинок.
# Использование: python3 tilda_mirror.py <URL> <out_dir>
import sys, os, re, hashlib, urllib.request

URL, OUT = sys.argv[1], sys.argv[2]
OUT_FILE = sys.argv[3] if len(sys.argv) > 3 else 'index.html'
ASSETS = os.path.join(OUT, 'assets')
os.makedirs(ASSETS, exist_ok=True)
import http.cookiejar

CJ = http.cookiejar.CookieJar()
OPENER = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CJ))
OPENER.addheaders = [
    ('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
    ('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'),
    ('Accept-Language', 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7')
]

# только URL с доменом tildacdn И путём после него (голые домены не матчатся)
TILDA = re.compile(r'(?:https?:)?//[-A-Za-z0-9.]*tildacdn\.[a-z]+/[^\s"\'()\\<>]+', re.I)

def fetch(u):
    res = OPENER.open(u, timeout=60)
    data = res.read()
    if data.startswith(b'\x1f\x8b'):
        import gzip
        data = gzip.decompress(data)
    return data

def absu(u):
    return 'https:' + u if u.startswith('//') else u

def full_variant(u):
    # убрать директивы Tilda /-/resize/20x/ /-/format/webp/ ... → получить оригинал
    return re.sub(r'/-/(?:[a-z]+/[^/]*/)+', '/', u)

def ext(u):
    e = os.path.splitext(u.split('?')[0])[1].lower()
    return e if 1 < len(e) <= 6 else ''

downloaded = {}   # full-url -> local path (assets/xxx.ext)
def grab(orig):
    """Скачать полный вариант URL, вернуть локальный путь (относительно OUT)."""
    full = full_variant(absu(orig))
    if full in downloaded:
        return downloaded[full]
    data = None; used = full
    for cand in (full, absu(orig)):
        try:
            data = fetch(cand); used = cand; break
        except Exception:
            continue
    if data is None:
        print('  ! fail', orig[:80]); return None
    ln = 'assets/' + hashlib.md5(used.encode()).hexdigest()[:16] + ext(used)
    with open(os.path.join(OUT, ln), 'wb') as f: f.write(data)
    downloaded[full] = ln
    # если это CSS — локализовать его содержимое (url() внутри)
    if ln.endswith('.css'):
        body = data.decode('utf-8', 'replace')
        body = localize(body, in_css=True)
        with open(os.path.join(OUT, ln), 'w', encoding='utf-8') as f: f.write(body)
    return ln

def localize(text, in_css=False):
    for orig in sorted(set(TILDA.findall(text)), key=len, reverse=True):
        ln = grab(orig)
        if ln:
            ref = os.path.basename(ln) if in_css else ln
            text = text.replace(orig, ref)
    return text

print('fetch page:', URL)
html = fetch(URL).decode('utf-8', 'replace')

# 1) вырезать Tilda-превью чрому (плашка «Купить N / ЛК», record-type 121, #order-кнопки)
def kill_records(text, markers):
    while True:
        starts = [m.start() for m in re.finditer(r'<div[^>]*id="rec\d+"', text)]
        starts.append(len(text)); killed = False
        for i in range(len(starts) - 1):
            s, e = starts[i], starts[i + 1]
            if any(mk in text[s:e] for mk in markers):
                text = text[:s] + text[e:]; killed = True; break
        if not killed:
            return text
# ТОЛЬКО плашка-превью Tilda-магазина шаблонов (кнопка «Купить … Все шаблоны=…»), не трогаем zero-блоки контента
html = kill_records(html, ['#order:Все шаблоны', '#order:Все&nbsp;шаблоны'])

# 2) скачать + локализовать все ассеты (картинки, css, js, шрифты)
print('localizing assets...')
html = localize(html)
print('assets downloaded:', len(downloaded))

# 3) форс-загрузка lazy-картинок (Tilda-lazyload в зеркале ненадёжен):
#    проставляем data-original/полный локальный путь в src и в фон
INJECT = """
<script>
(function(){function go(){document.querySelectorAll('[data-original]').forEach(function(e){var o=e.getAttribute('data-original');if(!o)return;if(e.tagName==='IMG'){if(e.getAttribute('src')!==o)e.setAttribute('src',o);}else{e.style.backgroundImage="url('"+o+"')";}});document.querySelectorAll('img[data-tu-lazy],img.t-img').forEach(function(i){var d=i.getAttribute('data-original')||i.getAttribute('data-tu-lazy');if(d&&i.getAttribute('src')!==d)i.setAttribute('src',d);});}
if(document.readyState!=='loading')go();document.addEventListener('DOMContentLoaded',go);window.addEventListener('load',go);setTimeout(go,500);setTimeout(go,1500);setTimeout(go,3000);})();
</script>
"""
html = html.replace('</body>', INJECT + '</body>') if '</body>' in html else html + INJECT

with open(os.path.join(OUT, OUT_FILE), 'w', encoding='utf-8') as f: f.write(html)
print('DONE ->', os.path.join(OUT, OUT_FILE), '| assets:', len(downloaded))
