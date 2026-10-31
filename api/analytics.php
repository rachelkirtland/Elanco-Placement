<?php
// Enables strict typing to help prevent unexpected data type errors.
declare(strict_types=1);

// Includes the database connection file.
require_once dirname(__DIR__) . '/src/Database.php';

// Sets the response format to JSON and prevents caching.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    // Connects to the database.
    $pdo = getDatabase();

    // Gets the requested analytics type, defaulting to summary.
    $type = $_GET['type'] ?? 'summary';

    // SUMMARY: Retrieves the main statistics for the dashboard.
    if ($type === 'summary') {

        // Counts the total number of recorded sightings.
        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM sightings'
        )->fetchColumn();

        // Counts the sightings recorded within the last 30 days.
        $recent = $pdo->query("
            SELECT COUNT(*) FROM sightings
            WHERE sighting_date >= date('now', '-30 days')
        ")->fetchColumn();

        // Counts the number of different regions with recorded sightings.
        $regions = (int) $pdo->query("
            SELECT COUNT(DISTINCT region) FROM sightings
            WHERE region IS NOT NULL AND region <> ''
        ")->fetchColumn();

        // Returns the statistics as JSON.
        echo json_encode([
            'total' => $total,
            'last_30_days' => (int) $recent,
            'regions' => $regions
        ]);
        exit;
    }

    // REGIONS: Retrieves the number of sightings recorded in each region.
    if ($type === 'regions') {

        // Groups sightings by region and sorts them by highest total.
        $stmt = $pdo->query("
            SELECT COALESCE(NULLIF(region, ''), 'Unknown') AS region,
                   COUNT(*) AS total
            FROM sightings
            GROUP BY region
            ORDER BY total DESC
            LIMIT 20
        ");

        // Returns the regional statistics as JSON.
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // TRENDS: Retrieves weekly sighting figures for the chart.
    if ($type === 'trends') {

        // Gets the selected time period, defaulting to one year.
        $period = $_GET['period'] ?? '1year';

        // Determines which date filter to apply based on the selected period.
        switch ($period) {

            // Retrieves sightings from the last six months.
            case '6months':
                $dateFilter = "WHERE sighting_date >= date('now', '-6 months')";
                break;

            // Retrieves sightings from the last year.
            case '1year':
                $dateFilter = "WHERE sighting_date >= date('now', '-1 year')";
                break;

            // Retrieves all recorded sightings without a date restriction.
            case 'all':
                $dateFilter = "";
                break;

            // Returns an error if an unsupported period is selected.
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid trends period']);
                exit;
        }

        // Groups sightings by week and counts the reports for each week.
        $stmt = $pdo->query("
            SELECT
                strftime('%Y-%W', sighting_date) AS week,
                COUNT(*) AS total
            FROM sightings
            $dateFilter
            GROUP BY week
            ORDER BY week ASC
        ");

        // Returns the weekly trend data as JSON.
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Returns an error if an unknown analytics type is requested.
    http_response_code(400);
    echo json_encode(['error' => 'Unknown analytics type']);

// Handles unexpected errors, such as database problems.
} catch (Throwable $e) {

    // Records the error details in the PHP error log.
    error_log((string) $e);

    // Returns a general server error message.
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load analytics']);
}