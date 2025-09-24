const STATUS_LABELS = {
  open: 'Open',
  countered: 'Countered',
  accepted: 'Accepted',
  declined: 'Declined',
  expired: 'Expired',
  cancelled: 'Cancelled',
};

const applyStatusLabel = (status) => STATUS_LABELS[status] || status;

const updateOfferRow = (row, status) => {
  if (!row) {
    return;
  }
  row.dataset.offerStatus = status;
  const statusBadge = row.querySelector('[data-offer-status]');
  if (statusBadge) {
    statusBadge.textContent = applyStatusLabel(status);
    const normalised = String(status || '')
      .toLowerCase()
      .replace(/[^a-z0-9_-]/g, '-');
    statusBadge.className = `offer-status badge offer-status--${normalised}`;
  }
  const actions = row.querySelector('[data-offer-actions]');
  if (actions) {
    actions.innerHTML = `<span class="listing-offers__note">Updated: ${applyStatusLabel(status)}.</span>`;
  }
};

const setOffersMessage = (container, message, isError = false) => {
  if (!container) {
    return;
  }
  container.textContent = message || '';
  container.hidden = !message;
  if (!message) {
    container.classList.remove('listing-offers__message--error');
    return;
  }
  container.classList.toggle('listing-offers__message--error', Boolean(isError));
};

document.addEventListener('DOMContentLoaded', () => {
  const offersContainer = document.querySelector('[data-offers]');
  if (!offersContainer) {
    return;
  }

  const messageBox = offersContainer.querySelector('[data-offers-message]');

  offersContainer.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    const action = form.dataset.offerAction;
    if (!action) {
      return;
    }
    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    const formData = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(async (response) => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) {
          const error = payload && payload.error ? payload.error : 'Unable to update the offer right now.';
          throw new Error(error);
        }
        const status = payload.offer && payload.offer.status ? String(payload.offer.status) : '';
        if (status) {
          const row = form.closest('[data-offer-row]');
          updateOfferRow(row, status);
        }
        const successMessage = payload.message
          || (status ? `Offer ${applyStatusLabel(status).toLowerCase()} successfully.` : 'Offer updated.');
        setOffersMessage(messageBox, successMessage, false);
      })
      .catch((error) => {
        const message = error instanceof Error ? error.message : 'Unable to update the offer right now.';
        setOffersMessage(messageBox, message, true);
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
        }
      });
  });
});
