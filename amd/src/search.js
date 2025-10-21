define([], function() {
  'use strict';

  // Select all matching elements under a root and return a real array.
  const selectAll = (root, selector) => Array.from(root.querySelectorAll(selector));

  // Normalize text for case-insensitive matching.
  const normalize = (text) => (text || '').trim().toLowerCase();

  // Build a searchable string from a card's data attributes and visible text.
  const buildSearchIndex = (cardEl) => {
    return [
      cardEl.dataset.name,
      cardEl.dataset.course,
      cardEl.dataset.tags,
      cardEl.dataset.competencies,
      cardEl.textContent
    ].map(normalize).join(' ');
  };

  function bindList(listContainerEl) {
    const searchInputEl   = listContainerEl.querySelector('.lab-search');
    const clearButtonEl   = listContainerEl.querySelector('.lab-clear');
    const cardsGridEl     = listContainerEl.querySelector('.lab-grid');
    const cardEls         = cardsGridEl ? selectAll(cardsGridEl, '.lab-card') : [];
    const emptyStateEl    = listContainerEl.querySelector('.lab-empty');
    const resultCountEl   = listContainerEl.querySelector('.lab-result-count');
    const cardIndexes = new Map();
    cardEls.forEach(card => cardIndexes.set(card, buildSearchIndex(card)));

    if (!searchInputEl || !cardsGridEl) {
      return;
    }

    // Update UI based on the current query.
    const applyFilter = () => {
      const query = normalize(searchInputEl.value);
      let visibleCount = 0;

      cardEls.forEach(card => {
        const matches = !query || cardIndexes.get(card).includes(query);
        card.style.display = matches ? '' : 'none';
        if (matches) {
          visibleCount++;
        }
      });

      if (emptyStateEl) {
        emptyStateEl.style.display = visibleCount === 0 ? '' : 'none';
      }

      if (resultCountEl) {
        if (query) {
          resultCountEl.style.display = 'block';
          resultCountEl.textContent = `${visibleCount} result${visibleCount === 1 ? '' : 's'} for “${query}”`;
        } else {
          resultCountEl.style.display = 'none';
          resultCountEl.textContent = '';
        }
      }

      if (clearButtonEl) {
        clearButtonEl.style.display = query ? 'block' : 'none';
      }
    };

    // Wire up events.
    searchInputEl.addEventListener('input', applyFilter);

    if (clearButtonEl) {
      clearButtonEl.addEventListener('click', () => {
        searchInputEl.value = '';
        applyFilter();
        searchInputEl.focus();
      });
    }

    // Quick slash-focus for convenience, scoped to this container.
    listContainerEl.addEventListener('keydown', (event) => {
      const isSlash = event.key === '/';
      const isNotAlreadyFocused = document.activeElement !== searchInputEl;
      if (isSlash && isNotAlreadyFocused) {
        event.preventDefault();
        searchInputEl.focus();
      }
    });
  }

  // Initialize search on all matching list containers.
  function init(rootSelector) {
    const selector = rootSelector || '.lab-list';
    const listContainers = document.querySelectorAll(selector);
    listContainers.forEach(bindList);
  }

  return { init };
});
