const managerRoot = document.querySelector('[data-shop-manager]');

if (managerRoot) {
  const services = window.ShopManagerServices || {};
  const tabs = Array.from(managerRoot.querySelectorAll('[data-manager-tab]'));
  const panels = new Map();
  const mountedControllers = new Set();

  tabs.forEach((tab) => {
    const panelId = tab.getAttribute('aria-controls');
    if (panelId) {
      const panel = document.getElementById(panelId);
      if (panel) {
        panels.set(tab, panel);
      }
    }
  });

  const setActiveTab = (tabKey) => {
    tabs.forEach((tab) => {
      const key = tab.dataset.managerTab;
      const isActive = key === tabKey;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      const panel = panels.get(tab);
      if (panel) {
        panel.hidden = !isActive;
        panel.classList.toggle('is-active', isActive);
      }
    });
    if (tabKey) {
      managerRoot.dataset.activeTab = tabKey;
    }

    const tabPanel = managerRoot.querySelector(`[data-manager-panel="${tabKey}"]`);
    if (tabPanel) {
      const repository = tabPanel.getAttribute('data-repository');
      if (repository && !mountedControllers.has(repository)) {
        const controller = services[repository];
        if (controller && typeof controller.mount === 'function') {
          try {
            controller.mount(tabPanel, {
              csrf: managerRoot.dataset.csrf || '',
              container: managerRoot,
            });
            mountedControllers.add(repository);
          } catch (error) {
            console.error('Shop manager controller mount failed:', error);
          }
        }
      }
    }
  };

  const applyHistory = (tabKey) => {
    if (!tabKey) {
      return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabKey);
    window.history.replaceState({ tab: tabKey }, '', url.toString());
  };

  const initialTab = managerRoot.dataset.activeTab || (tabs[0] && tabs[0].dataset.managerTab) || '';
  if (initialTab) {
    setActiveTab(initialTab);
    applyHistory(initialTab);
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const key = tab.dataset.managerTab;
      if (!key) {
        return;
      }
      setActiveTab(key);
      applyHistory(key);
      if (typeof services.onTabChange === 'function') {
        try {
          services.onTabChange(key, managerRoot);
        } catch (error) {
          console.error('Shop manager onTabChange failed:', error);
        }
      }
    });
  });

  window.addEventListener('popstate', (event) => {
    const stateTab = event.state && event.state.tab;
    const urlTab = new URL(window.location.href).searchParams.get('tab');
    const tabKey = stateTab || urlTab;
    if (tabKey) {
      setActiveTab(tabKey);
    }
  });

  managerRoot.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    const panel = form.closest('[data-manager-panel]');
    if (!panel) {
      return;
    }
    const repository = panel.getAttribute('data-repository');
    if (!repository) {
      return;
    }
    const controller = services[repository];
    if (!controller || typeof controller.handleAction !== 'function') {
      return;
    }
    try {
      const handled = controller.handleAction(form.dataset.managerAction || '', form, {
        event,
        csrf: managerRoot.dataset.csrf || '',
        container: managerRoot,
      });
      if (handled === true) {
        event.preventDefault();
      }
    } catch (error) {
      console.error('Shop manager action handler failed:', error);
    }
  });
}
