// Wait until the HTML document has finished loading before running the application.
document.addEventListener("DOMContentLoaded", () => {

  // Define the URL used to retrieve sighting records from the PHP API.
  const API_URL = "/elanco-placement/api/sightings.php";

  // Retrieve references to the HTML elements used by the application.
  const mapElement = document.getElementById("map");
  const filterForm = document.getElementById("filters");
  const reportForm = document.getElementById("reportForm");
  const statusElement = document.getElementById("status");
  const reportStatus = document.getElementById("reportStatus");

  // Declare variables used to store sighting data and map/chart objects.
  let sightings = [];
  let markersLayer;
  let trendChart;
  let selectedTrendPeriod = "alltime";
  // Store the currently selected city.
  let selectedCity = null;

  // Set the default report date to today.
  const sightingDate = document.getElementById("sightingDate");
  if (sightingDate) {
    sightingDate.value = new Date().toISOString().slice(0, 10);
    sightingDate.max = sightingDate.value;
  }
  // Populate the species dropdown using species from the database.
function updateSpeciesDropdown() {

    // Retrieve the species dropdown.
    const speciesSelect = document.getElementById("species");

    // Remember the currently selected species.
    const selectedSpecies = speciesSelect.value;

    // Get all unique species from the sightings data.
    const speciesList = [...new Set(
        sightings
            .map(sighting => getSpecies(sighting))
            .filter(species => species !== "Unknown")
    )].sort();

    // Clear existing dropdown options.
    speciesSelect.replaceChildren();

    // Add the default option.
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "All species";
    speciesSelect.appendChild(defaultOption);

    // Add each species as a dropdown option.
    speciesList.forEach(species => {

        const option = document.createElement("option");

        option.value = species;
        option.textContent = species;

        speciesSelect.appendChild(option);
    });

    // Restore the previous selection if it still exists.
    speciesSelect.value = speciesList.includes(selectedSpecies)
        ? selectedSpecies
        : "";
}

  // Initialise the Leaflet map and set its starting position to the UK.
  const map = L.map(mapElement).setView([54.5, -3], 6);

  // Add OpenStreetMap tiles to provide the geographical background.
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 18
  }).addTo(map);

  // Create a layer to hold any markers displayed on the map.
  markersLayer = L.layerGroup().addTo(map);

  // Escape API-provided text before inserting it into HTML.
  function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    })[char]);
  }

  // Display a status message and change its colour if an error occurs.
  function setStatus(message, isError = false) {
    statusElement.textContent = message;
    statusElement.style.color = isError ? "#b42318" : "";
  }

  // Retrieve and validate the date associated with a sighting.
  function getDate(sighting) {
    // Check both possible API field names for the sighting date.
    const raw = sighting.sighting_date ?? sighting.date ?? "";
    if (!raw) return null;

    // Extract the date in YYYY-MM-DD format.
    const match = String(raw).match(/^\d{4}-\d{2}-\d{2}/);
    if (!match) return null;

    // Convert the extracted date into a JavaScript Date object.
    const date = new Date(`${match[0]}T00:00:00Z`);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  // Retrieve the species name, checking alternative field names if necessary.
  function getSpecies(sighting) {
    return String(
      sighting.species ??
      sighting.latin_name ??
      sighting.latinName ??
      "Unknown"
    ).trim() || "Unknown";
  }

  // Retrieve the region associated with a sighting.
  function getRegion(sighting) {
    return String(sighting.region ?? "").trim();
  }

  // Retrieve the latitude and check that it is within the valid range.
  function getLatitude(sighting) {
    const value = Number(sighting.latitude ?? sighting.lat);
    return Number.isFinite(value) && value >= -90 && value <= 90
      ? value
      : null;
  }

  // Retrieve the longitude and check that it is within the valid range.
  function getLongitude(sighting) {
    const value = Number(
      sighting.longitude ?? sighting.lng ?? sighting.lon
    );
    return Number.isFinite(value) && value >= -180 && value <= 180
      ? value
      : null;
  }

  // Load sightings from the PHP API.
async function loadSightings(filters = {}) {

  // Inform the user that sighting records are being retrieved.
  setStatus("Loading sightings…");

  try {
    // Create an object to store the search parameters.
    const params = new URLSearchParams();

    // Add each supplied filter to the API request, ignoring empty values.
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        params.set(key, value);
      }
    });

    // Fetch up to 2,000 records per request, as allowed by your API.
    params.set("limit", "2000");

    // Send a GET request to the PHP API and request JSON data.
    const response = await fetch(
      `${API_URL}?${params.toString()}`,
      {
        headers: { Accept: "application/json" },
        cache: "no-store"
      }
    );

    // Convert the API response into a JavaScript object.
    const result = await response.json();

    // Check whether the request was successful.
    if (!response.ok || result.success === false) {
      throw new Error(
        result.error || `HTTP error ${response.status}`
      );
    }

    // Check that the API returned an array of sighting records.
    if (!Array.isArray(result.data)) {
      throw new Error("The API response does not contain a data array.");
    }

    // Store the retrieved sightings for use throughout the application.
    sightings = result.data;

    // Populate the species dropdown using the retrieved database records.
    updateSpeciesDropdown();
    
    updateDashboard();
    applyFilters();

  } catch (error) {

    // Log any API errors in the browser console for debugging.
    console.error("Error loading sightings:", error);

    // Display an error message if the sightings cannot be retrieved.
    setStatus(
      "Could not load sightings. Check api/sightings.php and the browser console.",
      true
    );
  }
}

// Update the summary statistics displayed on the dashboard.
function updateDashboard() {

  // Get the HTML elements where the dashboard statistics will be displayed.
  const totalElement = document.getElementById("total");
  const recentElement = document.getElementById("recent");
  const regionsElement = document.getElementById("regions");

  // Get the current date and calculate the date 30 days ago.
  const now = new Date();
  const cutoff = new Date(now);
  cutoff.setDate(cutoff.getDate() - 30);

  // Count the sightings recorded within the last 30 days.
  const recentCount = sightings.filter(sighting => {
    const date = getDate(sighting);
    return date && date <= now && date >= cutoff;
  }).length;

  // Create a collection of unique regions to prevent duplicate counting.
  const regions = new Set(
    sightings
      .map(getRegion)
      .filter(region => region !== "")
      .map(region => region.toLowerCase())
  );

  // Update the dashboard with the total number of sightings.
  totalElement.textContent = sightings.length.toLocaleString("en-GB");

  // Display the number of sightings recorded in the last 30 days.
  recentElement.textContent = recentCount.toLocaleString("en-GB");

  // Display the total number of unique regions.
  regionsElement.textContent = regions.size.toLocaleString("en-GB");
}

// Apply the date, species, and region filters.
function getFilteredSightings() {

  // Retrieve the values entered by the user in the filter form.
  const formData = new FormData(filterForm);

  // Store the selected start date, end date, species and region.
  const from = formData.get("from");
  const to = formData.get("to");
  const species = String(formData.get("species") || "").toLowerCase();
  const region = String(formData.get("region") || "")
    .trim()
    .toLowerCase();

  // Filter the sightings array to return records matching the selected criteria.
  return sightings.filter(sighting => {

    // Retrieve the sighting date and convert it into YYYY-MM-DD format.
    const date = getDate(sighting);
    const dateString = date
      ? date.toISOString().slice(0, 10)
      : "";

    // Exclude sightings recorded before the selected start date.
    if (from && (!dateString || dateString < from)) return false;

    // Exclude sightings recorded after the selected end date.
    if (to && (!dateString || dateString > to)) return false;

    // Check whether the sighting matches the selected species.
    if (
      species &&
      getSpecies(sighting).toLowerCase() !== species
    ) {
      return false;
    }

    // Check whether the sighting region contains the entered search term.
    if (
      region &&
      !getRegion(sighting).toLowerCase().includes(region)
    ) {
      return false;
    }

    // Return true when the sighting meets all selected filter conditions.
    return true;
  });
}

function updateMap(records) {
  // Reset the selected city whenever the map is refreshed.
  selectedCity = null;

  // Remove existing markers from the map.
  markersLayer.clearLayers();

  // Retrieve the city records container.
  const cityRecords = document.getElementById("cityRecords");

  // Group sightings according to their city.
  const cityGroups = {};

  // Loop through all filtered sightings.
  records.forEach(sighting => {

    // Retrieve the city name.
    const city = String(sighting.location ?? "").trim();

    // Retrieve the coordinates.
    const latitude = getLatitude(sighting);
    const longitude = getLongitude(sighting);

    // Skip records without valid coordinates or a city name.
    if (!city || latitude === null || longitude === null) return;

    // Use a consistent city name as the grouping key.
    const cityKey = city.toLowerCase();

    // Create a city group if one does not already exist.
    if (!cityGroups[cityKey]) {
      cityGroups[cityKey] = {
        name: city,
        coordinates: [latitude, longitude],
        sightings: []
      };
    }

    // Add the sighting to the corresponding city.
    cityGroups[cityKey].sightings.push(sighting);
  });

  // Create a marker for every city.
  Object.entries(cityGroups).forEach(([cityKey, cityData]) => {

    // Create a Leaflet marker using the city's coordinates.
    const marker = L.marker(cityData.coordinates);

    // Display the city name and total sightings in the popup.
    marker.bindPopup(`
      <strong>${escapeHTML(cityData.name)}</strong><br>
      ${cityData.sightings.length} sighting(s)
    `);

    // When a marker is clicked, toggle its city records.
marker.on("click", () => {

    // If the same city is clicked again, close its records.
    if (selectedCity === cityKey) {

        selectedCity = null;

        const cityRecords = document.getElementById("cityRecords");
        cityRecords.replaceChildren();

        const heading = document.createElement("h3");
        heading.textContent = "City sightings";

        const message = document.createElement("p");
        message.className = "muted";
        message.textContent = "Click a map marker to view its sightings.";

        cityRecords.append(heading, message);

    } else {

        // Otherwise, select the new city.
        selectedCity = cityKey;

        // Display all sightings belonging to that city.
        displayCityRecords(cityData.name, cityData.sightings);
    }
});

    // Add the marker to the map.
    markersLayer.addLayer(marker);
  });

  // Reset the records panel when the map is refreshed.
  cityRecords.replaceChildren();

  const heading = document.createElement("h3");
  heading.textContent = "City sightings";

  const message = document.createElement("p");
  message.className = "muted";
  message.textContent = "Click a map marker to view its sightings.";

  cityRecords.append(heading, message);

  // Return the total number of city markers.
  return Object.keys(cityGroups).length;
}


// Display all sightings belonging to the selected city.
function displayCityRecords(city, records) {

  // Retrieve the container underneath the map.
  const cityRecords = document.getElementById("cityRecords");

  // Clear previously displayed records.
  cityRecords.replaceChildren();

  // Display the selected city name.
  const heading = document.createElement("h3");
  heading.textContent = `Sightings in ${city}`;

  // Display the total number of records.
  const count = document.createElement("p");
  count.className = "muted";
  count.textContent =
    `${records.length} sighting${records.length === 1 ? "" : "s"} found`;

  cityRecords.append(heading, count);

  // Create a section for each sighting.
  records.forEach(sighting => {

    // Create a container for the individual record.
    const card = document.createElement("div");
    card.className = "sighting-card";

    // Display the species name.
    const species = document.createElement("h4");
    species.textContent = getSpecies(sighting);

    // Display the sighting date.
    const date = document.createElement("p");
    date.textContent =
      `Date: ${getDate(sighting)?.toISOString().slice(0, 10) || "Unknown"}`;

    // Display the location.
    const location = document.createElement("p");
    location.textContent = `Location: ${sighting.location || "Unknown"}`;

    // Display the region.
    const region = document.createElement("p");
    region.textContent = `Region: ${getRegion(sighting) || "Not provided"}`;

    // Add the information to the record card.
    card.append(species, date, location, region);

    // Add the card to the city records section.
    cityRecords.appendChild(card);
  });

}


// Show the selected sighting in the details panel.
function showSightingDetails(sighting) {

  // Retrieve the details panel from the HTML document.
  const detail = document.getElementById("detail");

  // Clear any existing sighting details before displaying new information.
  detail.replaceChildren();

  // Create a visual icon to represent the selected sighting.
  const icon = document.createElement("span");
  icon.className = "detail-icon";
  icon.textContent = "⌖";

  // Create a heading displaying the species name.
  const heading = document.createElement("h3");
  heading.textContent = getSpecies(sighting);

  // Prepare the sighting information, including fallback values if data is missing.
  const fields = [
    ["Date", getDate(sighting)?.toISOString().slice(0, 10) || "Unknown"],
    ["Location", sighting.location ?? sighting.location_name ?? "Not provided"],
    ["Region", getRegion(sighting) || "Not provided"],
    ["Notes", sighting.notes || "No additional notes"]
  ];

  // Add the icon and species heading to the details panel.
  detail.append(icon, heading);

  // Loop through each information field and create a paragraph for it.
  fields.forEach(([label, value]) => {
    const paragraph = document.createElement("p");
    paragraph.className = "muted";

    // Create a bold label to identify the information being displayed.
    const strong = document.createElement("strong");
    strong.textContent = `${label}: `;

    // Add the label and its corresponding value to the paragraph.
    paragraph.append(strong, document.createTextNode(String(value)));

    // Add the completed paragraph to the details panel.
    detail.appendChild(paragraph);
  });
}

// Build the weekly trend chart using the available sighting records.

function updateChart(records) {

  // Get the current date to determine which sightings should be included.
  const now = new Date();

  // Store the earliest date to include in the selected chart period.
  let cutoff = null;

  // Calculate the cutoff date when the user selects the last six months.
  if (selectedTrendPeriod === "6months") {
      cutoff = new Date(now);
      cutoff.setMonth(cutoff.getMonth() - 6);
  }

  // Calculate the cutoff date when the user selects the last year.
  if (selectedTrendPeriod === "1year") {
      cutoff = new Date(now);
      cutoff.setFullYear(cutoff.getFullYear() - 1);
  }

  // Create a Map to store the number of sightings recorded in each week.
  const weeklyCounts = new Map();

  // Loop through the filtered sightings to calculate weekly totals.
  records.forEach(sighting => {

      // Retrieve the date associated with the current sighting.
      const date = getDate(sighting);

      // Ignore sightings that do not have a valid date.
      if (!date) return;

      // Exclude future sightings.
      if (date > now) return;

      // Apply selected graph period.
      if (cutoff && date < cutoff) return;

      // Group sightings by Monday of each week.
      const monday = new Date(date);

      // Calculate the number of days since Monday.
      const day = (monday.getUTCDay() + 6) % 7;

      // Adjust the date to the Monday of the relevant week.
      monday.setUTCDate(monday.getUTCDate() - day);

      // Convert the Monday date into a YYYY-MM-DD string to identify the week.
      const key = monday.toISOString().slice(0, 10);

      // Increase the weekly sighting count by one.
      // If the week does not exist yet, initialise its count to zero first.
      weeklyCounts.set(
          key,
          (weeklyCounts.get(key) || 0) + 1
      );
  });

  // Get all the week identifiers from the weekly counts and sort them chronologically.
  const weeks = [...weeklyCounts.keys()].sort();

  // Convert the week dates into readable labels for the chart's horizontal axis.
  const labels = weeks.map(week =>
      new Date(`${week}T00:00:00Z`).toLocaleDateString("en-GB", {
          day: "2-digit",
          month: "short",
          year: "numeric",
          timeZone: "UTC"
      })
  );

  // Display a message when no sightings exist in the selected period.

  // Retrieve the total number of sightings recorded for each week.
  const values = weeks.map(week => weeklyCounts.get(week));

  // Get references to the chart canvas and the message displayed when no data exists.
  const canvas = document.getElementById("trendChart");
  const noDataMessage = document.getElementById("chartNoData");

  // Check whether any weekly sighting data is available.
  const hasData = values.length > 0;

  // Show or hide the no-data message depending on whether records exist.
  if (noDataMessage) {
      noDataMessage.style.display = hasData ? "none" : "block";
  }

  // Show or hide the graph depending on whether there is data to display.
  if (hasData) {
      canvas.style.display = "block";
  } else {
      canvas.style.display = "none";
  }

  // If there is no data, clear the existing chart.
  if (!hasData) {
      if (trendChart) {
          trendChart.data.labels = [];
          trendChart.data.datasets[0].data = [];
          trendChart.update();
      }
      return;
  }

  // Update existing chart.
  // This avoids creating a new chart every time the filters change.
  if (trendChart) {
      trendChart.data.labels = labels;
      trendChart.data.datasets[0].data = values;
      trendChart.update();
      return;
  }

  // Create chart for the first time.
  // Display weekly sighting totals using a line chart.
  trendChart = new Chart(canvas, {
      type: "line",
      data: {
          // Set the dates displayed along the horizontal axis.
          labels: labels,
          datasets: [{
              // Set the name displayed in the chart legend.
              label: "Weekly Tick Sightings",

              // Supply the weekly sighting totals.
              data: values,

              // Set the line colour and the background fill colour.
              borderColor: "#287a55",
              backgroundColor: "rgba(40, 122, 85, 0.12)",

              // Fill the area underneath the line.
              fill: true,

              // Smooth the line connecting the data points.
              tension: 0.3,

              // Set the size of the individual data points.
              pointRadius: 3
          }]
      },
      options: {
          // Allow the chart to resize automatically.
          responsive: true,

          // Prevent the chart from maintaining a fixed aspect ratio.
          maintainAspectRatio: false,
          plugins: {
              // Display the chart legend.
              legend: {
                  display: true
              }
          },
          scales: {
              // Configure the vertical axis to begin at zero.
              y: {
                  beginAtZero: true,
                  ticks: {
                      // Display whole numbers rather than decimal values.
                      precision: 0
                  }
              }
          }
      }
  });

}

// Weekly chart period buttons
// Select all buttons used to change the time period displayed in the chart.
const trendButtons = document.querySelectorAll(
  ".chart-controls button"
);

// Add a click event listener to each chart period button.
trendButtons.forEach(button => {
  button.addEventListener("click", () => {

      // Retrieve the time period associated with the clicked button.
      const period = button.dataset.period;

      // Log the selected period in the browser console for debugging.
      console.log("Selected graph period:", period);

      // Update the selected chart period.
      selectedTrendPeriod = period;

      // Highlight the active button and remove the active class from the others.
      trendButtons.forEach(btn => {
          btn.classList.toggle("active", btn === button);
      });

      // Refresh graph using current filters.
      // Retrieve sightings matching the currently selected filters.
      const filtered = getFilteredSightings();

      // Update the chart using the selected time period and filtered records.
      updateChart(filtered);
  });
});


// Refresh the map, chart, and status after filtering.
function applyFilters() {

  // Retrieve all sightings that match the current filter selections.
  const filtered = getFilteredSightings();

  // Update the map and retrieve the number of displayed markers.
  const markerCount = updateMap(filtered);

  // Refresh the weekly trend chart using the filtered sightings.
  updateChart(filtered);

  // Display the number of matching sightings and map markers.
  setStatus(
    `${filtered.length} sighting${filtered.length === 1 ? "" : "s"} found; ` +
    `${markerCount} displayed on the map.`
  );
}

// Listen for filter form submissions without reloading the webpage.
filterForm.addEventListener("submit", event => {

  // Prevent the browser from performing its default form submission.
  event.preventDefault();

  // Retrieve the selected start and end dates.
  const from = filterForm.elements.from.value;
  const to = filterForm.elements.to.value;

  // Check that the start date is not later than the end date.
  if (from && to && from > to) {
    setStatus("The start date must be before the end date.", true);
    return;
  }

  // Apply the selected filters when the date range is valid.
  applyFilters();
});

// Listen for the filter reset button being clicked.
filterForm.addEventListener("reset", () => {

  // Wait until the browser has reset the form controls.
  setTimeout(applyFilters, 0);
});

// Report submission requires a separate PHP endpoint.
// Listen for users submitting a new sighting report.
reportForm.addEventListener("submit", async event => {

  // Prevent the browser from reloading the page when the form is submitted.
  event.preventDefault();

  // Clear any previous report submission messages.
  reportStatus.textContent = "";

  // Collect all values entered into the report form.
  const data = new FormData(reportForm);

  // Retrieve and remove unnecessary spaces from the coordinate values.
  const latitude = String(data.get("latitude") || "").trim();
  const longitude = String(data.get("longitude") || "").trim();

  // Check that latitude and longitude are either both entered or both left blank.
  if ((latitude === "") !== (longitude === "")) {
    reportStatus.textContent =
      "Enter both latitude and longitude, or leave both blank.";
    return;
  }

  // Validate the coordinates if the user has provided them.
  if (latitude !== "" && longitude !== "") {
    const lat = Number(latitude);
    const lng = Number(longitude);

    // Ensure that the coordinates contain valid numerical values.
    // Latitude must be between -90 and 90.
    // Longitude must be between -180 and 180.
    if (
      !Number.isFinite(lat) || lat < -90 || lat > 90 ||
      !Number.isFinite(lng) || lng < -180 || lng > 180
    ) {
      reportStatus.textContent = "Enter valid coordinates.";
      return;
    }
  }

  // Retrieve the submit button so it can be disabled during submission.
  const submitButton = reportForm.querySelector('button[type="submit"]');

  // Disable the button to prevent users submitting the report multiple times.
  submitButton.disabled = true;

  // Change the button text to show that the report is being processed.
  submitButton.textContent = "Submitting…";

  try {
    // Change this URL to the PHP endpoint that saves user reports.
    const response = await fetch("api/report.php", {
      method: "POST",
      headers: { Accept: "application/json" },

      // Send the collected report information to the PHP endpoint.
      body: data
    });

    // Convert the server response into a JavaScript object.
    const result = await response.json();

    // Check whether the server successfully processed the submitted report.
    if (!response.ok || result.success === false) {
      throw new Error(result.error || "The report could not be submitted.");
    }

    // Display a success message after the report has been saved.
    reportStatus.textContent =
      result.message || "Thank you. Your sighting has been submitted.";

    // Clear the report form after successful submission.
    reportForm.reset();

    // Restore the report date to today's date.
    if (sightingDate) {
      sightingDate.value = new Date().toISOString().slice(0, 10);
    }

    // Reload the sightings to refresh the dashboard with the latest database records.
    await loadSightings();

  } catch (error) {

    // Log any submission errors in the browser console for debugging.
    console.error("Report submission failed:", error);

    // Display an error message if the report could not be submitted.
    reportStatus.textContent =
      error.message || "Unable to submit the report. Please try again.";

  } finally {

    // Re-enable the submit button whether the submission succeeds or fails.
    submitButton.disabled = false;

    // Restore the original button text.
    submitButton.textContent = "Submit sighting";
  }
});

// Start the application by loading the initial sighting records from the API.
loadSightings();
});