<style>
/* ==========================================================================
   ANTI-BONK PREMIUM COMPACT SIDEBAR FILTER MODULE
   ========================================================================== */
#filterPanel {
  position: fixed;
  top: 0;
  right: 0;
  width: 320px; /* Reduced from 380px for a sleek profile */
  height: 100vh;
  background: #ffffff;
  box-shadow: -6px 0 24px rgba(15, 23, 42, 0.12);
  border-left: 1px solid #e2e8f0;
  padding: 0.85rem 1rem; /* Ultra-compact padding */
  transform: translateX(100%);
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  z-index: 2000;
  overflow-y: auto;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

#filterPanel.open {
  transform: translateX(0);
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.5rem;
  margin-bottom: 0.65rem;
}

.filter-header h2 {
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.01em;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.close-panel-btn {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  width: 22px;
  height: 22px;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  transition: all 0.15s ease;
  padding: 0;
}

.close-panel-btn:hover {
  background: #fee2e2;
  color: #ef4444;
  border-color: #fca5a5;
}

/* Compact Container Body */
.filter-body {
  display: flex;
  flex-direction: column;
  gap: 0.55rem; /* Tight gap between controls */
  flex: 1;
}

.filter-group {
  margin-bottom: 0;
}

.filter-group label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-family: 'Inter', sans-serif;
  font-size: 0.65rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 0.2rem;
}

/* Section divider header for POIs */
.filter-section-title {
  font-family: 'Inter', sans-serif;
  font-size: 0.7rem;
  font-weight: 700;
  color: #0284c7;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 0.35rem;
  margin-bottom: 0.35rem;
  padding-top: 0.4rem;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* 2-Column Grid Layout for Form Groups */
.filter-grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

/* Crisp, ultra-compact inputs */
.filter-input, .filter-select {
  width: 100%;
  height: 26px;
  padding: 0 0.45rem;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background-color: #f8fafc;
  color: #0f172a;
  font-family: 'Inter', sans-serif;
  font-size: 0.72rem;
  font-weight: 500;
  box-sizing: border-box;
  outline: none;
  transition: all 0.15s ease;
}

.filter-input::placeholder {
  color: #94a3b8;
}

.filter-input:focus, .filter-select:focus {
  outline: none;
  border-color: #0284c7;
  background-color: #ffffff;
}

/* Custom crisp checkbox module */
.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 6px;
  font-size: 0.65rem !important;
  color: #64748b !important;
  font-weight: 500 !important;
  text-transform: none !important;
  letter-spacing: normal !important;
  cursor: pointer;
  margin-top: 0.25rem;
  user-select: none;
}

.checkbox-label input {
  margin: 0;
  width: 13px;
  height: 13px;
  accent-color: #0284c7;
  cursor: pointer;
}

/* Precision Compact Distance/Elevation Range Styling */
.crisp-badge {
  float: none;
  font-family: 'JetBrains Mono', monospace;
  font-weight: 600; 
  font-size: 0.65rem; 
  padding: 1px 5px;
  background-color: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 3px;
  color: #0284c7;
  text-transform: none;
  letter-spacing: normal;
}

.range-slider-wrapper {
  position: relative;
  height: 16px;
  margin-top: 0.1rem;
  display: flex;
  align-items: center;
}

.range-slider-wrapper input[type="range"] {
  position: absolute;
  width: 100%;
  height: 4px;
  margin: 0;
  background: none;
  pointer-events: none;
  appearance: none;
  -webkit-appearance: none;
}

/* Cross-browser precision slider handles configuration */
.range-slider-wrapper input[type="range"]::-webkit-slider-thumb {
  height: 12px;
  width: 12px;
  border-radius: 50%;
  background: #0284c7;
  border: 1.5px solid #ffffff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
  cursor: pointer;
  pointer-events: auto;
  appearance: none;
  -webkit-appearance: none;
  margin-top: -4px;
  transition: transform 0.1s ease;
}

.range-slider-wrapper input[type="range"]::-webkit-slider-thumb:active {
  transform: scale(1.2);
}

.range-slider-wrapper input[type="range"]::-moz-range-thumb {
  height: 10px;
  width: 10px;
  border-radius: 50%;
  background: #0284c7;
  border: 1.5px solid #ffffff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
  cursor: pointer;
  pointer-events: auto;
}

.range-slider-wrapper input[type="range"]::-webkit-slider-runnable-track {
  width: 100%;
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
}

/* Compact Action Footer Panel */
.action-footer {
  border-top: 1px solid #e2e8f0; 
  margin-top: 0.65rem; 
  padding-top: 0.65rem;
  display: flex;
  gap: 0.5rem;
}

.btn-reset, .btn-poi-search {
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-family: 'Inter', sans-serif;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0 0.5rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
  box-sizing: border-box;
  line-height: 1;
}

.btn-reset {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #475569;
}

.btn-reset:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fca5a5;
}

.btn-poi-search {
  flex: 2;
  background: #0284c7;
  border: 1px solid #0284c7;
  color: #ffffff;
}

.btn-poi-search:hover {
  background: #0369a1;
  border-color: #0369a1;
}
</style>

<aside id="filterPanel" aria-hidden="true">
  <div>
    <!-- Header -->
    <div class="filter-header">
      <h2>⚙️ Filter & Configs</h2>
      <button type="button" class="close-panel-btn" id="closeFilters" aria-label="Close">✕</button>
    </div>

    <div class="filter-body">
      <!-- Search Name Block -->
      <div class="filter-group">
        <label for="filterName">Route Name</label>
        <input id="filterName" class="filter-input" type="text" placeholder="Search track title...">
        
        <label class="checkbox-label">
          <input id="filterNameNot" type="checkbox"> Exclude this name (NOT logic)
        </label>
      </div>

      <!-- Distance Slider Group -->
      <div class="filter-group">
        <label>Distance <span id="distValue" class="crisp-badge">0 - 400 km</span></label>
        <div class="range-slider-wrapper">
          <input id="filterDistanceMin" type="range" min="0" max="400" step="1" value="0">
          <input id="filterDistanceMax" type="range" min="0" max="400" step="1" value="400">
        </div>
      </div>

      <!-- Elevation Gain Slider Group -->
      <div class="filter-group">
        <label>Elevation <span id="elevValue" class="crisp-badge">0 - 10000 m</span></label>
        <div class="range-slider-wrapper">
          <input id="filterElevationMin" type="range" min="0" max="10000" step="50" value="0">
          <input id="filterElevationMax" type="range" min="0" max="12000" step="50" value="12000">
        </div>
      </div>

      <!-- 2-Column Select Grid: Activity & Country -->
      <div class="filter-grid-2col">
        <div class="filter-group">
          <label for="filterType">Activity Type</label>
          <select id="filterType" class="filter-select">
            <option value="">All Types</option>
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
      </div>

      <!-- Tags Input Block -->
      <div class="filter-group">
        <label for="filterTags">Tags</label>
        <input id="filterTags" class="filter-input" type="text" placeholder="e.g. gravel, weekend, mallorca">
      </div>

      <!-- Overpass POI / Route Configuration Section -->
      <div class="filter-section-title">
        <span>🛒 Route Stops & POI Config</span>
      </div>

      <!-- POI Options Grid -->
      <div class="filter-grid-2col">
        <div class="filter-group">
          <label for="radiusSelect">Search Radius</label>
          <select id="radiusSelect" class="filter-select">
            <option value="200">200m (Tight)</option>
            <option value="500" selected>500m (Default)</option>
            <option value="800">800m (Wide)</option>
          </select>
        </div>

        <div class="filter-group d-flex flex-column justify-content-end">
          <label class="checkbox-label" style="margin-top: 0;">
            <input id="SundayBox" type="checkbox"> Sunday Mode
          </label>
        </div>
      </div>

      <div class="filter-group">
        <label class="checkbox-label">
          <input id="drinkFountains" type="checkbox"> Water Tap Points (Fountains)
        </label>
      </div>

    </div>
  </div>

  <!-- Footer Actions Anchor -->
  <div class="action-footer">
    <button class="btn-reset" type="button" id="clearFilters">
      Reset
    </button>
    <button class="btn-poi-search" type="button" id="btnFetchPoisSidebar" onclick="if(typeof refreshShops === 'function') refreshShops();">
      🔍 Find POIs
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

    // --- Distance Slider Handler ---
    const distMin = document.getElementById('filterDistanceMin');
    const distMax = document.getElementById('filterDistanceMax');
    const distDisplay = document.getElementById('distValue');

    function updateDistDisplay() {
        if (!distMin || !distMax || !distDisplay) return;
        if (parseInt(distMin.value) > parseInt(distMax.value)) {
            let tmp = distMin.value;
            distMin.value = distMax.value;
            distMax.value = tmp;
        }
        distDisplay.textContent = `${distMin.value} - ${distMax.value} km`;
    }

    if (distMin && distMax) {
        distMin.addEventListener('input', updateDistDisplay);
        distMax.addEventListener('input', updateDistDisplay);
    }

    // --- Elevation Slider Handler ---
    const elevMin = document.getElementById('filterElevationMin');
    const elevMax = document.getElementById('filterElevationMax');
    const elevDisplay = document.getElementById('elevValue');

    function updateElevDisplay() {
        if (!elevMin || !elevMax || !elevDisplay) return;
        if (parseInt(elevMin.value) > parseInt(elevMax.value)) {
            let tmp = elevMin.value;
            elevMin.value = elevMax.value;
            elevMax.value = tmp;
        }
        elevDisplay.textContent = `${elevMin.value} - ${elevMax.value} m`;
    }

    if (elevMin && elevMax) {
        elevMin.addEventListener('input', updateElevDisplay);
        elevMax.addEventListener('input', updateElevDisplay);
    }
});
</script>