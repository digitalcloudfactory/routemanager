console.log('🔥🔥 routes_shared.js loaded safely');

const panel = document.getElementById('filterPanel');
const DEBUG_FILTERS = true;

function dbg(...args) {
  if (DEBUG_FILTERS) console.log('[filters]', ...args);
}

let filterName;
let filterNameNot;
let filterDistanceMin, filterDistanceMax, distValueDisplay;
let filterElevation;
let filterType;
let filterTags;
let filterCountry;
let filteredRoutes = [];

document.addEventListener('DOMContentLoaded', () => {
  // Cache filter elements safely
  filterName = document.getElementById('filterName');
  filterNameNot = document.getElementById('filterNameNot');
  filterElevation = document.getElementById('filterElevation');
  filterType = document.getElementById('filterType');
  filterTags = document.getElementById('filterTags');
  filterCountry = document.getElementById('filterCountry');
  
  filterDistanceMin = document.getElementById('filterDistanceMin');
  filterDistanceMax = document.getElementById('filterDistanceMax');
  distValueDisplay = document.getElementById('distValue');

  // FIX: If essential elements are completely absent from the file, 
  // do not throw an early exit return; just gracefully halt filter execution
  if (!filterDistanceMin || !filterDistanceMax) {
    console.warn("⚠️ Distance sliders not found on this page layout view.");
  }

  dbg('started loading - configuration parsing active');

  // Establish interactive double-range sliders only if they exist in the HTML structure
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

  // Define input elements we want to actively monitor
  const filterIds = ['filterName', 'filterNameNot', 'filterElevation', 'filterType', 'filterTags', 'filterCountry'];
  
  filterIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return; // FIX: Silently skip missing filter inputs instead of breaking script thread

    const triggerUpdate = (e) => {
        dbg(`Event (${e.type}) fired on: ${id}`);
        applyFilters();
        updateURLFromFilters();
    };

    el.addEventListener('input', triggerUpdate);
    el.addEventListener('change', triggerUpdate);
    
    if (el.type === 'checkbox') {
        el.addEventListener('click', triggerUpdate);
    }
  });

  const closeBtn = document.getElementById('closeFilters');
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

  const openBtn = document.getElementById('openFilters');
  if (panel && openBtn) {
    openBtn.addEventListener('click', () => {
      panel.classList.toggle('open');
      panel.setAttribute('aria-hidden', !panel.classList.contains('open'));
    });
  }

  // Restore active query properties from standard URL schema setup
  loadFiltersFromURL();
});

function applyFilters() {
  dbg('applyFilters() called');

  // Verify routes array array exists globally before filtering tracking routines
  if (typeof routes === 'undefined') {
    console.error("❌ Global routes data structure not loaded yet.");
    return;
  }

  // 1. Safe extraction patterns to avoid script fatal errors if elements are missing
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

  // 2. Perform safe item matching arrays loop
  filteredRoutes = routes.filter(r => {
    let nameMatch = true;
    if (nameQuery) {
      const contains = r.name && r.name.toLowerCase().includes(nameQuery);
      nameMatch = isNegated ? !contains : contains;
    }

    const routeTags = (r.tags || '').split(',').map(t => t.trim().toLowerCase());
    const tagsMatch = !tags.length || tags.every(t => routeTags.includes(t));
    const countryMatch = !selectedCountry || (r.country === selectedCountry);

    return (
      nameMatch &&
      tagsMatch &&
      (parseFloat(r.distance_km) >= minDist && parseFloat(r.distance_km) <= maxDist) &&
      (!minElev || parseFloat(r.elevation) >= minElev) &&
      (!type || r.type == type) &&
      (!selectedCountry || (r.country && r.country.trim() === selectedCountry.trim()))
    );
  });

  dbg(`Filters applied. Showing ${filteredRoutes.length} of ${routes.length} routes.`);

  // 3. Dynamically forward filtered subsets to active view handlers
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

    if (filterName && filterName.value.trim()) params.set('name', filterName.value.trim());
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
    dbg('clearFilters() called');
    
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
  dbg('loadFiltersFromURL()');
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
  
  dbg('Filters restored from URL parameters');
  applyFilters();
}

// Light/Dark System Engine
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
