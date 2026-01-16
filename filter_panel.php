<aside id="filterPanel" aria-hidden="true">
  <article>
    <header class="grid">
      <strong>Filters</strong>
      <a href="#" aria-label="Close" onclick="toggleFilters(false)"></a>
    </header>

    <label>
      Name
      <input id="filterName" type="text" placeholder="Route name">
    </label>

    <label>
      Min distance (km)
      <input id="filterDistance" type="number" min="0" step="0.1">
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
      Tags
      <input
        id="filterTags"
        type="text"
        placeholder="Comma-separated tags (e.g. gravel, mallorca)"
      >
    </label>

    <footer>
      <button class="secondary" type="button" onclick="clearFilters()">
        Clear
      </button>
    </footer>
  </article>
</aside>
