<?php
// Load schedule config
$config_file = __DIR__ . '/dashboard_config.json';
$config = [];
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true) ?: [];
}

$schedule = $config['schedule'] ?? [
    'work_days' => [2, 3, 4, 5, 6], // Default Mon-Fri
    'holidays' => [],
    'extra_work_days' => []
];

// Map work_days to SQL-safe string
$work_days_sql = !empty($schedule['work_days']) ? implode(',', array_map('intval', $schedule['work_days'])) : '2,3,4,5,6';

// Map holidays and extra days to SQL-safe strings (Sanitized)
$holiday_dates = array_map(function($h) { return "'" . substr(preg_replace('/[^0-9-]/', '', $h['date']), 0, 10) . "'"; }, $schedule['holidays'] ?? []);
$extra_work_dates = array_map(function($e) { return "'" . substr(preg_replace('/[^0-9-]/', '', $e['date']), 0, 10) . "'"; }, $schedule['extra_work_days'] ?? []);

$holidays_sql = !empty($holiday_dates) ? implode(',', $holiday_dates) : "'1900-01-01'";
$extra_work_sql = !empty($extra_work_dates) ? implode(',', $extra_work_dates) : "'1900-01-01'";

$query = "DECLARE @Today DATE = CAST(GETDATE() AS DATE);

DECLARE @StartDate DATE;

DECLARE @EndDate DATE = DATEADD(day, -1, @Today);



-- ปรับ Logic: ถ้าวันที่ 1 ให้โชว์ข้อมูลเดือนที่แล้วทั้งเดือน

IF (DAY(@Today) = 1)

BEGIN

SET @StartDate = DATEFROMPARTS(YEAR(@EndDate), MONTH(@EndDate), 1);

END

ELSE

BEGIN

SET @StartDate = DATEFROMPARTS(YEAR(@Today), MONTH(@Today), 1);

END



;WITH MonthDates AS (

SELECT @StartDate AS DateValue

WHERE @StartDate <= @EndDate

UNION ALL

SELECT DATEADD(day, 1, DateValue)

FROM MonthDates

WHERE DateValue < @EndDate

),

WorkingDays AS (

SELECT COUNT(*) AS WorkDayCount

FROM MonthDates

WHERE 
    (
        ((DATEPART(dw, DateValue) + @@DATEFIRST) % 7) IN ($work_days_sql) 
        AND DateValue NOT IN ($holidays_sql)
    )
    OR (DateValue IN ($extra_work_sql))

),

QuestionCounts AS (

SELECT 

(SELECT COUNT(*) FROM RSMSSQL.DAILY_FACTORY_5S.dbo.MASTER_QUESTION MQ WITH (NOLOCK)
 INNER JOIN RSMSSQL.DAILY_FACTORY_5S.dbo.MASER_TOPIC MT WITH (NOLOCK) ON MQ.TOPIC_ID = MT.KEYID
 WHERE MT.EFFECTIVEYEAR * 100 + MT.EFFECTIVEMONTH = (
     SELECT MAX(EFFECTIVEYEAR * 100 + EFFECTIVEMONTH) FROM RSMSSQL.DAILY_FACTORY_5S.dbo.MASER_TOPIC
     WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH <= YEAR(GETDATE()) * 100 + MONTH(GETDATE())
 )
 AND MT.REVISION = (
     SELECT MAX(REVISION) FROM RSMSSQL.DAILY_FACTORY_5S.dbo.MASER_TOPIC
     WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH = (
         SELECT MAX(EFFECTIVEYEAR * 100 + EFFECTIVEMONTH) FROM RSMSSQL.DAILY_FACTORY_5S.dbo.MASER_TOPIC
         WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH <= YEAR(GETDATE()) * 100 + MONTH(GETDATE())
     )
 )
) AS FactoryQC,

(SELECT COUNT(*) FROM RSMSSQL.DAILY_OFFICE_5S.dbo.MASTER_QUESTION MQ WITH (NOLOCK)
 INNER JOIN RSMSSQL.DAILY_OFFICE_5S.dbo.MASER_TOPIC MT WITH (NOLOCK) ON MQ.TOPIC_ID = MT.KEYID
 WHERE MT.EFFECTIVEYEAR * 100 + MT.EFFECTIVEMONTH = (
     SELECT MAX(EFFECTIVEYEAR * 100 + EFFECTIVEMONTH) FROM RSMSSQL.DAILY_OFFICE_5S.dbo.MASER_TOPIC
     WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH <= YEAR(GETDATE()) * 100 + MONTH(GETDATE())
 )
 AND MT.REVISION = (
     SELECT MAX(REVISION) FROM RSMSSQL.DAILY_OFFICE_5S.dbo.MASER_TOPIC
     WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH = (
         SELECT MAX(EFFECTIVEYEAR * 100 + EFFECTIVEMONTH) FROM RSMSSQL.DAILY_OFFICE_5S.dbo.MASER_TOPIC
         WHERE EFFECTIVEYEAR * 100 + EFFECTIVEMONTH <= YEAR(GETDATE()) * 100 + MONTH(GETDATE())
     )
 )
) AS OfficeQC

),

AreaList AS (

SELECT AREA FROM RSMSSQL.DAILY_FACTORY_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK) WHERE AREA IS NOT NULL GROUP BY AREA

UNION 

SELECT AREA FROM RSMSSQL.DAILY_OFFICE_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK) WHERE AREA IS NOT NULL GROUP BY AREA

),

CombinedData AS (

SELECT AREA, OWNER, ANSWER, updatedate, 'Factory' AS SourceType

FROM RSMSSQL.DAILY_FACTORY_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK)

WHERE updatedate >= @StartDate AND updatedate < DATEADD(day, 1, @EndDate)

AND (
    (
        ((DATEPART(dw, updatedate) + @@DATEFIRST) % 7) IN ($work_days_sql) 
        AND CAST(updatedate AS DATE) NOT IN ($holidays_sql)
    )
    OR (CAST(updatedate AS DATE) IN ($extra_work_sql))
) and OWNER is not NULL

UNION ALL

SELECT AREA, OWNER, ANSWER, updatedate, 'Office' AS SourceType

FROM RSMSSQL.DAILY_OFFICE_5S.dbo.DALIY_SAFECHECK WITH (NOLOCK)

WHERE updatedate >= @StartDate AND updatedate < DATEADD(day, 1, @EndDate) and OWNER is not NULL

AND (
    (
        ((DATEPART(dw, updatedate) + @@DATEFIRST) % 7) IN ($work_days_sql) 
        AND CAST(updatedate AS DATE) NOT IN ($holidays_sql)
    )
    OR (CAST(updatedate AS DATE) IN ($extra_work_sql))
)

),

DailyAreaScores AS (

SELECT 

C.AREA,

C.SourceType,

CAST(C.updatedate AS DATE) AS check_date,

SUM(TRY_CAST(C.ANSWER AS DECIMAL(18, 4))) AS daily_sum_answer,

COUNT(DISTINCT C.OWNER) AS daily_count_owner,

CASE 

WHEN (SUM(TRY_CAST(C.ANSWER AS DECIMAL(18, 4))) / NULLIF(COUNT(DISTINCT C.OWNER) * MAX(CASE WHEN C.SourceType = 'Factory' THEN Q.FactoryQC ELSE Q.OfficeQC END) * 5.0, 0)) > 1.0 

THEN 1.0 

ELSE (SUM(TRY_CAST(C.ANSWER AS DECIMAL(18, 4))) / NULLIF(COUNT(DISTINCT C.OWNER) * MAX(CASE WHEN C.SourceType = 'Factory' THEN Q.FactoryQC ELSE Q.OfficeQC END) * 5.0, 0))

END AS score

FROM CombinedData C

CROSS JOIN QuestionCounts Q

GROUP BY C.AREA, C.SourceType, CAST(C.updatedate AS DATE)

)

SELECT 

A.AREA,

DATENAME(month, @StartDate) AS ReportMonth,   -- เพิ่มคอลัมน์ เดือน

YEAR(@StartDate) AS ReportYear,     -- เพิ่มคอลัมน์ ปี

MAX(W.WorkDayCount) AS total_working_days,


-- Factory Details

MAX(Q.FactoryQC) AS factory_question_count,

ISNULL(SUM(CASE WHEN D.SourceType = 'Factory' THEN D.daily_count_owner END), 0) AS factory_total_evaluator,

ISNULL(SUM(CASE WHEN D.SourceType = 'Factory' THEN D.daily_sum_answer END), 0) AS factory_total_score,

(ISNULL(SUM(CASE WHEN D.SourceType = 'Factory' THEN D.score END), 0) / NULLIF(MAX(W.WorkDayCount), 0)) * 100 AS factory_avg_pct,


-- Office Details

MAX(Q.OfficeQC) AS office_question_count,

ISNULL(SUM(CASE WHEN D.SourceType = 'Office' THEN D.daily_count_owner END), 0) AS office_total_evaluator,

ISNULL(SUM(CASE WHEN D.SourceType = 'Office' THEN D.daily_sum_answer END), 0) AS office_total_score,

(ISNULL(SUM(CASE WHEN D.SourceType = 'Office' THEN D.score END), 0) / NULLIF(MAX(W.WorkDayCount), 0)) * 100 AS office_avg_pct



FROM AreaList A

CROSS JOIN WorkingDays W

CROSS JOIN QuestionCounts Q

LEFT JOIN DailyAreaScores D ON A.AREA = D.AREA

GROUP BY A.AREA

ORDER BY A.AREA

OPTION (MAXRECURSION 31);
";
$getRes = $conn->prepare($query);
$getRes->execute();
// Load employee counts from JSON
$json_file = __DIR__ . '/employee_counts.json';
$employee_data = [];
if (file_exists($json_file)) {
	$employee_data = json_decode(file_get_contents($json_file), true) ?: [];
}

$area = [];
$score = [];
$factory_total_evaluator = [];
$office_total_evaluator = [];
$ReportMonth = [];	
$ReportYear = [];

$all_results = [];
while($row = $getRes->fetch( PDO::FETCH_ASSOC ))
{
	$area_name = $row['AREA'];
	$working_days = $row['total_working_days'] ?? 0;
	
	$f_emp = $employee_data[$area_name]['factory'] ?? 0;
	$o_emp = $employee_data[$area_name]['office'] ?? 0;
	
	$f_qc = $row['factory_question_count'] ?? 0;
	$o_qc = $row['office_question_count'] ?? 0;
	
	$f_total = $row['factory_total_score'] ?? 0;
	$o_total = $row['office_total_score'] ?? 0;

	// Formula: score = ผลรวมคะแนน / {จำนวนพนักงาน * question_count * 5 * working_days}
	$f_possible = $f_emp * $f_qc * 5 * $working_days;
	$o_possible = $o_emp * $o_qc * 5 * $working_days;
	
	$f_score = ($f_possible > 0) ? ($f_total / $f_possible) * 100 : 0;
	$o_score = ($o_possible > 0) ? ($o_total / $o_possible) * 100 : 0;

	$reportymonth = $row['ReportMonth'] ?? 0;
	$reportyear = $row['ReportYear'] ?? 0;

	// Total score = ค่าเฉลี่ยของ factory score และ office score (เฉพาะที่มีพนักงาน)
	$scores_to_average = [];
	if ($f_emp > 0) $scores_to_average[] = $f_score;
	if ($o_emp > 0) $scores_to_average[] = $o_score;
	
	$final_score = (count($scores_to_average) > 0) ? (array_sum($scores_to_average) / count($scores_to_average)) : 0;

	$all_results[] = [
		'area' => $area_name,
		'score' => $final_score,
		'debug' => [
			'f_total' => $f_total,
			'f_possible' => $f_possible,
			'f_emp' => $f_emp,
			'f_qc' => $f_qc,
			'f_act_eval' => $row['factory_total_evaluator'] ?? 0,
			'o_total' => $o_total,
			'o_possible' => $o_possible,
			'o_emp' => $o_emp,
			'o_qc' => $o_qc,
			'o_act_eval' => $row['office_total_evaluator'] ?? 0,
			'working_days' => $working_days
		]
	];
}

// Sort by Total Score DESC
usort($all_results, function($a, $b) {
	return $b['score'] <=> $a['score'];
});

// Re-populate arrays for HTML template
$area = [];
$score = [];
$debug_data = [];
foreach ($all_results as $res) {
	$area[] = $res['area'];
	$score[] = $res['score'];
	$debug_data[] = $res['debug'];
}

$average_score = (count($score) > 0) ? array_sum($score) / count($score) : 0;

if (!function_exists('getMasterColors')) {
	function getMasterColors($scoreValue) {
		if ($scoreValue >= 80) return ['bg' => '#6bbb5e', 'border' => '#559149', 'header_bg' => '#0052cc', 'header_border' => '#0052cc', 'text' => '#000000'];
		if ($scoreValue >= 70) return ['bg' => '#a2cf6e', 'border' => '#82a856', 'header_bg' => '#0052cc', 'header_border' => '#0052cc', 'text' => '#000000'];
		if ($scoreValue >= 60) return ['bg' => '#ffe467', 'border' => '#cbb44d', 'header_bg' => '#0052cc', 'header_border' => '#0052cc', 'text' => '#000000'];
		if ($scoreValue >= 50) return ['bg' => '#f6ab63', 'border' => '#c4874c', 'header_bg' => '#0052cc', 'header_border' => '#0052cc', 'text' => '#000000'];
		return ['bg' => '#ec6566', 'border' => '#bb4f4f', 'header_bg' => '#0052cc', 'header_border' => '#0052cc', 'text' => '#000000'];
	}
}
?>
