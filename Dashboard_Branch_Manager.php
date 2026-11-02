<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/tab_views.php';

// Enforce Branch Manager access (branch_manager, admin, or super_admin)
require_branch_manager_access();

$userId = $_SESSION['user_id'];
$pdo = get_db_connection();
$activeTab = $_GET['tab'] ?? 'home';

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

    $ins = $pdo->prepare('INSERT INTO departments (name_km, name_en, code, entity_id, created_by) VALUES (:km, :en, :code, :ent, :uid)');
    $ins->execute([
        'km'   => $nameKm,
        'en'   => !empty($nameEn) ? $nameEn : null,
        'code' => !empty($code) ? $code : null,
        'ent'  => $entityId,
        'uid'  => $userId
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

// Current logged in manager data
$curStmt = $pdo->prepare('SELECT id, full_name, username, email, role, branch, created_at FROM users WHERE id = :id LIMIT 1');
$curStmt->execute(['id' => $userId]);
$currentUser = $curStmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$myBranch = $currentUser['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ';

// Handle Branch Manager POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && in_array($_POST['action'], ['save_evaluation', 'delete_evaluation'])) {
        process_evaluation_request($pdo, $currentUser, 'Dashboard_Branch_Manager.php');
    }

    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', is_khmer() ? 'លេខកូដសុវត្ថិភាពមិនត្រឹមត្រូវ។ សូមព្យាយាមម្តងទៀត។' : 'Invalid security token.');
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
            $userMatchedEntity = resolve_branch_entity($pdo, $myBranch);
            if ($userMatchedEntity) {
                $entity = $userMatchedEntity['name_km'];
                $chkDept = $pdo->prepare('SELECT id FROM departments WHERE entity_id = :eid AND (name_km = :d1 OR name_en = :d2) LIMIT 1');
                $chkDept->execute(['eid' => $userMatchedEntity['id'], 'd1' => $department, 'd2' => $department]);
                if (!$chkDept->fetch()) {
                    set_flash('danger', is_khmer() ? 'នាយកដ្ឋានដែលបានជ្រើសរើសមិនស្ថិតនៅក្រោមអង្គភាព/សាខារបស់អ្នកទេ!' : 'The selected department does not belong to your entity/branch.');
                    header('Location: Dashboard_Branch_Manager.php?tab=activities&view=create');
                    exit;
                }
            }

            if (empty($entity) || empty($department) || empty($description) || empty($target) || empty($start_quarter) || empty($end_quarter) || empty($activity_type) || empty($responsible_person) || empty($implementer)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញរាល់ព័ត៌មានចាំបាច់ (មានសញ្ញា *)' : 'Please fill all required fields.');
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
                    'br'     => $myBranch,
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
                    set_flash('success', is_khmer() ? 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកជោគជ័យ! សូមបន្តបន្ថែមថ្មីមួយទៀត។' : 'New activity saved successfully! Continue adding another.');
                    header('Location: Dashboard_Branch_Manager.php?tab=activities&view=create');
                    exit;
                } else {
                    set_flash('success', is_khmer() ? 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកដោយជោគជ័យ!' : 'New activity registered successfully!');
                    header('Location: Dashboard_Branch_Manager.php?tab=activities&view=list');
                    exit;
                }
            }
        } elseif ($action === 'edit_activity') {
            // Branch Manager has permission to EDIT activities!
            $actId = (int)($_POST['activity_id'] ?? 0);
            $year = (int)($_POST['year'] ?? 2026);
            $entity = trim($_POST['entity'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target = trim($_POST['target'] ?? '');
            $start_quarter = trim($_POST['start_quarter'] ?? '');
            $end_quarter = trim($_POST['end_quarter'] ?? '');
            $activity_type = trim($_POST['activity_type'] ?? '');
            $responsible_person = trim($_POST['responsible_person'] ?? '');
            $implementer = trim($_POST['implementer'] ?? '');
            $kpi_1 = trim($_POST['kpi_1'] ?? '');
            $kpi_2 = trim($_POST['kpi_2'] ?? '');
            $kpi_3 = trim($_POST['kpi_3'] ?? '');

            if ($actId <= 0 || empty($entity) || empty($department) || empty($description) || empty($target)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញព័ត៌មានចាំបាច់ដើម្បីកែប្រែសកម្មភាព!' : 'Please fill all required fields.');
            } else {
                // Strict check: verify target activity belongs to this branch manager's branch
                $chk = $pdo->prepare('
                    SELECT a.id, a.branch as act_branch, u.branch as user_branch 
                    FROM activities a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE a.id = :id 
                    LIMIT 1
                ');
                $chk->execute(['id' => $actId]);
                $targetAct = $chk->fetch();

                if (!$targetAct) {
                    set_flash('danger', is_khmer() ? 'រកមិនឃើញសកម្មភាពការងារនេះទេ!' : 'Activity not found.');
                } else {
                    $actBranch = !empty($targetAct['act_branch']) ? $targetAct['act_branch'] : ($targetAct['user_branch'] ?? '');
                    if ($actBranch !== $myBranch) {
                        set_flash('danger', is_khmer() ? 'ប្រធានសាខាអាចកែប្រែបានតែសកម្មភាពរបស់ users ស្ថិតក្រោមសាខាខ្លួនឯង (' . htmlspecialchars($myBranch) . ') ប៉ុណ្ណោះ មិនអាចកែប្រែឆ្លងសាខាឡើយ!' : 'Access denied. You can only edit activities under your own branch (' . htmlspecialchars($myBranch) . ').');
                    } else {
                        $userMatchedEntity = resolve_branch_entity($pdo, $myBranch);
                        if ($userMatchedEntity) {
                            $entity = $userMatchedEntity['name_km'];
                            $chkDept = $pdo->prepare('SELECT id FROM departments WHERE entity_id = :eid AND (name_km = :d1 OR name_en = :d2) LIMIT 1');
                            $chkDept->execute(['eid' => $userMatchedEntity['id'], 'd1' => $department, 'd2' => $department]);
                            if (!$chkDept->fetch()) {
                                set_flash('danger', is_khmer() ? 'នាយកដ្ឋានដែលបានជ្រើសរើសមិនស្ថិតនៅក្រោមអង្គភាព/សាខារបស់អ្នកទេ!' : 'The selected department does not belong to your entity/branch.');
                                header('Location: Dashboard_Branch_Manager.php?tab=' . urlencode($activeTab));
                                exit;
                            }
                        }

                        $upd = $pdo->prepare('
                            UPDATE activities SET
                                year = :yr,
                                entity = :ent,
                                department = :dept,
                                description = :desc,
                                target = :tgt,
                                start_quarter = :sq,
                                end_quarter = :eq,
                                activity_type = :act_t,
                                activity_subtype = :act_st,
                                responsible_person = :resp,
                                implementer = :imp,
                                kpi_1 = :k1,
                                kpi_2 = :k2,
                                kpi_3 = :k3
                            WHERE id = :id
                        ');
                        $upd->execute([
                            'yr'     => $year,
                            'ent'    => $entity,
                            'dept'   => $department,
                            'desc'   => $description,
                            'tgt'    => $target,
                            'sq'     => $start_quarter,
                            'eq'     => $end_quarter,
                            'act_t'  => $activity_type,
                            'act_st' => $activity_subtype,
                            'resp'   => $responsible_person,
                            'imp'    => $implementer,
                            'k1'     => $kpi_1,
                            'k2'     => $kpi_2,
                            'k3'     => $kpi_3,
                            'id'     => $actId
                        ]);
                        set_flash('success', is_khmer() ? 'ព័ត៌មានសកម្មភាពការងារត្រូវបានកែប្រែដោយជោគជ័យ!' : 'Activity updated successfully!');
                    }
                }
            }
            header('Location: Dashboard_Branch_Manager.php?tab=' . urlencode($activeTab));
            exit;
        } elseif ($action === 'approve_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            if ($actId > 0) {
                // Strict check: Branch Manager can ONLY approve activities for users/activities belonging to their OWN branch
                $chk = $pdo->prepare('
                    SELECT a.id, a.branch as act_branch, u.branch as user_branch, u.full_name as author_name 
                    FROM activities a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE a.id = :id 
                    LIMIT 1
                ');
                $chk->execute(['id' => $actId]);
                $targetAct = $chk->fetch();

                if (!$targetAct) {
                    set_flash('danger', is_khmer() ? 'រកមិនឃើញសកម្មភាពការងារនេះទេ!' : 'Activity not found.');
                } else {
                    $actBranch = !empty($targetAct['act_branch']) ? $targetAct['act_branch'] : ($targetAct['user_branch'] ?? '');
                    if ($actBranch !== $myBranch) {
                        set_flash('danger', is_khmer() ? 'ប្រធានសាខាអាចអនុម័តបានតែ users ស្ថិតក្រោមសាខាខ្លួនឯង (' . htmlspecialchars($myBranch) . ') ប៉ុណ្ណោះ មិនមានសិទ្ធិអនុម័តឆ្លងសាខាឡើយ!' : 'Access denied. Branch Managers can only approve activities for users under their own branch (' . htmlspecialchars($myBranch) . ').');
                    } else {
                        $approverName = $currentUser['full_name'] . ' (' . (is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager') . ')';
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
                        set_flash('success', is_khmer() ? 'សកម្មភាពត្រូវបានអនុម័តដោយជោគជ័យ! ✅' : 'Activity has been approved successfully! ✅');
                    }
                }
            }
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_Branch_Manager.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'unapprove_activity') {
            // Revoke approval feature is disabled per policy: Activities can be edited but cannot be revoked
            set_flash('danger', is_khmer() ? 'មុខងារដកការអនុម័តសកម្មភាពត្រូវបានបិទ (មិនអាចដកការអនុម័តវិញបានឡើយ ប៉ុន្តែលោកអ្នកអាចធ្វើការកែប្រែសកម្មភាពបាន)!' : 'Revoke approval feature is disabled. Activities can be edited, but not revoked.');
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_Branch_Manager.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'delete_activity') {
            // STRICT SAFETY: Branch Managers are NOT allowed to delete activities!
            set_flash('danger', is_khmer() ? 'ប្រធានសាខាមិនមានសិទ្ធិលុបសកម្មភាពឡើយ (អ្នកមានសិទ្ធិពិនិត្យមើល និងកែប្រែប៉ុណ្ណោះ)!' : 'Branch Managers cannot delete activities (View and Edit permissions only).');
            header('Location: Dashboard_Branch_Manager.php?tab=activities&view=list');
            exit;
        } elseif ($action === 'edit_user') {
            // Branch Manager can edit user contact info within their branch
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            $chkTarget = $pdo->prepare('SELECT id, role, branch FROM users WHERE id = :id LIMIT 1');
            $chkTarget->execute(['id' => $targetUserId]);
            $targetUser = $chkTarget->fetch();

            if (!$targetUser) {
                set_flash('danger', is_khmer() ? 'រកមិនឃើញគណនីបុគ្គលិកនេះទេ!' : 'User not found.');
            } elseif ($targetUser['branch'] !== $myBranch && !is_admin()) {
                set_flash('danger', is_khmer() ? 'លោកអ្នកអាចកែប្រែព័ត៌មានបុគ្គលិកនៅក្នុងសាខារបស់អ្នកប៉ុណ្ណោះ!' : 'You can only edit personnel belonging to your branch.');
            } elseif (in_array($targetUser['role'], ['admin', 'super_admin'], true) && $targetUserId !== $userId) {
                set_flash('danger', is_khmer() ? 'លោកអ្នកមិនមានសិទ្ធិកែប្រែគណនីថ្នាក់ដឹកនាំឡើយ!' : 'Unauthorized operation.');
            } elseif (empty($fullName) || empty($username) || empty($email)) {
                set_flash('danger', is_khmer() ? 'សូមបំពេញឈ្មោះពេញ ឈ្មោះគណនី និងអ៊ីមែល!' : 'Full name, username, and email are required.');
            } else {
                $chkUniq = $pdo->prepare('SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id LIMIT 1');
                $chkUniq->execute(['u' => $username, 'e' => $email, 'id' => $targetUserId]);
                if ($chkUniq->fetch()) {
                    set_flash('danger', is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់ ឬ អ៊ីមែលនេះត្រូវបានប្រើប្រាស់ដោយគណនីផ្សេងរួចហើយ!' : 'Username or email already taken.');
                } else {
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់ថ្មីត្រូវមានយ៉ាងតិច ៦ តួអក្សរ!' : 'Password must be at least 6 characters.');
                            header('Location: Dashboard_Branch_Manager.php?tab=home');
                            exit;
                        }
                        $pwdHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET full_name = :fn, username = :un, email = :em, password = :pwd WHERE id = :id');
                        $upd->execute(['fn' => $fullName, 'un' => $username, 'em' => $email, 'pwd' => $pwdHash, 'id' => $targetUserId]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET full_name = :fn, username = :un, email = :em WHERE id = :id');
                        $upd->execute(['fn' => $fullName, 'un' => $username, 'em' => $email, 'id' => $targetUserId]);
                    }

                    if ($targetUserId === $userId) {
                        $_SESSION['full_name'] = $fullName;
                        $_SESSION['username']  = $username;
                        $_SESSION['email']     = $email;
                    }
                    set_flash('success', is_khmer() ? "បានកែប្រែព័ត៌មានបុគ្គលិក '{$username}' ដោយជោគជ័យ!" : "Staff information updated successfully!");
                }
            }
            header('Location: Dashboard_Branch_Manager.php?tab=home');
            exit;
        } elseif ($action === 'delete_user') {
            // STRICT SAFETY: Branch Managers are NOT allowed to delete users!
            set_flash('danger', is_khmer() ? 'ប្រធានសាខាមិនមានសិទ្ធិលុបគណនីបុគ្គលិកឡើយ!' : 'Branch Managers cannot delete user accounts.');
            header('Location: Dashboard_Branch_Manager.php?tab=home');
            exit;
        } elseif ($action === 'change_password') {
            $currPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            $userPassStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $userPassStmt->execute(['id' => $userId]);
            $uRow = $userPassStmt->fetch();

            if (!password_verify($currPass, $uRow['password'])) {
                set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវទេ!' : 'Current password is incorrect.');
            } elseif (strlen($newPass) < 6) {
                set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់ថ្មីត្រូវមានយ៉ាងតិច ៦ តួអក្សរ!' : 'New password must be at least 6 characters.');
            } elseif ($newPass !== $confirmPass) {
                set_flash('danger', is_khmer() ? 'ពាក្យសម្ងាត់ផ្ទៀងផ្ទាត់មិនត្រូវគ្នាទេ!' : 'Passwords do not match.');
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $updStmt = $pdo->prepare('UPDATE users SET password = :pwd WHERE id = :id');
                $updStmt->execute(['pwd' => $hash, 'id' => $userId]);
                set_flash('success', is_khmer() ? 'ពាក្យសម្ងាត់ត្រូវបានផ្លាស់ប្តូរដោយជោគជ័យ!' : 'Password updated successfully!');
            }
            header('Location: Dashboard_Branch_Manager.php?tab=home');
            exit;
        }
    }

    header('Location: Dashboard_Branch_Manager.php?tab=' . urlencode($activeTab));
    exit;
}

// Fetch staff belonging to THIS specific branch
$staffStmt = $pdo->prepare('SELECT id, full_name, username, email, role, branch, created_at FROM users WHERE branch = :br ORDER BY id ASC');
$staffStmt->execute(['br' => $myBranch]);
$branchStaff = $staffStmt->fetchAll();

// Fetch activities related to THIS specific branch
$actStmt = $pdo->prepare('
    SELECT a.*, u.full_name as author_name, u.username as author_username, u.branch as user_branch 
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE a.branch = :br OR u.branch = :br2 
    ORDER BY a.id DESC
');
$actStmt->execute(['br' => $myBranch, 'br2' => $myBranch]);
$branchActivities = $actStmt->fetchAll();

// For Branch Manager, user context is strictly limited to staff within their own branch
$allUsers = $branchStaff;

$stats = [
    'total'          => count($branchStaff),
    'branch_staff'   => count($branchStaff),
    'branch_acts'    => count($branchActivities),
    'in_progress'    => 0,
    'super_admin'    => 0,
    'admin'          => 0,
    'branch_manager' => 0,
    'user'           => 0
];
foreach ($branchStaff as $u) {
    if (isset($stats[$u['role']])) $stats[$u['role']]++;
}
foreach ($branchActivities as $a) {
    if (!empty($a['end_quarter'])) {
        $stats['in_progress']++;
    }
}

$flash = get_flash();
$csrfToken = generate_csrf_token();

// Extract initials
$names = explode(' ', trim($currentUser['full_name']));
$initials = '';
foreach ($names as $n) {
    if (!empty($n)) $initials .= strtoupper($n[0]);
}
$initials = substr($initials, 0, 2) ?: 'BM';
$joinedDate = date('F j, Y', strtotime($currentUser['created_at']));
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Manager Portal - PPAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/dashboards.css?v=3">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=3">
</head>
<body class="dashboard-body branch-manager-theme">

<div class="app-layout">
    <!-- 1. Left Sidebar -->
    <?php render_sidebar($activeTab, $currentUser, 'Dashboard_Branch_Manager.php'); ?>

    <!-- 2. Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="dashboard-navbar branch-manager-navbar">
            <div class="nav-brand">
                <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar" style="background: transparent; border: none; color: #ffffff; cursor: pointer; display: flex; align-items: center; margin-right: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="brand-badge-icon branch-manager-icon" style="background: #ffffff; border-radius: 50%; padding: 2px; border: 2px solid #d97706; width: 40px; height: 40px; min-width: 40px; min-height: 40px; max-width: 40px; max-height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.18); overflow: hidden; box-sizing: border-box; flex-shrink: 0;">
                    <img src="assets/images/logo.png" alt="MPWT Logo" style="width: 36px; height: 36px; max-width: 36px; max-height: 36px; object-fit: contain; border-radius: 50%; display: block;">
                </div>
                <div>
                    <div class="brand-title">PPAP - <?= htmlspecialchars($myBranch) ?></div>
                    <div class="brand-sub"><?= t('status_branch_manager') ?></div>
                </div>
            </div>

            <div class="nav-actions">
                <!-- Language Switcher Pill -->
                <?php render_language_switcher(); ?>

                <span class="system-status-pill branch-manager-status-pill">
                    <span class="status-dot"></span> <?= is_khmer() ? 'ប្រតិបត្តិការសាខា' : 'BRANCH ONLINE' ?>
                </span>

                <div class="user-pill">
                    <div class="user-pill-avatar" style="background: linear-gradient(135deg, #0891b2, #0284c7);"><?= htmlspecialchars($initials) ?></div>
                    <div class="user-pill-info">
                        <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                        <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 2px;">
                            @<?= htmlspecialchars($currentUser['username']) ?>
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
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'home'): ?>
                <!-- Monthly Report Progress Panel (វឌ្ឍនភាពការងារទទួលរបាយការណ៍) -->
                <?php render_monthly_report_progress($pdo); ?>

                <!-- Metrics Grid -->
                <div class="metrics-grid" style="margin-bottom: 28px;">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: #e0f2fe; color: #0284c7;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= count($branchStaff) ?></div>
                            <div class="metric-label"><?= t('stat_branch_members') ?></div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: #ecfeff; color: #0891b2;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= count($branchActivities) ?></div>
                            <div class="metric-label"><?= t('stat_branch_activities') ?></div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: #dcfce7; color: #16a34a;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['in_progress'] ?></div>
                            <div class="metric-label"><?= t('stat_branch_in_progress') ?></div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: #fef3c7; color: #d97706;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="metric-value" style="font-size: 1.15rem; color: #b45309;"><?= is_khmer() ? 'ពិនិត្យ & កែប្រែ' : 'View & Edit' ?></div>
                            <div class="metric-label"><?= t('stat_branch_permission') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Panel 1: Branch Activities Review & Edit -->
                <div class="panel-card" style="margin-bottom: 28px;">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2><?= t('branch_activities_title') ?> (<?= count($branchActivities) ?>)</h2>
                            <p><?= t('branch_activities_sub') ?> &bull; <span style="color: #0284c7; font-weight: 600;">សិទ្ធិប្រតិបត្តិការ៖ ពិនិត្យមើល និងកែប្រែ (មិនអាចលុប)</span></p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="bmActSearch" class="input-modern" placeholder="<?= is_khmer() ? 'ស្វែងរកសកម្មភាព...' : 'Search activity...' ?>" onkeyup="filterBmActTable()" style="padding: 7px 12px; font-size: 0.85rem; width: 180px; border-radius: 8px;">
                            <a href="print_activity_report.php" target="_blank" class="btn-action-view" style="padding: 7px 11px; font-size: 0.82rem; text-decoration: none; border-radius: 8px;" title="បោះពុម្ពរបាយការណ៍ផ្លូវការ">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                <span><?= is_khmer() ? 'បោះពុម្ព' : 'Print' ?></span>
                            </a>
                            <a href="export_activities_excel.php" class="btn-action-view" style="padding: 7px 11px; font-size: 0.82rem; background: #ecfdf5; color: #047857; border-color: #a7f3d0; text-decoration: none; border-radius: 8px;" title="ទាញយកជា Excel (.xlsx)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span><?= is_khmer() ? 'Excel' : 'Excel' ?></span>
                            </a>
                            <a href="Dashboard_Branch_Manager.php?tab=activities&view=create" class="btn-primary-toggle" style="padding: 7px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; text-decoration: none; background: #0891b2; border-color: #0891b2;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span><?= t('btn_create_activity') ?></span>
                            </a>
                        </div>
                    </div>

                    <?php if (empty($branchActivities)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                            <div style="font-size: 2.2rem; margin-bottom: 8px;">⚓</div>
                            <p style="font-weight: 600; font-size: 0.95rem; color: #334155;"><?= is_khmer() ? 'មិនទាន់មានសកម្មភាពសម្រាប់សាខានេះនៅឡើយទេ' : 'No activities recorded for this branch yet.' ?></p>
                            <a href="Dashboard_Branch_Manager.php?tab=activities&view=create" class="btn-primary-toggle" style="margin-top: 10px; display: inline-flex; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 0.86rem;"><?= t('btn_create_activity') ?></a>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="dashboard-table" id="bmActTable">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;"><?= t('th_year') ?></th>
                                        <th><?= t('th_entity_dept') ?></th>
                                        <th><?= t('th_description') ?></th>
                                        <th><?= t('th_target') ?></th>
                                        <th><?= t('th_period') ?></th>
                                        <th><?= t('th_responsible') ?></th>
                                        <th style="text-align: center;"><?= is_khmer() ? 'បានដាក់ស្នើ' : 'Submitted' ?></th>
                                        <th style="text-align: center;"><?= is_khmer() ? 'អនុម័ត' : 'Approval' ?></th>
                                        <th style="text-align: right;"><?= t('th_actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchActivities as $act): ?>
                                        <tr>
                                            <td><span class="badge-id"><?= htmlspecialchars($act['year']) ?></span></td>
                                            <td>
                                                <strong style="color: #0f172a;"><?= htmlspecialchars($act['entity']) ?></strong>
                                                <div class="sub-text"><?= htmlspecialchars($act['department']) ?></div>
                                            </td>
                                            <td style="max-width: 250px;">
                                                <div style="font-weight: 500; color: #0f172a;"><?= htmlspecialchars($act['description']) ?></div>
                                                <div style="font-size: 0.76rem; color: #0891b2; margin-top: 2px;"><?= htmlspecialchars($act['activity_type']) ?></div>
                                            </td>
                                            <td><span style="font-weight: 600; color: #059669;"><?= htmlspecialchars($act['target']) ?></span></td>
                                            <td>
                                                <span style="font-size: 0.8rem; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                                                    <?= htmlspecialchars($act['start_quarter']) ?> &rarr; <?= htmlspecialchars($act['end_quarter']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; font-size: 0.88rem;"><?= htmlspecialchars($act['responsible_person']) ?></div>
                                                <div class="sub-text"><?= t('th_imp_prefix') ?><?= htmlspecialchars($act['implementer']) ?></div>
                                            </td>
                                            <!-- Column 1: បានដាក់ស្នើ (Submitted) -->
                                            <td style="text-align: center; white-space: nowrap;">
                                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; border-radius: 6px; background: #dcfce7; color: #15803d; font-size: 0.78rem; font-weight: 600; border: 1px solid #bbf7d0;">
                                                    <span style="color: #16a34a; font-weight: 800; font-size: 0.95rem;">✓</span>
                                                    <span><?= is_khmer() ? 'បានដាក់ស្នើ' : 'Submitted' ?></span>
                                                </span>
                                            </td>
                                            <!-- Column 2: អនុម័ត (Approval) -->
                                            <td style="text-align: center; white-space: nowrap;">
                                                <?php if (!empty($act['is_approved'])): ?>
                                                    <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 2px;">
                                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; border-radius: 6px; background: #dcfce7; color: #15803d; font-size: 0.78rem; font-weight: 600; border: 1px solid #bbf7d0;">
                                                            <span style="color: #16a34a; font-weight: 800; font-size: 0.95rem;">✓</span>
                                                            <span><?= is_khmer() ? 'បានអនុម័ត' : 'Approved' ?></span>
                                                        </span>
                                                        <span style="font-size: 0.70rem; color: #0284c7;"><?= htmlspecialchars($act['approved_by_name'] ?? 'Branch Manager') ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 4px;">
                                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; background: #fef3c7; color: #b45309; font-size: 0.76rem; font-weight: 600; border: 1px solid #fde68a;">
                                                            <span>⏳</span>
                                                            <span><?= is_khmer() ? 'រង់ចាំអនុម័ត' : 'Pending' ?></span>
                                                        </span>
                                                        <form action="Dashboard_Branch_Manager.php?tab=home" method="POST" style="margin: 0; display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="action" value="approve_activity">
                                                            <input type="hidden" name="activity_id" value="<?= $act['id'] ?>">
                                                            <button type="submit" style="background: #10b981; color: #ffffff; border: none; padding: 4px 10px; border-radius: 6px; font-size: 0.76rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.06);">
                                                                <span>✓</span>
                                                                <span><?= is_khmer() ? 'អនុម័ត' : 'Approve' ?></span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <!-- View Details -->
                                                <button type="button" class="btn-action-view" onclick='viewActivityDetails(<?= json_encode($act, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="<?= t('btn_view_details') ?>" style="padding: 5px 8px; font-size: 0.78rem; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <span><?= t('btn_view_details') ?></span>
                                                </button>

                                                <!-- Edit Activity Button -->
                                                <button type="button" class="btn-action-edit" onclick='openEditActivityModal(<?= json_encode($act, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="<?= t('btn_edit_activity') ?>" style="padding: 5px 8px; font-size: 0.78rem; margin-left: 4px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    <span><?= is_khmer() ? 'កែប្រែ' : 'Edit' ?></span>
                                                </button>
                                                <!-- NO DELETE BUTTON HERE: Branch Manager has view & edit rights only -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Panel 2: Branch Staff Directory (View & Edit Info, No Delete) -->
                <div class="panel-card" style="margin-bottom: 28px;">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2><?= t('branch_staff_title') ?> (<?= count($branchStaff) ?>)</h2>
                            <p><?= t('branch_staff_sub') ?> &bull; <span class="branch-pill">🏢 <?= htmlspecialchars($myBranch) ?></span></p>
                        </div>
                        <input type="text" id="bmStaffSearch" class="input-modern" placeholder="<?= is_khmer() ? 'ស្វែងរកបុគ្គលិក...' : 'Search staff...' ?>" onkeyup="filterBmStaffTable()" style="padding: 7px 12px; font-size: 0.85rem; width: 220px; border-radius: 8px;">
                    </div>

                    <div class="table-container">
                        <table class="dashboard-table" id="bmStaffTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th><?= t('th_username') ?></th>
                                    <th><?= t('th_email') ?></th>
                                    <th><?= t('th_assigned_role') ?></th>
                                    <th><?= t('th_reg_date') ?></th>
                                    <th style="text-align: right;"><?= t('th_actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branchStaff as $st): ?>
                                    <tr>
                                        <td><span class="badge-id">#<?= $st['id'] ?></span></td>
                                        <td>
                                            <strong style="color: #0f172a;"><?= htmlspecialchars($st['full_name']) ?></strong>
                                            <div class="sub-text">@<?= htmlspecialchars($st['username']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($st['email']) ?></td>
                                        <td><?= get_role_badge_html($st['role']) ?></td>
                                        <td><?= date('M j, Y', strtotime($st['created_at'])) ?></td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <button type="button" class="btn-action-edit" onclick='openBmEditStaffModal(<?= json_encode([
                                                "id"        => $st["id"],
                                                "full_name" => $st["full_name"],
                                                "username"  => $st["username"],
                                                "email"     => $st["email"]
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="<?= t('btn_update_user') ?>" style="padding: 5px 9px; font-size: 0.78rem;">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span><?= is_khmer() ? 'កែប្រែ' : 'Edit' ?></span>
                                            </button>
                                            <!-- NO DELETE BUTTON HERE: Branch Manager cannot delete users -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Render Content for Tab 2, 3, 4, or 5 -->
                <?php render_tab_content($activeTab, 'branch_manager', $allUsers, $stats, $currentUser, $csrfToken, $branchActivities); ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal for Branch Manager Editing Staff Contact Info (No Delete Option) -->
<div id="bmEditStaffModal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.25rem;">✏️</span>
                <h3 style="margin: 0; font-size: 1.12rem; font-weight: 700; color: #0f172a;"><?= t('modal_edit_user_title') ?></h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeBmEditStaffModal()">&times;</button>
        </div>
        <form action="Dashboard_Branch_Manager.php?tab=home" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="target_user_id" id="bmEditStaffId" value="">
            
            <div class="modal-body" style="padding: 20px 24px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះពេញ' : 'Full Name' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" id="bmEditStaffFullName" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់' : 'Username' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="username" id="bmEditStaffUsername" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'អាសយដ្ឋានអ៊ីមែល' : 'Email Address' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" id="bmEditStaffEmail" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_new_password_optional') ?>
                    </label>
                    <input type="password" name="new_password" class="input-modern" placeholder="<?= is_khmer() ? 'បញ្ចូលប្រសិនបើចង់ផ្លាស់ប្តូរ (យ៉ាងតិច ៦ តួ)' : 'Leave blank to keep unchanged' ?>" minlength="6" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cancel" onclick="closeBmEditStaffModal()"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-save-modal" style="background: #0891b2;"><?= t('btn_update_user') ?></button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
function toggleSidebar() {
    document.querySelector('.app-sidebar').classList.toggle('sidebar-collapsed');
}

function filterBmActTable() {
    const input = document.getElementById('bmActSearch');
    if (!input) return;
    const val = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#bmActTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
}

function filterBmStaffTable() {
    const input = document.getElementById('bmStaffSearch');
    if (!input) return;
    const val = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#bmStaffTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
}

function openBmEditStaffModal(user) {
    if (!user) return;
    document.getElementById('bmEditStaffId').value = user.id || '';
    document.getElementById('bmEditStaffFullName').value = user.full_name || '';
    document.getElementById('bmEditStaffUsername').value = user.username || '';
    document.getElementById('bmEditStaffEmail').value = user.email || '';
    const m = document.getElementById('bmEditStaffModal');
    if (m) m.style.display = 'flex';
}

function closeBmEditStaffModal() {
    const m = document.getElementById('bmEditStaffModal');
    if (m) m.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const m = document.getElementById('bmEditStaffModal');
    if (e.target === m) closeBmEditStaffModal();
});
</script>
</body>
</html>

