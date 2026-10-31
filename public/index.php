<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Sets the character encoding and makes the page responsive -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Page title shown in the browser tab -->
  <title>UK Tick Tracker</title>

  <!-- Links to external libraries and the website's CSS and JavaScript files -->
  <link rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="/elanco-placement/public/assets/style.css">

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
  <script src="assets/app.js" defer></script>
</head>

<body>

<!-- Header containing the website logo and report button -->
<header class="topbar">
  <a class="brand" href="index.php">
    <span>Elanco UK Tick Tracker<small>Community surveillance</small></span>
  </a>
  <a class="button button-light" href="#report">+ Report a sighting</a>
</header>

<!-- Navigation links to different sections of the page -->
<nav class="section-nav" aria-label="Page navigation">
    <a href="#top">Home</a>
    <a href="#education">Tick prevention</a>
    <a href="#stats-section">Statistics</a>
    <a href="#map-section">Sighting map</a>
    <a href="#trends-section">Trends</a>
    <a href="#more-info">Tick species</a>
</nav>

<main class="container" id="top">

  <!-- Introduction section explaining the purpose of the website -->
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

  <!-- Education section providing advice on preventing tick bites -->
  <section class="education" id="education">
    <p class="eyebrow">STAY INFORMED</p>
    <h2>Reduce the risk of tick bites</h2>

    <div class="education-grid">
      <article>
        <span>01</span>
        <h3>Check regularly</h3>
        <p>Check yourself and your pets after walks in grassy or wooded areas.</p>
      </article>

      <article>
        <span>02</span>
        <h3>Cover up</h3>
        <p>Wear suitable clothing and use an appropriate insect repellent.</p>
      </article>

      <article>
        <span>03</span>
        <h3>Remove safely</h3>
        <p>Use a fine-tipped tick remover and follow NHS or veterinary guidance.</p>
      </article>
    </div>
  </section>

  <!-- Statistics section displaying the main sighting figures -->
  <p class="eyebrow" id="stats-section">EXPLORE THE DATA</p>

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
      <span>Locations represented</span>
      <strong id="location">—</strong>
      <small>Locations with reports</small>
    </article>
  </section>

  <!-- Map section allowing users to explore and filter tick sightings -->
  <section class="panel" id="map-section">
    <div class="section-heading">
      <div>
        <h2>Sighting map</h2>
      </div>
      <span class="live-label">● Database connected</span>
    </div>

    <!-- Form containing filters for the map data -->
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

      <label>Location
        <input name="location" placeholder="e.g. Sheffield">
      </label>

      <button class="button" type="submit">Apply filters</button>
      <button class="button button-outline" type="reset">Reset</button>
    </form>

    <!-- Displays loading messages and filter status -->
    <p id="status" class="status" role="status" aria-live="polite">
      Loading sightings…
    </p>

    <!-- Layout containing the map and selected sighting details -->
    
    <div class="map-layout">

    <div id="map" aria-label="Map of tick sightings in the UK"></div>

    <aside class="map-sidebar">

        <div class="detail" id="detail">
        <span class="detail-icon">⌖</span>
        <h3>Explore a sighting</h3>
        <p class="muted">
            Select a marker on the map to see its details.
        </p>
        </div>

        <div class="map-legend">
        <strong>Reported activity</strong>

        <div class="legend-items">
            <span>
            <i class="legend-dot low"></i>
            Low activity
            </span>

            <span>
            <i class="legend-dot moderate"></i>
            Moderate activity
            </span>

            <span>
            <i class="legend-dot high"></i>
            High activity
            </span>
        </div>
        </div>
        <p class="map-note">
            Colours represent relative numbers of community reports,
            not confirmed disease risk.
        </p>
    </aside>
    </div>

    <!-- Information about the map and its data source -->
    <p class="map-note">
    Only reports with valid coordinates are displayed.
    Map data © OpenStreetMap contributors.
    Colours indicate report counts, not confirmed disease risk.
    </p>

    <!-- Displays all sightings for the selected city -->
    <div id="cityRecords">
        <h3>City sightings</h3>
        <p class="muted">Click a map marker to view its sightings.</p>
    </div>
  </section>

  <!-- Trends section displaying changes in tick sightings over time -->
  <section class="panel" id="trends-section">
    <div class="section-heading">
    <div>
        <p class="eyebrow">ACTIVITY OVER TIME</p>
        <h2>Weekly sighting trends</h2>
    </div>

    
    <!-- Weekly graph filters -->
    <div class="chart-filters">

        <label for="trendSpecies">Tick species:</label>
        <select id="trendSpecies">
            <option value="">All species</option>
        </select>

        <label for="trendLocation">Location:</label>
        <input
            type="text"
            id="trendLocation"
            placeholder="Search town or city..."
            maxlength="150"
        >

    </div>


      <!-- Buttons allowing users to change the chart's time period -->
      <div class="chart-controls">
        <button type="button" data-period="6months">
            6 Months
        </button>

        <button type="button" data-period="1year">
            1 Year
        </button>

        <button type="button" class="active" data-period="all">
            All Time
        </button>
      </div>
    </div>

    <!-- Chart displaying weekly sighting figures -->
    <div class="chart-wrap">
      <canvas id="trendChart" aria-label="Weekly tick sightings chart"></canvas>

      <!-- Message displayed when there is no chart data -->
      <p id="chartNoData" class="muted"
       style="display: none; text-align: center; padding: 50px 10px;">
        No data available for this period.
      </p>
    </div>
  </section>

  <!-- Report section introducing the tick sighting submission form -->
  <section class="report-grid" id="report">
    <div>
      <p class="eyebrow">HELP YOUR COMMUNITY</p>
      <h2>Report a tick sighting</h2>
      <p class="muted">Share what you observed to help build a
        better picture of tick activity across the UK.</p>
      <p class="muted">If you are unsure of the species, choose
        Unknown. Never submit a guessed location.</p>
    </div>
    <!-- Form allowing users to submit a new tick sighting -->
    <form id="reportForm" class="report-form">

      <!-- Date when the tick was observed -->
      <label>Date observed
        <input type="date" name="sighting_date" id="sightingDate" required>
      </label>

      <!-- Town or area where the tick was found -->
      <label>Location
        <input name="location_name" maxlength="150"
               placeholder="Town or area" required>
      </label>

      <!-- Dropdown allowing users to select the tick species -->
      <label>Species
        <select name="species" required>
          <option value="Unknown">Not sure</option>
          <option>Ixodes ricinus</option>
          <option>Ixodes hexagonus</option>
          <option>Dermacentor reticulatus</option>
          <option>Other</option>
        </select>
      </label>

      <!-- Optional text area for additional information -->
      <label>Notes (optional)
        <textarea name="notes" rows="3" maxlength="2000"
                  placeholder="Where was the tick observed?"></textarea>
      </label>

      <!-- Button used to submit the sighting form -->
      <button class="button" type="submit">Submit sighting</button>

      <!-- Displays messages about the form submission -->
      <p id="reportStatus" role="status" aria-live="polite"></p>
    </form>
  </section>

  <!-- Additional information section about other UK tick species -->
  <section class="panel extra-info" id="more-info">

    <!-- Heading and introduction to the tick species section -->
    <div class="section-heading">
        <div>
            <p class="eyebrow">TICK IDENTIFICATION</p>
            <h2>Other tick species found in the UK</h2>
            <p class="muted">
                Learn about other tick species, their habitats
                and the animals they commonly feed on.
            </p>
        </div>
    </div>

    <!-- Grid containing information cards for different tick species -->
    <div class="tick-grid">

        <!-- Information card about the Passerine tick -->
        <article class="tick-card">
            <div class="tick-content">
                <h3>Passerine tick</h3>
                <p><i>Ixodes frontalis</i></p>
                <p>
                    Generally found in bird nests in woodland,
                    parks and gardens.
                </p>
                <p>
                    Feeds on songbirds (passerines), including
                    thrushes, warblers and blackbirds.
                </p>

                <!-- Link to the source of the species information -->
                <a href="https://www.esccapuk.org.uk/page/Ixodes+frontalis/79/"
                   target="_blank" rel="noopener noreferrer">
                    Source: ESCCAP
                </a>
            </div>
        </article>

        <!-- Information card about the Fox/Badger tick -->
        <article class="tick-card">
            <div class="tick-content">
                <h3>Fox/Badger tick</h3>
                <p><i>Ixodes canisuga</i></p>
                <p>
                    Commonly found in areas with high populations
                    of foxes and badgers.
                </p>
                <p>
                    Feeds on foxes, cats, dogs, horses,
                    badgers and sheep.
                </p>

                <!-- Link to the source of the species information -->
                <a href="https://www.esccapuk.org.uk/page/Ixodes+canisuga/66/"
                   target="_blank" rel="noopener noreferrer">
                    Source: ESCCAP
                </a>
            </div>
        </article>

        <!-- Information card about the Southern rodent tick -->
        <article class="tick-card">
            <div class="tick-content">
                <h3>Southern rodent tick</h3>
                <p><i>Ixodes acuminatus</i></p>
                <p>
                    Typically found in grassy and wooded areas,
                    primarily in southern England.
                </p>
                <p>
                    Specialises in parasitising small rodents
                    within their burrows and nests.
                </p>

                <!-- Link to the source of the species information -->
                <a href="https://www.esccapuk.org.uk/page/Ixodes+acuminatus/75/"
                   target="_blank" rel="noopener noreferrer">
                    Source: ESCCAP
                </a>
            </div>
        </article>

        <!-- Information card about the Tree-hole tick -->
        <article class="tick-card">
            <div class="tick-content">
                <h3>Tree-hole tick</h3>
                <p><i>Ixodes arboricola</i></p>
                <p>
                    Commonly found in tree holes and other
                    sheltered areas.
                </p>
                <p>
                    Infests cavity-nesting birds.
                </p>

                <!-- Link to the source of the species information -->
                <a href="https://www.esccapuk.org.uk/page/Ixodes+arboricola/77/"
                   target="_blank" rel="noopener noreferrer">
                    Source: ESCCAP
                </a>
            </div>
        </article>

        <!-- Information card about the Marsh tick -->
        <article class="tick-card">
            <div class="tick-content">
                <h3>Marsh tick</h3>
                <p><i>Ixodes apronophorus</i></p>
                <p>
                    Typically found in marshy and humid areas.
                </p>
                <p>
                    Feeds on larger animals such as dogs,
                    cattle, sheep and foxes.
                </p>

                <!-- Link to the source of the species information -->
                <a href="https://www.esccapuk.org.uk/page/Ixodes+apronophorus/76/"
                   target="_blank" rel="noopener noreferrer">
                    Source: ESCCAP
                </a>
            </div>
        </article>

    </div>
</section>

<!-- Footer containing a disclaimer about the website's data -->
<footer>
  UK Tick Tracker · Community sightings for awareness,
  not a diagnosis or a confirmed disease surveillance measure.
</footer>

<!-- Ends the main page content -->
</main>

</body>
</html>