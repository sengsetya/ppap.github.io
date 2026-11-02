<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/tab_views.php';

// Enforce Admin access
require_admin_access();

$userId = $_SESSION['user_id'];
$pdo = get_db_connection();
$activeTab = $_GET['tab'] ?? 'home';

// Current logged in Admin data
$curStmt = $pdo->prepare('SELECT id, full_name, username, email, role, branch, created_at FROM users WHERE id = :id LIMIT 1');
$curStmt->execute(['id' => $userId]);
$currentUser = $curStmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle CSV / Excel Template Download
if (isset($_GET['download_template']) && $_GET['download_template'] == 1) {
    download_activities_csv_template();
    exit;
}

// Handle Evaluation POST Actions (save / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['save_evaluation', 'delete_evaluation'])) {
    process_evaluation_request($pdo, $currentUser, 'Dashboard_admin.php');
}

// Handle AJAX Add Entity
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_entity') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF security token.']);
        exit;
    }
    $nameKm = trim($_POST['name_km'] ?? '');
    $nameEn = trim($_POST['name_en'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($nameKm)) {
        echo json_encode(['success' => false, 'message' => is_khmer() ? 'សូមបញ្ចូលឈ្មោះអង្គភាពជាភាសាខ្មែរ' : 'Entity name in Khmer is required.']);
        exit;
    }

    $chk = $pdo->prepare('SELECT id FROM entities WHERE name_km = :km LIMIT 1');
    $chk->execute(['km' => $nameKm]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => is_khmer() ? 'អង្គភាពនេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Entity already exists!']);
        exit;
    }

    $ins = $pdo->prepare('INSERT INTO entities (name_km, name_en, code, created_by) VALUES (:km, :en, :code, :uid)');
    $ins->execute([
        'km'   => $nameKm,
        'en'   => !empty($nameEn) ? $nameEn : null,
        'code' => !empty($code) ? $code : null,
        'uid'  => $userId
    ]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'name_km' => $nameKm,
        'name_en' => $nameEn,
        'code' => $code,
        'message' => is_khmer() ? 'អង្គភាពថ្មីត្រូវបានបញ្ចូលដោយជោគជ័យ!' : 'New entity registered successfully!'
    ]);
    exit;
}

// Handle AJAX Add Department
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_department') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF security token.']);
        exit;
    }
    $nameKm = trim($_POST['name_km'] ?? '');
    $nameEn = trim($_POST['name_en'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $entityId = !empty($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;

    if (empty($nameKm)) {
        echo json_encode(['success' => false, 'message' => is_khmer() ? 'សូមបញ្ចូលឈ្មោះនាយកដ្ឋានជាភាសាខ្មែរ' : 'Department name in Khmer is required.']);
        exit;
    }

    $chk = $pdo->prepare('SELECT id FROM departments WHERE name_km = :km LIMIT 1');
    $chk->execute(['km' => $nameKm]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => is_khmer() ? 'នាយកដ្ឋាននេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Department already exists!']);
        exit;
    }

    $unitLevel = in_array($_POST['unit_level'] ?? '', ['national', 'sub_national']) ? $_POST['unit_level'] : 'national';
    $ins = $pdo->prepare('INSERT INTO departments (name_km, name_en, code, unit_level, entity_id, created_by) VALUES (:km, :en, :code, :level, :ent, :uid)');
    $ins->execute([
        'km'    => $nameKm,
        'en'    => !empty($nameEn) ? $nameEn : null,
        'code'  => !empty($code) ? $code : null,
        'level' => $unitLevel,
        'ent'   => $entityId,
        'uid'   => $userId
    ]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'name_km' => $nameKm,
        'name_en' => $nameEn,
        'code' => $code,
        'message' => is_khmer() ? 'នាយកដ្ឋានថ្មីត្រូវបានបញ្ចូលដោយជោគជ័យ!' : 'New department registered successfully!'
    ]);
    exit;
}

// Handle Admin Actions (Add / Delete activity / Add / Delete entity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid security token.');
    } else {
        if ($action === 'add_activity') {
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
                    header('Location: Dashboard_admin.php?tab=activities&view=create');
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
                    header('Location: Dashboard_admin.php?tab=activities&view=create');
                    exit;
                } else {
                    set_flash('success', 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកដោយជោគជ័យ!');
                    header('Location: Dashboard_admin.php?tab=activities&view=list');
                    exit;
                }
            }
        } elseif ($action === 'delete_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            $chk = $pdo->prepare('SELECT is_approved FROM activities WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $actId]);
            $actData = $chk->fetch();
            if ($actData && !empty($actData['is_approved'])) {
                set_flash('danger', is_khmer() ? 'សកម្មភាពនេះមិនមានសិទ្ធិលុបចេញបានទេ ដោយសារត្រូវបានអនុម័តរួចហើយ!' : 'This activity cannot be deleted because it has already been approved!');
                header('Location: Dashboard_admin.php?tab=activities&view=list');
                exit;
            }
            $del = $pdo->prepare('DELETE FROM activities WHERE id = :id');
            $del->execute(['id' => $actId]);
            set_flash('success', 'សកម្មភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!');
            header('Location: Dashboard_admin.php?tab=activities&view=list');
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
                    header('Location: Dashboard_admin.php?tab=activities&view=list');
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
                        activity_type = :act_t, activity_subtype = :act_st, responsible_person = :resp, implementer = :imp,
                        kpi_1 = :k1, kpi_2 = :k2, kpi_3 = :k3
                    WHERE id = :id
                ');
                $upd->execute([
                    'yr'     => $year, 'ent'    => $entity, 'dept'   => $department,
                    'desc'   => $description, 'tgt'    => $target, 'sq'     => $start_quarter,
                    'eq'     => $end_quarter, 'act_t'  => $activity_type, 'act_st' => $activity_subtype,
                    'resp'   => $responsible_person, 'imp'    => $implementer, 'k1'     => $kpi_1,
                    'k2'     => $kpi_2, 'k3'     => $kpi_3, 'id'     => $actId
                ]);
                set_flash('success', is_khmer() ? 'សកម្មភាពត្រូវបានកែប្រែដោយជោគជ័យ!' : 'Activity updated successfully!');
            }
            header('Location: Dashboard_admin.php?tab=activities&view=list');
            exit;
        } elseif ($action === 'approve_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            if ($actId > 0) {
                $approverName = $currentUser['full_name'] . ' (' . (is_khmer() ? 'អ្នកគ្រប់គ្រង' : 'Admin') . ')';
                $upd = $pdo->prepare('
                    UPDATE activities SET
                        is_approved = 1,
                        approved_at = NOW(),
                        approved_by = :uid,
                        approved_by_name = :name
                    WHERE id = :id
                ');
                $upd->execute([
                    'uid'  => $userId,
                    'name' => $approverName,
                    'id'   => $actId
                ]);
                set_flash('success', is_khmer() ? 'សកម្មភាពត្រូវបានអនុម័តដោយជោគជ័យ! ✅' : 'Activity approved successfully! ✅');
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'unapprove_activity') {
            set_flash('danger', is_khmer() ? 'មុខងារដកការអនុម័តសកម្មភាពត្រូវបានបិទ (មិនអាចដកការអនុម័តវិញបានឡើយ ប៉ុន្តែលោកអ្នកអាចធ្វើការកែប្រែសកម្មភាពបាន)!' : 'Revoke approval feature is disabled. Activities can be edited, but not revoked.');
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'add_entity') {
            $nameKm = trim($_POST['name_km'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $unitLevel = in_array($_POST['unit_level'] ?? '', ['national', 'sub_national']) ? $_POST['unit_level'] : 'national';

            if (empty($nameKm)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលឈ្មោះអង្គភាពជាភាសាខ្មែរ' : 'Please provide entity name in Khmer.');
            } else {
                $chk = $pdo->prepare('SELECT id FROM entities WHERE name_km = :km LIMIT 1');
                $chk->execute(['km' => $nameKm]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? 'អង្គភាព "' . htmlspecialchars($nameKm) . '" មានក្នុងប្រព័ន្ធរួចហើយ!' : 'Entity already exists!');
                } else {
                    $ins = $pdo->prepare('INSERT INTO entities (name_km, name_en, code, unit_level, created_by) VALUES (:km, :en, :code, :level, :uid)');
                    $ins->execute([
                        'km'    => $nameKm,
                        'en'    => !empty($nameEn) ? $nameEn : null,
                        'code'  => !empty($code) ? $code : null,
                        'level' => $unitLevel,
                        'uid'   => $userId
                    ]);
                    set_flash('success', is_khmer() ? 'អង្គភាពថ្មី "' . htmlspecialchars($nameKm) . '" ត្រូវបានបញ្ចូលដោយជោគជ័យ!' : 'New entity successfully registered!');
                }
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'edit_entity') {
            $entityId = (int)($_POST['entity_id'] ?? 0);
            $nameKm = trim($_POST['name_km'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $unitLevel = in_array($_POST['unit_level'] ?? '', ['national', 'sub_national']) ? $_POST['unit_level'] : 'national';

            if ($entityId <= 0 || empty($nameKm)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលឈ្មោះអង្គភាពជាភាសាខ្មែរ' : 'Please provide entity name in Khmer.');
            } else {
                $chk = $pdo->prepare('SELECT id FROM entities WHERE name_km = :km AND id != :id LIMIT 1');
                $chk->execute(['km' => $nameKm, 'id' => $entityId]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? 'ឈ្មោះអង្គភាពនេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Entity name already exists!');
                } else {
                    $upd = $pdo->prepare('UPDATE entities SET name_km = :km, name_en = :en, code = :code, unit_level = :level WHERE id = :id');
                    $upd->execute([
                        'km'    => $nameKm,
                        'en'    => !empty($nameEn) ? $nameEn : null,
                        'code'  => !empty($code) ? $code : null,
                        'level' => $unitLevel,
                        'id'    => $entityId
                    ]);
                    set_flash('success', is_khmer() ? 'អង្គភាព "' . htmlspecialchars($nameKm) . '" ត្រូវបានកែប្រែដោយជោគជ័យ!' : 'Entity updated successfully!');
                }
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_entity') {
            $entityId = (int)($_POST['entity_id'] ?? 0);
            if ($entityId > 0) {
                $del = $pdo->prepare('DELETE FROM entities WHERE id = :id');
                $del->execute(['id' => $entityId]);
                set_flash('success', is_khmer() ? 'អង្គភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Entity deleted successfully!');
            }
            header('Location: Dashboard_admin.php?tab=' . urlencode($activeTab));
            exit;
        } elseif ($action === 'add_department') {
            $nameKm = trim($_POST['name_km'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $entityId = !empty($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;

            if (empty($nameKm)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលឈ្មោះនាយកដ្ឋានជាភាសាខ្មែរ' : 'Please provide department name in Khmer.');
            } else {
                $chk = $pdo->prepare('SELECT id FROM departments WHERE name_km = :km LIMIT 1');
                $chk->execute(['km' => $nameKm]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? 'នាយកដ្ឋាន "' . htmlspecialchars($nameKm) . '" មានក្នុងប្រព័ន្ធរួចហើយ!' : 'Department already exists!');
                } else {
                    $unitLevel = in_array($_POST['unit_level'] ?? '', ['national', 'sub_national']) ? $_POST['unit_level'] : 'national';
                    $ins = $pdo->prepare('INSERT INTO departments (name_km, name_en, code, unit_level, entity_id, created_by) VALUES (:km, :en, :code, :level, :ent, :uid)');
                    $ins->execute([
                        'km'    => $nameKm,
                        'en'    => !empty($nameEn) ? $nameEn : null,
                        'code'  => !empty($code) ? $code : null,
                        'level' => $unitLevel,
                        'ent'   => $entityId,
                        'uid'   => $userId
                    ]);
                    set_flash('success', is_khmer() ? 'នាយកដ្ឋានថ្មី "' . htmlspecialchars($nameKm) . '" ត្រូវបានបញ្ចូលដោយជោគជ័យ!' : 'New department successfully registered!');
                }
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'edit_department') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $nameKm = trim($_POST['name_km'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $entityId = !empty($_POST['entity_id']) ? (int)$_POST['entity_id'] : null;
            $unitLevel = in_array($_POST['unit_level'] ?? '', ['national', 'sub_national']) ? $_POST['unit_level'] : 'national';

            if ($deptId <= 0 || empty($nameKm)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលឈ្មោះនាយកដ្ឋានជាភាសាខ្មែរ' : 'Please provide department name in Khmer.');
            } else {
                $chk = $pdo->prepare('SELECT id FROM departments WHERE name_km = :km AND id != :id LIMIT 1');
                $chk->execute(['km' => $nameKm, 'id' => $deptId]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? 'ឈ្មោះនាយកដ្ឋាននេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Department name already exists!');
                } else {
                    $upd = $pdo->prepare('UPDATE departments SET name_km = :km, name_en = :en, code = :code, unit_level = :level, entity_id = :ent_id WHERE id = :id');
                    $upd->execute([
                        'km'     => $nameKm,
                        'en'     => !empty($nameEn) ? $nameEn : null,
                        'code'   => !empty($code) ? $code : null,
                        'level'  => $unitLevel,
                        'ent_id' => $entityId,
                        'id'     => $deptId
                    ]);
                    set_flash('success', is_khmer() ? 'នាយកដ្ឋាន "' . htmlspecialchars($nameKm) . '" ត្រូវបានកែប្រែដោយជោគជ័យ!' : 'Department updated successfully!');
                }
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_department') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            if ($deptId > 0) {
                $del = $pdo->prepare('DELETE FROM departments WHERE id = :id');
                $del->execute(['id' => $deptId]);
                set_flash('success', is_khmer() ? 'នាយកដ្ឋានត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Department deleted successfully!');
            }
            header('Location: Dashboard_admin.php?tab=' . urlencode($activeTab));
            exit;
        } elseif ($action === 'add_announcement') {
            $subject = trim($_POST['subject'] ?? '');
            $title = trim($_POST['title'] ?? 'សេចក្តីជូនដំណឹង');
            $description = trim($_POST['description'] ?? '');
            $dtReceive = trim($_POST['datetime_receive'] ?? '');
            $dtExpire = trim($_POST['datetime_expire'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if (empty($subject) || empty($title) || empty($description) || empty($dtReceive) || empty($dtExpire)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់នៃសេចក្តីជូនដំណឹង (កម្មវត្ថុ, ចំណងជើង, ខ្លឹមសារ, កាលបរិច្ឆេទ)!' : 'Please fill all required announcement fields.');
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO announcements (subject, title, description, datetime_receive, datetime_expire, is_active, created_by)
                    VALUES (:subj, :title, :desc, :dt_rec, :dt_exp, :active, :uid)
                ');
                $ins->execute([
                    'subj'   => $subject,
                    'title'  => $title,
                    'desc'   => $description,
                    'dt_rec' => date('Y-m-d H:i:s', strtotime($dtReceive)),
                    'dt_exp' => date('Y-m-d H:i:s', strtotime($dtExpire)),
                    'active' => $isActive,
                    'uid'    => $userId
                ]);
                set_flash('success', is_khmer() ? 'សេចក្តីជូនដំណឹងត្រូវបានផ្សព្វផ្សាយដោយជោគជ័យ! 📢' : 'Announcement published successfully! 📢');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'edit_announcement') {
            $annId = (int)($_POST['announcement_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $title = trim($_POST['title'] ?? 'សេចក្តីជូនដំណឹង');
            $description = trim($_POST['description'] ?? '');
            $dtReceive = trim($_POST['datetime_receive'] ?? '');
            $dtExpire = trim($_POST['datetime_expire'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if ($annId <= 0 || empty($subject) || empty($title) || empty($description) || empty($dtReceive) || empty($dtExpire)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញព័ត៌មានចាំបាច់ដើម្បីកែសម្រួលសេចក្តីជូនដំណឹង!' : 'Please fill all required fields to edit announcement.');
            } else {
                $upd = $pdo->prepare('
                    UPDATE announcements SET
                        subject = :subj, title = :title, description = :desc,
                        datetime_receive = :dt_rec, datetime_expire = :dt_exp, is_active = :active
                    WHERE id = :id
                ');
                $upd->execute([
                    'subj'   => $subject,
                    'title'  => $title,
                    'desc'   => $description,
                    'dt_rec' => date('Y-m-d H:i:s', strtotime($dtReceive)),
                    'dt_exp' => date('Y-m-d H:i:s', strtotime($dtExpire)),
                    'active' => $isActive,
                    'id'     => $annId
                ]);
                set_flash('success', is_khmer() ? 'សេចក្តីជូនដំណឹងត្រូវបានកែសម្រួលដោយជោគជ័យ! ✅' : 'Announcement updated successfully! ✅');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_announcement') {
            $annId = (int)($_POST['announcement_id'] ?? 0);
            if ($annId > 0) {
                $del = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
                $del->execute(['id' => $annId]);
                set_flash('success', is_khmer() ? 'សេចក្តីជូនដំណឹងត្រូវបានលុបដោយជោគជ័យ!' : 'Announcement deleted successfully!');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'toggle_announcement_status') {
            $annId = (int)($_POST['announcement_id'] ?? 0);
            if ($annId > 0) {
                $upd = $pdo->prepare('UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $upd->execute(['id' => $annId]);
                set_flash('success', is_khmer() ? 'ស្ថានភាពសេចក្តីជូនដំណឹងត្រូវបានផ្លាស់ប្តូរ!' : 'Announcement status toggled successfully!');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'add_user') {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $branch = trim($_POST['branch'] ?? '');

            if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់ (ឈ្មោះពេញ ឈ្មោះគណនី អ៊ីមែល ពាក្យសម្ងាត់)!' : 'All required fields must be filled.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('danger', is_khmer() ? 'អាសយដ្ឋានអ៊ីមែលមិនត្រឹមត្រូវទេ!' : 'Invalid email address provided.');
            } elseif (strlen($password) < 6) {
                set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់ត្រូវមានយ៉ាងតិច ៦ តួអក្សរ!' : 'Password must be at least 6 characters.');
            } elseif (!in_array($role, ['admin', 'branch_manager', 'user'], true)) {
                set_flash('danger', is_khmer() ? 'តួនាទីដែលបានជ្រើសមិនត្រឹមត្រូវទេ!' : 'Invalid role selected.');
            } else {
                $chk = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
                $chk->execute(['u' => $username, 'e' => $email]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់ ឬ អ៊ីមែលនេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Username or email already exists.');
                } else {
                    $branch = !empty($branch) ? $branch : 'កំពង់ផែស្វយ័តភ្នំពេញ';
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role, branch) VALUES (:fn, :un, :em, :pwd, :rl, :br)');
                    $ins->execute([
                        'fn'  => $fullName,
                        'un'  => $username,
                        'em'  => $email,
                        'pwd' => $hash,
                        'rl'  => $role,
                        'br'  => $branch
                    ]);
                    set_flash('success', is_khmer() ? "បានបង្កើតគណនី '{$username}' ({$branch}) ដោយជោគជ័យ!" : "User account '{$username}' created successfully!");
                }
            }
            header('Location: Dashboard_admin.php?tab=home');
            exit;
        } elseif ($action === 'edit_user') {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $branch = trim($_POST['branch'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            $chkTarget = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
            $chkTarget->execute(['id' => $targetUserId]);
            $targetUser = $chkTarget->fetch();

            if (!$targetUser) {
                set_flash('danger', is_khmer() ? 'រកមិនឃើញគណនីអ្នកប្រើប្រាស់នេះទេ!' : 'User not found.');
            } elseif ($targetUser['role'] === 'super_admin') {
                set_flash('danger', is_khmer() ? 'លោកអ្នកមិនមានសិទ្ធិកែប្រែគណនី Super Admin ឡើយ!' : 'You do not have permission to modify a Super Admin account.');
            } elseif (empty($fullName) || empty($username) || empty($email)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញឈ្មោះពេញ ឈ្មោះគណនី និងអ៊ីមែល!' : 'Full name, username, and email are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('danger', is_khmer() ? 'អាសយដ្ឋានអ៊ីមែលមិនត្រឹមត្រូវទេ!' : 'Invalid email address provided.');
            } elseif (!in_array($role, ['admin', 'branch_manager', 'user'], true)) {
                set_flash('danger', is_khmer() ? 'តួនាទីមិនត្រឹមត្រូវទេ!' : 'Invalid role selected.');
            } else {
                $chkUniq = $pdo->prepare('SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id LIMIT 1');
                $chkUniq->execute(['u' => $username, 'e' => $email, 'id' => $targetUserId]);
                if ($chkUniq->fetch()) {
                    set_flash('danger', is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់ ឬ អ៊ីមែលនេះត្រូវបានប្រើប្រាស់ដោយគណនីផ្សេងរួចហើយ!' : 'Username or email is already taken by another account.');
                } else {
                    $branch = !empty($branch) ? $branch : 'កំពង់ផែស្វយ័តភ្នំពេញ';

                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់ថ្មីត្រូវមានយ៉ាងតិច ៦ តួអក្សរ!' : 'New password must be at least 6 characters.');
                            header('Location: Dashboard_admin.php?tab=home');
                            exit;
                        }
                        $pwdHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET full_name = :fn, username = :un, email = :em, role = :rl, branch = :br, password = :pwd WHERE id = :id');
                        $upd->execute([
                            'fn'  => $fullName,
                            'un'  => $username,
                            'em'  => $email,
                            'rl'  => $role,
                            'br'  => $branch,
                            'pwd' => $pwdHash,
                            'id'  => $targetUserId
                        ]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET full_name = :fn, username = :un, email = :em, role = :rl, branch = :br WHERE id = :id');
                        $upd->execute([
                            'fn'  => $fullName,
                            'un'  => $username,
                            'em'  => $email,
                            'rl'  => $role,
                            'br'  => $branch,
                            'id'  => $targetUserId
                        ]);
                    }

                    if ($targetUserId === $userId) {
                        $_SESSION['full_name'] = $fullName;
                        $_SESSION['username']  = $username;
                        $_SESSION['email']     = $email;
                    }

                    set_flash('success', is_khmer() ? "បានកែប្រែព័ត៌មានគណនី '{$username}' ដោយជោគជ័យ!" : "User account '{$username}' updated successfully!");
                }
            }
            header('Location: Dashboard_admin.php?tab=home');
            exit;
        } elseif ($action === 'delete_user') {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);

            if ($targetUserId === $userId) {
                set_flash('danger', is_khmer() ? 'លោកអ្នកមិនអាចលុបគណនីផ្ទាល់ខ្លួនរបស់អ្នកបានទេ!' : 'You cannot delete your own account while logged in.');
            } else {
                $chkTarget = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
                $chkTarget->execute(['id' => $targetUserId]);
                $targetUser = $chkTarget->fetch();

                if (!$targetUser) {
                    set_flash('danger', is_khmer() ? 'រកមិនឃើញគណនីអ្នកប្រើប្រាស់នេះទេ!' : 'User not found.');
                } elseif ($targetUser['role'] === 'super_admin') {
                    set_flash('danger', is_khmer() ? 'លោកអ្នកមិនមានសិទ្ធិលុបគណនី Super Admin ឡើយ!' : 'You do not have permission to delete a Super Admin account.');
                } else {
                    $delStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $delStmt->execute(['id' => $targetUserId]);
                    set_flash('success', is_khmer() ? "គណនី '{$targetUser['username']}' ត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!" : "User account '{$targetUser['username']}' deleted successfully.");
                }
            }
            header('Location: Dashboard_admin.php?tab=home');
            exit;
        } elseif ($action === 'import_excel_activities') {
            $defaultYear = (int)($_POST['default_year'] ?? 2026);
            $defaultEntity = trim($_POST['default_entity'] ?? '');
            $defaultDept = trim($_POST['default_department'] ?? '');
            $assignedUserId = (int)($_POST['assigned_user_id'] ?? $userId);
            $isApproved = isset($_POST['is_approved']) ? (int)$_POST['is_approved'] : 1;
            $responsiblePerson = trim($_POST['responsible_person'] ?? $currentUser['full_name']);
            $submitMode = $_POST['submit_mode'] ?? 'import';

            $parsedRows = [];

            // 1. Check file upload
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['excel_file']['tmp_name'];
                $fileName = $_FILES['excel_file']['name'];
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $parsedRows = parse_excel_or_csv_file($fileTmp, $ext);
            } elseif (!empty($_POST['csv_raw'])) {
                // 2. Direct pasted text
                $parsedRows = parse_csv_file_content($_POST['csv_raw']);
            }

            if (empty($parsedRows)) {
                set_flash('danger', is_khmer() ? 'សូមជ្រើសរើសឯកសារ Excel ឬ បិទភ្ជាប់ទិន្នន័យ CSV ត្រឹមត្រូវ!' : 'Please upload a valid Excel file or paste CSV content.');
                header('Location: Dashboard_admin.php?tab=upload_excel');
                exit;
            }

            $mappedActivities = map_excel_rows_to_activities(
                $parsedRows,
                $defaultEntity,
                $defaultDept,
                $defaultYear,
                $assignedUserId,
                $isApproved,
                $responsiblePerson
            );

            if (empty($mappedActivities)) {
                set_flash('danger', is_khmer() ? 'ពុំមានទិន្នន័យសកម្មភាពត្រឹមត្រូវត្រូវបានរកឃើញក្នុងឯកសារនេះឡើយ។ សូមពិនិត្យទម្រង់ក្បាលជួរឈរ (Headers)!' : 'No valid activities parsed from file. Please check column headers.');
                header('Location: Dashboard_admin.php?tab=upload_excel');
                exit;
            }

            if ($submitMode === 'preview') {
                $_SESSION['excel_preview_activities'] = $mappedActivities;
                $_SESSION['excel_preview_stats'] = [
                    'count' => count($mappedActivities),
                    'year' => $defaultYear,
                    'user_id' => $assignedUserId
                ];
                set_flash('info', is_khmer() ? 'បានស្រង់ទិន្នន័យចំនួន ' . count($mappedActivities) . ' សកម្មភាពជោគជ័យ! សូមពិនិត្យមើលខាងក្រោមមុននឹងបញ្ជាក់ការបញ្ចូល។' : 'Preview generated: ' . count($mappedActivities) . ' activities ready.');
                header('Location: Dashboard_admin.php?tab=upload_excel');
                exit;
            } else {
                // Direct import
                $res = insert_activities_into_db($pdo, $mappedActivities, $userId, $currentUser['full_name']);
                unset($_SESSION['excel_preview_activities'], $_SESSION['excel_preview_stats']);
                if ($res['success']) {
                    set_flash('success', is_khmer() ? '🎉 បានបញ្ចូលទិន្នន័យសកម្មភាពចំនួន ' . $res['count'] . ' ពីឯកសារ Excel ចូលក្នុងប្រព័ន្ធដោយជោគជ័យ!' : 'Successfully imported ' . $res['count'] . ' activities from Excel!');
                    header('Location: Dashboard_admin.php?tab=activities&view=list');
                    exit;
                } else {
                    set_flash('danger', is_khmer() ? 'មានបញ្ហាក្នុងការបញ្ចូលទិន្នន័យទៅក្នុង Database: ' . implode(', ', $res['errors'] ?? []) : 'Import failed: ' . implode(', ', $res['errors'] ?? []));
                    header('Location: Dashboard_admin.php?tab=upload_excel');
                    exit;
                }
            }
        } elseif ($action === 'confirm_excel_import') {
            $previewActivities = $_SESSION['excel_preview_activities'] ?? [];
            if (!empty($previewActivities)) {
                $res = insert_activities_into_db($pdo, $previewActivities, $userId, $currentUser['full_name']);
                unset($_SESSION['excel_preview_activities'], $_SESSION['excel_preview_stats']);
                if ($res['success']) {
                    set_flash('success', is_khmer() ? '🎉 បានបញ្ជាក់បញ្ចូលទិន្នន័យសកម្មភាពចំនួន ' . $res['count'] . ' ពីឯកសារ Excel ដោយជោគជ័យ!' : 'Confirmed & imported ' . $res['count'] . ' activities successfully!');
                    header('Location: Dashboard_admin.php?tab=activities&view=list');
                    exit;
                } else {
                    set_flash('danger', is_khmer() ? 'មានបញ្ហាក្នុងការបញ្ចូលទិន្នន័យទៅក្នុង Database!' : 'Import failed.');
                    header('Location: Dashboard_admin.php?tab=upload_excel');
                    exit;
                }
            }
            header('Location: Dashboard_admin.php?tab=upload_excel');
            exit;
        } elseif ($action === 'cancel_excel_preview') {
            unset($_SESSION['excel_preview_activities'], $_SESSION['excel_preview_stats']);
            set_flash('info', is_khmer() ? 'បានបោះបង់ការពិនិត្យទិន្នន័យរួចរាល់' : 'Preview cancelled.');
            header('Location: Dashboard_admin.php?tab=upload_excel');
            exit;
        } elseif ($action === 'add_activity_priority') {
            $actCode = strtoupper(trim($_POST['activity_code'] ?? ''));
            $actType = trim($_POST['activity_type'] ?? '');
            if ($actType === '__CUSTOM__') $actType = '';
            $actSubtype = trim($_POST['activity_subtype'] ?? '');
            $noted = trim($_POST['noted'] ?? '');
            $submitType = $_POST['submit_type'] ?? 'save';

            if (empty($actCode) || empty($actType)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលកូដសកម្មភាព និងប្រភេទសកម្មភាព!' : 'Activity code and activity type are required.');
            } else {
                $chk = $pdo->prepare('
                    SELECT id FROM activity_priorities 
                    WHERE activity_code = :code 
                      AND activity_type = :type 
                      AND COALESCE(activity_subtype, "") = :subtype 
                    LIMIT 1
                ');
                $chk->execute([
                    'code'    => $actCode,
                    'type'    => $actType,
                    'subtype' => $actSubtype
                ]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? "ទិន្នន័យសកម្មភាពអាទិភាពនេះ (កូដ '{$actCode}', ប្រភេទ និង អនុប្រភេទ) មានក្នុងប្រព័ន្ធរួចហើយ!" : "This activity priority record already exists!");
                } else {
                    $ins = $pdo->prepare('
                        INSERT INTO activity_priorities (activity_code, activity_type, activity_subtype, noted, created_by)
                        VALUES (:code, :type, :subtype, :noted, :uid)
                    ');
                    $ins->execute([
                        'code'    => $actCode,
                        'type'    => $actType,
                        'subtype' => !empty($actSubtype) ? $actSubtype : null,
                        'noted'   => !empty($noted) ? $noted : null,
                        'uid'     => $userId
                    ]);

                    if ($submitType === 'save_and_new') {
                        set_flash('success', is_khmer() ? "សកម្មភាពអាទិភាព '{$actCode}' ត្រូវបានរក្សាទុកជោគជ័យ! សូមបន្តបន្ថែមថ្មីមួយទៀត។" : "Activity priority '{$actCode}' saved successfully! Add another below.");
                    } else {
                        set_flash('success', is_khmer() ? "សកម្មភាពអាទិភាព '{$actCode}' ត្រូវបានបញ្ចូលដោយជោគជ័យ! ⚡" : "Activity priority '{$actCode}' registered successfully! ⚡");
                    }
                }
            }
            header('Location: Dashboard_admin.php?tab=activity_priority');
            exit;
        } elseif ($action === 'edit_activity_priority') {
            $priorityId = (int)($_POST['priority_id'] ?? 0);
            $actCode = strtoupper(trim($_POST['activity_code'] ?? ''));
            $actType = trim($_POST['activity_type'] ?? '');
            if ($actType === '__CUSTOM__') $actType = '';
            $actSubtype = trim($_POST['activity_subtype'] ?? '');
            $noted = trim($_POST['noted'] ?? '');

            if ($priorityId <= 0 || empty($actCode) || empty($actType)) {
                set_flash('danger', is_khmer() ? 'សូមបញ្ចូលកូដសកម្មភាព និងប្រភេទសកម្មភាព!' : 'Activity code and activity type are required.');
            } else {
                $chk = $pdo->prepare('
                    SELECT id FROM activity_priorities 
                    WHERE activity_code = :code 
                      AND activity_type = :type 
                      AND COALESCE(activity_subtype, "") = :subtype 
                      AND id != :id 
                    LIMIT 1
                ');
                $chk->execute([
                    'code'    => $actCode,
                    'type'    => $actType,
                    'subtype' => $actSubtype,
                    'id'      => $priorityId
                ]);
                if ($chk->fetch()) {
                    set_flash('danger', is_khmer() ? "ទិន្នន័យសកម្មភាពអាទិភាពនេះ (កូដ '{$actCode}', ប្រភេទ និង អនុប្រភេទ) មានក្នុងប្រព័ន្ធរួចហើយ!" : "This activity priority record already exists!");
                } else {
                    $upd = $pdo->prepare('
                        UPDATE activity_priorities SET
                            activity_code = :code,
                            activity_type = :type,
                            activity_subtype = :subtype,
                            noted = :noted
                        WHERE id = :id
                    ');
                    $upd->execute([
                        'code'    => $actCode,
                        'type'    => $actType,
                        'subtype' => !empty($actSubtype) ? $actSubtype : null,
                        'noted'   => !empty($noted) ? $noted : null,
                        'id'      => $priorityId
                    ]);
                    set_flash('success', is_khmer() ? "សកម្មភាពអាទិភាព '{$actCode}' ត្រូវបានកែសម្រួលដោយជោគជ័យ! ✅" : "Activity priority '{$actCode}' updated successfully! ✅");
                }
            }
            header('Location: Dashboard_admin.php?tab=activity_priority');
            exit;
        } elseif ($action === 'delete_activity_priority') {
            $priorityId = (int)($_POST['priority_id'] ?? 0);
            if ($priorityId > 0) {
                $del = $pdo->prepare('DELETE FROM activity_priorities WHERE id = :id');
                $del->execute(['id' => $priorityId]);
                set_flash('success', is_khmer() ? 'សកម្មភាពអាទិភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Activity priority deleted successfully!');
            }
            header('Location: Dashboard_admin.php?tab=activity_priority');
            exit;
        }
    }

    header('Location: Dashboard_admin.php?tab=' . urlencode($activeTab));
    exit;
}

// Current logged in Admin data
$curStmt = $pdo->prepare('SELECT id, full_name, username, email, role, created_at FROM users WHERE id = :id LIMIT 1');
$curStmt->execute(['id' => $userId]);
$currentUser = $curStmt->fetch();

// Fetch all users for Admin Directory
$usersStmt = $pdo->query('SELECT id, full_name, username, email, role, branch, created_at FROM users ORDER BY id ASC');
$allUsers = $usersStmt->fetchAll();

// PPAP Official Branches and distribution statistics
$branchesList = get_ppap_branches();
$branchCounts = [];
foreach ($branchesList as $b) {
    $branchCounts[$b] = 0;
}
foreach ($allUsers as $u) {
    $b = $u['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ';
    if (!isset($branchCounts[$b])) {
        $branchCounts[$b] = 0;
    }
    $branchCounts[$b]++;
}

// Fetch all activities with creator info for Admin
$actStmt = $pdo->query('SELECT a.*, u.full_name as author_name, u.username as author_username, u.branch as user_branch FROM activities a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC');
$adminActivities = $actStmt->fetchAll();

// Fetch all registered entities & departments
$allEntities = get_all_entities($pdo);
$allDepartments = get_all_departments($pdo);

$stats = [
    'total'       => count($allUsers),
    'user'        => 0,
    'admin'       => 0,
    'super'       => 0,
    'entities'    => count($allEntities),
    'departments' => count($allDepartments)
];
foreach ($allUsers as $u) {
    if ($u['role'] === 'user') $stats['user']++;
    elseif ($u['role'] === 'admin') $stats['admin']++;
    elseif ($u['role'] === 'super_admin') $stats['super']++;
}

$flash = get_flash();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard admin - PPAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/dashboards.css?v=3">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=3">
    <script src="assets/js/chart.umd.min.js"></script>
    <script src="assets/js/chartjs-plugin-datalabels.min.js"></script>
</head>
<body class="dashboard-body admin-theme">

<div class="app-layout">
    <!-- 1. Left Sidebar -->
    <?php render_sidebar($activeTab, $currentUser, 'Dashboard_admin.php'); ?>

    <!-- 2. Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="dashboard-topbar admin-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="page-current-title">
                    <?php
                    switch ($activeTab) {
                        case 'activities': echo t('page_activities'); break;
                        case 'evaluation': echo t('page_evaluation'); break;
                        case 'notifications': echo t('page_notifications'); break;
                        case 'reports': echo t('page_reports'); break;
                        case 'unit_reports': echo t('page_unit_reports'); break;
                        case 'entities': echo t('page_entities'); break;
                        case 'departments': echo t('page_departments'); break;
                        case 'upload_excel': echo t('page_upload_excel'); break;
                        case 'activity_priority':
                        case 'activity_priorities': echo t('page_activity_priority'); break;
                        case 'home': default: echo t('page_admin_home'); break;
                    }
                    ?>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Language Switcher Pill -->
                <?php render_language_switcher(); ?>

                <span class="system-status-pill admin-status-pill">
                    <span class="status-dot"></span> <?= t('status_admin') ?>
                </span>
                <div class="user-pill">
                    <div class="user-pill-avatar admin-pill-avatar">AD</div>
                    <div class="user-pill-info">
                        <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                        <span class="role-tag-admin"><?= t('role_admin') ?></span>
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

                <!-- Metrics Row -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon metric-blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['total'] ?></div>
                            <div class="metric-label"><?= t('stat_registered_accounts') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['user'] ?></div>
                            <div class="metric-label"><?= t('stat_normal_members') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-purple">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['admin'] + $stats['super'] ?></div>
                            <div class="metric-label"><?= t('stat_staff_officers') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(2, 132, 199, 0.12); color: #0284c7;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= count($allEntities) ?></div>
                            <div class="metric-label"><?= t('stat_total_entities') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= count($allDepartments) ?></div>
                            <div class="metric-label"><?= t('stat_total_departments') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value" style="color: #0284c7;"><?= is_khmer() ? 'សកម្ម' : 'Active' ?></div>
                            <div class="metric-label"><?= t('stat_directory_sync') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Executive Management Quick Card -->
                <div class="panel-card" style="margin-bottom: 24px; border-left: 5px solid #0284c7; background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 1.4rem;">🏛️ 🏢</span>
                                <h2 style="margin: 0; font-size: 1.18rem; color: #0f172a;"><?= is_khmer() ? 'ការគ្រប់គ្រងអង្គភាព និងនាយកដ្ឋាន' : 'Entity & Department Management' ?></h2>
                                <span class="hero-badge admin-badge" style="font-size: 0.75rem; padding: 2px 10px;"><?= count($allEntities) ?> <?= is_khmer() ? 'អង្គភាព' : 'Entities' ?> • <?= count($allDepartments) ?> <?= is_khmer() ? 'នាយកដ្ឋាន' : 'Depts' ?></span>
                            </div>
                            <p style="margin: 4px 0 0 0; font-size: 0.88rem; color: #64748b;"><?= is_khmer() ? 'បង្កើត និងគ្រប់គ្រងរចនាសម្ព័ន្ធអង្គភាព និងនាយកដ្ឋានទាំងអស់សម្រាប់ប្រើប្រាស់ក្នុងទម្រង់សកម្មភាព' : 'Create and manage organizational entities and departments used across activity planning forms' ?></p>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="Dashboard_admin.php?tab=entities" class="btn-primary-toggle" style="padding: 8px 14px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.86rem; display: inline-flex; align-items: center; gap: 6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                                </svg>
                                <span><?= t('menu_entities') ?></span>
                            </a>
                            <a href="Dashboard_admin.php?tab=departments" class="btn-primary-toggle" style="background: #059669; border-color: #059669; padding: 8px 14px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.86rem; display: inline-flex; align-items: center; gap: 6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span><?= t('btn_add_dept') ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Admin Directory Section -->
                <div class="panel-card">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2><?= t('dir_title') ?></h2>
                            <p><?= t('dir_subtitle') ?></p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <!-- Branch Filter Dropdown -->
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label for="branchFilter" style="font-size: 0.82rem; font-weight: 600; color: #475569; white-space: nowrap;">🏢 <?= is_khmer() ? 'សាខា:' : 'Branch:' ?></label>
                                <select id="branchFilter" class="input-modern" onchange="filterUserTable()" style="padding: 7px 12px; font-size: 0.85rem; min-width: 190px; border-radius: 8px;">
                                    <option value=""><?= t('filter_all_branches') ?> (<?= count($allUsers) ?>)</option>
                                    <?php foreach ($branchesList as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>">
                                            <?= htmlspecialchars($b) ?> (<?= $branchCounts[$b] ?? 0 ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Live Keyword Search -->
                            <input type="text" id="userSearch" class="input-modern" placeholder="<?= t('ph_search_user') ?>" onkeyup="filterUserTable()" style="padding: 7px 12px; font-size: 0.85rem; width: 200px; border-radius: 8px;">
                            
                            <!-- Export CSV Button -->
                            <button type="button" class="btn-export" onclick="exportToCsv()" title="<?= t('btn_export_csv') ?>" style="padding: 7px 12px; font-size: 0.85rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>CSV</span>
                            </button>

                            <!-- Add New User Button -->
                            <button type="button" class="btn-primary-toggle" onclick="openCreateUserModal()" style="padding: 7px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer; background: #0284c7; border-color: #0284c7;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <span><?= t('btn_create_user') ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="dashboard-table" id="directoryTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th><?= t('th_username') ?></th>
                                    <th><?= t('th_email') ?></th>
                                    <th><?= t('th_branch') ?></th>
                                    <th><?= t('th_assigned_role') ?></th>
                                    <th><?= t('th_reg_date') ?></th>
                                    <th style="text-align: right;"><?= t('th_actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $u): ?>
                                    <tr data-branch="<?= htmlspecialchars($u['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ') ?>">
                                        <td><span class="badge-id">#<?= $u['id'] ?></span></td>
                                        <td>
                                            <strong style="color: #0f172a;"><?= htmlspecialchars($u['full_name']) ?></strong>
                                            <div class="sub-text">@<?= htmlspecialchars($u['username']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <span class="branch-pill">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                                                </svg>
                                                <span><?= htmlspecialchars($u['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ') ?></span>
                                            </span>
                                        </td>
                                        <td><?= get_role_badge_html($u['role']) ?></td>
                                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <?php if ($u['role'] === 'super_admin'): ?>
                                                <span class="self-tag" style="background: #f3e8ff; color: #7c3aed; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px;">👑 Root Admin</span>
                                            <?php else: ?>
                                                <button type="button" class="btn-action-edit" title="<?= t('btn_update_user') ?>" onclick='openEditUserModal(<?= json_encode([
                                                    "id"        => $u["id"],
                                                    "full_name" => $u["full_name"],
                                                    "username"  => $u["username"],
                                                    "email"     => $u["email"],
                                                    "role"      => $u["role"],
                                                    "branch"    => $u["branch"] ?? "កំពង់ផែស្វយ័តភ្នំពេញ"
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    <span><?= is_khmer() ? 'កែប្រែ' : 'Edit' ?></span>
                                                </button>
                                                <?php if ($u['id'] !== $userId): ?>
                                                    <form action="Dashboard_admin.php" method="POST" style="display: inline-block; margin: 0 0 0 4px;" onsubmit="return confirm('<?= t('confirm_delete_user') ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn-action-delete" title="<?= t('btn_delete') ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            <span><?= t('btn_delete') ?></span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="self-tag" style="background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 0.72rem; margin-left: 4px; font-weight: 600;"><?= is_khmer() ? 'គណនីរបស់អ្នក' : 'You' ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="noResultsRow" style="display: none;">
                                    <td colspan="7" style="text-align: center; padding: 36px 20px; color: #64748b;">
                                        <div style="font-size: 2rem; margin-bottom: 8px;">🔍</div>
                                        <div style="font-weight: 600; font-size: 0.95rem; color: #334155;"><?= is_khmer() ? 'មិនមានទិន្នន័យត្រូវគ្នានឹងការស្វែងរក ឬសាខានេះទេ' : 'No users match your search criteria or selected branch.' ?></div>
                                        <div style="font-size: 0.82rem; margin-top: 4px;"><?= is_khmer() ? 'សូមសាកល្បងផ្លាស់ប្តូរពាក្យគន្លឹះ ឬជ្រើសរើស "គ្រប់សាខាទាំងអស់"' : 'Try adjusting your search keyword or selecting "All Branches".' ?></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($activeTab === 'entities'): ?>
                <!-- Dedicated Entity Management Page -->
                <div class="breadcrumb-nav">
                    <a href="Dashboard_admin.php?tab=home"><?= t('crumb_home') ?></a>
                    <span class="separator">&gt;</span>
                    <span class="current"><?= t('menu_entities') ?></span>
                </div>

                <!-- Form Add New Entity (Full-width, Spacious & Clean) -->
                <div class="panel-card" style="margin-bottom: 24px;">
                    <div class="panel-card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2 style="font-size: 1.15rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.25rem;">🏛️</span>
                                <span><?= t('entity_form_title') ?></span>
                            </h2>
                            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.86rem;"><?= t('entity_form_sub') ?></p>
                        </div>
                    </div>

                    <form action="Dashboard_admin.php?tab=entities" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_entity">
                        <input type="hidden" name="redirect_tab" value="entities">

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; align-items: flex-end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_entity_name_km') ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="name_km" class="input-modern" placeholder="<?= t('ph_entity_name_km') ?>" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_entity_name_en') ?>
                                </label>
                                <input type="text" name="name_en" class="input-modern" placeholder="<?= t('ph_entity_name_en') ?>" style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= is_khmer() ? 'កម្រិតអង្គភាព (Unit Level)' : 'Unit Level' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="unit_level" class="input-modern" style="width: 100%; box-sizing: border-box;" required>
                                    <option value="national" selected><?= is_khmer() ? 'អង្គភាពថ្នាក់ជាតិ (National Level)' : 'National Level' ?></option>
                                    <option value="sub_national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ក្រោមជាតិ (Sub-national Level)' : 'Sub-national Level' ?></option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_entity_code') ?>
                                </label>
                                <input type="text" name="code" class="input-modern" placeholder="<?= t('ph_entity_code') ?>" style="width: 100%; box-sizing: border-box; text-transform: uppercase;">
                            </div>

                            <div>
                                <button type="submit" class="btn-primary-toggle" style="width: 100%; height: 42px; padding: 0 18px; font-size: 0.92rem; font-weight: 700; justify-content: center; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; border-radius: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span><?= t('btn_add_entity') ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- List of Registered Entities (Full-width, Spacious & Balanced) -->
                <div class="panel-card" style="width: 100%;">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2 style="font-size: 1.15rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.25rem;">📋</span>
                                <span><?= t('entity_list_title') ?> (<?= count($allEntities) ?>)</span>
                            </h2>
                            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.86rem;"><?= is_khmer() ? 'បញ្ជីអង្គភាពដែលបានចុះឈ្មោះក្នុងមូលដ្ឋានទិន្នន័យផ្លូវការ' : 'Official entities registered in database' ?></p>
                        </div>
                        <input type="text" id="entitySearch" class="input-modern" placeholder="<?= is_khmer() ? 'ស្វែងរកអង្គភាព...' : 'Search entities...' ?>" onkeyup="filterEntityTable()" style="padding: 8px 14px; font-size: 0.88rem; width: 250px; border-radius: 8px;">
                    </div>

                        <div class="table-container" style="overflow-x: auto;">
                            <table class="dashboard-table" id="entityTable" style="table-layout: fixed; width: 100%; min-width: 860px;">
                                <thead>
                                    <tr style="background: #0e2a86; color: #ffffff;">
                                        <th style="width: 55px; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;">ID</th>
                                        <th style="width: 28%; background: #0e2a86; color: #ffffff; padding: 12px 12px;"><?= t('th_entity_name_km') ?></th>
                                        <th style="width: 26%; background: #0e2a86; color: #ffffff; padding: 12px 12px;"><?= t('th_entity_name_en') ?></th>
                                        <th style="width: 14%; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= t('th_entity_code') ?></th>
                                        <th style="width: 16%; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= is_khmer() ? 'កម្រិតអង្គភាព' : 'Level' ?></th>
                                        <th style="width: 155px; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= t('th_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allEntities as $ent): 
                                        $entLevel = $ent['unit_level'] ?? 'national';
                                    ?>
                                        <tr>
                                            <td style="text-align: center;"><span class="badge-id">#<?= $ent['id'] ?></span></td>
                                            <td><strong style="color: #0f172a; font-size: 0.88rem; line-height: 1.4; display: block;"><?= htmlspecialchars($ent['name_km']) ?></strong></td>
                                            <td><span style="color: #64748b; font-size: 0.84rem; line-height: 1.35; display: block;"><?= htmlspecialchars($ent['name_en'] ?? '-') ?></span></td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($ent['code'])): ?>
                                                    <span style="font-size: 0.76rem; font-weight: 800; padding: 3px 9px; border-radius: 6px; background: #eff6ff; color: #0e2a86; border: 1px solid #bfdbfe; font-family: monospace; display: inline-block;">
                                                        <?= htmlspecialchars($ent['code']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($entLevel === 'sub_national'): ?>
                                                    <span style="font-size: 0.74rem; padding: 3px 8px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 6px; font-weight: 700; display: inline-block;">
                                                        <?= is_khmer() ? 'ថ្នាក់ក្រោមជាតិ' : 'Sub-national' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size: 0.74rem; padding: 3px 8px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; font-weight: 700; display: inline-block;">
                                                        <?= is_khmer() ? 'ថ្នាក់ជាតិ' : 'National' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                                    <button type="button" class="btn-action-edit" onclick='openEditEntityModal(<?= json_encode($ent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' title="<?= t('btn_edit') ?>" style="padding: 5px 9px; font-size: 0.78rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span><?= t('btn_edit') ?></span>
                                                    </button>
                                                    
                                                    <form action="Dashboard_admin.php?tab=entities" method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= t('confirm_delete_entity') ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="delete_entity">
                                                        <input type="hidden" name="entity_id" value="<?= $ent['id'] ?>">
                                                        <button type="submit" class="btn-action-delete" title="<?= t('btn_delete') ?>" style="padding: 5px 9px; font-size: 0.78rem; font-weight: 700; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            <span><?= t('btn_delete') ?></span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

            <?php elseif ($activeTab === 'departments'): ?>
                <!-- Dedicated Department Management Page -->
                <div class="breadcrumb-nav">
                    <a href="Dashboard_admin.php?tab=home"><?= t('crumb_home') ?></a>
                    <span class="separator">&gt;</span>
                    <span class="current"><?= t('menu_departments') ?></span>
                </div>

                <!-- Form Add New Department (Full-width, Spacious & Clean) -->
                <div class="panel-card" style="margin-bottom: 24px;">
                    <div class="panel-card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2 style="font-size: 1.15rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.25rem;">🏢</span>
                                <span><?= t('dept_form_title') ?></span>
                            </h2>
                            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.86rem;"><?= t('dept_form_sub') ?></p>
                        </div>
                    </div>

                    <form action="Dashboard_admin.php?tab=departments" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_department">
                        <input type="hidden" name="redirect_tab" value="departments">

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_dept_entity') ?>
                                </label>
                                <select name="entity_id" class="input-modern" style="width: 100%; box-sizing: border-box;">
                                    <option value=""><?= t('ph_select_dept_entity') ?></option>
                                    <?php foreach ($allEntities as $e): ?>
                                        <option value="<?= $e['id'] ?>">
                                            <?= htmlspecialchars($e['name_km']) ?><?= !empty($e['name_en']) ? ' (' . htmlspecialchars($e['name_en']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_dept_name_km') ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="name_km" class="input-modern" placeholder="<?= t('ph_dept_name_km') ?>" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_dept_name_en') ?>
                                </label>
                                <input type="text" name="name_en" class="input-modern" placeholder="<?= t('ph_dept_name_en') ?>" style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= is_khmer() ? 'កម្រិតអង្គភាព (Unit Level)' : 'Unit Level' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="unit_level" class="input-modern" style="width: 100%; box-sizing: border-box;" required>
                                    <option value="national" selected><?= is_khmer() ? 'អង្គភាពថ្នាក់ជាតិ (National Level)' : 'National Level' ?></option>
                                    <option value="sub_national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ក្រោមជាតិ (Sub-national Level)' : 'Sub-national Level' ?></option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #334155;">
                                    <?= t('field_dept_code') ?>
                                </label>
                                <input type="text" name="code" class="input-modern" placeholder="<?= t('ph_dept_code') ?>" style="width: 100%; box-sizing: border-box; text-transform: uppercase;">
                            </div>

                            <div>
                                <button type="submit" class="btn-primary-toggle" style="background: #059669; border-color: #059669; width: 100%; height: 42px; padding: 0 18px; font-size: 0.92rem; font-weight: 700; justify-content: center; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; border-radius: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span><?= t('btn_add_dept') ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- List of Registered Departments (Full-width, Spacious & Balanced) -->
                <div class="panel-card" style="width: 100%;">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2 style="font-size: 1.15rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.25rem;">📋</span>
                                <span><?= t('dept_list_title') ?> (<?= count($allDepartments) ?>)</span>
                            </h2>
                            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.86rem;"><?= is_khmer() ? 'បញ្ជីនាយកដ្ឋានដែលបានចុះឈ្មោះក្នុងមូលដ្ឋានទិន្នន័យផ្លូវការ' : 'Official departments registered in database' ?></p>
                        </div>
                        <input type="text" id="deptSearch" class="input-modern" placeholder="<?= is_khmer() ? 'ស្វែងរកនាយកដ្ឋាន...' : 'Search departments...' ?>" onkeyup="filterDepartmentTable()" style="padding: 8px 14px; font-size: 0.88rem; width: 250px; border-radius: 8px;">
                    </div>

                        <div class="table-container" style="overflow-x: auto;">
                            <table class="dashboard-table" id="departmentTable" style="table-layout: fixed; width: 100%; min-width: 1050px;">
                                <thead>
                                    <tr style="background: #0e2a86; color: #ffffff;">
                                        <th style="width: 55px; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;">ID</th>
                                        <th style="width: 25%; background: #0e2a86; color: #ffffff; padding: 12px 12px;"><?= t('th_dept_name_km') ?></th>
                                        <th style="width: 22%; background: #0e2a86; color: #ffffff; padding: 12px 12px;"><?= t('th_dept_name_en') ?></th>
                                        <th style="width: 20%; background: #0e2a86; color: #ffffff; padding: 12px 12px;"><?= t('th_dept_entity') ?></th>
                                        <th style="width: 10%; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= t('th_dept_code') ?></th>
                                        <th style="width: 11%; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= is_khmer() ? 'កម្រិតអង្គភាព' : 'Level' ?></th>
                                        <th style="width: 155px; text-align: center; background: #0e2a86; color: #ffffff; padding: 12px 10px;"><?= t('th_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allDepartments as $dept): 
                                        $dLevel = $dept['unit_level'] ?? 'national';
                                    ?>
                                        <tr>
                                            <td style="text-align: center;"><span class="badge-id">#<?= $dept['id'] ?></span></td>
                                            <td><strong style="color: #0f172a; font-size: 0.88rem; line-height: 1.4; display: block;"><?= htmlspecialchars($dept['name_km']) ?></strong></td>
                                            <td><span style="color: #64748b; font-size: 0.84rem; line-height: 1.35; display: block;"><?= htmlspecialchars($dept['name_en'] ?? '-') ?></span></td>
                                            <td>
                                                <?php if (!empty($dept['entity_name_km'])): ?>
                                                    <span class="role-tag-admin" style="font-size: 0.75rem; padding: 3px 8px; line-height: 1.35; display: inline-block;"><?= htmlspecialchars($dept['entity_name_km']) ?></span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.82rem;"><?= is_khmer() ? 'ទូទៅ' : 'General' ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($dept['code'])): ?>
                                                    <span style="font-size: 0.76rem; font-weight: 800; padding: 3px 9px; border-radius: 6px; background: #eff6ff; color: #0e2a86; border: 1px solid #bfdbfe; font-family: monospace; display: inline-block;">
                                                        <?= htmlspecialchars($dept['code']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($dLevel === 'sub_national'): ?>
                                                    <span style="font-size: 0.74rem; padding: 3px 8px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 6px; font-weight: 700; display: inline-block;">
                                                        <?= is_khmer() ? 'ថ្នាក់ក្រោមជាតិ' : 'Sub-national' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size: 0.74rem; padding: 3px 8px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; font-weight: 700; display: inline-block;">
                                                        <?= is_khmer() ? 'ថ្នាក់ជាតិ' : 'National' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                                    <button type="button" class="btn-action-edit" onclick='openEditDepartmentModal(<?= json_encode($dept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' title="<?= t('btn_edit') ?>" style="padding: 5px 9px; font-size: 0.78rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span><?= t('btn_edit') ?></span>
                                                    </button>

                                                    <form action="Dashboard_admin.php?tab=departments" method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= t('confirm_delete_dept') ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="delete_department">
                                                        <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                                                        <button type="submit" class="btn-action-delete" title="<?= t('btn_delete') ?>" style="padding: 5px 9px; font-size: 0.78rem; font-weight: 700; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            <span><?= t('btn_delete') ?></span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

            <?php else: ?>
                <!-- Render Content for Tab 2, 3, 4, or 5 -->
                <?php render_tab_content($activeTab, 'admin', $allUsers, $stats, $currentUser, $csrfToken, $adminActivities); ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal for Creating New User -->
<div id="createUserModal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.25rem;">👤</span>
                <h3 style="margin: 0; font-size: 1.12rem; font-weight: 700; color: #0f172a;"><?= t('modal_create_user_title') ?></h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeCreateUserModal()">&times;</button>
        </div>
        <form action="Dashboard_admin.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="modal-body" style="padding: 20px 24px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះពេញ (Full Name)' : 'Full Name' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" class="input-modern" placeholder="e.g. Chan Vathanaka" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់ (Username)' : 'Username' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="username" class="input-modern" placeholder="e.g. chan_vathanaka" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'អាសយដ្ឋានអ៊ីមែល (Email)' : 'Email' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" class="input-modern" placeholder="e.g. vathanaka@ppap.com" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ពាក្យសម្ងាត់ដំបូង (Password)' : 'Initial Password' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" name="password" class="input-modern" placeholder="Min. 6 characters" minlength="6" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_branch') ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="branch" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <?php foreach ($branchesList as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'កំណត់តួនាទី (Role)' : 'Role' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="role" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <option value="user" selected><?= is_khmer() ? 'សមាជិកទូទៅ' : 'Normal User' ?></option>
                        <option value="branch_manager"><?= is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager' ?></option>
                        <option value="admin"><?= is_khmer() ? 'អ្នកគ្រប់គ្រង' : 'Admin' ?></option>
                    </select>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cancel" onclick="closeCreateUserModal()"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-save-modal" style="background: #0284c7;"><?= is_khmer() ? 'បង្កើតគណនី' : 'Create User' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Editing User -->
<div id="editUserModal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.25rem;">✏️</span>
                <h3 style="margin: 0; font-size: 1.12rem; font-weight: 700; color: #0f172a;"><?= t('modal_edit_user_title') ?></h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form action="Dashboard_admin.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="target_user_id" id="editUserId" value="">
            
            <div class="modal-body" style="padding: 20px 24px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះពេញ (Full Name)' : 'Full Name' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" id="editFullName" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់ (Username)' : 'Username' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="username" id="editUsername" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'អាសយដ្ឋានអ៊ីមែល (Email)' : 'Email' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" id="editEmail" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_branch') ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="branch" id="editBranch" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <?php foreach ($branchesList as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'កំណត់តួនាទី (Role)' : 'Role' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="role" id="editRole" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <option value="user"><?= is_khmer() ? 'សមាជិកទូទៅ' : 'Normal User' ?></option>
                        <option value="branch_manager"><?= is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager' ?></option>
                        <option value="admin"><?= is_khmer() ? 'អ្នកគ្រប់គ្រង' : 'Admin' ?></option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_new_password_optional') ?>
                    </label>
                    <input type="password" name="new_password" id="editNewPassword" class="input-modern" placeholder="<?= is_khmer() ? 'បញ្ចូលប្រសិនបើចង់ផ្លាស់ប្តូរ (យ៉ាងតិច ៦ តួអក្សរ)' : 'Min. 6 chars (leave blank to keep current)' ?>" minlength="6" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cancel" onclick="closeEditUserModal()"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-save-modal" style="background: #0284c7;"><?= t('btn_update_user') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Entity (កែសម្រួលអង្គភាព) -->
<div id="editEntityModal" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 2px solid #0e2a86; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #09173f 0%, #0e2a86 100%); color: #ffffff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #d97706;">
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #fbbf24; display: flex; align-items: center; gap: 8px;">
                <span>✏️</span>
                <span><?= is_khmer() ? 'កែសម្រួលព័ត៌មានអង្គភាព' : 'Edit Entity Details' ?></span>
            </h3>
            <button type="button" onclick="closeEditEntityModal()" style="background: transparent; border: none; color: #ffffff; font-size: 1.4rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form action="Dashboard_admin.php?tab=entities" method="POST" style="padding: 22px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_entity">
            <input type="hidden" name="redirect_tab" value="entities">
            <input type="hidden" name="entity_id" id="editEntityId" value="">

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_entity_name_km') ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name_km" id="editEntityNameKm" class="input-modern" required style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_entity_name_en') ?>
                </label>
                <input type="text" name="name_en" id="editEntityNameEn" class="input-modern" style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= is_khmer() ? 'កម្រិតអង្គភាព (Unit Level)' : 'Unit Level' ?> <span style="color: #ef4444;">*</span>
                </label>
                <select name="unit_level" id="editEntityUnitLevel" class="input-modern" style="width: 100%; box-sizing: border-box;" required>
                    <option value="national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ជាតិ (National Level)' : 'National Level' ?></option>
                    <option value="sub_national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ក្រោមជាតិ (Sub-national Level)' : 'Sub-national Level' ?></option>
                </select>
            </div>

            <div style="margin-bottom: 22px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_entity_code') ?>
                </label>
                <input type="text" name="code" id="editEntityCode" class="input-modern" style="width: 100%; box-sizing: border-box; text-transform: uppercase;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                <button type="button" onclick="closeEditEntityModal()" class="btn-cancel" style="padding: 9px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #475569; font-weight: 600; cursor: pointer;"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-primary-toggle" style="padding: 9px 20px; border-radius: 8px; border: none; background: #0e2a86; color: #ffffff; font-weight: 700; cursor: pointer;">💾 <?= is_khmer() ? 'រក្សាទុកការកែប្រែ' : 'Save Changes' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Department (កែសម្រួលនាយកដ្ឋាន) -->
<div id="editDepartmentModal" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 14px; width: 100%; max-width: 540px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 2px solid #0e2a86; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #09173f 0%, #0e2a86 100%); color: #ffffff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #d97706;">
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #fbbf24; display: flex; align-items: center; gap: 8px;">
                <span>✏️</span>
                <span><?= is_khmer() ? 'កែសម្រួលព័ត៌មាននាយកដ្ឋាន' : 'Edit Department Details' ?></span>
            </h3>
            <button type="button" onclick="closeEditDepartmentModal()" style="background: transparent; border: none; color: #ffffff; font-size: 1.4rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form action="Dashboard_admin.php?tab=departments" method="POST" style="padding: 22px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_department">
            <input type="hidden" name="redirect_tab" value="departments">
            <input type="hidden" name="department_id" id="editDeptId" value="">

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_dept_entity') ?>
                </label>
                <select name="entity_id" id="editDeptEntityId" class="input-modern" style="width: 100%; box-sizing: border-box;">
                    <option value=""><?= t('ph_select_dept_entity') ?></option>
                    <?php foreach ($allEntities as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['name_km']) ?><?= !empty($e['name_en']) ? ' (' . htmlspecialchars($e['name_en']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_dept_name_km') ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name_km" id="editDeptNameKm" class="input-modern" required style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_dept_name_en') ?>
                </label>
                <input type="text" name="name_en" id="editDeptNameEn" class="input-modern" style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= is_khmer() ? 'កម្រិតអង្គភាព (Unit Level)' : 'Unit Level' ?> <span style="color: #ef4444;">*</span>
                </label>
                <select name="unit_level" id="editDeptUnitLevel" class="input-modern" style="width: 100%; box-sizing: border-box;" required>
                    <option value="national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ជាតិ (National Level)' : 'National Level' ?></option>
                    <option value="sub_national"><?= is_khmer() ? 'អង្គភាពថ្នាក់ក្រោមជាតិ (Sub-national Level)' : 'Sub-national Level' ?></option>
                </select>
            </div>

            <div style="margin-bottom: 22px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 0.88rem; color: #1e293b;">
                    <?= t('field_dept_code') ?>
                </label>
                <input type="text" name="code" id="editDeptCode" class="input-modern" style="width: 100%; box-sizing: border-box; text-transform: uppercase;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                <button type="button" onclick="closeEditDepartmentModal()" class="btn-cancel" style="padding: 9px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #475569; font-weight: 600; cursor: pointer;"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-primary-toggle" style="padding: 9px 20px; border-radius: 8px; border: none; background: #0e2a86; color: #ffffff; font-weight: 700; cursor: pointer;">💾 <?= is_khmer() ? 'រក្សាទុកការកែប្រែ' : 'Save Changes' ?></button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
function toggleSidebar() {
    document.querySelector('.app-sidebar').classList.toggle('sidebar-collapsed');
}

function filterEntityTable() {
    const input = document.getElementById('entitySearch');
    if (!input) return;
    const val = input.value.toLowerCase();
    const rows = document.querySelectorAll('#entityTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
}

function openEditEntityModal(ent) {
    if (!ent) return;
    document.getElementById('editEntityId').value = ent.id || '';
    document.getElementById('editEntityNameKm').value = ent.name_km || '';
    document.getElementById('editEntityNameEn').value = ent.name_en || '';
    document.getElementById('editEntityCode').value = ent.code || '';
    const lvl = ent.unit_level || 'national';
    document.getElementById('editEntityUnitLevel').value = lvl;
    document.getElementById('editEntityModal').style.display = 'flex';
}

function closeEditEntityModal() {
    const modal = document.getElementById('editEntityModal');
    if (modal) modal.style.display = 'none';
}

function filterDepartmentTable() {
    const input = document.getElementById('deptSearch');
    if (!input) return;
    const val = input.value.toLowerCase();
    const rows = document.querySelectorAll('#departmentTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
}

function openEditDepartmentModal(dept) {
    if (!dept) return;
    document.getElementById('editDeptId').value = dept.id || '';
    document.getElementById('editDeptEntityId').value = dept.entity_id || '';
    document.getElementById('editDeptNameKm').value = dept.name_km || '';
    document.getElementById('editDeptNameEn').value = dept.name_en || '';
    document.getElementById('editDeptCode').value = dept.code || '';
    const lvl = dept.unit_level || 'national';
    document.getElementById('editDeptUnitLevel').value = lvl;
    document.getElementById('editDepartmentModal').style.display = 'flex';
}

function closeEditDepartmentModal() {
    const modal = document.getElementById('editDepartmentModal');
    if (modal) modal.style.display = 'none';
}

function filterUserTable() {
    const searchInput = document.getElementById('userSearch');
    const branchFilter = document.getElementById('branchFilter');
    const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const branchVal = branchFilter ? branchFilter.value.trim() : '';

    const rows = document.querySelectorAll('#directoryTable tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        if (row.id === 'noResultsRow') return;

        const rowText = row.innerText.toLowerCase();
        const rowBranch = row.getAttribute('data-branch') || '';

        const matchesSearch = !searchVal || rowText.includes(searchVal);
        const matchesBranch = !branchVal || rowBranch === branchVal;

        if (matchesSearch && matchesBranch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noResultsRow = document.getElementById('noResultsRow');
    if (noResultsRow) {
        noResultsRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
}

function openCreateUserModal() {
    const m = document.getElementById('createUserModal');
    if (m) m.style.display = 'flex';
}

function closeCreateUserModal() {
    const m = document.getElementById('createUserModal');
    if (m) m.style.display = 'none';
}

function openEditUserModal(user) {
    if (!user) return;
    document.getElementById('editUserId').value = user.id || '';
    document.getElementById('editFullName').value = user.full_name || '';
    document.getElementById('editUsername').value = user.username || '';
    document.getElementById('editEmail').value = user.email || '';
    document.getElementById('editBranch').value = user.branch || 'កំពង់ផែស្វយ័តភ្នំពេញ';
    document.getElementById('editRole').value = user.role || 'user';
    document.getElementById('editNewPassword').value = '';
    
    const m = document.getElementById('editUserModal');
    if (m) m.style.display = 'flex';
}

function closeEditUserModal() {
    const m = document.getElementById('editUserModal');
    if (m) m.style.display = 'none';
}

// Close modals when clicking backdrop
window.addEventListener('click', function(e) {
    const createM = document.getElementById('createUserModal');
    const editM = document.getElementById('editUserModal');
    if (e.target === createM) closeCreateUserModal();
    if (e.target === editM) closeEditUserModal();
});

function exportToCsv() {
    let csv = [];
    const table = document.getElementById('directoryTable');
    if (!table) return;
    const rows = table.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].id === 'noResultsRow') continue;
        if (rows[i].style.display === 'none') continue;
        
        let row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            // Skip the actions column
            if (j === cols.length - 1) continue;
            let text = cols[j].innerText.replace(/"/g, '""').replace(/\r?\n|\r/g, ' ').trim();
            row.push('"' + text + '"');
        }
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    }
    const blob = new Blob(["\uFEFF" + csv.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ppap_users_directory.csv';
    a.click();
}
</script>
</body>
</html>
