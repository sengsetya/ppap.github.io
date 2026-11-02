<?php
/**
 * Controller to Export Activities to Microsoft Excel (.xlsx)
 * Follows the official Royal Government of Cambodia & MPWT formatting
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tab_views.php';
require_once __DIR__ . '/includes/excel_exporter.php';

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
    // Admins can filter by entity or dept
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

// Determine Unit Name for Title
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

// Khmer digits for Year in title
$kmDigits = ['0'=>'០', '1'=>'១', '2'=>'២', '3'=>'៣', '4'=>'៤', '5'=>'៥', '6'=>'៦', '7'=>'៧', '8'=>'៨', '9'=>'៩'];
$kmYear = strtr((string)$year, $kmDigits);

$isZipAvailable = class_exists('ZipArchive');
$fileExt = $isZipAvailable ? '.xlsx' : '.xls';

$meta = [
    'year'      => $kmYear,
    'unit_name' => $unitName,
    'title'     => 'របាយការណ៍វាយតម្លៃសមិទ្ធកម្មផែនការសកម្មភាពអាទិភាពប្រចាំឆ្នាំ ' . $kmYear,
    'subtitle'  => 'របស់' . $unitName,
    'filename'  => 'Report_Activities_' . $year . '_' . date('Ymd_His') . $fileExt
];

export_activities_to_excel($activities, $evalsByAct, $meta);

