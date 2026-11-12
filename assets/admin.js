(() => {
  const input = document.querySelector('#f2di-files');
  if (!input) return;

  const countField = document.querySelector('#f2di-file-count');
  const status = document.querySelector('#f2di-file-status');
  const limit = Number(input.dataset.limit);

  function reportSelection() {
    const count = input.files.length;
    countField.value = count;
    input.setCustomValidity(count > limit ? 'Selecione até ' + limit + ' arquivos.' : '');
    status.textContent = count ? count + (count === 1 ? ' arquivo selecionado.' : ' arquivos selecionados.') : '';
  }

  input.addEventListener('change', reportSelection);
  reportSelection();

  const search = document.querySelector('#f2di-category-search');
  if (!search) return;

  const categories = document.querySelector('.f2di-categories');
  const empty = document.querySelector('#f2di-category-empty');
  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

  function filterList(list, query, showBranch = false) {
    let hasMatch = false;
    for (const item of list.children) {
      const label = item.firstElementChild;
      const matches = showBranch || normalize(label.textContent).includes(query);
      const nested = item.querySelector(':scope > ul');
      const childMatch = nested ? filterList(nested, query, matches) : false;
      item.hidden = !(matches || childMatch);
      hasMatch = hasMatch || !item.hidden;
    }
    return hasMatch;
  }

  search.hidden = false;
  search.addEventListener('input', () => {
    const found = filterList(categories, normalize(search.value.trim()));
    categories.hidden = !found;
    empty.hidden = found;
  });
})();
