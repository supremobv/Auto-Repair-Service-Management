document.addEventListener('click', event => {
  const control = event.target.closest('[data-action]');
  if (!control) return;
  if (control.dataset.action === 'sidebar') document.body.classList.toggle('nav-open');
  if (control.dataset.action === 'print') window.print();
});
document.addEventListener('submit', event => {
  const form = event.target;
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
  if (!form.checkValidity()) return;
  const button = event.submitter;
  if (button) { button.disabled = true; button.classList.add('submitting'); }
});
document.querySelectorAll('[data-local-date]').forEach(input => {
  if (!input.value) { const date = new Date(); date.setMinutes(date.getMinutes()-date.getTimezoneOffset()); input.value = date.toISOString().slice(0,10); }
});
