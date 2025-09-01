document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.side-nav-toggle');
  const sideNav = document.querySelector('.side-nav');
  if (toggle && sideNav) {
    toggle.addEventListener('click', () => {
      sideNav.classList.toggle('open');
    });
  }
});
