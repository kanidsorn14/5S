	<?php
	include "sqlconnect.php";
	date_default_timezone_set('Asia/Bangkok');
	$month = date('F Y');

	// Load Config
	$config_file = 'dashboard_config.json';
	$config = [];
	if (file_exists($config_file)) {
		$config = json_decode(file_get_contents($config_file), true) ?: [];
	}
	// Fallback defaults
	$col_no_w = ($config['column_widths']['no'] ?? 8) . '%';
	$col_area_w = ($config['column_widths']['area'] ?? 77) . '%';
	$col_score_w = ($config['column_widths']['score'] ?? 15) . '%';
	
	$fs_title = ($config['font_sizes']['title'] ?? 70) . 'px';
	$fs_month = ($config['font_sizes']['month'] ?? 40) . 'px';
	$fs_th = ($config['font_sizes']['table_head'] ?? 28) . 'px';
	$fs_tb = ($config['font_sizes']['table_body'] ?? 34) . 'px';
	$fs_cno = ($config['font_sizes']['col_no'] ?? 30) . 'px';
	$fs_carea = ($config['font_sizes']['col_area'] ?? 36) . 'px';
	$fs_cscore = ($config['font_sizes']['col_score'] ?? 30) . 'px';

	$color_title = $config['colors']['title_color'] ?? '#0052cc';
	$color_month = $config['colors']['month_color'] ?? '#d86b00';
	$color_default = $config['colors']['default_score_color'] ?? '#0b600d';
	$summary_header_bg = $config['colors']['summary_header_bg'] ?? '#0052cc';

	// Summary Score Config
	$ss_config = $config['summary_score'] ?? [];
	$ss_text = $ss_config['text'] ?? '5S Rangsit';
	$ss_fs = ($ss_config['font_size'] ?? 42) . 'px';
	$ss_vfs = ($ss_config['score_font_size'] ?? 80) . 'px';
	$ss_color = $ss_config['color'] ?? $color_title;
	$ss_bg = $ss_config['bg_color'] ?? '#f0f7ff';
	$ss_top = ($ss_config['top'] ?? 10) . 'px';
	$ss_right = ($ss_config['right'] ?? 0) . 'px';
	$ss_bc = $ss_config['border_color'] ?? $color_title;
	$ss_bw = ($ss_config['border_width'] ?? 3) . 'px';
	$ss_br = ($ss_config['border_radius'] ?? 12) . 'px';

	require 'calculate_score.php';

	function evaluateCondition($score, $cond) {
		$op = $cond['operator'] ?? '<';
		$v1 = (float)($cond['threshold'] ?? 0);
		$v2 = (float)($cond['threshold2'] ?? 0);
		
		switch ($op) {
			case '<': return $score < $v1;
			case '<=': return $score <= $v1;
			case '>': return $score > $v1;
			case '>=': return $score >= $v1;
			case 'between': return ($score >= $v1 && $score <= $v2);
			default: return false;
		}
	}

	function getScoreColors($score, $config) {
		$conditions = $config['colors']['conditions'] ?? [];
		$default_main = $config['colors']['default_score_color'] ?? '#008000';
		
		foreach ($conditions as $cond) {
			if (evaluateCondition($score, $cond)) {
				$base = $cond['color'];
				return [
					'no' => !empty($cond['use_custom_no']) ? ($cond['color_no'] ?? $base) : $base,
					'area' => !empty($cond['use_custom_area']) ? ($cond['color_area'] ?? $base) : $base,
					'score' => !empty($cond['use_custom_score']) ? ($cond['color_score'] ?? $base) : $base,
					'bg' => $cond['bg_color'] ?? 'transparent',
					'text' => $cond['text_color'] ?? $base,
					'bg_no' => !empty($cond['use_custom_no']) ? ($cond['bg_no'] ?? 'transparent') : 'transparent',
					'bg_area' => !empty($cond['use_custom_area']) ? ($cond['bg_area'] ?? 'transparent') : 'transparent',
					'bg_score' => !empty($cond['use_custom_score']) ? ($cond['bg_score'] ?? 'transparent') : 'transparent'
				];
			}
		}
		
		return [
			'no' => $default_main,
			'area' => $default_main,
			'score' => $default_main,
			'bg' => 'transparent',
			'text' => $default_main,
			'bg_no' => 'transparent',
			'bg_area' => 'transparent',
			'bg_score' => 'transparent'
		];
	}

	$scoring_mode = $config['colors']['scoring_mode'] ?? 'custom';

	// Override average score colors based on master logic
	$master_color_avg = getMasterColors($average_score);
	$ss_bg = $master_color_avg['bg'];
	$ss_bc = $master_color_avg['border'];
	$ss_color = $master_color_avg['text'];

	?>
	<!doctype html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>Real Time 5S Score</title>
		<style>
			html,body{height:100%;margin:0;font-family: Arial, Helvetica, sans-serif;background:#fff;overflow:hidden}
			/* viewport holds the absolute stage that will be scaled */
			.viewport{position:fixed;inset:0;background:#fff}
			#stage{position:absolute;width:1920px;height:1080px;transform-origin:0 0}
			.container{width:1900px; height: 1040px; margin:10px auto; padding:10px; display: flex; flex-direction: column;}
			.header{text-align: center; margin-bottom: 20px; position: relative;}
			.title{color:<?php echo $color_title; ?>; font-size:<?php echo $fs_title; ?>; font-weight:700; margin-bottom: 5px;}
			.month{color:<?php echo $color_month; ?>; font-weight:700; font-size:<?php echo $fs_month; ?>;}
			.avg-score{position:absolute; top: <?php echo $ss_top; ?>; right: <?php echo $ss_right; ?>; color: <?php echo $ss_color; ?>; background: <?php echo $ss_bg; ?>; border: <?php echo $ss_bw; ?> solid <?php echo $ss_bc; ?>; border-radius: <?php echo $ss_br; ?>; display: flex; flex-direction: column; min-width: 280px; overflow: hidden;}
			.ss-label{font-size: <?php echo $ss_fs; ?>; font-weight: 700; padding: 5px 15px; text-align: center; background: <?php echo $summary_header_bg; ?>; color: #fff; line-height: 1.2;}
			.ss-value{font-size: <?php echo $ss_vfs; ?>; font-weight: 700; padding: 5px 15px; text-align: center; border-top: <?php echo $ss_bw; ?> solid <?php echo $ss_bc; ?>;}
			
            .tables-container { display: flex; gap: 20px; flex-grow: 1; justify-content: space-between; align-items: stretch; margin-bottom: 20px; }
			.table-wrap { flex: 1; background: #fff; display: flex; flex-direction: column; }
            
			table{width:100%; height: 100%; border-collapse:collapse; table-layout: fixed; border: 2px solid #000;}
			thead th{background:darkblue; padding:10px; border: 2px solid #fff; text-align:center; font-size:<?php echo $fs_th; ?>; color: #fff;}
			thead th.center{text-align:center}
			tbody td{padding:8px 10px; font-size:<?php echo $fs_tb; ?>; color:<?php echo $color_default; ?>; font-weight:700; border: 2px solid #000;}
			tbody td.score{font-weight:700; text-align:center; color:<?php echo $color_default; ?>}
			tbody tr.empty td{height:65px}
			.neg td{color: inherit !important}
			.mid td{color:blue !important}
		
			.col-no{width:<?php echo $col_no_w; ?>; font-size:<?php echo $fs_cno; ?>; text-align: center;}
			.col-area{width:<?php echo $col_area_w; ?>; font-size:<?php echo $fs_carea; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
			.col-score{width:<?php echo $col_score_w; ?>; font-size:<?php echo $fs_cscore; ?>; text-align:center;}
			
			/* Footer Style */
			.footer {
				text-align: center;
				padding: 10px 0;
				font-size: 32px;
				font-weight: 700;
				color: #444;
				width: 100%;
				margin-top: auto;
			}
			</style>
	</head>
	<body>
		<div class="viewport">
			<div id="stage">
				<div class="container">
					<div class="header">
						<a href="manage_employees.php" target="_blank" style="text-decoration: none;">
							<div class="title">Real Time 5S Score (Rangsit)</div>
						</a>
						<div class="month">Month : <?php echo htmlspecialchars($reportymonth, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($reportyear, ENT_QUOTES, 'UTF-8'); ?></div>
						<div class="avg-score">
							<div class="ss-label"><?php echo htmlspecialchars($ss_text); ?></div>
							<div class="ss-value"><?php echo number_format($average_score, 0); ?>%</div>
						</div>
					</div>

					<div class="tables-container">
                        <?php for($table=0; $table<3; $table++): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th class="col-no">No.</th>
                                        <th class="col-area">Area</th>
                                        <th class="col-score">Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php for($i = $table*9; $i < ($table+1)*9; $i++): 
                                    if(!isset($area[$i])) {
                                        // Fill empty rows to keep tables aligned
                                        echo '<tr class="empty"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                                        continue;
                                    } ?>
                                    <?php 
                                    $score_val = $score[$i];
                                    if($score_val > 100) $score_val = 100;

                                    if ($scoring_mode === 'standard') {
                                        $master = getMasterColors($score_val);
                                        $row_style = "";
                                        $score_style = "background: {$master['bg']}; color: #000 !important; border-radius: 8px;";
                                        $row_colors = [
                                            'no' => $color_default,
                                            'area' => $color_default,
                                            'score' => '#000',
                                            'bg_no' => 'transparent',
                                            'bg_area' => 'transparent'
                                        ];
                                    } else {
                                        $row_colors = getScoreColors($score_val, $config);
                                        $row_bg = $row_colors['bg'] ?? 'transparent';
                                        $row_style = ($row_bg !== 'transparent') ? "background: {$row_bg};" : "";
                                        
                                        $bg_no = $row_colors['bg_no'] ?? 'transparent';
                                        $bg_area = $row_colors['bg_area'] ?? 'transparent';
                                        $bg_score = $row_colors['bg_score'] ?? 'transparent';

                                        $no_style = ($bg_no !== 'transparent') ? "background: {$bg_no};" : "";
                                        $area_style = ($bg_area !== 'transparent') ? "background: {$bg_area};" : "";
                                        $score_style = ($bg_score !== 'transparent') ? "background: {$bg_score};" : "";
                                    }
                                    ?>
                                    <tr class="empty" style="<?php echo $row_style; ?>">
                                        <td class="col-no" style="<?php echo $no_style ?? ""; ?> color: <?php echo $row_colors['no']; ?> !important;"><?php echo $i+1; ?></td>
                                        <td class="col-area" title="<?php echo htmlspecialchars($area[$i]); ?>" style="<?php echo $area_style ?? ""; ?> color: <?php echo $row_colors['area']; ?> !important;"><?php echo htmlspecialchars($area[$i]); ?></td>
                                        <td class="col-score" style="<?php echo $score_style; ?> color: <?php echo $row_colors['score']; ?> !important;"><?php echo number_format($score_val, 0); ?>%</td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endfor; ?>
					</div>
					<div class="footer">
						คะแนนมาจาก 4ส คือ 1.สะสาง 2.สะดวก 3.สะอาด 4.สร้างวินัย
					</div>
				</div>
			</div>
		</div>

		<script>
			(function(){
				const designW = 1920;
				const designH = 1080;
				const stage = document.getElementById('stage');

				function scaleStage(){
					const sw = window.innerWidth / designW;
					const sh = window.innerHeight / designH;
					const s = Math.min(sw, sh);
					stage.style.transform = 'scale(' + s + ')';
					stage.style.left = ((window.innerWidth - designW * s) / 2) + 'px';
					stage.style.top = ((window.innerHeight - designH * s) / 2) + 'px';
				}

				function scheduleRefresh() {
					<?php
					$now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
					$target = new DateTime('07:30:00', new DateTimeZone('Asia/Bangkok'));
					if ($now > $target) {
						$target->modify('+1 day');
					}
					$delay_ms = ($target->getTimestamp() - $now->getTimestamp()) * 1000;
					?>
					const delay = <?php echo $delay_ms; ?>;
					console.log('Next refresh in ' + Math.round(delay / 60000) + ' minutes (Bangkok Time)');
					setTimeout(() => window.location.reload(), delay);
				}
				scheduleRefresh();

                // Live Update when employee counts change
                let lastModifiedData = null;
                let lastModifiedConfig = null;
                async function checkUpdate() {
                    try {
                        const [respData, respConfig] = await Promise.all([
                            fetch('employee_counts.json', { method: 'HEAD', cache: 'no-store' }),
                            fetch('dashboard_config.json', { method: 'HEAD', cache: 'no-store' })
                        ]);
                        const modData = respData.headers.get('Last-Modified');
                        const modConfig = respConfig.headers.get('Last-Modified');
                        
                        if ((lastModifiedData && modData !== lastModifiedData) || 
                            (lastModifiedConfig && modConfig !== lastModifiedConfig)) {
                            window.location.reload();
                        }
                        lastModifiedData = modData;
                        lastModifiedConfig = modConfig;
                    } catch (e) {}
                }
                setInterval(checkUpdate, 5000);

				window.addEventListener('resize', scaleStage);
				window.addEventListener('load', scaleStage);
				setTimeout(scaleStage, 50);
			})();
		</script>
	</body>
	</html>
