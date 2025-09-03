const translations = {
  en: {
    home: 'Home',
    about: 'About',
    help: 'Help/FAQ',
    dashboard: 'Dashboard',
    notifications: 'Notifications',
    messages: 'Messages',
    logout: 'Logout',
    login: 'Login',
    register: 'Register',
    search: 'Search...',
    send: 'Send',
    conversationWith: 'Conversation with'
  },
  es: {
    home: 'Inicio',
    about: 'Acerca de',
    help: 'Ayuda/FAQ',
    dashboard: 'Panel',
    notifications: 'Notificaciones',
    messages: 'Mensajes',
    logout: 'Salir',
    login: 'Iniciar sesión',
    register: 'Registrarse',
    search: 'Buscar...',
    send: 'Enviar',
    conversationWith: 'Conversación con'
  }
};

let lang = localStorage.getItem('lang') || 'en';
const flagImg = document.querySelector('#language-toggle img');

function applyTranslations() {
  document.documentElement.lang = lang;
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.dataset.i18n;
    if (translations[lang][key]) {
      el.textContent = translations[lang][key];
    }
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.dataset.i18nPlaceholder;
    if (translations[lang][key]) {
      el.placeholder = translations[lang][key];
    }
  });
  if (flagImg) {
    flagImg.src = `/assets/flags/${lang}.svg`;
    flagImg.alt = lang === 'en' ? 'English' : 'Español';
  }
}

applyTranslations();

document.getElementById('language-toggle').addEventListener('click', () => {
  lang = lang === 'en' ? 'es' : 'en';
  localStorage.setItem('lang', lang);
  applyTranslations();
});
