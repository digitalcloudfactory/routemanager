<style>
/* ==========================================================================
   ANTI-BONK PREMIUM SIDEBAR FILTER MODULE
   ========================================================================== */
#filterPanel {
  position: fixed;
  top: 0;
  right: 0;
  width: 380px;
  height: 100%;
  background: #ffffff;
  box-shadow: -8px 0 32px rgba(15, 23, 42, 0.08);
  border-left: 1px solid #e2e8f0;
  padding: 2.25rem 1.75rem;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  z-index: 2000;
  overflow-y: auto;
  box-sizing: border-box;
}

#filterPanel.open {
  transform: translateX(0);
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 1.25rem;
  margin-bottom: 2rem;
}

.filter-header h2 {
  font-family: 'Inter', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
}

.close-panel-btn {
  background: #f1f5f9;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-size: 0.9rem;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.close-panel-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.filter-group {
  margin-bottom: 1.75rem;
}

.filter-group label {
  display: block;
  font-family: 'Inter', sans-serif;
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.6rem;
}

/* Crisp, premium input & select fields */
.filter-input, .filter-select {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background-color: #ffffff;
  color: #0f172a;
  font-family: 'Inter', sans-serif;
  font-size: 0.92rem;
  font-weight: 500;
  box-sizing: border-box;
  transition: all 0.15s ease;
}

.filter-input::placeholder {
  color: #94a3b8;
}

.filter-input:focus, .filter-select:focus {
  outline: none;
  border-color: #0284c7;
  box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.08);
}

/* Custom crisp look for checkbox layout module */
.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 10px;
  font-size: 0.88rem !important;
  color: #334155 !important;
  font-weight: 500 !important;
  text-transform: none !important;
  letter-spacing: normal !important;
  cursor: pointer;
  margin-top: 0.5rem;
  user-select: none;
}

.checkbox-label input {
  margin: 0;
  width: 18px;
  height: 18px;
  accent-color: #0284c7;
  cursor: pointer;
}

/* Premium Dynamic Distance Range Component styling */
.crisp-badge {
  float: right;
  font-family: 'Inter', sans-serif;
  font-weight: 700; 
  font-size: 0.78rem; 
  padding: 3px 8px;
  background-color: #0f172a;
  border-radius: 6px;
  color: #ffffff;
  text-transform: none;
  letter-spacing: normal;
}

.range-slider-wrapper {
  position: relative;
  height: 30px;
  margin-top: 1rem;
}

.range-slider-wrapper input[type="range"] {
  position: absolute;
  width: 100%;
  height: 6px;
  margin: 0;
  background: none;
  pointer-events: none;
  appearance: none;
  -webkit-appearance: none;
}

/* Cross-browser precision slider handles configuration */
.range-slider-wrapper input[type="range"]::-webkit-slider-thumb {
  height: 18px;
  width: 18px;
  border-radius: 50%;
  background: #0284c7;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
  cursor: pointer;
  pointer-events: auto;
  appearance: none;
  -webkit-appearance: none;
  margin-top: -6px;
  transition: transform 0.1s ease;
}
.range-slider-wrapper input[type="range"]::-webkit-slider-thumb:active {
  transform: scale(1.15);
}

.range-slider-wrapper input[type="range"]::-moz-range-thumb {
  height: 14px;
  width: 14px;
  border-radius: 50%;
  background: #0284c7;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
  cursor: pointer;
  pointer-events: auto;
  border: none;
}

.range-slider-wrapper input[type="range"]::-webkit-slider-runnable-track {
  width: 100%;
  height: 6px;
  background: #e2e8f0;
  border-radius: 3px;
}

/* High-contrast Action controls wrapper bottom panel anchor */
.action-footer {
  border-top: 1px solid #f1f5f9; 
  margin-top: 2.5rem; 
  padding-top: 1.5rem;
}

.btn-reset {
  width: 100%;
  font-family: 'Inter', sans-serif;
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.btn-reset:hover {
  background: #e2e8f0;
  color: #0f172a;
  border-color: #cbd5e1;
}
</style>

<aside id="filterPanel" aria-hidden="true">
  <div class="filter-header">
    <h2>Filters</h2>
    <button type="button" class="close-panel-btn" id="closeFilters" aria-label="Close">✕</button>
  </div>

  <div class="filter-group">
    <label for="filterName">Route Name</label>
    <input id="filterName" class="filter-input" type="text" placeholder="Search track title...">
    
    <label class="checkbox-label">
      <input id="filterNameNot" type="checkbox"> Exclude this name (NOT logic)
    </label>
  </div>

  <div class="filter-group">
    <label>Distance <span id="distValue" class="crisp-badge">0 - 400 km</span></label>
    <div class="range-slider-wrapper">
      <input id="filterDistanceMin" type="range" min="0" max="400" step="1" value="0">
      <input id="filterDistanceMax" type="range" min="0" max="400" step="1" value="400">
    </div>
  </div>

  <div class="filter-group">
    <label for="filterElevation">Min Elevation</label>
    <input id="filterElevation" class="filter-input" type="number" min="0" placeholder="0 meters">
  </div>

  <div class="filter-group">
    <label for="filterType">Activity Type</label>
    <select id="filterType" class="filter-select">
      <option value="">All Disciplines</option>
      <option value="1">Ride</option>
      <option value="2">Run</option>
      <option value="3">Walk</option>
      <option value="6">Gravel</option>
    </select>
  </div>

  <div class="filter-group">
    <label for="filterCountry">Country</label>
    <select id="filterCountry" class="filter-select">
      <option value="">All Countries</option>
      <?php 
      if (!empty($countries) && is_array($countries)): 
        foreach ($countries as $country): 
          if (empty($country)) continue;
      ?>
          <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
      <?php 
        endforeach; 
      endif; 
      ?>
    </select>
  </div>

  <div class="filter-group">
    <label for="filterTags">Tags</label>
    <input id="filterTags" class="filter-input" type="text" placeholder="e.g. gravel, weekend, mallorca">
  </div>

  <div class="action-footer">
    <button class="btn-reset" type="button" id="clearFilters">
      Reset Filters
    </button>
  </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('filterPanel');
    const openBtn = document.getElementById('openFilters');
    const closeBtn = document.getElementById('closeFilters');
    
    if (openBtn && panel) {
        openBtn.addEventListener('click', () => {
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
        });
    }

    if (closeBtn && panel) {
        closeBtn.addEventListener('click', () => {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        });
    }

    const minInput = document.getElementById('filterDistanceMin');
    const maxInput = document.getElementById('filterDistanceMax');
    const distDisplay = document.getElementById('distValue');

    function updateSliderDisplay() {
        if (!minInput || !maxInput || !distDisplay) return;
        
        if (parseInt(minInput.value) > parseInt(maxInput.value)) {
            let tmp = minInput.value;
            minInput.value = maxInput.value;
            maxInput.value = tmp;
        }
        distDisplay.textContent = `${minInput.value} - ${maxInput.value} km`;
    }

    if (minInput && maxInput) {
        minInput.addEventListener('input', updateSliderDisplay);
        maxInput.addEventListener('input', updateSliderDisplay);
    }
});
</script>
