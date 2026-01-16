function updateURLFromFilters() {
  const params = new URLSearchParams();

  const name = document.getElementById('filterName').value.trim();
  const minDist = document.getElementById('filterDistance').value;
  const minElev = document.getElementById('filterElevation').value;
  const type = document.getElementById('filterType').value;
  const tags = document.getElementById('filterTags').value.trim();

  if (name) params.set('name', name);
  if (minDist) params.set('minDist', minDist);
  if (minElev) params.set('minElev', minElev);
  if (type) params.set('type', type);
  if (tags) params.set('tags', tags);

  const newUrl =
    window.location.pathname +
    (params.toString() ? '?' + params.toString() : '');

  history.replaceState({}, '', newUrl);
}

function loadFiltersFromURL() {
  const params = new URLSearchParams(window.location.search);

  if (params.has('name')) filterName.value = params.get('name');
  if (params.has('minDist')) filterDistance.value = params.get('minDist');
  if (params.has('minElev')) filterElevation.value = params.get('minElev');
  if (params.has('type')) filterType.value = params.get('type');
  if (params.has('tags')) filterTags.value = params.get('tags');

  applyFilters();
}

document.addEventListener('DOMContentLoaded', loadFiltersFromURL);


function applyFilters() {
  const name = document.getElementById('filterName')?.value.toLowerCase() || '';
  const minDist = parseFloat(document.getElementById('filterDistance')?.value) || 0;
  const minElev = parseFloat(document.getElementById('filterElevation')?.value) || 0;
  const type = document.getElementById('filterType')?.value || '';

  const tagInput = (document.getElementById('filterTags')?.value || '')
    .toLowerCase()
    .split(',')
    .map(t => t.trim())
    .filter(Boolean);

  filteredRoutes = routes.filter(r => {
    const routeTags = (r.tags || '')
      .split(',')
      .map(t => t.trim().toLowerCase())
      .filter(Boolean);

    return (
      r.name.toLowerCase().includes(name) &&
      r.distance_km >= minDist &&
      r.elevation >= minElev &&
      (!type || String(r.type) === String(type)) &&
      (tagInput.length === 0 || tagInput.every(t => routeTags.includes(t)))
    );
  });

  // 🔥 THIS IS THE IMPORTANT PART
  if (typeof onFiltersUpdated === 'function') {
    onFiltersUpdated(filteredRoutes);
  } else if (typeof renderTable === 'function') {
    renderTable(filteredRoutes);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  ['filterName','filterDistance','filterElevation','filterType','filterTags']
    .forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', applyFilters);
    });

  const filterBtn = document.getElementById('openFilters');
  if (filterBtn) {
    filterBtn.onclick = () => {
      const panel = document.getElementById('filterPanel');
      panel.classList.toggle('open');
    };
  }
});
