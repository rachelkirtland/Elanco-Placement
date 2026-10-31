<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Use POST to submit a report']);
    exit;
}

try {
    $input = json_decode(
        file_get_contents('php://input'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid request body');
    }

    $date = $input['sighting_date'] ?? '';
    $location = trim((string) ($input['location_name'] ?? ''));
    $species = trim((string) ($input['species'] ?? 'Unknown'));
    $region = trim((string) ($input['region'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));

    $parsedDate = is_string($date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date)
        : false;

    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Enter a valid sighting date');
    }

    if ($date > date('Y-m-d')) {
        throw new InvalidArgumentException('Date cannot be in the future');
    }

    if ($location === '' || strlen($location) > 150) {
        throw new InvalidArgumentException('Enter a location (maximum 150 characters)');
    }

    $allowedSpecies = [
        'Unknown',
        'Ixodes ricinus',
        'Ixodes hexagonus',
        'Dermacentor reticulatus',
        'Other'
    ];

    if (!in_array($species, $allowedSpecies, true)) {
        throw new InvalidArgumentException('Select a valid species');
    }

    if (strlen($region) > 100 || strlen($notes) > 2000) {
        throw new InvalidArgumentException('Region or notes exceed the allowed length');
    }

    $lat = $input['latitude'] ?? null;
    $lon = $input['longitude'] ?? null;

    if (($lat === null) !== ($lon === null)) {
        throw new InvalidArgumentException('Provide both coordinates or neither');
    }

    if ($lat !== null || $lon !== null) {
        if (!is_numeric($lat) || !is_numeric($lon)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lon < -180 || (float) $lon > 180) {
            throw new InvalidArgumentException('Invalid coordinates');
        }

        $lat = (float) $lat;
        $lon = (float) $lon;
    }

    $stmt = getDatabase()->prepare("
        INSERT INTO sightings (
            sighting_date, latitude, longitude, location_name,
            region, species, notes, source
        ) VALUES (
            :date, :lat, :lon, :location,
            :region, :species, :notes, 'community'
        )
    ");

    $stmt->execute([
        ':date' => $date,
        ':lat' => $lat,
        ':lon' => $lon,
        ':location' => $location,
        ':region' => $region ?: null,
        ':species' => $species,
        ':notes' => $notes ?: null
    ]);

    http_response_code(201);

    echo json_encode([
        'message' => 'Sighting submitted successfully',
        'id' => (int) getDatabase()->lastInsertId()
    ]);

} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON request']);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);

} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save report']);
}
