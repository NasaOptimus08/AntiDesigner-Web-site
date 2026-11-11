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
      breadcrumb: 'CRM'
    },
    knowledge: {
      title: '🧠 База знаний',
      breadcrumb: 'KNOWLEDGE',
      onActivate: () => initKnowledgeTab()
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
  // 7. МОДУЛЬ: 📅 ИНТЕРАКТИВНЫЙ КОНТЕНТ-ПЛАН (#calendar)
  // ===========================================================================
  let calendarItems = [];
  let currentCalFilter = 'all';
  let isCalendarLoaded = false;

  const calStatTotal = document.getElementById('calStatTotal');
  const calStatReady = document.getElementById('calStatReady');
  const calStatPlanned = document.getElementById('calStatPlanned');
  const calendarFilterPills = document.getElementById('calendarFilterPills');
  const calendarGridCells = document.getElementById('calendarGridCells');
  const calAddPostBtn = document.getElementById('calAddPostBtn');
  const calGenerateAiBtn = document.getElementById('calGenerateAiBtn');

  // Modal elements
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
    await loadCalendarItems();
  }

  async function loadCalendarItems() {
    if (!calendarGridCells) return;
    calendarGridCells.innerHTML = '<div class="calendar-loading-hint">Синхронизация с базой данных SQLite...</div>';

    try {
      const response = await fetch('api/content.php?action=calendar_list');
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      if (data.status === 'success') {
        calendarItems = data.items || [];
        isCalendarLoaded = true;
        renderCalendarGrid();
      } else {
        throw new Error(data.message || 'Ошибка загрузки сетки');
      }
    } catch (err) {
      calendarGridCells.innerHTML = `<div class="calendar-loading-hint" style="color: #eb3b5a;">Ошибка: ${escapeHtml(err.message)}</div>`;
      showToast(`Ошибка календаря: ${err.message}`, 'error');
    }
  }

  function renderCalendarGrid() {
    if (!calendarGridCells) return;

    // Считаем метрики
    const total = calendarItems.length;
    const readyOrPublished = calendarItems.filter(i => i.status === 'ready' || i.status === 'published').length;
    const planned = calendarItems.filter(i => i.status === 'planned').length;

    if (calStatTotal) calStatTotal.textContent = total;
    if (calStatReady) calStatReady.textContent = readyOrPublished;
    if (calStatPlanned) calStatPlanned.textContent = planned;

    // Фильтрация по формату
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

      // Форматируем дату красиво
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

    // Привязываем события клика на карточки
    calendarGridCells.querySelectorAll('.cal-card-box').forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('.cal-btn-factory')) return;
        const id = parseInt(card.getAttribute('data-id'), 10);
        const item = calendarItems.find(i => i.id === id);
        if (item) openCalModal(item);
      });
    });

    // Кнопка быстрого перехода в фабрику
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
      calModalSaveBtn.textContent = 'Сохранение...';

      let url = 'api/content.php';
      let payload = {};

      if (id > 0) {
        payload = { action: 'calendar_update', id, topic, scheduled_for: date, format, status };
      } else {
        payload = { action: 'calendar_create', topic, scheduled_for: date, format, status };
      }

      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка сохранения');
      }

      showToast(id > 0 ? 'Публикация обновлена' : 'Публикация добавлена', 'success');
      closeCalModal();
      await loadCalendarItems();
    } catch (err) {
      showToast(`Ошибка: ${err.message}`, 'error');
    } finally {
      calModalSaveBtn.disabled = false;
      calModalSaveBtn.textContent = '💾 Сохранить';
    }
  }

  async function deleteCalModalItem() {
    const id = calModalItemId ? parseInt(calModalItemId.value, 10) : 0;
    if (id <= 0) return;

    if (!confirm('Удалить эту публикацию из контент-плана?')) return;

    try {
      calModalDeleteBtn.disabled = true;
      const res = await fetch('api/content.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'calendar_delete', id })
      });
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка удаления');
      }

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

    // 1. Заполняем тему
    if (factoryTopicInput) {
      factoryTopicInput.value = item.topic;
    }

    // 2. Переключаем режим фабрики под формат
    const formatModeMap = {
      tg_post: 'telegram',
      vc_ru: 'article',
      reels_hook: 'instagram',
      lead_magnet: 'lead_magnet'
    };
    const targetMode = formatModeMap[item.format] || 'all';

    const modeBtn = document.querySelector(`.mode-tab-btn[data-mode="${targetMode}"]`);
    if (modeBtn) {
      modeBtn.click();
    }

    // 3. Переключаемся на таб фабрики
    switchTab('factory');
    showToast(`Тема «${item.topic.substring(0, 35)}...» перенесена в Контент-фабрику`, 'info');
  }

  // Фильтры календаря
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

  if (calAddPostBtn) {
    calAddPostBtn.addEventListener('click', () => openCalModal(null));
  }

  if (calModalClose) {
    calModalClose.addEventListener('click', closeCalModal);
  }

  if (calModalSaveBtn) {
    calModalSaveBtn.addEventListener('click', saveCalModalItem);
  }

  if (calModalDeleteBtn) {
    calModalDeleteBtn.addEventListener('click', deleteCalModalItem);
  }

  if (calModalGoFactoryBtn) {
    calModalGoFactoryBtn.addEventListener('click', () => {
      const id = calModalItemId ? parseInt(calModalItemId.value, 10) : 0;
      const item = calendarItems.find(i => i.id === id);
      if (item) transferToFactory(item);
    });
  }

  if (calGenerateAiBtn) {
    calGenerateAiBtn.addEventListener('click', async () => {
      if (!confirm('Сгенерировать новый 30-дневный контент-план через нейросеть Gemini на основе Базы Знаний?')) return;

      try {
        calGenerateAiBtn.disabled = true;
        calGenerateAiBtn.innerHTML = '<span class="loader-spinner" style="width:14px;height:14px;border-width:2px;"></span> AI генерирует...';
        showToast('Gemini анализирует боли ЦА и генерирует сетку на 30 дней...', 'info', 6000);

        const res = await fetch('api/content.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'calendar_generate_ai' })
        });

        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
          throw new Error(data.message || 'Ошибка AI-генерации');
        }

        showToast(data.message || 'Контент-план сгенерирован!', 'success');
        await loadCalendarItems();
      } catch (err) {
        showToast(`Ошибка генерации: ${err.message}`, 'error');
      } finally {
        calGenerateAiBtn.disabled = false;
        calGenerateAiBtn.innerHTML = '<span class="btn-icon">⚡</span> AI-план на 30 дней';
      }
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
