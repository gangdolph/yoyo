// Square integration for purchasing marketplace memberships.

async function initMemberCheckout() {
  const container = document.getElementById('member-card-container');
  const form = document.getElementById('member-purchase-form');
  const tokenField = document.getElementById('member-token');
  const planField = document.getElementById('membership-plan');

  if (!container || !form || !tokenField || !planField || !window.Square) {
    return;
  }

  try {
    const appId = container.dataset.appId;
    const locationId = container.dataset.locationId;
    const payments = window.Square.payments(appId, locationId);
    const card = await payments.card();
    await card.attach('#member-card-container');

    async function handleSubmit(event) {
      event.preventDefault();

      if (!planField.value) {
        alert('Select a membership plan before checking out.');
        return;
      }

      try {
        const result = await card.tokenize();
        if (result.status === 'OK') {
          tokenField.value = result.token;
          form.removeEventListener('submit', handleSubmit);
          form.submit();
        } else {
          const message = result.errors && result.errors[0] ? result.errors[0].message : 'Unable to tokenize card.';
          alert(message);
        }
      } catch (err) {
        console.error('Membership tokenize failed', err);
        alert('We were unable to process your card. Please try again.');
      }
    }

    form.addEventListener('submit', handleSubmit);
  } catch (error) {
    console.error('Membership checkout initialisation failed', error);
  }
}

document.addEventListener('DOMContentLoaded', initMemberCheckout);
