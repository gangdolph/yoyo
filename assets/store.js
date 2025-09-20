const storeContainer = document.querySelector('.store');
if (storeContainer) {
  const alertBox = storeContainer.querySelector('.store__alert');
  const tabs = Array.from(storeContainer.querySelectorAll('.store__tab'));
  const panels = new Map();

  tabs.forEach((tab) => {
    const panelId = tab.getAttribute('aria-controls');
    if (!panelId) {
      return;
    }
    const panel = document.getElementById(panelId);
    if (!panel) {
      return;
    }
    panels.set(tab, panel);
    tab.addEventListener('click', () => {
      tabs.forEach((other) => {
        const panelForTab = panels.get(other);
        const isActive = other === tab;
        other.classList.toggle('is-active', isActive);
        other.setAttribute('aria-selected', isActive ? 'true' : 'false');
        if (panelForTab) {
          panelForTab.hidden = !isActive;
          panelForTab.classList.toggle('is-active', isActive);
        }
      });
    });
  });

  const showMessage = (message, isError = false) => {
    if (!alertBox) {
      return;
    }
    alertBox.textContent = message;
    alertBox.classList.toggle('is-error', Boolean(isError));
  };

  const clearMessage = () => {
    if (!alertBox) {
      return;
    }
    alertBox.textContent = '';
    alertBox.classList.remove('is-error');
  };

  const updateOrderSummary = (orderId, { statusLabel, trackingNumber }) => {
    const row = document.querySelector(`#store-panel-orders tr[data-order-id="${orderId}"]`);
    if (!row) {
      return;
    }
    const shippingCell = row.querySelector('.store-orders__shipping');
    if (!shippingCell) {
      return;
    }
    const statusEl = shippingCell.querySelector('[data-field="status"]');
    if (statusEl && statusLabel !== undefined && statusLabel) {
      statusEl.textContent = statusLabel;
    }
    if (trackingNumber !== undefined) {
      const trackingWrapper = shippingCell.querySelector('[data-field="tracking"]');
      if (trackingWrapper) {
        trackingWrapper.textContent = '';
        if (trackingNumber) {
          const small = document.createElement('small');
          small.textContent = `Tracking: ${trackingNumber}`;
          trackingWrapper.append(document.createElement('br'));
          trackingWrapper.append(small);
        }
      }
    }
  };

  const handleInventoryToggle = (row, show) => {
    const editButton = row.querySelector('.store-inventory__edit');
    const form = row.querySelector('.store-inventory__form');
    if (!editButton || !form) {
      return;
    }
    if (show) {
      form.hidden = false;
      editButton.hidden = true;
      const stockInput = form.querySelector('input[name="stock_delta"]');
      if (stockInput) {
        stockInput.focus();
      }
    } else {
      form.hidden = true;
      editButton.hidden = false;
    }
  };

  const handleInventorySubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const row = form.closest('.store-inventory');
    if (!row) {
      return;
    }
    const formData = new FormData(form);
    clearMessage();

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
      const nextStock = payload.data?.stock ?? payload.data?.quantity;
      if (stockCell && nextStock !== undefined) {
        stockCell.textContent = nextStock;
      }
      if (thresholdCell && payload.data?.reorder_threshold !== undefined) {
        thresholdCell.textContent = payload.data.reorder_threshold;
      }

      const stockInput = form.querySelector('input[name="stock_delta"]');
      if (stockInput) {
        stockInput.value = '0';
      }
      showMessage(payload.message || 'Inventory updated.');
      handleInventoryToggle(row, false);
    } catch (error) {
      showMessage(error instanceof Error ? error.message : 'Unable to update inventory.', true);
    }
  };

  storeContainer.querySelectorAll('.store-inventory').forEach((row) => {
    const editButton = row.querySelector('.store-inventory__edit');
    const form = row.querySelector('.store-inventory__form');
    const cancelButton = row.querySelector('[data-action="cancel"]');

    if (editButton && form) {
      editButton.addEventListener('click', () => handleInventoryToggle(row, true));
      form.addEventListener('submit', handleInventorySubmit);
    }
    if (cancelButton) {
      cancelButton.addEventListener('click', () => handleInventoryToggle(row, false));
    }
  });

  const handleStatusSubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const row = form.closest('.store-shipping');
    if (!row) {
      return;
    }
    const formData = new FormData(form);
    clearMessage();

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Unable to update order status.');
      }

      const statusLabel = payload.data?.status_label ?? payload.data?.status ?? '';
      const statusSelect = form.querySelector('select[name="status"]');
      if (statusSelect && payload.data?.status) {
        statusSelect.value = payload.data.status;
      }
      const orderId = Number.parseInt(row.dataset.orderId || '', 10);
      if (!Number.isNaN(orderId)) {
        updateOrderSummary(orderId, { statusLabel, trackingNumber: undefined });
      }
      showMessage(payload.message || 'Fulfillment status updated.');
    } catch (error) {
      showMessage(error instanceof Error ? error.message : 'Unable to update order status.', true);
    }
  };

  const handleTrackingSubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const row = form.closest('.store-shipping');
    if (!row) {
      return;
    }
    const formData = new FormData(form);
    clearMessage();

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Unable to update tracking number.');
      }

      const trackingInput = form.querySelector('input[name="tracking_number"]');
      if (trackingInput) {
        trackingInput.value = payload.data?.tracking_number || '';
      }

      const orderId = Number.parseInt(row.dataset.orderId || '', 10);
      if (!Number.isNaN(orderId)) {
        updateOrderSummary(orderId, {
          statusLabel: undefined,
          trackingNumber: payload.data?.tracking_number || '',
        });
      }

      showMessage(payload.message || 'Tracking updated.');
    } catch (error) {
      showMessage(error instanceof Error ? error.message : 'Unable to update tracking number.', true);
    }
  };

  storeContainer.querySelectorAll('.store-shipping__status-form').forEach((form) => {
    form.addEventListener('submit', handleStatusSubmit);
  });

  storeContainer.querySelectorAll('.store-shipping__tracking-form').forEach((form) => {
    form.addEventListener('submit', handleTrackingSubmit);
  });
}
