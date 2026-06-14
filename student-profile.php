<?php
/**
 * Student profile: view, add, or edit a single student.
 * URL:  ?id=123  -> view/edit
 *       ?new=1   -> blank add form
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_department();

$me = current_faculty();
if ($me === null) {
    $prefix = is_file('faculty-login.php') ? '' : '../';
    redirect($prefix . 'faculty-login.php');
}

$is_new = isset($_GET['new']);
$id     = (int)($_GET['id'] ?? 0);

$student = null;
$achievements = [];
if (!$is_new && $id > 0) {
    [$scope, $p, $t] = scope_sql_department('s');
    $student = db_one(
        "SELECT s.*, d.name AS dept_name, d.code AS department_code FROM students s
           JOIN departments d ON d.id = s.department_id
          WHERE s.id = ? $scope",
        array_merge([$id], $p), 'i' . $t
    );
    if (!$student) {
        http_response_code(404);
        exit('Student not found.');
    }

    // Pull achievements from the dedicated achievements table
    $achievements = db_select(
        'SELECT id, title, event_name, level, position, event_date, description
           FROM achievements
          WHERE student_id = ? AND is_published = 1
          ORDER BY event_date DESC, id DESC',
        [$id], 'i'
    );

    // Pull all required documents for this department and check if student has uploaded them
    $documents = db_select(
        'SELECT dr.id AS req_id, dr.document_name, sd.file_path
         FROM dept_document_requirements dr
         LEFT JOIN student_documents sd ON sd.requirement_id = dr.id AND sd.student_id = ?
         WHERE dr.department_id = ?
         ORDER BY dr.id',
        [$id, $student['department_id']], 'ii'
    );
}

$departments = db_select('SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY display_order, name');

// Departments the current user is allowed to save to.
$allowed_dept_ids = ($me['role'] === 'SUPER_ADMIN')
    ? array_map(fn($r) => (int)$r['id'], $departments)
    : [(int)$me['department_id']];

$ok_flash  = flash_get('student_saved');
$err_flash = flash_get('student_error');

function is_selected($value, $current): string {
    return (string)$value === (string)$current ? 'selected' : '';
}

// Map position text to CSS class + icon for the medal chip.
function medal_class(string $position): string {
    $l = strtolower($position);
    if (str_contains($l, 'gold') || $l === '1st')   return 'gold';
    if (str_contains($l, 'silver') || $l === '2nd')  return 'silver';
    if (str_contains($l, 'bronze') || $l === '3rd')  return 'bronze';
    if (str_contains($l, 'participat'))               return 'participation';
    return 'gold';
}
function medal_icon(string $class): string {
    return match($class) {
        'silver'        => 'bi-award-fill',
        'participation' => 'bi-flag-fill',
        default         => 'bi-trophy-fill',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_new ? 'Add' : 'Edit' ?> Student | Sports Portal</title>
    <?= csrf_meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= h(url('css/public.css')) ?>">
    <link rel="stylesheet" href="<?= h(url('css/admin.css')) ?>">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="app-wrapper">

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <img src="<?= h(url('images/ytc-logo.png')) ?>" alt="YTC Logo">
                <div class="sidebar-brand-text">
                    <h2>Sports Database</h2>
                    <span>Yashoda Technical Campus</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="admin/dashboard.php"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a>
                <?php if (has_multiple_departments()): ?>
                    <a href="faculty-select.php?change=1"><i class="bi bi-building"></i> <span>Select Department</span></a>
                <?php endif; ?>
                <a href="student-search.php"><i class="bi bi-search"></i> <span>Search Students</span></a>
                <a href="student-profile.php?new=1" class="active"><i class="bi bi-person-plus"></i> <span><?= $is_new ? 'Add Student' : 'Student Profile' ?></span></a>
                <a href="admin/provisional_list.php"><i class="bi bi-clipboard-check"></i> <span>Provisional Players</span></a>
                <?php if ($me['role'] === 'SUPER_ADMIN'): ?>
                    <div class="sidebar-nav-label">Admin</div>
                    <a href="admin/faculty_manage.php"><i class="bi bi-people-fill"></i> <span>Faculty Management</span></a>
                <?php endif; ?>
                <div class="sidebar-nav-label">Site</div>
                <a href="index.php"><i class="bi bi-globe"></i> <span>View Website</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar"><?= h(initials($me['full_name'])) ?></div>
                    <div class="sidebar-user-info">
                        <h4><?= h($me['full_name']) ?></h4>
                        <span><?= h($me['department_name'] ?? $me['role']) ?></span>
                    </div>
                    <a href="admin/logout.php" class="btn-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
                        <a href="admin/dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a><span class="sep">/</span>
                        <a href="student-search.php">Search</a><span class="sep">/</span>
                        <span class="current"><?= $is_new ? 'Add New Student' : 'Student Profile' ?></span>
                    </nav>
                </div>
                <div class="top-bar-right">
                    <a href="student-search.php" class="top-back-btn" title="Back to student search" aria-label="Back to student search">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </header>

            <div class="content-body">
                <?php if ($ok_flash): ?>
                    <div class="alert-banner success" style="padding:.8rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem;display:flex;align-items:center;gap:.5rem;background:rgba(25,135,84,.1);color:#0a3622;border:1px solid rgba(25,135,84,.2)">
                        <i class="bi bi-check-circle"></i> <?= h($ok_flash['msg']) ?>
                    </div>
                <?php endif; ?>
                <?php if ($err_flash): ?>
                    <div class="alert-banner error" style="padding:.8rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem;display:flex;align-items:center;gap:.5rem;background:rgba(220,53,69,.1);color:#842029;border:1px solid rgba(220,53,69,.2)">
                        <i class="bi bi-exclamation-circle"></i> <?= h($err_flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$is_new && $student): ?>
                    <!-- ======== VIEW MODE ======== -->
                    <div id="viewMode">
                        <!-- Profile Banner -->
                        <div class="profile-banner">
                            <div class="profile-photo-wrap">
                                <?php if (!empty($student['photo_path']) && is_file(__DIR__ . '/' . $student['photo_path'])): ?>
                                    <img src="<?= h(url($student['photo_path'])) ?>" alt="<?= h($student['full_name']) ?>" class="profile-photo">
                                <?php else: ?>
                                    <div class="profile-photo-placeholder"><?= h(initials($student['full_name'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <h1><?= h($student['full_name']) ?></h1>
                                <div class="enrollment"><i class="bi bi-upc-scan"></i> <?= h($student['enrollment_no']) ?></div>
                                <div class="profile-meta-chips">
                                    <span class="meta-chip"><i class="bi bi-building"></i> <?= h($student['dept_name']) ?></span>
                                    <span class="meta-chip"><i class="bi bi-calendar3"></i> <?= h($student['academic_year'] ?: '—') ?></span>
                                    <span class="meta-chip"><i class="bi bi-mortarboard"></i> <?= h($student['study_year'] ?: '—') ?> Year</span>
                                </div>
                            </div>
                            <div class="profile-actions">
                                <button class="btn-act edit" onclick="document.getElementById('viewMode').style.display='none';document.getElementById('formMode').style.display='block';window.scrollTo({top:0,behavior:'smooth'})">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <a class="btn-act export" href="admin/export_xlsx.php?id=<?= (int)$student['id'] ?>">
                                    <i class="bi bi-download"></i> Export
                                </a>

                            </div>
                        </div>

                        <!-- Official Student Record Document -->
                        <div class="record-document">
                            <div class="record-doc-header"><i class="bi bi-file-earmark-text"></i> Student Sports Record — Official Copy</div>
                            <table class="record-table">
                                <tr class="record-section-row"><td colspan="4"><i class="bi bi-person-vcard"></i> Personal Information</td></tr>
                                <tr>
                                    <td class="rec-label">Full Name</td>
                                    <td class="rec-value"><?= h($student['full_name']) ?></td>
                                    <?php if (in_array($student['department_code'] ?? '', ['engineering', 'pharmacy'], true)): ?>
                                        <td class="rec-label">Father First Name</td>
                                        <td class="rec-value"><?= h($student['mother_name'] ?: '—') ?></td>
                                    <?php elseif (in_array($student['department_code'] ?? '', ['mba', 'mca', 'bba', 'bca', 'architecture'], true)): ?>
                                        <td class="rec-label">Mother Name</td>
                                        <td class="rec-value"><?= h($student['mother_name'] ?: '-') ?></td>
                                    <?php else: ?>
                                        <td class="rec-label">Enrollment No.</td>
                                        <td class="rec-value"><?= h($student['enrollment_no']) ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (($student['department_code'] ?? '') === 'engineering'): ?>
                                    <tr>
                                        <td class="rec-label">Enrollment No.</td>
                                        <td class="rec-value"><?= h($student['enrollment_no']) ?></td>
                                        <td class="rec-label"></td>
                                        <td class="rec-value"></td>
                                    </tr>
                                <?php elseif (in_array($student['department_code'] ?? '', ['polytechnic', 'dpharm', 'pharmacy', 'mba', 'mca', 'bba', 'bca', 'architecture'], true)): ?>
                                    <tr>
                                        <td class="rec-label">Roll No.</td>
                                        <td class="rec-value"><?= h($student['roll_no'] ?: '-') ?></td>
                                        <?php if (in_array($student['department_code'] ?? '', ['pharmacy', 'mba', 'mca', 'bba', 'bca', 'architecture'], true)): ?>
                                            <td class="rec-label">Enrollment No.</td>
                                            <td class="rec-value"><?= h($student['enrollment_no']) ?></td>
                                        <?php else: ?>
                                            <td class="rec-label"></td>
                                            <td class="rec-value"></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="rec-label">Date of Birth</td>
                                    <td class="rec-value"><?= h(format_date($student['dob'] ?: null) ?: '—') ?></td>
                                    <td class="rec-label">Gender</td>
                                    <td class="rec-value"><?= h($student['gender'] ?: '—') ?></td>
                                </tr>
                                <tr>
                                    <td class="rec-label">Blood Group</td>
                                    <td class="rec-value"><?= h($student['blood_group'] ?: '—') ?></td>
                                    <td class="rec-label">Status</td>
                                    <td class="rec-value" style="color:#38a169;font-weight:700">● Active</td>
                                </tr>

                                <tr class="record-section-row"><td colspan="4"><i class="bi bi-telephone"></i> Contact Details</td></tr>
                                <tr>
                                    <td class="rec-label">Email</td>
                                    <td class="rec-value"><?= h($student['email'] ?: '—') ?></td>
                                    <td class="rec-label">Mobile No.</td>
                                    <td class="rec-value"><?= h($student['mobile'] ?: '—') ?></td>
                                </tr>
                                <tr>
                                    <td class="rec-label">Parent's Phone</td>
                                    <td class="rec-value"><?= h($student['parent_phone'] ?: '—') ?></td>
                                    <td class="rec-label">Address</td>
                                    <td class="rec-value"><?= h($student['address'] ?: '—') ?></td>
                                </tr>

                                <tr class="record-section-row"><td colspan="4"><i class="bi bi-mortarboard"></i> Academic Details</td></tr>
                                <tr>
                                    <td class="rec-label">Department</td>
                                    <td class="rec-value"><?= h($student['dept_name']) ?></td>
                                    <td class="rec-label">Program / Branch</td>
                                    <td class="rec-value"><?= h($student['program'] ?: '—') ?></td>
                                </tr>
                                <tr>
                                    <td class="rec-label">Academic Year</td>
                                    <td class="rec-value"><?= h($student['academic_year'] ?: '—') ?></td>
                                    <td class="rec-label">Year of Study</td>
                                    <td class="rec-value"><?= h($student['study_year'] ?: '—') ?></td>
                                </tr>

                                <tr class="record-section-row"><td colspan="4"><i class="bi bi-trophy"></i> Sports Information</td></tr>
                                <tr>
                                    <td class="rec-label">Primary Sport</td>
                                    <td class="rec-value"><?= h($student['sport_1'] ?: '—') ?></td>
                                    <td class="rec-label">Secondary Sport</td>
                                    <td class="rec-value"><?= h($student['sport_2'] ?: '—') ?></td>
                                </tr>
                                <?php if (!empty($student['sports_history'])): ?>
                                <tr>
                                    <td class="rec-label">Sports History</td>
                                    <td class="rec-value" colspan="3"><?= nl2br(h($student['sports_history'])) ?></td>
                                </tr>
                                <?php endif; ?>

                                <tr class="record-section-row"><td colspan="4"><i class="bi bi-file-earmark-pdf"></i> Uploaded Documents</td></tr>
                                <?php if (!empty($documents)): ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr style="border-bottom: 1px solid var(--light-gray)">
                                            <td class="rec-label"><?= h($doc['document_name']) ?></td>
                                            <td class="rec-value" colspan="3">
                                                <?php if ($doc['file_path']): ?>
                                                    <a href="<?= h(url($doc['file_path'])) ?>" target="_blank" class="text-primary" style="text-decoration:none;font-weight:600">
                                                        <i class="bi bi-download"></i> View Document
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color:var(--medium-gray);font-style:italic;font-size:.85rem">No document uploaded yet</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="rec-label" colspan="4" style="text-align:center;color:var(--medium-gray);font-style:italic">No document requirements for this department.</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <!-- Achievements Table -->
                        <div class="achievements-section">
                            <table class="ach-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px">#</th>
                                        <th>Achievement / Event</th>
                                        <th>Level</th>
                                        <th>Medal / Position</th>
                                        <th>Year</th>
                                        <th>Remarks</th>
                                        <th style="width:50px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($achievements): ?>
                                        <?php foreach ($achievements as $i => $ach):
                                            $mc = medal_class($ach['position'] ?: '');
                                            $mi = medal_icon($mc);
                                        ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= h($ach['title'] ?: $ach['event_name'] ?: '—') ?></td>
                                                <td><?= h($ach['level'] ?: '—') ?></td>
                                                <td><span class="ach-medal <?= h($mc) ?>"><i class="bi <?= $mi ?>"></i> <?= h($ach['position'] ?: '—') ?></span></td>
                                                <td><?= h($ach['event_date'] ? substr($ach['event_date'], 0, 4) : '—') ?></td>
                                                <td><?= h($ach['description'] ?: '—') ?></td>
                                                <td>
                                                    <form method="post" action="admin/achievement_delete.php" style="display:inline" onsubmit="return confirm('Delete this achievement?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="ach_id" value="<?= (int)$ach['id'] ?>">
                                                        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                                                        <button type="submit" style="background:none;border:none;color:#c53030;cursor:pointer;font-size:.85rem" title="Delete"><i class="bi bi-trash3"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" style="text-align:center;color:var(--medium-gray);padding:1.5rem">No achievements recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ======== ADD / EDIT MODE ======== -->
                <div id="formMode" style="<?= $is_new ? '' : 'display:none' ?>">
                    <div class="form-section">
                        <h2 id="formTitle">
                            <i class="bi bi-<?= $is_new ? 'person-plus-fill' : 'pencil-square' ?>"></i>
                            <?= $is_new ? 'Add New Student' : 'Edit Student Profile' ?>
                        </h2>
                        <p id="formSubtitle"><?= $is_new ? 'Fill in student details to add them to the sports database.' : 'Update the student details below.' ?></p>

                        <form method="post" action="admin/student_save.php" enctype="multipart/form-data" id="studentForm">
                            <?= csrf_field() ?>
                            <?php if (!$is_new && $student): ?>
                                <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                            <?php endif; ?>

                            <!-- Photo Upload -->
                            <div style="margin-bottom:1.5rem">
                                <label style="font-size:.78rem;font-weight:600;color:var(--primary-navy);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.5rem;display:block">Passport Photo</label>
                                <div class="photo-upload">
                                    <div class="photo-preview" id="photoPreview">
                                        <?php if (!$is_new && $student && !empty($student['photo_path']) && is_file(__DIR__ . '/' . $student['photo_path'])): ?>
                                            <img src="<?= h(url($student['photo_path'])) ?>" alt="">
                                        <?php else: ?>
                                            <i class="bi bi-camera"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="photo-upload-btn"><i class="bi bi-upload"></i> Upload Photo <input type="file" id="photo" name="photo" accept="image/*" style="display:none"></label>
                                        <p style="font-size:.72rem;color:var(--medium-gray);margin-top:.35rem">JPG/PNG, max 2MB, passport size</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Info -->
                            <h3 style="font-size:.88rem;font-weight:700;color:var(--primary-navy);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--light-gray)">
                                <i class="bi bi-person-vcard" style="color:var(--accent-gold);margin-right:.4rem"></i> Personal Information
                            </h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" placeholder="Enter full name" required value="<?= h($student['full_name'] ?? '') ?>">
                                </div>
                                <div class="form-group" id="parentNameField" style="display:none">
                                    <label for="mother_name" id="parentNameLabel">Father First Name *</label>
                                    <input type="text" id="mother_name" name="mother_name" placeholder="Enter father's first name" value="<?= h($student['mother_name'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="enrollment_no">Enrollment No. *</label>
                                    <input type="text" id="enrollment_no" name="enrollment_no" placeholder="e.g. EN2025001" required value="<?= h($student['enrollment_no'] ?? '') ?>">
                                </div>
                                <div class="form-group" id="rollNumberField" style="display:none">
                                    <label for="roll_no">Roll No.</label>
                                    <input type="text" id="roll_no" name="roll_no" placeholder="Enter roll number" value="<?= h($student['roll_no'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dob">Date of Birth *</label>
                                    <input type="date" id="dob" name="dob" required
                                           min="1900-01-01" max="<?= date('Y-m-d') ?>"
                                           value="<?= h($student['dob'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender">
                                        <option value="">Select</option>
                                        <?php foreach (gender_options() as $g): ?>
                                            <option value="<?= h($g) ?>" <?= is_selected($g, $student['gender'] ?? '') ?>><?= h($g) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="blood_group">Blood Group</label>
                                    <select id="blood_group" name="blood_group">
                                        <option value="">Select</option>
                                        <?php foreach (blood_options() as $b): ?>
                                            <option value="<?= h($b) ?>" <?= is_selected($b, $student['blood_group'] ?? '') ?>><?= h($b) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Contact -->
                            <h3 style="font-size:.88rem;font-weight:700;color:var(--primary-navy);margin:1.5rem 0 1rem;padding-bottom:.5rem;border-bottom:1px solid var(--light-gray)">
                                <i class="bi bi-telephone" style="color:var(--accent-gold);margin-right:.4rem"></i> Contact Details
                            </h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" placeholder="student@email.com" required value="<?= h($student['email'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="mobile">Mobile No. *</label>
                                    <input type="tel" id="mobile" name="mobile" placeholder="10-digit mobile number" required pattern="(?:\+91[- ]?)?[0-9]{10}" maxlength="14" inputmode="tel" value="<?= h($student['mobile'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="parent_phone">Parent's Phone</label>
                                    <input type="tel" id="parent_phone" name="parent_phone" placeholder="+91 XXXXX XXXXX" value="<?= h($student['parent_phone'] ?? '') ?>">
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" placeholder="City, District" value="<?= h($student['address'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Academic -->
                            <h3 style="font-size:.88rem;font-weight:700;color:var(--primary-navy);margin:1.5rem 0 1rem;padding-bottom:.5rem;border-bottom:1px solid var(--light-gray)">
                                <i class="bi bi-mortarboard" style="color:var(--accent-gold);margin-right:.4rem"></i> Academic Details
                            </h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department_id">Department *</label>
                                    <select id="department_id" name="department_id" required>
                                        <?php foreach ($departments as $d): ?>
                                            <?php if (!in_array((int)$d['id'], $allowed_dept_ids, true) && (int)$d['id'] !== (int)($student['department_id'] ?? 0)) continue; ?>
                                            <option value="<?= (int)$d['id'] ?>" data-code="<?= h($d['code']) ?>" <?= is_selected($d['id'], $student['department_id'] ?? $me['department_id'] ?? '') ?>>
                                                <?= h($d['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($me['role'] === 'FACULTY'): ?>
                                        <small style="color:var(--medium-gray);font-size:.78rem">You can only save students to your assigned department.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="program">Program / Branch</label>
                                    <input type="text" id="program" name="program" placeholder="e.g. B.E. Computer Engg." value="<?= h($student['program'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="academic_year">Academic Year *</label>
                                    <select id="academic_year" name="academic_year" required>
                                        <option value="">—</option>
                                        <?php $selectedAcademicYear = $student['academic_year'] ?? current_academic_year(); ?>
                                        <?php foreach (academic_year_options() as $y): ?>
                                            <option value="<?= h($y) ?>" <?= is_selected($y, $selectedAcademicYear) ?>><?= h($y) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="study_year">Year of Study *</label>
                                    <select id="study_year" name="study_year" required>
                                        <option value="" disabled hidden>Select</option>
                                        <?php foreach (year_options() as $y): ?>
                                            <option value="<?= h($y) ?>" data-study-year="<?= h(strtolower($y)) ?>" <?= is_selected($y, $student['study_year'] ?? '') ?>><?= h($y) ?> Year</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div id="dynamic-docs-container" style="margin-bottom:1.5rem">
                                <h3 style="font-size:.88rem;font-weight:700;color:var(--primary-navy);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--light-gray)">
                                    <i class="bi bi-file-earmark-arrow-up" style="color:var(--accent-gold);margin-right:.4rem"></i> Required Documents
                                </h3>
                                <div id="docs-fields-grid" class="form-grid">
                                    <p style="color:var(--medium-gray);font-size:.8rem;grid-column:1/-1">Please select a department to see required documents.</p>
                                </div>
                            </div>

                            <!-- Sports -->
                            <h3 style="font-size:.88rem;font-weight:700;color:var(--primary-navy);margin:1.5rem 0 1rem;padding-bottom:.5rem;border-bottom:1px solid var(--light-gray)">
                                <i class="bi bi-trophy" style="color:var(--accent-gold);margin-right:.4rem"></i> Sports Information
                            </h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="sport_1">Primary Sport *</label>
                                    <input type="text" id="sport_1" name="sport_1" placeholder="e.g. Cricket" required value="<?= h($student['sport_1'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="sport_2">Secondary Sport</label>
                                    <input type="text" id="sport_2" name="sport_2" placeholder="e.g. Athletics" value="<?= h($student['sport_2'] ?? '') ?>">
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label for="achievements">Achievements / Notes</label>
                                    <textarea id="achievements" name="achievements" placeholder="One per line. E.g. 'Gold - Inter-College Cricket 2025'"><?= h($student['achievements'] ?? '') ?></textarea>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label for="sports_history">Sports History / Games Played</label>
                                    <textarea id="sports_history" name="sports_history" placeholder="e.g. 2023 — Inter-college Cricket (Runner-up). 2024 — University Football selection."><?= h($student['sports_history'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-save">
                                    <i class="bi bi-check-circle"></i> <?= $is_new ? 'Save Student' : 'Update Profile' ?>
                                </button>
                                <a href="<?= $is_new ? 'student-search.php' : 'student-profile.php?id=' . (int)$student['id'] ?>" class="btn-cancel">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>

                                <?php if (!$is_new && $student): ?>
                                    <form method="post" action="admin/student_delete.php" style="display:inline;margin-left:auto" onsubmit="return confirm('Are you sure you want to permanently delete this student record? This action cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                                        <button type="submit" class="btn-cancel" style="background:#fff5f5;color:#c53030;border-color:#fed7d7">
                                            <i class="bi bi-trash3"></i> Delete Student
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggle  = document.getElementById('sidebarToggle');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('show');
                });
                overlay.addEventListener('click', function () {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                });
            }

            // Dynamic Document Requirements
            var deptSelect = document.getElementById('department_id');
            var docsGrid = document.getElementById('docs-fields-grid');
            var parentNameField = document.getElementById('parentNameField');
            var parentNameLabel = document.getElementById('parentNameLabel');
            var parentNameInput = document.getElementById('mother_name');
            var rollField = document.getElementById('rollNumberField');
            var rollInput = document.getElementById('roll_no');
            var studyYearSelect = document.getElementById('study_year');

            function updateDepartmentFields() {
                if (!deptSelect) return;
                var option = deptSelect.options[deptSelect.selectedIndex];
                var usesFatherFirstName = option && ['engineering', 'pharmacy'].includes(option.dataset.code);
                var isPolytechnic = option && (option.dataset.code === 'polytechnic' || option.dataset.code === 'dpharm');
                var isPharmFacultyDepartment = option && ['pharmacy', 'mba', 'mca', 'bba', 'bca', 'architecture'].includes(option.dataset.code);
                var isDiploma = option && option.dataset.code === 'polytechnic';
                if (parentNameField && parentNameInput && parentNameLabel) {
                    var showParentName = usesFatherFirstName || isPharmFacultyDepartment;
                    parentNameField.style.display = showParentName ? '' : 'none';
                    parentNameInput.required = usesFatherFirstName;
                    parentNameLabel.textContent = usesFatherFirstName ? 'Father First Name *' : 'Mother Name';
                    parentNameInput.placeholder = usesFatherFirstName
                        ? "Enter father's first name"
                        : "Enter mother's name";
                    if (!showParentName) parentNameInput.value = '';
                }
                if (rollField && rollInput) {
                    var showRollNumber = isPolytechnic || isPharmFacultyDepartment;
                    rollField.style.display = showRollNumber ? '' : 'none';
                    rollInput.required = false;
                    if (!showRollNumber) rollInput.value = '';
                }
                if (studyYearSelect) {
                    Array.from(studyYearSelect.options).forEach(function(yearOption) {
                        var hideFinal = isDiploma && yearOption.dataset.studyYear === 'final';
                        yearOption.hidden = hideFinal;
                        yearOption.disabled = hideFinal;
                    });
                    if (isDiploma && studyYearSelect.value === 'Final') {
                        studyYearSelect.value = '';
                    }
                }
            }
            if (deptSelect) {
                deptSelect.addEventListener('change', updateDepartmentFields);
                updateDepartmentFields();
            }
            if (deptSelect && docsGrid) {
                async function updateRequirements() {
                    var deptId = deptSelect.value;
                    if (!deptId) {
                        docsGrid.innerHTML = '<p style=\"color:var(--medium-gray);font-size:.8rem;grid-column:1/-1\">Please select a department to see required documents.</p>';
                        return;
                    }
                    try {
                        var response = await fetch('api/get_dept_requirements.php?dept_id=' + deptId);
                        var reqs = await response.json();
                        if (!Array.isArray(reqs) || reqs.length === 0) {
                            docsGrid.innerHTML = '<p style=\"color:var(--medium-gray);font-size:.8rem;grid-column:1/-1\">No specific documents required for this department.</p>';
                            return;
                        }
                        var html = '';
                        reqs.forEach(function(req) {
                            var requiredAttr = ''; // Removed mandatory requirement
                            html += '<div class=\"form-group\">' +
                                    '<label for=\"doc_' + req.id + '\">' + req.document_name + '</label>' +
                                    '<input type=\"file\" id=\"doc_' + req.id + '\" name=\"doc_' + req.id + '\" ' + requiredAttr + ' accept=\".pdf,image/jpeg,image/png\">' +
                                    '</div>';
                        });
                        docsGrid.innerHTML = html;
                    } catch (e) {
                        docsGrid.innerHTML = '<p style=\"color:red;font-size:.8rem;grid-column:1/-1\">Error loading requirements.</p>';
                    }
                }
                deptSelect.addEventListener('change', updateRequirements);
                updateRequirements(); // Initial load
            }
        });

        // Photo preview
        (function () {
            var input = document.getElementById('photo');
            var preview = document.getElementById('photoPreview');
            if (!input || !preview) return;
            input.addEventListener('change', function (e) {
                var f = e.target.files[0];
                if (!f) return;
                var reader = new FileReader();
                reader.onload = function (ev) {
                    preview.innerHTML = '<img src="' + ev.target.result + '" alt="">';
                };
                reader.readAsDataURL(f);
            });
        })();
    </script>
</body>
</html>
