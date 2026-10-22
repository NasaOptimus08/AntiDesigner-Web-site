/* ÐÑÐ¾ÑÐ¾ÑÐ¸Ð¿ — комментарии клиента. Хранение: API на сервере. */
(function () {
  var PAGE = location.pathname.split('/').pop() || 'index.html';
  var API = 'api/';

  function el(t, c, h) { var e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); }
  function fmt(ts) { try { return new Date(ts).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; } }
  function secKey(s) { var l = s.querySelector('.sec-label'); return l ? l.textContent.trim() : 'Блок'; }

  var PAGE_KEY = '📄 Страница в целом';

  function listFor(container) {
    var l = container.querySelector(':scope > .cmt-list');
    if (!l) { l = el('div', 'cmt-list'); container.appendChild(l); }
    return l;
  }

  function renderItem(container, c) {
    listFor(container).appendChild(el('div', 'cmt-item',
      '<span>' + fmt(c.ts) + '</span><p>' + esc(c.text) + '</p>'));
  }

  function pagePanel() {
    var p = document.getElementById('cmt-page-panel');
    if (!p) {
      p = el('div', '', '');
      p.id = 'cmt-page-panel';
      p.appendChild(el('div', 'cmt-panel-title', '💬 Комментарии к странице в целом'));
      var main = document.querySelector('main');
      main.insertBefore(p, main.firstChild);
    }
    return p;
  }

  function openForm(container, key, placeAfterBtn) {
    if (container.querySelector('.cmt-form')) return;
    var f = el('div', 'cmt-form',
      '<textarea rows="3" placeholder="Что поправить или добавить в этом блоке? Пишите как удобно…"></textarea>' +
      '<div class="cmt-row"><button type="button">Отправить</button><span class="cmt-x">отмена</span></div>');
    container.appendChild(f);
    f.querySelector('.cmt-x').onclick = function () { f.remove(); };
    f.querySelector('button').onclick = function () {
      var text = f.querySelector('textarea').value.trim();
      if (!text) { f.querySelector('textarea').focus(); return; }
      var b = f.querySelector('button'); b.disabled = true; b.textContent = 'Отправляю…';
      fetch(API + 'comment', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page: PAGE, section: key, text: text, name: 'Клиент' })
      }).then(function (r) {
        if (!r.ok) throw new Error('bad status');
        f.remove();
        renderItem(container, { text: text, ts: Date.now() });
      }).catch(function () {
        b.disabled = false; b.textContent = 'Отправить';
        alert('Не получилось отправить. Проверьте интернет и попробуйте ещё раз.');
      });
    };
    f.querySelector('textarea').focus();
  }

  function init() {
    // кнопка на каждом блоке
    document.querySelectorAll('main section').forEach(function (s) {
      var key = secKey(s);
      var b = el('button', 'cmt-btn', '💬 Комментировать');
      b.type = 'button';
      b.onclick = function () { openForm(s, key); };
      s.appendChild(b);
    });
    // плавающая кнопка «к странице в целом»
    var fb = el('button', 'cmt-fab', '💬 Комментарий к странице');
    fb.type = 'button';
    fb.onclick = function () {
      var p = pagePanel();
      openForm(p, PAGE_KEY);
      p.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    document.body.appendChild(fb);
    // подгрузка существующих
    fetch(API + 'comments?page=' + encodeURIComponent(PAGE))
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (items) {
        if (!items || !items.length) return;
        var map = {};
        document.querySelectorAll('main section').forEach(function (s) { map[secKey(s)] = s; });
        items.forEach(function (c) {
          if (c.section === PAGE_KEY) renderItem(pagePanel(), c);
          else if (map[c.section]) renderItem(map[c.section], c);
          else renderItem(pagePanel(), c);
        });
      }).catch(function () { /* прототип: молча */ });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
