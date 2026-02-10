console.log('🔥🔥 routes_shared.js loaded');

const panel = document.getElementById('filterPanel');

const DEBUG_FILTERS = true;
function dbg(...args) {
  if (DEBUG_FILTERS) console.log('[filters]', ...args);
}


let filterName;
let filterNameNot;
let filterDistance;
let filterElevation;
let filterType;
let filterTags;
let filteredRoutes = [];

document.addEventListener('DOMContentLoaded', () => {
  // Cache filter elements
  filterName = document.getElementById('filterName');
  filterNameNot = document.getElementById('filterNameNot');
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
  filterNameNot,
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


const closeBtn = document.getElementById('closeFilters');
  
if (closeBtn && panel) {
  closeBtn.addEventListener('click', (e) => {
    e.preventDefault();
    panel.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
  });
}

  
  // Clear filters button
  document.getElementById('clearFilters')?.addEventListener('click', () => {
    clearFilters();
  });

  // Filter panel toggle
  
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

  const filterNameEl = document.getElementById('filterName');
  const filterNameNotEl = document.getElementById('filterNameNot');
  
  if (!filterNameEl) {
    dbg('applyFilters aborted: filterName not found');
    return;
  }

  // 1. Gather current filter values
  const nameQuery = filterNameEl.value.trim().toLowerCase();
  const isNegated = filterNameNotEl ? filterNameNotEl.checked : false;
  
  const minDist = parseFloat(filterDistance.value) || 0;
  const minElev = parseFloat(filterElevation.value) || 0;
  const type = filterType.value;
  const tags = filterTags.value
    .toLowerCase()
    .split(',')
    .map(t => t.trim())
    .filter(Boolean);

  // 2. Execute the filter
  filteredRoutes = routes.filter(r => {
    // Handle Name Logic
    let nameMatch = true;
    if (nameQuery) {
      const contains = r.name.toLowerCase().includes(nameQuery);
      // If negated is true, we want routes that DON'T contain the string
      nameMatch = isNegated ? !contains : contains;
    }

    // Handle Tags Logic
    const routeTags = (r.tags || '')
      .split(',')
      .map(t => t.trim().toLowerCase());
    const tagsMatch = !tags.length || tags.every(t => routeTags.includes(t));

    // Combined criteria
    return (
      nameMatch &&
      tagsMatch &&
      (!minDist || r.distance_km >= minDist) &&
      (!minElev || r.elevation >= minElev) &&
      (!type || r.type == type)
    );
  });

  dbg(`Filters applied. Showing ${filteredRoutes.length} of ${routes.length} routes.`);

  // 3. Render results to UI
  if (typeof renderTable === 'function') {
    renderTable(filteredRoutes);
  }

  if (typeof drawRoutes === 'function') {
    drawRoutes(filteredRoutes);
  }
}

function updateURLFromFilters() {

 dbg('updateURLFromFilters() called');
 if (!filterName) return;
  
  const params = new URLSearchParams();

  if (filterName.value.trim()) params.set('name', filterName.value.trim());
  if (filterDistance.value) params.set('minDist', filterDistance.value);
  if (filterElevation.value) params.set('minElev', filterElevation.value);
  if (filterType.value) params.set('type', filterType.value);
  if (filterTags.value.trim()) params.set('tags', filterTags.value.trim());
  if (filterNameNot.checked) params.set('notName', '1');
  
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
  filterNameNot.checked = false;
  
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
  if (params.has('notName')) filterNameNot.checked = params.get('notName') === '1';
  
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
