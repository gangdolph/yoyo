const toggleView = (targetButton, resultsContainer) => {
  const productContainer = resultsContainer ? resultsContainer.querySelector('#product-container') : document.getElementById('product-container');
  if (!productContainer) {
    return;
  }
  const buttons = (resultsContainer || document).querySelectorAll('.view-toggle button');
  buttons.forEach((btn) => {
    btn.classList.toggle('active', btn === targetButton);
  });
  if (targetButton.classList.contains('view-grid')) {
    productContainer.classList.remove('list-view');
  } else if (targetButton.classList.contains('view-list')) {
    productContainer.classList.add('list-view');
  }
};

const handleViewToggleClick = (event) => {
  const button = event.target.closest('.view-toggle button');
  if (!button) {
    return;
  }
  const results = button.closest('.listing-results');
  toggleView(button, results);
};

const showCartToast = (message) => {
  const toast = document.getElementById('cart-toast');
  if (!toast) {
    return;
  }
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2000);
};

const handleAddToCartClick = (event) => {
  const button = event.target.closest('.add-to-cart');
  if (!button) {
    return;
  }
  event.preventDefault();
  const id = button.dataset.id;
  if (!id) {
    return;
  }
  const availableAttr = parseInt(button.dataset.available || '', 10);
  if (!Number.isNaN(availableAttr) && availableAttr <= 0) {
    showCartToast('Out of stock');
    return;
  }
  fetch('/cart.php?action=add', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `id=${encodeURIComponent(id)}`,
    credentials: 'same-origin',
  })
    .then((res) => res.json())
    .then((data) => {
      if (typeof data.available === 'number') {
        button.dataset.available = String(data.available);
        button.disabled = data.available <= 0;
      }
      if (data.success) {
        const link = document.querySelector('.cart-link a');
        let badge = document.querySelector('.cart-link .badge');
        if (badge) {
          badge.textContent = data.count;
        } else if (link) {
          badge = document.createElement('span');
          badge.className = 'badge';
          badge.textContent = data.count;
          link.appendChild(badge);
        }
      }
      if (data.message) {
        showCartToast(data.message);
      } else if (data.success) {
        showCartToast('Added to cart');
      } else {
        showCartToast('Unable to add to cart. Try again.');
      }
    })
    .catch(() => {
      showCartToast('Unable to add to cart. Try again.');
    });
};

const setupTagFilter = () => {
  const tagFilter = document.querySelector('[data-tag-filter]');
  if (!tagFilter) {
    return;
  }
  const searchInput = tagFilter.querySelector('[data-tag-search]');
  const optionsContainer = tagFilter.querySelector('[data-tag-options]');
  const emptyState = tagFilter.querySelector('.tag-filter-empty');

  const applyFilter = () => {
    if (!searchInput || !optionsContainer) {
      return;
    }
    const rawTerm = searchInput.value.trim().toLowerCase();
    const term = rawTerm.replace(/\s+/g, '-');
    let matches = 0;

    optionsContainer.querySelectorAll('.tag-filter-option').forEach((option) => {
      const tag = option.dataset.tag || '';
      const checkbox = option.querySelector('input[type="checkbox"]');
      const isSelected = checkbox ? checkbox.checked : false;
      const isMatch = term === '' || tag.includes(term) || tag.includes(rawTerm);
      const shouldShow = isSelected || isMatch;
      option.classList.toggle('is-hidden', !shouldShow);
      if (shouldShow) {
        matches += 1;
      }
    });

    if (emptyState) {
      emptyState.hidden = matches !== 0;
    }
  };

  if (searchInput) {
    searchInput.addEventListener('input', applyFilter);
    searchInput.addEventListener('search', applyFilter);
  }

  if (optionsContainer) {
    optionsContainer.addEventListener('change', applyFilter);
  }

  applyFilter();
};

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', handleViewToggleClick);
  document.addEventListener('click', handleAddToCartClick);
  setupTagFilter();
});

document.addEventListener('buy:refresh', () => {
  const searchInput = document.querySelector('[data-tag-filter] [data-tag-search]');
  if (searchInput) {
    searchInput.dispatchEvent(new Event('input'));
  }
});
