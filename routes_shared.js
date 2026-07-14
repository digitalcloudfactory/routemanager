console.log('🔥🔥 routes_shared.js loaded safely');

const DEBUG_FILTERS = true;
function dbg(...args) {
  if (DEBUG_FILTERS) console.log('[filters]', ...args);
}

/**
 * Haversine Formula: Calculates distance in km between two lat/lng pairs
 */
function getHaversineDistanceKm(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Keep these global so map.php can reference them
let filterName, filterNameNot, filterDistanceMin, filterDistanceMax, distValueDisplay;
let filterElevationMin, filterElevationMax, elevValueDisplay;
let filterType, filterTags, filterCountry, panel;
let filteredRoutes = [];

document.addEventListener('DOMContentLoaded', () => {
  dbg('DOMContentLoaded fired - initializing elements safely...');

  // 1. Assign all elements INSIDE the DOMContentLoaded safety net
  panel = document.getElementById('filterPanel');
  filterName = document.getElementById('filterName');
  filterNameNot = document.getElementById('filterNameNot');
  filterType = document.getElementById('filterType');
  filterTags = document.getElementById('filterTags');
  filterCountry = document.getElementById('filterCountry');

  // Distance Controls
  filterDistanceMin = document.getElementById('filterDistanceMin');
  filterDistanceMax = document.getElementById('filterDistanceMax');
  distValueDisplay = document.getElementById('distValue');

  // Elevation Controls
  filterElevationMin = document.getElementById('filterElevationMin');
  filterElevationMax = document.getElementById('filterElevationMax');
  elevValueDisplay = document.getElementById('elevValue');

  const openBtn = document.getElementById('openFilters');
  const closeBtn = document.getElementById('closeFilters');

  // 2. Setup Distance Sliders if they exist
  const distRangeUpdate = (e) => {
    if (!filterDistanceMin || !filterDistanceMax) return;
    if (parseFloat(filterDistanceMin.value) > parseFloat(filterDistanceMax.value)) {
      if (e.target.id === 'filterDistanceMin') filterDistanceMin.value = filterDistanceMax.value;
      else filterDistanceMax.value = filterDistanceMin.value;
    }
    if (distValueDisplay) {
      distValueDisplay.textContent = `${filterDistanceMin.value} - ${filterDistanceMax.value} km`;
    }
    applyFilters();
    updateURLFromFilters();
  };

  if (filterDistanceMin) filterDistanceMin.addEventListener('input', distRangeUpdate);
  if (filterDistanceMax) filterDistanceMax.addEventListener('input', distRangeUpdate);

  // 3. Setup Elevation Sliders if they exist
  const elevRangeUpdate = (e) => {
    if (!filterElevationMin || !filterElevationMax) return;
    if (parseFloat(filterElevationMin.value) > parseFloat(filterElevationMax.value)) {
      if (e.target.id === 'filterElevationMin') filterElevationMin.value = filterElevationMax.value;
      else filterElevationMax.value = filterElevationMin.value;
    }
    if (elevValueDisplay) {
      elevValueDisplay.textContent = `${filterElevationMin.value} - ${filterElevationMax.value} m`;
    }
    applyFilters();
    updateURLFromFilters();
  };

  if (filterElevationMin) filterElevationMin.addEventListener('input', elevRangeUpdate);
  if (filterElevationMax) filterElevationMax.addEventListener('input', elevRangeUpdate);

// 4. Setup Input Watchers for Text/Select/Checkbox Inputs safely
  const filterIds = ['filterName', 'filterNameNot', 'filterType', 'filterTags', 'filterCountry', 'filterCityInput', 'filterCityLat', 'filterCityLng'];
  filterIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;

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

  // 5. Setup Panel Toggle Click Events
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

  // 6. Fire off initial filter loading pass
  loadFiltersFromURL();
});

function applyFilters() {
  dbg('applyFilters() executing...');
  if (typeof routes === 'undefined') {
    console.error("❌ Global 'routes' array is missing!");
    return;
  }

  // Read selected city coordinates from filter_panel inputs
  const targetCityLat = parseFloat(document.getElementById('filterCityLat')?.value);
  const targetCityLng = parseFloat(document.getElementById('filterCityLng')?.value);
  const hasCityFilter = !isNaN(targetCityLat) && !isNaN(targetCityLng);


if (hasCityFilter) {
    dbg(`🌆 Active City Filter: "${cityInputVal}" @ [Lat: ${targetCityLat}, Lng: ${targetCityLng}] (10km Radius)`);
  } else if (cityInputVal) {
    dbg(`⚠️ City text "${cityInputVal}" present, but valid Lat/Lng coordinates are missing!`);
  } else {
    dbg(`🌆 No City Filter active.`);
  }

  const nameQuery = filterName ? filterName.value.trim().toLowerCase() : '';
  const isNegated = filterNameNot ? filterNameNot.checked : false;
  const selectedCountry = filterCountry ? filterCountry.value : '';
  const type = filterType ? filterType.value : '';
  
  const minDist = filterDistanceMin ? (parseFloat(filterDistanceMin.value) || 0) : 0;
  const maxDist = filterDistanceMax ? (parseFloat(filterDistanceMax.value) || 9999) : 9999;
  
  const minElev = filterElevationMin ? (parseFloat(filterElevationMin.value) || 0) : 0;
  const maxElev = filterElevationMax ? (parseFloat(filterElevationMax.value) || 10000) : 10000;

  const tags = filterTags && filterTags.value
    ? filterTags.value.toLowerCase().split(',').map(t => t.trim()).filter(Boolean)
    : [];

  let cityPassedCount = 0;
  let cityFailedCount = 0;

  // Single-pass filtering across all conditions
  filteredRoutes = routes.filter(r => {
    // 1. City Start Radius Check (10 km)
    if (hasCityFilter) {
      if (!r.summary_polyline) {
        dbg(`❌ Route ${r.route_id} ("${r.name}") excluded: No summary_polyline available.`);
        cityFailedCount++;
        return false;
      }
      try {
        const decodedPoints = polyline.decode(r.summary_polyline);
        if (decodedPoints && decodedPoints.length > 0) {
          const startLat = decodedPoints[0][0];
          const startLng = decodedPoints[0][1];
          const distKm = getHaversineDistanceKm(targetCityLat, targetCityLng, startLat, startLng);

          if (distKm > 10) {
            cityFailedCount++;
            return false;
          } else {
            cityPassedCount++;
          }
        } else {
          cityFailedCount++;
          return false;
        }
      } catch (err) {
        console.error("Error decoding polyline for route:", r.route_id, err);
        cityFailedCount++;
        return false;
      }
    }

    // 2. Route Name Search
    if (nameQuery) {
      const contains = r.name && r.name.toLowerCase().includes(nameQuery);
      if (isNegated ? contains : !contains) return false;
    }

    // 3. Tags Filter
    if (tags.length > 0) {
      const routeTags = (r.tags || '').split(',').map(t => t.trim().toLowerCase());
      if (!tags.every(t => routeTags.includes(t))) return false;
    }

    // 4. Distance Filter
    const dist = parseFloat(r.distance_km || r.distance || 0);
    if (dist < minDist || dist > maxDist) return false;

    // 5. Elevation Filter
    const elev = parseFloat(r.elevation || r.elevation_gain || r.total_elevation_gain || 0);
    if (elev < minElev || elev > maxElev) return false;

    // 6. Discipline/Type Filter
    if (type && r.type != type) return false;

    // 7. Country Filter
    if (selectedCountry && (!r.country || r.country.trim() !== selectedCountry.trim())) return false;

    return true;
  });

  if (hasCityFilter) {
    dbg(`📍 City Filter Results: ${cityPassedCount} routes within 10km, ${cityFailedCount} excluded.`);
  }

  dbg(`Filters finished. Showing ${filteredRoutes.length} of ${routes.length} routes.`);

  // --- Call map renderer if it exists ---
  if (typeof drawRoutes === 'function') {
    drawRoutes(filteredRoutes);
    console.log('drawRoutes Called -- Map Function');
  }

  // --- Call table renderer if it exists ---
  if (typeof renderTable === 'function') {
    renderTable(filteredRoutes);
    console.log('renderTable Called -- Table Function');
  }
}

function updateURLFromFilters() {
    if (!filterName) return;
    const params = new URLSearchParams();

    // Text & Checkbox filters
    if (filterName.value.trim()) params.set('name', filterName.value.trim());
    if (filterNameNot && filterNameNot.checked) params.set('notName', '1');

    // Distance Sliders
    if (filterDistanceMin && filterDistanceMin.value != 0) params.set('minDist', filterDistanceMin.value);
    if (filterDistanceMax && filterDistanceMax.value != 400) params.set('maxDist', filterDistanceMax.value);
    
    // Elevation Sliders
    if (filterElevationMin && filterElevationMin.value != 0) params.set('minElev', filterElevationMin.value);
    if (filterElevationMax && filterElevationMax.value != 10000) params.set('maxElev', filterElevationMax.value);

    // Dropdowns & Tags
    if (filterCountry && filterCountry.value) params.set('country', filterCountry.value);
    if (filterType && filterType.value) params.set('type', filterType.value);
    const tagsVal = filterTags ? filterTags.value.trim() : '';
    if (tagsVal) params.set('tags', tagsVal);

// --- CITY RADIUS FILTER URL SYNC ---
    const cityInput = document.getElementById('filterCityInput');
    const cityLat = document.getElementById('filterCityLat');
    const cityLng = document.getElementById('filterCityLng');

    if (cityInput && cityInput.value.trim()) params.set('city', cityInput.value.trim());
    if (cityLat && cityLat.value) params.set('lat', cityLat.value);
    if (cityLng && cityLng.value) params.set('lng', cityLng.value);

    // Update browser URL without reloading page
    const queryString = params.toString();
    const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
    history.replaceState({}, '', newUrl);
}

function clearFilters() {
    if (filterName) filterName.value = '';
    if (filterNameNot) filterNameNot.checked = false;
    if (filterType) filterType.value = '';
    if (filterTags) filterTags.value = '';
    if (filterCountry) filterCountry.value = '';

    const filterCityLat = document.getElementById('filterCityLat');
    const filterCityLng = document.getElementById('filterCityLng');
    const filterCityInput = document.getElementById('filterCityInput');
    if (filterCityLat) filterCityLat.value = '';
    if (filterCityLng) filterCityLng.value = '';
    if (filterCityInput) filterCityInput.value = '';
  
    // Reset Distance
    if (filterDistanceMin) filterDistanceMin.value = 0;
    if (filterDistanceMax) filterDistanceMax.value = 400;
    if (distValueDisplay) distValueDisplay.textContent = "0 - 400 km";

    // Reset Elevation
    if (filterElevationMin) filterElevationMin.value = 0;
    if (filterElevationMax) filterElevationMax.value = 10000;
    if (elevValueDisplay) elevValueDisplay.textContent = "0 - 10000 m";

    applyFilters();
    updateURLFromFilters();
}

function loadFiltersFromURL() {
  dbg('loadFiltersFromURL() checking strings...');
  const params = new URLSearchParams(window.location.search);

  // Restore Distance
  if (filterDistanceMin) filterDistanceMin.value = params.get('minDist') || 0;
  if (filterDistanceMax) filterDistanceMax.value = params.get('maxDist') || 400;
  if (distValueDisplay && filterDistanceMin && filterDistanceMax) {
    distValueDisplay.textContent = `${filterDistanceMin.value} - ${filterDistanceMax.value} km`;
  }

  // Restore Elevation
  if (filterElevationMin) filterElevationMin.value = params.get('minElev') || 0;
  if (filterElevationMax) filterElevationMax.value = params.get('maxElev') || 10000;
  if (elevValueDisplay && filterElevationMin && filterElevationMax) {
    elevValueDisplay.textContent = `${filterElevationMin.value} - ${filterElevationMax.value} m`;
  }

  // Restore Text / Select Controls
  if (params.has('name') && filterName) filterName.value = params.get('name');
  if (params.has('country') && filterCountry) filterCountry.value = params.get('country');
  if (params.has('type') && filterType) filterType.value = params.get('type');
  if (params.has('tags') && filterTags) filterTags.value = params.get('tags');
  if (params.has('notName') && filterNameNot) filterNameNot.checked = params.get('notName') === '1';

// --- RESTORE CITY RADIUS FROM URL ---
  const cityInput = document.getElementById('filterCityInput');
  const cityLat = document.getElementById('filterCityLat');
  const cityLng = document.getElementById('filterCityLng');

  if (cityInput && params.has('city')) cityInput.value = params.get('city');
  if (cityLat && params.has('lat')) cityLat.value = params.get('lat');
  if (cityLng && params.has('lng')) cityLng.value = params.get('lng');
  
  // Re-run filter logic with updated state
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