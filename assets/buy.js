document.addEventListener('DOMContentLoaded', () => {
  const gridBtn = document.querySelector('.view-grid');
  const listBtn = document.querySelector('.view-list');
  const container = document.getElementById('product-container');

  const showToast = message => {
    const toast = document.createElement('div');
    toast.textContent = message;
    Object.assign(toast.style, {
      position: 'fixed',
      bottom: '1rem',
      right: '1rem',
      background: '#333',
      color: '#fff',
      padding: '0.5rem 1rem',
      borderRadius: '4px',
      zIndex: 1000
    });
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  };

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
      fetch('cart.php?action=add', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${encodeURIComponent(id)}`,
        credentials: 'same-origin'
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showToast('Added to cart');
            const cartLink = document.querySelector('.cart-link a');
            if (cartLink) {
              let badge = cartLink.querySelector('.badge');
              if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge';
                cartLink.appendChild(badge);
              }
              badge.textContent = data.count;
            }
          }
        });
    });
  });
});
