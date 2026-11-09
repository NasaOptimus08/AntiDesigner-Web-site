function setupHoverCarousel(containerSelector, cardSelector) {
  const container = document.querySelector(containerSelector);
  if (!container) return;
  const cards = container.querySelectorAll(cardSelector);
  const defaultActive = container.querySelector(`${cardSelector}.is-flagship`) || cards[1] || cards[0];

  cards.forEach((card) => {
    card.addEventListener('mouseenter', () => {
      cards.forEach((c) => c.classList.remove('is-active'));
      card.classList.add('is-active');
    });
  });

  container.addEventListener('mouseleave', () => {
    cards.forEach((c) => c.classList.remove('is-active'));
    if (defaultActive) {
      defaultActive.classList.add('is-active');
    }
  });
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
  setupHoverCarousel('.formats-carousel-stage', '.format-card');
  setupHoverCarousel('.cases-carousel-stage', '.case-card-stage');

  // Управление аккордеоном FAQ (CSS Grid экспандер)
  const faqItems = document.querySelectorAll('.faq-item');
  if (!faqItems.length) return;

  faqItems.forEach(item => {
    const trigger = item.querySelector('.faq-summary');
    if (!trigger) return;

    trigger.addEventListener('click', () => {
      const isActive = item.classList.contains('is-active');

      // Закрываем все остальные вкладки
      faqItems.forEach(el => {
        el.classList.remove('is-active');
      });

      // Если кликнутая не была активна — открываем её
      if (!isActive) {
        item.classList.add('is-active');
      }
    });
  });

  // Настройка Intersection Observer для глобальной анимации при скролле
  const observerOptions = {
    root: null,
    rootMargin: '0px',
    threshold: 0.15 // Элемент появляется, когда 15% его высоты видно
  };

  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('reveal-visible');
        observer.unobserve(entry.target); // Анимируем только один раз
      }
    });
  }, observerOptions);

  // Ищем все скрытые элементы и вешаем обзервер
  const revealElements = document.querySelectorAll('.reveal-hidden');
  revealElements.forEach(el => revealObserver.observe(el));

  // --- Премиальный цикл виджета аналитики (С идеальным центрированием) ---
  const statsWidget = document.querySelector('.stats-widget');
  const timelineEl = document.getElementById('syncTimeline');
  const counterEl = document.getElementById('syncCounterNum');

  if (statsWidget && timelineEl && counterEl) {
    const targetNum = 132;
    const forwardDuration = 3200; 
    const rewindDuration = 700;   
    const holdTimeEnd = 700;      
    const holdTimeStart = 200;    

    const easeOutQuart = (x) => 1 - Math.pow(1 - x, 4);

    function getTargetDistance() {
      const items = timelineEl.querySelectorAll('span');
      if (items.length < 2) return 0;
      return items[items.length - 1].offsetTop - items[0].offsetTop;
    }

    function updateActiveMonth(easeValue) {
      const spans = timelineEl.querySelectorAll('span');
      const maxIndex = spans.length - 1;
      const currentIndex = Math.round(easeValue * maxScrollIndex); 
      
      spans.forEach((span, i) => {
        if (i === currentIndex) {
          span.classList.add('active-month');
        } else {
          span.classList.remove('active-month');
        }
      });
    }

    let maxScrollIndex = 0;

    function animateCycle() {
      const totalDistance = getTargetDistance();
      const spans = timelineEl.querySelectorAll('span');
      maxScrollIndex = spans.length - 1;

      // 1. Скролл вперед
      let startForward = null;
      function stepForward(timestamp) {
        if (!startForward) startForward = timestamp;
        const progress = Math.min((timestamp - startForward) / forwardDuration, 1);
        const ease = easeOutQuart(progress);

        counterEl.innerText = Math.floor(ease * targetNum);
        timelineEl.style.transform = `translateY(-${ease * totalDistance}px)`;
        updateActiveMonth(ease);

        if (progress < 1) {
          window.requestAnimationFrame(stepForward);
        } else {
          setTimeout(() => {
            // 2. Скролл назад
            let startRewind = null;
            function stepRewind(timestamp) {
              if (!startRewind) startRewind = timestamp;
              const rewProgress = Math.min((timestamp - startRewind) / rewindDuration, 1);
              const rewEase = easeOutQuart(rewProgress);
              const currentVal = Math.max(0, 1 - rewEase);

              counterEl.innerText = Math.floor(currentVal * targetNum);
              timelineEl.style.transform = `translateY(-${currentVal * totalDistance}px)`;
              updateActiveMonth(currentVal);

              if (rewProgress < 1) {
                window.requestAnimationFrame(stepRewind);
              } else {
                setTimeout(animateCycle, holdTimeStart);
              }
            }
            window.requestAnimationFrame(stepRewind);
          }, holdTimeEnd);
        }
      }
      window.requestAnimationFrame(stepForward);
    }

    // Запуск анимации синхронно или при появлении карточки
    const statsObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        animateCycle();
        statsObserver.unobserve(statsWidget);
      }
    }, { threshold: 0.1 });

    statsObserver.observe(statsWidget);
  }

  // Пауза анимаций .hero-blob и .roadmap-track-glow вне экрана через класс .is-in-view
  const animatedSections = document.querySelectorAll('#hero, #process');
  if (animatedSections.length) {
    const viewObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const isInView = entry.isIntersecting;
        entry.target.classList.toggle('is-in-view', isInView);
        entry.target.querySelectorAll('.hero-blob, .roadmap-track-glow').forEach((el) => {
          el.classList.toggle('is-in-view', isInView);
        });
      });
    }, { threshold: 0.05 });

    animatedSections.forEach((sec) => viewObserver.observe(sec));
  }

  // Оптимизированный автозапуск и пауза видео при скролле (IntersectionObserver)
  const allMediaVideos = document.querySelectorAll('.stack-video-mockup, .case-visual-media');
  if (allMediaVideos.length) {
    const videoObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const v = entry.target;
        if (entry.isIntersecting) {
          v.play().catch(() => {});
        } else {
          v.pause();
        }
      });
    }, { rootMargin: '200px 0px', threshold: 0.1 });

    allMediaVideos.forEach(v => videoObserver.observe(v));
  }

  // Мобильное бургер-меню
  const burgerBtn = document.getElementById('mobileBurgerBtn');
  const menuOverlay = document.getElementById('mobileMenuOverlay');
  const menuLinks = document.querySelectorAll('.mobile-nav-link, .mobile-menu-cta');

  if (burgerBtn && menuOverlay) {
    function toggleMenu(isOpen) {
      burgerBtn.classList.toggle('is-active', isOpen);
      menuOverlay.classList.toggle('is-open', isOpen);
      burgerBtn.setAttribute('aria-expanded', isOpen);
      document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    burgerBtn.addEventListener('click', () => {
      const isOpen = menuOverlay.classList.contains('is-open');
      toggleMenu(!isOpen);
    });

    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        toggleMenu(false);
      });
    });

    // Закрытие меню по нажатию клавиши Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && menuOverlay.classList.contains('is-open')) {
        toggleMenu(false);
      }
    });

    // Закрытие меню при клике по логотипу
    const brandLink = document.querySelector('.nav-brand');
    if (brandLink) {
      brandLink.addEventListener('click', () => {
        if (menuOverlay.classList.contains('is-open')) {
          toggleMenu(false);
        }
      });
    }
  }

  // Микро-подсказка свайпа при первом показе на планшетах
  const formatSlider = document.querySelector('.formats-carousel-stage');
  if (formatSlider && window.innerWidth >= 769 && window.innerWidth <= 960) {
    let hasNudged = false;
    const nudgeObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting && !hasNudged) {
        hasNudged = true;
        setTimeout(() => {
          formatSlider.scrollTo({ left: 55, behavior: 'smooth' });
          setTimeout(() => {
            formatSlider.scrollTo({ left: 0, behavior: 'smooth' });
          }, 500);
        }, 400);
        nudgeObserver.disconnect();
      }
    }, { threshold: 0.25 });
    nudgeObserver.observe(formatSlider);
  }
});

