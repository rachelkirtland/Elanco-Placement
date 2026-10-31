<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/src/Database.php';

try {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is not enabled.');
    }

    $url = 'https://dev-task.elancoapps.com/data/tick-sightings';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_FOLLOWLOCATION => false
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Elanco request failed: ' . $curlError);
    }

    if ($status !== 200) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Elanco returned HTTP ' . $status
        ]);
        exit;
    }

    $records = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($records) || !array_is_list($records)) {
        throw new RuntimeException('Unexpected API response format.');
    }

    $pdo = getDatabase();

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    $stmt = $pdo->prepare("
        INSERT INTO sightings (
            external_id,
            sighting_date,
            latin_name,
            location,
            region,
            species,
            source
        ) VALUES (
            :external_id,
            :sighting_date,
            :latin_name,
            :location,
            :region,
            :species,
            'elanco'
        )
        ON CONFLICT(external_id) DO UPDATE SET
            sighting_date = excluded.sighting_date,
            latin_name = excluded.latin_name,
            location = excluded.location,
            region = excluded.region,
            species = excluded.species
        WHERE sightings.source = 'elanco'
    ");

    $check = $pdo->prepare("
        SELECT id FROM sightings WHERE external_id = ?
    ");

    $pdo->beginTransaction();

    foreach ($records as $record) {
        if (!is_array($record)) {
            $skipped++;
            continue;
        }

        $externalId = trim((string) ($record['id'] ?? ''));
        $date = trim((string) ($record['date'] ?? ''));
        $location = trim((string) ($record['location'] ?? ''));
        $species = trim((string) ($record['species'] ?? ''));
        $latinName = trim((string) ($record['latinName'] ?? ''));

        if ($externalId === '' || $date === '') {
            $skipped++;
            continue;
        }

        try {
            $parsedDate = new DateTimeImmutable($date);
        } catch (Throwable $e) {
            $skipped++;
            continue;
        }

        // Reject malformed dates rather than silently changing them.
        if ($parsedDate->format('Y-m-d\TH:i:s') !== $date) {
            $skipped++;
            continue;
        }

        $check->execute([$externalId]);
        $exists = $check->fetchColumn() !== false;

        $stmt->execute([
            ':external_id' => $externalId,
            ':sighting_date' => $date,
            ':latin_name' => $latinName !== '' ? $latinName : null,
            ':location' => $location !== '' ? $location : null,
            ':region' => $location !== '' ? $location : null,
            ':species' => $species !== '' ? $species : null
        ]);

        if (!$exists) {
            $inserted++;
        } else {
            $updated++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'api_records_received' => count($records),
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'database' => 'database/ticks.sqlite'
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO &&
        $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $e);
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Import failed. Check the PHP error log.'
    ], JSON_PRETTY_PRINT);
}
