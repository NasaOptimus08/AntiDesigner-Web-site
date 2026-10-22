#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
nbsp.py — расстановка неразрывных пробелов в русском тексте (канон clean-typography).

Клеит короткие предлоги/союзы к следующему слову, частицы бы/же/ли к предыдущему,
числа к единицам, № и § к числу. HTML-теги не трогает (обрабатывает только текст).
Консервативен: по спискам ниже. После прогона просмотреть глазами.

Примеры:
  python3 nbsp.py "Плавание для детей с нуля"
  echo "<h2>Плавание с нуля</h2>" | python3 nbsp.py --stdin --entity
  python3 nbsp.py --file index.html --entity --inplace

Флаги:
  --stdin     читать текст из stdin (иначе — первый позиционный аргумент)
  --file P    читать из файла P
  --inplace   с --file: записать результат обратно в файл
  --entity    выводить &nbsp; вместо символа U+00A0 (для чистого HTML)
"""
import sys, re

NBSP = ' '

# Предлоги/союзы — клеим к СЛЕДУЮЩЕМУ слову (стоят перед словом)
PREP = ['в','во','с','со','к','ко','о','об','обо','у','и','а','но','да','из','изо',
        'от','ото','до','по','за','на','под','надо','над','при','про','без','безо',
        'для','не','ни','что','как','или','чем','где','же','то','если','между','через']
# Частицы — клеим к ПРЕДЫДУЩЕМУ слову (стоят после слова)
PART = ['бы','б','же','ж','ли','ль']
# Единицы измерения после числа
UNIT = ['₽','руб','р','кг','г','км','мм','см','м','л','мл','%','шт','мин','ч','сек',
        'год','года','лет','тыс','млн','млрд','°','°C','₽/мес']

_prep_re = re.compile(r'(?<![^\s(«"\'>—–-])(' + '|'.join(PREP) + r')[ \t]+(?=\S)', re.IGNORECASE)
_part_re = re.compile(r'(?<=\S)[ \t]+(' + '|'.join(PART) + r')(?![^\s.,;:!?)»"\'<])', re.IGNORECASE)
_num_re  = re.compile(r'(\d)[ \t]+(' + '|'.join(u.replace('.', r'\.') for u in UNIT) + r')(?![\w])', re.IGNORECASE)
_sign_re = re.compile(r'(№|§)[ \t]+(?=\d)')
_abbr_re = re.compile(r'\b(т|и)\.[ \t]+([депг])\.')  # т. д., т. п., т. е., и др. (частичн.)

def process_text(t: str) -> str:
    t = _prep_re.sub(lambda m: m.group(1) + NBSP, t)
    t = _part_re.sub(lambda m: NBSP + m.group(1), t)
    t = _num_re.sub(lambda m: m.group(1) + NBSP + m.group(2), t)
    t = _sign_re.sub(lambda m: m.group(1) + NBSP, t)
    t = _abbr_re.sub(lambda m: m.group(1) + '.' + NBSP + m.group(2) + '.', t)
    return t

def process(s: str) -> str:
    # Не трогаем содержимое тегов и уже готовых &nbsp;: режем строку на теги/текст.
    out = []
    for part in re.split(r'(<[^>]+>)', s):
        out.append(part if part.startswith('<') else process_text(part))
    return ''.join(out)

def main():
    args = sys.argv[1:]
    entity = '--entity' in args
    inplace = '--inplace' in args
    text = fpath = None
    if '--file' in args:
        fpath = args[args.index('--file') + 1]
        with open(fpath, encoding='utf-8') as f: text = f.read()
    elif '--stdin' in args:
        text = sys.stdin.read()
    else:
        pos = [a for a in args if not a.startswith('--')]
        if not pos:
            print(__doc__); sys.exit(1)
        text = pos[0]
    res = process(text)
    if entity:
        res = res.replace(NBSP, '&nbsp;')
    if inplace and fpath:
        with open(fpath, 'w', encoding='utf-8') as f: f.write(res)
        sys.stderr.write(f'nbsp: обновлён {fpath}\n')
    else:
        sys.stdout.write(res if text.endswith('\n') or '--stdin' in args else res + '\n')

if __name__ == '__main__':
    main()
