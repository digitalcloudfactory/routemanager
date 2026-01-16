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
  const name = document.getElementById('filterName').value.trim();
  const minDist = document.getElementById('filterDistance').value.trim();
  const minElev = document.getElementById('filterElevation').value.trim();
  const type = document.getElementById('filterType').value;
  const tags = document.getElementById('filterTags').value.trim();

  // Apply filtering logic
  filteredRoutes = routes.filter(r => {
    const routeTags = (r.tags || '').split(',').map(t => t.trim().toLowerCase());

    return (
      (!name || r.name.toLowerCase().includes(name.toLowerCase())) &&
      (!minDist || r.distance_km >= parseFloat(minDist)) &&
      (!minElev || r.elevation >= parseFloat(minElev)) &&
      (!type || r.type == type) &&
      (!tags || tags.split(',').every(t => routeTags.includes(t.trim().toLowerCase())))
    );
  });

  renderTable(filteredRoutes);

  // --- Update URL query string ---
  const params = new URLSearchParams();
  if (name) params.set('name', name);
  if (minDist) params.set('minDist', minDist);
  if (minElev) params.set('minElev', minElev);
  if (type) params.set('type', type);
  if (tags) params.set('tags', tags);

  const newUrl = window.location.pathname + '?' + params.toString();
  window.history.replaceState({}, '', newUrl);

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
