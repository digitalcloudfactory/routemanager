console.log('🔥 routes_shared.js loaded');

const panel = document.getElementById('filterPanel');

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

  });
  el.addEventListener('change', () => {

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
});
