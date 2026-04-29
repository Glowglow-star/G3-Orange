<?php
// ── DB Connection ─────────────────────────────────────────────────────────
$host   = "webdev.iyaserver.com";
$userid = "xiuyuanq_orange";
$userpw = "orange37465";
$db     = "xiuyuanq_orangeDB";

$mysql = new mysqli($host, $userid, $userpw, $db);

if ($mysql->connect_errno) {
    echo json_encode(["error" => "DB connection error: " . $mysql->connect_error]);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$task = isset($_REQUEST["task"]) ? $_REQUEST["task"] : "all";

// ── Helper: run query and return rows as array ────────────────────────────
function runQuery($mysql, $sql) {
    $results = $mysql->query($sql);
    if (!$results) {
        return ["error" => $mysql->error, "query" => $sql];
    }
    $rows = [];
    while ($row = $results->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// ── TASK 1: Stops by District ─────────────────────────────────────────────
// date field is days since 1970-01-01 (R epoch); 2007 data has district codes
$sql_t1 = "
    SELECT 
        district AS district_code,
        COUNT(*) AS stop_count
    FROM ca_san_francisco_50k
    WHERE district != '' AND district IS NOT NULL
    GROUP BY district
    ORDER BY stop_count DESC
";

// ── TASK 2a: Stops by Hour of Day ─────────────────────────────────────────
// time field is still seconds since midnight; FLOOR(time/3600) = hour 0-23
// date column changed to real DATE but time column is unchanged
$sql_t2a = "
    SELECT 
        FLOOR(time / 3600) AS hour,
        COUNT(*) AS stop_count
    FROM ca_san_francisco_50k
    WHERE time IS NOT NULL AND time != ''
    GROUP BY hour
    ORDER BY hour ASC
";

// ── TASK 2b: Stops by Day of Week ─────────────────────────────────────────
// date is stored as M/D/YYYY string (e.g. '1/8/2012'), need STR_TO_DATE to parse
$sql_t2b = "
    SELECT 
        DAYNAME(STR_TO_DATE(date, '%m/%d/%Y')) AS weekday,
        DAYOFWEEK(STR_TO_DATE(date, '%m/%d/%Y')) AS weekday_num,
        COUNT(*) AS stop_count
    FROM ca_san_francisco_50k
    WHERE date IS NOT NULL AND date != ''
    GROUP BY weekday_num, weekday
    ORDER BY weekday_num ASC
";

// ── TASK 2c: Stops by Month ───────────────────────────────────────────────
// date is stored as M/D/YYYY string, need STR_TO_DATE to parse
$sql_t2c = "
    SELECT 
        MONTHNAME(STR_TO_DATE(date, '%m/%d/%Y')) AS month,
        MONTH(STR_TO_DATE(date, '%m/%d/%Y')) AS month_num,
        COUNT(*) AS stop_count
    FROM ca_san_francisco_50k
    WHERE date IS NOT NULL AND date != ''
    GROUP BY month_num, month
    ORDER BY month_num ASC
";

// ── TASK 3: Reason for Stop x Outcome ────────────────────────────────────
$sql_t3 = "
    SELECT
        reason_for_stop AS reason,
        COUNT(*) AS total,
        SUM(outcome = 'warning')  AS warning,
        SUM(outcome = 'citation') AS citation,
        SUM(outcome = 'arrest')   AS arrest,
        ROUND(100 * SUM(outcome = 'warning')  / COUNT(*), 1) AS warning_pct,
        ROUND(100 * SUM(outcome = 'citation') / COUNT(*), 1) AS citation_pct,
        ROUND(100 * SUM(outcome = 'arrest')   / COUNT(*), 1) AS arrest_pct,
        SUM(search_conducted = 'True') AS searches,
        SUM(contraband_found = 'True') AS contraband_hits,
        ROUND(
            100 * SUM(contraband_found = 'True')
            / NULLIF(SUM(search_conducted = 'True'), 0)
        , 1) AS hit_rate_pct
    FROM ca_san_francisco_50k
    WHERE outcome IN ('warning', 'citation', 'arrest')
      AND reason_for_stop != '' AND reason_for_stop IS NOT NULL
    GROUP BY reason_for_stop
    ORDER BY total DESC
";

// ── TASK 4a: Race x Outcome ───────────────────────────────────────────────
$sql_t4a = "
    SELECT
        subject_race AS race,
        COUNT(*) AS total,
        ROUND(100 * SUM(outcome = 'warning')  / COUNT(*), 1) AS warning_pct,
        ROUND(100 * SUM(outcome = 'citation') / COUNT(*), 1) AS citation_pct,
        ROUND(100 * SUM(outcome = 'arrest')   / COUNT(*), 1) AS arrest_pct
    FROM ca_san_francisco_50k
    WHERE outcome IN ('warning', 'citation', 'arrest')
      AND subject_race != '' AND subject_race IS NOT NULL
    GROUP BY subject_race
    ORDER BY total DESC
";

// ── TASK 4b: Age Group x Arrest Rate ─────────────────────────────────────
$sql_t4b = "
    SELECT
        CASE
            WHEN subject_age BETWEEN 18 AND 25 THEN '18-25'
            WHEN subject_age BETWEEN 26 AND 40 THEN '26-40'
            WHEN subject_age BETWEEN 41 AND 65 THEN '41-65'
            WHEN subject_age > 65             THEN '66+'
            ELSE 'Other'
        END AS age_group,
        COUNT(*) AS total,
        SUM(outcome = 'arrest') AS arrests,
        ROUND(100 * SUM(outcome = 'arrest') / COUNT(*), 1) AS arrest_pct
    FROM ca_san_francisco_50k
    WHERE outcome IN ('warning', 'citation', 'arrest')
      AND subject_age IS NOT NULL AND subject_age != ''
    GROUP BY age_group
    ORDER BY FIELD(age_group, '18-25', '26-40', '41-65', '66+', 'Other')
";

// ── Route and respond ─────────────────────────────────────────────────────
switch ($task) {
    case '1':
        echo json_encode(["task" => "stops_by_district",  "data" => runQuery($mysql, $sql_t1)]);
        break;
    case '2a':
        echo json_encode(["task" => "stops_by_hour",      "data" => runQuery($mysql, $sql_t2a)]);
        break;
    case '2b':
        echo json_encode(["task" => "stops_by_weekday",   "data" => runQuery($mysql, $sql_t2b)]);
        break;
    case '2c':
        echo json_encode(["task" => "stops_by_month",     "data" => runQuery($mysql, $sql_t2c)]);
        break;
    case '3':
        echo json_encode(["task" => "reason_vs_outcome",  "data" => runQuery($mysql, $sql_t3)]);
        break;
    case '4a':
        echo json_encode(["task" => "race_vs_outcome",    "data" => runQuery($mysql, $sql_t4a)]);
        break;
    case '4b':
        echo json_encode(["task" => "age_vs_arrest",      "data" => runQuery($mysql, $sql_t4b)]);
        break;
    default: // 'all'
        echo json_encode([
            "stops_by_district" => runQuery($mysql, $sql_t1),
            "stops_by_hour"     => runQuery($mysql, $sql_t2a),
            "stops_by_weekday"  => runQuery($mysql, $sql_t2b),
            "stops_by_month"    => runQuery($mysql, $sql_t2c),
            "reason_vs_outcome" => runQuery($mysql, $sql_t3),
            "race_vs_outcome"   => runQuery($mysql, $sql_t4a),
            "age_vs_arrest"     => runQuery($mysql, $sql_t4b),
        ]);
        break;
}

$mysql->close();
?>
