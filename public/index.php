
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UK Tick Tracker</title>

  <link rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="/elanco-placement/public/assets/style.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
  <script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">
    <!-- <span class="brand-icon">✳</span> -->
    <span>Elanco UK Tick Tracker<small>Community surveillance</small></span>
  </a>
  <a class="button button-light" href="#report">+ Report a sighting</a>
</header>

<main class="container">
  <section class="intro">
    <div>
      <p class="eyebrow">ELANCO UK TICK SURVEILLANCE</p>
      <h1>Know what's happening<br>in your area.</h1>
      <p class="muted">Explore community-reported tick sightings
        and learn how to protect your pets.</p>
    </div>
    <div class="notice">
      <strong>Community-powered insights</strong>
      <p>Reports indicate tick sightings, not confirmed disease cases.</p>
    </div>
  </section>
<!-- Stat cards -->
  <section class="stats" aria-label="Sighting statistics">
    <article class="stat">
      <span>Total sightings</span>
      <strong id="total">—</strong>
      <small>All recorded reports</small>
    </article>
    <article class="stat">
      <span>Last 30 days</span>
      <strong id="recent">—</strong>
      <small>Recent reported activity</small>
    </article>
    <article class="stat">
      <span>Regions represented</span>
      <strong id="regions">—</strong>
      <small>Regions with reports</small>
    </article>
  </section>
<!-- Map search -->
  <section class="panel">
    <div class="section-heading">
      <div>
        <p class="eyebrow">EXPLORE THE DATA</p>
        <h2>Sighting map</h2>
      </div>
      <span class="live-label">● Database connected</span>
    </div>

    <form id="filters" class="filters">
      <label>From
        <input type="date" name="from">
      </label>
      <label>To
        <input type="date" name="to">
      </label>
      <label>Species
        <select name="species" id="species">
            <option value="">All species</option>
        </select>
      </label>
      <label>Region
        <input name="region" placeholder="e.g. Yorkshire">
      </label>
      <button class="button" type="submit">Apply filters</button>
      <button class="button button-outline" type="reset">Reset</button>
    </form>

    <p id="status" class="status" role="status" aria-live="polite">
      Loading sightings…
    </p>
    <div class="map-layout">
      <div id="map" aria-label="Map of tick sightings in the UK"></div>
      <aside class="detail" id="detail">
        <span class="detail-icon">⌖</span>
        <h3>Explore a sighting</h3>
        <p class="muted">Select a marker on the map to see its details.</p>
      </aside>
    </div>
    <p class="map-note">Only reports with valid coordinates are displayed.
      Map data © OpenStreetMap contributors.</p>
      <!-- Displays all sightings for the selected city. -->
    <div id="cityRecords">
        <h3>City sightings</h3>
        <p class="muted">Click a map marker to view its sightings.</p>
    </div>
  </section>

  <section class="panel">
    <div class="section-heading">
      <div>
        <p class="eyebrow">ACTIVITY OVER TIME</p>
        <h2>Weekly sighting trends</h2>
      </div>
    
      <div class="chart-controls">
    <button type="button" data-period="6months">
        6 Months
    </button>
    <button type="button" data-period="1year">
        1 Year
    </button>
    <button type="button" class="active"data-period="all">
        All Time
    </button>
</div>
    </div>
    <div class="chart-wrap">
      <canvas id="trendChart" aria-label="Weekly tick sightings chart"></canvas>
      <p id="chartNoData" class="muted"
       style="display: none; text-align: center; padding: 50px 10px;">
        No data available for this period.
    </p>
    </div>
  </section>

  <section class="report-grid" id="report">
    <div>
      <p class="eyebrow">HELP YOUR COMMUNITY</p>
      <h2>Report a tick sighting</h2>
      <p class="muted">Share what you observed to help build a
        better picture of tick activity across the UK.</p>
      <p class="muted">If you are unsure of the species, choose
        Unknown. Never submit a guessed location.</p>
    </div>

    <form id="reportForm" class="report-form">
      <label>Date observed
        <input type="date" name="sighting_date" id="sightingDate" required>
      </label>
      <label>Location
        <input name="location_name" maxlength="150"
               placeholder="Town or area" required>
      </label>
      <label>Region
        <input name="region" maxlength="100"
               placeholder="e.g. South Yorkshire">
      </label>
      <label>Species
        <select name="species" required>
          <option value="Unknown">Not sure</option>
          <option>Ixodes ricinus</option>
          <option>Ixodes hexagonus</option>
          <option>Dermacentor reticulatus</option>
          <option>Other</option>
        </select>
      </label>
      <label>Notes (optional)
        <textarea name="notes" rows="3" maxlength="2000"
                  placeholder="Where was the tick observed?"></textarea>
      </label>
      <button class="button" type="submit">Submit sighting</button>
      <p id="reportStatus" role="status" aria-live="polite"></p>
    </form>
  </section>

  <section class="education">
    <p class="eyebrow">STAY INFORMED</p>
    <h2>Reduce the risk of tick bites</h2>
    <div class="education-grid">
      <article><span>01</span><h3>Check regularly</h3>
        <p>Check yourself and your pets after walks in grassy or wooded areas.</p>
      </article>
      <article><span>02</span><h3>Cover up</h3>
        <p>Wear suitable clothing and use an appropriate insect repellent.</p>
      </article>
      <article><span>03</span><h3>Remove safely</h3>
        <p>Use a fine-tipped tick remover and follow NHS or veterinary guidance.</p>
      </article>
    </div>
  </section>

  <footer>UK Tick Tracker · Community sightings for awareness,
    not a diagnosis or a confirmed disease surveillance measure.</footer>
</main>
</body>
</html>
