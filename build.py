# -*- coding: utf-8 -*-
"""Генератор вайрфрейм-прототипа AntiDesigner. python build.py -> index.html, dist/"""
import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DIST_DIR = os.path.join(BASE_DIR, "dist")
os.makedirs(DIST_DIR, exist_ok=True)

NAV_MAIN = [
    ("#hero", "Главная"),
    ("#approach", "Почему я"),
    ("#stack", "Стек"),
    ("#formats", "Форматы и цены"),
    ("#cases", "Кейсы"),
    ("#process", "Процесс"),
    ("#faq", "FAQ"),
    ("index.html", "🎨 Дизайн-система"),
]

CSS = """
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1d1d1d;background:#fff;line-height:1.5;-webkit-font-smoothing:antialiased}
.wrap{max-width:1160px;margin:0 auto;padding:0 20px}
a{color:inherit}
/* header */
header{position:sticky;top:0;background:rgba(255,255,255,0.96);backdrop-filter:blur(10px);border-bottom:1px solid #e3e3e3;z-index:50}
.hrow{display:flex;align-items:center;gap:18px;padding:14px 0;position:relative}
.logo{font-size:17px;font-weight:700;letter-spacing:1px;text-decoration:none;flex-shrink:0}
.logo small{display:block;font-size:11px;font-weight:400;letter-spacing:.3px;color:#8a8a8a}
nav{display:flex;gap:16px;margin-left:auto;white-space:nowrap}
nav a{font-size:13.5px;text-decoration:none;color:#444;padding:4px 2px;border-bottom:2px solid transparent;transition:all .15s}
nav a:hover{border-color:#1d1d1d;color:#1d1d1d}
.hcta{white-space:nowrap}
/* burger */
.burg{flex-shrink:0;width:38px;height:38px;border:1px solid #c9c9c9;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;cursor:pointer;user-select:none}
#burger{display:none}
.drop{display:none;position:absolute;top:100%;right:0;min-width:240px;background:#fff;border:1px solid #ddd;border-radius:0 0 12px 12px;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:10px 0}
#burger:checked ~ .drop{display:block}
.drop a{display:block;padding:10px 20px;font-size:14px;text-decoration:none;color:#333}
.drop a:hover{background:#f4f4f4}
.drop hr{border:none;border-top:1px solid #eee;margin:6px 0}
/* buttons, chips */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#1d1d1d;color:#fff;font-size:14px;font-weight:600;padding:12px 22px;border-radius:8px;text-decoration:none;border:1px solid #1d1d1d;transition:all .15s;cursor:pointer}
.btn:hover{background:#333;border-color:#333}
.btn.ghost{background:#fff;color:#1d1d1d}
.btn.ghost:hover{background:#f4f4f4}
.btn.sm{padding:8px 14px;font-size:13px}
.btn-sub{display:block;font-size:11.5px;font-weight:400;opacity:0.75;margin-top:2px}
.chip{display:inline-block;border:1px solid #c9c9c9;border-radius:99px;padding:6px 12px;font-size:12.5px;color:#333;background:#fff;margin:0 6px 8px 0}
.chip.on{border-color:#1d1d1d;background:#1d1d1d;color:#fff}
/* sections */
section{position:relative;border:1px solid #ddd;border-radius:14px;background:#fafafa;margin:28px 0;padding:34px 28px 28px}
.sec-label{position:absolute;top:-11px;left:18px;background:#fff;border:1px solid #ccc;border-radius:99px;font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#7a7a7a;padding:3px 12px}
.note{font-size:12.5px;color:#888;font-style:italic;margin-top:16px;line-height:1.45;padding-top:12px;border-top:1px dashed #e2e2e2}
.note:before{content:"→ ";font-weight:700;color:#666}
h1{font-size:36px;line-height:1.2;font-weight:700;margin:10px 0 14px;letter-spacing:-0.02em;text-wrap:balance}
h2{font-size:26px;line-height:1.25;margin-bottom:8px;font-weight:700;letter-spacing:-0.01em;text-wrap:balance}
h3{font-size:17px;margin-bottom:8px;font-weight:700;line-height:1.3;text-wrap:balance}
p, li{text-wrap:pretty}
.kicker{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#8a8a8a;display:inline-block;margin-bottom:6px}
.lead{font-size:16px;color:#555;max-width:680px;margin:0 0 18px;line-height:1.55}
.muted{color:#777;font-size:13.5px}
/* placeholders */
.ph{border:1.5px dashed #b9b9b9;border-radius:10px;background:repeating-linear-gradient(45deg,#f4f4f4,#f4f4f4 12px,#ececec 12px,#ececec 24px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#777;font-size:13px;padding:16px;min-height:90px;gap:6px}
.ph b{font-size:13.5px;color:#444}
/* grids */
.grid{display:grid;gap:18px;margin-top:18px}
.g2{grid-template-columns:1fr 1fr}
.g3{grid-template-columns:repeat(3,1fr)}
.g4{grid-template-columns:repeat(4,1fr)}
.split{display:grid;grid-template-columns:1.2fr .8fr;gap:28px;align-items:start}
.card{border:1px solid #ddd;border-radius:12px;background:#fff;padding:22px;display:flex;flex-direction:column;gap:10px}
.card.highlight{border:2px solid #1d1d1d;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.06);position:relative}
.card-badge{position:absolute;top:-11px;right:16px;background:#1d1d1d;color:#fff;font-size:10px;letter-spacing:1px;text-transform:uppercase;padding:3px 10px;border-radius:99px;font-weight:600}
/* hot card clickable */
a.card{text-decoration:none;color:inherit}
.hot{border:2px solid #1d1d1d;box-shadow:0 6px 18px rgba(0,0,0,.1);position:relative;transition:transform .18s,box-shadow .18s;cursor:pointer;display:flex;flex-direction:column;min-height:100%}
.hot:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,.16)}
.hot .badge{position:absolute;top:-11px;right:14px;background:#1d1d1d;color:#fff;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;padding:4px 12px;border-radius:99px;font-weight:600}
.hot .go{margin-top:auto;font-weight:700;font-size:13.5px;padding-top:14px;border-top:1px solid #eee;color:#1d1d1d;display:block}
.case-body{display:flex;flex-direction:column;flex:1;gap:8px}

/* comparison & lists */
.chk-list{list-style:none;margin:8px 0;display:flex;flex-direction:column;gap:8px}
.chk-list li{position:relative;padding-left:22px;font-size:13.5px;color:#444;line-height:1.45}
.chk-list li:before{content:"✓";position:absolute;left:0;top:0;font-weight:700;color:#1d1d1d}
.chk-list.cross li:before{content:"✕";color:#999}
/* steps */
.step{display:flex;gap:16px;align-items:flex-start;border:1px solid #ddd;background:#fff;border-radius:12px;padding:18px}
.step b.num{flex:0 0 34px;height:34px;border-radius:50%;border:1.5px solid #1d1d1d;display:flex;align-items:center;justify-content:center;font-size:15px;background:#fff}
.step-time{display:inline-block;font-size:11px;letter-spacing:1px;text-transform:uppercase;background:#f0f0f0;padding:2px 8px;border-radius:6px;color:#666;margin-bottom:4px}
/* faq */
details{border:1px solid #ddd;background:#fff;border-radius:10px;padding:14px 18px;margin-top:10px;font-size:14px;transition:all .15s}
details[open]{border-color:#1d1d1d}
details summary{cursor:pointer;font-weight:600;user-select:none;outline:none}
details p{margin-top:10px;color:#555;line-height:1.55}
/* footer */
footer{border-top:1px solid #e3e3e3;margin-top:40px;padding:32px 0 80px;font-size:13px;color:#777}
.frow{display:flex;gap:30px;flex-wrap:wrap;justify-content:space-between}
/* comments UI */
.cmt-btn{position:absolute;top:-11px;right:16px;background:#fff;border:1.5px solid #1d1d1d;border-radius:99px;font-size:11px;padding:3px 12px;cursor:pointer;font-family:inherit;font-weight:600}
.cmt-btn:hover{background:#1d1d1d;color:#fff}
.cmt-fab{position:fixed;left:22px;bottom:22px;background:#fff;border:1.5px solid #1d1d1d;color:#1d1d1d;border-radius:99px;padding:12px 18px;font-size:13.5px;font-family:inherit;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.18);cursor:pointer;z-index:60}
.cmt-fab:hover{background:#1d1d1d;color:#fff}
.cmt-form{margin-top:14px;border:1.5px solid #1d1d1d;border-radius:10px;background:#fff;padding:14px}
.cmt-form textarea{width:100%;border:1px solid #c9c9c9;border-radius:8px;padding:10px;font-size:13.5px;font-family:inherit;resize:vertical;outline:none}
.cmt-row{display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap}
.cmt-row button{background:#1d1d1d;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;cursor:pointer;font-family:inherit;font-weight:600}
.cmt-x{font-size:12.5px;color:#9a9a9a;cursor:pointer;text-decoration:underline}
.cmt-list{margin-top:12px;display:grid;gap:8px}
.cmt-item{background:#fff8d6;border:1px solid #e6d783;border-radius:10px;padding:10px 14px;font-size:13px}
.cmt-item span{color:#8c7820;font-size:11px;margin-left:6px}
.cmt-item p{margin-top:4px;white-space:pre-wrap;color:#333}
#cmt-page-panel{border:1.5px dashed #1d1d1d;border-radius:14px;background:#fffdf2;margin:24px 0;padding:18px 22px}
#cmt-page-panel:empty{display:none}
.cmt-panel-title{font-size:14px;font-weight:700}
/* responsive */
@media(max-width:1020px){nav{display:none}}
@media(max-width:880px){.g3,.g4{grid-template-columns:1fr 1fr}.split{grid-template-columns:1fr}h1{font-size:30px}}
@media(max-width:600px){.g2,.g3,.g4{grid-template-columns:1fr}.hcta{display:none}section{padding:26px 18px 20px}}
"""

def ph(text, sub=None, h=None):
    style = f' style="min-height:{h}px"' if h else ""
    sub_html = f'<span class="muted" style="font-size:11.5px">{sub}</span>' if sub else ""
    return f'<div class="ph"{style}><b>{text}</b>{sub_html}</div>'

def sec(num_title, inner, note=None, sid=None):
    id_attr = f' id="{sid}"' if sid else ""
    n = f'<p class="note">{note}</p>' if note else ""
    return f'<section{id_attr}><span class="sec-label">{num_title}</span>{inner}{n}</section>'

def generate_index_html():
    navhtml = "".join(f'<a href="{h}">{t}</a>' for h, t in NAV_MAIN)
    drop_html = "".join(f'<a href="{h}">{t}</a>' for h, t in NAV_MAIN)

    # ---------------- 1. Hero Section ----------------
    hero_inner = f'''
    <div class="split" style="align-items:center">
      <div>
        <span class="kicker">Сайты и&nbsp;брендинг под ключ</span>
        <h1>Запускаю сайты под ключ за&nbsp;7&nbsp;дней.<br>Тексты, структуру и&nbsp;визуал беру на&nbsp;себя.</h1>
        <p class="lead">Погружаюсь в&nbsp;ваш бизнес, формулирую смыслы и&nbsp;создаю графику. Вы&nbsp;получаете готовый сайт на&nbsp;Tilda или чистом коде без поиска копирайтеров и&nbsp;составления ТЗ.</p>
        <div style="margin:14px 0 20px">
          <span class="chip on">5-7 дней на&nbsp;запуск</span>
          <span class="chip on">Тексты и&nbsp;визуал включены</span>
          <span class="chip on">Договор и&nbsp;чеки самозанятого</span>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
          <a class="btn" href="https://t.me/AntiDesigner01" target="_blank">
            <span>Обсудить задачу в&nbsp;Telegram</span>
            <span class="btn-sub">без длинных брифов, ответ за&nbsp;15&nbsp;минут</span>
          </a>
          <a class="btn ghost" href="#formats">Посмотреть форматы и&nbsp;цены</a>
        </div>
      </div>
      <div>
        {ph("Интерактивный мокап: Ноутбук + Смартфон", "Показ адаптивной сборки сайта с тремя плашками: «PageSpeed 100/100», «Тексты включены», «0 ₽ конструкторам»", 280)}
      </div>
    </div>
    '''
    hero_note = "Цель: за 3 секунды зацепить внимание и дать понятный первый шаг без барьера «пришлите мне бриф и текст»."
    s1 = sec("01 · Первый экран (Hero)", hero_inner, hero_note, "hero")

    # ---------------- 2. Approach ----------------
    approach_inner = f'''
    <span class="kicker">Формат и&nbsp;подход</span>
    <h2>Почему со&nbsp;мной проще, чем со&nbsp;студией или фрилансером</h2>
    <p class="lead">Вам не&nbsp;нужно быть маркетологом и&nbsp;управлять командой. Я&nbsp;объединил скорость одного эксперта и&nbsp;возможности целого агентства благодаря нейросетям.</p>
    <div class="grid g3">
      <div class="card">
        <span class="kicker" style="color:#999">01 · Веб-студии</span>
        <h3>Студии и&nbsp;агентства</h3>
        <ul class="chk-list cross">
          <li>Дорого: ценник от&nbsp;150&nbsp;000&nbsp;₽</li>
          <li>Долго: 1-2 месяца согласований</li>
          <li>Оплата офиса, менеджеров и&nbsp;простоев</li>
          <li>Бесконечные созвоны вместо работы</li>
        </ul>
        <p class="muted" style="margin-top:auto">Вы оплачиваете штатную иерархию, а&nbsp;проект часто отдают джуниору.</p>
      </div>
      <div class="card">
        <span class="kicker" style="color:#999">02 · Фриланс</span>
        <h3>Случайный фриланс</h3>
        <ul class="chk-list cross">
          <li>Риск срыва сроков и&nbsp;тишина в&nbsp;чате</li>
          <li>«Пришлите текст и&nbsp;фото&nbsp;— я&nbsp;только верстаю»</li>
          <li>Шаблонный дизайн без маркетинга</li>
          <li>Правки и&nbsp;переделки за&nbsp;свой счёт</li>
        </ul>
        <p class="muted" style="margin-top:auto">От вас требуют быть копирайтером и&nbsp;арт-директором одновременно.</p>
      </div>
      <div class="card highlight">
        <span class="card-badge">Оптимально для бизнеса</span>
        <span class="kicker" style="color:#1d1d1d">03 · AntiDesigner</span>
        <h3>Компактная студия</h3>
        <ul class="chk-list">
          <li><strong>Готовый сайт за&nbsp;5-7 дней</strong></li>
          <li><strong>Чек от&nbsp;18&nbsp;000&nbsp;₽ без скрытых наценок</strong></li>
          <li><strong>Тексты, смыслы и&nbsp;AI-визуал включены</strong></li>
          <li><strong>Прямой диалог в&nbsp;Telegram и&nbsp;официальный договор</strong></li>
        </ul>
        <p class="muted" style="margin-top:auto">Работа в&nbsp;одном окне: один человек отвечает за&nbsp;весь результат от&nbsp;идеи до&nbsp;заявок.</p>
      </div>
    </div>
    <div style="margin-top:22px;text-align:center">
      <a class="btn" href="https://t.me/AntiDesigner01" target="_blank">Обсудить ваш проект →</a>
    </div>
    '''
    approach_note = "Цель: логическая связка с Hero. Снимаем страх «почему не пойти в студию или на биржу» и фиксируем ключевую выгоду: тексты и визуал включены."
    s2 = sec("02 · Разница подходов", approach_inner, approach_note, "approach")

    # ---------------- 3. Stack ----------------
    stack_inner = f'''
    <span class="kicker">Стек и&nbsp;автономия</span>
    <h2>Сайт под ваши задачи, а&nbsp;не&nbsp;под ограничения платформ</h2>
    <p class="lead">Без принудительных подписок и&nbsp;костылей. Подбираем технологическую основу под сценарий использования: чистый автономный код или Tilda.</p>
    <div class="grid g2">
      <div class="card highlight">
        <span class="card-badge">0 ₽ абонентской платы</span>
        <h3>Вариант 1: Автономный сайт (Чистый код)</h3>
        <p class="muted">Идеально для контекстной рекламы, трафика и&nbsp;бизнеса, который не&nbsp;хочет платить ренту конструкторам каждый месяц.</p>
        <ul class="chk-list">
          <li><strong>0&nbsp;₽ абонентской платы</strong> за&nbsp;конструкторы (навсегда)</li>
          <li><strong>Скорость загрузки 0.4-0.8&nbsp;с</strong> (100/100 PageSpeed Insights)</li>
          <li><strong>Готов к&nbsp;ИИ-поиску (GEO)</strong>: разметка для YandexGPT, Алисы и&nbsp;ChatGPT</li>
          <li><strong>Понятная админка</strong>: смена цен, текстов и&nbsp;акций без программиста</li>
          <li>Свой сервер: сайт защищён от&nbsp;блокировок и&nbsp;сбоев внешних платформ</li>
        </ul>
        {ph("Интерактив: Скриншот админки + виджет PageSpeed 100/100", "Демонстрация легковесного кода и моментального отклика в браузере", 120)}
      </div>
      <div class="card">
        <span class="kicker" style="color:#777">Привычная экосистема</span>
        <h3>Вариант 2: Сборка на&nbsp;Tilda</h3>
        <p class="muted">Отлично подходит, если в&nbsp;команде уже умеют работать с&nbsp;Tilda или требуется сложный каталог с&nbsp;корзиной на&nbsp;этой платформе.</p>
        <ul class="chk-list">
          <li>Индивидуальный дизайн на&nbsp;Zero-блоках без шаблонных рамок</li>
          <li>Привычное управление и&nbsp;настройка для вашей команды</li>
          <li>Быстрое подключение CRM, эквайринга и&nbsp;сервисов доставки</li>
          <li>Стандартный тариф Tilda оплачивается отдельно клиентом</li>
        </ul>
        {ph("Интерактив: Zero-блок сетка + адаптив под смартфоны", "Показ структуры блоков с идеальным адаптивом под все разрешения", 120)}
      </div>
    </div>
    <div style="margin-top:22px;text-align:center">
      <a class="btn ghost" href="https://t.me/AntiDesigner01" target="_blank">Подобрать стек под проект →</a>
    </div>
    '''
    stack_note = "Цель: снять технический страх «на чем сделают сайт». Даем клиенту выбор и подчеркиваем суперсилу: чистый код без абонентки против Tilda."
    s3 = sec("03 · Техническая база: Архитектура без костылей", stack_inner, stack_note, "stack")

    # ---------------- 4. Formats & Pricing ----------------
    formats_inner = f'''
    <span class="kicker">Инвестиции и&nbsp;форматы</span>
    <h2>Фиксированные форматы под вашу задачу</h2>
    <p class="lead">Без почасовой оплаты и&nbsp;раздутых смет. Вы&nbsp;заранее знаете точную цену и&nbsp;день запуска.</p>
    <div class="grid g3">
      <!-- Тариф 1 -->
      <div class="card hot">
        <span class="badge">Хит для запуска</span>
        <span class="kicker" style="color:#777">Формат 01</span>
        <h3>«Быстрый старт / Запуск направления»</h3>
        <div style="font-size:24px;font-weight:700;margin:6px 0 2px">18&nbsp;000&nbsp;– 25&nbsp;000&nbsp;₽</div>
        <div class="step-time">Срок: 5-7 рабочих дней</div>
        <p class="muted" style="font-size:13px">Для проверки гипотезы, запуска новой услуги или быстрого теста продукта.</p>
        <ul class="chk-list" style="margin-top:8px">
          <li>Продающая структура до&nbsp;5-6 экранов</li>
          <li>Смыслы, копирайтинг и&nbsp;графика включены</li>
          <li>Выбор стека: Tilda или чистый код</li>
          <li>Интеграция заявок в&nbsp;Telegram / CRM</li>
          <li>Адаптив под все смартфоны и&nbsp;планшеты</li>
        </ul>
        <span class="go">Выбрать тариф в&nbsp;Telegram →</span>
      </div>

      <!-- Тариф 2 -->
      <div class="card hot">
        <span class="badge">Для маркетологов</span>
        <span class="kicker" style="color:#777">Формат 02</span>
        <h3>«Партнёрский продакшен (White Label)»</h3>
        <div style="font-size:24px;font-weight:700;margin:6px 0 2px">18&nbsp;000&nbsp;– 25&nbsp;000&nbsp;₽ <small style="font-size:13px;font-weight:400;color:#777">/ проект</small></div>
        <div class="step-time">Жесткий SLA: до&nbsp;5 рабочих дней</div>
        <p class="muted" style="font-size:13px">Для директологов, маркетологов и&nbsp;агентств под рекламный трафик.</p>
        <ul class="chk-list" style="margin-top:8px">
          <li>Экспресс-сборка лендингов под трафик (Директ / Telegram Ads)</li>
          <li>Скорость загрузки 100/100 PageSpeed (без потери кликов)</li>
          <li>Работа от&nbsp;лица вашего агентства без переманивания клиентов</li>
          <li>Быстрое клонирование под A/B сплит-тесты</li>
          <li>Связь в&nbsp;мессенджере с&nbsp;реакцией до&nbsp;15 минут</li>
        </ul>
        <span class="go">Обсудить партнёрство →</span>
      </div>

      <!-- Тариф 3 -->
      <div class="card hot">
        <span class="badge">Под ключ</span>
        <span class="kicker" style="color:#777">Формат 03</span>
        <h3>«Полный цикл: Айдентика + Веб-система»</h3>
        <div style="font-size:24px;font-weight:700;margin:6px 0 2px">35&nbsp;000&nbsp;– 40&nbsp;000&nbsp;₽</div>
        <div class="step-time">Срок: 14 рабочих дней</div>
        <p class="muted" style="font-size:13px">Комплексная упаковка нового бренда, школы или D2C-производства.</p>
        <ul class="chk-list" style="margin-top:8px">
          <li>Логотип, шрифтовая пара и&nbsp;базовый гайдлайн бренда</li>
          <li>AI-предметная съёмка и&nbsp;лукбук без аренды фотостудий</li>
          <li>Полноценный многостраничный сайт или каталог</li>
          <li>Передача автономной системы со&nbsp;своей админкой</li>
          <li>Видеоинструкция по&nbsp;управлению контентом</li>
        </ul>
        <span class="go">Заказать полный цикл →</span>
      </div>
    </div>
    <div style="margin-top:20px;text-align:center">
      <p class="muted">Все карточки кликабельны и&nbsp;ведут в&nbsp;Telegram для мгновенного обсуждения выбранного формата.</p>
    </div>
    '''
    formats_note = "Цель: прозрачные цены и вилки без скрытых доплат. Четкое разделение на роли: соло-предприниматель, маркетолог (White Label) и комплексный бренд."
    s4 = sec("04 · Форматы работы и инвестиции", formats_inner, formats_note, "formats")

    # ---------------- 5. Portfolio / Cases ----------------
    cases_inner = f'''
    <span class="kicker">Реальные проекты</span>
    <h2>Кейсы: от&nbsp;концепта до&nbsp;релиза за&nbsp;дни</h2>
    <p class="lead">Примеры реализованных проектов. За&nbsp;счёт ИИ-пайплайна и&nbsp;современных платформ удаётся сократить сроки запуска и&nbsp;бюджеты в&nbsp;раза без потери качества.</p>
    <div class="grid g3">
      <!-- Кейс 1 -->
      <a class="card hot" href="http://79.174.90.149/baikal/" target="_blank">
        <span class="badge">Чистый код · 5 дней</span>
        {ph("Превью: Поездка на Байкал", "Скриншот первого экрана: авторский тур, чистота, горы", 140)}
        <div class="case-body">
          <h3>01 · Поездка на&nbsp;Байкал</h3>
          <p class="muted" style="font-size:13px"><strong>Задача:</strong> упаковать авторский зимний тур и&nbsp;запустить приём бронирований за&nbsp;5 дней.</p>
          <p class="muted" style="font-size:13px"><strong>Решение:</strong> автономный сайт на&nbsp;чистом коде, молниеносная загрузка на&nbsp;слабом мобильном интернете.</p>
          <div style="font-size:13.5px;font-weight:700;margin-top:auto">Результат: конверсия в&nbsp;заявку 21%</div>
        </div>
        <span class="go">Открыть живой сайт (79.174.90.149/baikal/) →</span>
      </a>

      <!-- Кейс 2 -->
      <a class="card hot" href="https://astroschool-kvasar.ru/" target="_blank">
        <span class="badge">EdTech / Tilda</span>
        {ph("Превью: Онлайн-школа «Квазар»", "Скриншот платформы: астрологическая школа со сложной структурой", 140)}
        <div class="case-body">
          <h3>02 · Онлайн-школа «Квазар»</h3>
          <p class="muted" style="font-size:13px"><strong>Задача:</strong> создать современный образовательный сайт для школы со&nbsp;сложной структурой курсов.</p>
          <p class="muted" style="font-size:13px"><strong>Решение:</strong> глубокая смысловая проработка модулей, удобная навигация и&nbsp;бесшовный мобильный адаптив.</p>
          <div style="font-size:13.5px;font-weight:700;margin-top:auto">Результат: рост заявок на&nbsp;курсы +45%</div>
        </div>
        <span class="go">Открыть живой сайт (astroschool-kvasar.ru) →</span>
      </a>

      <!-- Кейс 3 -->
      <a class="card hot" href="https://devdeev.ru/" target="_blank">
        <span class="badge">Tilda Zero-блоки</span>
        {ph("Превью: devdeev.ru", "Скриншот портфолио: тёмная тема, интерактивные Zero-блоки", 140)}
        <div class="case-body">
          <h3>03 · Портфолио devdeev.ru</h3>
          <p class="muted" style="font-size:13px"><strong>Задача:</strong> разработать технологичный сайт-портфолио с&nbsp;простым самостоятельным управлением.</p>
          <p class="muted" style="font-size:13px"><strong>Решение:</strong> индивидуальная сборка на&nbsp;Zero-блоках с&nbsp;кастомной анимацией и&nbsp;интеграциями.</p>
          <div style="font-size:13.5px;font-weight:700;margin-top:auto">Результат: удобная админка, запуск за&nbsp;5 дней</div>
        </div>
        <span class="go">Открыть живой сайт (devdeev.ru) →</span>
      </a>
    </div>
    <div style="margin-top:22px;text-align:center">
      <a class="btn" href="https://t.me/AntiDesigner01" target="_blank">Посмотреть больше работ в&nbsp;Telegram →</a>
      <p class="muted" style="font-size:12px;margin-top:6px">Пришлю примеры под вашу нишу в&nbsp;течение 1&nbsp;минуты</p>
    </div>
    '''
    cases_note = "Цель: социальное доказательство через реальные ссылки. Показываем владение обоими стеками (чистый код на сервере и Tilda Zero-блоки) и конкретные бизнес-результаты."
    s5 = sec("05 · Портфолио: Реальные проекты", cases_inner, cases_note, "cases")

    # ---------------- 6. Process (4 steps) ----------------
    process_inner = f'''
    <span class="kicker">Как строится работа</span>
    <h2>4&nbsp;шага от&nbsp;первой идеи до&nbsp;готового сайта</h2>
    <p class="lead">Без созвонов на&nbsp;два часа и&nbsp;брифов на&nbsp;50 страниц. Всё общение&nbsp;— в&nbsp;мессенджере.</p>
    <div class="grid g2">
      <div class="step">
        <b class="num">1</b>
        <div>
          <span class="step-time">День 1 · Старт</span>
          <h3>Вводные в&nbsp;Telegram</h3>
          <p class="muted">Вы надиктовываете задачу голосовым сообщением или скидываете ссылки на&nbsp;конкурентов. Я&nbsp;сам изучаю нишу, конкурентов и&nbsp;собираю продающие смыслы.</p>
        </div>
      </div>
      <div class="step">
        <b class="num">2</b>
        <div>
          <span class="step-time">День 2-3 · Смыслы</span>
          <h3>Смысловой прототип</h3>
          <p class="muted">Показываю структуру экранов и&nbsp;готовые тексты в&nbsp;вайрфрейме. Согласовываем логику продаж и&nbsp;офферы до&nbsp;начала визуальной вёрстки.</p>
        </div>
      </div>
      <div class="step">
        <b class="num">3</b>
        <div>
          <span class="step-time">День 4-6 · Продакшен</span>
          <h3>Сборка, графика и&nbsp;код</h3>
          <p class="muted">Генерирую и&nbsp;ретуширую AI-визуал, верстаю сайт на&nbsp;чистом коде или Tilda, настраиваю интерактив, подключаю формы и&nbsp;уведомления заявок в&nbsp;Telegram.</p>
        </div>
      </div>
      <div class="step">
        <b class="num">4</b>
        <div>
          <span class="step-time">День 7 · Релиз</span>
          <h3>Релиз и&nbsp;передача доступов</h3>
          <p class="muted">Тестируем отправку заявок, привязываем ваш домен, записываю короткую видеоинструкцию по&nbsp;управлению. Сайт готов принимать реальный трафик.</p>
        </div>
      </div>
    </div>
    <div style="margin-top:22px;text-align:center">
      <a class="btn" href="https://t.me/AntiDesigner01" target="_blank">Обсудить ваш проект в&nbsp;Telegram →</a>
    </div>
    '''
    process_note = "Цель: снять страх сложного процесса. Клиент видит прозрачный таймлайн по дням и понимает, что от него не потребуют сидеть над ТЗ."
    s6 = sec("06 · Процесс работы: 4 шага к релизу", process_inner, process_note, "process")

    # ---------------- 7. FAQ ----------------
    faq_inner = f'''
    <span class="kicker">Вопросы и&nbsp;ответы</span>
    <h2>Отвечаю на&nbsp;главные вопросы до&nbsp;старта</h2>
    <p class="lead">Коротко о&nbsp;договорах, оплате, правах на&nbsp;сайт и&nbsp;дальнейшей поддержке.</p>
    <div style="max-width:860px">
      <details>
        <summary>1. Как мы оформляем сотрудничество?</summary>
        <p>Работаю официально как самозанятый (НПД). Перед началом работы подписываем договор с&nbsp;зафиксированными сроками, этапами и&nbsp;стоимостью. Предоставляю официальные чеки из&nbsp;приложения «Мой налог» и&nbsp;закрывающие акты для вашей бухгалтерии.</p>
      </details>
      <details>
        <summary>2. Кому принадлежат домен и сайт?</summary>
        <p>Домен регистрируется строго на&nbsp;ваши паспортные данные&nbsp;— вы его единственный полноправный владелец. Для сайтов на&nbsp;чистом коде я размещаю проект на&nbsp;своем быстром сервере (VPS) с&nbsp;бесплатным администрированием: сайт летает, а&nbsp;вы не думаете о технической части. При желании проект легко переносится на любой ваш хостинг.</p>
      </details>
      <details>
        <summary>3. Что если мне не понравится прототип?</summary>
        <p>Мы разделяем работу на этапы: на 2-м шаге вы смотрите смысловой прототип с&nbsp;текстами и структурой. Все правки по смыслам вносятся бесплатно и без ограничений до тех пор, пока логика не станет идеальной. Только после этого мы переходим к визуалу и коду.</p>
      </details>
      <details>
        <summary>4. Нужно ли платить за конструктор каждый месяц?</summary>
        <p>Если мы выбираем автономный сайт на чистом коде, абонентская плата равна 0&nbsp;₽ навсегда. Вы платите только за домен раз в год (~200-300&nbsp;₽). Если мы собираем на Tilda&nbsp;— оплачивается стандартный ежемесячный тариф самой платформы Tilda.</p>
      </details>
      <details>
        <summary>5. А что если потом нужно будет поменять цены или добавить блок?</summary>
        <p>В автономных сайтах есть простая админка для самостоятельной правки цен и текстов. Кроме того, вы всегда можете подключить формат дизайн-подписки или разовую задачу: мелкие правки вносятся день в день без долгих согласований.</p>
      </details>
    </div>
    <div style="margin-top:22px">
      <a class="btn ghost" href="https://t.me/AntiDesigner01" target="_blank">Задать свой вопрос в&nbsp;Telegram →</a>
    </div>
    '''
    faq_note = "Цель: отработка последних возражений (бухгалтерия, безопасность, права на сайт, отсутствие скрытых платежей)."
    s7 = sec("07 · FAQ: Частые вопросы", faq_inner, faq_note, "faq")

    # ---------------- 8. Final Action ----------------
    action_inner = f'''
    <div class="split" style="align-items:center">
      <div>
        <span class="kicker">Финальный шаг</span>
        <h2>Давайте обсудим ваш сайт в&nbsp;Telegram</h2>
        <p class="lead">Не&nbsp;нужно заполнять длинные брифы. Просто расскажите о&nbsp;задаче своими словами или пришлите ссылку на&nbsp;действующий сайт.</p>
        <div class="card" style="margin-bottom:18px;background:#fff">
          <div style="font-weight:700;margin-bottom:6px">Как проходит первый контакт:</div>
          <ul class="chk-list">
            <li>Вы описываете задачу в&nbsp;свободной форме (можно голосовым)</li>
            <li>Я&nbsp;изучаю нишу, конкурентов и&nbsp;возвращаюсь с&nbsp;предложенным стеком и&nbsp;структурой</li>
            <li>Называю точные сроки и&nbsp;стоимость до&nbsp;старта работ</li>
          </ul>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <a class="btn" href="https://t.me/AntiDesigner01" target="_blank" style="padding:14px 26px;font-size:15px">
            <span>Написать в&nbsp;Telegram @AntiDesigner01</span>
          </a>
          <span class="muted" style="font-size:12.5px">● Отвечаю лично в&nbsp;течение 15&nbsp;минут</span>
        </div>
      </div>
      <div>
        {ph("Виджет быстрого старта: Карточка диалога", "Имитация мессенджера Telegram: статус «Онлайн», аватарка, пример короткого сообщения для лёгкого первого шага", 220)}
      </div>
    </div>
    '''
    action_note = "Цель: мягкое закрытие всей воронки в простое целевое действие без давления и бюрократии."
    s8 = sec("08 · Финальный экран (Action)", action_inner, action_note, "action")

    body_html = f"{s1}\n{s2}\n{s3}\n{s4}\n{s5}\n{s6}\n{s7}\n{s8}"

    full_html = f'''<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>AntiDesigner · Прототип сайта (вайрфрейм)</title>
<style>
{CSS}
</style>
<script src="comments.js" defer></script>
</head>
<body>
<header><div class="wrap hrow">
  <a class="logo" href="#hero">AntiDesigner<small>Евгений Антипин · Сайты и&nbsp;брендинг</small></a>
  <nav>{navhtml}</nav>
  <a class="btn sm hcta" href="https://t.me/AntiDesigner01" target="_blank">Обсудить проект</a>
  <input type="checkbox" id="burger">
  <label class="burg" for="burger">☰</label>
  <div class="drop">{drop_html}</div>
</div></header>

<main class="wrap">
{body_html}
</main>

<footer><div class="wrap frow">
  <div>
    <b>AntiDesigner</b> · Евгений Антипин<br>
    Запуск сайтов и&nbsp;бренд-систем под ключ<br>
    © 2026 Все права защищены
  </div>
  <div>
    <b>Контакты:</b><br>
    Telegram: <a href="https://t.me/AntiDesigner01" target="_blank">@AntiDesigner01</a><br>
    Email: <a href="mailto:Optimuspptimus@gmail.com">Optimuspptimus@gmail.com</a>
  </div>
  <div>
    <b>Правовая информация:</b><br>
    Работа по&nbsp;договору самозанятого (НПД)<br>
    Предоставление чеков и&nbsp;закрывающих актов<br>
    <a href="#">Публичная оферта</a> · <a href="#">Политика конфиденциальности</a>
  </div>
  <div>
    <a class="btn ghost sm" href="#hero">Наверх ↑</a>
  </div>
</div></footer>
</body>
</html>'''

    # Сохраняем в dist/index.html и в корень для локального просмотра
    with open(os.path.join(DIST_DIR, "index.html"), "w", encoding="utf-8") as f:
        f.write(full_html)
    
    with open(os.path.join(DIST_DIR, "wire.css"), "w", encoding="utf-8") as f:
        f.write(CSS)

    with open(os.path.join(BASE_DIR, "prototype.html"), "w", encoding="utf-8") as f:
        f.write(full_html)

    # Также копируем comments.js в dist
    src_comments = os.path.join(BASE_DIR, ".agents", "skills", "wireframe-prototype", "assets", "comments.js")
    if os.path.exists(src_comments):
        with open(src_comments, "r", encoding="utf-8") as src, open(os.path.join(DIST_DIR, "comments.js"), "w", encoding="utf-8") as dst:
            dst.write(src.read())
        with open(src_comments, "r", encoding="utf-8") as src, open(os.path.join(BASE_DIR, "comments.js"), "w", encoding="utf-8") as dst:
            dst.write(src.read())

    print("Prototype built successfully in dist/ and prototype.html")

if __name__ == "__main__":
    generate_index_html()
