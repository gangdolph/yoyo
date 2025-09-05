document.addEventListener('DOMContentLoaded', () => {
  const textarea = document.querySelector('.message-form textarea');
  if (textarea) {
    document.querySelectorAll('.message-tools button').forEach(btn => {
      const format = btn.dataset.format;
      const insert = btn.dataset.insert;
      btn.addEventListener('click', () => {
        if (typeof textarea.selectionStart !== 'number' || typeof textarea.selectionEnd !== 'number') {
          textarea.focus();
        }
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? 0;
        const value = textarea.value;
        if (format === 'bold') {
          textarea.value = value.slice(0, start) + '**' + value.slice(start, end) + '**' + value.slice(end);
          textarea.selectionStart = start + 2;
          textarea.selectionEnd = end + 2;
        } else if (format === 'italic') {
          textarea.value = value.slice(0, start) + '*' + value.slice(start, end) + '*' + value.slice(end);
          textarea.selectionStart = start + 1;
          textarea.selectionEnd = end + 1;
        } else if (insert) {
          textarea.value = value.slice(0, start) + insert + value.slice(end);
          textarea.selectionStart = textarea.selectionEnd = start + insert.length;
        }
        textarea.focus();
      });
    });
  }
});
