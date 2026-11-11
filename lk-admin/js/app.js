/**
 * =============================================================================
 * ANTIDESIGNER CORE — CLIENT-SIDE SPA ROUTER & API CONTROLLER (Sprint 2)
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
      breadcrumb: 'CALENDAR'
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

    // Переключение ссылок в сайдбаре
    navItems.forEach(item => {
      const itemTab = item.getAttribute('data-tab');
      if (itemTab === resolvedKey) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    // Переключение контейнеров
    tabPanes.forEach(pane => {
      if (pane.id === `pane-${resolvedKey}`) {
        pane.classList.add('active');
      } else {
        pane.classList.remove('active');
      }
    });

    // Заголовки и хлебные крошки
    if (pageTitleEl) pageTitleEl.textContent = targetModule.title;
    if (breadcrumbActiveEl) breadcrumbActiveEl.textContent = targetModule.breadcrumb;

    // Синхронизация хэша
    if (window.location.hash !== `#${resolvedKey}`) {
      window.history.replaceState(null, '', `#${resolvedKey}`);
    }

    // Хук активации модуля
    if (typeof targetModule.onActivate === 'function') {
      targetModule.onActivate();
    }
  }

  // ---------------------------------------------------------------------------
  // 4. ПРОВЕРКА СОЕДИНЕНИЯ С БАЗОЙ И СЕРВЕРОМ (PING DB)
  // ---------------------------------------------------------------------------
  async function pingDatabaseApi() {
    if (!apiStatusBadge) return;

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
          apiText.textContent = `DB: ONLINE (${data.tables_count || 8} TBL)`;
        }
      } else {
        throw new Error(data.message || 'Unknown response');
      }
    } catch (err) {
      apiStatusBadge.className = 'api-status-badge error';
      if (apiText) apiText.textContent = 'DB: OFFLINE';
      console.warn('AntiDesigner Core DB ping warning:', err.message);
    }
  }

  // ===========================================================================
  // 5. МОДУЛЬ: ⚙️ СКИЛЛЫ / ПРОМПТ-ИНЖИНИРИНГ (#skills)
  // ===========================================================================
  let skillsData = [];
  let activeSkillId = null;
  let isSkillsLoaded = false;

  const skillsListEl = document.getElementById('skillsList');
  const skillsCountBadgeEl = document.getElementById('skillsCountBadge');
  const skillEditorTitleEl = document.getElementById('skillEditorTitle');
  const skillEditorCodeEl = document.getElementById('skillEditorCode');
  const skillTovInputEl = document.getElementById('skillTovInput');
  const skillPromptInputEl = document.getElementById('skillPromptInput');
  const saveSkillBtn = document.getElementById('saveSkillBtn');
  const skillSaveStatusEl = document.getElementById('skillSaveStatus');
  const skillUpdatedTimestampEl = document.getElementById('skillUpdatedTimestamp');

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

        // Выбираем первый скилл по умолчанию или сохраняем активный
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

  function renderSkillsList() {
    if (!skillsListEl) return;
    skillsListEl.innerHTML = '';

    if (skillsCountBadgeEl) {
      skillsCountBadgeEl.textContent = `${skillsData.length} активных`;
    }

    skillsData.forEach(skill => {
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

    // Обновляем подсветку карточки в списке
    if (skillsListEl) {
      skillsListEl.querySelectorAll('.skill-item-card').forEach(c => {
        if (parseInt(c.getAttribute('data-id'), 10) === id) {
          c.classList.add('active');
        } else {
          c.classList.remove('active');
        }
      });
    }

    // Заполняем поля редактора
    if (skillEditorTitleEl) skillEditorTitleEl.textContent = skill.title;
    if (skillEditorCodeEl) skillEditorCodeEl.textContent = `SKILL: ${skill.code.toUpperCase()}`;
    if (skillTovInputEl) skillTovInputEl.value = skill.tone_of_voice || '';
    if (skillPromptInputEl) skillPromptInputEl.value = skill.system_prompt || '';
    if (skillUpdatedTimestampEl) {
      skillUpdatedTimestampEl.textContent = skill.updated_at ? `Обновлено: ${skill.updated_at}` : 'Синхронизировано';
    }
    if (skillSaveStatusEl) skillSaveStatusEl.textContent = '';
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
          tone_of_voice: newTov,
          system_prompt: newPrompt
        })
      });

      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Ошибка сохранения');
      }

      // Обновляем локальные данные
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
  // 6. МОДУЛЬ: 🧠 БАЗА ЗНАНИЙ (RAG CONTEXT) (#knowledge)
  // ===========================================================================
  let kbData = [];
  let activeKbCategory = 'tariffs';
  let isKbLoaded = false;

  const kbCategoriesNav = document.getElementById('kbCategoriesNav');
  const kbCategoryBadgeEl = document.getElementById('kbCategoryBadge');
  const kbActiveTitleEl = document.getElementById('kbActiveTitle');
  const kbContentInputEl = document.getElementById('kbContentInput');
  const saveKbBtn = document.getElementById('saveKbBtn');
  const kbSaveStatusEl = document.getElementById('kbSaveStatus');
  const kbUpdatedTimestampEl = document.getElementById('kbUpdatedTimestamp');

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

    // Обновляем кнопки категорий
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
  // ===========================================================================
  let generatedBatch = [];
  let activeGeneratedIndex = 0;

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

  // Генерация материалов пакета
  async function generateContentBatch() {
    if (!factoryTopicInputEl || !generateBtn) return;

    const topic = factoryTopicInputEl.value.trim();
    if (!topic) {
      showToast('Укажите тему или инфоповод для генерации', 'error');
      factoryTopicInputEl.focus();
      return;
    }

    // Собираем отмеченные форматы
    const checkedFormats = [];
    document.querySelectorAll('input[name="factoryFormats"]:checked').forEach(cb => {
      checkedFormats.push(cb.value);
    });

    if (checkedFormats.length === 0) {
      showToast('Выберите хотя бы один формат для генерации', 'error');
      return;
    }

    // Блокируем кнопку и включаем лоадер
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span class="btn-icon">↺</span> Анализ базы и генерация...';
    if (factoryLoader) {
      factoryLoader.style.display = 'flex';
      if (factoryLoaderText) {
        factoryLoaderText.textContent = `Нейросеть Gemini объединяет базу знаний и пишет пакет (${checkedFormats.length} форматов)...`;
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
        body: JSON.stringify({
          topic: topic,
          formats: checkedFormats
        })
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

      // Отображаем результаты
      renderGeneratedResults(data.topic || topic);
      showToast(`Пакет из ${generatedBatch.length} материалов успешно сгенерирован!`, 'success');

      if (data.errors && data.errors.length > 0) {
        data.errors.forEach(errMsg => showToast(errMsg, 'info'));
      }
    } catch (err) {
      showToast(`Ошибка генерации: ${err.message}`, 'error');
      console.error('Factory generation error:', err);
    } finally {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<span class="btn-icon">⚡</span> Сгенерировать пакет (Omnichannel)';
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

    // Создаем вкладки форматов
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
      case 'tg_post': return 'TG';
      case 'vc_ru': return 'VC';
      case 'reels': return 'REELS';
      case 'lead_magnet': return 'PDF';
      case 'site_geo': return 'SEO';
      default: return 'DOC';
    }
  }

  function selectGeneratedTab(index) {
    activeGeneratedIndex = index;
    const currentItem = generatedBatch[index];
    if (!currentItem) return;

    // Переключаем активную кнопку вкладки
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

    if (factoryActionStatus) {
      factoryActionStatus.textContent = currentItem.status === 'published'
        ? 'Материал опубликован в канале'
        : 'Материал сохранен как черновик в SQLite';
    }
  }

  // Сохранить черновик
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

  // Скопировать в буфер обмена
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

  // Публикация в Telegram
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
        factoryActionStatus.textContent = '✓ Успешно опубликовано в Telegram канале';
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
