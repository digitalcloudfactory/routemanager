<aside id="filterPanel" aria-hidden="true">
  <article>
    <header class="grid">
      <strong>Filters</strong>
      <a href="#" aria-label="Close" id="closeFilters"></a>
    </header>

    <label>
      Name
      <input id="filterName" type="text" placeholder="Route name">
    </label>

    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; margin-top: -0.5rem; margin-bottom: 1rem;">
    <input id="filterNameNot" type="checkbox" style="margin-bottom: 0;">
    Exclude this name (NOT)
    </label>

    <label>
      Distance (km): <span id="distValue">0 - 400</span>
      <div class="range-slider" style="position: relative; height: 35px; margin-top: 10px;">
        <input id="filterDistanceMin" type="range" min="0" max="400" step="1" value="0" 
               style="position: absolute; pointer-events: none; width: 100%; z-index: 2; appearance: none; background: none;">
        <input id="filterDistanceMax" type="range" min="0" max="400" step="1" value="400" 
               style="position: absolute; width: 100%; z-index: 1;">
      </div>
    </label>

    <label>
      Min elevation (m)
      <input id="filterElevation" type="number" min="0">
    </label>

    <label>
      Type
      <select id="filterType">
        <option value="">All</option>
        <option value="1">Ride</option>
        <option value="2">Run</option>
        <option value="3">Walk</option>
        <option value="6">Gravel</option>
      </select>
    </label>

    <label>
    Country
    <select id="filterCountry">
      <option value="">All Countries</option>
      <?php foreach ($countries as $country): ?>
        <option value="<?= htmlspecialchars($country) ?>"><?= htmlspecialchars($country) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

    <label>
      Tags
      <input
        id="filterTags"
        type="text"
        placeholder="Comma-separated tags (e.g. gravel, mallorca)"
      >
    </label>

    <footer>
      <button class="secondary" type="button" id="clearFilters">
        Clear
      </button>
    </footer>
  </article>
</aside>
