<?php
session_start();
include "sqlconnect.php";

$json_file = 'employee_counts.json';
$config_file = 'dashboard_config.json';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Save Employee Counts
    $counts = $_POST['counts'] ?? [];
    file_put_contents($json_file, json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Save Dashboard Config
    $new_config = $_POST['config'] ?? [];
    // Ensure numeric types for certain fields and validate column widths
    $width_sum = 0;
    foreach (['column_widths', 'font_sizes', 'summary_score'] as $group) {
        if (isset($new_config[$group])) {
            foreach ($new_config[$group] as $key => $val) {
                // Keep text as string, cast others to float/int
                if ($key !== 'text' && $key !== 'color' && $key !== 'bg_color' && $key !== 'border_color') {
                    $new_config[$group][$key] = (float)$val;
                }
                if ($group === 'column_widths') $width_sum += (float)$val;
            }
        }
    }
    
    // PHP Side Validation for Widths (allow small float margin)
    if (abs($width_sum - 100) > 0.1) {
        $error_message = "Total column width must be exactly 100% (Current: $width_sum%)";
    }

    if (!isset($error_message)) {
        // Handle dynamic conditions
        if (isset($new_config['colors']['conditions'])) {
            // Re-index to ensure it's a clean array and cast threshold to float
            $new_config['colors']['conditions'] = array_values($new_config['colors']['conditions']);
            foreach ($new_config['colors']['conditions'] as $key => $cond) {
                $new_config['colors']['conditions'][$key]['threshold'] = (float)$cond['threshold'];
                $new_config['colors']['conditions'][$key]['threshold2'] = !empty($cond['threshold2']) ? (float)$cond['threshold2'] : null;
                $new_config['colors']['conditions'][$key]['operator'] = $cond['operator'] ?? '<';
                // Column specific overrides
                $new_config['colors']['conditions'][$key]['use_custom_no'] = isset($cond['use_custom_no']);
                $new_config['colors']['conditions'][$key]['use_custom_area'] = isset($cond['use_custom_area']);
                $new_config['colors']['conditions'][$key]['use_custom_score'] = isset($cond['use_custom_score']);
            }
        } else {
            $new_config['colors']['conditions'] = [];
        }

        // Handle Schedule
        if (isset($new_config['schedule'])) {
            $new_config['schedule']['work_days'] = array_map('intval', $new_config['schedule']['work_days'] ?? []);
            // Clean up holiday/extra arrays (ensure they are lists and filtered)
            $new_config['schedule']['holidays'] = array_values(array_filter($new_config['schedule']['holidays'] ?? [], function($h) { return !empty($h['date']); }));
            $new_config['schedule']['extra_work_days'] = array_values(array_filter($new_config['schedule']['extra_work_days'] ?? [], function($e) { return !empty($e['date']); }));
        } else {
            // Default to Mon-Fri if nothing submitted
            $new_config['schedule'] = ['work_days' => [2,3,4,5,6], 'holidays' => [], 'extra_work_days' => []];
        }

        date_default_timezone_set('Asia/Bangkok');
        $new_config['updatetime'] = date('Y-m-d H:i:s');
        file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $_SESSION['message'] = "Saved successfully!";
        
        $active_tab = $_POST['active_tab'] ?? 'employee';
        header("Location: manage_employees.php?tab=$active_tab");
        exit;
    } else {
        $_SESSION['error_message'] = $error_message;
        $active_tab = $_POST['active_tab'] ?? 'employee';
        header("Location: manage_employees.php?tab=$active_tab");
        exit;
    }
}

// Load messages from session
$message = $_SESSION['message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['message'], $_SESSION['error_message']);

// Load existing data
$employee_data = [];
if (file_exists($json_file)) {
    $employee_data = json_decode(file_get_contents($json_file), true) ?: [];
}

$config = [];
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true) ?: [];
}

// Fetch Areas
$query = "
    SELECT AREA 
    FROM RSMSSQL.DAILY_FACTORY_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK)
    WHERE AREA IS NOT NULL
    GROUP BY AREA
    UNION 
    SELECT AREA 
    FROM RSMSSQL.DAILY_OFFICE_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK)
    WHERE AREA IS NOT NULL
    GROUP BY AREA
    ORDER BY AREA ASC
";
$getRes = $conn->prepare($query);
$getRes->execute();
$areas = $getRes->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employee Counts - 5S Score</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/inter.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin-top: 50px;
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        h2 {
            font-weight: 700;
            color: #0052cc;
            margin-bottom: 30px;
            text-align: center;
        }
        .table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #fff;
            border-bottom: 2px solid #dee2e6;
            color: #0052cc;
        }
        .th-factory { background-color: #e3f2fd !important; color: #007bff !important; }
        .th-office { background-color: #fff3e0 !important; color: #e65100 !important; }
        .td-factory { background-color: #f7fbff; }
        .td-office { background-color: #fffcf8; }
        .btn-save {
            background: linear-gradient(135deg, #0052cc 0%, #003d99 100%);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,82,204,0.3);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 82, 204, 0.1);
            border-color: #0052cc;
        }
        .alert {
            border-radius: 10px;
            position: sticky;
            top: 20px;
            z-index: 1050;
        }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
        .nav-link { font-weight: 600; color: #666; border: none !important; border-bottom: 3px solid transparent !important; }
        .nav-link.active { color: #0052cc !important; border-bottom: 3px solid #0052cc !important; background: none !important; }
        .config-section { background: #fdfdfd; border: 1px solid #eee; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .config-title { font-size: 1.1rem; font-weight: 700; color: #444; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .input-group-text { font-size: 0.9rem; background: #f1f3f5; }
        .condition-card { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 15px; margin-bottom: 15px; transition: box-shadow 0.2s; }
        .condition-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .advanced-settings { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-top: 12px; border: 1px dashed #ced4da; }
    </style>
</head>
<body>

<div class="container" style="max-width: 900px;">
    <h2>Dashboard Management</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="manageTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="employee-tab" data-bs-toggle="tab" data-bs-target="#employee-pane" type="button" role="tab">Employee Counts</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config-pane" type="button" role="tab">Dashboard Settings</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule-pane" type="button" role="tab">Holidays & Schedule</button>
        </li>
    </ul>

    <form method="POST">
        <input type="hidden" name="active_tab" id="active_tab_input" value="employee">
        <div class="tab-content" id="manageTabsContent">
            <!-- Tab 1: Employee Counts -->
            <div class="tab-pane fade show active" id="employee-pane" role="tabpanel">
                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto; border-radius: 8px; border: 1px solid #dee2e6;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50%;">Area Name</th>
                                <th style="width: 25%;" class="th-factory text-center">Factory</th>
                                <th style="width: 25%;" class="th-office text-center">Office</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($areas as $area_name): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($area_name); ?></strong></td>
                                    <td class="td-factory">
                                        <input type="number" name="counts[<?php echo htmlspecialchars($area_name); ?>][factory]" class="form-control form-control-sm text-center" value="<?php echo htmlspecialchars($employee_data[$area_name]['factory'] ?? ''); ?>" min="0">
                                    </td>
                                    <td class="td-office">
                                        <input type="number" name="counts[<?php echo htmlspecialchars($area_name); ?>][office]" class="form-control form-control-sm text-center" value="<?php echo htmlspecialchars($employee_data[$area_name]['office'] ?? ''); ?>" min="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Dashboard Settings -->
            <div class="tab-pane fade" id="config-pane" role="tabpanel">
                <div style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
                    <!-- Column Widths -->
                    <div class="config-section">
                        <div class="config-title d-flex justify-content-between align-items-center">
                            <span>Column Widths (%)</span>
                            <span id="width-total-badge" class="badge rounded-pill bg-success" style="font-size: 0.8rem;">Total: 100%</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">No. Column</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[column_widths][no]" class="form-control width-input" value="<?php echo $config['column_widths']['no'] ?? 8; ?>" step="0.1">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Area Column</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[column_widths][area]" class="form-control width-input" value="<?php echo $config['column_widths']['area'] ?? 77; ?>" step="0.1">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Score Column</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[column_widths][score]" class="form-control width-input" value="<?php echo $config['column_widths']['score'] ?? 15; ?>" step="0.1">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Font Sizes -->
                    <div class="config-section">
                        <div class="config-title">Font Sizes (px)</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Main Title</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][title]" class="form-control" value="<?php echo $config['font_sizes']['title'] ?? 70; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Month Display</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][month]" class="form-control" value="<?php echo $config['font_sizes']['month'] ?? 40; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Table Header</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][table_head]" class="form-control" value="<?php echo $config['font_sizes']['table_head'] ?? 28; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Table Body</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][table_body]" class="form-control" value="<?php echo $config['font_sizes']['table_body'] ?? 34; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Col: No.</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][col_no]" class="form-control" value="<?php echo $config['font_sizes']['col_no'] ?? 30; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Col: Area</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][col_area]" class="form-control" value="<?php echo $config['font_sizes']['col_area'] ?? 36; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Col: Score</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[font_sizes][col_score]" class="form-control" value="<?php echo $config['font_sizes']['col_score'] ?? 30; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Score (Top Right) -->
                    <div class="config-section">
                        <div class="config-title">Average Score (Top-Right)</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Display Text</label>
                                <input type="text" name="config[summary_score][text]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['summary_score']['text'] ?? '5S Rangsit'); ?>">
                            </div>
                             <div class="col-md-3">
                                <label class="form-label small">Label Font Size (px)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][font_size]" class="form-control" value="<?php echo $config['summary_score']['font_size'] ?? 42; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Score Font Size (px)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][score_font_size]" class="form-control" value="<?php echo $config['summary_score']['score_font_size'] ?? 80; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Font Color</label>
                                <input type="color" name="config[summary_score][color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['summary_score']['color'] ?? '#0052cc'); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label small">Position: Top</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][top]" class="form-control" value="<?php echo $config['summary_score']['top'] ?? 10; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Position: Right</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][right]" class="form-control" value="<?php echo $config['summary_score']['right'] ?? 0; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Background</label>
                                <input type="color" name="config[summary_score][bg_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['summary_score']['bg_color'] ?? '#f0f7ff'); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label small">Border Color</label>
                                <input type="color" name="config[summary_score][border_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['summary_score']['border_color'] ?? '#0052cc'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Border Width</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][border_width]" class="form-control" value="<?php echo $config['summary_score']['border_width'] ?? 3; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Border Radius</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="config[summary_score][border_radius]" class="form-control" value="<?php echo $config['summary_score']['border_radius'] ?? 12; ?>">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Colors -->
                    <div class="config-section">
                        <div class="config-title">Layout Colors</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Title Color</label>
                                <input type="color" name="config[colors][title_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['colors']['title_color'] ?? '#0052cc'); ?>" title="Choose your color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Month Color</label>
                                <input type="color" name="config[colors][month_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['colors']['month_color'] ?? '#d86b00'); ?>" title="Choose your color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Summary Header BG</label>
                                <input type="color" name="config[colors][summary_header_bg]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['colors']['summary_header_bg'] ?? '#0052cc'); ?>" title="Choose your color">
                            </div>
                        </div>
                    </div>

                    <!-- Colors & Logic -->
                    <div class="config-section">
                        <div class="config-title">Colors & Thresholds</div>
                        
                        <!-- Scoring Mode Selector -->
                        <div class="mb-4 p-3 bg-light rounded border">
                            <label class="form-label small fw-bold d-block mb-3">Scoring System Mode</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="config[colors][scoring_mode]" id="mode_standard" value="standard" <?php echo ($config['colors']['scoring_mode'] ?? 'custom') === 'standard' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="mode_standard">
                                        <strong>Summary Standard (Fixed)</strong><br>
                                        <span class="text-muted">Uses fixed color bands (80/70/60/50) from the summary page. Only affects the score cell background.</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="config[colors][scoring_mode]" id="mode_custom" value="custom" <?php echo ($config['colors']['scoring_mode'] ?? 'custom') === 'custom' ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="mode_custom">
                                        <strong>Custom (Row Conditions)</strong><br>
                                        <span class="text-muted">Allows you to define your own thresholds and colors for the entire row.</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label small">Default Score Color</label>
                                <input type="color" name="config[colors][default_score_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($config['colors']['default_score_color'] ?? '#0b600d'); ?>" title="Choose your color">
                            </div>
                        </div>

                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <label class="form-label small fw-bold mb-0">Score Color Conditions (If Score < Threshold)</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCondition()">+ Add Condition</button>
                        </div>
                        
                        <div id="conditions-container">
                            <?php 
                            $conditions = $config['colors']['conditions'] ?? [];
                            foreach ($conditions as $index => $cond): ?>
                                <div class="condition-card card">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-3">
                                            <select name="config[colors][conditions][<?php echo $index; ?>][operator]" class="form-select form-select-sm operator-select" onchange="toggleThreshold2(this)">
                                                <option value="<" <?php if(($cond['operator'] ?? '') == '<') echo 'selected'; ?>>If Score < </option>
                                                <option value="<=" <?php if(($cond['operator'] ?? '') == '<=') echo 'selected'; ?>>If Score <= </option>
                                                <option value=">" <?php if(($cond['operator'] ?? '') == '>') echo 'selected'; ?>>If Score > </option>
                                                <option value=">=" <?php if(($cond['operator'] ?? '') == '>=') echo 'selected'; ?>>If Score >= </option>
                                                <option value="between" <?php if(($cond['operator'] ?? '') == 'between') echo 'selected'; ?>>Between</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="config[colors][conditions][<?php echo $index; ?>][threshold]" class="form-control" value="<?php echo $cond['threshold']; ?>" step="0.1">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="col threshold2-col <?php echo ($cond['operator'] ?? '') !== 'between' ? 'd-none' : ''; ?>">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">and</span>
                                                <input type="number" name="config[colors][conditions][<?php echo $index; ?>][threshold2]" class="form-control" value="<?php echo $cond['threshold2'] ?? ''; ?>" step="0.1">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="col-2 text-center">
                                            <label class="small d-block mb-1">Text Color</label>
                                            <input type="color" name="config[colors][conditions][<?php echo $index; ?>][color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($cond['color'] ?? '#000000'); ?>" title="Choose your color">
                                        </div>
                                        <div class="col-2 text-center">
                                            <label class="small d-block mb-1">BG Color</label>
                                            <input type="color" name="config[colors][conditions][<?php echo $index; ?>][bg_color]" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($cond['bg_color'] ?? '#ffffff'); ?>" title="Choose your color">
                                        </div>
                                        <div class="col-auto">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" onclick="toggleAdvanced(this)">Columns</button>
                                                <button type="button" class="btn btn-outline-danger" onclick="this.closest('.condition-card').remove()">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Advanced Column Colors -->
                                    <div class="advanced-settings <?php echo (!empty($cond['use_custom_no']) || !empty($cond['use_custom_area']) || !empty($cond['use_custom_score'])) ? '' : 'd-none'; ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-1">
                                                    <input class="form-check-input" type="checkbox" name="config[colors][conditions][<?php echo $index; ?>][use_custom_no]" <?php echo !empty($cond['use_custom_no']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small">Custom No.</label>
                                                </div>
                                                <div class="input-group input-group-sm mb-1">
                                                    <span class="input-group-text small">Text</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][color_no]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['color_no'] ?? '#000000'); ?>">
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text small">BG</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][bg_no]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['bg_no'] ?? 'transparent'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-1">
                                                    <input class="form-check-input" type="checkbox" name="config[colors][conditions][<?php echo $index; ?>][use_custom_area]" <?php echo !empty($cond['use_custom_area']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small">Custom Area</label>
                                                </div>
                                                <div class="input-group input-group-sm mb-1">
                                                    <span class="input-group-text small">Text</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][color_area]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['color_area'] ?? '#000000'); ?>">
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text small">BG</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][bg_area]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['bg_area'] ?? 'transparent'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-1">
                                                    <input class="form-check-input" type="checkbox" name="config[colors][conditions][<?php echo $index; ?>][use_custom_score]" <?php echo !empty($cond['use_custom_score']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small">Custom Score</label>
                                                </div>
                                                <div class="input-group input-group-sm mb-1">
                                                    <span class="input-group-text small">Text</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][color_score]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['color_score'] ?? '#000000'); ?>">
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text small">BG</span>
                                                    <input type="color" name="config[colors][conditions][<?php echo $index; ?>][bg_score]" class="form-control form-control-color" value="<?php echo htmlspecialchars($cond['bg_score'] ?? 'transparent'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Advanced Styles (CSS) -->
                    <div class="config-section mb-0">
                        <div class="config-title mb-0 text-secondary" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#advancedCssCollapse" aria-expanded="false" aria-controls="advancedCssCollapse">
                            Advanced Settings (Custom CSS) <small class="text-muted fw-normal ms-2" style="font-size: 0.8rem;">(Click to expand)</small>
                        </div>
                        <div class="collapse mt-3" id="advancedCssCollapse">
                            <label class="form-label small text-muted">
                                คุณสามารถเพิ่ม CSS เพื่อตกแต่งเพิ่มเติมได้ที่นี่ (ตัวอย่าง:)<br>
                                <code>.title { font-style: italic; }</code><br>
                                <code>thead th { text-transform: uppercase; }</code>
                            </label>
                            <textarea name="config[custom_css]" class="form-control font-monospace text-muted" rows="4" style="font-size: 0.85rem;" placeholder="Enter custom CSS here..."><?php echo htmlspecialchars($config['custom_css'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Holidays & Schedule -->
            <div class="tab-pane fade" id="schedule-pane" role="tabpanel">
                <div style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
                    <!-- Weekly Schedule -->
                    <div class="config-section">
                        <div class="config-title">Weekly Working Schedule</div>
                        <p class="small text-muted mb-3">Select the days that are standard working days for your factory/office.</p>
                        <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded border">
                            <?php 
                                $work_days = $config['schedule']['work_days'] ?? [2,3,4,5,6]; 
                                $days_map = [
                                    2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 
                                    5 => 'Thursday', 6 => 'Friday', 0 => 'Saturday', 1 => 'Sunday'
                                ];
                                foreach($days_map as $val => $label):
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="config[schedule][work_days][]" value="<?php echo $val; ?>" id="day_<?php echo $val; ?>" <?php echo in_array($val, $work_days) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="day_<?php echo $val; ?>">
                                        <?php echo $label; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Holidays -->
                    <div class="config-section">
                        <div class="config-title d-flex justify-content-between align-items-center">
                            <span>Public Holidays (Exclude from Score)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHoliday()">+ Add Holiday</button>
                        </div>
                        <p class="small text-muted">These specific dates will be skipped even if they fall on a work day.</p>
                        <div id="holidays-container">
                            <?php 
                            $holidays = $config['schedule']['holidays'] ?? [];
                            foreach($holidays as $idx => $h): ?>
                                <div class="row g-2 mb-2 holiday-row">
                                    <div class="col-md-4">
                                        <input type="date" name="config[schedule][holidays][<?php echo $idx; ?>][date]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($h['date']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="config[schedule][holidays][<?php echo $idx; ?>][name]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($h['name']); ?>" placeholder="Holiday Name (Optional)">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.holiday-row').remove()">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Extra Work Days -->
                    <div class="config-section">
                        <div class="config-title d-flex justify-content-between align-items-center">
                            <span>Special Working Days (Always Include)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addExtraWork()">+ Add Extra Work Day</button>
                        </div>
                        <p class="small text-muted">These specific dates will be included even if they fall on a weekend/holiday.</p>
                        <div id="extra-work-container">
                            <?php 
                            $extra_days = $config['schedule']['extra_work_days'] ?? [];
                            foreach($extra_days as $idx => $e): ?>
                                <div class="row g-2 mb-2 extra-work-row">
                                    <div class="col-md-4">
                                        <input type="date" name="config[schedule][extra_work_days][<?php echo $idx; ?>][date]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($e['date']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="config[schedule][extra_work_days][<?php echo $idx; ?>][name]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($e['name']); ?>" placeholder="Event Name (Optional)">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.extra-work-row').remove()">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" name="save" id="save-btn" class="btn btn-primary btn-save">Save All Changes</button>
            <a href="index.php" class="btn btn-outline-secondary ms-2" style="border-radius: 8px; padding: 10px 20px;">View Dashboard</a>
        </div>
    </form>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
    let conditionCount = <?php echo count($conditions); ?>;
    function addCondition() {
        const container = document.getElementById('conditions-container');
        const div = document.createElement('div');
        div.className = 'condition-card card';
        div.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-3">
                    <select name="config[colors][conditions][${conditionCount}][operator]" class="form-select form-select-sm operator-select" onchange="toggleThreshold2(this)">
                        <option value="<">If Score < </option>
                        <option value="<=">If Score <= </option>
                        <option value=">">If Score > </option>
                        <option value=">=">If Score >= </option>
                        <option value="between">Between</option>
                    </select>
                </div>
                <div class="col">
                    <div class="input-group input-group-sm">
                        <input type="number" name="config[colors][conditions][${conditionCount}][threshold]" class="form-control" value="0" step="0.1">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col threshold2-col d-none">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">and</span>
                        <input type="number" name="config[colors][conditions][${conditionCount}][threshold2]" class="form-control" value="" step="0.1">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-2 text-center">
                    <label class="small d-block mb-1">Text Color</label>
                    <input type="color" name="config[colors][conditions][${conditionCount}][color]" class="form-control form-control-color w-100" value="#000000" title="Choose your text color">
                </div>
                <div class="col-2 text-center">
                    <label class="small d-block mb-1">BG Color</label>
                    <input type="color" name="config[colors][conditions][${conditionCount}][bg_color]" class="form-control form-control-color w-100" value="#ffffff" title="Choose your background color">
                </div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAdvanced(this)">Columns</button>
                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.condition-card').remove()">Remove</button>
                    </div>
                </div>
            </div>
            <div class="advanced-settings d-none">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="config[colors][conditions][${conditionCount}][use_custom_no]">
                            <label class="form-check-label small">Custom No.</label>
                        </div>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text small">Text</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][color_no]" class="form-control form-control-color" value="#000000">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text small">BG</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][bg_no]" class="form-control form-control-color" value="#ffffff">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="config[colors][conditions][${conditionCount}][use_custom_area]">
                            <label class="form-check-label small">Custom Area</label>
                        </div>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text small">Text</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][color_area]" class="form-control form-control-color" value="#000000">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text small">BG</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][bg_area]" class="form-control form-control-color" value="#ffffff">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="config[colors][conditions][${conditionCount}][use_custom_score]">
                            <label class="form-check-label small">Custom Score</label>
                        </div>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text small">Text</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][color_score]" class="form-control form-control-color" value="#000000">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text small">BG</span>
                            <input type="color" name="config[colors][conditions][${conditionCount}][bg_score]" class="form-control form-control-color" value="#ffffff">
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
        conditionCount++;
    }

    function toggleThreshold2(select) {
        const row = select.closest('.condition-card');
        const t2Col = row.querySelector('.threshold2-col');
        if (select.value === 'between') {
            t2Col.classList.remove('d-none');
        } else {
            t2Col.classList.add('d-none');
            t2Col.querySelector('input').value = '';
        }
    }

    function toggleAdvanced(btn) {
        const card = btn.closest('.condition-card');
        const advanced = card.querySelector('.advanced-settings');
        advanced.classList.toggle('d-none');
        btn.classList.toggle('active');
    }

    let holidayCount = <?php echo count($holidays ?? []); ?>;
    function addHoliday() {
        const container = document.getElementById('holidays-container');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 holiday-row';
        div.innerHTML = `
            <div class="col-md-4">
                <input type="date" name="config[schedule][holidays][${holidayCount}][date]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <input type="text" name="config[schedule][holidays][${holidayCount}][name]" class="form-control form-control-sm" placeholder="Holiday Name (Optional)">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.holiday-row').remove()">Remove</button>
            </div>
        `;
        container.appendChild(div);
        holidayCount++;
    }

    let extraWorkCount = <?php echo count($extra_days ?? []); ?>;
    function addExtraWork() {
        const container = document.getElementById('extra-work-container');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 extra-work-row';
        div.innerHTML = `
            <div class="col-md-4">
                <input type="date" name="config[schedule][extra_work_days][${extraWorkCount}][date]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <input type="text" name="config[schedule][extra_work_days][${extraWorkCount}][name]" class="form-control form-control-sm" placeholder="Event Name (Optional)">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.extra-work-row').remove()">Remove</button>
            </div>
        `;
        container.appendChild(div);
        extraWorkCount++;
    }

    // Tab selection persistence
    const tabInput = document.getElementById('active_tab_input');
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => {
            const tabId = e.target.id.replace('-tab', '');
            tabInput.value = tabId;
        });
    });

    // Check URL for tab
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
        const tabEl = document.querySelector(`#${activeTab}-tab`);
        if (tabEl) {
            const bsTab = new bootstrap.Tab(tabEl);
            bsTab.show();
            tabInput.value = activeTab;
        }
    }

    // Width Validation
    const widthInputs = document.querySelectorAll('.width-input');
    const widthBadge = document.getElementById('width-total-badge');
    const saveBtn = document.getElementById('save-btn');

    function validateWidths() {
        let total = 0;
        widthInputs.forEach(input => {
            total += parseFloat(input.value || 0);
        });
        
        // Round to 1 decimal place to avoid float issues
        total = Math.round(total * 10) / 10;
        
        widthBadge.textContent = 'Total: ' + total + '%';
        
        if (Math.abs(total - 100) < 0.1) {
            widthBadge.classList.replace('bg-danger', 'bg-success');
            saveBtn.disabled = false;
        } else {
            widthBadge.classList.replace('bg-success', 'bg-danger');
            saveBtn.disabled = true;
        }
    }

    widthInputs.forEach(input => {
        input.addEventListener('input', validateWidths);
    });

    // Initial check
    validateWidths();
</script>
</body>
</html>
