// Square Web Payments entry point for checkout

async function initSquare() {
  const container = document.getElementById('card-container');
  if (!container || !window.Square) {
    console.error('Square.js failed to load.');
    return;
  }

  const appId = container.dataset.appId;
  const locationId = container.dataset.locationId;
  const payments = window.Square.payments(appId, locationId);
  const card = await payments.card();
  await card.attach('#card-container');

  const form = document.getElementById('payment-form');
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    try {
      const result = await card.tokenize();
      if (result.status === 'OK') {
        document.getElementById('token').value = result.token;
        form.submit();
      } else {
        const message = result.errors && result.errors[0] ? result.errors[0].message : 'Tokenization failed';
        alert(message);
      }
    } catch (err) {
      console.error(err);
      alert('An unexpected error occurred');
    }
  });
}

document.addEventListener('DOMContentLoaded', initSquare);
