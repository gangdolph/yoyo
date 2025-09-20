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

  const STORAGE_KEY = 'serviceWizardState';
  const STORAGE_VERSION = '1.0.0';
  const storage = (() => {
    try {
      return window.sessionStorage;
    } catch (error) {
      return null;
    }
  })();

  const state = {
    serviceId: null,
    flow: null,
    history: [],
    currentIndex: -1,
    stepOrder: [],
  };
  let suppressPersist = false;

  function buildTaxonomySignature() {
    const signature = [];
    flows.forEach((flow, serviceId) => {
      const stepIds = Array.from(flow.querySelectorAll('[data-step-id]'))
        .map((step) => step.dataset.stepId || '')
        .join('|');
      signature.push(`${serviceId}:${stepIds}`);
    });
    signature.sort();
    return signature.join(';');
  }

  const taxonomySignature = buildTaxonomySignature();

  function collectActiveFieldValues() {
    if (!state.flow) {
      return {};
    }

    const collected = {};
    const seen = new Set();
    state.flow.querySelectorAll('[data-field-name]').forEach((field) => {
      const fieldName = field.dataset.fieldName;
      if (!fieldName || seen.has(fieldName)) {
        return;
      }
      seen.add(fieldName);

      if (field instanceof HTMLInputElement && field.type === 'file') {
        return;
      }

      collected[fieldName] = getFieldValue(fieldName);
    });

    return collected;
  }

  function persistState() {
    if (!storage) {
      return;
    }

    if (!state.serviceId) {
      try {
        storage.removeItem(STORAGE_KEY);
      } catch (error) {
        // Ignore persistence errors.
      }
      return;
    }

    const payload = {
      version: STORAGE_VERSION,
      signature: taxonomySignature,
      serviceId: state.serviceId,
      currentStep: state.history[state.currentIndex] || null,
      history: state.history.slice(),
      fields: collectActiveFieldValues(),
    };

    try {
      storage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (error) {
      // Ignore persistence errors.
    }
  }

  function clearPersistedState() {
    if (!storage) {
      return;
    }

    try {
      storage.removeItem(STORAGE_KEY);
    } catch (error) {
      // Ignore persistence errors.
    }
  }

  function readPersistedState() {
    if (!storage) {
      return null;
    }

    let raw;
    try {
      raw = storage.getItem(STORAGE_KEY);
    } catch (error) {
      return null;
    }

    if (!raw) {
      return null;
    }

    try {
      const parsed = JSON.parse(raw);
      if (!parsed || parsed.version !== STORAGE_VERSION || parsed.signature !== taxonomySignature) {
        return null;
      }

      const serviceId = typeof parsed.serviceId === 'string' ? parsed.serviceId : '';
      if (!serviceId || !flows.has(serviceId)) {
        return null;
      }

      const map = stepElements.get(serviceId);
      if (!map) {
        return null;
      }

      const persistedFields = {};
      if (parsed.fields && typeof parsed.fields === 'object') {
        Object.keys(parsed.fields).forEach((key) => {
          persistedFields[key] = parsed.fields[key];
        });
      }

      const rawHistory = Array.isArray(parsed.history)
        ? parsed.history.filter((stepId) => typeof stepId === 'string' && map.has(stepId))
        : [];

      let currentStep = typeof parsed.currentStep === 'string' && map.has(parsed.currentStep)
        ? parsed.currentStep
        : null;

      if (currentStep && rawHistory.indexOf(currentStep) === -1) {
        rawHistory.push(currentStep);
      }

      if (!currentStep && rawHistory.length > 0) {
        currentStep = rawHistory[rawHistory.length - 1];
      }

      return {
        serviceId,
        currentStep,
        history: rawHistory,
        fields: persistedFields,
      };
    } catch (error) {
      return null;
    }
  }

  function restoreFieldValues(serviceId, fieldValues) {
    const flow = flows.get(serviceId);
    if (!flow || !fieldValues || typeof fieldValues !== 'object') {
      return;
    }

    Object.keys(fieldValues).forEach((fieldName) => {
      const fields = Array.from(flow.querySelectorAll(`[data-field-name="${fieldName}"]`));
      if (fields.length === 0) {
        return;
      }

      const value = fieldValues[fieldName];
      const first = fields[0];

      if (first instanceof HTMLInputElement && first.type === 'checkbox') {
        const values = Array.isArray(value) ? value.map((item) => String(item)) : [];
        fields.forEach((field) => {
          if (field instanceof HTMLInputElement) {
            field.checked = values.includes(field.value);
          }
        });
        return;
      }

      if (first instanceof HTMLInputElement && first.type === 'radio') {
        const selected = value != null ? String(value) : '';
        fields.forEach((field) => {
          if (field instanceof HTMLInputElement) {
            field.checked = field.value === selected;
          }
        });
        return;
      }

      if (first instanceof HTMLInputElement && first.type === 'file') {
        return;
      }

      if (first instanceof HTMLSelectElement) {
        const selectedValue = Array.isArray(value) ? (value[0] != null ? String(value[0]) : '') : value;
        fields.forEach((field) => {
          if (field instanceof HTMLSelectElement) {
            field.value = selectedValue != null ? String(selectedValue) : '';
          }
        });
        return;
      }

      if (first instanceof HTMLTextAreaElement) {
        const resolved = value != null ? String(value) : '';
        fields.forEach((field) => {
          if (field instanceof HTMLTextAreaElement) {
            field.value = resolved;
          }
        });
        return;
      }

      const resolvedValue = value != null ? String(value) : '';
      fields.forEach((field) => {
        if (field instanceof HTMLInputElement) {
          field.value = resolvedValue;
        }
      });
    });
  }

  const persistedState = readPersistedState();

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
    persistState();
  }

  function handleBack() {
    if (!state.serviceId || state.currentIndex <= 0) {
      return;
    }
    const previousStepId = state.history[state.currentIndex - 1];
    showStep(previousStepId);
    persistState();
  }

  function startService(serviceId, options = {}) {
    const { skipPersist = false } = options;
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

    if (!skipPersist) {
      persistState();
    }
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

  function handleFieldMutation(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (suppressPersist) {
      return;
    }
    const fieldName = target.dataset.fieldName;
    if (!fieldName) {
      return;
    }

    if (fieldName === 'brand_id') {
      filterModelSelects();
    }

    persistState();
  }

  form.addEventListener('change', handleFieldMutation);
  form.addEventListener('input', (event) => {
    const target = event.target;
    if (
      target instanceof HTMLInputElement
      || target instanceof HTMLTextAreaElement
      || target instanceof HTMLSelectElement
    ) {
      handleFieldMutation(event);
    }
  });

  nextButton.addEventListener('click', handleNext);
  backButton.addEventListener('click', handleBack);

  form.addEventListener('submit', () => {
    clearPersistedState();
  });

  form.addEventListener('reset', () => {
    suppressPersist = true;
    clearPersistedState();
    window.setTimeout(() => {
      suppressPersist = false;
    }, 0);
  });

  wizardRoot.addEventListener('click', (event) => {
    const target = event.target;
    if (target instanceof HTMLElement && target.dataset.action === 'reset') {
      suppressPersist = true;
      clearPersistedState();
      window.setTimeout(() => {
        suppressPersist = false;
      }, 0);
    }
  });

  const defaultService = wizardRoot.dataset.defaultService;
  const initialServiceCandidate = persistedState && persistedState.serviceId && flows.has(persistedState.serviceId)
    ? persistedState.serviceId
    : null;

  const initialService = initialServiceCandidate
    || ((defaultService && flows.has(defaultService))
      ? defaultService
      : (serviceOptions.find((option) => option.checked)?.value || serviceOptions[0].value));

  startService(initialService, { skipPersist: true });

  if (persistedState && persistedState.serviceId === initialService) {
    restoreFieldValues(initialService, persistedState.fields);
    filterModelSelects();

    const map = stepElements.get(initialService);
    if (map) {
      if (Array.isArray(persistedState.history) && persistedState.history.length > 0) {
        state.history = persistedState.history.slice();
        let currentStepId = persistedState.currentStep && map.has(persistedState.currentStep)
          ? persistedState.currentStep
          : state.history[state.history.length - 1];
        if (currentStepId && state.history.indexOf(currentStepId) === -1) {
          state.history.push(currentStepId);
        }
        if (currentStepId) {
          state.currentIndex = state.history.indexOf(currentStepId);
          prepareStep(currentStepId);
          showStep(currentStepId);
        }
      } else if (persistedState.currentStep && map.has(persistedState.currentStep)) {
        const currentStepId = persistedState.currentStep;
        if (state.history.indexOf(currentStepId) === -1) {
          state.history.push(currentStepId);
        }
        state.currentIndex = state.history.indexOf(currentStepId);
        prepareStep(currentStepId);
        showStep(currentStepId);
      }
    }

    persistState();
  } else {
    persistState();
  }
})();
