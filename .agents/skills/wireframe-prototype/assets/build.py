# -*- coding: utf-8 -*-
"""Генератор вайрфрейм-прототипа БРЕНД. python3 build.py -> dist/"""
import os

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "dist")

NAV_MAIN = [
    ("hr.html", "Для HR"),
    ("marketing.html", "Для маркетинга"),
    ("event.html", "Для event"),
    ("new-year.html", "Новый год"),
    ("ideas.html", "Идеи подарков"),
    ("sobrat.html", "Собрать подарок"),
]
NAV_MORE = [
    ("process.html", "Как мы работаем"),
    ("blog.html", "Блог"),
    ("contacts.html", "Контакты"),
]

CSS = """
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1d1d1d;background:#fff;line-height:1.45}
.wrap{max-width:1200px;margin:0 auto;padding:0 24px}
a{color:inherit}
/* header */
header{position:sticky;top:0;background:#fff;border-bottom:1px solid #e3e3e3;z-index:50}
.hrow{display:flex;align-items:center;gap:18px;padding:14px 0;position:relative}
.logo{font-size:20px;font-weight:700;letter-spacing:4px;text-decoration:none;flex-shrink:0}
.logo small{display:block;font-size:10px;font-weight:400;letter-spacing:.4px;color:#8a8a8a}
nav{display:flex;gap:14px;margin-left:auto;white-space:nowrap}
nav a{font-size:13px;text-decoration:none;color:#444;padding:4px 2px;border-bottom:2px solid transparent}
nav a:hover{border-color:#1d1d1d}
nav a.on{border-color:#1d1d1d;font-weight:600}
.hcta{white-space:nowrap}
/* burger */
.burg{flex-shrink:0;width:38px;height:38px;border:1px solid #c9c9c9;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;cursor:pointer;user-select:none}
#burger{display:none}
.drop{display:none;position:absolute;top:100%;right:0;min-width:230px;background:#fff;border:1px solid #ddd;border-radius:0 0 12px 12px;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:10px 0}
#burger:checked ~ .drop{display:block}
.drop a{display:block;padding:10px 20px;font-size:14px;text-decoration:none;color:#333}
.drop a:hover{background:#f4f4f4}
.drop a.on{font-weight:700}
.drop .dmain{display:none}
.drop hr{border:none;border-top:1px solid #eee;margin:6px 0}
/* buttons, chips */
.btn{display:inline-block;background:#1d1d1d;color:#fff;font-size:14px;padding:12px 22px;border-radius:8px;text-decoration:none;border:1px solid #1d1d1d}
.btn.ghost{background:#fff;color:#1d1d1d}
.btn.sm{padding:8px 14px;font-size:13px}
.chip{display:inline-block;border:1px solid #c9c9c9;border-radius:99px;padding:7px 14px;font-size:13px;color:#333;background:#fff;margin:0 6px 8px 0}
.chip.on{border-color:#1d1d1d;background:#1d1d1d;color:#fff}
/* sections */
section{position:relative;border:1px solid #ddd;border-radius:14px;background:#fafafa;margin:26px 0;padding:30px 28px 28px}
.sec-label{position:absolute;top:-11px;left:18px;background:#fff;border:1px solid #ccc;border-radius:99px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a8a8a;padding:3px 12px}
.note{font-size:12.5px;color:#9a9a9a;font-style:italic;margin-top:10px}
.note:before{content:"→ "}
h1{font-size:38px;line-height:1.15;font-weight:700;margin:10px 0 14px}
h2{font-size:26px;line-height:1.2;margin-bottom:6px}
h3{font-size:16px;margin-bottom:6px}
.kicker{font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:#8a8a8a}
.lead{font-size:16px;color:#555;max-width:560px;margin:0 0 18px}
.muted{color:#8a8a8a;font-size:13px}
/* placeholders */
.ph{border:1.5px dashed #b9b9b9;border-radius:10px;background:repeating-linear-gradient(45deg,#f1f1f1,#f1f1f1 12px,#e9e9e9 12px,#e9e9e9 24px);display:flex;align-items:center;justify-content:center;text-align:center;color:#8f8f8f;font-size:13px;padding:10px;min-height:90px}
/* grids */
.grid{display:grid;gap:16px;margin-top:18px}
.g2{grid-template-columns:1fr 1fr}
.g3{grid-template-columns:repeat(3,1fr)}
.g4{grid-template-columns:repeat(4,1fr)}
.g5{grid-template-columns:repeat(5,1fr)}
.split{display:grid;grid-template-columns:1.1fr .9fr;gap:32px;align-items:start}
.card{border:1px solid #ddd;border-radius:12px;background:#fff;padding:18px;display:flex;flex-direction:column;gap:8px}
.card .ph{min-height:130px}
.card a.btn{align-self:flex-start}
.price{font-weight:700;font-size:15px}
.tag{font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#8a8a8a}
/* акцентные кликабельные карточки наборов */
a.card{text-decoration:none;color:inherit}
.hot{border:2px solid #1d1d1d;box-shadow:0 5px 16px rgba(0,0,0,.14);position:relative;transition:transform .15s,box-shadow .15s;cursor:pointer}
.hot:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,0,0,.2)}
.hot .badge{position:absolute;top:-11px;right:14px;background:#1d1d1d;color:#fff;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;padding:4px 12px;border-radius:99px}
.hot .chip{padding:4px 10px;font-size:11.5px;margin:0 5px 5px 0}
.hot .go{margin-top:auto;font-weight:700;font-size:14.5px;padding-top:6px;border-top:1px solid #eee}
/* мини-страница набора: фото с выносками */
.annwrap{display:grid;grid-template-columns:1fr 1.3fr 1fr;gap:22px;align-items:center;margin-top:18px}
.ann{margin-bottom:20px}
.ann b{display:block;font-size:12px;letter-spacing:1px;text-transform:uppercase;margin-bottom:3px}
.ann p{font-size:12.5px;color:#8a8a8a;line-height:1.4}
.annL .ann{text-align:right}
.setpage-head{text-align:center;max-width:780px;margin:0 auto}
@media(max-width:900px){.annwrap{grid-template-columns:1fr}.annL .ann{text-align:left}}
/* steps / faq / form */
.step{display:flex;gap:14px;align-items:flex-start;border:1px solid #ddd;background:#fff;border-radius:12px;padding:16px}
.step b.num{flex:0 0 34px;height:34px;border-radius:50%;border:1.5px solid #1d1d1d;display:flex;align-items:center;justify-content:center;font-size:15px}
details{border:1px solid #ddd;background:#fff;border-radius:10px;padding:14px 16px;margin-top:10px;font-size:14px}
details summary{cursor:pointer;font-weight:600}
details p{margin-top:8px;color:#666}
.form{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
.form .full{grid-column:1/-1}
.inp{border:1px solid #c9c9c9;border-radius:8px;background:#fff;padding:12px;font-size:13.5px;color:#9a9a9a}
.check{font-size:12.5px;color:#8a8a8a}
/* misc */
.iconrow{display:grid;grid-template-columns:repeat(8,1fr);gap:10px;margin-top:16px;text-align:center}
.iconrow div{border:1px solid #ddd;border-radius:10px;background:#fff;padding:14px 6px;font-size:12.5px}
.iconrow span{display:block;width:26px;height:26px;border:1.5px solid #999;border-radius:50%;margin:0 auto 8px}
footer{border-top:1px solid #e3e3e3;margin-top:40px;padding:26px 0 90px;font-size:13px;color:#777}
.frow{display:flex;gap:30px;flex-wrap:wrap;justify-content:space-between}
/* комментарии клиента */
.cmt-btn{position:absolute;top:-11px;right:16px;background:#fff;border:1.5px solid #1d1d1d;border-radius:99px;font-size:11px;padding:4px 12px;cursor:pointer;font-family:inherit}
.cmt-btn:hover{background:#1d1d1d;color:#fff}
.cmt-fab{position:fixed;left:22px;bottom:22px;background:#fff;border:1.5px solid #1d1d1d;color:#1d1d1d;border-radius:99px;padding:13px 18px;font-size:13.5px;font-family:inherit;box-shadow:0 6px 20px rgba(0,0,0,.18);cursor:pointer;z-index:60}
.cmt-fab:hover{background:#1d1d1d;color:#fff}
.cmt-form{margin-top:14px;border:1.5px solid #1d1d1d;border-radius:10px;background:#fff;padding:12px}
.cmt-form textarea{width:100%;border:1px solid #c9c9c9;border-radius:8px;padding:10px;font-size:14px;font-family:inherit;resize:vertical}
.cmt-row{display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap}
.cmt-row input{flex:1;min-width:160px;border:1px solid #c9c9c9;border-radius:8px;padding:9px 10px;font-size:13px;font-family:inherit}
.cmt-row button{background:#1d1d1d;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:13.5px;cursor:pointer;font-family:inherit}
.cmt-x{font-size:12.5px;color:#9a9a9a;cursor:pointer;text-decoration:underline}
.cmt-list{margin-top:12px;display:grid;gap:8px}
.cmt-item{background:#fff8d6;border:1px solid #e6d783;border-radius:10px;padding:10px 14px;font-size:13.5px}
.cmt-item b{font-size:13px}
.cmt-item span{color:#a08c2a;font-size:11.5px;margin-left:6px}
.cmt-item p{margin-top:4px;white-space:pre-wrap}
#cmt-page-panel{border:1.5px dashed #1d1d1d;border-radius:14px;background:#fffdf2;margin:26px 0;padding:18px 22px}
#cmt-page-panel:empty{display:none}
.cmt-panel-title{font-size:14px;font-weight:700}
@media(max-width:1100px){nav{display:none}.drop .dmain{display:block}}
@media(max-width:900px){.g3,.g4,.g5{grid-template-columns:1fr 1fr}.split{grid-template-columns:1fr}.iconrow{grid-template-columns:repeat(4,1fr)}h1{font-size:30px}}
@media(max-width:560px){.g2,.g3,.g4,.g5{grid-template-columns:1fr}.form{grid-template-columns:1fr}.hcta{display:none}}
"""

def ph(text, h=None):
    style = f' style="min-height:{h}px"' if h else ""
    return f'<div class="ph"{style}>{text}</div>'

def sec(label, inner, note=None):
    n = f'<p class="note">{note}</p>' if note else ""
    return f'<section><span class="sec-label">{label}</span>{inner}{n}</section>'

def card(title, sub, price=None, tag=None, img="Фото бокса", link="box.html", btn="Смотреть набор"):
    p = f'<div class="price">{price}</div>' if price else ""
    t = f'<div class="tag">{tag}</div>' if tag else ""
    return (f'<div class="card">{ph(img)}{t}<h3>{title}</h3>'
            f'<p class="muted" style="font-size:13.5px">{sub}</p>{p}'
            f'<a class="btn ghost sm" href="{link}">{btn}</a></div>')

def pain(title, text):
    return f'<div class="card"><h3>«{title}»</h3><p class="muted" style="font-size:13.5px">{text}</p></div>'

def hotcard(title, sub, chips, link, img="Фото бокса", price=None, cta="Смотреть набор"):
    ch = "".join(f'<span class="chip">{c}</span>' for c in chips)
    p = f'<div class="price">{price}</div>' if price else ""
    return (f'<a class="card hot" href="{link}"><span class="badge">клик · страница набора</span>'
            f'{ph(img, 150)}<h3>{title}</h3>'
            f'<p class="muted" style="font-size:13.5px">{sub}</p>{p}'
            f'<div>{ch}</div><span class="go">{cta} →</span></a>')

def metric(num, label):
    return f'<div class="card" style="text-align:center"><h3 style="font-size:24px">{num}</h3><p class="muted">{label}</p></div>'

def lead_form(button="Получить расчёт и варианты", extra_note="Заявка уходит менеджеру в Telegram + на почту. Поля — как в макетах клиента.", aud=None):
    audhtml = f'<div class="inp">{aud} ▾</div>' if aud else ""
    return f'''
    <div class="form">
      <div class="inp full" style="min-height:76px">Опишите, какие смыслы вы хотите передать подарком…</div>
      <div class="inp">Количество наборов (например, 100)</div>
      {audhtml}
      <div class="inp">Бюджет на 1 набор ▾</div>
      <div class="inp">Город доставки</div>
      <div class="inp">Желаемая дата получения ▾</div>
      <div class="inp">Ваше имя</div>
      <div class="inp">Телефон</div>
      <div class="inp">E-mail</div>
      <div class="inp">Требуется брендирование? Да / Нет ▾</div>
      <div class="full"><a class="btn" href="#">{button} →</a></div>
      <div class="check full">☑ Нажимая кнопку, вы соглашаетесь на обработку персональных данных</div>
    </div>
    <p class="note">{extra_note}</p>'''

def page(fname, title, active, body):
    navhtml = "".join(
        f'<a href="{h}" class="{"on" if h == active else ""}">{t}</a>' for h, t in NAV_MAIN
    )
    drop_main = "".join(
        f'<a class="dmain {"on" if h == active else ""}" href="{h}">{t}</a>' for h, t in NAV_MAIN
    )
    drop_more = "".join(
        f'<a class="{"on" if h == active else ""}" href="{h}">{t}</a>' for h, t in NAV_MORE
    )
    return f'''<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{title} — БРЕНД · прототип</title>
<link rel="stylesheet" href="wire.css">
<script src="comments.js" defer></script>
</head>
<body>
<header><div class="wrap hrow">
  <a class="logo" href="index.html">БРЕНД<small>корпоративные подарки со смыслом</small></a>
  <nav>{navhtml}</nav>
  <a class="btn sm hcta" href="contacts.html">Получить подборку идей</a>
  <input type="checkbox" id="burger">
  <label class="burg" for="burger">☰</label>
  <div class="drop">{drop_main}<hr class="dmain">{drop_more}</div>
</div></header>
<main class="wrap">
{body}
</main>
<footer><div class="wrap frow">
  <div><b>БРЕНД</b> · подарки со смыслом<br>© БРЕНД, 2026</div>
  <div>+7 (900) 000-00-00 · hello@example.ru<br>Telegram: @your_team</div>
  <div>Москва, ул. Садовая, 10<br>Пн–Пт 10:00–19:00</div>
  <div><a href="#">Политика обработки персональных данных</a><br><a href="#">Реквизиты</a></div>
</div></footer>
</body>
</html>'''

P = {}

# ---------------- Наборы: данные для мини-страниц (структура — скрин «Зимняя сказка») ----------------
SETS = {
"velkompak": dict(dir_page="hr.html", dir_label="Для HR", kicker="Welcome pack для новых сотрудников",
    title="Велкомпак «Ты здесь не случайно»", sub="С первого дня — ощущение «меня ждали»: маршрут первого месяца, место, которое готово, и команда, которая рада.",
    price="от 2 500 ₽ · цена-заглушка", chips=["Новичкам", "Первый рабочий день", "IT-команды"], aud="Кому дарим",
    img="Фото: открытый велкомпак (чёрный + лайм, макет клиента)",
    items=[("Шоппер «Ты здесь не случайно»", "Задаёт тон первому дню и уносит все подарки домой"),
           ("Гайд «Твои первые шаги внутри»", "Маршрут первого месяца: знакомство, процессы, первые решения, твой вклад"),
           ("Блокнот + ручка", "«Первые мысли. Идеи. Решения.» — для всего, что приходит в голову новичку"),
           ("Карточка «Твоё место готово»", "Приветствие, которое ждёт на рабочем столе"),
           ("Бейдж «Доступ открыт»", "Лента и держатель в стиле команды"),
           ("Календарь «30 дней. Один шаг команды»", "Маленькие задания на каждый день первого месяца"),
           ("Шоколад с посланиями", "«Задай вопрос», «не бойся ошибок», «ты уже свой»")],
    related=["smena", "vosstanovlenie", "nasvyazi"]),
"smena": dict(dir_page="hr.html", dir_label="Для HR", kicker="Благодарность линейной команде",
    title="Набор «Смена вывезена»", sub="Для людей, на которых всё держится: ритейл, склад, поддержка. Спасибо за каждый день, а не раз в год.",
    price="от 1 800 ₽ · цена-заглушка", chips=["Ритейл и склад", "Поддержка", "Итоги сезона"], aud="Кому дарим",
    img="Фото: набор «Смена вывезена» с котом-талисманом (белый + красный, макет клиента)",
    items=[("Шоппер «Смена вывезена. +100 к спокойствию»", "С персонажем-талисманом, которого узнает вся команда"),
           ("Термостакан «Пауза тоже важна»", "Напоминание про передышку прямо в руках"),
           ("Открытка «За что мы благодарим»", "Раскладушка: ранние открытия, порядок, кассы, сложные дни, вклад каждого"),
           ("Письмо «Спасибо за каждый день»", "От лица компании, с перечислением реального вклада"),
           ("Набор «Для вечера после насыщенного дня»", "Чай + шоколад в отдельной коробке"),
           ("Карточка выбора", "«Выберите то, что порадует именно вас»"),
           ("Подарочный сертификат", "Финальный аккорд благодарности")],
    related=["vosstanovlenie", "velkompak", "dorog"]),
"vosstanovlenie": dict(dir_page="hr.html", dir_label="Для HR", kicker="Набор заботы после нагрузки",
    title="Набор «Режим: восстановление»", sub="Проект вывезен — можно выдохнуть. Набор, который разрешает команде паузу.",
    price="от 2 200 ₽ · цена-заглушка", chips=["После проекта", "Высокая нагрузка", "Wellness"], aud="Кому дарим",
    img="Фото: бокс «Режим: восстановление» (зелёный + крафт, макет клиента)",
    items=[("Шоппер «Проект вывезен. Можно выдохнуть»", "Фиксирует момент: работа сделана, теперь — вы"),
           ("Травяной чай «Для тихого вечера»", "Ритуал замедления после насыщенного периода"),
           ("Маска для сна «Режим „не трогать“»", "Время восстановиться — официально разрешено"),
           ("Аромасвеча «Для вечера без дедлайнов»", "Атмосфера паузы дома"),
           ("Карточки «7 техник перезагрузки»", "Выдох 4–6, выгрузка из головы, стоп-экран, тёплая пауза, закрыть день"),
           ("Письмо «Спасибо за сильную работу»", "Ценим не только результат, но и усилия, которые за ним стоят"),
           ("Подарочный сертификат", "«Выберите то, что поможет восстановиться именно вам»")],
    related=["smena", "nasvyazi", "velkompak"]),
"nasvyazi": dict(dir_page="hr.html", dir_label="Для HR", kicker="Для удалённых и гибридных команд",
    title="Набор «На связи. В системе»", sub="Remote, но не отдельно: физическое «мы вместе» для команды в разных городах.",
    price="от 2 400 ₽ · цена-заглушка", chips=["Удалённые команды", "Гибрид", "Онбординг удалённых"], aud="Кому дарим",
    img="Фото: бокс «На связи. В системе» (чёрный + лайм/синий, макет клиента)",
    items=[("Шоппер «На связи. В системе»", "Манифест удалённой работы: remote, но не отдельно"),
           ("Кофе «Для тихого фокуса»", "Продуктивное утро без опенспейса"),
           ("Термокружка «Пауза между созвонами»", "Перерыв — тоже часть работы"),
           ("Блокнот «Лог мыслей»", "Что обсудили, что решили, кого спросить, что сделать"),
           ("Карточки «Remote rules»", "7 правил команды: асинхронно ≠ забыто, фокус — тоже работа"),
           ("Стикерпак удалёнки", "«Не трогать: деплою», «кофе в фокусе», «я в решает»"),
           ("Кабель-органайзер + заглушка камеры", "Провода в порядок, приватность включена")],
    related=["velkompak", "vosstanovlenie", "dorog"]),
"partnerstvo": dict(dir_page="marketing.html", dir_label="Для маркетинга", kicker="Статусный бокс для партнёров",
    title="Набор «Надёжное партнёрство»", sub="Надёжное партнёрство начинается с деталей: знак того, как вы строите отношения — спокойно, прозрачно и надолго.",
    price="от 3 500 ₽ · цена-заглушка", chips=["Ключевым партнёрам", "Руководителям", "Итоги года"], aud="Кому дарим",
    img="Фото: бокс «Надёжное партнёрство» (чёрный + золото, макет клиента)",
    items=[("Письмо партнёру", "О доверии и совместной работе, с личной подписью"),
           ("Карточка «Наши принципы»", "Договариваемся честно, держим сроки, работаем прозрачно, берём ответственность"),
           ("Блокнот переговоров", "«Решения. Договорённости. Следующие шаги.»"),
           ("Чёрный чай с бергамотом", "«Для спокойных решений» — жест уважения к времени партнёра"),
           ("Набор «Детали договорённостей»", "Зажимы, стикеры-стрелки и карточка «Следующий шаг: что решили, кто отвечает»"),
           ("Линейка-закладка", "«Точность создаёт доверие» — метафора подхода в металле"),
           ("Карта «Ваш прямой контакт» + QR", "Телефон, почта и партнёрский центр со всеми материалами")],
    related=["proizvodstvo", "vstrecha", "ng-partner"]),
"proizvodstvo": dict(dir_page="marketing.html", dir_label="Для маркетинга", kicker="Бокс-инфоповод",
    title="Набор «Мы открыли новое производство»", sub="Запуск площадки, о котором расскажут клиенты, партнёры и СМИ: бокс вместо пресс-релиза.",
    price="от 2 900 ₽ · цена-заглушка", chips=["СМИ и блогерам", "Клиентам", "Партнёрам"], aud="Кому дарим",
    img="Фото: бокс «Мы открыли новое производство» (серый + синий, макет клиента)",
    items=[("Первая продукция с новой линии", "Коробка «сделано на новом производстве» — партия №0001"),
           ("Образец материала до обработки", "«С этого начинается продукт» — можно потрогать"),
           ("Карточка «Почему это важно»", "Больше возможностей, стабильное качество, новые продукты, ближе к клиенту"),
           ("Производственный маршрут", "6 шагов: идея → материалы → производство → контроль → упаковка → продукт"),
           ("Паспорт качества", "Дата запуска, первая партия, проверено командой"),
           ("Бирка «Сделано здесь»", "Металлический артефакт запуска"),
           ("Конверт «Для публикации» + QR", "Фото, факты и видео — всё для поста или статьи")],
    related=["bloger", "partnerstvo", "vstrecha"]),
"bloger": dict(dir_page="marketing.html", dir_label="Для маркетинга", kicker="PR-рассылка инфлюенсерам",
    title="PR-бокс «Пока ты не открыл последнюю страницу»", sub="Бокс-история, который хочется распаковать на камеру: пример рассылки под запуск книги.",
    price="от 2 600 ₽ · цена-заглушка", chips=["Блогерам", "Инфлюенсерам", "Запуск продукта"], aud="Кому дарим",
    img="Фото: чёрный бокс-тайна с книгой и ключом (макет клиента)",
    items=[("Книга с бандеролью", "«Не читай финал первым. Он всё изменит» — главный герой рассылки"),
           ("Письмо «Персонально для вас»", "С именем получателя: «мы выбрали вас не случайно»"),
           ("Конверт «Открыть после 50-й страницы»", "Механика, которая ведёт получателя по сюжету"),
           ("Закладка «Место, где всё начало меняться»", "Деталь, которую снимают для сторис"),
           ("Свечи для чтения", "«Для главы, после которой не уснуть» и «Твоё время на эту историю»"),
           ("Ключ-улика и конверт с сургучом", "Реквизит истории — крупный план в любой распаковке"),
           ("Конверт «Для публикации» + QR", "3 причины рассказать об этой книге + материалы")],
    related=["proizvodstvo", "vstrecha", "partnerstvo"]),
"vstrecha": dict(dir_page="marketing.html", dir_label="Для маркетинга и event", kicker="After-meeting / after-event",
    title="Набор «Впечатление после встречи»", sub="Событие закончилось — разговор продолжается: касание, которое возвращает клиента к диалогу.",
    price="От 1 290 ₽ · от 20 шт.", chips=["Клиентам после встречи", "Гостям события", "B2B-продажи"], aud="Кому дарим",
    img="Фото: бокс «Событие закончилось. Разговор продолжается» (бежевый + зелёный, макет клиента)",
    items=[("Блокнот «После встречи»", "Идеи, контакты, следующие шаги"),
           ("Карточка «Что осталось после события» + QR", "Главные идеи, вопросы, материалы и запись"),
           ("Конверт «Открыть через 7 дней»", "Возвращает к разговору, когда эмоции улеглись"),
           ("Зелёный чай", "«Для разговора, который стоит продолжить»"),
           ("Карточки «Задача — решение»", "Как продукт помогает: процессы, затраты, управляемость, клиентский опыт"),
           ("Конверт «Передай коллеге»", "Бесплатный билет на следующее мероприятие — новый контакт в воронку")],
    related=["sponsor", "partnerstvo", "bloger"]),
"sponsor": dict(dir_page="event.html", dir_label="Для event", kicker="Спонсорский бокс участника",
    title="Спонсорский набор участника", sub="Ваш бренд — польза для участника. Контакт. Результат: бокс, который окупает спонсорство.",
    price="от 1 500 ₽ · цена-заглушка", chips=["Участникам", "От спонсора", "Конференции"], aud="Кому дарим",
    img="Фото: чёрный спонсорский бокс с жёлтыми акцентами (макет клиента)",
    items=[("Блокнот «Идеи. Встречи. Результаты.»", "Для важных заметок и хороших решений"),
           ("Орехи «Энергия для важных решений»", "Полезный перекус между сессиями"),
           ("Вода «Для ясных идей»", "Забота, которую замечают в зале"),
           ("Батончик «Фокус на главное»", "Энергия и концентрация на весь день"),
           ("Карточка «Ваш доступ к возможностям» + QR", "Материалы, спецусловия, приглашение на встречу со спонсором"),
           ("Крышка-навигатор", "Польза → Контакты → Решения → Продолжение → Результат")],
    related=["spiker", "vip", "vstrecha"]),
"spiker": dict(dir_page="event.html", dir_label="Для event", kicker="Для спикеров и экспертов",
    title="Спикер-пак", sub="Ваш голос. Ваша экспертиза. Ваше влияние: всё, чтобы провести выступление на высшем уровне.",
    price="от 2 800 ₽ · цена-заглушка", chips=["Спикерам", "Модераторам", "VIP-экспертам"], aud="Кому дарим",
    img="Фото: чёрный спикер-пак с кликером (макет клиента)",
    items=[("Кликер «Управляйте уверенно»", "Свободное управление презентацией"),
           ("Материалы для спикера", "Чек-лист подготовки, структура выступления, приёмы вовлечения аудитории"),
           ("Карточка «Работа с вопросами»", "Советы по подаче и сложным вопросам из зала"),
           ("Перекус «Энергия для яркого выступления»", "Орехи и ягоды — поддержать энергию на протяжении дня"),
           ("Карточка «Ваш опыт вдохновляет»", "Личная благодарность от организаторов"),
           ("Вода «Фокус на главном»", "Мелочь, которую спикеры запоминают")],
    related=["sponsor", "vip", "vstrecha"]),
"vip": dict(dir_page="event.html", dir_label="Для event", kicker="Благодарность VIP-гостям",
    title="Набор «Спасибо, что были с нами»", sub="Ваше присутствие сделало событие особенным: статусная благодарность важным гостям.",
    price="от 2 400 ₽ · цена-заглушка", chips=["VIP-гостям", "Партнёрам события", "Хедлайнерам"], aud="Кому дарим",
    img="Фото: чёрный бокс с золотым тиснением (макет клиента)",
    items=[("Карточка «Ваша поддержка важна для нас»", "Благодарность за доверие, участие и вклад в атмосферу"),
           ("Аромасвеча «Тепло этого вечера»", "«Пусть хорошие впечатления останутся дольше»"),
           ("Конфеты ручной работы", "«Маленький комплимент» — с благодарностью за участие"),
           ("Коробка с тиснением золотом", "Статус в каждой детали"),
           ("Личная подпись организатора", "Жест, который не масштабируется — и потому ценится")],
    related=["sponsor", "spiker", "ng-partner"]),
"skazka": dict(dir_page="new-year.html", dir_label="Новый год", kicker="Новогодний набор детям сотрудников",
    title="«Зимняя сказка»", sub="Радость, игра и маленькое чудо для детей вашей команды.",
    price="цена уточняется", chips=["3–6 лет", "7–10 лет", "10–13 лет", "Универсальные"], aud="Возраст детей",
    img="Фото: деревянная коробка с овечкой и книгой (макет клиента)",
    items=[("Книга «Зимняя сказка»", "Добрые истории, которые учат мечтать и верить в чудеса"),
           ("Мягкая овечка", "Обнимашка, которая дарит ощущение заботы и тепла"),
           ("Горячий шоколад", "Тепло и уют в каждой чашке — для волшебных моментов"),
           ("Маршмеллоу «Воздушные облачка»", "Маленькие радости для сладких моментов"),
           ("Пазл на 54 элемента", "Развитие внимания, усидчивости и логического мышления"),
           ("Сладости с фразами из сказок", "Добрые слова, которые вдохновляют и поддерживают"),
           ("Деревянная коробка", "После праздников превращается в кормушку для птиц или органайзер")],
    related=["dorog", "ng-partner", "vosstanovlenie"]),
"dorog": dict(dir_page="new-year.html", dir_label="Новый год", kicker="Новогодний набор для сотрудников",
    title="«Ты нам дорог»", sub="Благодарность за вклад и книга, которая станет ориентиром на следующий год.",
    price="цена уточняется", chips=["Для всей команды", "Для офиса и удалённых", "Для руководителей"], aud="Возраст сотрудников",
    img="Фото: белый бокс с книгой и тремя конвертами (макет клиента)",
    items=[("Книга «Лидерство на все времена»", "Уроки великих людей для каждого дня — раскрывает наши ценности"),
           ("3 конверта — 3 этапа", "Откройте после 50, 100 и 150 страниц: каждое письмо — о важном"),
           ("Термостакан", "«Важные дела начинаются с людей» — для ежедневных дел и заботы о себе"),
           ("Чай для тёплых моментов", "Пауза, которая помогает замедлиться и подумать о главном"),
           ("Карточка благодарности", "«Спасибо, что вы — важная часть нашей команды»")],
    related=["skazka", "ng-partner", "smena"]),
"ng-partner": dict(dir_page="new-year.html", dir_label="Новый год", kicker="Новогодний набор для партнёров",
    title="«Партнёрство, которое строит будущее»", sub="Новый год — время подвести итоги и вместе наметить новые вершины. Знак признательности и уверенности в общем будущем.",
    price="3 900 ₽ за набор · из макета", chips=["Клиентам", "Ключевым партнёрам", "Руководителям"], aud="Кому дарим",
    img="Фото: крафт-бокс с письмом под сургучной печатью (макет клиента)",
    items=[("Письмо партнёру с сургучной печатью", "«Спасибо за доверие и совместную работу. Впереди — новые цели и большие победы»"),
           ("Мост к правильному пути", "Деревянный кабель-органайзер: порядок на рабочем столе и в задачах"),
           ("Блокнот «Важные секреты»", "Карманный блокнот для идей, планов и важных мыслей"),
           ("Кофе в зёрнах", "«Для строительства будущих отношений» — 100% арабика"),
           ("Крафт-конверт с итогами года", "Цифры сотрудничества и планы общего будущего")],
    related=["dorog", "skazka", "partnerstvo"]),
}

def set_link(key):
    return f"set-{key}.html"

def set_page_body(key, s):
    n = len(s["items"])
    half = (n + 1) // 2
    annL = "".join(f'<div class="ann"><b>{a}</b><p>{b}</p></div>' for a, b in s["items"][:half])
    annR = "".join(f'<div class="ann"><b>{a}</b><p>{b}</p></div>' for a, b in s["items"][half:])
    rel = "".join(hotcard(SETS[r]["title"], SETS[r]["sub"], SETS[r]["chips"][:2], set_link(r), price=SETS[r]["price"]) for r in s["related"])
    return "".join([
sec("1 · Название набора",
f'''<p class="muted"><a href="{s["dir_page"]}">← Назад: {s["dir_label"]}</a></p>
<div class="setpage-head">
  <div class="kicker">{s["kicker"]}</div>
  <h1>{s["title"]}</h1>
  <p class="lead" style="margin:0 auto 14px">{s["sub"]}</p>
  <p><span class="chip on">{s["price"]}</span><span class="chip">тираж от 30</span><span class="chip">от 10 раб. дней</span></p>
  <a class="btn" href="#order">Получить расчёт и варианты →</a>
</div>''',
"Мини-страница набора: структура — как макет «Зимняя сказка» клиента (заголовок → фото с выносками → форма)."),

sec("2 · Что внутри — фото с выносками",
f'''<div class="annwrap">
  <div class="annL">{annL}</div>
  {ph(s["img"], 420)}
  <div>{annR}</div>
</div>''',
"Выноски к каждому предмету — как на макете: название + смысл предмета. В дизайне — линии-коннекторы к фото."),

sec("3 · Закажите набор со смыслом",
f'''<div class="split" id="order">
  <div>
    <h2>Закажите набор со смыслом</h2>
    <div class="grid g2" style="margin-top:14px">
      <div class="card"><h3>✓ Персональный подход</h3><p class="muted">к каждому заказу</p></div>
      <div class="card"><h3>✓ Качественные материалы</h3><p class="muted">и продуманный состав</p></div>
      <div class="card"><h3>✓ Брендирование</h3><p class="muted">и персонализация</p></div>
      <div class="card"><h3>✓ Доставка</h3><p class="muted">по всей России</p></div>
    </div>
    <p class="muted" style="margin-top:14px;border:1px dashed #c9c9c9;border-radius:10px;padding:12px">🎁 Минимальный тираж — от 30 наборов · Срок производства — от 10 рабочих дней</p>
  </div>
  <div>{lead_form(aud=s["aud"])}</div>
</div>''',
"Блок и поля формы — из макета клиента: смыслы, тираж, аудитория, бюджет, город, дата, брендирование."),

sec("4 · Похожие наборы",
f'''<h2>Смотрите также</h2>
<div class="grid g3">{rel}</div>''',
"Перелинковка удерживает в каталоге наборов."),
])


# ------------------------------------------------------------------ ГЛАВНАЯ
P["index.html"] = ("Главная", "", "".join([
sec("Hero · первый экран",
f'''<div class="split">
  <div>
    <div class="kicker">Корпоративные подарки со смыслом</div>
    <h1>Подарки, которыми компания говорит важное</h1>
    <p class="lead">Создаём подарки, которые укрепляют отношения, поддерживают культуру и запоминаются надолго.</p>
    <a class="btn" href="sobrat.html">Собрать подарок под вашу задачу →</a>
    <a class="btn ghost" href="ideas.html" style="margin-left:8px">Смотреть идеи</a>
  </div>
  {ph("Фото: открытый бокс «Важно только важное» (бежево-золотая гамма, как на макете главного экрана)", 300)}
</div>''',
"Заголовок и подзаголовок — из макета клиента. Два CTA: горячим — квиз, тёплым — каталог идей."),

sec("Направления · 4 карточки",
f'''<h2>Под какую задачу подарок?</h2>
<div class="grid g4">
  {card("Для HR", "Сказать «вы важны» сотрудникам и команде: welcome pack, благодарность, поддержка, удалёнка", tag="направление", img="Иконка/фото", link="hr.html", btn="К разделу HR")}
  {card("Для маркетинга и PR", "Запомниться клиентам и партнёрам: партнёрские боксы, PR-рассылки, презентации продукта", tag="направление", img="Иконка/фото", link="marketing.html", btn="К разделу")}
  {card("Для event", "Продлить впечатление после события: спонсорские боксы, спикер-паки, VIP-гости", tag="направление", img="Иконка/фото", link="event.html", btn="К разделу")}
  {card("Новый год 2027", "Детям, сотрудникам и партнёрам — с заботой и смыслом. Сезонный раздел", tag="сезон · выделена визуально", img="Иконка/фото", link="new-year.html", btn="К разделу НГ")}
</div>''',
"Ключевая развилка сайта. У конкурентов направлений-страниц под роль покупателя нет — это отстройка."),

sec("Цифры доверия",
'''<div class="grid g4">
  <div class="card"><h3>от 30 наборов</h3><p class="muted">минимальный тираж</p></div>
  <div class="card"><h3>от 10 рабочих дней</h3><p class="muted">срок производства</p></div>
  <div class="card"><h3>Доставка по всей России</h3><p class="muted">в офис и по адресам сотрудников</p></div>
  <div class="card"><h3>N лет · X компаний</h3><p class="muted">цифры уточним у клиента</p></div>
</div>''',
"Снимает базовые B2B-вопросы сразу под hero. Цифры N/X — запросить у клиента."),

sec("Клиенты",
ph("Лента логотипов клиентов (6–10 шт). Если логотипов пока нет — блок скрываем", 70),
"Есть у всех сильных конкурентов. Уточнить у клиента, кого можно показывать."),

sec("Популярные идеи · витрина",
f'''<h2>Идеи подарков со смыслом</h2>
<p class="lead">Каждую идею адаптируем под ваш бренд, бюджет и тираж.</p>
<div class="grid g5">
  {card("Спасибо за год вместе", "Выразить благодарность и отметить вклад команды", "От 1 390 ₽ · от 20 шт.", "Для HR", link="set-dorog.html")}
  {card("Это про нас", "Подарок, который говорит о ценностях бренда", "От 1 750 ₽ · от 30 шт.", "Для маркетинга", link="set-partnerstvo.html")}
  {card("Впечатление после встречи", "Продлить эмоции и остаться в памяти", "От 1 290 ₽ · от 20 шт.", "Для event", link="set-vstrecha.html")}
  {card("Забота, которая чувствуется", "Про внимание к людям и их комфорту", "От 1 550 ₽ · от 20 шт.", "Для HR", link="set-vosstanovlenie.html")}
  {card("Партнёрство, которое создаёт будущее", "Подчеркнуть статус и укрепить отношения", "От 2 250 ₽ · от 30 шт.", "Для маркетинга", link="set-ng-partner.html")}
</div>
<p style="margin-top:16px"><a class="btn ghost" href="ideas.html">Все идеи подарков →</a></p>''',
"Карточки и цены — из макета «Идеи подарков». Открытые вилки цен — отстройка: конкуренты цены прячут."),

sec("Квиз-тизер",
'''<div class="split">
  <div>
    <h2>Расскажите, что должен сказать подарок</h2>
    <p class="lead">Ответьте на 4 вопроса — предложим идею, состав и подачу, которые передадут нужный смысл.</p>
    <a class="btn" href="sobrat.html">Собрать подарок →</a>
  </div>
  <div>
    <span class="chip on">Кому дарим?</span><span class="chip">Что хотим сказать?</span><span class="chip">Какое чувство?</span><span class="chip">Повод и рамки</span>
  </div>
</div>''',
"Главная фишка сайта: ни у одного из 7 конкурентов квиза-конструктора нет."),

sec("Как мы работаем · кратко",
'''<h2>Как мы работаем</h2>
<div class="grid g3">
  <div class="step"><b class="num">1</b><div><b>Бриф</b><br><span class="muted">задача, смыслы, бюджет — 1 день</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Концепция</b><br><span class="muted">идея, состав, тексты — 2–3 дня</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Макет и образец</b><br><span class="muted">утверждаем до тиража</span></div></div>
  <div class="step"><b class="num">4</b><div><b>Производство</b><br><span class="muted">брендирование, от 10 раб. дней</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Упаковка</b><br><span class="muted">ручная сборка, контроль качества</span></div></div>
  <div class="step"><b class="num">6</b><div><b>Доставка</b><br><span class="muted">в офис или по адресам по РФ</span></div></div>
</div>
<p style="margin-top:14px"><a class="btn ghost sm" href="process.html">Подробнее об этапах и условиях →</a></p>''',
"Снимает страх «успеем ли к дате». Полная версия — на отдельной странице."),

sec("Отзывы",
f'''<h2>Что говорят клиенты</h2>
<div class="grid g3">
  <div class="card">{ph("Фото/лого", 60)}<p class="muted">«Текст отзыва — 2–3 предложения…»<br><b>Имя, должность, компания</b></p></div>
  <div class="card">{ph("Фото/лого", 60)}<p class="muted">«Текст отзыва…»<br><b>Имя, должность, компания</b></p></div>
  <div class="card">{ph("Фото/лого", 60)}<p class="muted">«Текст отзыва…»<br><b>Имя, должность, компания</b></p></div>
</div>''',
"Запросить у клиента 3–5 отзывов. Если нет — временно скрываем блок."),

sec("FAQ",
'''<h2>Частые вопросы</h2>
<details><summary>Какой минимальный тираж?</summary><p>От 30 наборов. Для меньших тиражей — индивидуальный расчёт.</p></details>
<details><summary>Сколько занимает производство?</summary><p>От 10 рабочих дней после утверждения макета. В сезон (октябрь–декабрь) сроки больше — бронируйте заранее.</p></details>
<details><summary>Можно ли добавить наш логотип и фирменные цвета?</summary><p>Да: брендируем упаковку, вложения, открытки и сами предметы.</p></details>
<details><summary>Работаете ли по договору с юрлицами?</summary><p>Да: договор, счёт, закрывающие документы.</p></details>
<details><summary>Доставляете ли подарки удалённым сотрудникам?</summary><p>Да, отправляем по списку адресов по всей России.</p></details>''',
"Вопросы собраны по паттернам конкурентов: тираж, сроки, брендирование, документы, удалёнка."),

sec("Лид-магнит + заявка",
f'''<div class="split">
  <div>
    <h2>Получить подборку идей под вашу задачу</h2>
    <p class="lead">Расскажите о задаче — за 1 день пришлём PDF-подборку с идеями, составами и ценами.</p>
  </div>
  <div>{lead_form("Получить подборку идей")}</div>
</div>''',
"Финальный конверсионный блок каждой страницы."),
]))

# ------------------------------------------------------------------ HR
P["hr.html"] = ("Для HR", "hr.html", "".join([
sec("1 · Hero — оффер под роль",
f'''<div class="split">
  <div>
    <div class="kicker">Для HR и внутренних коммуникаций</div>
    <h1>Подарки, которые говорят сотрудникам: «вы важны»</h1>
    <p class="lead">Welcome pack, благодарность, поддержка после нагрузки, объединение удалённых команд — подарки как инструмент разговора с людьми, а не строчка в бюджете.</p>
    <p><span class="chip">Тираж от 30</span><span class="chip">От 10 раб. дней</span><span class="chip">Доставка по РФ и по адресам</span><span class="chip">Под ваш брендбук</span></p>
    <a class="btn" href="sobrat.html">Собрать подарок под задачу →</a>
    <a class="btn ghost" href="#sets" style="margin-left:8px">Смотреть наборы</a>
  </div>
  {ph("Фото: welcome pack «Ты здесь не случайно» (чёрный + лайм, макет клиента)", 300)}
</div>''',
"Оффер сразу под роль HR + маркеры доверия. Два CTA: квиз и якорь на витрину."),

sec("2 · Готовые наборы · сразу под hero",
f'''<h2 id="sets">Готовые идеи подарков для HR</h2>
<p class="lead">Каждая карточка — отдельная мини-страница набора: фото, что внутри и форма расчёта.</p>
<div class="grid g4">
  {hotcard("Велкомпак «Ты здесь не случайно»", "Адаптация новичков: «меня ждали» с первого дня", ["Новичкам", "Первый день", "IT-команды"], "set-velkompak.html", price="от 2 500 ₽")}
  {hotcard("«Смена вывезена»", "Благодарность линейной команде: ритейл, склад, поддержка", ["Ритейл и склад", "Поддержка"], "set-smena.html", price="от 1 800 ₽")}
  {hotcard("«Режим: восстановление»", "Поддержать после сложного проекта и высокой нагрузки", ["После проекта", "Wellness"], "set-vosstanovlenie.html", price="от 2 200 ₽")}
  {hotcard("«На связи. В системе»", "Объединить удалённую и гибридную команду", ["Удалёнка", "Гибрид"], "set-nasvyazi.html", price="от 2 400 ₽")}
</div>''',
"Первый блок после hero — готовые идеи, как на НГ-экране клиента. Жирная рамка + тень + бейдж = «это кликается»."),

sec("3 · Боли — узнавание",
f'''<h2>Знакомо?</h2>
<div class="grid g4">
  {pain("Подарили павербанки — через неделю никто и не вспомнил", "Безликий мерч не работает на культуру: деньги потрачены, эффекта ноль")}
  {pain("Новичок теряется в первую неделю", "Онбординг на словах: человек сидит с чужим ноутбуком и не чувствует, что его ждали")}
  {pain("Команда выжата после релиза, а «спасибо» в чате не считывается", "Усилия людей остаются незамеченными — копится усталость и обида")}
  {pain("Удалёнщики чувствуют себя отрезанными", "Офис получает пиццу по пятницам, а команда в 12 городах — ничего")}
</div>''',
"Блок узнавания: HR видит свою ситуацию и читает дальше. Формулировки уточним под реальные боли клиентов."),

sec("4 · Переворот — решение",
f'''<div class="split">
  <div>
    <h2>Подарок — это сообщение, а не предмет</h2>
    <p class="lead">Мы собираем не «набор вещей с логотипом», а послание команде: сначала формулируем, что компания хочет сказать человеку, потом подбираем предметы, тексты и упаковку, которые это передают.</p>
    <p><span class="chip on">«Мы тебя ждали»</span><span class="chip on">«Мы видим твою работу»</span><span class="chip on">«Можно выдохнуть»</span><span class="chip on">«Ты часть команды»</span></p>
  </div>
  {ph("Фото-коллаж: карточки-послания из боксов («Ты здесь не случайно», «Смена вывезена», «Проект вывезен»)", 220)}
</div>''',
"Ядро позиционирования БРЕНД. Отстройка от промо-сувенирки: продаём смысл, не предметы."),

sec("5 · Анатомия подарка со смыслом",
f'''<h2>Как устроен такой подарок</h2>
<div class="grid g4">
  <div class="step"><b class="num">1</b><div><b>Смысл</b><br><span class="muted">формулируем послание: что человек должен почувствовать, открыв коробку</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Предметы-носители</b><br><span class="muted">каждая вещь работает на послание: гайд первого месяца, маска «не трогать», чай для паузы</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Тексты и вложения</b><br><span class="muted">карточки, письма, стикеры — то, что превращает набор в разговор</span></div></div>
  <div class="step"><b class="num">4</b><div><b>Упаковка в вашем стиле</b><br><span class="muted">брендбук, тон голоса, внутренний сленг команды</span></div></div>
</div>
{ph("Разворот бокса: фото велкомпака с подписями-выносками к каждому предмету", 200)}''',
"Прогрев продуктом: показываем глубину проработки — этого нет у конкурентов из промо-сувенирки."),

sec("6 · Сценарии — выбор задачи",
f'''<h2>С какой задачей пришли?</h2>
<div class="grid g4">
  {card("Адаптировать новичков", "Welcome pack помогает почувствовать себя частью команды с первого дня", tag="сценарий", img="Иконка", link="set-velkompak.html")}
  {card("Поблагодарить за вклад", "Подарки, которые говорят: «мы видим вашу работу и ценим это»", tag="сценарий", img="Иконка", link="set-smena.html")}
  {card("Поддержать и дать паузу", "Наборы заботы после сложных проектов и насыщенных периодов", tag="сценарий", img="Иконка", link="set-vosstanovlenie.html")}
  {card("Объединить команду", "Для удалённых и гибридных команд — ощущение «мы вместе»", tag="сценарий", img="Иконка", link="set-nasvyazi.html")}
</div>''',
"4 сценария из макета клиента. Клик — якорь к набору ниже или на страницу набора."),

sec("7 · Выгоды языком HR-метрик",
f'''<h2>Что это даёт компании</h2>
<div class="grid g4">
  {metric("−N%", "текучесть на испытательном сроке: новичок с первого дня чувствует «меня ждали»")}
  {metric("+N", "к eNPS: люди, которых видят и благодарят, рекомендуют работодателя")}
  {metric("×N", "вовлечённость удалённых: физическое «мы вместе» вместо смайлика в чате")}
  {metric("1 повод", "для внутреннего PR: фото боксов расходятся по чатам и соцсетям сотрудников")}
</div>''',
"Конкуренты показывают фото коробок — мы говорим языком KPI. Цифры N подставим из кейсов клиента или уберём в пользу формулировок."),

sec("8 · Мини-кейс",
f'''<div class="split">
  <div>
    <div class="kicker">Кейс · заглушка</div>
    <h2>Как welcome pack сократил «тихие увольнения» новичков</h2>
    <p class="lead">Задача → что собрали (смысл, состав, тираж) → как вручали → реакция команды и цифры. 3–5 абзацев + фото.</p>
    <a class="btn ghost sm" href="#">Читать кейс →</a>
  </div>
  {ph("Фото кейса: боксы на онбординге, реакции в чатах", 180)}
</div>''',
"Социальное доказательство. Контент запросим у клиента; пока страница-заглушка."),

sec("9 · Поводы — календарь HR",
'''<h2>Поводы, когда подарок работает сильнее всего</h2>
<div class="iconrow">
  <div><span></span>Welcome pack</div>
  <div><span></span>Новый год и итоги года</div>
  <div><span></span>День компании</div>
  <div><span></span>Завершение проекта</div>
  <div><span></span>День рождения сотрудника</div>
  <div><span></span>Профессиональные праздники</div>
  <div><span></span>Удалённая команда</div>
  <div><span></span>Wellness / забота</div>
</div>''',
"Лента поводов из макета. Каждый повод в будущем — SEO-страница."),

sec("10 · Процесс и условия · кратко",
'''<h2>От брифа до коробки в руках — 6 шагов</h2>
<div class="grid g3">
  <div class="step"><b class="num">1</b><div><b>Бриф</b> <span class="muted">· 1 день</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Концепция и смыслы</b> <span class="muted">· 2–3 дня</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Макет и образец</b></div></div>
  <div class="step"><b class="num">4</b><div><b>Производство</b> <span class="muted">· от 10 раб. дней</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Сборка и контроль</b></div></div>
  <div class="step"><b class="num">6</b><div><b>Доставка: офис или адреса сотрудников</b></div></div>
</div>
<p style="margin-top:12px"><span class="chip">Договор и закрывающие</span><span class="chip">Образец до тиража</span><span class="chip">Брендирование</span><span class="chip">Доставка удалёнщикам по списку адресов</span></p>
<p style="margin-top:8px"><a class="btn ghost sm" href="process.html">Подробнее об этапах и условиях →</a></p>''',
"Сжатая версия страницы «Как мы работаем» — чтобы HR не уходил со страницы за ответом «успеем ли»."),

sec("11 · FAQ под HR",
'''<h2>Частые вопросы HR</h2>
<details><summary>Какой бюджет на человека закладывать?</summary><p>Рабочие вилки: 1 500–2 500 ₽ — забота и благодарность, 2 500–4 000 ₽ — welcome pack, от 4 000 ₽ — статусные наборы. Соберём варианты в 2–3 бюджетах.</p></details>
<details><summary>Доставите каждому удалёнщику домой?</summary><p>Да: собираем список адресов, упаковываем персонально, отправляем по РФ и присылаем отчёт о вручении.</p></details>
<details><summary>У нас меньше 30 человек — возьмётесь?</summary><p>Базовый тираж от 30, для меньших — индивидуальный расчёт.</p></details>
<details><summary>Успеем к дате X?</summary><p>От утверждения макета — от 10 рабочих дней. В сезон (октябрь–декабрь) бронируйте слот заранее.</p></details>
<details><summary>Можно под наш брендбук и тон голоса?</summary><p>Да, включая внутренний сленг команды в текстах вложений.</p></details>''',
"Возражения именно HR-а: бюджет на голову, удалёнка, малый тираж, дедлайн."),

sec("12 · Лид-магнит",
f'''<div class="split">
  <div>
    <h2>Календарь HR-поводов на год + идеи под каждый</h2>
    <p class="lead">PDF: 12 месяцев, поводы, идеи подарков и вилки бюджета. Заберите на почту или в Telegram.</p>
  </div>
  <div class="form">
    <div class="inp">E-mail или @telegram</div>
    <div class="full"><a class="btn" href="#">Получить календарь →</a></div>
    <div class="check full">☑ Согласие на обработку персональных данных</div>
  </div>
</div>''',
"Мягкая конверсия для тех, кто не готов к заявке: собираем базу HR-ов."),

sec("13 · Финальный CTA",
f'''<div class="split">
  <div>
    <h2>Нужны идеи для вашей команды?</h2>
    <p class="lead">Расскажите о вашей задаче — за 1 день предложим идеи, состав и расчёт, которые передадут нужный смысл.</p>
  </div>
  <div>{lead_form("Получить идеи для вашей команды")}</div>
</div>'''),
]))

# ------------------------------------------------------------------ MARKETING
P["marketing.html"] = ("Для маркетинга и PR", "marketing.html", "".join([
sec("1 · Hero — оффер под роль",
f'''<div class="split">
  <div>
    <div class="kicker">Для маркетинга и PR</div>
    <h1>Подарки, которые продолжают разговор с клиентом, когда встреча закончилась</h1>
    <p class="lead">Партнёрские боксы, PR-рассылки, запуски продуктов и after-meeting касания: физический носитель бренда, который не улетает в спам и не теряется в ленте.</p>
    <p><span class="chip">Тираж от 30</span><span class="chip">От 10 раб. дней</span><span class="chip">Рассылка по адресам получателей</span><span class="chip">QR-механики и персонализация</span></p>
    <a class="btn" href="sobrat.html">Собрать подарок под кампанию →</a>
    <a class="btn ghost" href="#sets" style="margin-left:8px">Смотреть наборы</a>
  </div>
  {ph("Фото: бокс «Надёжное партнёрство начинается с деталей» (чёрный + золото)", 300)}
</div>''',
"Клиент зовёт направление «PR» — в меню «Для маркетинга», внутри покрываем оба."),

sec("2 · Готовые наборы · сразу под hero",
f'''<h2 id="sets">Готовые идеи подарков для маркетинга и PR</h2>
<p class="lead">Каждая карточка — отдельная мини-страница набора: фото, что внутри и форма расчёта.</p>
<div class="grid g4">
  {hotcard("«Надёжное партнёрство»", "Статусный бокс для ключевых партнёров и клиентов", ["Партнёрам", "Руководителям"], "set-partnerstvo.html", price="от 3 500 ₽")}
  {hotcard("«Мы открыли новое производство»", "Бокс-инфоповод вместо пресс-релиза", ["СМИ", "Клиентам", "Партнёрам"], "set-proizvodstvo.html", price="от 2 900 ₽")}
  {hotcard("PR-бокс для блогера", "Бокс-история, который распаковывают на камеру", ["Блогерам", "Запуск продукта"], "set-bloger.html", price="от 2 600 ₽")}
  {hotcard("«Впечатление после встречи»", "After-meeting касание, которое возвращает к разговору", ["Клиентам", "B2B-продажи"], "set-vstrecha.html", price="От 1 290 ₽")}
</div>''',
"Первый блок после hero — готовые идеи, как на НГ-экране клиента. Акцентные карточки-ссылки."),

sec("3 · Боли — узнавание",
f'''<h2>Знакомо?</h2>
<div class="grid g4">
  {pain("Мерч с логотипом летит в ящик стола", "Ручки и блокноты не создают ни касания, ни повода вспомнить о бренде")}
  {pain("Письма после выставки никто не открывает", "Email-фоллоу-ап тонет среди сотни таких же — контакт остывает за неделю")}
  {pain("Блогеры не распаковывают безликие посылки", "Рассылка без идеи не даёт ни сторис, ни упоминаний — бюджет в никуда")}
  {pain("Партнёр не чувствует своей значимости", "Годы работы вместе, а на Новый год — корзина с шампанским, как у всех")}
</div>''',
"Боли маркетолога и PR-менеджера: слив бюджета на касания, которые не работают."),

sec("4 · Переворот — решение",
f'''<div class="split">
  <div>
    <h2>Подарок — это медиаканал с открываемостью 100%</h2>
    <p class="lead">Коробку, которая пришла лично тебе, открывают всегда. Мы превращаем это касание в сообщение бренда: смысл, история, предметы-носители и QR-мостик в ваши материалы, бонусы и встречи.</p>
    <p><span class="chip on">«Мы ценим доверие»</span><span class="chip on">«У нас большие новости»</span><span class="chip on">«Разговор продолжается»</span></p>
  </div>
  {ph("Фото: крышка PR-бокса с QR «Сканируйте и получите доступ к материалам»", 220)}
</div>''',
"Ключевой аргумент для маркетолога: подарок как канал со 100% open rate + измеримость через QR."),

sec("5 · Анатомия PR-бокса",
f'''<h2>Как устроен бокс, о котором рассказывают</h2>
<div class="grid g4">
  <div class="step"><b class="num">1</b><div><b>Идея-история</b><br><span class="muted">не «набор с логотипом», а сюжет: тайна книги, запуск производства, итоги партнёрства</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Персональное обращение</b><br><span class="muted">письмо с именем получателя — рассылка перестаёт быть массовой</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Повод для контента</b><br><span class="muted">детали, которые хочется снять: сургуч, конверты-этапы, карточки-цитаты</span></div></div>
  <div class="step"><b class="num">4</b><div><b>QR-мостик в воронку</b><br><span class="muted">материалы, спецусловия, приглашение на встречу — касание становится измеримым</span></div></div>
</div>
{ph("Разворот PR-бокса с выносками: письмо, сургуч, QR, карточки", 200)}''',
"Прогрев продуктом: глубина проработки видна до заявки."),

sec("6 · Сценарии — выбор задачи",
f'''<h2>С какой задачей пришли?</h2>
<div class="grid g4">
  {card("Укрепить партнёрство", "Статусные боксы для ключевых партнёров и клиентов", tag="сценарий", img="Иконка", link="set-partnerstvo.html")}
  {card("Создать инфоповод", "PR-боксы для блогеров и СМИ: распаковка, публикации, охваты", tag="сценарий", img="Иконка", link="set-bloger.html")}
  {card("Презентовать продукт", "Запуск продукта или производства — бокс вместо пресс-релиза", tag="сценарий", img="Иконка", link="set-proizvodstvo.html")}
  {card("Вернуться после встречи", "After-meeting подарок, который возвращает к разговору", tag="сценарий", img="Иконка", link="set-vstrecha.html")}
</div>'''),

sec("7 · Выгоды языком метрик маркетинга",
f'''<h2>Что это даёт бренду</h2>
<div class="grid g4">
  {metric("100%", "открываемость: персональную коробку открывает каждый получатель")}
  {metric("N сторис", "с одной рассылки блогерам: детали бокса созданы для съёмки")}
  {metric("+N%", "доходимость до встречи после after-meeting касания")}
  {metric("QR", "переходы в материалы и заявки — касание можно посчитать")}
</div>''',
"Говорим языком KPI маркетолога. Цифры N — из будущих кейсов, пока формулировки."),

sec("8 · Мини-кейс",
f'''<div class="split">
  <div>
    <div class="kicker">Кейс · заглушка</div>
    <h2>Как рассылка 40 боксов дала N публикаций и M заявок</h2>
    <p class="lead">Задача → идея и состав → механика QR → цифры охватов и заявок. 3–5 абзацев + скриншоты сторис.</p>
    <a class="btn ghost sm" href="#">Читать кейс →</a>
  </div>
  {ph("Коллаж: сторис блогеров с распаковкой бокса", 180)}
</div>''',
"Контент запросим у клиента; пока заглушка."),

sec("9 · Процесс и условия · кратко",
'''<h2>От брифа до вручения — 6 шагов</h2>
<div class="grid g3">
  <div class="step"><b class="num">1</b><div><b>Бриф</b> <span class="muted">· 1 день</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Идея и сюжет</b> <span class="muted">· 2–3 дня</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Макет и образец</b></div></div>
  <div class="step"><b class="num">4</b><div><b>Производство</b> <span class="muted">· от 10 раб. дней</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Сборка и персонализация</b></div></div>
  <div class="step"><b class="num">6</b><div><b>Рассылка по адресам получателей</b></div></div>
</div>
<p style="margin-top:12px"><span class="chip">Договор и закрывающие</span><span class="chip">Образец до тиража</span><span class="chip">Персонализация под получателя</span><span class="chip">Логистика по списку адресов</span></p>
<p style="margin-top:8px"><a class="btn ghost sm" href="process.html">Подробнее об этапах и условиях →</a></p>'''),

sec("10 · FAQ под маркетинг",
'''<h2>Частые вопросы</h2>
<details><summary>Можно персонализировать каждый бокс под получателя?</summary><p>Да: имя в письме, подбор содержимого под сегмент, разные вложения для разных получателей.</p></details>
<details><summary>Возьмёте на себя рассылку по блогерам?</summary><p>Да: упакуем, подпишем и отправим по вашему списку адресов, пришлём статусы доставки.</p></details>
<details><summary>Как понять, что рассылка сработала?</summary><p>QR-механика: переходы, промокоды, заявки — согласуем метрики на брифе.</p></details>
<details><summary>Минимальный тираж для PR-рассылки?</summary><p>Базово от 30, для точечных VIP-рассылок — индивидуальный расчёт.</p></details>''',
"Возражения маркетолога: персонализация, логистика, измеримость."),

sec("11 · Лид-магнит",
f'''<div class="split">
  <div>
    <h2>10 идей PR-рассылок, о которых рассказывают в сторис</h2>
    <p class="lead">PDF с механиками, составами и вилками бюджета — на почту или в Telegram.</p>
  </div>
  <div class="form">
    <div class="inp">E-mail или @telegram</div>
    <div class="full"><a class="btn" href="#">Получить подборку →</a></div>
    <div class="check full">☑ Согласие на обработку персональных данных</div>
  </div>
</div>'''),

sec("12 · Финальный CTA",
f'''<div class="split">
  <div><h2>Нужен подарок под вашу кампанию?</h2><p class="lead">Расскажите о задаче — предложим концепцию, состав и расчёт за 1 день.</p></div>
  <div>{lead_form("Получить идеи для кампании")}</div>
</div>'''),
]))

# ------------------------------------------------------------------ EVENT
P["event.html"] = ("Для event", "event.html", "".join([
sec("1 · Hero — оффер под роль",
f'''<div class="split">
  <div>
    <div class="kicker">Для организаторов ивентов</div>
    <h1>Подарок, который продлевает ценность вашего события</h1>
    <p class="lead">Welcome gift, спикер-паки, спонсорские боксы и after-event касания: событие заканчивается, а впечатление и контакт продолжают работать.</p>
    <p><span class="chip">Тираж от 30</span><span class="chip">От 10 раб. дней</span><span class="chip">Доставка на площадку к монтажу</span><span class="chip">Линейка под статусы гостей</span></p>
    <a class="btn" href="sobrat.html">Обсудить боксы для вашего события →</a>
    <a class="btn ghost" href="#sets" style="margin-left:8px">Смотреть наборы</a>
  </div>
  {ph("Фото: спонсорский бокс «Ваш бренд — польза для участника» (чёрный + жёлтый)", 300)}
</div>''',
"Заголовок из макета «Экран Event», чипсы заменены на B2B-маркеры доверия."),

sec("2 · Готовые наборы · сразу под hero",
f'''<h2 id="sets">Готовые идеи подарков для событий</h2>
<p class="lead">Каждая карточка — отдельная мини-страница набора: фото, что внутри и форма расчёта.</p>
<div class="grid g4">
  {hotcard("Спонсорский набор участника", "Ваш бренд — польза для участника, контакт и результат", ["Участникам", "От спонсора"], "set-sponsor.html", price="от 1 500 ₽")}
  {hotcard("Спикер-пак", "Всё для выступления на высшем уровне", ["Спикерам", "Модераторам"], "set-spiker.html", price="от 2 800 ₽")}
  {hotcard("Благодарность VIP-гостям", "«Спасибо, что были с нами» — статус в каждой детали", ["VIP", "Партнёрам события"], "set-vip.html", price="от 2 400 ₽")}
  {hotcard("«Впечатление после встречи»", "After-event: событие закончилось, разговор продолжается", ["Гостям", "After-event"], "set-vstrecha.html", price="От 1 290 ₽")}
</div>''',
"Первый блок после hero — готовые идеи, как на НГ-экране клиента. Акцентные карточки-ссылки."),

sec("3 · Боли — узнавание",
f'''<h2>Знакомо?</h2>
<div class="grid g4">
  {pain("Пакеты участника бросают на стойке", "Листовки и ручка с логотипом — гость уносит в лучшем случае бейдж")}
  {pain("Спонсор недоволен «размещением логотипа»", "Баннер на сцене не даёт спонсору ни контактов, ни лидов — в следующем году он не вернётся")}
  {pain("После события — тишина", "Три дня контента и эмоций, а через неделю о вас не вспоминает никто")}
  {pain("VIP-гости ждут особого отношения", "Спикеры и партнёры получают то же, что и все, и это считывается")}
</div>''',
"Боли организатора: ценность события испаряется, спонсоры не видят отдачи."),

sec("4 · Переворот — решение",
f'''<div class="split">
  <div>
    <h2>Бокс — это событие, которое продолжается после события</h2>
    <p class="lead">Подарок уезжает домой к гостю и продолжает работать: напоминает, ведёт по QR к материалам и бонусам, даёт спонсору канал контакта, а вам — повод для следующего касания.</p>
    <p><span class="chip on">«Событие продолжается»</span><span class="chip on">«Вы были важной частью»</span><span class="chip on">«Ваш бренд — польза для участника»</span></p>
  </div>
  {ph("Фото: крышка спонсорского бокса «Польза → Контакты → Решения → Продолжение → Результат»", 220)}
</div>''',
"Ядро оффера для event: продлеваем жизнь события и монетизируем спонсорство."),

sec("5 · Анатомия спонсорского бокса",
f'''<h2>Как устроен бокс, который окупает спонсора</h2>
<div class="grid g5">
  <div class="step"><b class="num">1</b><div><b>Польза</b><br><span class="muted">инструменты от партнёра, а не сувениры</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Контакты</b><br><span class="muted">нужные люди и возможности</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Решения</b><br><span class="muted">практические материалы экспертов</span></div></div>
  <div class="step"><b class="num">4</b><div><b>Продолжение</b><br><span class="muted">QR: бонусы и спецпредложения спонсора</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Результат</b><br><span class="muted">новые идеи, проекты, клиенты</span></div></div>
</div>
{ph("Разворот спонсорского бокса с выносками к предметам", 200)}''',
"Пять шагов — дословно с крышки бокса из макета клиента. Готовый язык продажи спонсорам."),

sec("6 · Сценарии — выбор задачи",
f'''<h2>С какой задачей пришли?</h2>
<div class="grid g4">
  {card("Усилить впечатление", "Подарки, которые запоминаются и выделяют ваше событие", tag="сценарий", img="Иконка", link="set-sponsor.html")}
  {card("Создать инфоповод", "Повод для контента, публикаций и обсуждений в соцсетях", tag="сценарий", img="Иконка", link="set-sponsor.html")}
  {card("Подчеркнуть статус", "Премиальные детали для спикеров, партнёров и VIP-гостей", tag="сценарий", img="Иконка", link="set-spiker.html")}
  {card("Поблагодарить важных гостей", "Подарки, которые укрепляют доверие и отношения", tag="сценарий", img="Иконка", link="set-vip.html")}
</div>''',
"Формулировки из макета клиента."),

sec("7 · Выгоды языком метрик события",
f'''<h2>Что это даёт событию</h2>
<div class="grid g4">
  {metric("+N", "к NPS события: гость уносит ценность, а не пакет листовок")}
  {metric("N постов", "гости сами публикуют распаковку — бесплатные охваты события")}
  {metric("₽", "спонсорские пакеты дороже: бокс — измеримый канал для партнёра")}
  {metric("+N%", "возвращаемость: конверт «открыть через 7 дней» и билет коллеге работают на следующее событие")}
</div>''',
"KPI организатора: NPS, охваты, монетизация спонсорства, retention гостей."),

sec("8 · Мини-кейс",
f'''<div class="split">
  <div>
    <div class="kicker">Кейс · заглушка</div>
    <h2>Как боксы для 200 участников подняли продажи спонсорских пакетов</h2>
    <p class="lead">Событие → состав бокса → механика QR спонсора → цифры сканирований и продлений. 3–5 абзацев + фото.</p>
    <a class="btn ghost sm" href="#">Читать кейс →</a>
  </div>
  {ph("Фото: гости события с боксами", 180)}
</div>''',
"Контент запросим у клиента; пока заглушка."),

sec("9 · Поводы на мероприятиях",
'''<h2>Когда бокс работает сильнее всего</h2>
<div class="iconrow" style="grid-template-columns:repeat(6,1fr)">
  <div><span></span>Запуск события</div>
  <div><span></span>Welcome gift</div>
  <div><span></span>Спикеры и VIP</div>
  <div><span></span>After-event подарки</div>
  <div><span></span>Партнёры и спонсоры</div>
  <div><span></span>Юбилей события</div>
</div>'''),

sec("10 · Процесс под дедлайн события",
'''<h2>Успеем к дате события</h2>
<div class="grid g3">
  <div class="step"><b class="num">1</b><div><b>Бриф</b> <span class="muted">· 1 день: формат, гости, спонсоры</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Концепция</b> <span class="muted">· 2–3 дня, согласуем со спонсорами</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Макет и образец</b></div></div>
  <div class="step"><b class="num">4</b><div><b>Производство</b> <span class="muted">· от 10 раб. дней</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Сборка тиража</b></div></div>
  <div class="step"><b class="num">6</b><div><b>Доставка на площадку к монтажу</b></div></div>
</div>
<p style="margin-top:12px"><span class="chip">Резерв слота под дату события</span><span class="chip">Доставка на площадку</span><span class="chip">Договор и закрывающие</span></p>
<p style="margin-top:8px"><a class="btn ghost sm" href="process.html">Подробнее об этапах и условиях →</a></p>''',
"Для event главный страх — дата. Отдельно подчёркиваем резерв слота и доставку на площадку."),

sec("11 · FAQ под event",
'''<h2>Частые вопросы организаторов</h2>
<details><summary>За сколько недель до события заказывать?</summary><p>Оптимально за 4–6 недель. Горящие сроки обсуждаем отдельно.</p></details>
<details><summary>Доставите прямо на площадку?</summary><p>Да, к дате монтажа, с поштучной пересчётом и запасом.</p></details>
<details><summary>Можно разные боксы для участников, спикеров и VIP?</summary><p>Да, линейка в одном стиле события с разным наполнением.</p></details>
<details><summary>Как вшить спонсора в бокс?</summary><p>Продукты и материалы спонсора, QR на его бонусы, совместное брендирование — согласуем с его брендбуком.</p></details>''',
"Возражения организатора: сроки, площадка, линейка по статусам, интеграция спонсора."),

sec("12 · Лид-магнит",
f'''<div class="split">
  <div>
    <h2>Чек-лист подарков события: от welcome gift до after-event</h2>
    <p class="lead">PDF: что дарить каждой аудитории события, вилки бюджета, тайминг заказа.</p>
  </div>
  <div class="form">
    <div class="inp">E-mail или @telegram</div>
    <div class="full"><a class="btn" href="#">Получить чек-лист →</a></div>
    <div class="check full">☑ Согласие на обработку персональных данных</div>
  </div>
</div>'''),

sec("13 · Финальный CTA",
f'''<div class="split">
  <div><h2>Нужны идеи для вашего события?</h2><p class="lead">Расскажите о мероприятии — предложим концепции, состав, оформление и рассчитаем стоимость.</p></div>
  <div>{lead_form("Получить идеи для ивента")}</div>
</div>'''),
]))

# ------------------------------------------------------------------ НОВЫЙ ГОД
P["new-year.html"] = ("Новый год", "new-year.html", "".join([
sec("1 · Hero сезона",
f'''<div class="split">
  <div>
    <div class="kicker">Новогодние подарки ✦</div>
    <h1>Новогодние подарки с заботой и смыслом</h1>
    <p class="lead">Для детей сотрудников, вашей команды и партнёров — под вашу задачу, бюджет и фирменный стиль.</p>
    <p><span class="chip">Тираж от 30 наборов</span><span class="chip">Индивидуальная комплектация</span><span class="chip">Доставка по России</span></p>
    <a class="btn" href="#segments">Получить подборку подарков</a>
    <a class="btn ghost" href="ideas.html" style="margin-left:8px">Смотреть идеи</a>
  </div>
  {ph("Фото: три НГ-бокса с овечками (зелёно-бежево-золотая гамма, макет «Экран НГ»)", 300)}
</div>''',
"В сезон раздел получает новогоднюю атмосферу (в дизайне), плюс плашку-анонс на главной."),

sec("2 · Готовые наборы · сегменты, как на макете",
f'''<h2 id="segments">Кому дарим?</h2>
<p class="lead">Три сегмента с НГ-экрана клиента. Каждая карточка — мини-страница набора: фото, что внутри, форма расчёта.</p>
<div class="grid g3">
  {hotcard("Новогодние подарки детям", "«Зимняя сказка»: радость, игра и маленькое чудо", ["3–6 лет", "7–10 лет", "10–13 лет", "Универсальные"], "set-skazka.html", img="Фото: бокс с овечкой", cta="Смотреть подарки детям")}
  {hotcard("Новогодние подарки сотрудникам", "«Ты нам дорог»: спасибо за год, пусть будет время на главное", ["Для всей команды", "Для офиса и удалённых", "Для руководителей"], "set-dorog.html", img="Фото: бокс сотруднику", cta="Смотреть подарки сотрудникам")}
  {hotcard("Новогодние подарки партнёрам", "«Партнёрство, которое строит будущее»: ценность отношений в каждой детали", ["Клиентам", "Ключевым партнёрам", "Руководителям"], "set-ng-partner.html", img="Фото: бокс партнёру", price="3 900 ₽ за набор", cta="Смотреть подарки партнёрам")}
</div>''',
"Развилка 1-в-1 с НГ-экрана клиента, но карточки теперь явно кликабельные и ведут на страницы наборов."),

sec("3 · Боли — узнавание",
f'''<h2>Знакомо?</h2>
<div class="grid g4">
  {pain("Опять конфеты и шампанское, как у всех", "Подарок сливается с десятком таких же и не говорит о компании ничего")}
  {pain("Спохватились в ноябре — уже не успеть", "Производственные слоты заняты, остаётся то, что лежит на складе у сувенирки")}
  {pain("Детям от 3 до 15 — один и тот же набор", "Подросток получает погремушку: родитель-сотрудник это запоминает")}
  {pain("Партнёру неловко дарить «корзину»", "Годовое сотрудничество заслуживает большего, чем стандартный набор из каталога")}
</div>''',
"Сезонные боли того, кто отвечает за НГ-подарки в компании."),

sec("4 · Переворот — решение",
f'''<div class="split">
  <div>
    <h2>Новый год — единственный повод, когда компания говорит со всеми сразу</h2>
    <p class="lead">С командой, с детьми сотрудников, с партнёрами. Мы делаем так, чтобы каждый услышал своё: ребёнок — чудо, сотрудник — «спасибо за год», партнёр — «впереди общие вершины».</p>
    <p><span class="chip on">«Спасибо за год»</span><span class="chip on">«Пусть будет время на главное»</span><span class="chip on">«Впереди новые цели»</span></p>
  </div>
  {ph("Фото: три НГ-бокса рядом — детский, командный, партнёрский", 220)}
</div>''',
"Рефрейм: НГ-подарки не «обязаловка», а годовое послание компании всем аудиториям."),

sec("5 · Анатомия НГ-набора",
f'''<h2>Почему эти наборы запоминают</h2>
<div class="grid g4">
  <div class="step"><b class="num">1</b><div><b>Смысл под аудиторию</b><br><span class="muted">ребёнку — сказка, сотруднику — благодарность, партнёру — общее будущее</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Предметы с продолжением</b><br><span class="muted">конверты-этапы к книге, коробка-кормушка после праздника</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Персонализация</b><br><span class="muted">имя, письмо от первого лица, фирменные элементы</span></div></div>
  <div class="step"><b class="num">4</b><div><b>Ваш бренд в деталях</b><br><span class="muted">упаковка и вложения в стиле компании, а не «ёлочки из клипарта»</span></div></div>
</div>''',
"Прогрев продуктом: фишки из реальных макетов (конверты «после 50 страниц», кормушка)."),

sec("6 · Когда заказывать · снятие страха сроков",
'''<h2>Календарь НГ-сезона</h2>
<div class="grid g4">
  <div class="card"><h3>Сентябрь</h3><p class="muted">идеальный старт: полный выбор концепций и слотов</p></div>
  <div class="card"><h3>Октябрь</h3><p class="muted">рабочий срок: успеваем с образцом и брендированием</p></div>
  <div class="card"><h3>Ноябрь</h3><p class="muted">впритык: сокращённый выбор, бронируйте слот сразу</p></div>
  <div class="card"><h3>Декабрь</h3><p class="muted">только готовые решения со склада</p></div>
</div>''',
"Единственный «дедлайновый» блок: мягко подгоняет ранний заказ, у конкурентов такого нет."),

sec("7 · FAQ под НГ",
'''<h2>Частые вопросы</h2>
<details><summary>Успеем, если закажем в ноябре?</summary><p>Скорее всего да, но выбор будет уже: бронируйте слот и утверждайте макет в первые дни.</p></details>
<details><summary>Можно разные наборы детям разных возрастов?</summary><p>Да: 3–6, 7–10, 10–13 лет — разное наполнение в едином стиле.</p></details>
<details><summary>Доставите сотрудникам по домам до праздников?</summary><p>Да, по списку адресов по всей России с отчётом о вручении.</p></details>
<details><summary>Что с документами для закупки?</summary><p>Договор, счёт, закрывающие — всё для бухгалтерии.</p></details>''',
""),

sec("8 · Форма расчёта НГ",
f'''<div class="split">
  <div>
    <h2>Соберём набор под вашу компанию</h2>
    <p><span class="chip">Персонализация и фирменные элементы</span><span class="chip">Брендирование упаковки и вложений</span><span class="chip">Подбор состава под бюджет</span><span class="chip">Доставка по всей России</span></p>
    <p class="muted" style="margin-top:10px">Минимальный тираж — от 30 наборов · Срок производства — от 10 рабочих дней</p>
  </div>
  <div>{lead_form()}</div>
</div>''',
"Форма — из НГ-макетов клиента: смыслы, тираж, возраст, бюджет, город, дата, брендирование."),
]))

# ------------------------------------------------------------------ ИДЕИ
_filters = ('<div style="margin-bottom:6px"><span class="chip on">Все идеи</span><span class="chip">Для HR</span>'
            '<span class="chip">Для маркетинга</span><span class="chip">Для event</span><span class="chip">Новый год</span>'
            '&nbsp;&nbsp;<span class="chip">Повод ▾</span><span class="chip">Бюджет ▾</span><span class="chip">Тираж ▾</span>'
            '<span class="chip" style="border-style:dashed">Сбросить фильтры ⟳</span></div>')
P["ideas.html"] = ("Идеи подарков", "ideas.html", "".join([
sec("Hero каталога",
f'''<div class="split">
  <div>
    <div class="kicker">Идеи подарков</div>
    <h1>Идеи подарков со смыслом</h1>
    <p class="lead">Подборки подарков под разные задачи и поводы. Каждую идею адаптируем под ваш бренд, бюджет и тираж.</p>
  </div>
  {ph("Фото: бокс с сухоцветами (макет «Экран идеи подарков»)", 220)}
</div>'''),

sec("Фильтры",
_filters,
"Фильтры из макета: направление + повод + бюджет + тираж. В проде — живая фильтрация."),

sec("Сетка идей",
f'''<div class="grid g5">
  {card("Спасибо за год вместе", "Выразить благодарность и отметить вклад команды", "От 1 390 ₽ · от 20 шт.", "Для HR", link="set-dorog.html")}
  {card("Это про нас", "Подарок, который говорит о ценностях бренда", "От 1 750 ₽ · от 30 шт.", "Для маркетинга", link="set-partnerstvo.html")}
  {card("Впечатление после встречи", "Продлить эмоции и остаться в памяти", "От 1 290 ₽ · от 20 шт.", "Для event", link="set-vstrecha.html")}
  {card("Забота, которая чувствуется", "Про внимание к людям и их комфорту", "От 1 550 ₽ · от 20 шт.", "Для HR", link="set-vosstanovlenie.html")}
  {card("Партнёрство, которое создаёт будущее", "Подчеркнуть статус и укрепить отношения", "От 2 250 ₽ · от 30 шт.", "Для маркетинга", link="set-ng-partner.html")}
  {card("Велкомпак «Ты здесь не случайно»", "Онбординг новичков с первого дня", "от 2 500 ₽ · заглушка", "Для HR", link="set-velkompak.html")}
  {card("«Режим: восстановление»", "Поддержать после сложного проекта", "от 2 200 ₽ · заглушка", "Для HR", link="set-vosstanovlenie.html")}
  {card("Спикер-пак", "Фокус, польза и энергия для выступления", "от 2 800 ₽ · заглушка", "Для event", link="set-spiker.html")}
  {card("«Зимняя сказка» детям", "Новогоднее чудо детям сотрудников", "цена уточняется", "Новый год", link="set-skazka.html")}
  {card("PR-бокс для блогера", "Рассылка, о которой хочется рассказать", "от 2 600 ₽ · заглушка", "Для маркетинга", link="set-bloger.html")}
</div>
<p style="margin-top:16px;text-align:center"><a class="btn ghost" href="#">Показать ещё идеи ↓</a></p>''',
"Каждая карточка → страница набора (box.html). Каталог пополняется без правки вёрстки."),

sec("CTA",
f'''<div class="split">
  <div><h2>Не нашли свою задачу?</h2><p class="lead">Соберём идею под ваш смысл с нуля — начните с короткого квиза.</p><a class="btn" href="sobrat.html">Собрать подарок →</a></div>
  <div>{ph("Иллюстрация/фото", 140)}</div>
</div>'''),
]))

# ------------------------------------------------------------------ КВИЗ
P["sobrat.html"] = ("Собрать подарок", "sobrat.html", "".join([
sec("Квиз-конструктор",
f'''<div class="split">
  <div>
    <div class="kicker">Собрать подарок</div>
    <h1>Расскажите, что должен сказать подарок</h1>
    <p class="lead">Ответьте на несколько вопросов — мы предложим идею, состав и подачу, которые передадут нужный смысл.</p>

    <h3 style="margin-top:18px">1. Кому дарим?</h3>
    <p><span class="chip on">Сотрудникам</span><span class="chip">Новичкам</span><span class="chip">Клиентам</span><span class="chip">Партнёрам</span><span class="chip">Гостям события</span><span class="chip">Команде проекта</span></p>

    <h3 style="margin-top:14px">2. Что хотим сказать?</h3>
    <p><span class="chip">Спасибо</span><span class="chip on">Вы важны</span><span class="chip">Добро пожаловать</span><span class="chip">Мы ценим доверие</span><span class="chip">Вы сделали большое дело</span><span class="chip">Событие продолжается</span></p>

    <h3 style="margin-top:14px">3. Какое чувство должно остаться?</h3>
    <p><span class="chip on">Забота</span><span class="chip">Тепло</span><span class="chip">Уважение</span><span class="chip">Статус</span><span class="chip">Вдохновение</span><span class="chip">Вау</span><span class="chip">Причастность</span></p>

    <h3 style="margin-top:14px">4. Повод и рамки</h3>
    <div class="grid g4" style="margin-top:8px">
      <div class="inp">Повод: Новый год ▾</div>
      <div class="inp">Бюджет: до 2 500 ₽ ▾</div>
      <div class="inp">Тираж: 100 шт. ▾</div>
      <div class="inp">Срок: до 20 декабря ▾</div>
    </div>
    <p style="margin-top:18px"><a class="btn" href="#">Получить идею подарка →</a> <a class="btn ghost" href="contacts.html" style="margin-left:8px">Нужна помощь? 💬</a></p>
    <p class="muted">✦ Мы подберём идею, состав, упаковку и текст, которые точно передадут ваш смысл.</p>
  </div>
  <div class="card">
    <h3>Ваш смысл ♡</h3>
    <p class="muted">Кому: Сотрудникам<br>Что говорим: Вы важны<br>Ощущение: Забота и тепло<br>Повод: Новый год<br>Рамки: до 2 500 ₽ · 100 шт. · до 20 декабря</p>
    <div class="ph" style="min-height:70px">Живая панель-сводка выбора</div>
    <p class="muted">« Мы превратим этот смысл в подарок, который запомнится надолго.</p>
  </div>
</div>''',
"Структура квиза — точно из макета «Собрать подарок». В проде: выбор чипсов обновляет панель «Ваш смысл», по кнопке — контакты + заявка в Telegram. Такого конструктора нет ни у одного конкурента."),
]))

# ------------------------------------------------------------------ ПРОЦЕСС
P["process.html"] = ("Как мы работаем", "process.html", "".join([
sec("Hero страницы",
'''<div class="kicker">Процесс и условия</div>
<h1>Как мы работаем</h1>
<p class="lead">От брифа до коробки в руках получателя — 6 понятных этапов с фиксированными сроками.</p>''',
"Страница снимает главный B2B-страх: «успеем ли к дате» + вопросы про документы."),

sec("Этапы · подробно",
'''<div class="grid g2">
  <div class="step"><b class="num">1</b><div><b>Бриф · 1 день</b><br><span class="muted">Кому дарим, что хотим сказать, бюджет, тираж, сроки. Можно начать с квиза «Собрать подарок»</span></div></div>
  <div class="step"><b class="num">2</b><div><b>Концепция и смыслы · 2–3 дня</b><br><span class="muted">Идея, состав, тексты вложений, варианты в 2–3 бюджетах</span></div></div>
  <div class="step"><b class="num">3</b><div><b>Макет и образец</b><br><span class="muted">Дизайн-макет упаковки и вложений, физический образец до запуска тиража</span></div></div>
  <div class="step"><b class="num">4</b><div><b>Производство · от 10 раб. дней</b><br><span class="muted">Закупка, брендирование, печать. В сезон НГ — бронируем слот заранее</span></div></div>
  <div class="step"><b class="num">5</b><div><b>Сборка и упаковка</b><br><span class="muted">Ручная сборка, контроль качества каждого набора</span></div></div>
  <div class="step"><b class="num">6</b><div><b>Доставка</b><br><span class="muted">В офис одним тиражом или по списку адресов сотрудников по всей России</span></div></div>
</div>'''),

sec("Условия и B2B",
'''<div class="grid g4">
  <div class="card"><h3>Тираж от 30 наборов</h3><p class="muted">меньше — индивидуальный расчёт</p></div>
  <div class="card"><h3>Договор и счёт</h3><p class="muted">полный пакет закрывающих документов</p></div>
  <div class="card"><h3>Брендирование</h3><p class="muted">логотип, фирменные цвета, персональные вложения и тексты</p></div>
  <div class="card"><h3>Образец до тиража</h3><p class="muted">утверждаете вживую, а не по фото</p></div>
</div>''',
"Уточнить у клиента: реальные условия оплаты (предоплата?), точные сроки, гарантия сроков."),

sec("Доставка удалённым командам",
'''<h2>Доставим каждому — даже если команда в 40 городах</h2>
<p class="lead">Собираем список адресов, упаковываем персонально и отправляем каждому сотруднику домой. Вы получаете отчёт о вручении.</p>''',
"Растущий запрос, слабо раскрыт у конкурентов — выносим отдельным блоком."),

sec("FAQ",
'''<details><summary>Что будет, если не успеете к дате?</summary><p>Фиксируем срок в договоре. Условие гарантии — уточнить у клиента.</p></details>
<details><summary>Можно ли увидеть образец до оплаты тиража?</summary><p>Да, собираем образец после утверждения концепции.</p></details>
<details><summary>Из чего складывается цена набора?</summary><p>Состав + упаковка + брендирование + сборка + логистика. Присылаем смету с разбивкой.</p></details>''',
""),

sec("CTA",
f'''<div class="split">
  <div><h2>Обсудить вашу задачу</h2><p class="lead">Расскажите, что и кому хотите подарить — вернёмся с идеями и расчётом.</p></div>
  <div>{lead_form()}</div>
</div>'''),
]))

# ------------------------------------------------------------------ БЛОГ
P["blog.html"] = ("Блог", "blog.html", "".join([
sec("Hero блога",
'''<div class="kicker">Блог</div>
<h1>Идеи, смыслы и практика корпоративных подарков</h1>
<p class="lead">Статьи под сезонные запросы и вопросы HR, маркетинга и event-менеджеров.</p>''',
"SEO-машина: сезонные статьи публикуются за 3–4 месяца до пика запросов."),

sec("Сетка статей",
f'''<div class="grid g3">
  {card("Корпоративные подарки на Новый год 2027: когда заказывать, чтобы успеть", "Сезонная SEO-статья · публикация летом", tag="Новый год", img="Обложка статьи", link="#", btn="Читать")}
  {card("Welcome pack: что положить, чтобы новичок остался", "Под запрос «welcome pack на заказ»", tag="HR", img="Обложка статьи", link="#", btn="Читать")}
  {card("Подарки удалённой команде: логистика и идеи", "Под запрос «подарки удалённым сотрудникам»", tag="HR", img="Обложка статьи", link="#", btn="Читать")}
  {card("Как посчитать бюджет на подарки сотрудникам", "Калькулятор-статья для HR со сметой", tag="практика", img="Обложка статьи", link="#", btn="Читать")}
  {card("PR-рассылка блогерам: как сделать бокс, о котором расскажут", "Под маркетинг-запросы", tag="маркетинг", img="Обложка статьи", link="#", btn="Читать")}
  {card("50 идей подарков для event: от welcome gift до after-event", "Трафиковая подборка", tag="event", img="Обложка статьи", link="#", btn="Читать")}
</div>''',
"Темы — заготовки под контент-план. Каждая статья заканчивается CTA на квиз или подборку идей."),
]))

# ------------------------------------------------------------------ КОНТАКТЫ
P["contacts.html"] = ("Контакты", "contacts.html", "".join([
sec("Контакты + форма",
f'''<div class="split">
  <div>
    <div class="kicker">Контакты</div>
    <h1>Давайте создадим подарки, которые говорят от сердца</h1>
    <p class="lead">Отвечаем быстро, внимательно и по делу. Расскажите о вашей задаче — предложим идею, которая передаст ваш смысл.</p>
    <div class="grid g2">
      <div class="card"><h3>Телефон</h3><p class="muted">+7 (900) 000-00-00<br>Пн–Пт 10:00–19:00</p></div>
      <div class="card"><h3>Почта</h3><p class="muted">hello@example.ru<br>Отвечаем в течение дня</p></div>
      <div class="card"><h3>Telegram</h3><p class="muted">@your_team<br>Быстрые ответы</p></div>
      <div class="card"><h3>Офис</h3><p class="muted">Москва, ул. Садовая, 10<br>По предварительной встрече</p></div>
    </div>
  </div>
  <div class="card">
    {ph("Фото: бокс «Важно только важное»", 120)}
    <h2>Напишите нам ♡</h2>
    <p class="muted">Расскажите о вашем событии, бюджете и пожеланиях. Мы предложим решение, которое передаст нужный смысл.</p>
    <div class="form">
      <div class="inp">Ваше имя</div>
      <div class="inp">Компания</div>
      <div class="inp full">Email</div>
      <div class="inp">Телефон</div>
      <div class="inp">Повод / событие ▾</div>
      <div class="inp full" style="min-height:70px">Расскажите о задаче: кому, что важно сказать, бюджет, тираж, сроки…</div>
      <div class="check full">☐ Согласен на обработку персональных данных</div>
      <div class="full"><a class="btn" href="#">Отправить заявку</a></div>
    </div>
  </div>
</div>''',
"Структура и тексты — из макета «Экран Контакты». Контакты-заглушки заменим на реальные."),
]))

# ------------------------------------------------------------------ мини-страницы наборов
for _k, _s in SETS.items():
    P[set_link(_k)] = (_s["title"], _s["dir_page"], set_page_body(_k, _s))

# ------------------------------------------------------------------ build
import shutil
os.makedirs(OUT, exist_ok=True)
shutil.copy(os.path.join(os.path.dirname(os.path.abspath(__file__)), "comments.js"), os.path.join(OUT, "comments.js"))
with open(os.path.join(OUT, "wire.css"), "w") as f:
    f.write(CSS)
for fname, (title, active, body) in P.items():
    with open(os.path.join(OUT, fname), "w") as f:
        f.write(page(fname, title, active, body))
print("OK:", ", ".join(sorted(os.listdir(OUT))))
