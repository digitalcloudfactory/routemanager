console.log('🔥🔥 routes_shared.js loaded safely');

const DEBUG_FILTERS = true;
function dbg(...args) {
  if (DEBUG_FILTERS) console.log('[filters]', ...args);
}

// Keep these global so map.php can reference them, but don't assign them yet!
let filterName, filterNameNot, filterDistanceMin, filterDistanceMax, distValueDisplay;
let filterElevation, filterType, filterTags, filterCountry, panel;
let filteredRoutes = [];

document.addEventListener('DOMContentLoaded', () => {
  dbg('DOMContentLoaded fired - initializing elements safely...');

  // 1. Assign all elements INSIDE the DOMContentLoaded safety net
  panel = document.getElementById('filterPanel');
  filterName = document.getElementById('filterName');
  filterNameNot = document.getElementById('filterNameNot');
  filterElevation = document.getElementById('filterElevation');
  filterType = document.getElementById('filterType');
  filterTags = document.getElementById('filterTags');
  filterCountry = document.getElementById('filterCountry');
  filterDistanceMin = document.getElementById('filterDistanceMin');
  filterDistanceMax = document.getElementById('filterDistanceMax');
  distValueDisplay = document.getElementById('distValue');

  const openBtn = document.getElementById('openFilters');
  const closeBtn = document.getElementById('closeFilters');

  // 2. Setup Distance Sliders if they exist
  const rangeUpdate = (e) => {
    if (!filterDistanceMin || !filterDistanceMax) return;
    if (parseFloat(filterDistanceMin.value) > parseFloat(filterDistanceMax.value)) {
      if (e.target.id === 'filterDistanceMin') filterDistanceMin.value = filterDistanceMax.value;
      else filterDistanceMax.value = filterDistanceMin.value;
    }
    if (distValueDisplay) {
      distValueDisplay.textContent = `${filterDistanceMin.value} - ${filterDistanceMax.value}`;
    }
    applyFilters();
    updateURLFromFilters();
  };

  if (filterDistanceMin) filterDistanceMin.addEventListener('input', rangeUpdate);
  if (filterDistanceMax) filterDistanceMax.addEventListener('input', rangeUpdate);

  // 3. Setup Input Watchers safely
  const filterIds = ['filterName', 'filterNameNot', 'filterElevation', 'filterType', 'filterTags', 'filterCountry'];
  filterIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return; // Skip if missing, do not crash!

    const triggerUpdate = (e) => {
        dbg(`Event (${e.type}) fired on element: ${id}`);
        applyFilters();
        updateURLFromFilters();
    };

    el.addEventListener('input', triggerUpdate);
    el.addEventListener('change', triggerUpdate);
    if (el.type === 'checkbox') {
        el.addEventListener('click', triggerUpdate);
    }
  });

  // 4. Setup Panel Toggle Click Events
  if (openBtn && panel) {
    dbg('✅ Filters button and panel bound successfully.');
    openBtn.addEventListener('click', (e) => {
      e.preventDefault();
      panel.classList.add('open');
      panel.setAttribute('aria-hidden', 'false');
    });
  } else {
    console.warn('⚠️ Could not find #openFilters or #filterPanel in the layout.');
  }

  if (closeBtn && panel) {
    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
    });
  }

  document.getElementById('clearFilters')?.addEventListener('click', () => {
    clearFilters();
  });

  // 5. Fire off initial filter loading pass
  loadFiltersFromURL();
});

function applyFilters() {
  dbg('applyFilters() executing...');
  if (typeof routes === 'undefined') {
    console.error("❌ Global 'routes' array is missing!");
    return;
  }

  const nameQuery = filterName ? filterName.value.trim().toLowerCase() : '';
  const isNegated = filterNameNot ? filterNameNot.checked : false;
  const selectedCountry = filterCountry ? filterCountry.value : '';
  const type = filterType ? filterType.value : '';
  
  const minDist = filterDistanceMin ? (parseFloat(filterDistanceMin.value) || 0) : 0;
  const maxDist = filterDistanceMax ? (parseFloat(filterDistanceMax.value) || 9999) : 9999;
  const minElev = filterElevation ? (parseFloat(filterElevation.value) || 0) : 0;

  const tags = filterTags && filterTags.value
    ? filterTags.value.toLowerCase().split(',').map(t => t.trim()).filter(Boolean)
    : [];

  filteredRoutes = routes.filter(r => {
    let nameMatch = true;
    if (nameQuery) {
      const contains = r.name && r.name.toLowerCase().includes(nameQuery);
      nameMatch = isNegated ? !contains : contains;
    }

    const routeTags = (r.tags || '').split(',').map(t => t.trim().toLowerCase());
    const tagsMatch = !tags.length || tags.every(t => routeTags.includes(t));

    return (
      nameMatch &&
      tagsMatch &&
      (parseFloat(r.distance_km) >= minDist && parseFloat(r.distance_km) <= maxDist) &&
      (!minElev || parseFloat(r.elevation) >= minElev) &&
      (!type || r.type == type) &&
      (!selectedCountry || (r.country && r.country.trim() === selectedCountry.trim()))
    );
  });

  dbg(`Filters finished. Showing ${filteredRoutes.length} of ${routes.length} routes.`);

  // --- UPDATED LAYER: Call map renderer if it exists ---
  if (typeof drawRoutes === 'function') {
    drawRoutes(filteredRoutes);
    console.log('drawRoutes Called -- Map Function');
  }

  // --- NEW LAYER: Call table renderer if it exists ---
  if (typeof renderTable === 'function') {
    renderTable(filteredRoutes);
    console.log('renderTable Called -- Table Function');
  }
}

function updateURLFromFilters() {
    if (!filterName) return;
    const params = new URLSearchParams();

    if (filterName.value.trim()) params.set('name', filterName.value.trim());
    if (filterNameNot && filterNameNot.checked) params.set('notName', '1');
    if (filterDistanceMin && filterDistanceMin.value != 0) params.set('minDist', filterDistanceMin.value);
    if (filterDistanceMax && filterDistanceMax.value != 400) params.set('maxDist', filterDistanceMax.value);
    if (filterCountry && filterCountry.value) params.set('country', filterCountry.value);
    if (filterElevation && filterElevation.value) params.set('minElev', filterElevation.value);
    if (filterType && filterType.value) params.set('type', filterType.value);
    
    const tagsVal = filterTags ? filterTags.value.trim() : '';
    if (tagsVal) params.set('tags', tagsVal);

    const queryString = params.toString();
    const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
    history.replaceState({}, '', newUrl);
}

function clearFilters() {
    if (filterName) filterName.value = '';
    if (filterNameNot) filterNameNot.checked = false;
    if (filterElevation) filterElevation.value = '';
    if (filterType) filterType.value = '';
    if (filterTags) filterTags.value = '';
    if (filterCountry) filterCountry.value = '';
  
    if (filterDistanceMin) filterDistanceMin.value = 0;
    if (filterDistanceMax) filterDistanceMax.value = 400;
    if (distValueDisplay) distValueDisplay.textContent = "0 - 400";

    applyFilters();
    updateURLFromFilters();
}

function loadFiltersFromURL() {
  dbg('loadFiltersFromURL() checking strings...');
  const params = new URLSearchParams(window.location.search);

  if (filterDistanceMin) filterDistanceMin.value = params.get('minDist') || 0;
  if (filterDistanceMax) filterDistanceMax.value = params.get('maxDist') || 400;
  if (distValueDisplay && filterDistanceMin && filterDistanceMax) {
    distValueDisplay.textContent = `${filterDistanceMin.value} - ${filterDistanceMax.value}`;
  }

  if (params.has('name') && filterName) filterName.value = params.get('name');
  if (params.has('country') && filterCountry) filterCountry.value = params.get('country');
  if (params.has('minElev') && filterElevation) filterElevation.value = params.get('minElev');
  if (params.has('type') && filterType) filterType.value = params.get('type');
  if (params.has('tags') && filterTags) filterTags.value = params.get('tags');
  if (params.has('notName') && filterNameNot) filterNameNot.checked = params.get('notName') === '1';
  
  applyFilters();
}

// Light/Dark Toggle
const toggle = document.getElementById("themeToggle");
const root = document.documentElement;
if (toggle) {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    root.setAttribute("data-theme", savedTheme);
    toggle.textContent = savedTheme === "dark" ? "☀️ Light mode" : "🌙 Dark mode";
  }
  toggle.addEventListener("click", () => {
    const current = root.getAttribute("data-theme") || "light";
    const next = current === "light" ? "dark" : "light";
    root.setAttribute("data-theme", next);
    localStorage.setItem("theme", next);
    toggle.textContent = next === "dark" ? "☀️ Light mode" : "🌙 Dark mode";
  });
}
