/* Дизайн-комментарии: виджет-пины для ревью дизайна сайта с клиентом.
   Кнопка слева снизу → клик по месту страницы ставит пин с комментарием →
   все комментарии собираются в одной панели + сводка по страницам.
   Клик мимо → всё скрыто, остаётся кнопка. Хранение — на сервере (см. comments-server.js). Скилл design-comments.

   ПОДКЛЮЧЕНИЕ (в каждый .html перед </body>):
     <script src="/ПУТЬ/comments-widget.js?v=1" data-api="/ПУТЬ/api" defer></script>
   data-api — адрес API этого прототипа (по умолчанию = папка виджета + "api").
   Необязательно: window.DC_PAGE_LABELS = { 'index.html':'Главная', ... } — красивые имена страниц. */
(function () {
  'use strict';
  if (window.__dcLoaded) return; window.__dcLoaded = true;

  // ---------- config ----------
  var S = document.querySelector('script[data-dc]') || document.querySelector('script[src*="comments-widget"]');
  var API = (S && S.getAttribute('data-api')) || window.DC_COMMENTS_API || (function () {
    try { var u = new URL(S.src, location.href); return u.pathname.replace(/[^/]+$/, '') + 'api'; }
    catch (e) { return '/api'; }
  })();
  var PAGE_LABELS = window.DC_PAGE_LABELS || {};
  var ACCENT = (S && S.getAttribute('data-accent')) || '#2E6BFF';
  var ACCENT_DARK = (S && S.getAttribute('data-accent-dark')) || '#1B4BC4';

  var PAGE = (function () {
    var p = location.pathname.split('/').filter(Boolean).pop() || 'index.html';
    if (!/\.html?$/i.test(p)) p = 'index.html';
    return p;
  })();
  function label(p) { return PAGE_LABELS[p] || p; }

  var state = { open: false, placing: false, items: [], all: [], name: localStorage.getItem('dc_name') || '' };

  // ---------- styles ----------
  var css = document.createElement('style');
  css.textContent = [
    '#dc-fab{position:fixed;left:20px;bottom:20px;z-index:2147483000;width:56px;height:56px;border-radius:50%;',
    'background:' + ACCENT + ';border:none;cursor:pointer;box-shadow:0 10px 28px -8px rgba(20,40,90,.5),0 2px 8px rgba(0,0,0,.15);',
    'display:flex;align-items:center;justify-content:center;transition:transform .18s;font-family:-apple-system,Segoe UI,Roboto,sans-serif}',
    '#dc-fab:hover{transform:translateY(-2px)}',
    '#dc-fab svg{width:26px;height:26px;fill:#fff}',
    '#dc-fab.on{background:' + ACCENT_DARK + '}',
    '#dc-badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;',
    'background:#ff4d4f;color:#fff;font-size:11px;font-weight:700;display:none;align-items:center;justify-content:center;line-height:20px;',
    'box-shadow:0 0 0 2px #fff;font-family:inherit}',
    '#dc-layer{position:absolute;top:0;left:0;width:100%;height:0;z-index:2147482000;pointer-events:none}',
    '.dc-pin{position:absolute;transform:translate(-50%,-100%);pointer-events:auto;cursor:pointer;z-index:2}',
    '.dc-pin i{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50% 50% 50% 2px;',
    'background:' + ACCENT + ';color:#fff;font-size:13px;font-weight:800;box-shadow:0 4px 12px -2px rgba(20,40,90,.5),0 0 0 2px #fff;',
    'font-family:-apple-system,Segoe UI,Roboto,sans-serif;font-style:normal;transition:transform .15s}',
    '.dc-pin:hover i{transform:scale(1.12)}',
    '.dc-pin.done i{background:#8A93A6;box-shadow:0 3px 8px -2px rgba(0,0,0,.3),0 0 0 2px #fff}',
    '#dc-body.placing,#dc-body.placing *{cursor:crosshair!important}',
    '#dc-panel{position:fixed;left:20px;bottom:88px;z-index:2147483000;width:340px;max-width:calc(100vw - 40px);',
    'max-height:72vh;display:none;flex-direction:column;background:#fff;border-radius:18px;overflow:hidden;',
    'box-shadow:0 24px 60px -20px rgba(20,30,60,.4),0 4px 16px rgba(0,0,0,.1);border:1px solid rgba(12,13,17,.08);',
    'font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#0C0D11}',
    '#dc-panel.show{display:flex}',
    '.dc-ph{padding:15px 16px 12px;border-bottom:1px solid rgba(12,13,17,.07)}',
    '.dc-ph h4{margin:0;font-size:15px;font-weight:800;letter-spacing:-.01em}',
    '.dc-ph .sub{font-size:12px;color:#7A8194;margin-top:2px}',
    '.dc-add{margin:12px 16px 4px;padding:10px;border:1.5px dashed #B9C6E6;background:#F4F7FF;color:' + ACCENT + ';',
    'border-radius:12px;font-size:13.5px;font-weight:700;cursor:pointer;text-align:center;transition:.15s}',
    '.dc-add:hover{background:#E9F0FF;border-color:' + ACCENT + '}',
    '.dc-add.active{background:' + ACCENT + ';color:#fff;border-style:solid;border-color:' + ACCENT + '}',
    '.dc-list{overflow-y:auto;padding:6px 12px 4px;flex:1}',
    '.dc-empty{padding:18px 6px 22px;text-align:center;color:#9AA1B2;font-size:13px;line-height:1.5}',
    '.dc-item{display:flex;gap:10px;padding:10px 6px;border-bottom:1px solid rgba(12,13,17,.05)}',
    '.dc-item:last-child{border-bottom:none}',
    '.dc-num{flex:none;width:22px;height:22px;border-radius:50%;background:' + ACCENT + ';color:#fff;font-size:11px;font-weight:800;',
    'display:flex;align-items:center;justify-content:center;margin-top:1px}',
    '.dc-item.done .dc-num{background:#8A93A6}',
    '.dc-item.done .dc-txt{text-decoration:line-through;color:#9AA1B2}',
    '.dc-ic{flex:1;min-width:0}',
    '.dc-txt{font-size:13px;line-height:1.45;color:#1B2130;word-wrap:break-word;white-space:pre-wrap}',
    '.dc-meta{font-size:11px;color:#9AA1B2;margin-top:3px}',
    '.dc-acts{display:flex;gap:8px;margin-top:6px}',
    '.dc-acts button{background:none;border:none;padding:0;font-size:11.5px;font-weight:700;cursor:pointer;color:#7A8194;font-family:inherit}',
    '.dc-acts button:hover{color:' + ACCENT + '}',
    '.dc-acts button.del:hover{color:#ff4d4f}',
    '.dc-sum{border-top:1px solid rgba(12,13,17,.07);padding:10px 16px 14px;background:#FAFBFD}',
    '.dc-sum h5{margin:0 0 7px;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#9AA1B2}',
    '.dc-srow{display:flex;align-items:center;justify-content:space-between;padding:4px 0;font-size:12.5px;cursor:pointer;color:#3A4150}',
    '.dc-srow:hover{color:' + ACCENT + '}',
    '.dc-srow.cur{font-weight:800;color:' + ACCENT + '}',
    '.dc-scount{flex:none;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#E4E9F5;color:#5A6478;',
    'font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center}',
    '.dc-srow.has .dc-scount{background:#ff4d4f;color:#fff}',
    '#dc-pop{position:absolute;z-index:2147483600;width:270px;background:#fff;border-radius:14px;',
    'box-shadow:0 20px 50px -14px rgba(20,30,60,.45),0 3px 12px rgba(0,0,0,.12);border:1px solid rgba(12,13,17,.08);',
    'padding:12px;display:none;font-family:-apple-system,Segoe UI,Roboto,sans-serif;transform:translate(-50%,10px)}',
    '#dc-pop.show{display:block}',
    '#dc-pop textarea{width:100%;box-sizing:border-box;min-height:66px;resize:vertical;border:1px solid #D5DBEA;',
    'border-radius:9px;padding:8px 10px;font-size:13px;font-family:inherit;color:#1B2130;outline:none}',
    '#dc-pop textarea:focus{border-color:' + ACCENT + '}',
    '#dc-pop .row{display:flex;gap:8px;margin-top:8px}',
    '#dc-pop input{flex:1;min-width:0;border:1px solid #D5DBEA;border-radius:9px;padding:7px 9px;font-size:12.5px;font-family:inherit;outline:none}',
    '#dc-pop input:focus{border-color:' + ACCENT + '}',
    '#dc-pop .btns{display:flex;gap:8px;margin-top:9px}',
    '#dc-pop .btns button{flex:1;border:none;border-radius:9px;padding:8px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit}',
    '#dc-pop .save{background:' + ACCENT + ';color:#fff}',
    '#dc-pop .cancel{background:#EEF0F5;color:#5A6478}',
    '#dc-pop .view-txt{font-size:13.5px;line-height:1.5;color:#1B2130;white-space:pre-wrap;word-wrap:break-word}',
    '#dc-pop .view-meta{font-size:11.5px;color:#9AA1B2;margin-top:7px}',
    '@media(max-width:560px){#dc-panel{width:calc(100vw - 32px);left:16px}#dc-fab{left:16px;bottom:16px}}'
  ].join('');
  document.head.appendChild(css);

  // ---------- dom ----------
  var fab = document.createElement('button');
  fab.id = 'dc-fab'; fab.title = 'Комментарии к прототипу';
  fab.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4V6a2 2 0 0 1 2-2z"/></svg><span id="dc-badge">0</span>';
  var layer = document.createElement('div'); layer.id = 'dc-layer';
  var panel = document.createElement('div'); panel.id = 'dc-panel';
  var pop = document.createElement('div'); pop.id = 'dc-pop';
  document.body.appendChild(fab); document.body.appendChild(layer);
  document.body.appendChild(panel); document.body.appendChild(pop);
  if (!document.body.id) document.body.id = 'dc-body'; var BODY = document.body;

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function ago(ts) {
    var d = Math.floor((Date.now() - ts) / 1000);
    if (d < 60) return 'только что';
    if (d < 3600) return Math.floor(d / 60) + ' мин назад';
    if (d < 86400) return Math.floor(d / 3600) + ' ч назад';
    return Math.floor(d / 86400) + ' дн назад';
  }
  function api(path, opts) { return fetch(API + path, opts).then(function (r) { return r.json().catch(function () { return {}; }); }); }

  // ---------- data ----------
  function load() {
    return api('/comments', {}).then(function (all) {
      state.all = Array.isArray(all) ? all : [];
      state.items = state.all.filter(function (c) { return c.page === PAGE; }).sort(function (a, b) { return a.ts - b.ts; });
      renderBadge(); renderPins(); if (state.open) renderPanel();
    }).catch(function () {});
  }
  function renderBadge() {
    var n = state.items.filter(function (c) { return !c.resolved; }).length;
    var b = document.getElementById('dc-badge'); b.textContent = n; b.style.display = n > 0 ? 'flex' : 'none';
  }
  function renderPins() {
    layer.innerHTML = ''; layer.style.height = document.documentElement.scrollHeight + 'px';
    if (!state.open) return;
    var docW = document.documentElement.scrollWidth;
    state.items.forEach(function (c, i) {
      var pin = document.createElement('div');
      pin.className = 'dc-pin' + (c.resolved ? ' done' : '');
      pin.style.left = (c.x / 100 * docW) + 'px'; pin.style.top = c.y + 'px';
      pin.innerHTML = '<i>' + (i + 1) + '</i>';
      pin.addEventListener('click', function (e) { e.stopPropagation(); openView(c, i + 1, pin); });
      layer.appendChild(pin);
    });
  }
  function renderPanel() {
    var itemsHtml = state.items.length ? state.items.map(function (c, i) {
      return '<div class="dc-item' + (c.resolved ? ' done' : '') + '" data-id="' + c.id + '">' +
        '<div class="dc-num">' + (i + 1) + '</div>' +
        '<div class="dc-ic"><div class="dc-txt">' + esc(c.text) + '</div>' +
        '<div class="dc-meta">' + esc(c.name) + ' · ' + ago(c.ts) + '</div>' +
        '<div class="dc-acts"><button class="show">Показать</button>' +
        '<button class="res">' + (c.resolved ? 'Вернуть' : 'Решено') + '</button>' +
        '<button class="del">Удалить</button></div></div></div>';
    }).join('') : '<div class="dc-empty">Пока комментариев нет.<br>Нажмите «Оставить комментарий» и кликните по нужному месту на странице.</div>';

    var byPage = {}; state.all.forEach(function (c) { if (!c.resolved) byPage[c.page] = (byPage[c.page] || 0) + 1; });
    var order = []; state.all.forEach(function (c) { if (order.indexOf(c.page) < 0) order.push(c.page); });
    order.sort(); if (order.indexOf(PAGE) < 0) order.unshift(PAGE);
    var sumHtml = order.map(function (p) {
      var n = byPage[p] || 0;
      return '<div class="dc-srow' + (p === PAGE ? ' cur' : '') + (n ? ' has' : '') + '" data-page="' + esc(p) + '">' +
        '<span>' + esc(label(p)) + (p === PAGE ? ' · тут' : '') + '</span><span class="dc-scount">' + n + '</span></div>';
    }).join('');

    panel.innerHTML =
      '<div class="dc-ph"><h4>Комментарии</h4><div class="sub">' + esc(label(PAGE)) + ' · ' + state.items.length + ' всего</div></div>' +
      '<div class="dc-add' + (state.placing ? ' active' : '') + '" id="dc-add">' + (state.placing ? 'Кликните по месту на странице…' : '＋ Оставить комментарий') + '</div>' +
      '<div class="dc-list">' + itemsHtml + '</div>' +
      '<div class="dc-sum"><h5>Все страницы</h5>' + sumHtml + '</div>';

    document.getElementById('dc-add').addEventListener('click', function (e) { e.stopPropagation(); togglePlacing(); });
    panel.querySelectorAll('.dc-srow').forEach(function (r) {
      r.addEventListener('click', function () { var p = r.getAttribute('data-page'); if (p && p !== PAGE) location.href = p; });
    });
    panel.querySelectorAll('.dc-item').forEach(function (el) {
      var id = el.getAttribute('data-id');
      var c = state.items.filter(function (x) { return x.id === id; })[0]; if (!c) return;
      el.querySelector('.show').addEventListener('click', function (e) { e.stopPropagation(); scrollToPin(c); });
      el.querySelector('.res').addEventListener('click', function (e) { e.stopPropagation(); api('/resolve', post({ id: id })).then(load); });
      el.querySelector('.del').addEventListener('click', function (e) { e.stopPropagation(); if (confirm('Удалить комментарий?')) api('/delete', post({ id: id })).then(load); });
    });
  }
  function post(obj) { return { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(obj) }; }
  function scrollToPin(c) { window.scrollTo({ top: Math.max(0, c.y - window.innerHeight / 2), behavior: 'smooth' }); }

  // ---------- open / close ----------
  function open() { state.open = true; fab.classList.add('on'); panel.classList.add('show'); renderPins(); renderPanel(); }
  function close() { state.open = false; state.placing = false; fab.classList.remove('on'); panel.classList.remove('show'); pop.classList.remove('show'); BODY.classList.remove('placing'); renderPins(); }
  function togglePlacing() { state.placing = !state.placing; BODY.classList.toggle('placing', state.placing); pop.classList.remove('show'); renderPanel(); }

  // ---------- new pin form ----------
  function openForm(x, y, clientX, clientY) {
    pop.style.left = clientX + 'px'; pop.style.top = (clientY + window.scrollY) + 'px';
    pop.innerHTML =
      '<textarea placeholder="Что поправить в этом месте?" id="dc-ta"></textarea>' +
      '<div class="row"><input id="dc-nm" placeholder="Ваше имя" value="' + esc(state.name) + '"></div>' +
      '<div class="btns"><button class="cancel" id="dc-cx">Отмена</button><button class="save" id="dc-sv">Сохранить</button></div>';
    pop.classList.add('show');
    var ta = document.getElementById('dc-ta'); ta.focus();
    document.getElementById('dc-cx').addEventListener('click', function (e) { e.stopPropagation(); pop.classList.remove('show'); });
    document.getElementById('dc-sv').addEventListener('click', function (e) {
      e.stopPropagation(); var text = ta.value.trim(); if (!text) { ta.focus(); return; }
      var nm = document.getElementById('dc-nm').value.trim() || 'Клиент'; state.name = nm; localStorage.setItem('dc_name', nm);
      api('/comment', post({ page: PAGE, x: x, y: y, text: text, name: nm })).then(function () {
        pop.classList.remove('show'); state.placing = false; BODY.classList.remove('placing'); return load();
      });
    });
    ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) document.getElementById('dc-sv').click(); });
  }

  // ---------- view pin ----------
  function openView(c, num, pinEl) {
    var r = pinEl.getBoundingClientRect();
    pop.style.left = (r.left + r.width / 2) + 'px'; pop.style.top = (r.bottom + window.scrollY + 6) + 'px';
    pop.innerHTML =
      '<div class="view-txt"><b>#' + num + '.</b> ' + esc(c.text) + '</div>' +
      '<div class="view-meta">' + esc(c.name) + ' · ' + ago(c.ts) + (c.resolved ? ' · решено' : '') + '</div>' +
      '<div class="btns"><button class="cancel" id="dc-vres">' + (c.resolved ? 'Вернуть' : 'Решено') + '</button>' +
      '<button class="save" id="dc-vdel" style="background:#ff4d4f">Удалить</button></div>';
    pop.classList.add('show');
    document.getElementById('dc-vres').addEventListener('click', function (e) { e.stopPropagation(); pop.classList.remove('show'); api('/resolve', post({ id: c.id })).then(load); });
    document.getElementById('dc-vdel').addEventListener('click', function (e) { e.stopPropagation(); if (confirm('Удалить комментарий?')) { pop.classList.remove('show'); api('/delete', post({ id: c.id })).then(load); } });
  }

  // ---------- events ----------
  fab.addEventListener('click', function (e) { e.stopPropagation(); state.open ? close() : open(); });
  document.addEventListener('click', function (e) {
    if (insideUI(e.target)) return;
    if (state.placing) {
      var docW = document.documentElement.scrollWidth;
      openForm((e.pageX / docW) * 100, e.pageY, e.clientX, e.clientY); return;
    }
    if (state.open) { if (pop.classList.contains('show')) { pop.classList.remove('show'); return; } close(); }
  }, true);
  function insideUI(t) { return !!(t.closest && (t.closest('#dc-panel') || t.closest('#dc-fab') || t.closest('#dc-pop') || t.closest('.dc-pin'))); }
  window.addEventListener('resize', function () { if (state.open) renderPins(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if (state.placing) togglePlacing(); else if (state.open) close(); } });

  load();
})();
