console.log('🔥 routes_shared.js loaded');

const DEBUG_FILTERS = true;
function dbg(...args) {
  if (DEBUG_FILTERS) console.log('[filters]', ...args);
}


let filterName;
let filterDistance;
let filterElevation;
let filterType;
let filterTags;
let filteredRoutes = [];

document.addEventListener('DOMContentLoaded', () => {
  // Cache filter elements
  filterName = document.getElementById('filterName');
  filterDistance = document.getElementById('filterDistance');
  filterElevation = document.getElementById('filterElevation');
  filterType = document.getElementById('filterType');
  filterTags = document.getElementById('filterTags');

  // If filter panel not present (safety)
  if (!filterName) return;

  // Attach input listeners

  dbg('started loading - fired');
[
  filterName,
  filterDistance,
  filterElevation,
  filterType,
  filterTags
].forEach(el => {
  if (!el) return;
  el.addEventListener('input', () => {
    applyFilters();
    updateURLFromFilters();
  });
  el.addEventListener('change', () => {
    applyFilters();
    updateURLFromFilters();
  });
});

  // Clear filters button
  document.getElementById('clearFilters')?.addEventListener('click', () => {
    clearFilters();
  });

  // Filter panel toggle
  const panel = document.getElementById('filterPanel');
  const openBtn = document.getElementById('openFilters');

  if (panel && openBtn) {
    openBtn.addEventListener('click', () => {
      panel.classList.toggle('open');
      panel.setAttribute(
        'aria-hidden',
        !panel.classList.contains('open')
      );
    });
  }

  // Load filters from URL on page load
  loadFiltersFromURL();
});

function applyFilters() {

dbg('applyFilters() called');

  const filterName = document.getElementById('filterName');
  if (!filterName) {
    dbg('applyFilters aborted: filterName not found');
    return;
  }
  
  const name = filterName.value.trim().toLowerCase();
  const minDist = parseFloat(filterDistance.value) || 0;
  const minElev = parseFloat(filterElevation.value) || 0;
  const type = filterType.value;
  const tags = filterTags.value
    .toLowerCase()
    .split(',')
    .map(t => t.trim())
    .filter(Boolean);

  filteredRoutes = routes.filter(r => {
    const routeTags = (r.tags || '')
      .split(',')
      .map(t => t.trim().toLowerCase());

    return (
      (!name || r.name.toLowerCase().includes(name)) &&
      (!minDist || r.distance_km >= minDist) &&
      (!minElev || r.elevation >= minElev) &&
      (!type || r.type == type) &&
      (!tags.length || tags.every(t => routeTags.includes(t)))
    );
  });

  // Render depending on page
  if (typeof renderTable === 'function') {
    renderTable(filteredRoutes);
  }

  if (typeof drawRoutes === 'function') {
    drawRoutes(filteredRoutes);
  }
}

function updateURLFromFilters() {

 dbg('updateURLFromFilters() called');
  
  const params = new URLSearchParams();

  if (filterName.value.trim()) params.set('name', filterName.value.trim());
  if (filterDistance.value) params.set('minDist', filterDistance.value);
  if (filterElevation.value) params.set('minElev', filterElevation.value);
  if (filterType.value) params.set('type', filterType.value);
  if (filterTags.value.trim()) params.set('tags', filterTags.value.trim());

  dbg('New URL params:', params.toString());
  
  const newUrl =
    window.location.pathname +
    (params.toString() ? '?' + params.toString() : '');

     dbg('Replacing URL with:', newUrl);
  
  history.replaceState({}, '', newUrl);
}

function clearFilters() {
  filterName.value = '';
  filterDistance.value = '';
  filterElevation.value = '';
  filterType.value = '';
  filterTags.value = '';

  applyFilters();
  updateURLFromFilters();
}

function loadFiltersFromURL() {
  dbg('loadFiltersFromURL()');
  
  const params = new URLSearchParams(window.location.search);
dbg('URL params detected:', params.toString());
  
  if (params.has('name')) filterName.value = params.get('name');
  if (params.has('minDist')) filterDistance.value = params.get('minDist');
  if (params.has('minElev')) filterElevation.value = params.get('minElev');
  if (params.has('type')) filterType.value = params.get('type');
  if (params.has('tags')) filterTags.value = params.get('tags');

  
 dbg('Filters restored from URL');
  
  applyFilters();
}

  const toggle = document.getElementById("themeToggle");
  const root = document.documentElement;

  if (toggle) {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    root.setAttribute("data-theme", savedTheme);
    toggle.textContent = savedTheme === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  }

  toggle.addEventListener("click", () => {
    const current = root.getAttribute("data-theme") || "light";
    const next = current === "light" ? "dark" : "light";

    root.setAttribute("data-theme", next);
    localStorage.setItem("theme", next);

    toggle.textContent = next === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  });
}
