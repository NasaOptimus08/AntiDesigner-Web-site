/* AntiDesigner prototype — комментарии-стикеры.
   Хранение: LocalStorage (сохраняются в браузере при перезагрузке) + поддержка серверного API если доступен.
   + Кнопка «📋 Скопировать все правки для агента».
*/
(function () {
  var PAGE = location.pathname.split('/').pop() || 'prototype.html';
  var STORAGE_KEY = 'antidesigner_comments_' + PAGE;
  var PAGE_KEY = '📄 Страница в целом';

  function el(t, c, h) {
    var e = document.createElement(t);
    if (c) e.className = c;
    if (h != null) e.innerHTML = h;
    return e;
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }
  function fmt(ts) {
    try {
      return new Date(ts).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return '';
    }
  }
  function secKey(s) {
    var l = s.querySelector('.sec-label');
    return l ? l.textContent.trim() : 'Блок';
  }

  // Загрузка комментариев из LocalStorage
  function getLocalComments() {
    try {
      var data = localStorage.getItem(STORAGE_KEY);
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  // Сохранение комментария в LocalStorage
  function saveLocalComment(item) {
    var list = getLocalComments();
    list.push(item);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {}
    updateCopyButtonState();
  }

  // Удаление комментария
  function removeLocalComment(id) {
    var list = getLocalComments().filter(function (c) { return c.id !== id; });
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {}
    updateCopyButtonState();
  }

  function listFor(container) {
    var l = container.querySelector(':scope > .cmt-list');
    if (!l) {
      l = el('div', 'cmt-list');
      container.appendChild(l);
    }
    return l;
  }

  function renderItem(container, c) {
    var itemEl = el('div', 'cmt-item');
    itemEl.setAttribute('data-id', c.id);
    itemEl.innerHTML =
      '<div style="display:flex;justify-content:space-between;align-items:center;">' +
        '<b>📌 Правка</b>' +
        '<div>' +
          '<span>' + fmt(c.ts) + '</span>' +
          '<button type="button" class="cmt-del" title="Удалить правку" style="background:none;border:none;color:#999;cursor:pointer;margin-left:8px;font-size:13px;">✕</button>' +
        '</div>' +
      '</div>' +
      '<p>' + esc(c.text) + '</p>';

    itemEl.querySelector('.cmt-del').onclick = function () {
      if (confirm('Удалить эту правку?')) {
        removeLocalComment(c.id);
        itemEl.remove();
      }
    };

    listFor(container).appendChild(itemEl);
  }

  function pagePanel() {
    var p = document.getElementById('cmt-page-panel');
    if (!p) {
      p = el('div', '', '');
      p.id = 'cmt-page-panel';
      p.appendChild(el('div', 'cmt-panel-title', '💬 Общие комментарии и правки к странице'));
      var main = document.querySelector('main');
      if (main) main.insertBefore(p, main.firstChild);
    }
    return p;
  }

  function openForm(container, key) {
    if (container.querySelector('.cmt-form')) return;
    var f = el('div', 'cmt-form',
      '<textarea rows="3" placeholder="Напишите замечание или правку к этому блоку..."></textarea>' +
      '<div class="cmt-row">' +
        '<button type="button">Оставить стикер-правку</button>' +
        '<span class="cmt-x">отмена</span>' +
      '</div>');
    container.appendChild(f);

    f.querySelector('.cmt-x').onclick = function () { f.remove(); };
    f.querySelector('button').onclick = function () {
      var textarea = f.querySelector('textarea');
      var text = textarea.value.trim();
      if (!text) { textarea.focus(); return; }

      var commentObj = {
        id: 'c_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
        page: PAGE,
        section: key,
        text: text,
        ts: Date.now()
      };

      // Сохраняем локально — стикер остаётся всегда!
      saveLocalComment(commentObj);
      renderItem(container, commentObj);
      f.remove();

      // Фоновая отправка на сервер (если доступен бэкенд)
      fetch('api/comment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(commentObj)
      }).catch(function () {});
    };
    f.querySelector('textarea').focus();
  }

  // Кнопка быстрого копирования всех правок
  function updateCopyButtonState() {
    var btn = document.getElementById('cmt-copy-all');
    var comments = getLocalComments();
    if (!btn) return;
    if (comments.length > 0) {
      btn.style.display = 'inline-flex';
      btn.innerHTML = '📋 Скопировать ' + comments.length + ' ' + (comments.length === 1 ? 'правку' : 'правок') + ' для агента';
    } else {
      btn.style.display = 'none';
    }
  }

  function copyAllComments() {
    var comments = getLocalComments();
    if (!comments.length) {
      alert('Пока нет сохранённых правок');
      return;
    }
    var text = 'Вот правки по сайту (' + PAGE + '):\n\n';
    comments.forEach(function (c, i) {
      text += (i + 1) + '. [' + c.section + ']:\n' + c.text + '\n\n';
    });
    navigator.clipboard.writeText(text).then(function () {
      alert('Все ' + comments.length + ' правок скопированы в буфер обмена!\nПросто вставьте их в чат агенту: «Вот правки. Все исправь как в правках».');
    }).catch(function () {
      prompt('Скопируйте текст правок:', text);
    });
  }

  function init() {
    // Кнопка «Добавить правку» на каждом блоке
    document.querySelectorAll('main section').forEach(function (s) {
      var key = secKey(s);
      var b = el('button', 'cmt-btn', '💬 Добавить правку');
      b.type = 'button';
      b.onclick = function () { openForm(s, key); };
      s.appendChild(b);
    });

    // Панель плавающих кнопок внизу слева
    var bar = el('div', '', '');
    bar.style.position = 'fixed';
    bar.style.left = '20px';
    bar.style.bottom = '20px';
    bar.style.display = 'flex';
    bar.style.flexDirection = 'column';
    bar.style.gap = '8px';
    bar.style.zIndex = '100';

    // Кнопка добавления общего комментария
    var fb = el('button', 'cmt-fab', '💬 Замечание к странице');
    fb.type = 'button';
    fb.onclick = function () {
      var p = pagePanel();
      openForm(p, PAGE_KEY);
      p.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    bar.appendChild(fb);

    // Кнопка «Скопировать все правки для агента»
    var copyBtn = el('button', 'cmt-fab', '📋 Скопировать правки для агента');
    copyBtn.id = 'cmt-copy-all';
    copyBtn.type = 'button';
    copyBtn.style.background = '#00E5B8';
    copyBtn.style.color = '#000';
    copyBtn.style.borderColor = '#00E5B8';
    copyBtn.style.display = 'none';
    copyBtn.onclick = copyAllComments;
    bar.appendChild(copyBtn);

    document.body.appendChild(bar);

    // Восстанавливаем все сохраненные стикеры из LocalStorage
    var saved = getLocalComments();
    if (saved && saved.length) {
      var map = {};
      document.querySelectorAll('main section').forEach(function (s) { map[secKey(s)] = s; });
      saved.forEach(function (c) {
        if (c.section === PAGE_KEY) renderItem(pagePanel(), c);
        else if (map[c.section]) renderItem(map[c.section], c);
        else renderItem(pagePanel(), c);
      });
    }

    updateCopyButtonState();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
