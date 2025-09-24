/*
 * Change: Folded legacy Store Manager behaviours (inventory adjustments and shipping updates) into the unified Shop Manager
 *         module so only one dashboard script powers seller workflows.
 */
const services = (window.ShopManagerServices = window.ShopManagerServices || {});

const managerRoot = document.querySelector('[data-shop-manager]');

const getAlertBox = (root) => (root ? root.querySelector('[data-manager-alert]') : null);

const clearAlert = (root) => {
  const alert = getAlertBox(root);
  if (!alert) {
    return;
  }
  alert.textContent = '';
  alert.classList.remove('is-error');
};

const showAlert = (root, message, isError = false) => {
  const alert = getAlertBox(root);
  if (!alert) {
    return;
  }
  alert.textContent = message;
  alert.classList.toggle('is-error', Boolean(isError));
};

const updateOrderSummary = (root, orderId, { statusLabel, trackingNumber }) => {
  if (!orderId) {
    return;
  }
  const ordersPanel = root.querySelector('#shop-manager-panel-orders');
  if (!ordersPanel) {
    return;
  }
  const row = ordersPanel.querySelector(`tr[data-order-id="${orderId}"]`);
  if (!row) {
    return;
  }
  const shippingCell = row.querySelector('.store-orders__shipping');
  if (!shippingCell) {
    return;
  }
  if (statusLabel) {
    const statusElement = shippingCell.querySelector('[data-field="status"]');
    if (statusElement) {
      statusElement.textContent = statusLabel;
    }
  }
  if (trackingNumber !== undefined) {
    const trackingWrapper = shippingCell.querySelector('[data-field="tracking"]');
    if (trackingWrapper) {
      trackingWrapper.textContent = '';
      if (trackingNumber) {
        const lineBreak = document.createElement('br');
        const small = document.createElement('small');
        small.textContent = `Tracking: ${trackingNumber}`;
        trackingWrapper.append(lineBreak);
        trackingWrapper.append(small);
      }
    }
  }
};

const toggleInventoryRow = (row, show) => {
  const editButton = row.querySelector('.store-inventory__edit');
  const form = row.querySelector('.store-inventory__form');
  if (!form || !editButton) {
    return;
  }
  form.hidden = !show;
  editButton.hidden = show;
  if (show) {
    const stockInput = form.querySelector('input[name="stock_delta"]');
    if (stockInput) {
      stockInput.focus();
    }
  }
};

if (managerRoot) {
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

if (!services.inventory) {
  services.inventory = {};
}

services.inventory.mount = (panel) => {
  panel.querySelectorAll('.store-inventory').forEach((row) => {
    const editButton = row.querySelector('.store-inventory__edit');
    const cancelButton = row.querySelector('[data-action="cancel"]');
    const form = row.querySelector('.store-inventory__form');
    if (editButton && form) {
      editButton.addEventListener('click', () => toggleInventoryRow(row, true));
    }
    if (cancelButton) {
      cancelButton.addEventListener('click', () => toggleInventoryRow(row, false));
    }
  });
};

services.inventory.handleAction = (action, form, context) => {
  if (action !== 'inventory_adjust') {
    return false;
  }

  const { event, container } = context;
  if (event) {
    event.preventDefault();
  }

  const row = form.closest('.store-inventory');
  if (!row) {
    return true;
  }

  clearAlert(container);

  const formData = new FormData(form);

  (async () => {
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Unable to update inventory.');
      }

      const stockCell = row.querySelector('[data-field="stock"]');
      const thresholdCell = row.querySelector('[data-field="reorder_threshold"]');
      const data = payload.data || {};
      if (stockCell && data.stock !== undefined) {
        stockCell.textContent = data.stock;
      }
      if (thresholdCell && data.reorder_threshold !== undefined) {
        thresholdCell.textContent = data.reorder_threshold;
      }

      const stockInput = form.querySelector('input[name="stock_delta"]');
      if (stockInput) {
        stockInput.value = '0';
      }

      showAlert(container, payload.message || 'Inventory updated.');
      toggleInventoryRow(row, false);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to update inventory.';
      showAlert(container, message, true);
    }
  })();

  return true;
};

if (!services.shipping) {
  services.shipping = {};
}

services.shipping.handleAction = (action, form, context) => {
  if (action !== 'shipping_status' && action !== 'shipping_tracking') {
    return false;
  }

  const { event, container } = context;
  if (event) {
    event.preventDefault();
  }

  const row = form.closest('.store-shipping');
  if (!row) {
    return true;
  }

  clearAlert(container);
  const formData = new FormData(form);

  (async () => {
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || (action === 'shipping_status' ? 'Unable to update order status.' : 'Unable to update tracking number.'));
      }

      const data = payload.data || {};
      const orderId = Number.parseInt(row.dataset.orderId || '', 10);

      if (action === 'shipping_status') {
        const statusLabel = data.status_label || data.status || '';
        const statusSelect = form.querySelector('select[name="status"]');
        if (statusSelect && data.status) {
          statusSelect.value = data.status;
        }
        if (!Number.isNaN(orderId)) {
          updateOrderSummary(container, orderId, { statusLabel, trackingNumber: undefined });
        }
      } else {
        const trackingValue = data.tracking_number || '';
        const trackingInput = form.querySelector('input[name="tracking_number"]');
        if (trackingInput) {
          trackingInput.value = trackingValue;
        }
        if (!Number.isNaN(orderId)) {
          updateOrderSummary(container, orderId, { statusLabel: undefined, trackingNumber: trackingValue });
        }
      }

      const successMessage = payload.message || (action === 'shipping_status' ? 'Fulfillment status updated.' : 'Tracking updated.');
      showAlert(container, successMessage);
    } catch (error) {
      const message = error instanceof Error ? error.message : (action === 'shipping_status' ? 'Unable to update order status.' : 'Unable to update tracking number.');
      showAlert(container, message, true);
    }
  })();

  return true;
};
