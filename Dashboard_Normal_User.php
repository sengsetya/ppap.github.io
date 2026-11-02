<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/tab_views.php';

// Enforce login
require_login();

$userId = $_SESSION['user_id'];
$pdo = get_db_connection();
$activeTab = $_GET['tab'] ?? 'home';

// Fetch user profile early
$stmt = $pdo->prepare('SELECT id, full_name, username, email, role, branch, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle Evaluation POST Actions (save / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['save_evaluation', 'delete_evaluation'])) {
    process_evaluation_request($pdo, $currentUser, 'Dashboard_Normal_User.php');
}

// Handle password change by the user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid security token.');
    } else {
        if ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Verify current password
            $st = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $st->execute(['id' => $userId]);
            $userData = $st->fetch();

            if (!$userData || !password_verify($currentPassword, $userData['password'])) {
                set_flash('danger', 'Your current password was incorrect.');
            } elseif (strlen($newPassword) < 6) {
                set_flash('danger', 'New password must be at least 6 characters long.');
            } elseif ($newPassword !== $confirmPassword) {
                set_flash('danger', 'New password and confirmation do not match.');
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password = :pwd WHERE id = :id');
                $upd->execute(['pwd' => $newHash, 'id' => $userId]);
                set_flash('success', 'Your password has been updated successfully!');
            }
        } elseif ($action === 'add_activity') {
            $year = (int)($_POST['year'] ?? 2026);
            $entity = trim($_POST['entity'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target = trim($_POST['target'] ?? '');
            $start_quarter = trim($_POST['start_quarter'] ?? '');
            $end_quarter = trim($_POST['end_quarter'] ?? '');
            $activity_type = trim($_POST['activity_type'] ?? '');
            $activity_subtype = trim($_POST['activity_subtype'] ?? '');
            $priority_policy = trim($_POST['priority_policy'] ?? '');
            $priority_policy_detail = trim($_POST['priority_policy_detail'] ?? '');
            $responsible_person = trim($_POST['responsible_person'] ?? '');
            $implementer = trim($_POST['implementer'] ?? '');
            $kpi_1 = trim($_POST['kpi_1'] ?? '');
            $kpi_2 = trim($_POST['kpi_2'] ?? '');
            $kpi_3 = trim($_POST['kpi_3'] ?? '');
            $submit_type = $_POST['submit_type'] ?? 'save';

            // Enforce entity & branch from user profile to prevent cross-entity activities
            $userBranch = $currentUser['branch'] ?? '';
            $userMatchedEntity = resolve_branch_entity($pdo, $userBranch);
            if ($userMatchedEntity) {
                $entity = $userMatchedEntity['name_km'];
                $chkDept = $pdo->prepare('SELECT id FROM departments WHERE entity_id = :eid AND (name_km = :d1 OR name_en = :d2) LIMIT 1');
                $chkDept->execute(['eid' => $userMatchedEntity['id'], 'd1' => $department, 'd2' => $department]);
                if (!$chkDept->fetch()) {
                    set_flash('danger', is_khmer() ? 'នាយកដ្ឋានដែលបានជ្រើសរើសមិនស្ថិតនៅក្រោមអង្គភាព/សាខារបស់អ្នកទេ!' : 'The selected department does not belong to your entity/branch.');
                    header('Location: Dashboard_Normal_User.php?tab=activities&view=create');
                    exit;
                }
            }

            if (empty($entity) || empty($department) || empty($description) || empty($target) || empty($start_quarter) || empty($end_quarter) || empty($activity_type) || empty($responsible_person) || empty($implementer)) {
                set_flash('danger', 'សូមបំពេញរាល់ព័ត៌មានចាំបាច់ (មានសញ្ញា *)');
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO activities (
                        user_id, year, entity, department, branch, description, target,
                        start_quarter, end_quarter, activity_type, activity_subtype,
                        priority_policy, priority_policy_detail, responsible_person,
                        implementer, kpi_1, kpi_2, kpi_3
                    ) VALUES (
                        :uid, :yr, :ent, :dept, :br, :desc, :tgt,
                        :sq, :eq, :act_t, :act_st,
                        :pp, :ppd, :resp,
                        :imp, :k1, :k2, :k3
                    )
                ');
                $ins->execute([
                    'uid'    => $userId,
                    'yr'     => $year,
                    'ent'    => $entity,
                    'dept'   => $department,
                    'br'     => $userBranch,
                    'desc'   => $description,
                    'tgt'    => $target,
                    'sq'     => $start_quarter,
                    'eq'     => $end_quarter,
                    'act_t'  => $activity_type,
                    'act_st' => $activity_subtype,
                    'pp'     => $priority_policy,
                    'ppd'    => $priority_policy_detail,
                    'resp'   => $responsible_person,
                    'imp'    => $implementer,
                    'k1'     => $kpi_1,
                    'k2'     => $kpi_2,
                    'k3'     => $kpi_3
                ]);

                if ($submit_type === 'save_and_new') {
                    set_flash('success', 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកជោគជ័យ! សូមបន្តបន្ថែមថ្មីមួយទៀត។');
                    header('Location: Dashboard_Normal_User.php?tab=activities');
                    exit;
                } else {
                    set_flash('success', 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកដោយជោគជ័យ!');
                    header('Location: Dashboard_Normal_User.php?tab=activities&view=list');
                    exit;
                }
            }
        } elseif ($action === 'delete_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            $chk = $pdo->prepare('SELECT is_approved FROM activities WHERE id = :id AND user_id = :uid LIMIT 1');
            $chk->execute(['id' => $actId, 'uid' => $userId]);
            $actData = $chk->fetch();
            if ($actData && !empty($actData['is_approved'])) {
                set_flash('danger', is_khmer() ? 'សកម្មភាពនេះមិនមានសិទ្ធិលុបចេញបានទេ ដោយសារត្រូវបានអនុម័តរួចហើយ!' : 'This activity cannot be deleted because it has already been approved!');
                header('Location: Dashboard_Normal_User.php?tab=activities&view=list');
                exit;
            }
            $del = $pdo->prepare('DELETE FROM activities WHERE id = :id AND user_id = :uid');
            $del->execute(['id' => $actId, 'uid' => $userId]);
            set_flash('success', 'សកម្មភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!');
            header('Location: Dashboard_Normal_User.php?tab=activities&view=list');
            exit;
        } elseif ($action === 'approve_activity' || $action === 'unapprove_activity') {
            set_flash('danger', is_khmer() ? 'លោកអ្នកជាសមាជិកទូទៅ មិនមានសិទ្ធិអនុម័តសកម្មភាពឡើយ (តម្រូវឱ្យប្រធានសាខា Head of Unit ជាអ្នកអនុម័ត)!' : 'Normal users cannot approve activities (Head of Unit approval required).');
            header('Location: Dashboard_Normal_User.php?tab=' . urlencode($activeTab));
            exit;
        } elseif ($action === 'edit_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            $year = (int)($_POST['year'] ?? 2026);
            $entity = trim($_POST['entity'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target = trim($_POST['target'] ?? '');
            $start_quarter = trim($_POST['start_quarter'] ?? '');
            $end_quarter = trim($_POST['end_quarter'] ?? '');
            $activity_type = trim($_POST['activity_type'] ?? '');
            $activity_subtype = trim($_POST['activity_subtype'] ?? '');
            $responsible_person = trim($_POST['responsible_person'] ?? '');
            $implementer = trim($_POST['implementer'] ?? '');
            $kpi_1 = trim($_POST['kpi_1'] ?? '');
            $kpi_2 = trim($_POST['kpi_2'] ?? '');
            $kpi_3 = trim($_POST['kpi_3'] ?? '');

            // Enforce entity & branch from user profile
            $userBranch = $currentUser['branch'] ?? '';
            $userMatchedEntity = resolve_branch_entity($pdo, $userBranch);
            if ($userMatchedEntity) {
                $entity = $userMatchedEntity['name_km'];
                $chkDept = $pdo->prepare('SELECT id FROM departments WHERE entity_id = :eid AND (name_km = :d1 OR name_en = :d2) LIMIT 1');
                $chkDept->execute(['eid' => $userMatchedEntity['id'], 'd1' => $department, 'd2' => $department]);
                if (!$chkDept->fetch()) {
                    set_flash('danger', is_khmer() ? 'នាយកដ្ឋានដែលបានជ្រើសរើសមិនស្ថិតនៅក្រោមអង្គភាព/សាខារបស់អ្នកទេ!' : 'The selected department does not belong to your entity/branch.');
                    header('Location: Dashboard_Normal_User.php?tab=activities&view=list');
                    exit;
                }
            }

            if ($actId <= 0 || empty($entity) || empty($department) || empty($description) || empty($target)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញព័ត៌មានចាំបាច់ដើម្បីកែប្រែសកម្មភាព!' : 'Please fill all required fields.');
            } else {
                $upd = $pdo->prepare('
                    UPDATE activities SET
                        year = :yr, entity = :ent, department = :dept, description = :desc,
                        target = :tgt, start_quarter = :sq, end_quarter = :eq,
                        activity_type = :act_t, activity_subtype = :act_st,
                        responsible_person = :resp, implementer = :imp,
                        kpi_1 = :k1, kpi_2 = :k2, kpi_3 = :k3
                    WHERE id = :id AND user_id = :uid
                ');
                $upd->execute([
                    'yr'     => $year, 'ent'    => $entity, 'dept'   => $department,
                    'desc'   => $description, 'tgt'    => $target, 'sq'     => $start_quarter,
                    'eq'     => $end_quarter, 'act_t'  => $activity_type, 'act_st' => $activity_subtype,
                    'resp'   => $responsible_person, 'imp'    => $implementer,
                    'k1'     => $kpi_1, 'k2'     => $kpi_2, 'k3'     => $kpi_3,
                    'id'     => $actId, 'uid'    => $userId
                ]);
                set_flash('success', is_khmer() ? 'សកម្មភាពត្រូវបានកែប្រែដោយជោគជ័យ!' : 'Activity updated successfully!');
            }
            header('Location: Dashboard_Normal_User.php?tab=activities&view=list');
            exit;
        } elseif ($action === 'approve_activity' || $action === 'unapprove_activity') {
            set_flash('danger', is_khmer() ? 'លោកអ្នកជាសមាជិកទូទៅ មិនមានសិទ្ធិអនុម័តសកម្មភាពឡើយ (តម្រូវឱ្យប្រធានសាខា Head of Unit ជាអ្នកអនុម័ត)!' : 'Normal users cannot approve activities (Head of Unit approval required).');
            header('Location: Dashboard_Normal_User.php?tab=' . urlencode($activeTab));
            exit;
        }
    }

    header('Location: Dashboard_Normal_User.php?tab=' . urlencode($activeTab));
    exit;
}

// Fetch user profile
$stmt = $pdo->prepare('SELECT id, full_name, username, email, role, branch, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// For Normal User, system user breakdown is restricted
$allUsers = [];
$stats = [
    'total'          => 0,
    'super_admin'    => 0,
    'admin'          => 0,
    'branch_manager' => 0,
    'user'           => 1
];

// Fetch user's saved activities with user info
$actStmt = $pdo->prepare('SELECT a.*, u.full_name as author_name, u.username as author_username, u.branch as user_branch FROM activities a LEFT JOIN users u ON a.user_id = u.id WHERE a.user_id = :uid ORDER BY a.id DESC');
$actStmt->execute(['uid' => $userId]);
$myActivities = $actStmt->fetchAll();

$flash = get_flash();
$csrfToken = generate_csrf_token();

// Extract initials
$names = explode(' ', trim($currentUser['full_name']));
$initials = '';
foreach ($names as $n) {
    if (!empty($n)) $initials .= strtoupper($n[0]);
}
$initials = substr($initials, 0, 2) ?: 'U';
$joinedDate = date('F j, Y', strtotime($currentUser['created_at']));
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard_Normal_User - PPAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/dashboards.css?v=3">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=3">
</head>
<body class="dashboard-body user-theme">

<div class="app-layout">
    <!-- 1. Left Sidebar -->
    <?php render_sidebar($activeTab, $currentUser, 'Dashboard_Normal_User.php'); ?>

    <!-- 2. Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="dashboard-topbar user-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="page-current-title">
                    <?php
                    switch ($activeTab) {
                        case 'activities': echo t('page_my_activities'); break;
                        case 'evaluation': echo t('page_evaluation'); break;
                        case 'notifications': echo t('page_notifications'); break;
                        case 'reports': echo t('page_reports'); break;
                        case 'home': default: echo t('page_user_home'); break;
                    }
                    ?>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Language Switcher Pill -->
                <?php render_language_switcher(); ?>

                <span class="system-status-pill user-status-pill">
                    <span class="status-dot"></span> <?= t('status_user') ?>
                </span>
                <div class="user-pill" style="padding: 5px 14px 5px 6px; display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 9999px;">
                    <div class="user-pill-avatar user-pill-avatar-green" style="width: 38px; height: 38px; font-size: 0.92rem; font-weight: 700;">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div class="user-pill-info" style="display: flex; flex-direction: column;">
                        <strong style="font-size: 0.88rem; color: #0f172a; font-weight: 700;"><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                            @<?= htmlspecialchars($currentUser['username']) ?> &bull; <?= htmlspecialchars($currentUser['email']) ?>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="btn-nav-logout"><?= t('logout') ?></a>
            </div>
        </header>

        <!-- Main Inner Container -->
        <main class="dashboard-inner-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" style="margin-bottom: 24px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'home'): ?>
                <!-- Monthly Report Progress Panel (វឌ្ឍនភាពការងារទទួលរបាយការណ៍) -->
                <?php render_monthly_report_progress($pdo); ?>

                <!-- Info Metrics Grid -->
                <div class="info-grid" style="margin-bottom: 28px;">
                    <div class="info-item">
                        <div class="info-label"><?= is_khmer() ? 'តួនាទីគណនី' : 'Account Role' ?></div>
                        <div class="info-value" style="color: #047857; font-weight: 700;"><?= is_khmer() ? 'មន្ត្រី' : 'User' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= t('field_branch') ?></div>
                        <div class="info-value" style="color: #0284c7; font-size: 0.88rem;">🏢 <?= htmlspecialchars($currentUser['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member ID</div>
                        <div class="info-value">#<?= htmlspecialchars($currentUser['id']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Verified</div>
                        <div class="info-value" style="color: #0284c7;">Active &bull; Primary</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registered Since</div>
                        <div class="info-value"><?= htmlspecialchars($joinedDate) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Render Content for Tab 2, 3, 4, or 5 -->
                <?php render_tab_content($activeTab, 'user', $allUsers, $stats, $currentUser, $csrfToken, $myActivities); ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
function toggleSidebar() {
    document.querySelector('.app-sidebar').classList.toggle('sidebar-collapsed');
}
</script>
</body>
</html>
