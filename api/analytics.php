<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = getDatabase();
    $type = $_GET['type'] ?? 'summary';

    if ($type === 'summary') {
        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM sightings'
        )->fetchColumn();

        $recent = $pdo->query("
            SELECT COUNT(*) FROM sightings
            WHERE sighting_date >= date('now', '-30 days')
        ")->fetchColumn();

        $regions = (int) $pdo->query("
            SELECT COUNT(DISTINCT region) FROM sightings
            WHERE region IS NOT NULL AND region <> ''
        ")->fetchColumn();

        echo json_encode([
            'total' => $total,
            'last_30_days' => (int) $recent,
            'regions' => $regions
        ]);
        exit;
    }

    if ($type === 'regions') {
        $stmt = $pdo->query("
            SELECT COALESCE(NULLIF(region, ''), 'Unknown') AS region,
                   COUNT(*) AS total
            FROM sightings
            GROUP BY region
            ORDER BY total DESC
            LIMIT 20
        ");

        echo json_encode($stmt->fetchAll());
        exit;
    }

    
if ($type === 'trends') {

    $period = $_GET['period'] ?? '1year';

    // Determine date filter
    switch ($period) {
        case '6months':
            $dateFilter = "WHERE sighting_date >= date('now', '-6 months')";
            break;

        case '1year':
            $dateFilter = "WHERE sighting_date >= date('now', '-1 year')";
            break;

        case 'all':
            $dateFilter = "";
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid trends period']);
            exit;
    }

    $stmt = $pdo->query("
        SELECT
            strftime('%Y-%W', sighting_date) AS week,
            COUNT(*) AS total
        FROM sightings
        $dateFilter
        GROUP BY week
        ORDER BY week ASC
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}


    http_response_code(400);
    echo json_encode(['error' => 'Unknown analytics type']);

} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load analytics']);
}
