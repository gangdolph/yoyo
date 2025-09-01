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
      alert(`Added to cart: ${id}`);
      // TODO: integrate cart functionality
    });
  });
});
