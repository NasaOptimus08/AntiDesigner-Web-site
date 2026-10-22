---
name: video-compress
description: "Сжатие видео ПЕРЕД заливкой на Kinescope и на сервер (любые видео-уроки). Даёт «ютубовское» качество при кратно меньшем размере. Использовать ВСЕГДА перед загрузкой любого видео. ffmpeg → H.264 1080p CRF 22, faststart. Триггеры: «добавить урок», «залить видео», «сжать видео», любой .mp4 для курса/Kinescope, а также кодирование веб-видео (WebM/AV1/VP9), фоновое/герой видео с автоплеем на сайт и HDR-видео с SDR-фоллбэком."
---

# Сжатие видео перед заливкой

**Правило: ни одно видео не заливается на Kinescope/сервер без сжатия.** Скринкасты
и уроки весят в разы больше нужного. Сжатие даёт качество как у YouTube при кратно
меньшем размере — быстрее аплоад, меньше хранилища. Kinescope потом перекодирует сам,
но сжатый исходник = быстрый аплоад и лёгкое хранение.

## Команда по умолчанию (1080p — лучший баланс)
```
ffmpeg -y -i "ВХОД.mp4" \
  -vf "scale='min(1920,iw)':-2:flags=lanczos" \
  -c:v libx264 -crf 22 -preset slow -profile:v high -pix_fmt yuv420p \
  -c:a aac -b:a 128k -movflags +faststart \
  "ВЫХОД.mp4"
```
- `scale='min(1920,iw)':-2` — даунскейл до 1080p ТОЛЬКО если шире (узкие не растягивает),
  высота считается авто и чётная. Kinescope всё равно стримит студенту до 1080p, поэтому
  1080p-исходник = ровно то, что он увидит, без лишнего веса.
- `crf 22` — качество (меньше = лучше/тяжелее). 22 ≈ визуально без потерь для скринкастов.
  Для очень мелкого текста кода → 20; для максимальной экономии → 24.
- `preset slow` — лучше сжатие (medium быстрее, чуть больше).
- `+faststart` — moov-атом в начало → мгновенный старт стрима и аплоада.
- Аудио AAC 128k (чистая речь — можно 96k).

## Проверка результата
```
ls -lah "ВЫХОД.mp4"
ffprobe -v error -select_streams v:0 -show_entries stream=width,height,bit_rate \
  -of default=noprint_wrappers=1 "ВЫХОД.mp4"
```
Ждём кратное уменьшение (обычно 3–5×) при сохранении 1080p.

## Ориентиры и варианты
- Экранный урок ~6 мин: **~200 МБ → ~50 МБ** (проверено 06.07.2026).
- Текст кода мелкий и важна чёткость → 1440p: `scale='min(2560,iw)':-2` (тяжелее, но обычно
  Kinescope всё равно отдаёт 1080p, так что чаще не нужно).
- Нужно ещё меньше при том же качестве → H.265: `-c:v libx265 -crf 26 -tag:v hvc1`
  (−~30% к размеру, кодирует дольше; H.264 совместимее — по умолчанию он).
- ffmpeg на маке: `/opt/homebrew/bin/ffmpeg`.

## Веб-видео (свои ролики НА САЙТ, не Kinescope)

Для видео, которое лежит и играет прямо на сайте (фон, герой, короткий промо), кодируем в
**современный формат + mp4-фоллбэк** и раздаём через несколько `<source>` (вёрстка — в
скилле `site-speed`, раздел 2б). WebM(AV1) весит в 1.5–3× меньше H.264 при том же кадре.

**WebM / AV1 (основной, лёгкий):**
```
ffmpeg -y -i IN.mp4 -c:v libsvtav1 -crf 32 -preset 6 -pix_fmt yuv420p \
  -c:a libopus -b:a 128k -movflags +faststart OUT.webm
```
- Фоновое/герой видео звук не нужно → добавить `-an` (ещё легче).
- `crf` для веба выше, чем для уроков: 30–36 (фон визуально не страдает). Меньше = лучше/тяжелее.
- Нет `libsvtav1` в сборке ffmpeg → VP9: `-c:v libvpx-vp9 -crf 33 -b:v 0 -row-mt 1` (кодирует дольше).
- mp4-фоллбэк — обычной командой H.264 сверху (нужен для iOS Safari и старых браузеров).

**Даунскейл под место на странице** тот же приём: `-vf "scale='min(1920,iw)':-2:flags=lanczos"`
(фон на всю ширину ≤1920; узкие блоки — меньше).

## HDR-видео на сайт (+ обязательный SDR-фоллбэк)

Наш выбор: HDR грузится ТОЛЬКО на HDR-экранах, всем остальным — SDR (чтобы цвета не «плыли»).
Значит кодируем **три файла**: HDR-webm (Chrome/Android), HDR-mp4/HEVC (Safari), SDR-mp4 (дефолт).
Исходник должен быть настоящим HDR (10-bit, BT.2020/PQ) — обычный SDR в HDR не превратить.

**HDR10 → AV1 (webm, для Chrome/Android):**
```
ffmpeg -y -i IN_HDR.mov -c:v libsvtav1 -crf 28 -preset 5 -pix_fmt yuv420p10le \
  -color_primaries bt2020 -color_trc smpte2084 -colorspace bt2020nc \
  -svtav1-params "enable-hdr=1" -an -movflags +faststart OUT-hdr.webm
```

**HDR10 → HEVC (mp4, для Safari; тег `hvc1` обязателен, иначе Safari не проиграет):**
```
ffmpeg -y -i IN_HDR.mov -c:v libx265 -crf 22 -preset slow -pix_fmt yuv420p10le -tag:v hvc1 \
  -x265-params "hdr10=1:colorprim=bt2020:transfer=smpte2084:colormatrix=bt2020nc" \
  -an -movflags +faststart OUT-hdr.mp4
```

**SDR-фоллбэк из HDR — тон-маппинг** (НЕ просто «в 8 бит»: иначе на SDR-экране пересвет/блёклость):
```
ffmpeg -y -i IN_HDR.mov -vf "zscale=t=linear:npl=100,format=gbrpf32le,\
zscale=p=bt709,tonemap=hable,zscale=t=bt709:m=bt709:r=tv,format=yuv420p" \
  -c:v libx264 -crf 22 -preset slow -movflags +faststart -an OUT-sdr.mp4
```
(нужен ffmpeg с `zscale`/libzimg — на маке `brew install ffmpeg` идёт с ним; иначе `--enable-libzimg`).

**Проверка, что HDR-метаданные на месте:**
```
ffprobe -v error -select_streams v:0 -show_entries stream=color_primaries,color_transfer,color_space \
  -of default=noprint_wrappers=1 OUT-hdr.webm
```
Ждём `bt2020 / smpte2084 (PQ) / bt2020nc`. Если transfer=bt709 — файл вышел SDR, HDR не записался.

## Проверка перед публикацией

- Файл стал кратно легче (обычно 3–5×) при сохранении 1080p — сверить `ls -lah` до/после.
- Для веба: есть и `.webm` (лёгкий), и `.mp4` (совместимость) — оба через `<source>`.
- HDR (если есть) — три файла (HDR-webm, HDR-mp4, SDR-mp4) + SDR тон-маппингом.
- Если видео заливается на видеохостинг (YouTube/Kinescope и т.п.) — сжатый исходник ускоряет аплоад; хостинг перекодирует сам.
