<?php
/**
 * 5S Dashboard - Holiday API
 * Provides the current working schedule, public holidays, and extra working days.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Allow external callers

$config_file = __DIR__ . '/dashboard_config.json';

if (!file_exists($config_file)) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Configuration file not found'
    ]);
    exit;
}

$config = json_decode(file_get_contents($config_file), true);
$schedule = $config['schedule'] ?? [
    'work_days' => [2, 3, 4, 5, 6],
    'holidays' => [],
    'extra_work_days' => []
];

// Day mapping for better API readability
$days_map = [
    2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 
    5 => 'Thursday', 6 => 'Friday', 0 => 'Saturday', 1 => 'Sunday'
];

$response = [
    'status' => 'success',
    'data' => [
        'weekly_schedule' => array_map(function($d) use ($days_map) {
            return [
                'day_id' => $d,
                'day_name' => $days_map[$d] ?? 'Unknown'
            ];
        }, $schedule['work_days']),
        'public_holidays' => $schedule['holidays'],
        'extra_working_days' => $schedule['extra_work_days'],
        'last_updated' => $config['updatetime'] ?? null
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
