(function () {
  const wizardRoot = document.querySelector('[data-service-wizard]');
  if (!wizardRoot) {
    return;
  }

  const form = wizardRoot.querySelector('[data-service-form]');
  const picker = wizardRoot.querySelector('[data-service-picker]');
  const stepsContainer = wizardRoot.querySelector('[data-step-container]');
  const footer = wizardRoot.querySelector('[data-wizard-footer]');

  if (!form || !picker || !stepsContainer || !footer) {
    return;
  }

  const backButton = footer.querySelector('[data-action="back"]');
  const nextButton = footer.querySelector('[data-action="next"]');
  const submitButton = footer.querySelector('[data-action="submit"]');

  const serviceOptions = Array.from(picker.querySelectorAll('[data-service-option]'));
  const flows = new Map();
  const stepElements = new Map();

  stepsContainer.querySelectorAll('[data-service-flow]').forEach((flow) => {
    const serviceId = flow.dataset.serviceFlow;
    if (!serviceId) {
      return;
    }
    flows.set(serviceId, flow);
    const map = new Map();
    flow.querySelectorAll('[data-step-id]').forEach((step) => {
      const stepId = step.dataset.stepId;
      if (stepId) {
        map.set(stepId, step);
      }
    });
    stepElements.set(serviceId, map);
  });

  if (serviceOptions.length === 0 || flows.size === 0) {
    return;
  }

  wizardRoot.classList.remove('no-js');
  wizardRoot.classList.add('js-enabled');

  const state = {
    serviceId: null,
    flow: null,
    history: [],
    currentIndex: -1,
    stepOrder: [],
  };

  function markActiveService(serviceId) {
    serviceOptions.forEach((option) => {
      const optionLabel = option.closest('.wizard-picker__option');
      if (!optionLabel) {
        return;
      }
      if (option.value === serviceId) {
        optionLabel.classList.add('is-active');
      } else {
        optionLabel.classList.remove('is-active');
      }
    });
  }

  function applyFieldState(flow, enabled) {
    const fields = flow.querySelectorAll('[data-field-name]');
    fields.forEach((field) => {
      field.disabled = !enabled;
      if (!enabled) {
        if (field.type === 'radio' || field.type === 'checkbox') {
          field.checked = false;
        } else if (field.tagName === 'SELECT') {
          field.selectedIndex = 0;
        } else if (field.type === 'file') {
          field.value = '';
        } else {
          field.value = '';
        }
      }

      if (field.type === 'checkbox') {
        field.required = false;
      } else if (field.dataset.required === 'true' && enabled) {
        field.required = true;
      } else if (field.required) {
        field.required = false;
      }
    });
  }

  function ensureStepElement(stepId) {
    if (!state.serviceId) {
      return null;
    }
    const map = stepElements.get(state.serviceId);
    if (!map) {
      return null;
    }
    return map.get(stepId) || null;
  }

  function displayError(stepEl, message) {
    const errorEl = stepEl.querySelector('[data-step-error]');
    if (!errorEl) {
      return;
    }
    if (message) {
      errorEl.textContent = message;
      errorEl.hidden = false;
      stepEl.classList.add('wizard-step--invalid');
    } else {
      errorEl.textContent = '';
      errorEl.hidden = true;
      stepEl.classList.remove('wizard-step--invalid');
    }
  }

  function getFieldValue(fieldName) {
    if (!state.flow) {
      return '';
    }
    const fields = Array.from(state.flow.querySelectorAll(`[data-field-name="${fieldName}"]`));
    if (fields.length === 0) {
      return '';
    }
    const first = fields[0];
    if (first.type === 'checkbox') {
      return fields.filter((field) => field.checked).map((field) => field.value);
    }
    if (first.type === 'radio') {
      const selected = fields.find((field) => field.checked);
      return selected ? selected.value : '';
    }
    return first.value;
  }

  function filterModelSelects() {
    if (!state.flow) {
      return;
    }
    const brandValue = getFieldValue('brand_id');
    const selects = state.flow.querySelectorAll('select[data-options-source="models"]');
    selects.forEach((select) => {
      const groups = Array.from(select.querySelectorAll('optgroup'));
      let hasVisibleOption = false;

      groups.forEach((group) => {
        const groupBrand = group.dataset.brandId || '';
        const matchesGroup = !brandValue || groupBrand === brandValue;
        group.hidden = !!brandValue && !matchesGroup;

        Array.from(group.querySelectorAll('option')).forEach((option) => {
          if (!option.value) {
            return;
          }
          const optionBrand = option.dataset.brandId || groupBrand;
          const visible = !brandValue || optionBrand === brandValue;
          option.hidden = !!brandValue && !visible;
          if (!visible && option.selected) {
            option.selected = false;
          }
          if (visible) {
            hasVisibleOption = true;
          }
        });
      });

      if (!brandValue) {
        groups.forEach((group) => {
          group.hidden = false;
          Array.from(group.querySelectorAll('option')).forEach((option) => {
            option.hidden = false;
          });
        });
      }

      if (!hasVisibleOption) {
        select.value = '';
      }
    });
  }

  function prepareStep(stepId) {
    const stepEl = ensureStepElement(stepId);
    if (!stepEl) {
      return false;
    }
    if (stepEl.dataset.component === 'select') {
      const select = stepEl.querySelector('select[data-options-source="models"]');
      if (select) {
        filterModelSelects();
      }
    }
    return true;
  }

  function showStep(stepId) {
    if (!state.flow) {
      return;
    }
    state.flow.querySelectorAll('[data-step-id]').forEach((step) => {
      const shouldShow = step.dataset.stepId === stepId;
      step.hidden = !shouldShow;
      if (!shouldShow) {
        displayError(step, '');
      }
    });
    state.currentIndex = state.history.indexOf(stepId);
    updateFooter();
  }

  function removeFutureSteps() {
    if (state.currentIndex < 0) {
      return;
    }
    state.history = state.history.slice(0, state.currentIndex + 1);
  }

  function resolveNextStep(stepEl) {
    if (!stepEl) {
      return null;
    }

    if (stepEl.dataset.component === 'options') {
      const selected = Array.from(stepEl.querySelectorAll('input:checked'));
      for (let i = 0; i < selected.length; i += 1) {
        const next = selected[i].dataset.nextStep;
        if (next) {
          return next;
        }
      }
    }

    if (stepEl.dataset.terminal === 'true') {
      return null;
    }

    const explicitNext = stepEl.dataset.defaultNext;
    if (explicitNext) {
      return explicitNext;
    }

    const stepId = stepEl.dataset.stepId;
    const currentIndex = state.stepOrder.indexOf(stepId);
    if (currentIndex >= 0 && currentIndex + 1 < state.stepOrder.length) {
      return state.stepOrder[currentIndex + 1];
    }
    return null;
  }

  function isFinalStep() {
    if (!state.serviceId || state.currentIndex < 0) {
      return false;
    }
    const currentStepId = state.history[state.currentIndex];
    const stepEl = ensureStepElement(currentStepId);
    if (!stepEl) {
      return false;
    }
    const next = resolveNextStep(stepEl);
    return next === null;
  }

  function updateFooter() {
    const hasService = !!state.serviceId;
    backButton.disabled = !hasService || state.currentIndex <= 0;

    if (!hasService) {
      nextButton.disabled = true;
      submitButton.disabled = true;
      return;
    }

    if (isFinalStep()) {
      nextButton.disabled = true;
      submitButton.disabled = false;
    } else {
      nextButton.disabled = false;
      submitButton.disabled = true;
    }
  }

  function validateStep(stepEl) {
    if (!stepEl) {
      return true;
    }
    displayError(stepEl, '');

    switch (stepEl.dataset.component) {
      case 'select': {
        const control = stepEl.querySelector('select');
        if (!control) {
          return true;
        }
        if (control.required && !control.value) {
          displayError(stepEl, 'Please select an option to continue.');
          control.focus();
          return false;
        }
        return true;
      }
      case 'textarea': {
        const control = stepEl.querySelector('textarea');
        if (!control) {
          return true;
        }
        if (control.required && !control.value.trim()) {
          displayError(stepEl, 'Please provide a response before continuing.');
          control.focus();
          return false;
        }
        return true;
      }
      case 'input': {
        const control = stepEl.querySelector('input.wizard-field__control');
        if (!control) {
          return true;
        }
        if (control.required && !control.value.trim()) {
          displayError(stepEl, 'This field is required.');
          control.focus();
          return false;
        }
        return true;
      }
      case 'options': {
        const inputs = Array.from(stepEl.querySelectorAll('input'));
        if (stepEl.dataset.stepRequired === 'true') {
          const hasSelection = inputs.some((input) => input.checked);
          if (!hasSelection) {
            displayError(stepEl, 'Please choose at least one option.');
            if (inputs[0]) {
              inputs[0].focus();
            }
            return false;
          }
        }
        return true;
      }
      case 'file': {
        const control = stepEl.querySelector('input[type="file"]');
        if (!control) {
          return true;
        }
        if (control.dataset.required === 'true' && control.files.length === 0) {
          displayError(stepEl, 'Please attach a file to continue.');
          control.focus();
          return false;
        }
        return true;
      }
      default:
        return true;
    }
  }

  function handleNext() {
    if (!state.serviceId || state.currentIndex < 0) {
      return;
    }
    const currentStepId = state.history[state.currentIndex];
    const stepEl = ensureStepElement(currentStepId);
    if (!stepEl || !validateStep(stepEl)) {
      return;
    }

    const nextStepId = resolveNextStep(stepEl);
    if (nextStepId === null) {
      updateFooter();
      return;
    }

    const nextStepEl = ensureStepElement(nextStepId);
    if (!nextStepEl) {
      updateFooter();
      return;
    }

    removeFutureSteps();
    state.history.push(nextStepId);
    state.currentIndex = state.history.length - 1;
    prepareStep(nextStepId);
    showStep(nextStepId);
  }

  function handleBack() {
    if (!state.serviceId || state.currentIndex <= 0) {
      return;
    }
    const previousStepId = state.history[state.currentIndex - 1];
    showStep(previousStepId);
  }

  function startService(serviceId) {
    if (!flows.has(serviceId)) {
      return;
    }

    serviceOptions.forEach((option) => {
      if (option.value === serviceId) {
        option.checked = true;
      }
    });

    flows.forEach((flow, id) => {
      const isActive = id === serviceId;
      flow.classList.toggle('is-active', isActive);
      flow.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      applyFieldState(flow, isActive);
      if (isActive) {
        state.flow = flow;
      } else {
        flow.querySelectorAll('[data-step-id]').forEach((step) => {
          step.hidden = false;
          displayError(step, '');
        });
      }
    });

    state.serviceId = serviceId;
    state.history = [];
    state.currentIndex = -1;
    state.stepOrder = [];

    const flow = flows.get(serviceId);
    if (!flow) {
      updateFooter();
      return;
    }

    state.stepOrder = Array.from(flow.querySelectorAll('[data-step-id]')).map((step) => step.dataset.stepId);
    const entryStep = flow.dataset.entryStep || state.stepOrder[0];
    if (!entryStep) {
      updateFooter();
      return;
    }

    state.history.push(entryStep);
    state.currentIndex = 0;

    flow.querySelectorAll('[data-step-id]').forEach((step) => {
      const isEntry = step.dataset.stepId === entryStep;
      step.hidden = !isEntry;
      displayError(step, '');
    });

    filterModelSelects();
    markActiveService(serviceId);
    updateFooter();
  }

  picker.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.matches('[data-service-option]')) {
      startService(target.value);
    }
  });

  form.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (target.dataset.fieldName === 'brand_id') {
      filterModelSelects();
    }
  });

  nextButton.addEventListener('click', handleNext);
  backButton.addEventListener('click', handleBack);

  const defaultService = wizardRoot.dataset.defaultService;
  const initialService = (defaultService && flows.has(defaultService))
    ? defaultService
    : (serviceOptions.find((option) => option.checked)?.value || serviceOptions[0].value);

  startService(initialService);
})();
