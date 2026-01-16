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
