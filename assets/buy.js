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
});
