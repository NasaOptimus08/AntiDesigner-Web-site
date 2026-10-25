// Интерактивный фокус на тарифах #formats
const formatCards = document.querySelectorAll('.format-card');
if (formatCards.length) {
  formatCards.forEach(card => {
    card.addEventListener('mouseenter', () => {
      formatCards.forEach(c => c.classList.remove('is-active'));
      card.classList.add('is-active');
    });
  });

  // Возврат фокуса на флагман при уходе курсора с секции
  const stage = document.querySelector('.formats-carousel-stage');
  if (stage) {
    stage.addEventListener('mouseleave', () => {
      formatCards.forEach(c => c.classList.remove('is-active'));
      const flagship = document.querySelector('.format-card.is-flagship');
      if (flagship) flagship.classList.add('is-active');
    });
  }
}
