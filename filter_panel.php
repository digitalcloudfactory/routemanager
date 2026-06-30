<style>
/* Sidebar inner form structure enhancements matching light configuration modules */
.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}
.filter-header h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.close-panel-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #94a3b8;
  cursor: pointer;
}
.close-panel-btn:hover { color: #475569; }

.filter-group {
  margin-bottom: 1.25rem;
}
.filter-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.5rem;
}
.filter-input, .filter-select {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background-color: #ffffff;
  color: #1e293b;
  font-size: 0.9rem;
}
.filter-input:focus, .filter-select:focus {
  outline: none;
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
}
.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem !important;
  color: #64748b !important;
  font-weight: 500 !important;
  cursor: pointer;
}
.checkbox-label input {
  margin: 0;
  width: 16px;
  height: 16px;
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
  </div>

  <div class="filter-group" style="margin-top: -0.5rem;">
    <label class="checkbox-label">
      <input id="filterNameNot" type="checkbox"> Exclude this name (NOT logic)
    </label>
  </div>

  <div class="filter-group">
    <label>Distance: <span id="distValue" class="badge bg-light text-dark border float-end" style="font-weight: 600; font-size:0.8rem; padding: 2px 6px;">0 - 400 km</span></label>
    <div class="range-slider" style="position: relative; height: 35px; margin-top: 8px;">
      <input id="filterDistanceMin" type="range" min="0" max="400" step="1" value="0" 
             style="position: absolute; pointer-events: none; width: 100%; z-index: 2; appearance: none; background: none;">
      <input id="filterDistanceMax" type="range" min="0" max="400" step="1" value="400" 
             style="position: absolute; width: 100%; z-index: 1;">
    </div>
  </div>

  <div class="filter-group">
    <label for="filterElevation">Min Elevation (m)</label>
    <input id="filterElevation" class="filter-input" type="number" min="0" placeholder="0">
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

  <div class="pt-2" style="border-top: 1px solid #e2e8f0; margin-top: 2rem;">
    <button class="btn-custom w-100" type="button" id="clearFilters" style="background: #f1f5f9; border:none; color: #475569; font-weight:600;">
      Reset Filters
    </button>
  </div>
</aside>
