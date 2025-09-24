// Wallet top-up Square integration.

async function initWalletTopup() {
  const container = document.getElementById('wallet-card-container');
  const form = document.getElementById('wallet-topup-form');
  if (!container || !form || !window.Square) {
    return;
  }

  try {
    const appId = container.dataset.appId;
    const locationId = container.dataset.locationId;
    const payments = window.Square.payments(appId, locationId);
    const card = await payments.card();
    await card.attach('#wallet-card-container');

    async function handleSubmit(event) {
      event.preventDefault();

      const amountField = form.querySelector('input[name="amount"]');
      const amount = parseFloat(amountField ? amountField.value : '0');
      if (Number.isNaN(amount) || amount <= 0) {
        alert('Enter a valid top-up amount.');
        return;
      }

      try {
        const result = await card.tokenize();
        if (result.status === 'OK') {
          document.getElementById('wallet-token').value = result.token;
          form.removeEventListener('submit', handleSubmit);
          form.submit();
        } else {
          const message = result.errors && result.errors[0] ? result.errors[0].message : 'Unable to tokenize card';
          alert(message);
        }
      } catch (err) {
        console.error(err);
        alert('An unexpected error occurred while processing your card.');
      }
    }

    form.addEventListener('submit', handleSubmit);
  } catch (error) {
    console.error('Wallet top-up initialisation failed', error);
  }
}

document.addEventListener('DOMContentLoaded', initWalletTopup);
