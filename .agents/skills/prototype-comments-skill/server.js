// ÐÑÐ¾ÑÐ¾ÑÐ¸Ð¿ comments API. PM2: prototype-comments, порт 3777 (только localhost, наружу через nginx).
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 3777;
const FILE = path.join(__dirname, 'comments.json');

function readAll() {
  try { return JSON.parse(fs.readFileSync(FILE, 'utf8')); } catch (e) { return []; }
}
function writeAll(items) {
  fs.writeFileSync(FILE, JSON.stringify(items, null, 1));
}

const server = http.createServer((req, res) => {
  const u = new URL(req.url, 'http://localhost');
  res.setHeader('Cache-Control', 'no-store');

  if (req.method === 'GET' && u.pathname === '/comments') {
    const page = u.searchParams.get('page');
    let items = readAll();
    if (page) items = items.filter(c => c.page === page);
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.end(JSON.stringify(items));
    return;
  }

  if (req.method === 'POST' && u.pathname === '/comment') {
    let body = '';
    req.on('data', ch => { body += ch; if (body.length > 20000) req.destroy(); });
    req.on('end', () => {
      try {
        const d = JSON.parse(body);
        const text = String(d.text || '').trim().slice(0, 3000);
        if (!text) throw new Error('empty');
        const item = {
          id: Date.now().toString(36) + Math.random().toString(36).slice(2, 7),
          ts: Date.now(),
          page: String(d.page || 'unknown').slice(0, 100),
          section: String(d.section || 'Блок').slice(0, 200),
          name: String(d.name || 'Клиент').trim().slice(0, 100) || 'Клиент',
          text
        };
        const items = readAll();
        items.push(item);
        writeAll(items);
        res.statusCode = 201;
        res.setHeader('Content-Type', 'application/json; charset=utf-8');
        res.end(JSON.stringify({ ok: true, id: item.id }));
      } catch (e) {
        res.statusCode = 400;
        res.end('{"ok":false}');
      }
    });
    return;
  }

  res.statusCode = 404;
  res.end('not found');
});

server.listen(PORT, '127.0.0.1', () => console.log('prototype-comments on :' + PORT));
