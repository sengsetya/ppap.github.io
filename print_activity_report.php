<?php
/**
 * Official Printable Report for Activities (PPAP System)
 * Formatted exactly according to the Royal Government of Cambodia & MPWT standards
 * (Matching user provided sample image)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tab_views.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$pdo = get_db_connection();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';

// Fetch current user
$stmt = $pdo->prepare('SELECT id, full_name, username, role, branch FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Determine dashboard return file
$dashFile = 'Dashboard_admin.php';
if ($role === 'super_admin') {
    $dashFile = 'Dashboard_Super_Admin.php';
} elseif ($role === 'branch_manager') {
    $dashFile = 'Dashboard_Branch_Manager.php';
} elseif ($role === 'user') {
    $dashFile = 'Dashboard_Normal_User.php';
}

// Filters from request
$year = (int)($_GET['year'] ?? 2026);
$entityFilter = trim($_GET['entity'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$qFilter = trim($_GET['quarter'] ?? '');

// Build Query based on Role and Filters
$where = [];
$params = [];

// Year
if ($year > 0) {
    $where[] = 'a.year = :year';
    $params['year'] = $year;
}

// Role Scope
if ($role === 'super_admin' || $role === 'admin') {
    if (!empty($entityFilter)) {
        $where[] = 'a.entity = :entity';
        $params['entity'] = $entityFilter;
    }
    if (!empty($deptFilter)) {
        $where[] = 'a.department = :dept';
        $params['dept'] = $deptFilter;
    }
} elseif ($role === 'branch_manager') {
    $bmBranch = trim($currentUser['branch'] ?? '');
    if (!empty($bmBranch)) {
        $where[] = '(a.branch = :bm_branch1 OR u.branch = :bm_branch2 OR a.user_id = :bm_uid)';
        $params['bm_branch1'] = $bmBranch;
        $params['bm_branch2'] = $bmBranch;
        $params['bm_uid'] = $userId;
    } else {
        $where[] = 'a.user_id = :bm_uid';
        $params['bm_uid'] = $userId;
    }
    if (!empty($deptFilter)) {
        $where[] = 'a.department = :dept';
        $params['dept'] = $deptFilter;
    }
} else {
    // Normal User: own activities
    $where[] = 'a.user_id = :uid';
    $params['uid'] = $userId;
}

// Status filter
if ($statusFilter === 'approved') {
    $where[] = 'a.is_approved = 1';
} elseif ($statusFilter === 'pending') {
    $where[] = '(a.is_approved = 0 OR a.is_approved IS NULL)';
}

// Quarter filter
if (!empty($qFilter) && $qFilter !== 'all') {
    $where[] = '(a.start_quarter = :q OR a.end_quarter = :q)';
    $params['q'] = $qFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT a.*, u.full_name as author_name, u.username as author_username, u.branch as user_branch 
        FROM activities a 
        LEFT JOIN users u ON a.user_id = u.id 
        $whereClause 
        ORDER BY a.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch evaluations for these activities
$actIds = array_filter(array_column($activities, 'id'));
$evalsByAct = [];
if (!empty($actIds)) {
    $inSql = implode(',', array_map('intval', $actIds));
    try {
        $evSt = $pdo->query("SELECT * FROM activity_evaluations WHERE activity_id IN ($inSql) ORDER BY id ASC");
        foreach ($evSt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            $qNum = function_exists('parse_quarter_num') ? parse_quarter_num($ev['quarter']) : 1;
            $evalsByAct[$ev['activity_id']][$qNum] = $ev;
            $evalsByAct[$ev['activity_id']]['list'][] = $ev;
        }
    } catch (Exception $e) {}
}

// Calculate Unit Name for Title
$unitName = 'កំពង់ផែស្វយ័តភ្នំពេញ';
if (!empty($deptFilter)) {
    $unitName = $deptFilter;
} elseif (!empty($entityFilter)) {
    $unitName = $entityFilter;
} elseif (!empty($currentUser['branch'])) {
    $unitName = $currentUser['branch'];
} elseif (!empty($activities[0]['department'])) {
    $unitName = $activities[0]['department'];
} elseif (!empty($activities[0]['entity'])) {
    $unitName = $activities[0]['entity'];
}

// Khmer digits conversion
$kmDigits = ['0'=>'០', '1'=>'១', '2'=>'២', '3'=>'៣', '4'=>'៤', '5'=>'៥', '6'=>'៦', '7'=>'៧', '8'=>'៨', '9'=>'៩'];
$kmYear = strtr((string)$year, $kmDigits);

// Current Khmer Date
$curDay = strtr(date('d'), $kmDigits);
$curYearNum = strtr(date('Y'), $kmDigits);
$kmMonths = [
    '01' => 'មករា', '02' => 'កុម្ភៈ', '03' => 'មីនា', '04' => 'មេសា',
    '05' => 'ឧសភា', '06' => 'មិថុនា', '07' => 'កក្កដា', '08' => 'សីហា',
    '09' => 'កញ្ញា', '10' => 'តុលា', '11' => 'វិច្ឆិកា', '12' => 'ធ្នូ'
];
$curMonth = $kmMonths[date('m')] ?? date('m');

$kmDaysOfWeek = [
    'Sunday'    => 'ថ្ងៃអាទិត្យ',
    'Monday'    => 'ថ្ងៃចន្ទ',
    'Tuesday'   => 'ថ្ងៃអង្គារ',
    'Wednesday' => 'ថ្ងៃពុធ',
    'Thursday'  => 'ថ្ងៃព្រហស្បតិ៍',
    'Friday'    => 'ថ្ងៃសុក្រ',
    'Saturday'  => 'ថ្ងៃសៅរ៍'
];
$dayNameKh = $kmDaysOfWeek[date('l')] ?? 'ថ្ងៃ';

// Entities and Departments for Filter Dropdown
$allEntities = function_exists('get_all_entities') ? get_all_entities($pdo) : [];
$allDepts = function_exists('get_all_departments') ? get_all_departments($pdo) : [];

// Link to Excel Export with identical parameters
$excelExportUrl = 'export_activities_excel.php?' . http_build_query([
    'year'       => $year,
    'entity'     => $entityFilter,
    'department' => $deptFilter,
    'status'     => $statusFilter,
    'quarter'    => $qFilter
]);
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍សកម្មភាពអាទិភាព - <?= htmlspecialchars($unitName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Kantumruy Pro', 'Khmer OS', 'Khmer OS Battambang', sans-serif;
            background-color: #525659;
            color: #000000;
            line-height: 1.45;
            padding: 20px;
        }

        /* Top Action Toolbar (Hidden on Print) */
        .print-toolbar {
            max-width: 1180px;
            margin: 0 auto 16px auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.18);
        }
        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-tool {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            border: none;
        }
        .btn-tool-print {
            background: #0284c7;
            color: #ffffff;
        }
        .btn-tool-print:hover {
            background: #0369a1;
        }
        .btn-tool-excel {
            background: #10b981;
            color: #ffffff;
        }
        .btn-tool-excel:hover {
            background: #059669;
        }
        .btn-tool-back {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-tool-back:hover {
            background: #e2e8f0;
        }
        .select-filter {
            padding: 7px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #ffffff;
        }

        /* Paper Document Container (A4 Landscape) */
        .paper-sheet {
            max-width: 1180px;
            min-height: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 48px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            border-radius: 4px;
        }

        /* Official Document Header */
        .doc-header-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: start;
            margin-bottom: 24px;
        }
        .doc-header-left {
            text-align: left;
        }
        .doc-header-left img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            display: block;
            margin-bottom: 6px;
        }
        .doc-ministry-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: #000000;
            letter-spacing: 0.02em;
        }
        .doc-dots-line {
            letter-spacing: 2.5px;
            font-size: 0.80rem;
            color: #333333;
            margin: 2px 0;
        }
        .doc-letter-num {
            font-size: 0.90rem;
            font-weight: 600;
            color: #000000;
        }

        .doc-header-right {
            text-align: center;
        }
        .doc-kingdom-title {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #000000;
            margin-bottom: 4px;
        }
        .doc-kingdom-motto {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #000000;
        }
        .doc-kingdom-symbol {
            font-size: 0.95rem;
            letter-spacing: 4px;
            margin-top: 2px;
            color: #000000;
        }

        /* Report Main Title */
        .doc-title-block {
            text-align: center;
            margin: 18px 0 24px 0;
        }
        .doc-main-title {
            font-size: 1.12rem;
            font-weight: 700;
            color: #000000;
            line-height: 1.6;
        }
        .doc-sub-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #000000;
            margin-top: 4px;
        }

        /* Official Activities Table */
        .official-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            color: #000000;
            margin-bottom: 30px;
        }
        .official-table th,
        .official-table td {
            border: 1px solid #71717a;
            padding: 8px 10px;
            vertical-align: top;
            line-height: 1.45;
        }
        .official-table thead th {
            background-color: #d2bba0 !important; /* Exact Khaki color from user's image */
            color: #000000;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            font-size: 0.85rem;
            padding: 10px 8px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .col-code { width: 105px; text-align: center; }
        .col-no { width: 42px; text-align: center; }
        .col-cluster { width: 120px; text-align: center; }
        .col-desc { min-width: 260px; text-align: left; }
        .col-target { width: 110px; text-align: center; }
        .col-start { width: 95px; text-align: center; }
        .col-end { width: 95px; text-align: center; }
        .col-score { width: 68px; text-align: center; font-weight: 700; }
        .col-actual { width: 95px; text-align: center; }
        .col-challenges { width: 95px; text-align: center; }
        .col-doc { width: 160px; text-align: left; }

        /* Signatures Section */
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 24px;
            page-break-inside: avoid;
        }
        .sig-col-left {
            text-align: center;
            padding-right: 20px;
        }
        .sig-col-right {
            text-align: center;
            padding-left: 20px;
        }
        .sig-agree-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #000000;
            margin-bottom: 4px;
        }
        .sig-date-lunar {
            font-size: 0.88rem;
            color: #000000;
            line-height: 1.5;
        }
        .sig-date-solar {
            font-size: 0.88rem;
            color: #000000;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        .sig-role-title {
            font-weight: 700;
            font-size: 0.96rem;
            color: #000000;
            margin-top: 6px;
        }
        .sig-space {
            height: 90px;
        }
        .sig-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #000000;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .paper-sheet {
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }
            @page {
                size: A4 landscape;
                margin: 12mm 10mm 12mm 10mm;
            }
            .official-table thead th {
                background-color: #d2bba0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .official-table {
                page-break-after: auto;
            }
            .official-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .official-table thead {
                display: table-header-group;
            }
            .signatures-grid {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Toolbar (Hidden during Print) -->
    <div class="print-toolbar">
        <div class="toolbar-left">
            <a href="<?= $dashFile ?>?tab=reports" class="btn-tool btn-tool-back" title="ត្រឡប់ទៅផ្ទាំងរបាយការណ៍">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>ត្រឡប់ក្រោយ</span>
            </a>

            <!-- Year Filter -->
            <form action="print_activity_report.php" method="GET" style="display: flex; gap: 8px; align-items: center;">
                <select name="year" class="select-filter" onchange="this.form.submit()">
                    <option value="2026" <?= $year === 2026 ? 'selected' : '' ?>>ឆ្នាំ ២០២៦</option>
                    <option value="2025" <?= $year === 2025 ? 'selected' : '' ?>>ឆ្នាំ ២០២៥</option>
                    <option value="2027" <?= $year === 2027 ? 'selected' : '' ?>>ឆ្នាំ ២០២៧</option>
                </select>

                <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                    <select name="entity" class="select-filter" onchange="this.form.submit()">
                        <option value="">-- អង្គភាពទាំងអស់ --</option>
                        <?php foreach ($allEntities as $ent): ?>
                            <option value="<?= htmlspecialchars($ent['name_km']) ?>" <?= $entityFilter === $ent['name_km'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ent['name_km']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select name="status" class="select-filter" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>ស្ថានភាពទាំងអស់</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>✓ បានអនុម័ត</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>⏳ រង់ចាំអនុម័ត</option>
                </select>
            </form>
        </div>

        <div class="toolbar-right">
            <!-- Download Excel Button -->
            <a href="<?= $excelExportUrl ?>" class="btn-tool btn-tool-excel" title="ទាញយកជាទម្រង់ Microsoft Excel (.xlsx)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>ទាញយក Excel (.xlsx)</span>
            </a>

            <!-- Print Button -->
            <button type="button" onclick="window.print()" class="btn-tool btn-tool-print" title="បោះពុម្ពឯកសារ">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>បោះពុម្ពរបាយការណ៍ (Print)</span>
            </button>
        </div>
    </div>

    <!-- Official Paper Sheet -->
    <div class="paper-sheet">

        <!-- Top Header Grid: Ministry (Left) & Kingdom (Right) -->
        <div class="doc-header-grid">
            <div class="doc-header-left">
                <img src="assets/images/logo.png" alt="MPWT Logo">
                <div class="doc-ministry-title">ក្រសួងសាធារណការ និងដឹកជញ្ជូន</div>
                <div class="doc-dots-line">..................................................</div>
                <div class="doc-letter-num">លេខ៖ ..........................................</div>
            </div>

            <div class="doc-header-right">
                <div class="doc-kingdom-title">ព្រះរាជាណាចក្រកម្ពុជា</div>
                <div class="doc-kingdom-motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
                <div class="doc-kingdom-symbol">***</div>
            </div>
        </div>

        <!-- Document Main Title -->
        <div class="doc-title-block">
            <h1 class="doc-main-title">
                របាយការណ៍វាយតម្លៃសមិទ្ធកម្មផែនការសកម្មភាពអាទិភាពប្រចាំឆ្នាំ <?= $kmYear ?>
            </h1>
            <h2 class="doc-sub-title">
                របស់<?= htmlspecialchars($unitName) ?>
            </h2>
        </div>

        <!-- Official Activities Table (11 Columns Exact Match) -->
        <table class="official-table">
            <thead>
                <tr>
                    <th class="col-code">កូដ</th>
                    <th class="col-no">ល.រ</th>
                    <th class="col-cluster">ចង្កោម<br>សកម្មភាព<br>អាទិភាព</th>
                    <th class="col-desc">សកម្មភាពអាទិភាព</th>
                    <th class="col-target">គោលដៅត្រូវ<br>សម្រេច<br>(Target)</th>
                    <th class="col-start">កាលបរិច្ឆេទ<br>ចាប់ផ្តើម</th>
                    <th class="col-end">កាលបរិច្ឆេទ<br>បញ្ចប់</th>
                    <th class="col-score">ពិន្ទុ</th>
                    <th class="col-actual">វឌ្ឍនភាពជាក់ស្តែង</th>
                    <th class="col-challenges">បញ្ហា<br>ប្រឈម</th>
                    <th class="col-doc">ឯកសារ<br>(ឈ្មោះ)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 30px; color: #64748b; font-style: italic;">
                            ពុំមានទិន្នន័យសកម្មភាពសម្រាប់កាលបរិច្ឆេទ ឬលក្ខខណ្ឌដែលបានជ្រើសរើសឡើយ។
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $counter = 1;
                    foreach ($activities as $act): 
                        $aid = (int)$act['id'];
                        $evalList = $evalsByAct[$aid] ?? [];

                        // Find latest evaluated quarter
                        $latestEval = null;
                        for ($q = 4; $q >= 1; $q--) {
                            if (isset($evalList[$q])) {
                                $latestEval = $evalList[$q];
                                break;
                            }
                        }

                        // Score
                        $scoreText = '-';
                        if ($latestEval && function_exists('calculate_activity_kpi_score')) {
                            $sc = calculate_activity_kpi_score($latestEval);
                            if ($sc !== null) {
                                $scoreText = number_format($sc, 0) . '%';
                            }
                        }

                        // Actual progress text
                        $actualProgress = '';
                        if (!empty($latestEval['actual_result'])) {
                            $actualProgress = trim($latestEval['actual_result']);
                        } elseif ($scoreText !== '-') {
                            $actualProgress = $scoreText;
                        } else {
                            $actualProgress = !empty($act['is_approved']) ? 'បានអនុម័ត' : 'រង់ចាំអនុម័ត';
                        }

                        // Challenges text
                        $challenges = !empty($latestEval['challenges']) ? trim($latestEval['challenges']) : 'គ្មាន';

                        // Document Name text
                        $docName = '';
                        if (!empty($latestEval['attachment_name'])) {
                            $docName = trim($latestEval['attachment_name']);
                        } elseif (!empty($latestEval['ref_attachment_name'])) {
                            $docName = trim($latestEval['ref_attachment_name']);
                        } else {
                            $docName = !empty($act['target']) ? 'ឯកសារយោង (' . $act['target'] . ')' : '-';
                        }

                        // Code
                        $code = !empty($act['activity_code']) 
                            ? trim($act['activity_code']) 
                            : ('DIA-' . substr((string)$year, -2) . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT));

                        // Cluster
                        $cluster = !empty($act['activity_subtype']) 
                            ? trim($act['activity_subtype']) 
                            : (!empty($act['activity_type']) ? trim($act['activity_type']) : 'CPAP' . $counter);
                    ?>
                        <tr>
                            <td class="col-code"><?= htmlspecialchars($code) ?></td>
                            <td class="col-no"><?= $counter ?></td>
                            <td class="col-cluster"><?= htmlspecialchars($cluster) ?></td>
                            <td class="col-desc">
                                <?= nl2br(htmlspecialchars($act['description'])) ?>
                            </td>
                            <td class="col-target"><?= htmlspecialchars($act['target']) ?></td>
                            <td class="col-start"><?= htmlspecialchars($act['start_quarter'] ?? 'ត្រីមាសទី១') ?></td>
                            <td class="col-end"><?= htmlspecialchars($act['end_quarter'] ?? 'ត្រីមាសទី៤') ?></td>
                            <td class="col-score"><?= htmlspecialchars($scoreText) ?></td>
                            <td class="col-actual"><?= nl2br(htmlspecialchars($actualProgress)) ?></td>
                            <td class="col-challenges"><?= nl2br(htmlspecialchars($challenges)) ?></td>
                            <td class="col-doc"><?= nl2br(htmlspecialchars($docName)) ?></td>
                        </tr>
                    <?php 
                        $counter++;
                    endforeach; 
                    ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signatures Section Matching Official Template -->
        <div class="signatures-grid">
            <div class="sig-col-left">
                <div class="sig-agree-title">បានឃើញ និងឯកភាព</div>
                <div class="sig-date-lunar"><?= $dayNameKh ?> ១កើត ខែមិគសិរ ឆ្នាំម្សាញ់ សប្តស័ក ព.ស.២៥៦៩</div>
                <div class="sig-date-solar">រាជធានីភ្នំពេញ ថ្ងៃទី<?= $curDay ?> ខែ<?= $curMonth ?> ឆ្នាំ<?= $curYearNum ?></div>
                <div class="sig-role-title">ប្រធាននាយកដ្ឋាន</div>
                <div class="sig-space"></div>
                <div class="sig-name"></div>
            </div>

            <div class="sig-col-right">
                <div class="sig-date-lunar" style="margin-top: 24px;"><?= $dayNameKh ?> ១កើត ខែមិគសិរ ឆ្នាំម្សាញ់ សប្តស័ក ព.ស.២៥៦៩</div>
                <div class="sig-date-solar">រាជធានីភ្នំពេញ ថ្ងៃទី<?= $curDay ?> ខែ<?= $curMonth ?> ឆ្នាំ<?= $curYearNum ?></div>
                <div class="sig-role-title">អ្នករៀបចំតារាង</div>
                <div class="sig-space"></div>
                <div class="sig-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
            </div>
        </div>

    </div>

</body>
</html>

