/**
 * =============================================================================
 * ANTIDESIGNER CORE — CLIENT-SIDE SPA ROUTER & API CONTROLLER
 * Pure Vanilla JS (ES6+), Zero external dependencies
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // Метаданные 8 модулей панели управления
  const MODULES = {
    factory: {
      title: '⚡ Контент-фабрика',
      breadcrumb: 'FACTORY'
    },
    crm: {
      title: '👥 CRM и Лиды',
      breadcrumb: 'CRM'
    },
    knowledge: {
      title: '🧠 База знаний',
      breadcrumb: 'KNOWLEDGE'
    },
    outbound: {
      title: '🎯 Холодный парсинг',
      breadcrumb: 'OUTBOUND'
    },
    bot: {
      title: '🤖 ТГ-Бот и магниты',
      breadcrumb: 'BOT'
    },
    trends: {
      title: '📡 Тренды',
      breadcrumb: 'TRENDS'
    },
    calendar: {
      title: '📅 Контент-план',
      breadcrumb: 'CALENDAR'
    },
    skills: {
      title: '⚙️ Скиллы (Промпты)',
      breadcrumb: 'SKILLS'
    }
  };

  const DEFAULT_TAB = 'factory';

  // DOM-элементы навигации и заголовков
  const navItems = document.querySelectorAll('.nav-item');
  const tabPanes = document.querySelectorAll('.tab-pane');
  const pageTitleEl = document.getElementById('pageTitle');
  const breadcrumbActiveEl = document.getElementById('breadcrumbActive');
  const apiStatusBadge = document.getElementById('apiStatusBadge');
  const checkApiBtn = document.getElementById('checkApiBtn');

  /**
   * Получить чистый идентификатор вкладки из хэша
   */
  function getCurrentTabFromHash() {
    const rawHash = window.location.hash.replace(/^#\/?/, '').trim().toLowerCase();
    return MODULES[rawHash] ? rawHash : DEFAULT_TAB;
  }

  /**
   * Активировать выбранную вкладку
   */
  function switchTab(tabKey) {
    const targetModule = MODULES[tabKey] || MODULES[DEFAULT_TAB];
    const resolvedKey = MODULES[tabKey] ? tabKey : DEFAULT_TAB;

    // 1. Переключение активного состояния в меню сайдбара
    navItems.forEach(item => {
      const itemTab = item.getAttribute('data-tab');
      if (itemTab === resolvedKey) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    // 2. Переключение таб-панелей рабочей области
    tabPanes.forEach(pane => {
      if (pane.id === `pane-${resolvedKey}`) {
        pane.classList.add('active');
      } else {
        pane.classList.remove('active');
      }
    });

    // 3. Обновление заголовка и хлебных крошек
    if (pageTitleEl) {
      pageTitleEl.textContent = targetModule.title;
    }
    if (breadcrumbActiveEl) {
      breadcrumbActiveEl.textContent = targetModule.breadcrumb;
    }

    // Синхронизация хэша в адресной строке
    if (window.location.hash !== `#${resolvedKey}`) {
      window.history.replaceState(null, '', `#${resolvedKey}`);
    }
  }

  /**
   * Проверка тестового эндпоинта базы данных (/api/test.php)
   */
  async function pingDatabaseApi() {
    if (!apiStatusBadge) return;

    const apiDot = apiStatusBadge.querySelector('.api-dot');
    const apiText = apiStatusBadge.querySelector('.api-text');

    if (apiText) apiText.textContent = 'API: PINGING...';
    apiStatusBadge.className = 'api-status-badge';

    try {
      const response = await fetch('api/test.php', {
        method: 'GET',
        cache: 'no-store',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();

      if (data && data.status === 'success') {
        apiStatusBadge.className = 'api-status-badge online';
        if (apiText) {
          apiText.textContent = `DB: ONLINE (${data.tables_count || 8} TBL)`;
        }
      } else {
        throw new Error(data.message || 'Unknown response');
      }
    } catch (err) {
      apiStatusBadge.className = 'api-status-badge error';
      if (apiText) {
        apiText.textContent = 'DB: OFFLINE';
      }
      console.warn('AntiDesigner Core DB ping warning:', err.message);
    }
  }

  // Слушатель смены хэша в адресной строке
  window.addEventListener('hashchange', () => {
    switchTab(getCurrentTabFromHash());
  });

  // Слушатель кликов по ссылкам навигации
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const tab = item.getAttribute('data-tab');
      if (tab) {
        window.location.hash = `#${tab}`;
      }
    });
  });

  // Кнопка ручной проверки базы
  if (checkApiBtn) {
    checkApiBtn.addEventListener('click', (e) => {
      e.preventDefault();
      pingDatabaseApi();
    });
  }

  // Первоначальная инициализация при загрузке страницы
  switchTab(getCurrentTabFromHash());
  pingDatabaseApi();
});
