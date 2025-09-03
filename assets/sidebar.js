document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.side-nav-toggle');
  const sideNav = document.querySelector('.side-nav');
  const body = document.body;
  if (toggle && sideNav) {
    toggle.addEventListener('click', () => {
      sideNav.classList.toggle('open');
      body.classList.toggle('nav-open');
    });
  }
});
