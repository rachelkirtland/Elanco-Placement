<?php
// Enables strict typing to help prevent unexpected data type errors.
declare(strict_types=1);

// Includes the database connection file.
require_once dirname(__DIR__) . '/src/Database.php';

// Sets the response format to JSON and prevents caching.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Checks that the request uses the POST method.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    // Tells the user that only POST requests are allowed.
    header('Allow: POST');
    http_response_code(405);

    // Returns an error message and stops the script.
    echo json_encode(['error' => 'Use POST to submit a report']);
    exit;
}

try {
    // Reads the submitted JSON data and converts it into a PHP array.
    $input = json_decode(
        file_get_contents('php://input'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Checks that the submitted data is a valid array.
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid request body');
    }

    // Retrieves the submitted form values.
    $date = $input['sighting_date'] ?? '';
    $location = trim((string) ($input['location_name'] ?? ''));
    $species = trim((string) ($input['species'] ?? 'Unknown'));
    $region = trim((string) ($input['region'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    // Checks that the sighting date uses the correct format.
    $parsedDate = is_string($date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date)
        : false;

    // Returns an error if the date is invalid.
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Enter a valid sighting date');
    }

    // Prevents users from submitting a future sighting date.
    if ($date > date('Y-m-d')) {
        throw new InvalidArgumentException('Date cannot be in the future');
    }

    // Checks that a location has been entered and is not too long.
    if ($location === '' || strlen($location) > 150) {
        throw new InvalidArgumentException('Enter a location (maximum 150 characters)');
    }

    // Lists the tick species that users are allowed to select.
    $allowedSpecies = [
        'Unknown',
        'Ixodes ricinus',
        'Ixodes hexagonus',
        'Dermacentor reticulatus',
        'Other'
    ];

    // Checks that the selected species is in the allowed list.
    if (!in_array($species, $allowedSpecies, true)) {
        throw new InvalidArgumentException('Select a valid species');
    }

    // Checks that the region and notes do not exceed their character limits.
    if (strlen($region) > 100 || strlen($notes) > 2000) {
        throw new InvalidArgumentException('Region or notes exceed the allowed length');
    }

    // Retrieves the latitude and longitude if they were provided.
    $lat = $input['latitude'] ?? null;
    $lon = $input['longitude'] ?? null;

    // Checks that both coordinates are provided together.
    if (($lat === null) !== ($lon === null)) {
        throw new InvalidArgumentException('Provide both coordinates or neither');
    }

    // Validates the coordinates if they have been provided.
    if ($lat !== null || $lon !== null) {

        // Checks that the coordinates are numeric and within valid ranges.
        if (!is_numeric($lat) || !is_numeric($lon)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lon < -180 || (float) $lon > 180) {
            throw new InvalidArgumentException('Invalid coordinates');
        }

        // Converts the coordinates into decimal numbers.
        $lat = (float) $lat;
        $lon = (float) $lon;
    }

    // Prepares an SQL query to insert the new sighting into the database.
    $stmt = getDatabase()->prepare("
        INSERT INTO sightings (
            sighting_date, latitude, longitude, location_name,
            region, species, notes, source
        ) VALUES (
            :date, :lat, :lon, :location,
            :region, :species, :notes, 'community'
        )
    ");

    // Executes the query using the submitted form values.
    $stmt->execute([
        ':date' => $date,
        ':lat' => $lat,
        ':lon' => $lon,
        ':location' => $location,
        ':region' => $region ?: null,
        ':species' => $species,
        ':notes' => $notes ?: null
    ]);

    // Sets the response status to 201 to indicate successful creation.
    http_response_code(201);

    // Returns a success message and the ID of the new sighting.
    echo json_encode([
        'message' => 'Sighting submitted successfully',
        'id' => (int) getDatabase()->lastInsertId()
    ]);

// Handles errors caused by invalid JSON data.
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON request']);

// Handles errors caused by invalid or missing form information.
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);

// Handles unexpected errors, such as database problems.
} catch (Throwable $e) {

    // Records the error in the PHP error log.
    error_log((string) $e);

    // Returns a general error message to the user.
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save report']);
}