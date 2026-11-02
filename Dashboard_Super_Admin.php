<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/tab_views.php';

// Enforce Super Admin access
require_super_admin();

$userId = $_SESSION['user_id'];
$pdo = get_db_connection();
$activeTab = $_GET['tab'] ?? 'home';

// Current logged in Super Admin data
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
    process_evaluation_request($pdo, $currentUser, 'Dashboard_Super_Admin.php');
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

// Handle Super Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid security token.');
    } else {
        if ($action === 'change_role') {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $newRole = $_POST['new_role'] ?? 'user';

            if (in_array($newRole, ['super_admin', 'admin', 'branch_manager', 'user'], true)) {
                if ($targetUserId === $userId && $newRole !== 'super_admin') {
                    set_flash('danger', 'You cannot demote your own Super Admin account.');
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                    $updateStmt->execute(['role' => $newRole, 'id' => $targetUserId]);
                    set_flash('success', 'User role successfully updated to ' . htmlspecialchars(get_role_label($newRole)) . '.');
                }
            }
        } elseif ($action === 'delete_user') {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);

            if ($targetUserId === $userId) {
                set_flash('danger', 'You cannot delete your own account while logged in.');
            } else {
                $delStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $delStmt->execute(['id' => $targetUserId]);
                set_flash('success', 'User has been successfully deleted from the database.');
            }
        } elseif ($action === 'add_user') {
            $newFullName = trim($_POST['full_name'] ?? '');
            $newUsername = trim($_POST['username'] ?? '');
            $newEmail = trim($_POST['email'] ?? '');
            $newPassword = $_POST['password'] ?? '';
            $newRole = $_POST['role'] ?? 'user';
            $branch = trim($_POST['branch'] ?? '');

            if (empty($newFullName) || empty($newUsername) || empty($newEmail) || empty($newPassword)) {
                set_flash('danger', 'All fields are required to create a user.');
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                set_flash('danger', 'Invalid email address provided.');
            } elseif (strlen($newPassword) < 6) {
                set_flash('danger', 'Password must be at least 6 characters.');
            } elseif (!in_array($newRole, ['super_admin', 'admin', 'branch_manager', 'user'], true)) {
                set_flash('danger', 'Invalid role selected.');
            } else {
                $check = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
                $check->execute(['u' => $newUsername, 'e' => $newEmail]);
                if ($check->fetch()) {
                    set_flash('danger', 'Username or email already exists.');
                } else {
                    $branch = !empty($branch) ? $branch : 'កំពង់ផែស្វយ័តភ្នំពេញ';
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role, branch) VALUES (:fn, :un, :em, :pwd, :rl, :br)');
                    $ins->execute([
                        'fn'  => $newFullName,
                        'un'  => $newUsername,
                        'em'  => $newEmail,
                        'pwd' => $hash,
                        'rl'  => $newRole,
                        'br'  => $branch
                    ]);
                    set_flash('success', "New user '{$newUsername}' ({$newRole}) created successfully!");
                }
            }
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
                set_flash('danger', 'User not found.');
            } elseif (empty($fullName) || empty($username) || empty($email)) {
                set_flash('danger', 'Full name, username, and email are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('danger', 'Invalid email address provided.');
            } elseif (!in_array($role, ['super_admin', 'admin', 'branch_manager', 'user'], true)) {
                set_flash('danger', 'Invalid role selected.');
            } elseif ($targetUserId === $userId && $role !== 'super_admin') {
                set_flash('danger', 'You cannot demote your own Super Admin account.');
            } else {
                $chkUniq = $pdo->prepare('SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id LIMIT 1');
                $chkUniq->execute(['u' => $username, 'e' => $email, 'id' => $targetUserId]);
                if ($chkUniq->fetch()) {
                    set_flash('danger', 'Username or email is already taken by another account.');
                } else {
                    $branch = !empty($branch) ? $branch : 'កំពង់ផែស្វយ័តភ្នំពេញ';

                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            set_flash('danger', 'New password must be at least 6 characters.');
                            header('Location: Dashboard_Super_Admin.php?tab=home');
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

                    set_flash('success', "User account '{$username}' updated successfully!");
                }
            }
            header('Location: Dashboard_Super_Admin.php?tab=home');
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_entity') {
            $entityId = (int)($_POST['entity_id'] ?? 0);
            if ($entityId > 0) {
                $del = $pdo->prepare('DELETE FROM entities WHERE id = :id');
                $del->execute(['id' => $entityId]);
                set_flash('success', is_khmer() ? 'អង្គភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Entity deleted successfully!');
            }
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($activeTab));
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
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
                    set_flash('danger', is_khmer() ? 'ឈ្មោះនាយកដ្ឋាននេះមានក្នុងប្រព័ន្ធរួចហើយ!' : 'Department already exists!');
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_department') {
            $deptId = (int)($_POST['department_id'] ?? 0);
            if ($deptId > 0) {
                $del = $pdo->prepare('DELETE FROM departments WHERE id = :id');
                $del->execute(['id' => $deptId]);
                set_flash('success', is_khmer() ? 'នាយកដ្ឋានត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Department deleted successfully!');
            }
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($activeTab));
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
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
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'delete_announcement') {
            $annId = (int)($_POST['announcement_id'] ?? 0);
            if ($annId > 0) {
                $del = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
                $del->execute(['id' => $annId]);
                set_flash('success', is_khmer() ? 'សេចក្តីជូនដំណឹងត្រូវបានលុបដោយជោគជ័យ!' : 'Announcement deleted successfully!');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
            exit;
        } elseif ($action === 'toggle_announcement_status') {
            $annId = (int)($_POST['announcement_id'] ?? 0);
            if ($annId > 0) {
                $upd = $pdo->prepare('UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $upd->execute(['id' => $annId]);
                set_flash('success', is_khmer() ? 'ស្ថានភាពសេចក្តីជូនដំណឹងត្រូវបានផ្លាស់ប្តូរ!' : 'Announcement status toggled successfully!');
            }
            $targetTab = $_POST['redirect_tab'] ?? 'notifications';
            header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($targetTab));
            exit;
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

            $userBranch = $currentUser['branch'] ?? '';
            if (empty($entity) && !empty($userBranch)) {
                $userMatchedEntity = resolve_branch_entity($pdo, $userBranch);
                if ($userMatchedEntity) {
                    $entity = $userMatchedEntity['name_km'];
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
                    set_flash('success', is_khmer() ? 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកជោគជ័យ! សូមបន្តបន្ថែមថ្មីមួយទៀត។' : 'Activity saved successfully! Continue adding another.');
                    header('Location: Dashboard_Super_Admin.php?tab=activities&view=create');
                    exit;
                } else {
                    set_flash('success', is_khmer() ? 'សកម្មភាពថ្មីត្រូវបានរក្សាទុកដោយជោគជ័យ!' : 'Activity saved successfully!');
                    header('Location: Dashboard_Super_Admin.php?tab=activities&view=list');
                    exit;
                }
            }
        } elseif ($action === 'delete_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            if ($actId > 0) {
                $chk = $pdo->prepare('SELECT is_approved FROM activities WHERE id = :id LIMIT 1');
                $chk->execute(['id' => $actId]);
                $actData = $chk->fetch();
                if ($actData && !empty($actData['is_approved'])) {
                    set_flash('danger', is_khmer() ? 'សកម្មភាពនេះមិនមានសិទ្ធិលុបចេញបានទេ ដោយសារត្រូវបានអនុម័តរួចហើយ!' : 'This activity cannot be deleted because it has already been approved!');
                    header('Location: Dashboard_Super_Admin.php?tab=activities');
                    exit;
                }
                $del = $pdo->prepare('DELETE FROM activities WHERE id = :id');
                $del->execute(['id' => $actId]);
                set_flash('success', is_khmer() ? 'សកម្មភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Activity deleted successfully!');
            }
            header('Location: Dashboard_Super_Admin.php?tab=activities');
            exit;
        } elseif ($action === 'approve_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            if ($actId > 0) {
                $approverName = $currentUser['full_name'] . ' (Super Admin)';
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
            $redirectUrl = 'Dashboard_Super_Admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'unapprove_activity') {
            set_flash('danger', is_khmer() ? 'មុខងារដកការអនុម័តសកម្មភាពត្រូវបានបិទ (មិនអាចដកការអនុម័តវិញបានឡើយ ប៉ុន្តែលោកអ្នកអាចធ្វើការកែប្រែសកម្មភាពបាន)!' : 'Revoke approval feature is disabled. Activities can be edited, but not revoked.');
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_Super_Admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
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
            header('Location: Dashboard_Super_Admin.php?tab=activities');
            exit;
        } elseif ($action === 'approve_activity') {
            $actId = (int)($_POST['activity_id'] ?? 0);
            if ($actId > 0) {
                $approverName = $currentUser['full_name'] . ' (Super Admin)';
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
            $redirectUrl = 'Dashboard_Super_Admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'unapprove_activity') {
            set_flash('danger', is_khmer() ? 'មុខងារដកការអនុម័តសកម្មភាពត្រូវបានបិទ (មិនអាចដកការអនុម័តវិញបានឡើយ ប៉ុន្តែលោកអ្នកអាចធ្វើការកែប្រែសកម្មភាពបាន)!' : 'Revoke approval feature is disabled. Activities can be edited, but not revoked.');
            $targetTab = $_POST['redirect_tab'] ?? $activeTab;
            $redirectUrl = 'Dashboard_Super_Admin.php?tab=' . urlencode($targetTab);
            if ($targetTab === 'activities' && !empty($_GET['view'])) {
                $redirectUrl .= '&view=' . urlencode($_GET['view']);
            }
            header('Location: ' . $redirectUrl);
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
                header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
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
                header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
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
                header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
                exit;
            } else {
                // Direct import
                $res = insert_activities_into_db($pdo, $mappedActivities, $userId, $currentUser['full_name']);
                unset($_SESSION['excel_preview_activities'], $_SESSION['excel_preview_stats']);
                if ($res['success']) {
                    set_flash('success', is_khmer() ? '🎉 បានបញ្ចូលទិន្នន័យសកម្មភាពចំនួន ' . $res['count'] . ' ពីឯកសារ Excel ចូលក្នុងប្រព័ន្ធដោយជោគជ័យ!' : 'Successfully imported ' . $res['count'] . ' activities from Excel!');
                    header('Location: Dashboard_Super_Admin.php?tab=activities&view=list');
                    exit;
                } else {
                    set_flash('danger', is_khmer() ? 'មានបញ្ហាក្នុងការបញ្ចូលទិន្នន័យទៅក្នុង Database: ' . implode(', ', $res['errors'] ?? []) : 'Import failed: ' . implode(', ', $res['errors'] ?? []));
                    header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
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
                    header('Location: Dashboard_Super_Admin.php?tab=activities&view=list');
                    exit;
                } else {
                    set_flash('danger', is_khmer() ? 'មានបញ្ហាក្នុងការបញ្ចូលទិន្នន័យទៅក្នុង Database!' : 'Import failed.');
                    header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
                    exit;
                }
            }
            header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
            exit;
        } elseif ($action === 'cancel_excel_preview') {
            unset($_SESSION['excel_preview_activities'], $_SESSION['excel_preview_stats']);
            set_flash('info', is_khmer() ? 'បានបោះបង់ការពិនិត្យទិន្នន័យរួចរាល់' : 'Preview cancelled.');
            header('Location: Dashboard_Super_Admin.php?tab=upload_excel');
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
            header('Location: Dashboard_Super_Admin.php?tab=activity_priority');
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
            header('Location: Dashboard_Super_Admin.php?tab=activity_priority');
            exit;
        } elseif ($action === 'delete_activity_priority') {
            $priorityId = (int)($_POST['priority_id'] ?? 0);
            if ($priorityId > 0) {
                $del = $pdo->prepare('DELETE FROM activity_priorities WHERE id = :id');
                $del->execute(['id' => $priorityId]);
                set_flash('success', is_khmer() ? 'សកម្មភាពអាទិភាពត្រូវបានលុបចេញពីប្រព័ន្ធដោយជោគជ័យ!' : 'Activity priority deleted successfully!');
            }
            header('Location: Dashboard_Super_Admin.php?tab=activity_priority');
            exit;
        }
    }

    header('Location: Dashboard_Super_Admin.php?tab=' . urlencode($activeTab));
    exit;
}

// Fetch all users and calculate system stats
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

// Fetch all registered entities & departments
$allEntities = get_all_entities($pdo);
$allDepartments = get_all_departments($pdo);

$stats = ['total' => count($allUsers), 'super_admin' => 0, 'admin' => 0, 'user' => 0, 'entities' => count($allEntities), 'departments' => count($allDepartments)];
foreach ($allUsers as $u) {
    if (isset($stats[$u['role']])) {
        $stats[$u['role']]++;
    }
}

// Current logged in Super Admin data
$curStmt = $pdo->prepare('SELECT id, full_name, username, email, created_at, role FROM users WHERE id = :id LIMIT 1');
$curStmt->execute(['id' => $userId]);
$currentUser = $curStmt->fetch();

// Fetch all activities for Activity management tabs
$actStmt = $pdo->query('SELECT a.*, u.full_name as author_name, u.username as author_username, u.branch as user_branch FROM activities a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC');
$superActivities = $actStmt ? $actStmt->fetchAll() : [];

$flash = get_flash();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard_Super_Admin - PPAP</title>
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
<body class="dashboard-body super-admin-theme">

<div class="app-layout">
    <!-- 1. Left Sidebar -->
    <?php render_sidebar($activeTab, $currentUser, 'Dashboard_Super_Admin.php'); ?>

    <!-- 2. Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="dashboard-topbar">
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
                        case 'home': default: echo t('page_super_home'); break;
                    }
                    ?>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Language Switcher Pill -->
                <?php render_language_switcher(); ?>

                <span class="system-status-pill">
                    <span class="status-dot"></span> <?= t('status_super') ?>
                </span>
                <div class="user-pill">
                    <div class="user-pill-avatar super-pill-avatar">SA</div>
                    <div class="user-pill-info">
                        <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                        <span class="role-tag-super"><?= t('role_super_admin') ?></span>
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
                        <div class="metric-icon metric-purple">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['total'] ?></div>
                            <div class="metric-label"><?= t('stat_total_accounts') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['super_admin'] ?></div>
                            <div class="metric-label"><?= t('stat_super_admins') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['admin'] ?></div>
                            <div class="metric-label"><?= t('stat_admins') ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon metric-green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <div>
                            <div class="metric-value"><?= $stats['user'] ?></div>
                            <div class="metric-label"><?= t('stat_normal_users') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Form Add New User Panel (Full-width, Spacious & Clean) -->
                <div class="panel-card" style="margin-bottom: 24px; width: 100%; box-sizing: border-box;">
                    <div class="panel-card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2 style="font-size: 1.18rem; color: #0f172a; display: flex; align-items: center; gap: 8px; margin: 0;">
                                <span style="font-size: 1.3rem;">👤</span>
                                <span><?= is_khmer() ? 'បង្កើតគណនីថ្មី (Create User)' : 'Create User' ?></span>
                            </h2>
                            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.88rem;"><?= is_khmer() ? 'បញ្ចូលគណនីថ្មី និងកំណត់សាខានិងតួនាទីក្នុងប្រព័ន្ធ' : 'Provision an account with designated branch & role.' ?></p>
                        </div>
                    </div>

                    <form action="Dashboard_Super_Admin.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_user">

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 20px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= is_khmer() ? 'ឈ្មោះពេញ' : 'Full Name' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="full_name" class="input-modern" placeholder="e.g. Elena Rostova" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់' : 'Username' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="username" class="input-modern" placeholder="e.g. elena_r" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= is_khmer() ? 'អាសយដ្ឋានអ៊ីមែល' : 'Email Address' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="email" name="email" class="input-modern" placeholder="e.g. elena@ppap.com" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= is_khmer() ? 'ពាក្យសម្ងាត់ដំបូង' : 'Initial Password' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="password" name="password" class="input-modern" placeholder="Enter password (min 6 chars)" minlength="6" required style="width: 100%; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= t('field_branch') ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="branch" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                                    <?php foreach ($branchesList as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 6px;">
                                    <?= is_khmer() ? 'កំណត់តួនាទី' : 'Assign Role' ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="role" class="input-modern select-full" style="width: 100%; box-sizing: border-box;">
                                    <option value="user" selected><?= is_khmer() ? 'សមាជិកទូទៅ' : 'Normal User' ?></option>
                                    <option value="branch_manager"><?= is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager' ?></option>
                                    <option value="admin"><?= is_khmer() ? 'អ្នកគ្រប់គ្រង' : 'Admin' ?></option>
                                    <option value="super_admin"><?= is_khmer() ? 'Super Admin' : 'Super Admin' ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                            <button type="submit" class="btn-super-submit" style="padding: 10px 24px; font-size: 0.92rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; border-radius: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span><?= is_khmer() ? 'បង្កើតគណនី' : 'Create User' ?></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Users Management Directory Section (Full-width, Spacious & Balanced) -->
                <div class="panel-card" style="width: 100%; box-sizing: border-box;">
                    <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2><?= t('users_mgmt_title') ?></h2>
                            <p><?= t('users_mgmt_subtitle') ?></p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <!-- Branch Filter Dropdown -->
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label for="superBranchFilter" style="font-size: 0.82rem; font-weight: 600; color: #475569; white-space: nowrap;">🏢 <?= is_khmer() ? 'សាខា:' : 'Branch:' ?></label>
                                <select id="superBranchFilter" class="input-modern" onchange="filterSuperUserTable()" style="padding: 7px 12px; font-size: 0.85rem; min-width: 190px; border-radius: 8px;">
                                    <option value=""><?= t('filter_all_branches') ?> (<?= count($allUsers) ?>)</option>
                                    <?php foreach ($branchesList as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>">
                                            <?= htmlspecialchars($b) ?> (<?= $branchCounts[$b] ?? 0 ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Keyword Search -->
                            <input type="text" id="superUserSearch" class="input-modern" placeholder="<?= t('ph_search_user') ?>" onkeyup="filterSuperUserTable()" style="padding: 7px 12px; font-size: 0.85rem; width: 180px; border-radius: 8px;">
                            
                            <!-- Export CSV Button -->
                            <button type="button" class="btn-export" onclick="exportSuperToCsv()" title="<?= t('btn_export_csv') ?>" style="padding: 7px 12px; font-size: 0.85rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>CSV</span>
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="dashboard-table" id="superDirectoryTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?= t('th_user_id') ?></th>
                                    <th><?= t('th_account_info') ?></th>
                                    <th><?= t('th_email') ?></th>
                                    <th><?= t('th_branch') ?></th>
                                    <th><?= t('th_current_role') ?></th>
                                    <th><?= t('th_role_assign') ?></th>
                                    <th style="text-align: right;"><?= t('th_action') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $u): ?>
                                    <tr data-branch="<?= htmlspecialchars($u['branch'] ?? 'កំពង់ផែស្វយ័តភ្នំពេញ') ?>">
                                        <td><span class="badge-id">#<?= $u['id'] ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($u['full_name']) ?></strong>
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
                                        <td>
                                            <form action="Dashboard_Super_Admin.php" method="POST" class="inline-role-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <select name="new_role" class="select-role-modern" onchange="this.form.submit()" <?= $u['id'] === $userId ? 'title="Cannot change your own role"' : '' ?>>
                                                    <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="branch_manager" <?= $u['role'] === 'branch_manager' ? 'selected' : '' ?>>Branch Manager</option>
                                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Normal User</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <!-- Edit Button -->
                                            <button type="button" class="btn-action-edit" title="<?= t('btn_update_user') ?>" onclick='openSuperEditUserModal(<?= json_encode([
                                                "id"        => $u["id"],
                                                "full_name" => $u["full_name"],
                                                "username"  => $u["username"],
                                                "email"     => $u["email"],
                                                "role"      => $u["role"],
                                                "branch"    => $u["branch"] ?? "កំពង់ផែស្វយ័តភ្នំពេញ"
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="padding: 5px 9px; font-size: 0.78rem;">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span><?= is_khmer() ? 'កែប្រែ' : 'Edit' ?></span>
                                            </button>

                                            <?php if ($u['id'] !== $userId): ?>
                                                <form action="Dashboard_Super_Admin.php" method="POST" style="display: inline-block; margin: 0 0 0 4px;" onsubmit="return confirm('Confirm deletion of user <?= htmlspecialchars($u['username']) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn-icon-danger" title="Delete User">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="self-tag" style="margin-left: 4px;">You</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="superNoResultsRow" style="display: none;">
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
                    <a href="Dashboard_Super_Admin.php?tab=home"><?= t('crumb_home') ?></a>
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

                    <form action="Dashboard_Super_Admin.php?tab=entities" method="POST">
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
                                <button type="submit" class="btn-super-submit" style="width: 100%; height: 42px; padding: 0 18px; font-size: 0.92rem; font-weight: 700; justify-content: center; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; border-radius: 8px;">
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
                                                    
                                                    <form action="Dashboard_Super_Admin.php?tab=entities" method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= t('confirm_delete_entity') ?>');">
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
                    <a href="Dashboard_Super_Admin.php?tab=home"><?= t('crumb_home') ?></a>
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

                    <form action="Dashboard_Super_Admin.php?tab=departments" method="POST">
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
                                <button type="submit" class="btn-super-submit" style="width: 100%; height: 42px; padding: 0 18px; font-size: 0.92rem; font-weight: 700; justify-content: center; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; border-radius: 8px;">
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

                                                    <form action="Dashboard_Super_Admin.php?tab=departments" method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('<?= t('confirm_delete_dept') ?>');">
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
                <?php render_tab_content($activeTab, 'super_admin', $allUsers, $stats, $currentUser, $csrfToken, $superActivities); ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Super Admin Edit User Modal -->
<div id="superEditUserModal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.25rem;">✏️</span>
                <h3 style="margin: 0; font-size: 1.12rem; font-weight: 700; color: #0f172a;"><?= t('modal_edit_user_title') ?></h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeSuperEditUserModal()">&times;</button>
        </div>
        <form action="Dashboard_Super_Admin.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="target_user_id" id="superEditUserId" value="">
            
            <div class="modal-body" style="padding: 20px 24px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះពេញ' : 'Full Name' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="full_name" id="superEditFullName" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'ឈ្មោះអ្នកប្រើប្រាស់' : 'Username' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="username" id="superEditUsername" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'អាសយដ្ឋានអ៊ីមែល' : 'Email Address' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="email" name="email" id="superEditEmail" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_branch') ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="branch" id="superEditBranch" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <?php foreach ($branchesList as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= is_khmer() ? 'កំណត់តួនាទី' : 'Assign Role' ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="role" id="superEditRole" class="input-modern" required style="width: 100%; box-sizing: border-box;">
                        <option value="user"><?= is_khmer() ? 'សមាជិកទូទៅ' : 'Normal User' ?></option>
                        <option value="branch_manager"><?= is_khmer() ? 'ប្រធានសាខា' : 'Branch Manager' ?></option>
                        <option value="admin"><?= is_khmer() ? 'អ្នកគ្រប់គ្រង' : 'Admin' ?></option>
                        <option value="super_admin"><?= is_khmer() ? 'Super Admin' : 'Super Admin' ?></option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 0.86rem; color: #334155; display: block; margin-bottom: 5px;">
                        <?= t('field_new_password_optional') ?>
                    </label>
                    <input type="password" name="new_password" id="superEditNewPassword" class="input-modern" placeholder="<?= is_khmer() ? 'បញ្ចូលប្រសិនបើចង់ផ្លាស់ប្តូរ (យ៉ាងតិច ៦ តួអក្សរ)' : 'Min. 6 chars (leave blank to keep current)' ?>" minlength="6" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-cancel" onclick="closeSuperEditUserModal()"><?= t('btn_cancel') ?></button>
                <button type="submit" class="btn-save-modal" style="background: #7c3aed;"><?= t('btn_update_user') ?></button>
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
        <form action="Dashboard_Super_Admin.php?tab=entities" method="POST" style="padding: 22px;">
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
                <button type="submit" class="btn-super-submit" style="padding: 9px 20px; border-radius: 8px; border: none; background: #0e2a86; color: #ffffff; font-weight: 700; cursor: pointer;">💾 <?= is_khmer() ? 'រក្សាទុកការកែប្រែ' : 'Save Changes' ?></button>
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
        <form action="Dashboard_Super_Admin.php?tab=departments" method="POST" style="padding: 22px;">
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
                <button type="submit" class="btn-super-submit" style="padding: 9px 20px; border-radius: 8px; border: none; background: #0e2a86; color: #ffffff; font-weight: 700; cursor: pointer;">💾 <?= is_khmer() ? 'រក្សាទុកការកែប្រែ' : 'Save Changes' ?></button>
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

function filterSuperUserTable() {
    const searchInput = document.getElementById('superUserSearch');
    const branchFilter = document.getElementById('superBranchFilter');
    const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const branchVal = branchFilter ? branchFilter.value.trim() : '';

    const rows = document.querySelectorAll('#superDirectoryTable tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        if (row.id === 'superNoResultsRow') return;

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

    const noResultsRow = document.getElementById('superNoResultsRow');
    if (noResultsRow) {
        noResultsRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
}

function openSuperEditUserModal(user) {
    if (!user) return;
    document.getElementById('superEditUserId').value = user.id || '';
    document.getElementById('superEditFullName').value = user.full_name || '';
    document.getElementById('superEditUsername').value = user.username || '';
    document.getElementById('superEditEmail').value = user.email || '';
    document.getElementById('superEditBranch').value = user.branch || 'កំពង់ផែស្វយ័តភ្នំពេញ';
    document.getElementById('superEditRole').value = user.role || 'user';
    document.getElementById('superEditNewPassword').value = '';
    
    const m = document.getElementById('superEditUserModal');
    if (m) m.style.display = 'flex';
}

function closeSuperEditUserModal() {
    const m = document.getElementById('superEditUserModal');
    if (m) m.style.display = 'none';
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

window.addEventListener('click', function(e) {
    const editM = document.getElementById('superEditUserModal');
    if (e.target === editM) closeSuperEditUserModal();
    const editEnt = document.getElementById('editEntityModal');
    if (e.target === editEnt) closeEditEntityModal();
    const editDept = document.getElementById('editDepartmentModal');
    if (e.target === editDept) closeEditDepartmentModal();
});

function exportSuperToCsv() {
    let csv = [];
    const table = document.getElementById('superDirectoryTable');
    if (!table) return;
    const rows = table.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].id === 'superNoResultsRow') continue;
        if (rows[i].style.display === 'none') continue;
        
        let row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            // Skip action columns (role assign select & actions)
            if (j === cols.length - 1 || j === cols.length - 2) continue;
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
