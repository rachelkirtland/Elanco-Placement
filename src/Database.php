<?php
declare(strict_types=1);

function getDatabase(): PDO
{
    $directory = dirname(__DIR__) . '/database';

    if (!is_dir($directory) &&
        !mkdir($directory, 0750, true) &&
        !is_dir($directory)) {
        throw new RuntimeException(
            'Unable to create database directory.'
        );
    }

    $databasePath = $directory . '/elanco_ticks.sqlite';

    $pdo = new PDO('sqlite:' . $databasePath);

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sightings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT UNIQUE,
            sighting_date TEXT NOT NULL,
            latin_name TEXT,
            location TEXT,
            species TEXT,
            source TEXT NOT NULL DEFAULT 'community',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $columns = $pdo->query("PRAGMA table_info(sightings)")
               ->fetchAll(PDO::FETCH_COLUMN, 1);

if (!in_array('notes', $columns, true)) {
    $pdo->exec("ALTER TABLE sightings ADD COLUMN notes TEXT");
}

    // Create a table to store geographical coordinates for each city.
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS city_coordinates (
        city TEXT PRIMARY KEY COLLATE NOCASE,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL
    )
    ");
    // Store approximate coordinates for all cities in the sightings database.
    $cities = [
        ['Birmingham', 52.4862, -1.8904],
        ['Bristol', 51.4545, -2.5879],
        ['Cardiff', 51.4816, -3.1791],
        ['Edinburgh', 55.9533, -3.1883],
        ['Glasgow', 55.8642, -4.2518],
        ['Leeds', 53.8008, -1.5491],
        ['Leicester', 52.6369, -1.1398],
        ['Liverpool', 53.4084, -2.9916],
        ['London', 51.5072, -0.1276],
        ['Manchester', 53.4808, -2.2426],
        ['Newcastle', 54.9783, -1.6178],
        ['Nottingham', 52.9548, -1.1581],
        ['Sheffield', 53.3811, -1.4701],
        ['Southampton', 50.9097, -1.4044]
    ];

    // Prepare a statement to insert or update city coordinates.
    $stmt = $pdo->prepare("
        INSERT INTO city_coordinates (city, latitude, longitude)
        VALUES (?, ?, ?)
        ON CONFLICT(city) DO UPDATE SET
            latitude = excluded.latitude,
            longitude = excluded.longitude
    ");

    // Insert each city into the database.
    foreach ($cities as $city) {
        $stmt->execute($city);
    }

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_sightings_date
        ON sightings(sighting_date)
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_sightings_location
        ON sightings(location)
    ");

    return $pdo;
}
