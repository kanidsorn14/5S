<?php
header("location:http://i-smartweb-rs.ircthailand.co.th:88/5s/summary_overall");
session_start();
include "../sqlconnect.php";
date_default_timezone_set('Asia/Bangkok');

// Load logic to compute score. It requires $conn to be available.
require '../calculate_score.php';
// $average_score is populated by calculate_score.php
$master_color = getMasterColors($average_score);

// Load dashboard config for header bg color
$config_file = __DIR__ . '/../dashboard_config.json';
$dashboard_config = [];
if (file_exists($config_file)) {
    $dashboard_config = json_decode(file_get_contents($config_file), true) ?: [];
}
$summary_header_bg = $dashboard_config['colors']['summary_header_bg'] ?? '#0052cc';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>5S Score - Overall Summary</title>
    <link href="../assets/fonts/sarabun.css" rel="stylesheet">
    <style>
        html,body {height:100%; margin:0; font-family: 'Sarabun', sans-serif; background:#fff; overflow:hidden;}
        .viewport {position:fixed; inset:0; background:#fff;}
        #stage {position:absolute; width:1920px; height:1080px; transform-origin:0 0; display: flex; align-items: center; justify-content: center; padding: 40px; box-sizing: border-box;}
        
        .main-container {
            width: 100%;
            height: 100%;
            display: flex;
            gap: 60px;
        }

        /* Left Side: Criteria */
        .left-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .header-title {
            font-size: 85px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            margin-bottom: 50px;
            color: #111;
        }

        .criteria-container {
            display: flex;
            align-items: center;
            gap: 40px;
            height: 700px;
        }

        /* Thermometer Graphic */
        .thermometer-wrapper {
            position: relative;
            width: 140px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .thermometer {
            width: 80px;
            height: calc(100% - 70px);
            background: linear-gradient(to right, #cfd8dc 0%, #ffffff 50%, #b0bec5 100%);
            border-radius: 40px;
            border: 4px solid #fff;
            box-shadow: inset 0px 0px 10px rgba(0,0,0,0.2), 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
            margin-bottom: 70px; /* space for bulb */
        }

        .thermometer-liquid {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 34px;
            height: calc(100% - 20px);
            background: linear-gradient(to top, #cf1615 10%, #dc1c1c 20%, #ef7328 40%, #ffdf2a 60%, #9bc26b 80%, #158b4b 100%);
            border-radius: 17px;
            box-shadow: inset -2px 0px 4px rgba(0,0,0,0.2);
        }

        .thermometer-bulb {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 120px;
            background: radial-gradient(circle at 35% 35%, #ff5252 0%, #b71c1c 80%);
            border-radius: 50%;
            border: 6px solid #fff;
            box-shadow: inset -4px -4px 10px rgba(0,0,0,0.5), 0 5px 15px rgba(0,0,0,0.2);
            z-index: 2;
        }

        /* Criteria Boxes */
        .criteria-boxes {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: calc(100% - 90px); /* Leave room for thermometer bulb alignment */
            padding-bottom: 45px; 
            padding-top: 5px;
        }

        .c-box {
            border: 2px solid #a8b0b5;
            border-radius: 15px; 
            padding: 18px 25px;
            width: 530px;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.1);
            font-size: 28px; 
            font-weight: 700;
            line-height: 1.3;
            color: #000; 
        }

        .c-box-1 { background: #6bbb5e; border-color: #559149; } /* 80-100 */
        .c-box-2 { background: #a2cf6e; border-color: #82a856; } /* 70-79 */
        .c-box-3 { background: #ffe467; border-color: #cbb44d; } /* 60-69 */
        .c-box-4 { background: #f6ab63; border-color: #c4874c; } /* 50-59 */
        .c-box-5 { background: #ec6566; border-color: #bb4f4f; } /* <50 */

        /* Right Side: Score Card */
        .right-col {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .result-card {
            width: 90%;
            height: 900px;
            border-radius: 40px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .result-header {
            background: <?php echo $summary_header_bg; ?>;
            color: #fff;
            text-align: center;
            padding: 50px 20px;
            font-size: 75px;
            font-weight: 700;
            line-height: 1.2;
        }

        .result-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: <?php echo $master_color['bg']; ?>;
            border-left: 8px solid <?php echo $master_color['border']; ?>;
            border-right: 8px solid <?php echo $master_color['border']; ?>;
            border-bottom: 8px solid <?php echo $master_color['border']; ?>;
            border-radius: 0 0 40px 40px;
        }

        .score-value {
            font-size: 380px;
            font-weight: 700;
            color: <?php echo $master_color['text']; ?>;
            letter-spacing: -10px;
        }

        .footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            color: #444;
        }

    </style>
</head>
<body>
    <div class="viewport">
        <div id="stage">
            <div class="main-container">
                <!-- Left Column -->
                <div class="left-col">
                    <div class="header-title">เกณฑ์การทำ 5 ส.<br>(รังสิต)</div>
                    <div class="criteria-container">
                        <div class="thermometer-wrapper">
                            <div class="thermometer">
                                <div class="thermometer-liquid"></div>
                                <div class="thermometer-bulb"></div>
                            </div>
                        </div>
                        <div class="criteria-boxes">
                            <div class="c-box c-box-1">80%-100% (ดีมาก): เป็นไปตามมาตรฐาน<br>ทุกข้อ และมีการบำรุงรักษาเชิงรุก</div>
                            <div class="c-box c-box-2">70%-79% (ดี): เป็นไปตามมาตรฐาน<br>มีจุดที่สามารถปรับปรุงเล็กน้อย</div>
                            <div class="c-box c-box-3">60%-69%: อยู่ในเกณฑ์ยอมรับได้<br>แต่ควรได้รับการดูแล/ปรับปรุง</div>
                            <div class="c-box c-box-4">50%-59% (ต้องปรับปรุง):<br>พบสภาพที่ต่ำกว่ามาตรฐาน</div>
                            <div class="c-box c-box-5">ต่ำกว่า 50% (แย่มาก):<br>ต้องดำเนินการแก้ไขทันที</div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col">
                    <div class="result-card">
                        <div class="result-header">ผลลัพธ์เรียลไทม์<br>การทำ 5ส. (รังสิต)</div>
                        <div class="result-body">
                            <div class="score-value"><?php echo number_format($average_score, 0); ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer">
                คะแนนมาจาก 4ส คือ 1.สะสาง 2.สะดวก 3.สะอาด 4.สร้างวินัย
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

            // Daily 07:30 AM Auto Refresh
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

            window.addEventListener('resize', scaleStage);
            window.addEventListener('load', scaleStage);
            setTimeout(scaleStage, 50);
        })();
    </script>
</body>
</html>
