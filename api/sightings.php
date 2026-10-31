<?php
// Strict typing helps prevent unexpected data type errors.
declare(strict_types=1);

// Includes the database connection file.
require_once dirname(__DIR__) . '/src/Database.php';

// Sets the response format to JSON and prevents caching.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Function used to send a JSON response and stop the script.
function respond(array $data, int $status = 200): void
{
    // Sets the HTTP response status code.
    http_response_code($status);

    // Converts the response data into JSON format.
    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    // Stops the script after sending the response.
    exit;
}

try {
    // Connects to the database.
    $pdo = getDatabase();

    // Get one sighting by its local database ID.
    if (isset($_GET['id'])) {

        // Checks that the supplied ID is a valid integer.
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        // Returns an error if the ID is invalid or less than 1.
        if ($id === false || $id === null || $id < 1) {
            respond(['error' => 'Invalid sighting ID'], 400);
        }

        // Retrieves the selected sighting and its map coordinates.
        $stmt = $pdo->prepare(
            'SELECT
                sightings.*,
                city_coordinates.latitude,
                city_coordinates.longitude
            FROM sightings
            LEFT JOIN city_coordinates
                ON LOWER(TRIM(sightings.location)) = LOWER(TRIM(city_coordinates.city))
            WHERE sightings.id = :id'
        );

        // Runs the query using the supplied ID.
        $stmt->execute([':id' => $id]);

        // Stores the matching sighting.
        $sighting = $stmt->fetch();

        // Returns an error if the sighting does not exist.
        if (!$sighting) {
            respond(['error' => 'Sighting not found'], 404);
        }

        // Returns the selected sighting as JSON.
        respond([
            'success' => true,
            'data' => $sighting
        ]);
    }

    // Arrays used to store search conditions and their values.
    $where = [];
    $params = [];

    // Date filters.
    // Checks whether a start or end date has been provided.
    foreach (['from', 'to'] as $key) {
        if (!isset($_GET[$key]) || trim((string) $_GET[$key]) === '') {
            continue;
        }

        // Removes unnecessary spaces from the date.
        $value = trim((string) $_GET[$key]);

        // Checks that the date follows the YYYY-MM-DD format.
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        // Returns an error if the date is invalid.
        if (
            !$date ||
            $date->format('Y-m-d') !== $value ||
            ($errors !== false &&
                ($errors['warning_count'] > 0 ||
                 $errors['error_count'] > 0))
        ) {
            respond(['error' => "Invalid $key date. Use YYYY-MM-DD."], 400);
        }

        // Adds the appropriate date condition to the search.
        if ($key === 'from') {
            $where[] = 'sighting_date >= :from';
        } else {
            // Include the entire selected end date.
            $where[] = "sighting_date < datetime(:to, '+1 day')";
        }

        // Stores the date value for the SQL query.
        $params[":$key"] = $value;
    }

    // Checks that the start date is not later than the end date.
    if (
        isset($_GET['from'], $_GET['to']) &&
        $_GET['from'] !== '' &&
        $_GET['to'] !== '' &&
        $_GET['from'] > $_GET['to']
    ) {
        respond([
            'error' => 'Start date must precede end date.'
        ], 400);
    }

    // Search city/location.
    // Filters sightings using the location entered by the user.
    if (isset($_GET['location']) &&
        trim((string) $_GET['location']) !== '') {

        $location = trim((string) $_GET['location']);

        // Checks that the location search is not too long.
        if (strlen($location) > 100) {
            respond(['error' => 'Location search is too long.'], 400);
        }

        // Uses LIKE to find locations containing the search text.
        $where[] = 'location LIKE :location';
        $params[':location'] = '%' . $location . '%';
    }

    // Species filter.
    // Filters sightings by the selected tick species.
    if (isset($_GET['species']) &&
        trim((string) $_GET['species']) !== '') {

        $species = trim((string) $_GET['species']);

        // Checks that the species value is not too long.
        if (strlen($species) > 100) {
            respond(['error' => 'Species filter is too long.'], 400);
        }

        // Adds the species condition to the search.
        $where[] = 'species = :species';
        $params[':species'] = $species;
    }

    // Validate pagination.
    // Sets the maximum number of results and starting position.
    $limit = filter_var(
        $_GET['limit'] ?? 500,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2000]]
    );

    $offset = filter_var(
        $_GET['offset'] ?? 0,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    // Returns an error if the pagination values are invalid.
    if ($limit === false || $offset === false) {
        respond([
            'error' => 'Invalid limit or offset.'
        ], 400);
    }

    // Builds the SQL query to retrieve sightings and map coordinates.
    $sql = '
    SELECT
        sightings.*,
        city_coordinates.latitude,
        city_coordinates.longitude
    FROM sightings
    LEFT JOIN city_coordinates
        ON LOWER(TRIM(sightings.location)) = LOWER(TRIM(city_coordinates.city))
    ';

    // Adds the search filters if any have been provided.
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Sorts the results by newest date and ID, then applies pagination.
    $sql .= ' ORDER BY sighting_date DESC, id DESC
              LIMIT :limit OFFSET :offset';

    // Prepares the SQL query before executing it.
    $stmt = $pdo->prepare($sql);

    // Adds the filter values safely to the prepared statement.
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    // Adds the pagination values to the query.
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Executes the database query.
    $stmt->execute();

    // Retrieves all matching sightings.
    $data = $stmt->fetchAll();

    // Returns the sightings and pagination information as JSON.
    respond([
        'success' => true,
        'data' => $data,
        'count' => count($data),
        'limit' => $limit,
        'offset' => $offset
    ]);

// Handles unexpected errors during database operations.
} catch (Throwable $e) {

    // Records the error in the PHP error log.
    error_log('Sightings API error: ' . $e->getMessage());

    // Returns a general error message to the user.
    respond([
        'success' => false,
        'error' => 'Unable to retrieve sightings. Check the PHP error log.'
    ], 500);
}