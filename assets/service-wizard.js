(function () {
  const dataElement = document.getElementById('serviceWizardData');
  if (!dataElement) {
    return;
  }

  let config;
  try {
    config = JSON.parse(dataElement.textContent || '{}');
  } catch (error) {
    console.error('Failed to parse service wizard configuration', error);
    return;
  }

  const wizardRoot = document.querySelector('[data-service-wizard]');
  if (!wizardRoot) {
    return;
  }

  const form = wizardRoot.querySelector('[data-service-form]');
  const picker = wizardRoot.querySelector('[data-service-picker]');
  const stepsContainer = wizardRoot.querySelector('[data-step-container]');
  const footer = wizardRoot.querySelector('[data-wizard-footer]');
  const categoryField = form.querySelector('#serviceCategory');

  const backButton = footer.querySelector('[data-action="back"]');
  const nextButton = footer.querySelector('[data-action="next"]');
  const submitButton = footer.querySelector('[data-action="submit"]');

  const templates = {};
  wizardRoot.querySelectorAll('template[data-template]').forEach((tpl) => {
    templates[tpl.dataset.template] = tpl;
  });

  const taxonomy = new Map();
  (config.taxonomy || []).forEach((service) => {
    if (service && service.id) {
      taxonomy.set(service.id, service);
    }
  });

  const brandOptions = config.brands || [];
  const endpoints = config.endpoints || {};

  function escapeName(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }
    return value.replace(/([.#:[\],=])/g, '\\$1');
  }

  const state = {
    currentService: null,
    history: [],
    currentIndex: -1,
    modelsCache: new Map(),
  };

  function cloneTemplate(name) {
    const template = templates[name];
    if (!template) {
      throw new Error(`Missing template: ${name}`);
    }
    return template.content.firstElementChild.cloneNode(true);
  }

  function populateSelectOptions(select, options, placeholder) {
    select.innerHTML = '';
    if (placeholder) {
      const placeholderOption = document.createElement('option');
      placeholderOption.value = '';
      placeholderOption.textContent = placeholder;
      select.appendChild(placeholderOption);
    }
    options.forEach((option) => {
      const opt = document.createElement('option');
      opt.value = String(option.id);
      opt.textContent = option.name;
      select.appendChild(opt);
    });
  }

  function buildStepElement(stepId) {
    const stepConfig = state.currentService.flow.steps[stepId];
    const stepEl = cloneTemplate('step');
    stepEl.dataset.stepId = stepId;

    const titleEl = stepEl.querySelector('[data-step-title]');
    if (titleEl) {
      titleEl.textContent = stepConfig.heading || stepConfig.label;
    }

    const summaryEl = stepEl.querySelector('[data-step-summary]');
    if (summaryEl) {
      if (stepConfig.summary) {
        summaryEl.textContent = stepConfig.summary;
        summaryEl.hidden = false;
      } else {
        summaryEl.hidden = true;
      }
    }

    const bodyEl = stepEl.querySelector('[data-step-body]');
    if (!bodyEl) {
      return stepEl;
    }

    switch (stepConfig.component) {
      case 'select': {
        const field = cloneTemplate('select');
        const labelEl = field.querySelector('[data-field-label]');
        const control = field.querySelector('[data-field-control]');
        if (labelEl) {
          labelEl.textContent = stepConfig.label;
        }
        if (control) {
          control.name = stepConfig.name;
          control.required = !!stepConfig.required;
          control.dataset.stepField = stepId;
          if (stepConfig.optionsSource === 'brands') {
            populateSelectOptions(control, brandOptions, stepConfig.placeholder || 'Select an option');
          } else if (Array.isArray(stepConfig.options)) {
            populateSelectOptions(control, stepConfig.options, stepConfig.placeholder || 'Select an option');
          } else {
            populateSelectOptions(control, [], stepConfig.placeholder || 'Select an option');
          }
        }
        bodyEl.appendChild(field);
        break;
      }
      case 'textarea': {
        const field = cloneTemplate('textarea');
        const labelEl = field.querySelector('[data-field-label]');
        const control = field.querySelector('[data-field-control]');
        if (labelEl) {
          labelEl.textContent = stepConfig.label;
        }
        if (control) {
          control.name = stepConfig.name;
          control.required = !!stepConfig.required;
          control.dataset.stepField = stepId;
          if (stepConfig.placeholder) {
            control.placeholder = stepConfig.placeholder;
          }
        }
        bodyEl.appendChild(field);
        break;
      }
      case 'input': {
        const field = cloneTemplate('input');
        const labelEl = field.querySelector('[data-field-label]');
        const control = field.querySelector('[data-field-control]');
        if (labelEl) {
          labelEl.textContent = stepConfig.label;
        }
        if (control) {
          control.name = stepConfig.name;
          control.required = !!stepConfig.required;
          control.type = stepConfig.type || 'text';
          control.dataset.stepField = stepId;
          if (stepConfig.placeholder) {
            control.placeholder = stepConfig.placeholder;
          }
        }
        bodyEl.appendChild(field);
        break;
      }
      case 'options': {
        const field = cloneTemplate('options');
        const labelEl = field.querySelector('[data-field-label]');
        const listEl = field.querySelector('[data-options-list]');
        if (labelEl) {
          labelEl.textContent = stepConfig.label;
        }
        if (listEl && Array.isArray(stepConfig.options)) {
          stepConfig.options.forEach((option, index) => {
            const item = cloneTemplate('option-item');
            const input = item.querySelector('[data-option-input]');
            const optionLabel = item.querySelector('[data-option-label]');
            if (input) {
              input.type = stepConfig.inputType === 'checkbox' ? 'checkbox' : 'radio';
              input.name = stepConfig.name;
              input.value = option.value;
              if (stepConfig.required && stepConfig.inputType !== 'checkbox') {
                input.required = index === 0;
              }
              input.dataset.stepField = stepId;
              if (option.next) {
                input.dataset.nextStep = option.next;
              }
            }
            if (optionLabel) {
              optionLabel.textContent = option.label;
            }
            listEl.appendChild(item);
          });
        }
        bodyEl.appendChild(field);
        break;
      }
      case 'file': {
        const field = cloneTemplate('file');
        const labelEl = field.querySelector('[data-field-label]');
        const control = field.querySelector('[data-field-control]');
        if (labelEl) {
          labelEl.textContent = stepConfig.label;
        }
        if (control) {
          control.name = stepConfig.name;
          control.required = !!stepConfig.required;
          control.dataset.stepField = stepId;
          if (stepConfig.accept) {
            control.accept = stepConfig.accept;
          }
        }
        bodyEl.appendChild(field);
        break;
      }
      default: {
        console.warn('Unsupported step component', stepConfig.component);
        break;
      }
    }

    return stepEl;
  }

  function ensureStepRendered(stepId) {
    let stepEl = stepsContainer.querySelector(`[data-step-id="${stepId}"]`);
    if (!stepEl) {
      stepEl = buildStepElement(stepId);
      stepsContainer.appendChild(stepEl);
    }
    return stepEl;
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

  function validateStep(stepEl, stepConfig) {
    displayError(stepEl, '');
    switch (stepConfig.component) {
      case 'select': {
        const control = stepEl.querySelector('select');
        if (!control) {
          return true;
        }
        if (stepConfig.required && !control.value) {
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
        if (stepConfig.required && !control.value.trim()) {
          displayError(stepEl, 'Please provide a response before continuing.');
          control.focus();
          return false;
        }
        return true;
      }
      case 'input': {
        const control = stepEl.querySelector('input');
        if (!control) {
          return true;
        }
        if (stepConfig.required && !control.value.trim()) {
          displayError(stepEl, 'This field is required.');
          control.focus();
          return false;
        }
        return true;
      }
      case 'options': {
        const inputs = Array.from(stepEl.querySelectorAll('input'));
        if (!stepConfig.required) {
          return true;
        }
        const hasSelection = inputs.some((input) => input.checked);
        if (!hasSelection) {
          displayError(stepEl, 'Please choose at least one option.');
          if (inputs[0]) {
            inputs[0].focus();
          }
          return false;
        }
        return true;
      }
      case 'file': {
        const control = stepEl.querySelector('input[type="file"]');
        if (!control) {
          return true;
        }
        if (stepConfig.required && control.files.length === 0) {
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

  function resolveNextStep(stepConfig, stepEl) {
    if (stepConfig.component === 'options') {
      const selected = Array.from(stepEl.querySelectorAll('input:checked'));
      for (let i = 0; i < selected.length; i += 1) {
        const nextStep = selected[i].dataset.nextStep;
        if (nextStep) {
          return nextStep;
        }
      }
    }
    return typeof stepConfig.next === 'string' || stepConfig.next === null ? stepConfig.next : null;
  }

  function getFieldValue(fieldName) {
    const escaped = escapeName(fieldName);
    const field = form.querySelector(`[name="${escaped}"]`);
    if (!field) {
      return '';
    }
    if (field.type === 'checkbox') {
      const values = Array.from(form.querySelectorAll(`[name="${escaped}"]:checked`)).map((input) => input.value);
      return values;
    }
    return field.value;
  }

  function setActiveService(serviceId) {
    picker.querySelectorAll('[data-service-id]').forEach((button) => {
      if (button.dataset.serviceId === serviceId) {
        button.classList.add('is-active');
      } else {
        button.classList.remove('is-active');
      }
    });
  }

  function removeFutureSteps() {
    while (state.history.length - 1 > state.currentIndex) {
      const removedStepId = state.history.pop();
      const element = stepsContainer.querySelector(`[data-step-id="${removedStepId}"]`);
      if (element) {
        element.remove();
      }
    }
  }

  async function prepareStep(stepId) {
    const stepEl = ensureStepRendered(stepId);
    const stepConfig = state.currentService.flow.steps[stepId];
    if (stepConfig.component === 'select' && stepConfig.optionsSource === 'models') {
      const brandValue = getFieldValue('brand_id');
      const control = stepEl.querySelector('select');
      if (!brandValue) {
        populateSelectOptions(control, [], stepConfig.placeholder || 'Select an option');
        return false;
      }
      let models = state.modelsCache.get(brandValue);
      if (!models) {
        try {
          const response = await fetch(`${endpoints.models}?brand_id=${encodeURIComponent(brandValue)}`);
          models = await response.json();
          state.modelsCache.set(brandValue, Array.isArray(models) ? models : []);
        } catch (error) {
          console.error('Failed to load models', error);
          models = [];
          state.modelsCache.set(brandValue, []);
        }
      }
      populateSelectOptions(control, models, stepConfig.placeholder || 'Select an option');
    }
    return true;
  }

  function showStep(stepId) {
    stepsContainer.querySelectorAll('[data-step]').forEach((step) => {
      step.hidden = step.dataset.stepId !== stepId;
    });
    state.currentIndex = state.history.indexOf(stepId);
    updateFooter();
  }

  function isFinalStep() {
    if (!state.currentService || state.currentIndex < 0) {
      return false;
    }
    const currentStepId = state.history[state.currentIndex];
    const stepConfig = state.currentService.flow.steps[currentStepId];
    return stepConfig.next === null;
  }

  function updateFooter() {
    const hasService = !!state.currentService;
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

  async function handleNext() {
    if (!state.currentService || state.currentIndex < 0) {
      return;
    }
    const currentStepId = state.history[state.currentIndex];
    const stepConfig = state.currentService.flow.steps[currentStepId];
    const stepEl = ensureStepRendered(currentStepId);
    if (!validateStep(stepEl, stepConfig)) {
      return;
    }

    const nextStepId = resolveNextStep(stepConfig, stepEl);
    if (nextStepId === null) {
      updateFooter();
      return;
    }
    if (!state.currentService.flow.steps[nextStepId]) {
      console.warn('Unknown next step', nextStepId);
      updateFooter();
      return;
    }

    removeFutureSteps();
    state.history.push(nextStepId);
    const prepared = await prepareStep(nextStepId);
    if (!prepared) {
      state.history.pop();
      updateFooter();
      return;
    }
    showStep(nextStepId);
  }

  function handleBack() {
    if (!state.currentService || state.currentIndex <= 0) {
      return;
    }
    const previousIndex = state.currentIndex - 1;
    const previousStepId = state.history[previousIndex];
    state.currentIndex = previousIndex;
    showStep(previousStepId);
  }

  function resetWizard() {
    state.history = [];
    state.currentIndex = -1;
    state.currentService = null;
    state.modelsCache.clear();
    stepsContainer.innerHTML = '';
    form.reset();
    categoryField.value = '';
    picker.querySelectorAll('[data-service-id]').forEach((button) => button.classList.remove('is-active'));
    updateFooter();
  }

  async function startService(serviceId) {
    const service = taxonomy.get(serviceId);
    if (!service) {
      return;
    }
    resetWizard();
    state.currentService = service;
    categoryField.value = serviceId;
    setActiveService(serviceId);
    state.history = [service.flow.entry];
    await prepareStep(service.flow.entry);
    showStep(service.flow.entry);
  }

  function renderPicker() {
    picker.innerHTML = '';
    taxonomy.forEach((service) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'wizard-picker__option';
      button.dataset.serviceId = service.id;

      const title = document.createElement('span');
      title.className = 'wizard-picker__title';
      title.textContent = service.label;
      button.appendChild(title);

      if (service.summary) {
        const summary = document.createElement('span');
        summary.className = 'wizard-picker__summary';
        summary.textContent = service.summary;
        button.appendChild(summary);
      }

      button.addEventListener('click', () => startService(service.id));
      picker.appendChild(button);
    });
  }

  renderPicker();
  updateFooter();

  nextButton.addEventListener('click', () => {
    handleNext();
  });
  backButton.addEventListener('click', () => {
    handleBack();
  });
})();
