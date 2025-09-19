document.addEventListener('DOMContentLoaded', () => {
  const gridBtn = document.querySelector('.view-grid');
  const listBtn = document.querySelector('.view-list');
  const container = document.getElementById('product-container');

  if (gridBtn && listBtn && container) {
    gridBtn.addEventListener('click', () => {
      container.classList.remove('list-view');
      gridBtn.classList.add('active');
      listBtn.classList.remove('active');
    });
    listBtn.addEventListener('click', () => {
      container.classList.add('list-view');
      listBtn.classList.add('active');
      gridBtn.classList.remove('active');
    });
  }

  document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const id = btn.dataset.id;
      fetch('/cart.php?action=add', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${encodeURIComponent(id)}`,
        credentials: 'same-origin'
      })
        .then(res => res.json())
        .then(data => {
          const link = document.querySelector('.cart-link a');
          let badge = document.querySelector('.cart-link .badge');
          if (badge) {
            badge.textContent = data.count;
          } else {
            badge = document.createElement('span');
            badge.className = 'badge';
            badge.textContent = data.count;
            link.appendChild(badge);
          }
          const toast = document.getElementById('cart-toast');
          if (toast) {
            toast.textContent = 'Added to cart';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
          }
        });
    });
  });

  const tagFilter = document.querySelector('[data-tag-filter]');
  if (tagFilter) {
    const searchInput = tagFilter.querySelector('[data-tag-search]');
    const tagOptions = Array.from(tagFilter.querySelectorAll('.tag-filter-option'));
    const emptyState = tagFilter.querySelector('.tag-filter-empty');

    if (searchInput && tagOptions.length) {
      const applyFilter = () => {
        const rawTerm = searchInput.value.trim().toLowerCase();
        const term = rawTerm.replace(/\s+/g, '-');
        let matches = 0;

        tagOptions.forEach(option => {
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

      searchInput.addEventListener('input', applyFilter);
      searchInput.addEventListener('search', applyFilter);

      tagOptions.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        if (checkbox) {
          checkbox.addEventListener('change', applyFilter);
        }
      });

      applyFilter();
    }
  }
});
