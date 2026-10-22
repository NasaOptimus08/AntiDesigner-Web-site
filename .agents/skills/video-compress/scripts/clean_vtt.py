#!/usr/bin/env python3
# Чистит VTT от whisper перед заливкой в Kinescope:
#  - убирает реплики нулевой длины (Kinescope отклоняет весь файл из-за них)
#  - режет галлюцинации whisper («редактор субтитров», «продолжение следует» и т.п.)
# Использование: python3 clean_vtt.py file.vtt
import re, sys

TS = re.compile(r'^(\d\d):(\d\d):(\d\d)\.(\d\d\d) --> (\d\d):(\d\d):(\d\d)\.(\d\d\d)')
JUNK = re.compile(r'редактор\s+субтитров|корректор\s|субтитры\s+(сделал|подготовил)|'
                  r'продолжение\s+следует|dimatorzok|amara\.org|подписывайтесь на канал', re.I)

def ms(h, m, s, f): return ((int(h) * 60 + int(m)) * 60 + int(s)) * 1000 + int(f)
def fmt(t):
    h, t = divmod(t, 3600000); m, t = divmod(t, 60000); s, f = divmod(t, 1000)
    return f'{h:02d}:{m:02d}:{s:02d}.{f:03d}'

path = sys.argv[1]
lines = open(path, encoding='utf-8').read().split('\n')
cues, i = [], 0
while i < len(lines):
    m = TS.match(lines[i].strip())
    if m:
        start, end = ms(*m.groups()[:4]), ms(*m.groups()[4:])
        text, j = [], i + 1
        while j < len(lines) and lines[j].strip() and not TS.match(lines[j].strip()):
            text.append(lines[j]); j += 1
        cues.append([start, end, '\n'.join(text).strip()]); i = j
    else:
        i += 1

out, dropped = [], 0
for k, (start, end, text) in enumerate(cues):
    if not text or JUNK.search(text): dropped += 1; continue
    if end <= start:
        nxt = cues[k + 1][0] if k + 1 < len(cues) else start + 2000
        end = min(nxt, start + 2000)
        if end <= start: dropped += 1; continue
    if out and start < out[-1][1]:
        start = out[-1][1]
        if end <= start: dropped += 1; continue
    out.append([start, end, text])

with open(path, 'w', encoding='utf-8') as f:
    f.write('WEBVTT\n\n')
    for s, e, t in out: f.write(f'{fmt(s)} --> {fmt(e)}\n{t}\n\n')
print(f'{len(out)} реплик, выброшено {dropped}')
