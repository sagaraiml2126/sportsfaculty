<?php
/**
 * Dashboard. Department-scoped stats for FACULTY; global stats for SUPER_ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_department();

$me    = current_faculty();
$dept  = $me['department_id'];
$scope = scope_sql_department('s');
[$where, $p, $t] = $scope;

// Student count (scoped)
$student_count = (int)(db_one(
    "SELECT COUNT(*) AS n FROM students s WHERE 1=1 $where", $p, $t
)['n'] ?? 0);

// Active faculty count
$faculty_count = (int)(db_one(
    "SELECT COUNT(*) AS n FROM faculty WHERE role = 'FACULTY' AND is_active = 1"
)['n'] ?? 0);

// Achievement count (scoped via student)
$ach_count = (int)(db_one(
    "SELECT COUNT(*) AS n FROM achievements a
       JOIN students s ON s.id = a.student_id
      WHERE 1=1 $where", $p, $t
)['n'] ?? 0);

// Notices (global, just count)
$notice_count = (int)(db_one(
    "SELECT COUNT(*) AS n FROM notices WHERE is_published = 1"
)['n'] ?? 0);

// Recently added students (last 8)
$recent = db_select(
    "SELECT s.id, s.enrollment_no, s.full_name, s.sport_1, s.academic_year, s.study_year, d.name AS dept_name
       FROM students s
       JOIN departments d ON d.id = s.department_id
      WHERE 1=1 $where
      ORDER BY s.created_at DESC
      LIMIT 8", $p, $t
);

$flash = flash_get('dashboard_info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sports Portal</title>
    <?= csrf_meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= h(url('css/public.css')) ?>">
    <link rel="stylesheet" href="<?= h(url('css/admin.css')) ?>">
    <style>
        :root { --primary-navy:#1a365d; --primary-navy-dark:#0f2744; --primary-navy-light:#2c5282;
                --accent-gold:#c9a227; --accent-gold-light:#d4b84a; --accent-maroon:#722f37;
                --white:#fff; --off-white:#f8f9fa; --light-gray:#e9ecef; --medium-gray:#6c757d;
                --dark-gray:#343a40; --text-dark:#212529;
                --font-primary:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
                --sidebar-width:260px; --transition-smooth:all .3s ease-in-out; }
        *{margin:0;padding:0;box-sizing:border-box}
        html,body{height:100%;overflow:hidden}
        body{font-family:var(--font-primary);color:var(--text-dark);background:var(--off-white);line-height:1.6}
        .app-wrapper{display:flex;height:100vh}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-width);background:linear-gradient(180deg,var(--primary-navy-dark),var(--primary-navy));color:var(--white);display:flex;flex-direction:column;flex-shrink:0;overflow:hidden;z-index:100}
        .sidebar-brand{padding:1.25rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:.75rem}
        .sidebar-brand img{width:42px;height:42px;border-radius:8px;object-fit:contain;background:rgba(255,255,255,.1);padding:3px;flex-shrink:0}
        .sidebar-brand-text h2{font-size:.85rem;font-weight:700;color:var(--white);margin:0;white-space:nowrap}
        .sidebar-brand-text span{font-size:.7rem;color:rgba(255,255,255,.5)}
        .sidebar-nav{flex:1;padding:1rem 0;overflow-y:auto}
        .sidebar-nav-label{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.35);padding:.75rem 1.5rem .4rem}
        .sidebar-nav a{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.5rem;color:rgba(255,255,255,.65);font-size:.88rem;font-weight:500;text-decoration:none;transition:var(--transition-smooth);border-left:3px solid transparent}
        .sidebar-nav a:hover{color:var(--white);background:rgba(255,255,255,.06);border-left-color:rgba(201,162,39,.4)}
        .sidebar-nav a.active{color:var(--white);background:rgba(201,162,39,.12);border-left-color:var(--accent-gold)}
        .sidebar-nav a.active i{color:var(--accent-gold)}
        .sidebar-nav a i{font-size:1.15rem;width:22px;text-align:center;flex-shrink:0}
        .sidebar-nav a .nav-badge{margin-left:auto;background:var(--accent-gold);color:var(--primary-navy-dark);font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:10px}
        .sidebar-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08)}
        .sidebar-user{display:flex;align-items:center;gap:.75rem}
        .sidebar-user-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--accent-gold),var(--accent-maroon));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--white);flex-shrink:0}
        .sidebar-user-info h4{font-size:.82rem;font-weight:600;color:var(--white);margin:0}
        .sidebar-user-info span{font-size:.7rem;color:rgba(255,255,255,.5)}
        .btn-logout{margin-left:auto;background:0 0;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6);padding:.35rem .5rem;border-radius:6px;cursor:pointer;transition:var(--transition-smooth);font-size:.85rem;text-decoration:none}
        .btn-logout:hover{background:rgba(220,53,69,.2);border-color:rgba(220,53,69,.4);color:#ff8a8a}

        /* MAIN */
        .main-content{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
        .top-bar{background:var(--white);border-bottom:1px solid var(--light-gray);padding:.75rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
        .top-bar-left{display:flex;align-items:center;gap:1rem}
        .sidebar-toggle{background:0 0;border:1px solid var(--light-gray);color:var(--medium-gray);padding:.4rem .55rem;border-radius:6px;cursor:pointer;font-size:1.1rem}
        .sidebar-toggle:hover{background:var(--off-white);color:var(--primary-navy)}
        .breadcrumb-nav{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--medium-gray)}
        .breadcrumb-nav .current{color:var(--primary-navy);font-weight:600}
        .icon-btn{background:0 0;border:1px solid var(--light-gray);color:var(--medium-gray);width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:var(--transition-smooth)}
        .icon-btn:hover{background:var(--off-white);color:var(--primary-navy)}
        .content-body{flex:1;overflow-y:auto;padding:2rem}

        .page-header{margin-bottom:1.5rem}
        .page-header h1{font-size:1.5rem;font-weight:700;color:var(--primary-navy)}
        .page-header p{color:var(--medium-gray);font-size:.95rem;margin-top:.25rem}

        .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;margin-bottom:1.75rem}
        .stat-card{background:var(--white);border:1px solid var(--light-gray);border-radius:10px;padding:1.25rem;display:flex;align-items:center;gap:1rem;transition:var(--transition-smooth)}
        .stat-card:hover{box-shadow:0 6px 18px rgba(26,54,93,.08);transform:translateY(-2px)}
        .stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
        .stat-icon.students{background:rgba(26,54,93,.1);color:var(--primary-navy)}
        .stat-icon.ach{background:rgba(201,162,39,.15);color:var(--accent-gold)}
        .stat-icon.notice{background:rgba(114,47,55,.12);color:var(--accent-maroon)}
        .stat-icon.fac{background:rgba(44,82,130,.1);color:var(--primary-navy-light)}
        .stat-info h3{font-size:1.4rem;font-weight:700;color:var(--primary-navy);margin:0;line-height:1.1}
        .stat-info p{font-size:.78rem;color:var(--medium-gray);margin:0;text-transform:uppercase;letter-spacing:.4px}

        .data-card{background:var(--white);border:1px solid var(--light-gray);border-radius:10px;overflow:hidden}
        .data-card-header{padding:1rem 1.25rem;border-bottom:1px solid var(--light-gray);display:flex;align-items:center;justify-content:space-between}
        .data-card-header h2{font-size:1rem;font-weight:600;color:var(--primary-navy);margin:0}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:var(--off-white);padding:.7rem 1rem;font-size:.75rem;font-weight:700;color:var(--primary-navy);text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:1px solid var(--light-gray)}
        .data-table td{padding:.75rem 1rem;font-size:.88rem;border-bottom:1px solid var(--light-gray);color:var(--text-dark)}
        .data-table tr:last-child td{border-bottom:none}
        .student-cell{display:flex;align-items:center;gap:.7rem}
        .student-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary-navy),var(--primary-navy-light));color:var(--white);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;flex-shrink:0}
        .student-name{font-weight:600;color:var(--primary-navy)}
        .student-meta{font-size:.75rem;color:var(--medium-gray)}
        .sport-tag{display:inline-block;background:rgba(201,162,39,.12);color:var(--accent-gold);padding:.2rem .6rem;border-radius:4px;font-size:.72rem;font-weight:600}
        .dept-tag{display:inline-block;background:rgba(26,54,93,.08);color:var(--primary-navy);padding:.2rem .6rem;border-radius:4px;font-size:.72rem;font-weight:600}
        .year-tag{font-size:.78rem;color:var(--medium-gray)}
        .action-link{color:var(--primary-navy);text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .5rem;border-radius:4px;transition:var(--transition-smooth)}
        .action-link:hover{background:var(--off-white);color:var(--primary-navy-light)}

        .empty-row{text-align:center;color:var(--medium-gray);padding:2.5rem 1rem;font-size:.9rem}
        .alert-banner{padding:.8rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem;display:flex;align-items:center;gap:.5rem}
        .alert-banner.info{background:rgba(13,202,240,.08);color:#055160;border:1px solid rgba(13,202,240,.2)}

        @media(max-width:992px){
            .sidebar{position:fixed;left:-280px;top:0;height:100vh;transition:left .3s ease;z-index:1050}
            .sidebar.open{left:0}
            .top-bar{padding:.75rem 1.25rem}
            .content-body{padding:1.25rem}
        }
    </style>
</head>
<body>
    <div class="app-wrapper">

        <aside class="sidebar">
            <div class="sidebar-brand">
                <img src="<?= h(url('images/ytc-logo.png')) ?>" alt="YTC Logo">
                <div class="sidebar-brand-text">
                    <h2>Sports Database</h2>
                    <span>Yashoda Technical Campus</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Main</div>
                <a href="dashboard.php" class="active">
                    <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                </a>
                <?php if (has_multiple_departments()): ?>
                    <a href="../faculty-select.php?change=1">
                        <i class="bi bi-building"></i> <span>Select Department</span>
                    </a>
                <?php endif; ?>
                <a href="../student-search.php">
                    <i class="bi bi-search"></i> <span>Search Students</span>
                </a>
                <a href="../student-profile.php?new=1">
                    <i class="bi bi-person-plus"></i> <span>Add Student</span>
                </a>
                <a href="provisional_list.php">
                    <i class="bi bi-clipboard-check"></i> <span>Provisional Players</span>
                </a>
                <a href="final_list.php">
                    <i class="bi bi-check-all"></i> <span>Final Teams</span>
                </a>
                <a href="jersey_dashboard.php">
                    <i class="bi bi-person-badge"></i> <span>Jersey Kit</span>
                </a>
                <?php if (($me['role'] ?? '') === 'SUPER_ADMIN'): ?>
                    <div class="sidebar-nav-label">Site Content</div>
                    <a href="notices_list.php">
                        <i class="bi bi-megaphone"></i> <span>Notices</span>
                    </a>
                    <a href="achievements_list.php">
                        <i class="bi bi-trophy"></i> <span>Achievements</span>
                    </a>
                    <a href="contact_messages.php">
                        <i class="bi bi-envelope"></i> <span>Contact Messages</span>
                    </a>
                <?php endif; ?>
                <?php if ($me['role'] === 'SUPER_ADMIN'): ?>
                    <div class="sidebar-nav-label">Admin</div>
                    <a href="faculty_manage.php">
                        <i class="bi bi-people-fill"></i> <span>Faculty Management</span>
                    </a>
                <?php endif; ?>
                <div class="sidebar-nav-label">Site</div>
                <a href="../index.php">
                    <i class="bi bi-globe"></i> <span>View Website</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar"><?= h(initials($me['full_name'])) ?></div>
                    <div class="sidebar-user-info">
                        <h4><?= h($me['full_name']) ?></h4>
                        <span><?= h($me['department_name'] ?? $me['role']) ?></span>
                    </div>
                    <a href="logout.php" class="btn-logout" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <div class="breadcrumb-nav">
                        <span class="current">Dashboard</span>
                    </div>
                </div>
                <div class="top-bar-right">
                    <span style="font-size:.85rem;color:var(--medium-gray)">Welcome, <strong><?= h($me['full_name']) ?></strong></span>
                </div>
            </header>

            <div class="content-body">
                <div class="page-header">
                    <h1>Welcome back, <?= h($me['full_name']) ?>!</h1>
                    <p>Here's what's happening with your sports database.</p>
                </div>

                <?php if ($flash): ?>
                    <div class="alert-banner <?= h($flash['level']) ?>">
                        <i class="bi bi-info-circle"></i> <?= h($flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon students"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-info">
                            <h3><?= $student_count ?></h3>
                            <p><?= $me['role'] === 'SUPER_ADMIN' ? 'Total Students' : 'Department Students' ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon ach"><i class="bi bi-trophy-fill"></i></div>
                        <div class="stat-info">
                            <h3><?= $ach_count ?></h3>
                            <p>Achievements</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon notice"><i class="bi bi-megaphone-fill"></i></div>
                        <div class="stat-info">
                            <h3><?= $notice_count ?></h3>
                            <p>Active Notices</p>
                        </div>
                    </div>
                    <?php if ($me['role'] === 'SUPER_ADMIN'): ?>
                        <div class="stat-card">
                            <div class="stat-icon fac"><i class="bi bi-person-badge"></i></div>
                            <div class="stat-info">
                                <h3><?= $faculty_count ?></h3>
                                <p>Active Faculty</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="data-card">
                    <div class="data-card-header">
                        <h2><i class="bi bi-clock-history"></i> Recently Added Students</h2>
                        <a href="../student-search.php" class="action-link">View all <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!$recent): ?>
                        <div class="empty-row">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;color:var(--light-gray)"></i>
                            No students yet. <a href="../student-profile.php?new=1">Add the first one</a>.
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Department</th>
                                    <th>Sport</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <div class="student-avatar"><?= h(initials($r['full_name'])) ?></div>
                                            <div>
                                                <div class="student-name"><?= h($r['full_name']) ?></div>
                                                <div class="student-meta"><?= h($r['enrollment_no']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="dept-tag"><?= h($r['dept_name']) ?></span></td>
                                    <td><span class="sport-tag"><?= h($r['sport_1'] ?: '—') ?></span></td>
                                    <td><span class="year-tag"><?= h($r['academic_year']) ?> · <?= h($r['study_year']) ?></span></td>
                                    <td>
                                        <a class="action-link" href="../student-profile.php?id=<?= (int)$r['id'] ?>">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
