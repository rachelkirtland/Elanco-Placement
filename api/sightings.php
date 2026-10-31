<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

try {
    $pdo = getDatabase();

    // Get one sighting by its local database ID.
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        if ($id === false || $id === null || $id < 1) {
            respond(['error' => 'Invalid sighting ID'], 400);
        }

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
        $stmt->execute([':id' => $id]);

        $sighting = $stmt->fetch();

        if (!$sighting) {
            respond(['error' => 'Sighting not found'], 404);
        }

        respond([
            'success' => true,
            'data' => $sighting
        ]);
    }

    $where = [];
    $params = [];

    // Date filters.
    foreach (['from', 'to'] as $key) {
        if (!isset($_GET[$key]) || trim((string) $_GET[$key]) === '') {
            continue;
        }

        $value = trim((string) $_GET[$key]);

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            $date->format('Y-m-d') !== $value ||
            ($errors !== false &&
                ($errors['warning_count'] > 0 ||
                 $errors['error_count'] > 0))
        ) {
            respond(['error' => "Invalid $key date. Use YYYY-MM-DD."], 400);
        }

        if ($key === 'from') {
            $where[] = 'sighting_date >= :from';
        } else {
            // Include the entire selected end date.
            $where[] = "sighting_date < datetime(:to, '+1 day')";
        }

        $params[":$key"] = $value;
    }

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
    if (isset($_GET['location']) &&
        trim((string) $_GET['location']) !== '') {
        $location = trim((string) $_GET['location']);

        if (strlen($location) > 100) {
            respond(['error' => 'Location search is too long.'], 400);
        }

        $where[] = 'location LIKE :location';
        $params[':location'] = '%' . $location . '%';
    }

    // Species filter.
    if (isset($_GET['species']) &&
        trim((string) $_GET['species']) !== '') {
        $species = trim((string) $_GET['species']);

        if (strlen($species) > 100) {
            respond(['error' => 'Species filter is too long.'], 400);
        }

        $where[] = 'species = :species';
        $params[':species'] = $species;
    }

    // Region filter.
    if (isset($_GET['region']) &&
        trim((string) $_GET['region']) !== '') {
        $region = trim((string) $_GET['region']);

        if (strlen($region) > 100) {
            respond(['error' => 'Region filter is too long.'], 400);
        }

        $where[] = 'region = :region';
        $params[':region'] = $region;
    }

    // Validate pagination.
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

    if ($limit === false || $offset === false) {
        respond([
            'error' => 'Invalid limit or offset.'
        ], 400);
    }

    $sql = '
    SELECT
        sightings.*,
        city_coordinates.latitude,
        city_coordinates.longitude
    FROM sightings
    LEFT JOIN city_coordinates
        ON LOWER(TRIM(sightings.location)) = LOWER(TRIM(city_coordinates.city))
    ';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY sighting_date DESC, id DESC
              LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll();

    respond([
        'success' => true,
        'data' => $data,
        'count' => count($data),
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (Throwable $e) {
    error_log('Sightings API error: ' . $e->getMessage());

    respond([
        'success' => false,
        'error' => 'Unable to retrieve sightings. Check the PHP error log.'
    ], 500);
}
