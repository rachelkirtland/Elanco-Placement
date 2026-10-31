<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Send a JSON response.
function respond(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(['success' => false, 'error' => 'Use POST to submit a report'], 405);
}

try {

    // Retrieve submitted form values.
    $date = trim($_POST['sighting_date'] ?? '');
    $location = trim($_POST['location_name'] ?? '');
    $species = trim($_POST['species'] ?? 'Unknown');
    $notes = trim($_POST['notes'] ?? '');

    // Validate date.
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$parsedDate ||
        $parsedDate->format('Y-m-d') !== $date ||
        ($dateErrors !== false &&
            ($dateErrors['warning_count'] > 0 ||
             $dateErrors['error_count'] > 0))
    ) {
        throw new InvalidArgumentException('Enter a valid sighting date');
    }

    if ($date > date('Y-m-d')) {
        throw new InvalidArgumentException('Date cannot be in the future');
    }

    // Validate location.
    if ($location === '' || strlen($location) > 150) {
        throw new InvalidArgumentException(
            'Enter a location (maximum 150 characters)'
        );
    }

    // Validate species.
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

    // Validate notes.
    if (strlen($notes) > 2000) {
        throw new InvalidArgumentException(
            'Notes exceed the allowed length'
        );
    }

    // Connect to the database.
    $pdo = getDatabase();

    // Insert the report without a region field or coordinates.
    $stmt = $pdo->prepare("
        INSERT INTO sightings (
            sighting_date,
            location,
            species,
            notes,
            source
        )
        VALUES (
            :date,
            :location,
            :species,
            :notes,
            'community'
        )
    ");

    $stmt->execute([
        ':date' => $date,
        ':location' => $location,
        ':species' => $species,
        ':notes' => $notes ?: null
    ]);

    // Retrieve the new report ID.
    $newId = (int) $pdo->lastInsertId();

    respond([
        'success' => true,
        'message' => 'Sighting submitted successfully!',
        'id' => $newId
    ], 201);

} catch (InvalidArgumentException $e) {

    respond([
        'success' => false,
        'error' => $e->getMessage()
    ], 422);

} catch (Throwable $e) {

    error_log('Report submission error: ' . $e->getMessage());

    respond([
        'success' => false,
        'error' => 'Unable to save report. Check the PHP error log.'
    ], 500);
}
