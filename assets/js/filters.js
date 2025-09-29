const createDebounce = (fn, delay = 250) => {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
};

const serializeForm = (form) => {
  const formData = new FormData(form);
  const params = new URLSearchParams();
  formData.forEach((value, key) => {
    params.append(key, value);
  });
  return params;
};

const updateSelectOptions = (select, options) => {
  if (!select || !Array.isArray(options)) {
    return;
  }

  const preserveFocus = select === document.activeElement;
  select.innerHTML = '';

  if (options.length === 0) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'No options available';
    select.append(option);
    select.disabled = true;
    return;
  }

  options.forEach((opt) => {
    const option = document.createElement('option');
    option.value = opt.value;
    const countText = typeof opt.count === 'number' ? ` (${opt.count})` : '';
    option.textContent = `${opt.label}${countText}`;
    if (opt.selected) {
      option.selected = true;
    }
    if (opt.disabled && !opt.selected) {
      option.disabled = true;
    }
    select.append(option);
  });

  const allDisabled = options.every((opt) => opt.disabled && !opt.selected);
  select.disabled = allDisabled;

  if (!select.disabled && !options.some((opt) => opt.selected)) {
    select.selectedIndex = 0;
  }

  if (preserveFocus) {
    select.focus();
  }
};

const refreshTagControls = (form, tags) => {
  if (!Array.isArray(tags)) {
    return;
  }
  const tagContainer = form.querySelector('[data-tag-options]');
  const datalist = form.querySelector('datalist');
  if (tagContainer) {
    tagContainer.innerHTML = '';
    tags.forEach((tag) => {
      const label = document.createElement('label');
      label.className = 'tag-filter-option';
      label.dataset.tag = tag.value;

      const input = document.createElement('input');
      input.type = 'checkbox';
      input.name = 'tags[]';
      input.value = tag.value;
      if (tag.selected) {
        input.checked = true;
      }

      const span = document.createElement('span');
      span.textContent = tag.value;

      label.append(input, span);
      tagContainer.append(label);
    });
  }
  if (datalist) {
    datalist.innerHTML = '';
    tags.forEach((tag) => {
      const option = document.createElement('option');
      option.value = tag.value;
      datalist.append(option);
    });
  }
  const searchInput = form.querySelector('[data-tag-search]');
  if (searchInput) {
    searchInput.dispatchEvent(new Event('input'));
  }
};

const updateFormControls = (form, filters) => {
  if (!filters) {
    return;
  }

  updateSelectOptions(form.querySelector('[name="category"]'), filters.category);
  updateSelectOptions(form.querySelector('[name="subcategory"]'), filters.subcategory);
  updateSelectOptions(form.querySelector('[name="condition"]'), filters.condition);
  if (filters.brand) {
    updateSelectOptions(form.querySelector('[name="brand_id"]'), filters.brand);
  }
  if (filters.model) {
    updateSelectOptions(form.querySelector('[name="model_id"]'), filters.model);
  }
  if (filters.trade_type) {
    updateSelectOptions(form.querySelector('[name="trade_type"]'), filters.trade_type);
  }
  updateSelectOptions(form.querySelector('[name="sort"]'), filters.sort);
  updateSelectOptions(form.querySelector('[name="limit"]'), filters.limit);
  if (filters.tags) {
    refreshTagControls(form, filters.tags);
  }
};

const attachPaginationHandler = (form, resultsContainer, fetchFn, pageInput) => {
  resultsContainer.addEventListener('click', (event) => {
    const link = event.target.closest('.pagination a');
    if (!link) {
      return;
    }
    event.preventDefault();
    const url = new URL(link.href, window.location.href);
    const newPage = url.searchParams.get('page') || '1';
    if (pageInput) {
      pageInput.value = newPage;
    }
    fetchFn('pagination');
  });
};

const initFilterForm = (form) => {
  const endpoint = form.dataset.filterEndpoint || form.action || window.location.pathname;
  const resultsSelector = form.dataset.filterResults;
  const statusSelector = form.dataset.filterStatus;
  const resultsContainer = resultsSelector ? document.querySelector(resultsSelector) : null;
  const statusElement = statusSelector ? document.querySelector(statusSelector) : null;
  const pageInput = form.querySelector('[data-filter-page]');
  const contextInput = form.querySelector('[data-filter-context-value]');
  const defaultContext = form.dataset.filterContext || (contextInput ? contextInput.value : '');
  let abortController = null;
  let lastRequestId = 0;

  if (!resultsContainer) {
    return;
  }

  const finalize = (message) => {
    resultsContainer.removeAttribute('aria-busy');
    if (statusElement && message) {
      statusElement.textContent = message;
    }
  };

  const fetchListings = (trigger = 'change') => {
    if (abortController) {
      abortController.abort();
    }
    abortController = new AbortController();
    const currentController = abortController;
    const params = serializeForm(form);
    const contextValue = params.get('context') || defaultContext;
    if (contextValue) {
      params.set('context', contextValue);
    }

    const url = endpoint.includes('?') ? `${endpoint}&${params.toString()}` : `${endpoint}?${params.toString()}`;
    resultsContainer.setAttribute('aria-busy', 'true');
    if (statusElement) {
      statusElement.textContent = 'Loading results…';
    }
    const requestId = ++lastRequestId;

    fetch(url, {
      signal: currentController.signal,
      headers: {
        'Accept': 'application/json',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Request failed');
        }
        return response.json();
      })
      .then((payload) => {
        if (requestId !== lastRequestId || currentController.signal.aborted) {
          return;
        }
        if (!payload.success) {
          throw new Error(payload.error || 'Unable to load results');
        }
        resultsContainer.innerHTML = payload.results.html;
        if (pageInput) {
          pageInput.value = payload.results.page;
        }
        if (statusElement) {
          statusElement.textContent = `Updated results (${payload.results.total})`;
        }
        updateFormControls(form, payload.filters);
        resultsContainer.removeAttribute('aria-busy');

        const formData = serializeForm(form);
        formData.delete('context');
        const nextUrl = new URL(window.location.href);
        nextUrl.search = formData.toString();
        window.history.replaceState({}, '', nextUrl.toString());

        const context = payload.context || contextValue;
        if (context === 'buy') {
          document.dispatchEvent(new CustomEvent('buy:refresh'));
        } else if (context === 'trade') {
          document.dispatchEvent(new CustomEvent('trade:refresh'));
        }
      })
      .catch((error) => {
        if (currentController.signal.aborted) {
          return;
        }
        finalize('Unable to load results. Please try again.');
        console.error(error);
      });
  };

  const debouncedInput = createDebounce(() => fetchListings('input'));

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (pageInput) {
      pageInput.value = pageInput.value || '1';
    }
    fetchListings('submit');
  });

  form.addEventListener('change', (event) => {
    if (event.target === pageInput) {
      fetchListings('pagination');
      return;
    }
    if (pageInput) {
      pageInput.value = '1';
    }
    fetchListings('change');
  });

  form.addEventListener('input', (event) => {
    if (event.target.matches('[data-filter-input]')) {
      if (pageInput) {
        pageInput.value = '1';
      }
      debouncedInput();
    }
  });

  attachPaginationHandler(form, resultsContainer, fetchListings, pageInput);
};

window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-filter-form]').forEach((form) => {
    initFilterForm(form);
  });
});
