<?php
// Enables strict typing to help prevent unexpected data type errors.
declare(strict_types=1);

// Sets the response format to JSON.
header('Content-Type: application/json; charset=utf-8');

// Includes the database connection file.
require_once dirname(__DIR__) . '/src/Database.php';

try {
    // Checks whether PHP cURL is enabled.
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is not enabled.');
    }

    // URL of the external Elanco API containing tick sightings.
    $url = 'https://dev-task.elancoapps.com/data/tick-sightings';

    // Starts a cURL request to the external API.
    $ch = curl_init($url);

    // Sets the options for the API request.
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_FOLLOWLOCATION => false
    ]);

    // Sends the request and stores the returned data.
    $body = curl_exec($ch);

    // Gets the HTTP response status code.
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Gets any error returned by cURL.
    $curlError = curl_error($ch);

    // Checks whether the API request failed.
    if ($body === false) {
        throw new RuntimeException('Elanco request failed: ' . $curlError);
    }

    // Checks that the API returned a successful response.
    if ($status !== 200) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Elanco returned HTTP ' . $status
        ]);
        exit;
    }

    // Converts the API response from JSON into a PHP array.
    $records = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    // Checks that the API returned a list of records.
    if (!is_array($records) || !array_is_list($records)) {
        throw new RuntimeException('Unexpected API response format.');
    }

    // Connects to the local database.
    $pdo = getDatabase();

    // Counters used to track the import results.
    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    // Prepares a query to insert new sightings or update existing ones.
    $stmt = $pdo->prepare("
        INSERT INTO sightings (
            external_id,
            sighting_date,
            latin_name,
            location,
            species,
            source
        ) VALUES (
            :external_id,
            :sighting_date,
            :latin_name,
            :location,
            :species,
            'elanco'
        )

        -- Updates the existing record if its external ID already exists.
        ON CONFLICT(external_id) DO UPDATE SET
            sighting_date = excluded.sighting_date,
            latin_name = excluded.latin_name,
            location = excluded.location,
            species = excluded.species

        -- Only updates records originally imported from Elanco.
        WHERE sightings.source = 'elanco'
    ");

    // Prepares a query to check whether a sighting already exists.
    $check = $pdo->prepare("
        SELECT id FROM sightings WHERE external_id = ?
    ");

    // Starts a database transaction for the import process.
    $pdo->beginTransaction();

    // Loops through each sighting received from the API.
    foreach ($records as $record) {

        // Skips the record if it is not an array.
        if (!is_array($record)) {
            $skipped++;
            continue;
        }

        // Extracts the information from each API record.
        $externalId = trim((string) ($record['id'] ?? ''));
        $date = trim((string) ($record['date'] ?? ''));
        $location = trim((string) ($record['location'] ?? ''));
        $species = trim((string) ($record['species'] ?? ''));
        $latinName = trim((string) ($record['latinName'] ?? ''));

        // Skips records that do not have an ID or date.
        if ($externalId === '' || $date === '') {
            $skipped++;
            continue;
        }

        // Checks that the sighting date can be converted into a date object.
        try {
            $parsedDate = new DateTimeImmutable($date);
        } catch (Throwable $e) {
            $skipped++;
            continue;
        }

        // Rejects malformed dates rather than silently changing them.
        if ($parsedDate->format('Y-m-d\TH:i:s') !== $date) {
            $skipped++;
            continue;
        }

        // Checks whether the sighting already exists in the database.
        $check->execute([$externalId]);
        $exists = $check->fetchColumn() !== false;

        // Inserts or updates the sighting using the prepared statement.
        $stmt->execute([
            ':external_id' => $externalId,
            ':sighting_date' => $date,
            ':latin_name' => $latinName !== '' ? $latinName : null,
            ':location' => $location !== '' ? $location : null,
            ':species' => $species !== '' ? $species : null
        ]);

        // Updates the appropriate counter depending on the result.
        if (!$exists) {
            $inserted++;
        } else {
            $updated++;
        }
    }

    // Saves all database changes made during the transaction.
    $pdo->commit();

    // Returns a summary of the import results.
    echo json_encode([
        'success' => true,
        'api_records_received' => count($records),
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'database' => 'database/ticks.sqlite'
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

// Handles errors during the API request or database import.
} catch (Throwable $e) {

    // Reverses database changes if the transaction has not been completed.
    if (isset($pdo) && $pdo instanceof PDO &&
        $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Records the error details in the PHP error log.
    error_log((string) $e);

    // Returns an internal server error response.
    http_response_code(500);

    // Sends a general error message as JSON.
    echo json_encode([
        'success' => false,
        'error' => 'Import failed. Check the PHP error log.'
    ], JSON_PRETTY_PRINT);
}