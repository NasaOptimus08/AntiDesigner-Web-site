/**
 * =============================================================================
 * ANTIDESIGNER CORE — CLIENT-SIDE SPA ROUTER & API CONTROLLER
 * Pure Vanilla JS (ES6+), Zero external dependencies, Strict REST FastCGI
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // ---------------------------------------------------------------------------
  // 1. КОНФИГУРАЦИЯ И СЛОВАРЬ МОДУЛЕЙ ПАНЕЛИ
  // ---------------------------------------------------------------------------
  const MODULES = {
    factory: {
      title: '⚡ Контент-фабрика',
      breadcrumb: 'FACTORY',
      onActivate: () => initFactoryTab()
    },
    crm: {
      title: '👥 CRM и Лиды',
      breadcrumb: 'CRM',
      onActivate: () => initCrmTab()
    },
    knowledge: {
      title: '🧠 База знаний',
      breadcrumb: 'KNOWLEDGE',
      onActivate: () => initKnowledgeTab()
    },
    outbound: {
      title: '🎯 Холодный парсинг',
      breadcrumb: 'OUTBOUND',
      onActivate: () => initOutboundTab()
    },
    bot: {
      title: '🤖 ТГ-Бот и магниты',
      breadcrumb: 'BOT',
      onActivate: () => initBotTab()
    },
    trends: {
      title: '📡 Тренды',
      breadcrumb: 'TRENDS'
    },
    calendar: {
      title: '📅 Контент-план',
      breadcrumb: 'CALENDAR',
      onActivate: () => initCalendarTab()
    },
    skills: {
      title: '⚙️ Скиллы (Промпты)',
      breadcrumb: 'SKILLS',
      onActivate: () => initSkillsTab()
    }
  };

  const DEFAULT_TAB = 'factory';

  // DOM элементы каркаса
  const navItems = document.querySelectorAll('.nav-item');
  const tabPanes = document.querySelectorAll('.tab-pane');
  const pageTitleEl = document.getElementById('pageTitle');
  const breadcrumbActiveEl = document.getElementById('breadcrumbActive');
  const apiStatusBadge = document.getElementById('apiStatusBadge');
  const checkApiBtn = document.getElementById('checkApiBtn');
  const toastContainer = document.getElementById('toastContainer');

  // ---------------------------------------------------------------------------
  // 2. СИСТЕМА УВЕДОМЛЕНИЙ (TOAST NOTIFICATIONS)
  // ---------------------------------------------------------------------------
  function showToast(message, type = 'success', duration = 3800) {
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    let icon = '✓';
    if (type === 'error') icon = '✕';
    if (type === 'info') icon = 'ℹ';

    toast.innerHTML = `
      <span class="toast-icon">${icon}</span>
      <span class="toast-msg">${escapeHtml(message)}</span>
    `;

    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('toast-hiding');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 260);
    }, duration);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ---------------------------------------------------------------------------
  // 3. SPA РОУТИНГ И НАВИГАЦИЯ
  // ---------------------------------------------------------------------------
  function getCurrentTabFromHash() {
    const rawHash = window.location.hash.replace(/^#\/?/, '').trim().toLowerCase();
    return MODULES[rawHash] ? rawHash : DEFAULT_TAB;
  }

  function switchTab(tabKey) {
    const targetModule = MODULES[tabKey] || MODULES[DEFAULT_TAB];
    const resolvedKey = MODULES[tabKey] ? tabKey : DEFAULT_TAB;

    navItems.forEach(item => {
      const itemTab = item.getAttribute('data-tab');
      if (itemTab === resolvedKey) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    tabPanes.forEach(pane => {
      if (pane.id === `pane-${resolvedKey}`) {
        pane.classList.add('active');
      } else {
        pane.classList.remove('active');
      }
    });

    const contentCanvas = document.querySelector('.content-canvas');
    if (contentCanvas) {
      if (resolvedKey === 'bot') {
        contentCanvas.classList.add('bot-active');
      } else {
        contentCanvas.classList.remove('bot-active');
      }
    }

    if (pageTitleEl) pageTitleEl.textContent = targetModule.title;
    if (breadcrumbActiveEl) breadcrumbActiveEl.textContent = targetModule.breadcrumb;

    if (window.location.hash !== `#${resolvedKey}`) {
      window.history.replaceState(null, '', `#${resolvedKey}`);
    }

    if (typeof targetModule.onActivate === 'function') {
      targetModule.onActivate();
    }
  }

  // ---------------------------------------------------------------------------
  // 4. ПРОВЕРКА СОЕДИНЕНИЯ С БАЗОЙ И СЕРВЕРОМ (● DB: ONLINE)
  // ---------------------------------------------------------------------------
  async function pingDatabaseApi() {
    if (!apiStatusBadge) return;

    const fileBanner = document.getElementById('fileProtocolBanner');
    if (window.location.protocol === 'file:') {
      if (fileBanner) fileBanner.style.display = 'flex';
      apiStatusBadge.className = 'api-status-badge error';
      const apiText = apiStatusBadge.querySelector('.api-text');
      if (apiText) apiText.textContent = '● DB: OFFLINE (file://)';
      return;
    } else {
      if (fileBanner) fileBanner.style.display = 'none';
    }

    const apiText = apiStatusBadge.querySelector('.api-text');
    if (apiText) apiText.textContent = 'API: PINGING...';
    apiStatusBadge.className = 'api-status-badge';

    try {
      const response = await fetch('api/test.php', {
        method: 'GET',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      if (data && data.status === 'success') {
        apiStatusBadge.className = 'api-status-badge online';
        if (apiText) {
          apiText.textContent = `● DB: ONLINE (${data.tables_count || 8} TBL)`;
        }
      } else {
        throw new Error(data.message || 'Unknown response');
      }
    } catch (err) {
      apiStatusBadge.className = 'api-status-badge error';
      if (apiText) apiText.textContent = '● DB: OFFLINE';
      console.warn('AntiDesigner Core DB ping warning:', err.message);
    }
  }

  // ===========================================================================
  // 5. МОДУЛЬ: ⚙️ СКИЛЛЫ / ПРОМПТ-ИНЖИНИРИНГ (#skills)
  // 5 категорий: [Все] | [Статьи] | [Посты в ТГ] | [Инстаграм] | [Продажи] | [Бот]
  // ===========================================================================
  let skillsData = [];
  let currentSkillFilter = 'all';
  let activeSkillId = null;
  let isSkillsLoaded = false;

  const skillsListEl = document.getElementById('skillsList');
  const skillsCountBadgeEl = document.getElementById('skillsCountBadge');
  const skillsFilterTabs = document.getElementById('skillsFilterTabs');
  const skillEditorTitleEl = document.getElementById('skillEditorTitle');
  const skillEditorCodeEl = document.getElementById('skillEditorCode');
  const skillCategoryTagEl = document.getElementById('skillCategoryTag');
  const skillTovInputEl = document.getElementById('skillTovInput');
  const skillPromptInputEl = document.getElementById('skillPromptInput');
  const saveSkillBtn = document.getElementById('saveSkillBtn');
  const skillSaveStatusEl = document.getElementById('skillSaveStatus');
  const skillUpdatedTimestampEl = document.getElementById('skillUpdatedTimestamp');
  const skillFileInput = document.getElementById('skillFileInput');

  async function initSkillsTab() {
    if (isSkillsLoaded) return;
    await loadSkills();
  }

  async function loadSkills() {
    if (!skillsListEl) return;
    skillsListEl.innerHTML = '<div class="skills-list-skeleton">Синхронизация скиллов с базой данных...</div>';

    try {
      const res = await fetch('api/skills.php', {
        method: 'GET',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      if (data && data.status === 'success' && Array.isArray(data.skills)) {
        skillsData = data.skills;
        isSkillsLoaded = true;
        renderSkillsList();

        const initialSkill = activeSkillId
          ? skillsData.find(s => s.id === activeSkillId) || skillsData[0]
          : skillsData[0];

        if (initialSkill) {
          selectSkill(initialSkill.id);
        }
      } else {
        throw new Error(data.message || 'Ошибка формата ответа');
      }
    } catch (err) {
      skillsListEl.innerHTML = `<div class="action-status-hint" style="color:var(--danger-red);padding:14px;">Не удалось загрузить скиллы: ${err.message}</div>`;
      showToast(`Ошибка загрузки скиллов: ${err.message}`, 'error');
    }
  }

  function getFilteredSkills() {
    if (currentSkillFilter === 'all') return skillsData;
    return skillsData.filter(s => s.category === currentSkillFilter);
  }

  function renderSkillsList() {
    if (!skillsListEl) return;
    skillsListEl.innerHTML = '';

    const visibleSkills = getFilteredSkills();

    if (skillsCountBadgeEl) {
      skillsCountBadgeEl.textContent = `${visibleSkills.length} из ${skillsData.length}`;
    }

    if (visibleSkills.length === 0) {
      skillsListEl.innerHTML = '<div class="action-status-hint" style="padding:14px;">Нет скиллов в этой категории.</div>';
      return;
    }

    visibleSkills.forEach(skill => {
      const card = document.createElement('div');
      card.className = `skill-item-card ${skill.id === activeSkillId ? 'active' : ''}`;
      card.setAttribute('data-id', skill.id);
      card.innerHTML = `
        <div class="skill-card-top">
          <span class="skill-card-title">${escapeHtml(skill.title)}</span>
          <span class="tech-tag">${escapeHtml(skill.code)}</span>
        </div>
        <div class="skill-card-tov">${escapeHtml(skill.tone_of_voice || 'Стиль не задан')}</div>
      `;

      card.addEventListener('click', () => {
        selectSkill(skill.id);
      });

      skillsListEl.appendChild(card);
    });
  }

  function selectSkill(id) {
    activeSkillId = id;
    const skill = skillsData.find(s => s.id === id);
    if (!skill) return;

    if (skillsListEl) {
      skillsListEl.querySelectorAll('.skill-item-card').forEach(c => {
        if (parseInt(c.getAttribute('data-id'), 10) === id) {
          c.classList.add('active');
        } else {
          c.classList.remove('active');
        }
      });
    }

    if (skillEditorTitleEl) skillEditorTitleEl.textContent = skill.title;
    if (skillEditorCodeEl) skillEditorCodeEl.textContent = `SKILL: ${skill.code.toUpperCase()}`;
    if (skillCategoryTagEl) skillCategoryTagEl.textContent = `CATEGORY: ${(skill.category || 'ARTICLES').toUpperCase()}`;
    if (skillTovInputEl) skillTovInputEl.value = skill.tone_of_voice || '';
    if (skillPromptInputEl) skillPromptInputEl.value = skill.system_prompt || '';
    if (skillUpdatedTimestampEl) {
      skillUpdatedTimestampEl.textContent = skill.updated_at ? `Обновлено: ${skill.updated_at}` : 'Синхронизировано';
    }
    if (skillSaveStatusEl) skillSaveStatusEl.textContent = '';
  }

  // Фильтр категорий скиллов
  if (skillsFilterTabs) {
    skillsFilterTabs.querySelectorAll('.skill-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        skillsFilterTabs.querySelectorAll('.skill-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentSkillFilter = btn.getAttribute('data-cat') || 'all';
        renderSkillsList();

        const visible = getFilteredSkills();
        if (visible.length > 0 && !visible.some(s => s.id === activeSkillId)) {
          selectSkill(visible[0].id);
        }
      });
    });
  }

  // Загрузка файла скилла
  if (skillFileInput) {
    skillFileInput.addEventListener('change', async (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const currentSkill = skillsData.find(s => s.id === activeSkillId);
      const formData = new FormData();
      formData.append('file', file);
      formData.append('target', 'skill');
      if (currentSkill) {
        formData.append('skill_id', String(currentSkill.id));
        formData.append('skill_code', currentSkill.code);
      }

      showToast(`Загрузка файла ${file.name}...`, 'info');

      try {
        const res = await fetch('api/upload.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
          throw new Error(data.message || 'Ошибка загрузки файла');
        }

        if (skillPromptInputEl && data.prompt_text) {
          skillPromptInputEl.value = data.prompt_text;
          if (currentSkill) currentSkill.system_prompt = data.prompt_text;
        }

        showToast('Файл успешно загружен и обработан', 'success');
        if (skillSaveStatusEl) skillSaveStatusEl.textContent = `✓ Из файла: ${escapeHtml(data.filename)}`;
      } catch (err) {
        showToast(`Ошибка загрузки: ${err.message}`, 'error');
      } finally {
        skillFileInput.value = '';
      }
    });
  }

  async function saveActiveSkill() {
    if (!activeSkillId || !saveSkillBtn) return;
    const skill = skillsData.find(s => s.id === activeSkillId);
    if (!skill) return;

    const newTov = skillTovInputEl ? skillTovInputEl.value.trim() : '';
    const newPrompt = skillPromptInputEl ? skillPromptInputEl.value.trim() : '';

    if (!newPrompt) {
      showToast('Системный промпт не может быть пустым', 'error');
      return;
    }

    saveSkillBtn.disabled = true;
    saveSkillBtn.innerHTML = '<span class="btn-icon">↺</span> Сохранение...';
    if (skillSaveStatusEl) skillSaveStatusEl.textContent = 'Запись в SQLite...';

    try {
      const res = await fetch('api/skills.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: skill.id,
          code: skill.code,
          category: skill.category,
          tone_of_voice: newTov,
          system_prompt: newPrompt
        })
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка сохранения');
      }

      skill.tone_of_voice = newTov;
      skill.system_prompt = newPrompt;
      if (data.skill && data.skill.updated_at) {
        skill.updated_at = data.skill.updated_at;
        if (skillUpdatedTimestampEl) skillUpdatedTimestampEl.textContent = `Обновлено: ${skill.updated_at}`;
      }

      renderSkillsList();
      showToast(`Скилл «${skill.title}» успешно сохранен!`, 'success');
      if (skillSaveStatusEl) skillSaveStatusEl.textContent = '✓ Сохранено';
    } catch (err) {
      showToast(`Не удалось сохранить скилл: ${err.message}`, 'error');
      if (skillSaveStatusEl) skillSaveStatusEl.textContent = 'Ошибка сохранения';
    } finally {
      saveSkillBtn.disabled = false;
      saveSkillBtn.innerHTML = '<span class="btn-icon">💾</span> Сохранить скилл';
    }
  }

  if (saveSkillBtn) {
    saveSkillBtn.addEventListener('click', saveActiveSkill);
  }

  // ===========================================================================
  // 6. МОДУЛЬ: 🧠 БАЗА ЗНАНИЙ (RAG CONTEXT & MEMORY) (#knowledge)
  // 4 раздела: leads_archive, crm_memory, scraping_logs, content_rules
  // ===========================================================================
  let kbData = [];
  let activeKbCategory = 'leads_archive';
  let isKbLoaded = false;

  const kbCategoriesNav = document.getElementById('kbCategoriesNav');
  const kbCategoryBadgeEl = document.getElementById('kbCategoryBadge');
  const kbActiveTitleEl = document.getElementById('kbActiveTitle');
  const kbContentInputEl = document.getElementById('kbContentInput');
  const saveKbBtn = document.getElementById('saveKbBtn');
  const kbSaveStatusEl = document.getElementById('kbSaveStatus');
  const kbUpdatedTimestampEl = document.getElementById('kbUpdatedTimestamp');
  const kbFileInput = document.getElementById('kbFileInput');

  async function initKnowledgeTab() {
    if (isKbLoaded) return;
    await loadKnowledge();
  }

  async function loadKnowledge() {
    try {
      const res = await fetch('api/knowledge.php', {
        method: 'GET',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      if (data && data.status === 'success' && Array.isArray(data.items)) {
        kbData = data.items;
        isKbLoaded = true;
        selectKbCategory(activeKbCategory);
      } else {
        throw new Error(data.message || 'Ошибка данных');
      }
    } catch (err) {
      showToast(`Ошибка загрузки базы знаний: ${err.message}`, 'error');
    }
  }

  function selectKbCategory(category) {
    activeKbCategory = category;

    if (kbCategoriesNav) {
      kbCategoriesNav.querySelectorAll('.kb-cat-btn').forEach(btn => {
        if (btn.getAttribute('data-category') === category) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
    }

    const item = kbData.find(k => k.category === category);
    if (!item) return;

    if (kbCategoryBadgeEl) kbCategoryBadgeEl.textContent = `CATEGORY: ${item.category.toUpperCase()}`;
    if (kbActiveTitleEl) kbActiveTitleEl.textContent = item.title;
    if (kbContentInputEl) kbContentInputEl.value = item.content_markdown || '';
    if (kbUpdatedTimestampEl) {
      kbUpdatedTimestampEl.textContent = item.updated_at ? `Синхронизировано: ${item.updated_at}` : 'Синхронизировано';
    }
    if (kbSaveStatusEl) kbSaveStatusEl.textContent = '';

    // Синхронизация реестра лидов для категории leads_archive
    const kbLeadsDynamicPreview = document.getElementById('kbLeadsDynamicPreview');
    if (kbLeadsDynamicPreview) {
      if (category === 'leads_archive') {
        kbLeadsDynamicPreview.style.display = 'block';
        loadKbLeadsPreview();
      } else {
        kbLeadsDynamicPreview.style.display = 'none';
      }
    }
  }

  async function loadKbLeadsPreview() {
    const listEl = document.getElementById('kbLeadsPreviewList');
    if (!listEl) return;
    try {
      const res = await fetch('api/outreach.php?action=list');
      const data = await res.json();
      if (data && data.status === 'success' && Array.isArray(data.leads)) {
        if (data.leads.length === 0) {
          listEl.innerHTML = '<span style="color:#888e99;font-size:12px;">Лиды еще не добавлены. Запустите квалификацию в модуле «Холодный парсинг».</span>';
          return;
        }
        listEl.innerHTML = data.leads.slice(0, 6).map(l => `
          <div class="kb-lead-mini-card">
            <div class="mini-header">
              <span class="mini-company">${escapeHtml(l.company_name)}</span>
              <span class="score-pill ${l.deal_score >= 8 ? 'score-high' : 'score-med'}" style="font-size:10px;padding:2px 8px;">★ ${l.deal_score}/10</span>
            </div>
            <div class="mini-niche">${escapeHtml(l.niche || 'B2B')} • ${escapeHtml(l.lpr_name || 'ЛПР')}</div>
            <div class="mini-stats">
              <span class="ps-score-pill ${l.pagespeed_score >= 50 ? 'ps-warn' : 'ps-bad'}" style="font-size:10px;padding:2px 6px;">⚡ ${l.pagespeed_score}/100</span>
              <span style="color:#a3a8b5;font-size:11px;">FCP: ${l.load_time_sec}с</span>
              <span class="status-pill status-${l.status}" style="font-size:9px;padding:2px 6px;">${l.status}</span>
            </div>
          </div>
        `).join('');
      }
    } catch (e) {
      console.warn('KB Leads Preview error:', e);
    }
  }

  // Загрузка документов в Базу знаний (PDF, DOCX, MD, TXT)
  if (kbFileInput) {
    kbFileInput.addEventListener('change', async (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);
      formData.append('target', 'knowledge');
      formData.append('category', activeKbCategory);

      showToast(`Загрузка документа ${file.name}...`, 'info');

      try {
        const res = await fetch('api/upload.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
          throw new Error(data.message || 'Ошибка обработки документа');
        }

        if (kbContentInputEl && data.new_content) {
          kbContentInputEl.value = data.new_content;
          const item = kbData.find(k => k.category === activeKbCategory);
          if (item) item.content_markdown = data.new_content;
        }

        showToast('Файл успешно загружен и обработан', 'success');
        if (kbSaveStatusEl) kbSaveStatusEl.textContent = `✓ Прикреплен: ${escapeHtml(data.filename)}`;
      } catch (err) {
        showToast(`Ошибка загрузки: ${err.message}`, 'error');
      } finally {
        kbFileInput.value = '';
      }
    });
  }

  async function saveActiveKnowledge() {
    if (!saveKbBtn || !kbContentInputEl) return;
    const item = kbData.find(k => k.category === activeKbCategory);
    if (!item) return;

    const newMarkdown = kbContentInputEl.value.trim();
    if (!newMarkdown) {
      showToast('Контекст категории не может быть пустым', 'error');
      return;
    }

    saveKbBtn.disabled = true;
    saveKbBtn.innerHTML = '<span class="btn-icon">↺</span> Сохранение...';
    if (kbSaveStatusEl) kbSaveStatusEl.textContent = 'Запись в SQLite...';

    try {
      const res = await fetch('api/knowledge.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: item.id,
          category: item.category,
          title: item.title,
          content_markdown: newMarkdown
        })
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка сохранения');
      }

      item.content_markdown = newMarkdown;
      if (data.item && data.item.updated_at) {
        item.updated_at = data.item.updated_at;
        if (kbUpdatedTimestampEl) kbUpdatedTimestampEl.textContent = `Синхронизировано: ${item.updated_at}`;
      }

      showToast(`Контекст «${item.title}» успешно обновлен!`, 'success');
      if (kbSaveStatusEl) kbSaveStatusEl.textContent = '✓ Сохранено';
    } catch (err) {
      showToast(`Не удалось сохранить контекст: ${err.message}`, 'error');
      if (kbSaveStatusEl) kbSaveStatusEl.textContent = 'Ошибка сохранения';
    } finally {
      saveKbBtn.disabled = false;
      saveKbBtn.innerHTML = '<span class="btn-icon">💾</span> Сохранить контекст';
    }
  }

  if (kbCategoriesNav) {
    kbCategoriesNav.querySelectorAll('.kb-cat-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const cat = btn.getAttribute('data-category');
        if (cat) selectKbCategory(cat);
      });
    });
  }

  if (saveKbBtn) {
    saveKbBtn.addEventListener('click', saveActiveKnowledge);
  }

  // ===========================================================================
  // 7. МОДУЛЬ: ⚡ КОНТЕНТ-ФАБРИКА И ПУБЛИКАЦИЯ В TELEGRAM (#factory)
  // Режимы: [⚡ Полный пакет] | [📝 Статья] | [💬 Пост в ТГ] | [📸 Инстаграм] | [🧲 Лид-магнит]
  // ===========================================================================
  let generatedBatch = [];
  let activeGeneratedIndex = 0;
  let currentFactoryMode = 'all';

  const factoryModeSelector = document.getElementById('factoryModeSelector');
  const packageFormatsGroup = document.getElementById('packageFormatsGroup');
  const factoryTopicInputEl = document.getElementById('factoryTopicInput');
  const generateBtn = document.getElementById('generateBtn');
  const factoryLoader = document.getElementById('factoryLoader');
  const factoryLoaderText = document.getElementById('factoryLoaderText');
  const factoryResults = document.getElementById('factoryResults');
  const factoryResultTopic = document.getElementById('factoryResultTopic');
  const factoryResultTabs = document.getElementById('factoryResultTabs');
  const factoryActiveFormatTitle = document.getElementById('factoryActiveFormatTitle');
  const factoryActiveStatusBadge = document.getElementById('factoryActiveStatusBadge');
  const factoryContentTextarea = document.getElementById('factoryContentTextarea');
  const factoryCharCounter = document.getElementById('factoryCharCounter');
  const factoryCopyBtn = document.getElementById('factoryCopyBtn');
  const factorySaveDraftBtn = document.getElementById('factorySaveDraftBtn');
  const factoryPublishTgBtn = document.getElementById('factoryPublishTgBtn');
  const factoryActionStatus = document.getElementById('factoryActionStatus');

  function initFactoryTab() {
    updateCharCounter();
  }

  function updateCharCounter() {
    if (!factoryCharCounter || !factoryContentTextarea) return;
    const len = factoryContentTextarea.value.length;
    const words = factoryContentTextarea.value.trim() ? factoryContentTextarea.value.trim().split(/\s+/).length : 0;
    factoryCharCounter.textContent = `${len} символов · ${words} слов`;
  }

  if (factoryContentTextarea) {
    factoryContentTextarea.addEventListener('input', updateCharCounter);
  }

  // Переключение режима генерации
  if (factoryModeSelector) {
    factoryModeSelector.querySelectorAll('.mode-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        factoryModeSelector.querySelectorAll('.mode-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFactoryMode = btn.getAttribute('data-mode') || 'all';

        if (packageFormatsGroup) {
          packageFormatsGroup.style.display = (currentFactoryMode === 'all') ? 'flex' : 'none';
        }
      });
    });
  }

  // Генерация материалов
  async function generateContentBatch() {
    if (!factoryTopicInputEl || !generateBtn) return;

    const topic = factoryTopicInputEl.value.trim();
    if (!topic) {
      showToast('Укажите тему или инфоповод для генерации', 'error');
      factoryTopicInputEl.focus();
      return;
    }

    const payload = {
      topic: topic,
      format: currentFactoryMode
    };

    if (currentFactoryMode === 'all') {
      const checkedFormats = [];
      document.querySelectorAll('input[name="factoryFormats"]:checked').forEach(cb => {
        checkedFormats.push(cb.value);
      });
      if (checkedFormats.length === 0) {
        showToast('Выберите хотя бы один формат для генерации', 'error');
        return;
      }
      payload.formats = checkedFormats;
    }

    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span class="btn-icon">↺</span> Генерация через AIRouter...';
    if (factoryLoader) {
      factoryLoader.style.display = 'flex';
      if (factoryLoaderText) {
        factoryLoaderText.textContent = `AIRouter выполняет запрос к ${currentFactoryMode === 'article' || currentFactoryMode === 'lead_magnet' ? 'Heavy (Polza Gemini 3.8)' : 'Fast (Google Flash Lite)'}...`;
      }
    }
    if (factoryResults) {
      factoryResults.style.display = 'none';
    }

    try {
      const res = await fetch('api/content.php?action=generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка генерации материалов');
      }

      if (!Array.isArray(data.generated) || data.generated.length === 0) {
        throw new Error('Генерация вернула пустой результат');
      }

      generatedBatch = data.generated;
      activeGeneratedIndex = 0;

      renderGeneratedResults(data.topic || topic);
      showToast(`Сгенерировано материалов: ${generatedBatch.length}`, 'success');

      if (data.errors && data.errors.length > 0) {
        data.errors.forEach(errMsg => showToast(errMsg, 'info'));
      }
    } catch (err) {
      showToast(`Ошибка генерации: ${err.message}`, 'error');
      console.error('Factory generation error:', err);
    } finally {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<span class="btn-icon">⚡</span> Сгенерировать';
      if (factoryLoader) {
        factoryLoader.style.display = 'none';
      }
    }
  }

  function renderGeneratedResults(topic) {
    if (!factoryResults) return;
    factoryResults.style.display = 'flex';

    if (factoryResultTopic) {
      factoryResultTopic.textContent = `Тема: «${topic}»`;
    }

    if (factoryResultTabs) {
      factoryResultTabs.innerHTML = '';
      generatedBatch.forEach((item, idx) => {
        const tabBtn = document.createElement('button');
        tabBtn.type = 'button';
        tabBtn.className = `fmt-tab-btn ${idx === activeGeneratedIndex ? 'active' : ''}`;
        tabBtn.innerHTML = `<span>${getFormatShortBadge(item.format)}</span> <span>${escapeHtml(item.title)}</span>`;

        tabBtn.addEventListener('click', () => {
          selectGeneratedTab(idx);
        });

        factoryResultTabs.appendChild(tabBtn);
      });
    }

    selectGeneratedTab(0);
  }

  function getFormatShortBadge(formatCode) {
    switch (formatCode) {
      case 'tg_post':
      case 'tg_digest': return 'TG';
      case 'vc_ru': return 'VC';
      case 'reels':
      case 'reels_hook': return 'REELS';
      case 'ig_carousel': return 'CAROUSEL';
      case 'lead_magnet': return 'PDF';
      case 'site_geo': return 'SEO';
      case 'cold_pitch': return 'PITCH';
      case 'objections': return 'SALES';
      case 'bot_consultant': return 'BOT';
      default: return 'DOC';
    }
  }

  function selectGeneratedTab(index) {
    activeGeneratedIndex = index;
    const currentItem = generatedBatch[index];
    if (!currentItem) return;

    if (factoryResultTabs) {
      const btns = factoryResultTabs.querySelectorAll('.fmt-tab-btn');
      btns.forEach((b, i) => {
        if (i === index) b.classList.add('active');
        else b.classList.remove('active');
      });
    }

    if (factoryActiveFormatTitle) {
      factoryActiveFormatTitle.textContent = currentItem.title;
    }

    if (factoryActiveStatusBadge) {
      if (currentItem.status === 'published') {
        factoryActiveStatusBadge.className = 'status-badge-published';
        factoryActiveStatusBadge.textContent = 'ОПУБЛИКОВАНО';
      } else {
        factoryActiveStatusBadge.className = 'status-badge-draft';
        factoryActiveStatusBadge.textContent = 'ЧЕРНОВИК';
      }
    }

    if (factoryContentTextarea) {
      factoryContentTextarea.value = currentItem.content || '';
      updateCharCounter();
    }

    // Кнопка публикации в TG активна преимущественно для постов
    if (factoryPublishTgBtn) {
      const isTg = currentItem.format === 'tg_post' || currentItem.format === 'tg_digest';
      factoryPublishTgBtn.style.display = isTg ? 'inline-flex' : 'none';
    }

    if (factoryActionStatus) {
      factoryActionStatus.textContent = currentItem.status === 'published'
        ? 'Материал опубликован в Telegram-канале'
        : 'Материал сохранен как черновик в SQLite';
    }
  }

  async function saveDraftContent() {
    const currentItem = generatedBatch[activeGeneratedIndex];
    if (!currentItem || !factorySaveDraftBtn || !factoryContentTextarea) return;

    const newText = factoryContentTextarea.value.trim();
    if (!newText) {
      showToast('Текст материала не может быть пустым', 'error');
      return;
    }

    factorySaveDraftBtn.disabled = true;
    factorySaveDraftBtn.innerHTML = '<span class="btn-icon">↺</span> Сохранение...';

    try {
      const res = await fetch('api/content.php?action=update', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: currentItem.id,
          content_markdown: newText
        })
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка обновления');
      }

      currentItem.content = newText;
      showToast('Черновик успешно обновлен в базе!', 'success');
      if (factoryActionStatus) factoryActionStatus.textContent = '✓ Черновик сохранен в базе';
    } catch (err) {
      showToast(`Ошибка сохранения: ${err.message}`, 'error');
    } finally {
      factorySaveDraftBtn.disabled = false;
      factorySaveDraftBtn.innerHTML = '<span class="btn-icon">💾</span> Сохранить черновик';
    }
  }

  async function copyCurrentContent() {
    if (!factoryContentTextarea) return;
    const text = factoryContentTextarea.value;
    if (!text) {
      showToast('Нет текста для копирования', 'info');
      return;
    }

    try {
      await navigator.clipboard.writeText(text);
      showToast('Текст скопирован в буфер обмена!', 'info');
    } catch {
      factoryContentTextarea.select();
      document.execCommand('copy');
      showToast('Текст скопирован!', 'info');
    }
  }

  async function publishToTelegram() {
    const currentItem = generatedBatch[activeGeneratedIndex];
    if (!currentItem || !factoryPublishTgBtn || !factoryContentTextarea) return;

    const textToPublish = factoryContentTextarea.value.trim();
    if (!textToPublish) {
      showToast('Текст для публикации пуст', 'error');
      return;
    }

    factoryPublishTgBtn.disabled = true;
    factoryPublishTgBtn.innerHTML = '<span class="btn-icon">↺</span> Отправка в TG...';

    try {
      const res = await fetch('api/content.php?action=publish_tg', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          content_id: currentItem.id,
          text: textToPublish
        })
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка отправки в Telegram');
      }

      currentItem.status = 'published';
      if (factoryActiveStatusBadge) {
        factoryActiveStatusBadge.className = 'status-badge-published';
        factoryActiveStatusBadge.textContent = 'ОПУБЛИКОВАНО';
      }
      if (factoryActionStatus) {
        factoryActionStatus.textContent = '✓ Успешно опубликовано в Telegram-канале';
      }

      showToast('Пост успешно опубликован в Telegram!', 'success');
    } catch (err) {
      showToast(`Не удалось опубликовать: ${err.message}`, 'error');
    } finally {
      factoryPublishTgBtn.disabled = false;
      factoryPublishTgBtn.innerHTML = '<span class="btn-icon">✈️</span> Опубликовать в Telegram';
    }
  }

  if (generateBtn) {
    generateBtn.addEventListener('click', generateContentBatch);
  }

  if (factorySaveDraftBtn) {
    factorySaveDraftBtn.addEventListener('click', saveDraftContent);
  }

  if (factoryCopyBtn) {
    factoryCopyBtn.addEventListener('click', copyCurrentContent);
  }

  if (factoryPublishTgBtn) {
    factoryPublishTgBtn.addEventListener('click', publishToTelegram);
  }

  // ===========================================================================
  // 6. МОДУЛЬ: 👥 CRM, ВОРОНКА И АНАЛИТИКА (#crm)
  // ===========================================================================
  let crmCurrentPeriod = 'all';
  let crmCurrentSubtab = 'kanban';
  let isCrmLoaded = false;

  const crmPeriodFilter = document.getElementById('crmPeriodFilter');
  const crmSubtabsNav = document.getElementById('crmSubtabsNav');
  const crmSubpaneKanban = document.getElementById('crmSubpaneKanban');
  const crmSubpanePlatforms = document.getElementById('crmSubpanePlatforms');
  const crmSubpaneSite = document.getElementById('crmSubpaneSite');
  const crmAddLeadBtn = document.getElementById('crmAddLeadBtn');

  // Modal New Lead
  const crmNewLeadModal = document.getElementById('crmNewLeadModal');
  const crmLeadModalClose = document.getElementById('crmLeadModalClose');
  const crmSaveLeadBtn = document.getElementById('crmSaveLeadBtn');
  const crmLeadName = document.getElementById('crmLeadName');
  const crmLeadTg = document.getElementById('crmLeadTg');
  const crmLeadStatus = document.getElementById('crmLeadStatus');
  const crmLeadBudget = document.getElementById('crmLeadBudget');
  const crmLeadUtm = document.getElementById('crmLeadUtm');
  const crmLeadNotes = document.getElementById('crmLeadNotes');

  async function initCrmTab(forceReload = false) {
    if (isCrmLoaded && !forceReload) return;
    isCrmLoaded = true;
    await refreshActiveCrmSubtab();
  }

  async function refreshActiveCrmSubtab() {
    if (crmCurrentSubtab === 'kanban') {
      await loadKanbanData();
    } else if (crmCurrentSubtab === 'platforms') {
      await loadPlatformAnalytics();
    } else if (crmCurrentSubtab === 'site') {
      await loadSiteAnalytics();
    }
  }

  async function loadKanbanData() {
    try {
      const res = await fetch(`api/crm.php?action=kanban&period=${crmCurrentPeriod}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message || 'Ошибка загрузки CRM');

      renderKanbanColumns(data.columns || {}, data.counts || {});
    } catch (err) {
      showToast(`Ошибка CRM: ${err.message}`, 'error');
    }
  }

  function renderKanbanColumns(columns, counts) {
    const colIds = {
      new: { list: document.getElementById('kanbanColNew'), badge: document.getElementById('kanbanCountNew') },
      qualified: { list: document.getElementById('kanbanColQualified'), badge: document.getElementById('kanbanCountQualified') },
      hot: { list: document.getElementById('kanbanColHot'), badge: document.getElementById('kanbanCountHot') },
      deal_closed: { list: document.getElementById('kanbanColDeal'), badge: document.getElementById('kanbanCountDeal') },
      archived: { list: document.getElementById('kanbanColArchived'), badge: document.getElementById('kanbanCountArchived') }
    };

    Object.keys(colIds).forEach(status => {
      const col = colIds[status];
      if (col.badge) col.badge.textContent = counts[status] || 0;
      if (!col.list) return;

      const leads = columns[status] || [];
      if (leads.length === 0) {
        col.list.innerHTML = '<div style="font-size:11.5px;color:var(--color-text-subtle);text-align:center;padding:24px 0;">Нет лидов</div>';
        return;
      }

      col.list.innerHTML = leads.map(lead => `
        <div class="kanban-card" data-lead-id="${lead.id}">
          <div class="card-header-row">
            <div class="card-lead-name">${escapeHtml(lead.full_name || 'Без имени')}</div>
            <button type="button" class="btn-delete-lead" data-id="${lead.id}" data-name="${escapeHtml(lead.full_name || 'Лид')}" title="Удалить лида">🗑</button>
          </div>
          ${lead.telegram_username ? `<a href="https://t.me/${escapeHtml(lead.telegram_username)}" target="_blank" class="card-lead-tg">@${escapeHtml(lead.telegram_username)}</a>` : ''}
          <div class="card-chips-row">
            ${lead.utm_source ? `<span class="chip-utm">UTM: ${escapeHtml(lead.utm_source)}</span>` : ''}
            ${lead.budget_range ? `<span class="chip-budget">${escapeHtml(lead.budget_range)}</span>` : ''}
          </div>
          ${lead.notes ? `<div class="card-notes-text">${escapeHtml(lead.notes)}</div>` : ''}
          <select class="card-status-select" data-id="${lead.id}">
            <option value="new" ${lead.status === 'new' ? 'selected' : ''}>Новый</option>
            <option value="qualified" ${lead.status === 'qualified' ? 'selected' : ''}>Квалифицирован</option>
            <option value="hot" ${lead.status === 'hot' ? 'selected' : ''}>Горячий</option>
            <option value="deal_closed" ${lead.status === 'deal_closed' ? 'selected' : ''}>Сделка закрыта</option>
            <option value="archived" ${lead.status === 'archived' ? 'selected' : ''}>В архиве</option>
          </select>
        </div>
      `).join('');
    });

    // Привязываем слушатели удаления лидов
    document.querySelectorAll('.btn-delete-lead').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = parseInt(btn.getAttribute('data-id'), 10);
        const name = btn.getAttribute('data-name') || 'лида';
        if (!confirm(`Удалить лида «${name}»? Связанные сообщения чата также будут удалены.`)) return;

        try {
          const res = await fetch('api/crm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_lead', id })
          });
          const data = await res.json();
          if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка удаления');
          showToast(`Лид «${name}» удален`, 'info');
          await loadKanbanData();
        } catch (err) {
          showToast(`Ошибка: ${err.message}`, 'error');
        }
      });
    });

    // Привязываем слушатели смены статуса
    document.querySelectorAll('.card-status-select').forEach(sel => {
      sel.addEventListener('change', async () => {
        const id = parseInt(sel.getAttribute('data-id'), 10);
        const newStatus = sel.value;
        try {
          const res = await fetch('api/crm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_lead_status', id, status: newStatus })
          });
          const data = await res.json();
          if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка обновления');
          showToast('Статус лида обновлен', 'success');
          await loadKanbanData();
        } catch (err) {
          showToast(`Ошибка: ${err.message}`, 'error');
        }
      });
    });
  }

  let currentPlatformFilter = 'all';

  async function loadPlatformAnalytics(platform = currentPlatformFilter) {
    currentPlatformFilter = platform;
    try {
      const res = await fetch(`api/crm.php?action=platform_analytics&period=${crmCurrentPeriod}&platform=${platform}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      const elViews = document.getElementById('paViewsTotal');
      const elLikes = document.getElementById('paLikesTotal');
      const elComments = document.getElementById('paCommentsTotal');
      const elVideoPlays = document.getElementById('paVideoPlaysTotal');
      const elLeads = document.getElementById('paLeadsTotal');
      const elConvHint = document.getElementById('paConvHint');
      const elTableBody = document.getElementById('paTableBody');
      const elPubsBody = document.getElementById('paPubsTableBody');
      const elTableTitle = document.getElementById('paTableTitle');
      const elTableBadge = document.getElementById('paTableBadge');

      if (elViews) elViews.textContent = (data.total_views || 0).toLocaleString('ru-RU');
      if (elLikes) elLikes.textContent = (data.total_likes || 0).toLocaleString('ru-RU');
      if (elComments) elComments.textContent = (data.total_comments || 0).toLocaleString('ru-RU');
      if (elVideoPlays) elVideoPlays.textContent = (data.total_video_plays || 0).toLocaleString('ru-RU');
      if (elLeads) elLeads.textContent = (data.total_leads || 0).toLocaleString('ru-RU');
      if (elConvHint) elConvHint.textContent = `Конверсия в лид ${data.conversion_pct || 0}%`;

      // Таблица публикаций выбранной платформы
      if (elPubsBody && Array.isArray(data.publications)) {
        if (data.publications.length === 0) {
          elPubsBody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding:20px;color:var(--color-text-subtle);">Нет публикаций за выбранный период</td></tr>';
        } else {
          elPubsBody.innerHTML = data.publications.map(pub => {
            const platBadges = {
              telegram: '<span class="plat-badge tg">Telegram</span>',
              vc_ru: '<span class="plat-badge vc">VC.ru</span>',
              habr: '<span class="plat-badge habr">Habr</span>',
              instagram: '<span class="plat-badge inst">Instagram</span>',
              setka: '<span class="plat-badge setka">Setka</span>'
            };
            const badgeHtml = platBadges[pub.platform] || `<span class="tech-tag">${pub.platform}</span>`;
            return `
              <tr>
                <td><strong>${escapeHtml(pub.title)}</strong></td>
                <td>${badgeHtml}</td>
                <td>${(pub.views_count || 0).toLocaleString('ru-RU')}</td>
                <td>${(pub.likes_count || 0).toLocaleString('ru-RU')}</td>
                <td>${(pub.comments_count || 0).toLocaleString('ru-RU')}</td>
                <td>${(pub.video_plays || 0) > 0 ? (pub.video_plays).toLocaleString('ru-RU') : '-'}</td>
                <td><span class="tech-tag">${pub.published_date || '-'}</span></td>
                <td style="text-align: center;">
                  ${pub.post_url ? `<a href="${escapeHtml(pub.post_url)}" target="_blank" rel="noopener" class="btn-tech-outline btn-xs" title="Открыть материал">↗</a>` : '-'}
                </td>
              </tr>
            `;
          }).join('');
        }
        if (elTableBadge) elTableBadge.textContent = `${data.publications.length} МАТЕРИАЛОВ`;
      }

      if (elTableTitle) {
        const platformNames = {
          all: 'Все платформы',
          telegram: 'Telegram-канал',
          vc_ru: 'VC.ru Лонгриды',
          habr: 'Habr',
          instagram: 'Instagram',
          setka: 'Сетка (HeadHunter)'
        };
        elTableTitle.textContent = `Публикации (${platformNames[platform] || platform})`;
      }

      // Таблица детализации по каналам
      if (elTableBody && Array.isArray(data.platforms)) {
        elTableBody.innerHTML = data.platforms.map(p => `
          <tr>
            <td><strong>${p.icon} ${escapeHtml(p.name)}</strong></td>
            <td>${p.views.toLocaleString('ru-RU')}</td>
            <td>${p.reactions.toLocaleString('ru-RU')}</td>
            <td><span class="sys-green">${p.link_clicks.toLocaleString('ru-RU')}</span></td>
            <td><strong class="sys-accent">${p.leads}</strong></td>
            <td><span class="tech-tag">${p.conv_pct}</span></td>
          </tr>
        `).join('');
      }
    } catch (err) {
      showToast(`Ошибка аналитики платформ: ${err.message}`, 'error');
    }
  }

  async function loadSiteAnalytics() {
    try {
      const res = await fetch(`api/site_analytics.php?period=${crmCurrentPeriod}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      // Устройства
      const elBadge = document.getElementById('siteTotalVisitsBadge');
      const elMBar = document.getElementById('siteMobileBar');
      const elDBar = document.getElementById('siteDesktopBar');
      const elMPct = document.getElementById('siteMobilePct');
      const elDPct = document.getElementById('siteDesktopPct');
      const elMCount = document.getElementById('siteMobileCount');
      const elDCount = document.getElementById('siteDesktopCount');

      if (elBadge) elBadge.textContent = `${data.total_visits || 0} визитов`;
      if (elMBar) elMBar.style.width = `${data.devices.mobile_pct || 50}%`;
      if (elDBar) elDBar.style.width = `${data.devices.desktop_pct || 50}%`;
      if (elMPct) elMPct.textContent = `${data.devices.mobile_pct || 0}%`;
      if (elDPct) elDPct.textContent = `${data.devices.desktop_pct || 0}%`;
      if (elMCount) elMCount.textContent = `(${data.devices.mobile || 0})`;
      if (elDCount) elDCount.textContent = `(${data.devices.desktop || 0})`;

      // Воронка скролла
      const sf = data.scroll_funnel || {};
      ['25', '50', '75', '100'].forEach(tier => {
        const item = sf[`depth_${tier}`] || { count: 0, pct: 0 };
        const fill = document.getElementById(`funnelFill${tier}`);
        const pct = document.getElementById(`funnelPct${tier}`);
        if (fill) fill.style.width = `${item.pct}%`;
        if (pct) pct.textContent = `${item.pct}% (${item.count})`;
      });

      // Топ кликов
      const elBtnBody = document.getElementById('siteButtonsTableBody');
      if (elBtnBody && Array.isArray(data.top_buttons)) {
        if (data.top_buttons.length === 0) {
          elBtnBody.innerHTML = '<tr><td colspan="5" class="text-center">Нет кликов за выбранный период</td></tr>';
        } else {
          const totalClicks = data.top_buttons.reduce((acc, b) => acc + (b.clicks_count || 0), 0);
          elBtnBody.innerHTML = data.top_buttons.map(b => {
            const share = totalClicks > 0 ? Math.round((b.clicks_count / totalClicks) * 100) : 0;
            return `
              <tr>
                <td><code class="tech-code">${escapeHtml(b.button_name)}</code></td>
                <td><strong>${b.clicks_count}</strong></td>
                <td><span style="color:#00E5B8;">${b.mobile_clicks || 0}</span></td>
                <td><span style="color:#3867d6;">${b.desktop_clicks || 0}</span></td>
                <td><span class="tech-tag">${share}%</span></td>
              </tr>
            `;
          }).join('');
        }
      }
    } catch (err) {
      showToast(`Ошибка веб-аналитики: ${err.message}`, 'error');
    }
  }

  // Слушатели периодов
  if (crmPeriodFilter) {
    crmPeriodFilter.addEventListener('click', async (e) => {
      const btn = e.target.closest('.period-btn');
      if (!btn) return;
      crmPeriodFilter.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      crmCurrentPeriod = btn.getAttribute('data-period') || 'all';
      await refreshActiveCrmSubtab();
    });
  }

  // Слушатели подвкладок CRM
  if (crmSubtabsNav) {
    crmSubtabsNav.addEventListener('click', async (e) => {
      const btn = e.target.closest('.crm-subtab-btn');
      if (!btn) return;
      crmSubtabsNav.querySelectorAll('.crm-subtab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      crmCurrentSubtab = btn.getAttribute('data-subtab') || 'kanban';

      if (crmSubpaneKanban) crmSubpaneKanban.style.display = crmCurrentSubtab === 'kanban' ? 'block' : 'none';
      if (crmSubpanePlatforms) crmSubpanePlatforms.style.display = crmCurrentSubtab === 'platforms' ? 'block' : 'none';
      if (crmSubpaneSite) crmSubpaneSite.style.display = crmCurrentSubtab === 'site' ? 'block' : 'none';

      await refreshActiveCrmSubtab();
    });
  }

  // Слушатели фильтра платформенной аналитики
  document.querySelectorAll('#paPlatformFilterPills .pa-pill').forEach(btn => {
    btn.addEventListener('click', async () => {
      document.querySelectorAll('#paPlatformFilterPills .pa-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const plat = btn.getAttribute('data-platform') || 'all';
      await loadPlatformAnalytics(plat);
    });
  });

  // Экспорт инсайтов платформенной аналитики в Базу Знаний (crm_memory)
  const exportInsightsBtn = document.getElementById('exportInsightsBtn');
  if (exportInsightsBtn) {
    exportInsightsBtn.addEventListener('click', async () => {
      exportInsightsBtn.disabled = true;
      exportInsightsBtn.innerHTML = '<span class="loader-spinner btn-sm"></span> Экспорт инсайтов...';
      try {
        const res = await fetch('api/crm.php?action=export_insights', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'export_insights' })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка экспорта');
        showToast('✓ Топ-инсайты сохранены в Базу Знаний (раздел crm_memory)!', 'success');
      } catch (err) {
        showToast(`Ошибка экспорта: ${err.message}`, 'error');
      } finally {
        exportInsightsBtn.disabled = false;
        exportInsightsBtn.innerHTML = '<span class="btn-icon">⚡</span> Экспортировать инсайты в Базу Знаний (CRM-память)';
      }
    });
  }

  // Добавление лида
  if (crmAddLeadBtn) {
    crmAddLeadBtn.addEventListener('click', () => {
      if (crmNewLeadModal) crmNewLeadModal.style.display = 'flex';
    });
  }
  if (crmLeadModalClose) {
    crmLeadModalClose.addEventListener('click', () => {
      if (crmNewLeadModal) crmNewLeadModal.style.display = 'none';
    });
  }
  if (crmSaveLeadBtn) {
    crmSaveLeadBtn.addEventListener('click', async () => {
      const name = crmLeadName ? crmLeadName.value.trim() : '';
      const tg = crmLeadTg ? crmLeadTg.value.trim() : '';
      const status = crmLeadStatus ? crmLeadStatus.value : 'new';
      const budget = crmLeadBudget ? crmLeadBudget.value : '25к-35к';
      const utm = crmLeadUtm ? crmLeadUtm.value.trim() : 'direct';
      const notes = crmLeadNotes ? crmLeadNotes.value.trim() : '';

      if (!name) {
        showToast('Укажите имя клиента или компанию', 'error');
        return;
      }

      try {
        crmSaveLeadBtn.disabled = true;
        const res = await fetch('api/crm.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'create_lead', full_name: name, telegram_username: tg, status, budget_range: budget, utm_source: utm, notes })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка создания лида');

        showToast('Лид добавлен в воронку', 'success');
        if (crmNewLeadModal) crmNewLeadModal.style.display = 'none';
        if (crmLeadName) crmLeadName.value = '';
        if (crmLeadTg) crmLeadTg.value = '';
        if (crmLeadNotes) crmLeadNotes.value = '';
        await loadKanbanData();
      } catch (err) {
        showToast(`Ошибка: ${err.message}`, 'error');
      } finally {
        crmSaveLeadBtn.disabled = false;
      }
    });
  }

  // ===========================================================================
  // 7. МОДУЛЬ: 📅 ИНТЕРАКТИВНЫЙ КОНТЕНТ-ПЛАН ПО EXCEL & РУБРИКАТОР (#calendar)
  // ===========================================================================
  let smmPlanItems = [];
  let smmStatusFilter = 'all';
  let calendarItems = [];
  let currentCalFilter = 'all';
  let isCalendarLoaded = false;
  let activeCalView = 'table';

  // SMM Table & Rubricator elements
  const calViewSwitcher = document.getElementById('calViewSwitcher');
  const smmTableViewContainer = document.getElementById('smmTableViewContainer');
  const calendarGridContainer = document.getElementById('calendarGridContainer');
  const smmTableBody = document.getElementById('smmTableBody');
  const smmStatusFilterPills = document.getElementById('smmStatusFilterPills');
  const smmAddRowBtn = document.getElementById('smmAddRowBtn');
  const smmTopAddRowBtn = document.getElementById('smmTopAddRowBtn');
  const smmAutoPlanBtn = document.getElementById('smmAutoPlanBtn');
  const rubricatorCardsGrid = document.getElementById('rubricatorCardsGrid');
  const openCreateRubricModalBtn = document.getElementById('openCreateRubricModalBtn');
  const rubricsCountBadge = document.getElementById('rubricsCountBadge');

  // Rubric Modal elements
  const rubricModal = document.getElementById('rubricModal');
  const rubricModalHeading = document.getElementById('rubricModalHeading');
  const rubricModalClose = document.getElementById('rubricModalClose');
  const rubricModalId = document.getElementById('rubricModalId');
  const rubricModalTitle = document.getElementById('rubricModalTitle');
  const rubricModalDesc = document.getElementById('rubricModalDesc');
  const rubricModalExamples = document.getElementById('rubricModalExamples');
  const rubricModalCancelBtn = document.getElementById('rubricModalCancelBtn');
  const rubricModalSaveBtn = document.getElementById('rubricModalSaveBtn');

  // Calendar Grid elements
  const calStatTotal = document.getElementById('calStatTotal');
  const calStatReady = document.getElementById('calStatReady');
  const calStatPlanned = document.getElementById('calStatPlanned');
  const calendarFilterPills = document.getElementById('calendarFilterPills');
  const calendarGridCells = document.getElementById('calendarGridCells');
  const calAddPostBtn = document.getElementById('calAddPostBtn');
  const calEditModal = document.getElementById('calEditModal');
  const calModalTitle = document.getElementById('calModalTitle');
  const calModalClose = document.getElementById('calModalClose');
  const calModalItemId = document.getElementById('calModalItemId');
  const calModalDate = document.getElementById('calModalDate');
  const calModalFormat = document.getElementById('calModalFormat');
  const calModalStatus = document.getElementById('calModalStatus');
  const calModalTopic = document.getElementById('calModalTopic');
  const calModalSaveBtn = document.getElementById('calModalSaveBtn');
  const calModalDeleteBtn = document.getElementById('calModalDeleteBtn');
  const calModalGoFactoryBtn = document.getElementById('calModalGoFactoryBtn');

  const SMM_FORMATS = [
    { key: 'post_tg', name: '💬 Текстовый пост' },
    { key: 'post_setka', name: '🌐 Пост для Сетки' },
    { key: 'article', name: '📝 Статья' },
    { key: 'reels', name: '📹 Видео/Reels' },
    { key: 'carousel', name: '🖼 Карусель' },
    { key: 'lead_magnet', name: '🧲 Лид-магнит' },
    { key: 'poll', name: '💬 Опрос' }
  ];

  let SMM_RUBRICS = [
    '🏎 Скорость и чистый код vs Конструкторы',
    '🛠 Кейсы и До/После',
    '💰 Бизнес и Конверсия',
    '🎯 Тарифы и Условия',
    '❓ FAQ и Отработка возражений'
  ];

  const FMT_NAMES = {
    tg_post: { label: 'TG', name: 'Telegram' },
    vc_ru: { label: 'VC', name: 'VC / Habr' },
    reels_hook: { label: 'REELS', name: 'Reels' },
    lead_magnet: { label: 'PDF', name: 'Лид-магнит' },
    site_geo: { label: 'SEO', name: 'SEO-статья' }
  };

  const WEEK_DAYS_RU = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
  const MONTHS_RU = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

  async function initCalendarTab(forceReload = false) {
    if (isCalendarLoaded && !forceReload) return;
    isCalendarLoaded = true;
    await Promise.all([loadSmmTable(), loadRubrics(), loadCalendarItems()]);
  }

  // Переключатель видов (Таблица SMM vs Сетка 30 дней)
  if (calViewSwitcher) {
    calViewSwitcher.addEventListener('click', (e) => {
      const btn = e.target.closest('.view-tab-btn');
      if (!btn) return;
      calViewSwitcher.querySelectorAll('.view-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeCalView = btn.getAttribute('data-view') || 'table';

      if (smmTableViewContainer) smmTableViewContainer.style.display = activeCalView === 'table' ? 'block' : 'none';
      if (calendarGridContainer) calendarGridContainer.style.display = activeCalView === 'grid' ? 'block' : 'none';
    });
  }

  // 1. ЗАГРУЗКА И ОТРИСОВКА SMM ТАБЛИЦЫ (EXCEL SMM MEMORY STONES)
  async function loadSmmTable() {
    if (!smmTableBody) return;
    try {
      const res = await fetch('api/calendar.php?action=list');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      smmPlanItems = data.items || [];
      renderSmmTable();
    } catch (err) {
      smmTableBody.innerHTML = `<tr><td colspan="8" class="text-center" style="color:#eb3b5a;">Ошибка: ${escapeHtml(err.message)}</td></tr>`;
    }
  }

  function renderSmmTable() {
    if (!smmTableBody) return;

    const filtered = smmStatusFilter === 'all'
      ? smmPlanItems
      : smmPlanItems.filter(i => i.status === smmStatusFilter);

    if (filtered.length === 0) {
      smmTableBody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding:24px;color:var(--color-text-subtle);">Нет строк по выбранному статусу</td></tr>';
      return;
    }

    smmTableBody.innerHTML = filtered.map(row => {
      const id = row.id;

      const formatOptions = SMM_FORMATS.map(f =>
        `<option value="${f.key}" ${row.format === f.key ? 'selected' : ''}>${f.name}</option>`
      ).join('');

      const rubricOptions = SMM_RUBRICS.map(r =>
        `<option value="${r}" ${row.rubric === r ? 'selected' : ''}>${r}</option>`
      ).join('');

      return `
        <tr data-row-id="${id}">
          <td>
            <input type="text" class="smm-input-clean smm-field" data-id="${id}" data-key="day_label" value="${escapeHtml(row.day_label || '')}" placeholder="1 день">
          </td>
          <td>
            <select class="smm-select-clean smm-field" data-id="${id}" data-key="format">
              ${formatOptions}
            </select>
          </td>
          <td>
            <select class="smm-select-clean smm-field" data-id="${id}" data-key="rubric">
              ${rubricOptions}
            </select>
          </td>
          <td>
            <input type="text" class="smm-input-clean smm-field" data-id="${id}" data-key="title" value="${escapeHtml(row.title || '')}" placeholder="Тема публикации...">
          </td>
          <td>
            <input type="text" class="smm-input-clean smm-field" data-id="${id}" data-key="comment" value="${escapeHtml(row.comment || '')}" placeholder="Пояснение / ТЗ...">
          </td>
          <td>
            <select class="smm-select-clean smm-field" data-id="${id}" data-key="status">
              <option value="idea" ${row.status === 'idea' ? 'selected' : ''}>⚪ Банк идей</option>
              <option value="in_progress" ${row.status === 'in_progress' ? 'selected' : ''}>🟡 В работе</option>
              <option value="ready" ${row.status === 'ready' ? 'selected' : ''}>🟢 Готово</option>
              <option value="published" ${row.status === 'published' ? 'selected' : ''}>🔵 Опубликовано</option>
            </select>
          </td>
          <td>
            <div class="smm-platforms-cell">
              <label class="smm-chk-label" title="Telegram">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_tg" ${row.platform_tg ? 'checked' : ''}>
                <span>TG</span>
              </label>
              <label class="smm-chk-label" title="VC.ru">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_vc" ${row.platform_vc ? 'checked' : ''}>
                <span>VC</span>
              </label>
              <label class="smm-chk-label" title="Habr">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_habr" ${row.platform_habr ? 'checked' : ''}>
                <span>Habr</span>
              </label>
              <label class="smm-chk-label" title="Instagram">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_inst" ${row.platform_inst ? 'checked' : ''}>
                <span>Inst</span>
              </label>
              <label class="smm-chk-label" title="Сетка (HeadHunter)">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_setka" ${row.platform_setka ? 'checked' : ''}>
                <span>Setka</span>
              </label>
              <label class="smm-chk-label" title="Сайт">
                <input type="checkbox" class="smm-chk-field" data-id="${id}" data-key="platform_site" ${row.platform_site ? 'checked' : ''}>
                <span>Сайт</span>
              </label>
            </div>
          </td>
          <td>
            <div class="smm-actions-cell">
              <button type="button" class="btn-smm-factory" data-id="${id}" title="Перенести тему в Контент-фабрику">⚡ В фабрику</button>
              <button type="button" class="btn-smm-del" data-id="${id}" title="Удалить строку">&times;</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    // Привязываем слушатели автосохранения ячеек
    smmTableBody.querySelectorAll('.smm-field').forEach(input => {
      input.addEventListener('change', async () => {
        const id = parseInt(input.getAttribute('data-id'), 10);
        const key = input.getAttribute('data-key');
        const val = input.value;
        await saveSmmField(id, { [key]: val });
      });
    });

    smmTableBody.querySelectorAll('.smm-chk-field').forEach(chk => {
      chk.addEventListener('change', async () => {
        const id = parseInt(chk.getAttribute('data-id'), 10);
        const key = chk.getAttribute('data-key');
        const val = chk.checked ? 1 : 0;
        await saveSmmField(id, { [key]: val });
      });
    });

    // Кнопка переноса в фабрику
    smmTableBody.querySelectorAll('.btn-smm-factory').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-id'), 10);
        const row = smmPlanItems.find(r => r.id === id);
        if (row) {
          transferSmmToFactory(row);
        }
      });
    });

    // Кнопка удаления строки
    smmTableBody.querySelectorAll('.btn-smm-del').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = parseInt(btn.getAttribute('data-id'), 10);
        if (!confirm('Удалить эту строку из контент-плана?')) return;
        try {
          const res = await fetch('api/calendar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
          });
          const data = await res.json();
          if (!res.ok || data.status !== 'success') throw new Error(data.message);
          showToast('Строка удалена', 'info');
          await loadSmmTable();
        } catch (err) {
          showToast(`Ошибка: ${err.message}`, 'error');
        }
      });
    });
  }

  async function saveSmmField(id, patch) {
    try {
      const payload = Object.assign({ action: 'update', id }, patch);
      const res = await fetch('api/calendar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') throw new Error(data.message);

      // Обновляем локальный кэш
      const local = smmPlanItems.find(i => i.id === id);
      if (local) Object.assign(local, patch);
    } catch (err) {
      showToast(`Ошибка сохранения: ${err.message}`, 'error');
    }
  }

  function transferSmmToFactory(row) {
    if (!row) return;

    if (factoryTopicInputEl) {
      factoryTopicInputEl.value = row.title || '';
    } else if (factoryTopicInput) {
      factoryTopicInput.value = row.title || '';
    }

    const fmtMap = {
      post_tg: 'telegram',
      post_setka: 'setka',
      article: 'article',
      reels: 'instagram',
      lead_magnet: 'lead_magnet'
    };
    const targetMode = fmtMap[row.format] || 'all';

    const modeBtn = document.querySelector(`.mode-tab-btn[data-mode="${targetMode}"]`);
    if (modeBtn) modeBtn.click();

    switchTab('factory');
    showToast(`Тема «${(row.title || '').substring(0, 35)}...» перенесена в Контент-фабрику`, 'info');

    // Автоматический запуск генерации
    const startBtn = document.getElementById('generateBtn') || document.getElementById('startGenerationBtn');
    if (startBtn && !startBtn.disabled) {
      setTimeout(() => {
        startBtn.click();
      }, 150);
    }
  }

  async function addSmmRow(preset = {}) {
    try {
      const defaultDay = `${smmPlanItems.length + 1} день`;
      const payload = Object.assign({
        action: 'create',
        day_label: defaultDay,
        format: 'post_tg',
        rubric: SMM_RUBRICS[0] || '🏎 Скорость и чистый код',
        title: 'Новая тема публикации',
        comment: '',
        status: 'idea',
        platform_tg: 1
      }, preset);

      const res = await fetch('api/calendar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') throw new Error(data.message);

      showToast('Строка добавлена в контент-план', 'success');
      await loadSmmTable();
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    }
  }

  if (smmAddRowBtn) smmAddRowBtn.addEventListener('click', () => addSmmRow());
  if (smmTopAddRowBtn) smmTopAddRowBtn.addEventListener('click', () => addSmmRow());

  // Авто-проектирование плана на 1-2 дня через Flash-Lite
  if (smmAutoPlanBtn) {
    smmAutoPlanBtn.addEventListener('click', async () => {
      const originalHtml = smmAutoPlanBtn.innerHTML;
      smmAutoPlanBtn.disabled = true;
      smmAutoPlanBtn.innerHTML = '<span class="btn-icon">↺</span> ИИ анализирует тренды и собирает сетку на 1-2 дня...';

      try {
        const res = await fetch('api/calendar.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'auto_plan' })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка генерации авто-плана');

        showToast(`Спроектировано ${data.count || 0} публикаций на 1-2 дня!`, 'success');
        await loadSmmTable();
      } catch (err) {
        showToast(`Ошибка авто-плана: ${err.message}`, 'error');
      } finally {
        smmAutoPlanBtn.disabled = false;
        smmAutoPlanBtn.innerHTML = originalHtml;
      }
    });
  }

  // Фильтры статуса в SMM таблице
  if (smmStatusFilterPills) {
    smmStatusFilterPills.addEventListener('click', (e) => {
      const btn = e.target.closest('.smm-pill');
      if (!btn) return;
      smmStatusFilterPills.querySelectorAll('.smm-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      smmStatusFilter = btn.getAttribute('data-status') || 'all';
      renderSmmTable();
    });
  }

  let currentRubricsList = [];

  function openRubricModal(rubric = null) {
    if (!rubricModal) return;
    if (rubric) {
      if (rubricModalHeading) rubricModalHeading.textContent = 'Редактировать рубрику';
      if (rubricModalId) rubricModalId.value = rubric.id || 0;
      if (rubricModalTitle) rubricModalTitle.value = rubric.title || rubric.name || '';
      if (rubricModalDesc) rubricModalDesc.value = rubric.description || rubric.desc || '';
      if (rubricModalExamples) {
        const exs = Array.isArray(rubric.examples) ? rubric.examples : [];
        rubricModalExamples.value = exs.join('\n');
      }
    } else {
      if (rubricModalHeading) rubricModalHeading.textContent = 'Создать новую рубрику';
      if (rubricModalId) rubricModalId.value = '0';
      if (rubricModalTitle) rubricModalTitle.value = '';
      if (rubricModalDesc) rubricModalDesc.value = '';
      if (rubricModalExamples) rubricModalExamples.value = '';
    }
    rubricModal.style.display = 'flex';
  }

  function closeRubricModal() {
    if (rubricModal) rubricModal.style.display = 'none';
  }

  if (openCreateRubricModalBtn) {
    openCreateRubricModalBtn.addEventListener('click', () => openRubricModal());
  }
  if (rubricModalClose) {
    rubricModalClose.addEventListener('click', closeRubricModal);
  }
  if (rubricModalCancelBtn) {
    rubricModalCancelBtn.addEventListener('click', closeRubricModal);
  }

  if (rubricModalSaveBtn) {
    rubricModalSaveBtn.addEventListener('click', async () => {
      const id = parseInt(rubricModalId ? rubricModalId.value : '0', 10);
      const title = rubricModalTitle ? rubricModalTitle.value.trim() : '';
      const description = rubricModalDesc ? rubricModalDesc.value.trim() : '';
      const rawExamples = rubricModalExamples ? rubricModalExamples.value.trim() : '';

      if (!title) {
        showToast('Укажите название рубрики', 'error');
        if (rubricModalTitle) rubricModalTitle.focus();
        return;
      }

      const examples = rawExamples
        ? rawExamples.split('\n').map(s => s.trim()).filter(s => s.length > 0)
        : [];

      rubricModalSaveBtn.disabled = true;
      try {
        const res = await fetch('api/rubrics.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'save', id, title, description, examples })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка сохранения рубрики');

        showToast(id > 0 ? 'Рубрика обновлена' : 'Рубрика создана', 'success');
        closeRubricModal();
        await loadRubrics();
        renderSmmTable();
      } catch (err) {
        showToast(`Ошибка: ${err.message}`, 'error');
      } finally {
        rubricModalSaveBtn.disabled = false;
      }
    });
  }

  // 2. ЗАГРУЗКА РУБРИКАТОРА
  async function loadRubrics() {
    if (!rubricatorCardsGrid) return;
    try {
      const res = await fetch('api/rubrics.php?action=list');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      const rubrics = data.rubrics || [];
      currentRubricsList = rubrics;

      if (rubrics.length > 0) {
        SMM_RUBRICS = rubrics.map(r => r.title || r.name);
      }

      if (rubricsCountBadge) {
        rubricsCountBadge.textContent = `${rubrics.length} СМЫСЛОВЫХ РУБРИК`;
      }

      rubricatorCardsGrid.innerHTML = rubrics.map(r => `
        <div class="rubric-card" data-rubric-id="${r.id}">
          <div class="rubric-card-header">
            <span class="rubric-card-name">${escapeHtml(r.title || r.name)}</span>
            <div class="rubric-header-actions">
              <button type="button" class="btn-rubric-action btn-rubric-edit" data-id="${r.id}" title="Редактировать рубрику">✏️</button>
              <button type="button" class="btn-rubric-action btn-rubric-del" data-id="${r.id}" data-title="${escapeHtml(r.title || r.name)}" title="Удалить рубрику">🗑</button>
            </div>
          </div>
          <p class="rubric-card-desc">${escapeHtml(r.description || r.desc || '')}</p>
          <div class="rubric-examples-list">
            ${(r.examples || []).map(ex => `
              <div class="rubric-example-item">
                <span>${escapeHtml(ex)}</span>
                <button type="button" class="rubric-add-btn" data-rubric="${escapeHtml(r.title || r.name)}" data-title="${escapeHtml(ex)}">+ В план</button>
              </div>
            `).join('')}
          </div>
        </div>
      `).join('');

      // Кнопки добавления темы в контент-план
      rubricatorCardsGrid.querySelectorAll('.rubric-add-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const rub = btn.getAttribute('data-rubric');
          const title = btn.getAttribute('data-title');
          await addSmmRow({
            rubric: rub,
            title: title,
            day_label: `${smmPlanItems.length + 1} день`,
            status: 'in_progress',
            platform_tg: 1,
            platform_vc: 1
          });
          if (smmTableViewContainer) {
            window.scrollTo({ top: smmTableViewContainer.offsetTop - 80, behavior: 'smooth' });
          }
        });
      });

      // Кнопки редактирования рубрики
      rubricatorCardsGrid.querySelectorAll('.btn-rubric-edit').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = parseInt(btn.getAttribute('data-id'), 10);
          const rub = currentRubricsList.find(item => item.id === id);
          if (rub) {
            openRubricModal(rub);
          }
        });
      });

      // Кнопки удаления рубрики
      rubricatorCardsGrid.querySelectorAll('.btn-rubric-del').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.getAttribute('data-id'), 10);
          const title = btn.getAttribute('data-title') || 'рубрику';
          if (!confirm(`Удалить рубрику «${title}»?`)) return;

          try {
            const res = await fetch('api/rubrics.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'delete', id })
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка удаления');

            showToast(`Рубрика «${title}» удалена`, 'info');
            await loadRubrics();
            renderSmmTable();
          } catch (err) {
            showToast(`Ошибка: ${err.message}`, 'error');
          }
        });
      });

    } catch (err) {
      console.warn('Rubrics load warning:', err.message);
    }
  }

  // 3. СЕТКА 30 ДНЕЙ (МЕСЯЦ)
  async function loadCalendarItems() {
    if (!calendarGridCells) return;
    try {
      const response = await fetch('api/content.php?action=calendar_list');
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      if (data.status === 'success') {
        calendarItems = data.items || [];
        renderCalendarGrid();
      }
    } catch (err) {
      calendarGridCells.innerHTML = `<div class="calendar-loading-hint" style="color:#eb3b5a;">Ошибка: ${escapeHtml(err.message)}</div>`;
    }
  }

  function renderCalendarGrid() {
    if (!calendarGridCells) return;

    const total = calendarItems.length;
    const readyOrPublished = calendarItems.filter(i => i.status === 'ready' || i.status === 'published').length;
    const planned = calendarItems.filter(i => i.status === 'planned').length;

    if (calStatTotal) calStatTotal.textContent = total;
    if (calStatReady) calStatReady.textContent = readyOrPublished;
    if (calStatPlanned) calStatPlanned.textContent = planned;

    const filtered = currentCalFilter === 'all'
      ? calendarItems
      : calendarItems.filter(i => i.format === currentCalFilter);

    if (filtered.length === 0) {
      calendarGridCells.innerHTML = '<div class="calendar-loading-hint">Нет публикаций по выбранному фильтру.</div>';
      return;
    }

    const todayStr = new Date().toISOString().split('T')[0];

    calendarGridCells.innerHTML = filtered.map(item => {
      const isToday = item.scheduled_for === todayStr;
      const fmtInfo = FMT_NAMES[item.format] || { label: 'DOC', name: item.format };

      let dateLabel = item.scheduled_for || '';
      let wdayLabel = '';
      if (item.scheduled_for) {
        const d = new Date(item.scheduled_for + 'T00:00:00');
        if (!isNaN(d.getTime())) {
          dateLabel = `${d.getDate()} ${MONTHS_RU[d.getMonth()]}`;
          wdayLabel = WEEK_DAYS_RU[d.getDay()];
        }
      }

      const statusMap = {
        planned: 'Запланирован',
        ready: 'Готов',
        published: 'Опубликован',
        archived: 'Архив'
      };

      return `
        <div class="cal-day-cell ${isToday ? 'today' : ''}" data-id="${item.id}">
          <div class="cal-day-header">
            <span class="cal-day-date">${dateLabel}</span>
            <span class="cal-day-wday">${wdayLabel}</span>
          </div>
          <div class="cal-card-box" data-id="${item.id}">
            <div class="cal-card-top">
              <span class="cal-fmt-badge ${item.format}">${fmtInfo.label}</span>
              <span class="cal-status-dot ${item.status}" title="${statusMap[item.status] || item.status}"></span>
            </div>
            <div class="cal-topic-text" title="${escapeHtml(item.topic)}">${escapeHtml(item.topic)}</div>
            <div class="cal-card-actions">
              <span style="font-size: 10px; color: var(--color-text-subtle);">${statusMap[item.status] || item.status}</span>
              <button type="button" class="cal-btn-factory" data-id="${item.id}" title="Открыть тему в Контент-фабрике">⚡ В фабрику</button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    calendarGridCells.querySelectorAll('.cal-card-box').forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('.cal-btn-factory')) return;
        const id = parseInt(card.getAttribute('data-id'), 10);
        const item = calendarItems.find(i => i.id === id);
        if (item) openCalModal(item);
      });
    });

    calendarGridCells.querySelectorAll('.cal-btn-factory').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = parseInt(btn.getAttribute('data-id'), 10);
        const item = calendarItems.find(i => i.id === id);
        if (item) transferToFactory(item);
      });
    });
  }

  function openCalModal(item = null, defaultDate = null) {
    if (!calEditModal) return;
    if (item) {
      if (calModalTitle) calModalTitle.textContent = 'Редактирование публикации';
      if (calModalItemId) calModalItemId.value = item.id;
      if (calModalDate) calModalDate.value = item.scheduled_for || '';
      if (calModalFormat) calModalFormat.value = item.format || 'tg_post';
      if (calModalStatus) calModalStatus.value = item.status || 'planned';
      if (calModalTopic) calModalTopic.value = item.topic || '';
      if (calModalDeleteBtn) calModalDeleteBtn.style.display = 'inline-block';
      if (calModalGoFactoryBtn) calModalGoFactoryBtn.style.display = 'inline-block';
    } else {
      if (calModalTitle) calModalTitle.textContent = 'Новая публикация в сетке';
      if (calModalItemId) calModalItemId.value = '';
      if (calModalDate) calModalDate.value = defaultDate || new Date().toISOString().split('T')[0];
      if (calModalFormat) calModalFormat.value = 'tg_post';
      if (calModalStatus) calModalStatus.value = 'planned';
      if (calModalTopic) calModalTopic.value = '';
      if (calModalDeleteBtn) calModalDeleteBtn.style.display = 'none';
      if (calModalGoFactoryBtn) calModalGoFactoryBtn.style.display = 'none';
    }
    calEditModal.style.display = 'flex';
  }

  function closeCalModal() {
    if (calEditModal) calEditModal.style.display = 'none';
  }

  async function saveCalModalItem() {
    const id = calModalItemId ? parseInt(calModalItemId.value, 10) : 0;
    const date = calModalDate ? calModalDate.value.trim() : '';
    const format = calModalFormat ? calModalFormat.value : 'tg_post';
    const status = calModalStatus ? calModalStatus.value : 'planned';
    const topic = calModalTopic ? calModalTopic.value.trim().replace(/[—–]/g, '-') : '';

    if (!topic) {
      showToast('Укажите тему публикации', 'error');
      return;
    }

    try {
      calModalSaveBtn.disabled = true;
      let url = 'api/content.php';
      let payload = id > 0
        ? { action: 'calendar_update', id, topic, scheduled_for: date, format, status }
        : { action: 'calendar_create', topic, scheduled_for: date, format, status };

      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') throw new Error(data.message);

      showToast(id > 0 ? 'Публикация обновлена' : 'Публикация добавлена', 'success');
      closeCalModal();
      await loadCalendarItems();
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    } finally {
      calModalSaveBtn.disabled = false;
    }
  }

  async function deleteCalModalItem() {
    const id = calModalItemId ? parseInt(calModalItemId.value, 10) : 0;
    if (id <= 0) return;
    if (!confirm('Удалить эту публикацию?')) return;

    try {
      calModalDeleteBtn.disabled = true;
      const res = await fetch('api/content.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'calendar_delete', id })
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') throw new Error(data.message);
      showToast('Публикация удалена', 'info');
      closeCalModal();
      await loadCalendarItems();
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    } finally {
      calModalDeleteBtn.disabled = false;
    }
  }

  function transferToFactory(item) {
    if (!item) return;
    closeCalModal();
    if (factoryTopicInputEl) factoryTopicInputEl.value = item.topic;
    else if (factoryTopicInput) factoryTopicInput.value = item.topic;

    const formatModeMap = {
      tg_post: 'telegram',
      vc_ru: 'article',
      reels_hook: 'instagram',
      lead_magnet: 'lead_magnet'
    };
    const targetMode = formatModeMap[item.format] || 'all';
    const modeBtn = document.querySelector(`.mode-tab-btn[data-mode="${targetMode}"]`);
    if (modeBtn) modeBtn.click();

    switchTab('factory');
    showToast(`Тема «${item.topic.substring(0, 35)}...» перенесена в Контент-фабрику`, 'info');

    const startBtn = document.getElementById('generateBtn') || document.getElementById('startGenerationBtn');
    if (startBtn && !startBtn.disabled) {
      setTimeout(() => {
        startBtn.click();
      }, 150);
    }
  }

  if (calendarFilterPills) {
    calendarFilterPills.addEventListener('click', (e) => {
      const btn = e.target.closest('.cal-filter-btn');
      if (!btn) return;
      calendarFilterPills.querySelectorAll('.cal-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentCalFilter = btn.getAttribute('data-fmt') || 'all';
      renderCalendarGrid();
    });
  }

  if (calAddPostBtn) calAddPostBtn.addEventListener('click', () => openCalModal(null));
  if (calModalClose) calModalClose.addEventListener('click', closeCalModal);
  if (calModalSaveBtn) calModalSaveBtn.addEventListener('click', saveCalModalItem);
  if (calModalDeleteBtn) calModalDeleteBtn.addEventListener('click', deleteCalModalItem);
  if (calModalGoFactoryBtn) {
    calModalGoFactoryBtn.addEventListener('click', () => {
      const id = calModalItemId ? parseInt(calModalItemId.value, 10) : 0;
      const item = calendarItems.find(i => i.id === id);
      if (item) transferToFactory(item);
    });
  }

  // ===========================================================================
  // 8. МОДУЛЬ: 🤖 ТГ-БОТ & LIVE MESSENGER (#bot)
  // ===========================================================================
  let activeBotLeadId = null;
  let botLeadsData = [];
  let isBotLoaded = false;

  const botLeadsList = document.getElementById('botLeadsList');
  const botRefreshChatsBtn = document.getElementById('botRefreshChatsBtn');
  const botUnreadTotalBadge = document.getElementById('botUnreadTotalBadge');
  const chatLeadName = document.getElementById('chatLeadName');
  const chatLeadMeta = document.getElementById('chatLeadMeta');
  const chatLeadStatusBadge = document.getElementById('chatLeadStatusBadge');
  const chatLeadBudgetBadge = document.getElementById('chatLeadBudgetBadge');
  const chatMessagesStream = document.getElementById('chatMessagesStream');
  const chatCorrectionDrawer = document.getElementById('chatCorrectionDrawer');
  const closeCorrectionDrawerBtn = document.getElementById('closeCorrectionDrawerBtn');
  const correctionBadPreview = document.getElementById('correctionBadPreview');
  const correctionInputText = document.getElementById('correctionInputText');
  const correctionUserPrompt = document.getElementById('correctionUserPrompt');
  const correctionBadResponse = document.getElementById('correctionBadResponse');
  const saveCorrectionBtn = document.getElementById('saveCorrectionBtn');
  const operatorMessageInput = document.getElementById('operatorMessageInput');
  const sendOperatorMsgBtn = document.getElementById('sendOperatorMsgBtn');

  async function initBotTab(forceReload = false) {
    if (isBotLoaded && !forceReload) return;
    isBotLoaded = true;
    await loadBotLeads();
  }

  async function loadBotLeads() {
    if (!botLeadsList) return;
    botLeadsList.innerHTML = '<div class="messenger-loading-hint">Загрузка диалогов...</div>';

    try {
      const res = await fetch('api/bot.php?action=leads_list');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      botLeadsData = data.leads || [];
      if (botUnreadTotalBadge) {
        botUnreadTotalBadge.textContent = `${botLeadsData.length} диалогов`;
      }

      if (botLeadsData.length === 0) {
        botLeadsList.innerHTML = '<div class="messenger-loading-hint">Нет активных диалогов</div>';
        return;
      }

      botLeadsList.innerHTML = botLeadsData.map(lead => {
        const isActive = activeBotLeadId === lead.id;
        const timeStr = lead.last_message_at ? lead.last_message_at.substring(11, 16) : '';
        return `
          <div class="messenger-lead-item ${isActive ? 'active' : ''}" data-lead-id="${lead.id}">
            <div class="mli-top">
              <span class="mli-name">${escapeHtml(lead.full_name || 'Клиент')}</span>
              <span class="mli-time">${timeStr}</span>
            </div>
            <div class="mli-snippet">${escapeHtml(lead.last_message || 'Нет сообщений')}</div>
          </div>
        `;
      }).join('');

      // Привязываем клики по диалогам
      botLeadsList.querySelectorAll('.messenger-lead-item').forEach(item => {
        item.addEventListener('click', () => {
          const id = parseInt(item.getAttribute('data-lead-id'), 10);
          selectBotLead(id);
        });
      });

      // Автоматически открываем первый диалог, если ничего не выбрано
      if (!activeBotLeadId && botLeadsData.length > 0) {
        selectBotLead(botLeadsData[0].id);
      }
    } catch (err) {
      botLeadsList.innerHTML = `<div class="messenger-loading-hint" style="color:#eb3b5a;">Ошибка: ${escapeHtml(err.message)}</div>`;
    }
  }

  async function selectBotLead(leadId) {
    activeBotLeadId = leadId;

    if (botLeadsList) {
      botLeadsList.querySelectorAll('.messenger-lead-item').forEach(el => {
        if (parseInt(el.getAttribute('data-lead-id'), 10) === leadId) {
          el.classList.add('active');
        } else {
          el.classList.remove('active');
        }
      });
    }

    if (chatCorrectionDrawer) chatCorrectionDrawer.style.display = 'none';

    try {
      const res = await fetch(`api/bot.php?action=messages&lead_id=${leadId}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.message);

      const lead = data.lead || {};
      if (chatLeadName) chatLeadName.textContent = lead.full_name || 'Клиент';
      if (chatLeadMeta) chatLeadMeta.textContent = lead.telegram_username ? `@${lead.telegram_username} · UTM: ${lead.utm_source || 'direct'}` : 'Telegram контакт';
      if (chatLeadStatusBadge) chatLeadStatusBadge.textContent = (lead.status || 'NEW').toUpperCase();
      if (chatLeadBudgetBadge) chatLeadBudgetBadge.textContent = lead.budget_range || 'Тариф 25к';

      renderChatMessages(data.messages || []);
    } catch (err) {
      showToast(`Ошибка загрузки сообщений: ${err.message}`, 'error');
    }
  }

  function scrollChatToBottom() {
    const container = document.querySelector('.chat-messages-container') || document.getElementById('chatMessagesStream');
    if (container) {
      container.scrollTop = container.scrollHeight;
    }
  }
  window.scrollChatToBottom = scrollChatToBottom;

  function renderChatMessages(messages) {
    if (!chatMessagesStream) return;

    if (messages.length === 0) {
      chatMessagesStream.innerHTML = '<div class="chat-empty-hint">История переписки пуста. Напишите первое сообщение клиенту.</div>';
      return;
    }

    chatMessagesStream.innerHTML = messages.map(msg => {
      const isUser = msg.sender === 'user';
      const isBot = msg.sender === 'bot';
      const isOperator = msg.sender === 'operator';
      const time = msg.created_at ? msg.created_at.substring(11, 16) : '';

      let senderClass = 'bubble-user';
      let senderTitle = 'Клиент';
      if (isBot) {
        senderClass = 'bubble-bot';
        senderTitle = 'Бот AntiDesigner';
      } else if (isOperator) {
        senderClass = 'bubble-operator';
        senderTitle = 'Оператор Евгений';
      }

      return `
        <div class="chat-bubble ${senderClass}">
          <span class="bubble-sender-name">${senderTitle}</span>
          <div class="bubble-text">${escapeHtml(msg.text)}</div>
          <div class="bubble-meta">
            ${isBot ? `<button type="button" class="btn-bubble-correct" data-bad="${escapeHtml(msg.text)}">💡 Скорректировать ответ</button>` : ''}
            <span class="bubble-time">${time}</span>
          </div>
        </div>
      `;
    }).join('');

    scrollChatToBottom();
    setTimeout(() => scrollChatToBottom(), 50);

    // Слушатели кнопки коррекции ответа бота
    chatMessagesStream.querySelectorAll('.btn-bubble-correct').forEach(btn => {
      btn.addEventListener('click', () => {
        const badText = btn.getAttribute('data-bad') || '';
        openCorrectionDrawer(badText);
      });
    });
  }

  function openCorrectionDrawer(badResponseText) {
    if (!chatCorrectionDrawer) return;

    // Ищем последнее сообщение пользователя перед этим
    if (correctionUserPrompt) correctionUserPrompt.value = 'Запрос клиента';
    if (correctionBadResponse) correctionBadResponse.value = badResponseText;
    if (correctionBadPreview) correctionBadPreview.textContent = badResponseText;
    if (correctionInputText) {
      correctionInputText.value = '';
      correctionInputText.focus();
    }

    chatCorrectionDrawer.style.display = 'flex';
  }

  if (closeCorrectionDrawerBtn) {
    closeCorrectionDrawerBtn.addEventListener('click', () => {
      if (chatCorrectionDrawer) chatCorrectionDrawer.style.display = 'none';
    });
  }

  if (saveCorrectionBtn) {
    saveCorrectionBtn.addEventListener('click', async () => {
      const goodText = correctionInputText ? correctionInputText.value.trim() : '';
      const badText = correctionBadResponse ? correctionBadResponse.value : '';
      const promptText = correctionUserPrompt ? correctionUserPrompt.value : '';

      if (!goodText) {
        showToast('Введите эталонный правильный ответ', 'error');
        return;
      }

      try {
        saveCorrectionBtn.disabled = true;
        const res = await fetch('api/bot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'save_correction',
            user_prompt: promptText,
            bad_bot_response: badText,
            human_correction: goodText
          })
        });

        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message);

        showToast('Эталон сохранен в RLHF-память бота!', 'success');
        if (chatCorrectionDrawer) chatCorrectionDrawer.style.display = 'none';
      } catch (err) {
        showToast(`Ошибка: ${err.message}`, 'error');
      } finally {
        saveCorrectionBtn.disabled = false;
      }
    });
  }

  // Быстрые шаблоны
  document.querySelectorAll('.quick-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const text = chip.getAttribute('data-text');
      if (operatorMessageInput && text) {
        operatorMessageInput.value = text;
        operatorMessageInput.focus();
      }
    });
  });

  // Отправка ответа оператора
  if (sendOperatorMsgBtn) {
    sendOperatorMsgBtn.addEventListener('click', async () => {
      if (!activeBotLeadId) {
        showToast('Сначала выберите диалог слева', 'error');
        return;
      }
      const text = operatorMessageInput ? operatorMessageInput.value.trim() : '';
      if (!text) {
        showToast('Введите текст ответа', 'error');
        return;
      }

      try {
        sendOperatorMsgBtn.disabled = true;
        const res = await fetch('api/bot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'send',
            lead_id: activeBotLeadId,
            text: text,
            sender: 'operator'
          })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message);

        if (operatorMessageInput) operatorMessageInput.value = '';
        showToast('Ответ отправлен клиенту', 'success');
        await selectBotLead(activeBotLeadId);
        scrollChatToBottom();
        await loadBotLeads();
      } catch (err) {
        showToast(`Ошибка отправки: ${err.message}`, 'error');
      } finally {
        sendOperatorMsgBtn.disabled = false;
      }
    });
  }

  if (operatorMessageInput) {
    operatorMessageInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (sendOperatorMsgBtn) sendOperatorMsgBtn.click();
      }
    });
  }

  if (botRefreshChatsBtn) {
    botRefreshChatsBtn.addEventListener('click', () => loadBotLeads());
  }

  // Переключение подразделов ТГ-Ассистента (Мессенджер vs Конфигуратор VPS)
  const botSubtabsNav = document.getElementById('botSubtabsNav');
  const botSubpaneMessenger = document.getElementById('botSubpaneMessenger');
  const botSubpaneVps = document.getElementById('botSubpaneVps');

  if (botSubtabsNav) {
    botSubtabsNav.addEventListener('click', async (e) => {
      const btn = e.target.closest('.view-tab-btn');
      if (!btn) return;
      botSubtabsNav.querySelectorAll('.view-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const sub = btn.getAttribute('data-botsub') || 'messenger';
      if (botSubpaneMessenger) botSubpaneMessenger.style.display = sub === 'messenger' ? 'block' : 'none';
      if (botSubpaneVps) botSubpaneVps.style.display = sub === 'vps' ? 'block' : 'none';
      if (sub === 'vps') {
        await loadVpsConfig();
      }
    });
  }

  async function loadVpsConfig() {
    try {
      const res = await fetch('api/bot.php?action=get_config');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status === 'success' && data.config) {
        const c = data.config;
        const elPrompt = document.getElementById('vpsSystemPrompt');
        const elTone = document.getElementById('vpsToneOfVoice');
        const elMin = document.getElementById('vpsDelayMin');
        const elMax = document.getElementById('vpsDelayMax');
        const elBl = document.getElementById('vpsBlacklistPhones');
        const elId = document.getElementById('vpsApiId');
        const elHash = document.getElementById('vpsApiHash');

        if (elPrompt && c.system_prompt) elPrompt.value = c.system_prompt;
        if (elTone && c.tone_of_voice) elTone.value = c.tone_of_voice;
        if (elMin && c.typing_delay_min) elMin.value = c.typing_delay_min;
        if (elMax && c.typing_delay_max) elMax.value = c.typing_delay_max;
        if (elBl && c.blacklist_phones) elBl.value = c.blacklist_phones;
        if (elId && c.api_id) elId.value = c.api_id;
        if (elHash && c.api_hash) elHash.value = c.api_hash;
      }
    } catch (err) {
      console.warn('Error loading VPS config:', err);
    }
  }

  const vpsSaveConfigBtn = document.getElementById('vpsSaveConfigBtn');
  if (vpsSaveConfigBtn) {
    vpsSaveConfigBtn.addEventListener('click', async () => {
      vpsSaveConfigBtn.disabled = true;
      vpsSaveConfigBtn.innerHTML = '<span class="loader-spinner btn-sm"></span> Сохранение...';
      try {
        const payload = {
          action: 'save_config',
          system_prompt: (document.getElementById('vpsSystemPrompt')?.value || '').trim(),
          tone_of_voice: (document.getElementById('vpsToneOfVoice')?.value || '').trim(),
          typing_delay_min: parseInt(document.getElementById('vpsDelayMin')?.value || '3', 10),
          typing_delay_max: parseInt(document.getElementById('vpsDelayMax')?.value || '7', 10),
          blacklist_phones: (document.getElementById('vpsBlacklistPhones')?.value || '').trim(),
          api_id: (document.getElementById('vpsApiId')?.value || '').trim(),
          api_hash: (document.getElementById('vpsApiHash')?.value || '').trim()
        };

        const res = await fetch('api/bot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Ошибка сохранения');
        showToast('✓ Настройки ассистента успешно сохранены', 'success');
      } catch (err) {
        showToast(`Ошибка: ${err.message}`, 'error');
      } finally {
        vpsSaveConfigBtn.disabled = false;
        vpsSaveConfigBtn.innerHTML = '<span class="btn-icon">💾</span> Сохранить настройки';
      }
    });
  }

  const vpsTestMockBtn = document.getElementById('vpsTestMockBtn');
  const vpsSandboxConsole = document.getElementById('vpsSandboxConsole');
  const sandboxConsoleBody = document.getElementById('sandboxConsoleBody');
  const closeSandboxConsoleBtn = document.getElementById('closeSandboxConsoleBtn');

  if (closeSandboxConsoleBtn && vpsSandboxConsole) {
    closeSandboxConsoleBtn.addEventListener('click', () => {
      vpsSandboxConsole.style.display = 'none';
    });
  }

  if (vpsTestMockBtn) {
    vpsTestMockBtn.addEventListener('click', async () => {
      if (vpsSandboxConsole) vpsSandboxConsole.style.display = 'block';
      if (sandboxConsoleBody) {
        sandboxConsoleBody.innerHTML = `
          <div class="console-line info">[INIT] Инициализация Mock Sandbox (без сессии Telegram и без расхода токенов)...</div>
          <div class="console-line">[1/4] Имитация входящего вопроса от лида: "Здравствуйте! Сколько стоит сайт на чистом коде? Сайт на Тильде грузится 5 секунд."</div>
        `;
      }

      try {
        vpsTestMockBtn.disabled = true;
        const res = await fetch('api/bot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'test_mock' })
        });
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message);

        if (sandboxConsoleBody) {
          sandboxConsoleBody.innerHTML += `
            <div class="console-line success">[2/4] Проверка черного списка: ${escapeHtml(data.blacklist_status)}</div>
            <div class="console-line info">[3/4] Анализ коммерческого намерения: Интент подтвержден (ключевое слово: '${escapeHtml(data.matched_keyword)}')</div>
            <div class="console-line warning">[4/4] Имитация поведения человека: ${escapeHtml(data.simulated_delay)}</div>
            <div class="console-line success" style="margin-top:8px;">[BOT RESPONSE] ${escapeHtml(data.bot_response)}</div>
            <div class="console-line" style="color:#00E5B8;font-weight:700;margin-top:6px;">✓ СИМУЛЯЦИЯ ЗАВЕРШЕНА: Скрипт готов к боевому запуску!</div>
          `;
          sandboxConsoleBody.scrollTop = sandboxConsoleBody.scrollHeight;
        }
      } catch (err) {
        if (sandboxConsoleBody) {
          sandboxConsoleBody.innerHTML += `<div class="console-line error">[ERROR] ${escapeHtml(err.message)}</div>`;
        }
      } finally {
        vpsTestMockBtn.disabled = false;
      }
    });
  }

  const vpsDownloadZipBtn = document.getElementById('vpsDownloadZipBtn');
  if (vpsDownloadZipBtn) {
    vpsDownloadZipBtn.addEventListener('click', () => {
      window.location.href = 'api/bot.php?action=download_vps_package';
      showToast('Загрузка пакета antidesigner-tg-agent.zip началась...', 'success');
    });
  }

  // ===========================================================================
  // 9. МОДУЛЬ: 🎯 B2B-СКАУТ & ХОЛОДНЫЙ ПАРСИНГ (#outbound)
  // Two-stage pipeline: Flash-Lite -> Flash Heavy with strict filter >= 5
  // ===========================================================================
  let outreachLeads = [];
  let outreachStatusFilter = 'all';
  let outreachSearchQuery = '';
  let isOutreachLoaded = false;
  let isOutreachScanning = false;

  const scoutUrlInput = document.getElementById('scoutUrlInput');
  const scoutStartBtn = document.getElementById('scoutStartBtn');
  const scoutQuotaBadge = document.getElementById('scoutQuotaBadge');
  const scoutScannerBanner = document.getElementById('scoutScannerBanner');
  const scoutScannerTitle = document.getElementById('scoutScannerTitle');
  const scanStep1 = document.getElementById('scanStep1');
  const scanStep2 = document.getElementById('scanStep2');
  const scanStep3 = document.getElementById('scanStep3');
  const scoutStatusFilters = document.getElementById('scoutStatusFilters');
  const scoutSearchInput = document.getElementById('scoutSearchInput');
  const scoutRefreshBtn = document.getElementById('scoutRefreshBtn');
  const outreachLeadsList = document.getElementById('outreachLeadsList');

  async function initOutboundTab() {
    if (!isOutreachLoaded) {
      await loadOutreachLeads();
    }
  }

  async function loadOutreachLeads(force = false) {
    if (isOutreachLoaded && !force) return;
    try {
      const res = await fetch('api/outreach.php?action=list', {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка загрузки лидов');
      }

      outreachLeads = Array.isArray(data.leads) ? data.leads : [];
      isOutreachLoaded = true;

      // Обновляем дневную квоту
      if (scoutQuotaBadge) {
        scoutQuotaBadge.textContent = `Отобрано сегодня: ${data.today_count} из ${data.today_quota}`;
      }

      // Обновляем счетчики на фильтр-табах
      updateOutreachFilterCounts();

      // Отрисовываем карточки
      renderOutreachLeads();

    } catch (err) {
      console.error('Outreach load error:', err);
      showToast(`Ошибка загрузки лидов: ${err.message}`, 'error');
    }
  }

  function updateOutreachFilterCounts() {
    const countAllEl = document.getElementById('countAllLeads');
    const countQualEl = document.getElementById('countQualLeads');
    const countSentEl = document.getElementById('countSentLeads');
    const countRepliedEl = document.getElementById('countRepliedLeads');
    const countWonEl = document.getElementById('countWonLeads');
    const countLostEl = document.getElementById('countLostLeads');

    if (countAllEl) countAllEl.textContent = outreachLeads.length;
    if (countQualEl) countQualEl.textContent = outreachLeads.filter(l => l.status === 'qualified').length;
    if (countSentEl) countSentEl.textContent = outreachLeads.filter(l => l.status === 'sent').length;
    if (countRepliedEl) countRepliedEl.textContent = outreachLeads.filter(l => l.status === 'replied').length;
    if (countWonEl) countWonEl.textContent = outreachLeads.filter(l => l.status === 'won').length;
    if (countLostEl) countLostEl.textContent = outreachLeads.filter(l => l.status === 'lost').length;
  }

  function renderOutreachLeads() {
    if (!outreachLeadsList) return;

    let filtered = outreachLeads;

    // Фильтр по статусу
    if (outreachStatusFilter !== 'all') {
      filtered = filtered.filter(l => l.status === outreachStatusFilter);
    }

    // Фильтр по поисковой строке
    if (outreachSearchQuery.trim() !== '') {
      const q = outreachSearchQuery.toLowerCase();
      filtered = filtered.filter(l =>
        (l.company_name || '').toLowerCase().includes(q) ||
        (l.website_url || '').toLowerCase().includes(q) ||
        (l.lpr_name || '').toLowerCase().includes(q) ||
        (l.niche || '').toLowerCase().includes(q)
      );
    }

    if (filtered.length === 0) {
      outreachLeadsList.innerHTML = `
        <div class="module-placeholder-box" style="padding: 40px 20px;">
          <div class="box-icon">🔍</div>
          <h3>Лиды не найдены</h3>
          <p>По вашему фильтру нет целевых сайтов. Запустите квалификацию нового URL выше или сбросьте фильтр.</p>
        </div>
      `;
      return;
    }

    outreachLeadsList.innerHTML = filtered.map(lead => {
      const score = Number(lead.deal_score) || 5;
      const isHigh = score >= 8;
      const statusTitle = getLeadStatusTitle(lead.status);

      return `
        <div class="scout-lead-card" id="leadCard-${lead.id}" data-lead-id="${lead.id}">
          <!-- Свернутая строка карточки -->
          <div class="lead-card-header" onclick="toggleLeadCard(${lead.id})">
            <div class="lead-header-left">
              <span class="score-pill ${isHigh ? 'score-high' : 'score-med'}">
                ${isHigh ? '★' : '●'} ${score}/10 ${isHigh ? 'ВЫСОКИЙ ШАНС' : 'СРЕДНИЙ ШАНС'}
              </span>
              <div class="lead-title-box">
                <span class="lead-company-name">${escapeHtml(lead.company_name || 'Проект')}</span>
                <a href="${escapeHtml(lead.website_url)}" target="_blank" class="lead-website-link" onclick="event.stopPropagation()">
                  ${escapeHtml((lead.website_url || '').replace(/^https?:\/\//, ''))} ↗
                </a>
              </div>
              <span class="niche-badge">${escapeHtml(lead.niche || 'B2B')}</span>
            </div>
            <div class="lead-header-right">
              <span class="ps-score-pill ${lead.pagespeed_score >= 50 ? 'ps-warn' : 'ps-bad'}">
                ⚡ ${lead.pagespeed_score}/100 (${lead.load_time_sec}с)
              </span>
              <span class="lpr-preview-chip">👤 ${escapeHtml(lead.lpr_name || 'ЛПР')}</span>
              <span class="status-pill status-${lead.status}" id="statusPill-${lead.id}">${statusTitle}</span>
              <span class="accordion-toggle-arrow">▼</span>
            </div>
          </div>

          <!-- Раскрывающийся подробный дашборд лида -->
          <div class="lead-card-body" id="leadBody-${lead.id}" style="display: none;">
            <div class="lead-details-grid">
              <!-- Колонка 1: ЛПР и Контакты -->
              <div class="lead-detail-col">
                <h4 class="col-title">👤 Контакты ЛПР и Соцсети</h4>
                <div class="detail-field">
                  <label>Имя и должность:</label>
                  <div class="field-value highlight">${escapeHtml(lead.lpr_name || 'Не указано')}</div>
                </div>
                <div class="detail-field">
                  <label>Прямой контакт (TG / WA / Тел):</label>
                  <div class="field-value">${renderContactLink(lead.lpr_contact)}</div>
                </div>
                <div class="detail-field">
                  <label>Соцсети проекта:</label>
                  <div class="social-tags-list">${renderSocialLinks(lead.social_links)}</div>
                </div>
                <div class="detail-field" style="margin-top: 14px;">
                  <label>Статус воронки:</label>
                  <select class="input-tech select-lead-status" onchange="updateLeadStatus(${lead.id}, this.value)">
                    <option value="qualified" ${lead.status === 'qualified' ? 'selected' : ''}>Квалифицирован</option>
                    <option value="sent" ${lead.status === 'sent' ? 'selected' : ''}>Отправлен</option>
                    <option value="replied" ${lead.status === 'replied' ? 'selected' : ''}>Ответил</option>
                    <option value="won" ${lead.status === 'won' ? 'selected' : ''}>Won (Сделка закрыта)</option>
                    <option value="lost" ${lead.status === 'lost' ? 'selected' : ''}>Lost (Срыв сделки)</option>
                  </select>
                </div>
              </div>

              <!-- Колонка 2: Технический и Дизайн аудит -->
              <div class="lead-detail-col">
                <h4 class="col-title">⚡ Технический &amp; Дизайн аудит</h4>
                <div class="audit-speed-row">
                  <div class="speed-box">
                    <span class="speed-num ${lead.pagespeed_score >= 50 ? 'text-amber' : 'text-crimson'}">${lead.pagespeed_score}/100</span>
                    <span class="speed-lbl">PageSpeed v5</span>
                  </div>
                  <div class="speed-box">
                    <span class="speed-num">${lead.load_time_sec} сек</span>
                    <span class="speed-lbl">Мобильный FCP</span>
                  </div>
                </div>
                <div class="audit-bullets-box">
                  <span class="bullet-lbl">Технические проблемы:</span>
                  <pre class="audit-pre">${escapeHtml(lead.tech_findings || 'Не зафиксировано')}</pre>
                </div>
                <div class="audit-bullets-box">
                  <span class="bullet-lbl">Ошибки первого экрана &amp; UI:</span>
                  <pre class="audit-pre">${escapeHtml(lead.design_critique || 'Не зафиксировано')}</pre>
                </div>
                ${lead.screenshot_url ? `<div class="audit-screenshot-box"><img src="${lead.screenshot_url}" alt="Screenshot" class="audit-screenshot-img"></div>` : ''}
              </div>

              <!-- Колонка 3: Бизнес-анализ и стратегия -->
              <div class="lead-detail-col">
                <h4 class="col-title">📊 Бизнес-анализ &amp; Стратегия</h4>
                <div class="biz-analysis-box pros">
                  <span class="biz-lbl">✓ Почему бизнес согласится:</span>
                  <p class="biz-text">${escapeHtml(lead.pros_analysis || '')}</p>
                </div>
                <div class="biz-analysis-box cons">
                  <span class="biz-lbl">⚠ Возможные возражения:</span>
                  <p class="biz-text">${escapeHtml(lead.cons_analysis || '')}</p>
                </div>
                <div class="biz-analysis-box strategy">
                  <span class="biz-lbl">🎯 Стратегия захода:</span>
                  <p class="biz-text">${escapeHtml(lead.pitch_strategy || '')}</p>
                </div>
              </div>
            </div>

            <!-- Блок готового питча -->
            <div class="lead-pitch-card">
              <div class="pitch-card-header">
                <div class="pitch-title-wrap">
                  <span class="pitch-kicker">PERSONALIZED PITCH</span>
                  <h4>Готовый персонализированный питч в Telegram</h4>
                </div>
                <button type="button" class="btn-tech-outline btn-xs btn-copy-pitch" onclick="copyPitchText(${lead.id})">
                  <span class="btn-icon">📋</span> Копировать питч
                </button>
              </div>
              <div class="pitch-text-content" id="pitchText-${lead.id}">${escapeHtml(lead.generated_pitch || '')}</div>
            </div>

            <!-- Блок Win/Loss самообучения (Self-Learning Loop) -->
            <div class="lead-winloss-card">
              <div class="winloss-header">
                <div class="winloss-title-wrap">
                  <span class="winloss-kicker">AGENT MEMORY &amp; SELF-LEARNING LOOP</span>
                  <h4>🧠 Win/Loss Аналитика переписки</h4>
                </div>
                <span class="winloss-hint">ИИ зафиксирует выводы в память агента (agent_memory) и Базу Знаний</span>
              </div>
              <p class="winloss-desc">
                Вставьте сюда текст диалога после отправки питча и общения с клиентом. Нажмите соответствующую кнопку для запуска обратной связи.
              </p>
              <div class="winloss-form">
                <textarea id="chatHistory-${lead.id}" class="input-tech chat-history-textarea" rows="4" placeholder="Вставьте историю сообщений из Telegram...">${escapeHtml(lead.chat_history || '')}</textarea>
                <div class="winloss-actions-row">
                  <button type="button" class="btn-tech-primary btn-win-deal" onclick="submitWinLoss(${lead.id}, 'won')">
                    <span class="btn-icon">✅</span> Сделка закрыта (Won) -> Зафиксировать успех
                  </button>
                  <button type="button" class="btn-tech-outline btn-loss-deal" onclick="submitWinLoss(${lead.id}, 'lost')">
                    <span class="btn-icon">❌</span> Срыв сделки (Lost) -> Разобрать ошибку
                  </button>
                  <button type="button" class="btn-tech-outline btn-delete-lead" onclick="deleteOutreachLead(${lead.id})" title="Удалить лида">
                    🗑
                  </button>
                </div>
              </div>
              <div class="winloss-status-box" id="winLossStatus-${lead.id}" style="${lead.deal_outcome ? 'display: block;' : 'display: none;'}">
                ${lead.deal_outcome ? `<strong>✓ Итог зафиксирован (${escapeHtml(lead.deal_outcome)}):</strong> статус сохранен в памяти.` : ''}
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function getLeadStatusTitle(status) {
    switch (status) {
      case 'qualified': return 'Квалифицирован';
      case 'sent': return 'Отправлен';
      case 'replied': return 'Ответил';
      case 'won': return 'Won (Сделка)';
      case 'lost': return 'Lost (Отказ)';
      default: return status || 'Новый';
    }
  }

  function renderContactLink(contact) {
    if (!contact) return '<span style="color:#888e99;">Не найден</span>';
    if (contact.startsWith('https://t.me/') || contact.startsWith('t.me/')) {
      const url = contact.startsWith('http') ? contact : `https://${contact}`;
      return `<a href="${escapeHtml(url)}" target="_blank" class="lead-website-link">✈ ${escapeHtml(contact)} ↗</a>`;
    }
    if (contact.startsWith('https://wa.me/') || contact.startsWith('wa.me/')) {
      const url = contact.startsWith('http') ? contact : `https://${contact}`;
      return `<a href="${escapeHtml(url)}" target="_blank" class="lead-website-link" style="color:#22c55e;">💬 WhatsApp ↗</a>`;
    }
    if (contact.startsWith('+7') || contact.startsWith('8')) {
      const cleanPhone = contact.replace(/[^0-9+]/g, '');
      return `<a href="tel:${cleanPhone}" class="lead-website-link">📞 ${escapeHtml(contact)}</a>`;
    }
    return escapeHtml(contact);
  }

  function renderSocialLinks(linksStr) {
    if (!linksStr) return '<span style="color:#888e99;font-size:11px;">Не найдены</span>';
    const links = linksStr.split(',').map(s => s.trim()).filter(Boolean);
    if (links.length === 0) return '<span style="color:#888e99;font-size:11px;">Не найдены</span>';
    return links.map(l => {
      let icon = '🔗';
      let label = 'Ссылка';
      if (l.includes('vk.com')) { icon = '🔷'; label = 'VK'; }
      else if (l.includes('t.me')) { icon = '✈'; label = 'TG'; }
      else if (l.includes('instagram.com')) { icon = '📸'; label = 'Instagram'; }
      return `<a href="${escapeHtml(l)}" target="_blank" class="social-tag-link">${icon} ${label} ↗</a>`;
    }).join(' ');
  }

  // Экспорт глобальных функций для аккордеона и интерактивных кнопок
  window.toggleLeadCard = function(id) {
    const card = document.getElementById(`leadCard-${id}`);
    const body = document.getElementById(`leadBody-${id}`);
    if (!card || !body) return;

    const isOpen = card.classList.contains('open');
    if (isOpen) {
      card.classList.remove('open');
      body.style.display = 'none';
    } else {
      card.classList.add('open');
      body.style.display = 'block';
    }
  };

  window.copyPitchText = function(id) {
    const lead = outreachLeads.find(l => l.id == id);
    if (!lead || !lead.generated_pitch) {
      showToast('Текст питча отсутствует', 'error');
      return;
    }

    navigator.clipboard.writeText(lead.generated_pitch).then(() => {
      showToast('Текст питча скопирован в буфер обмена!', 'success');
    }).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = lead.generated_pitch;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      showToast('Текст питча скопирован в буфер обмена!', 'success');
    });
  };

  window.updateLeadStatus = async function(id, newStatus) {
    try {
      const res = await fetch('api/outreach.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', id, status: newStatus })
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка обновления статуса');
      }

      const lead = outreachLeads.find(l => l.id == id);
      if (lead) {
        lead.status = newStatus;
        const pill = document.getElementById(`statusPill-${id}`);
        if (pill) {
          pill.className = `status-pill status-${newStatus}`;
          pill.textContent = getLeadStatusTitle(newStatus);
        }
      }
      updateOutreachFilterCounts();
      showToast('Статус лида обновлен', 'success');
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    }
  };

  window.submitWinLoss = async function(id, outcome) {
    const historyEl = document.getElementById(`chatHistory-${id}`);
    const statusBox = document.getElementById(`winLossStatus-${id}`);
    if (!historyEl) return;

    const chatText = historyEl.value.trim();
    if (!chatText) {
      showToast('Вставьте текст переписки с клиентом для проведения Win/Loss анализа', 'warning');
      historyEl.focus();
      return;
    }

    if (statusBox) {
      statusBox.style.display = 'block';
      statusBox.innerHTML = '<span style="color:#00E5B8;">⏳ ИИ анализирует диалог и формулирует гипотезы...</span>';
    }

    try {
      const res = await fetch('api/outreach.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'win_loss_analysis',
          lead_id: id,
          chat_history: chatText,
          deal_outcome: outcome
        })
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка Win/Loss анализа');
      }

      const lead = outreachLeads.find(l => l.id == id);
      if (lead) {
        lead.status = (outcome === 'won') ? 'won' : 'lost';
        lead.deal_outcome = outcome;
        lead.chat_history = chatText;
        const pill = document.getElementById(`statusPill-${id}`);
        if (pill) {
          pill.className = `status-pill status-${lead.status}`;
          pill.textContent = getLeadStatusTitle(lead.status);
        }
      }
      updateOutreachFilterCounts();

      if (statusBox) {
        statusBox.innerHTML = `
          <div style="line-height: 1.5;">
            <div style="font-weight: 700; margin-bottom: 4px; color: #fff;">
              ${outcome === 'won' ? '✅ Сделка закрыта (Успех)' : '❌ Срыв сделки (Отказ)'} -> Вывод сохранен в память агента:
            </div>
            <p style="margin: 0 0 6px 0; color: #00E5B8;">«${escapeHtml(data.hypothesis)}»</p>
            <span class="tech-tag" style="font-size: 10px;">${escapeHtml(data.metric)}</span>
          </div>
        `;
      }

      showToast('Win/Loss анализ завершен! Вывод сохранен в память агента и Базу Знаний', 'success');

    } catch (err) {
      if (statusBox) {
        statusBox.innerHTML = `<span style="color:#F87171;">Ошибка анализа: ${escapeHtml(err.message)}</span>`;
      }
      showToast(`Ошибка: ${err.message}`, 'error');
    }
  };

  window.deleteOutreachLead = async function(id) {
    if (!confirm('Удалить лида из базы аутрича?')) return;
    try {
      const res = await fetch('api/outreach.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка удаления');
      }

      outreachLeads = outreachLeads.filter(l => l.id != id);
      updateOutreachFilterCounts();
      renderOutreachLeads();
      showToast('Лид удален', 'info');
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    }
  };

  async function startLeadScout() {
    if (isOutreachScanning) return;
    if (!scoutUrlInput) return;

    const targetUrl = scoutUrlInput.value.trim();
    if (!targetUrl) {
      showToast('Введите URL целевого сайта или название компании', 'warning');
      scoutUrlInput.focus();
      return;
    }

    isOutreachScanning = true;
    if (scoutStartBtn) scoutStartBtn.disabled = true;

    // Показываем баннер прогресса сканирования
    if (scoutScannerBanner) {
      scoutScannerBanner.style.display = 'flex';
      if (scanStep1) scanStep1.className = 'step-chip active';
      if (scanStep2) scanStep2.className = 'step-chip';
      if (scanStep3) scanStep3.className = 'step-chip';
      if (scoutScannerTitle) scoutScannerTitle.textContent = `Сканирование: ${targetUrl}...`;
    }

    const t1 = setTimeout(() => {
      if (scanStep1) scanStep1.className = 'step-chip';
      if (scanStep2) scanStep2.className = 'step-chip active';
      if (scoutScannerTitle) scoutScannerTitle.textContent = 'Этап 1: Flash-Lite анализирует контакты ЛПР...';
    }, 600);

    const t2 = setTimeout(() => {
      if (scanStep2) scanStep2.className = 'step-chip';
      if (scanStep3) scanStep3.className = 'step-chip active';
      if (scoutScannerTitle) scoutScannerTitle.textContent = 'Этап 2: Flash Heavy рассчитывает вероятность сделки и питч...';
    }, 1400);

    try {
      const res = await fetch('api/outreach.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'analyze_target', url: targetUrl })
      });
      const data = await res.json();

      clearTimeout(t1);
      clearTimeout(t2);

      if (data.status === 'success') {
        showToast(data.message || 'Лид успешно квалифицирован!', 'success');
        scoutUrlInput.value = '';
        await loadOutreachLeads(true);
        if (data.lead && data.lead.id) {
          setTimeout(() => toggleLeadCard(data.lead.id), 200);
        }
      } else if (data.status === 'rejected') {
        showToast(data.message || 'Лид отбракован: оценка ниже 5/10', 'warning');
      } else if (data.status === 'exists') {
        showToast(data.message || 'Этот сайт уже в базе', 'info');
      } else {
        throw new Error(data.message || 'Ошибка обработки сайта');
      }
    } catch (err) {
      clearTimeout(t1);
      clearTimeout(t2);
      showToast(`Ошибка: ${err.message}`, 'error');
    } finally {
      isOutreachScanning = false;
      if (scoutStartBtn) scoutStartBtn.disabled = false;
      if (scoutScannerBanner) scoutScannerBanner.style.display = 'none';
    }
  }

  // Обработчики кнопок поиска и фильтров скаута
  if (scoutStartBtn) {
    scoutStartBtn.addEventListener('click', () => startLeadScout());
  }

  if (scoutUrlInput) {
    scoutUrlInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        startLeadScout();
      }
    });
  }

  if (scoutRefreshBtn) {
    scoutRefreshBtn.addEventListener('click', () => {
      loadOutreachLeads(true);
      showToast('Список лидов обновлен', 'info');
    });
  }

  if (scoutSearchInput) {
    scoutSearchInput.addEventListener('input', (e) => {
      outreachSearchQuery = e.target.value;
      renderOutreachLeads();
    });
  }

  if (scoutStatusFilters) {
    scoutStatusFilters.querySelectorAll('.filter-pill').forEach(btn => {
      btn.addEventListener('click', () => {
        scoutStatusFilters.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        outreachStatusFilter = btn.getAttribute('data-status') || 'all';
        renderOutreachLeads();
      });
    });
  }

  // ---------------------------------------------------------------------------
  // 8. СЛУШАТЕЛИ СОБЫТИЙ И ИНИЦИАЛИЗАЦИЯ
  // ---------------------------------------------------------------------------
  window.addEventListener('hashchange', () => {
    switchTab(getCurrentTabFromHash());
  });

  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const tab = item.getAttribute('data-tab');
      if (tab) {
        window.location.hash = `#${tab}`;
      }
    });
  });

  if (checkApiBtn) {
    checkApiBtn.addEventListener('click', (e) => {
      e.preventDefault();
      pingDatabaseApi();
    });
  }

  // Первоначальный старт
  switchTab(getCurrentTabFromHash());
  pingDatabaseApi();
});
