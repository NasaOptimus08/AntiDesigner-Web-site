/* Дизайн-комментарии — сервер хранения (скилл design-comments).
   Чистый Node без зависимостей. Хранит комментарии-пины в comments.json рядом с собой.
   Запуск:  PORT=3900 pm2 start comments-server.js --name <проект>-comments
   Наружу — через nginx:  location /<путь>/api/ { proxy_pass http://127.0.0.1:3900/; }

   API:
     GET  /comments            -> все (для сводки по страницам)
     GET  /comments?page=X     -> комментарии одной страницы
     POST /comment  {page,x,y,text,name}   -> создать пин
     POST /resolve  {id}                    -> переключить «решено»
     POST /delete   {id}                    -> удалить
   Формат записи: {id, ts, page, x(%ширины), y(px от верха), text, name, resolved} */
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.env.PORT) || 3900;
const FILE = path.join(__dirname, 'comments.json');

function readAll() { try { return JSON.parse(fs.readFileSync(FILE, 'utf8')); } catch (e) { return []; } }
function writeAll(items) { fs.writeFileSync(FILE, JSON.stringify(items, null, 1)); }
function json(res, code, obj) { res.statusCode = code; res.setHeader('Content-Type', 'application/json; charset=utf-8'); res.end(JSON.stringify(obj)); }
function readBody(req) {
  return new Promise((resolve, reject) => {
    let b = ''; req.on('data', ch => { b += ch; if (b.length > 40000) req.destroy(); });
    req.on('end', () => { try { resolve(JSON.parse(b || '{}')); } catch (e) { reject(e); } });
  });
}

const server = http.createServer(async (req, res) => {
  const u = new URL(req.url, 'http://localhost');
  res.setHeader('Cache-Control', 'no-store');

  if (req.method === 'GET' && u.pathname === '/comments') {
    const page = u.searchParams.get('page');
    let items = readAll();
    if (page) items = items.filter(c => c.page === page);
    return json(res, 200, items);
  }

  if (req.method === 'POST' && u.pathname === '/comment') {
    try {
      const d = await readBody(req);
      const text = String(d.text || '').trim().slice(0, 3000);
      if (!text) return json(res, 400, { ok: false, err: 'empty' });
      const item = {
        id: Date.now().toString(36) + Math.random().toString(36).slice(2, 7),
        ts: Date.now(),
        page: String(d.page || 'unknown').slice(0, 120),
        x: Math.max(0, Math.min(100, Number(d.x) || 0)),
        y: Math.max(0, Number(d.y) || 0),
        name: (String(d.name || '').trim().slice(0, 60)) || 'Клиент',
        text, resolved: false
      };
      const items = readAll(); items.push(item); writeAll(items);
      return json(res, 201, { ok: true, item });
    } catch (e) { return json(res, 400, { ok: false }); }
  }

  if (req.method === 'POST' && u.pathname === '/resolve') {
    try {
      const d = await readBody(req);
      const items = readAll(); const it = items.find(c => c.id === d.id);
      if (!it) return json(res, 404, { ok: false });
      it.resolved = !it.resolved; writeAll(items);
      return json(res, 200, { ok: true, resolved: it.resolved });
    } catch (e) { return json(res, 400, { ok: false }); }
  }

  if (req.method === 'POST' && u.pathname === '/delete') {
    try {
      const d = await readBody(req);
      let items = readAll(); const n = items.length;
      items = items.filter(c => c.id !== d.id); writeAll(items);
      return json(res, 200, { ok: true, removed: n - items.length });
    } catch (e) { return json(res, 400, { ok: false }); }
  }

  res.statusCode = 404; res.end('not found');
});

server.listen(PORT, '127.0.0.1', () => console.log('design-comments on :' + PORT));
