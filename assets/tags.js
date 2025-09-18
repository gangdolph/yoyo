document.addEventListener('DOMContentLoaded', () => {
  const normalizeTag = (value) => {
    if (!value) {
      return null;
    }
    let tag = value.toLowerCase().trim();
    if (!tag) {
      return null;
    }
    tag = tag.replace(/[^a-z0-9\s_-]/g, '');
    tag = tag.replace(/\s+/g, '-');
    tag = tag.replace(/^[\-_]+|[\-_]+$/g, '');
    return tag || null;
  };

  const parseTags = (value) => {
    if (!value) {
      return [];
    }
    const parts = value.split(',');
    const seen = new Set();
    parts.forEach((part) => {
      const normalized = normalizeTag(part);
      if (normalized) {
        seen.add(normalized);
      }
    });
    return Array.from(seen);
  };

  document.querySelectorAll('[data-tag-editor]').forEach((editor) => {
    const listEl = editor.querySelector('[data-tag-list]');
    const input = editor.querySelector('[data-tag-source]');
    const store = editor.querySelector('[data-tag-store]');
    if (!listEl || !input || !store) {
      return;
    }

    let tags = parseTags(store.value);

    const render = () => {
      listEl.innerHTML = '';
      tags.forEach((tag) => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.textContent = tag;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'tag-chip-remove';
        remove.setAttribute('aria-label', `Remove tag ${tag}`);
        remove.textContent = '×';
        remove.addEventListener('click', () => {
          tags = tags.filter((t) => t !== tag);
          updateStore();
        });

        chip.appendChild(remove);
        listEl.appendChild(chip);
      });
    };

    const updateStore = () => {
      store.value = tags.join(', ');
      render();
    };

    const commitInput = () => {
      const raw = input.value;
      if (!raw) {
        return;
      }
      const parts = raw.split(',');
      parts.forEach((part) => {
        const normalized = normalizeTag(part);
        if (normalized && !tags.includes(normalized)) {
          tags.push(normalized);
        }
      });
      input.value = '';
      updateStore();
    };

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commitInput();
      } else if (event.key === 'Backspace' && input.value === '') {
        tags.pop();
        updateStore();
      }
    });

    input.addEventListener('blur', commitInput);

    updateStore();
  });
});
