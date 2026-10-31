Elanco Tick Sightings 
Project Overview

The Elanco Tick Sightings is a web-based application designed to display and explore tick sighting records across the United Kingdom.
The dashboard allows users to view geographical distributions of sightings, analyse trends over time and submit new sighting reports.

**Features**
Interactive Map: Displays tick sightings using Leaflet and OpenStreetMap.
City Markers: Shows approximate geographical locations based on city coordinates stored in the database.
Species Filtering: Allows users to filter sightings by existing tick species.
Date Filtering: Filter records using a selected date range.
Region Filtering: Search sightings by geographical region.
Trend Chart: Displays weekly tick sighting trends using Chart.js.
Sighting Reports: Allows users to submit new tick sighting records.
Dashboard Statistics: Displays total sightings, recent sightings and the number of regions represented.

**Technologies Used**
HTML5
CSS
JavaScript
PHP
SQLite
Leaflet.js
Chart.js
OpenStreetMap

**Database**
The application uses an SQLite database 
The database contains two main tables:
sightings – Stores tick sighting records.
city_coordinates – Stores approximate latitude and longitude coordinates for supported cities.

**Project Structure**
api/ – PHP API endpoints.
src/ – Database connection and supporting PHP files.
database/ – SQLite database storage.
index.html – Main dashboard page.
css/ – Stylesheets.
js/ – JavaScript functionality.

**Usage**
Open the dashboard in a web browser.
Explore sightings using the interactive map.
Click a city marker to view its recorded sightings.
Use the filters to search by date, species or region.
View weekly sighting trends.
Submit new sightings using the reporting form.
