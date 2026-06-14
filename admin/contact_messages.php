<?php
/**
 * Super-admin inbox for public Sports Department contact messages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('SUPER_ADMIN');

$me = current_faculty();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0 || !in_array($action, ['read', 'unread', 'delete'], true)) {
        http_response_code(400);
        exit('Bad request.');
    }

    if ($action === 'delete') {
        $changed = db_execute('DELETE FROM contact_messages WHERE id = ?', [$id], 'i');
        flash_set('contact_admin', $changed ? 'Message deleted.' : 'Message not found.', $changed ? 'success' : 'error');
    } else {
        $isRead = $action === 'read' ? 1 : 0;
        $changed = db_execute('UPDATE contact_messages SET is_read = ? WHERE id = ?', [$isRead, $id], 'ii');
        flash_set('contact_admin', $changed ? 'Message status updated.' : 'No status change was needed.', 'success');
    }

    redirect('contact_messages.php');
}

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'unread', 'read'], true)) {
    $status = 'all';
}
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);

$where = [];
$params = [];
$types = '';
if ($status !== 'all') {
    $where[] = 'is_read = ?';
    $params[] = $status === 'read' ? 1 : 0;
    $types .= 'i';
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

$sql = 'SELECT id, name, email, phone, subject, message, INET6_NTOA(ip) AS ip_address,
               is_read, created_at
          FROM contact_messages'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY is_read ASC, created_at DESC LIMIT 200';
$messages = db_select($sql, $params, $types);
$unread = (int)(db_one('SELECT COUNT(*) AS n FROM contact_messages WHERE is_read = 0')['n'] ?? 0);
$flash = flash_get('contact_admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | Sports Portal</title>
    <?= csrf_meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= h(url('css/admin.css')) ?>">
    <style>
        :root{--navy:#1a365d;--navy-dark:#0f2744;--gold:#c9a227;--light:#e9ecef;--muted:#6c757d}
        *{box-sizing:border-box}body{margin:0;background:#f8f9fa;color:#212529;font-family:Inter,system-ui,sans-serif}
        .layout{display:flex;min-height:100vh}.sidebar{width:260px;background:linear-gradient(180deg,var(--navy-dark),var(--navy));color:#fff;flex-shrink:0}
        .brand{padding:1.25rem;border-bottom:1px solid rgba(255,255,255,.1);display:flex;gap:.75rem;align-items:center}.brand img{width:42px;height:42px;object-fit:contain}.brand strong{display:block}.brand span{font-size:.75rem;color:rgba(255,255,255,.55)}
        .nav-label{padding:1rem 1.5rem .35rem;color:rgba(255,255,255,.4);font-size:.68rem;font-weight:700;letter-spacing:1.4px;text-transform:uppercase}
        .side-link{display:flex;align-items:center;gap:.75rem;padding:.72rem 1.5rem;color:rgba(255,255,255,.7);text-decoration:none;border-left:3px solid transparent}.side-link:hover,.side-link.active{color:#fff;background:rgba(201,162,39,.12);border-left-color:var(--gold)}.side-link i{width:20px}.count{margin-left:auto;background:var(--gold);color:var(--navy-dark);border-radius:999px;padding:.1rem .45rem;font-size:.7rem;font-weight:700}
        main{flex:1;min-width:0}.topbar{height:72px;background:#fff;border-bottom:1px solid var(--light);display:flex;align-items:center;justify-content:space-between;padding:0 2rem}.content{padding:2rem;max-width:1400px;margin:auto}
        .page-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1.5rem}.page-head h1{font-size:1.55rem;color:var(--navy);font-weight:750}.page-head p{color:var(--muted);margin:0}
        .filters{display:flex;gap:.6rem;flex-wrap:wrap;background:#fff;border:1px solid var(--light);padding:1rem;border-radius:10px;margin-bottom:1rem}.filters input{min-width:250px;flex:1}.btn-navy{background:var(--navy);color:#fff}.btn-navy:hover{background:var(--navy-dark);color:#fff}
        .message{background:#fff;border:1px solid var(--light);border-left:4px solid transparent;border-radius:10px;padding:1.1rem;margin-bottom:.85rem}.message.unread{border-left-color:var(--gold);background:#fffdf6}.message-head{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}.sender{font-weight:700;color:var(--navy)}.meta{font-size:.8rem;color:var(--muted)}.subject{font-weight:650;margin:.7rem 0 .25rem}.body{white-space:pre-wrap;overflow-wrap:anywhere;color:#343a40}.actions{display:flex;gap:.45rem;justify-content:flex-end;margin-top:.9rem;flex-wrap:wrap}.actions form{margin:0}
        .alert-box{padding:.8rem 1rem;border-radius:8px;margin-bottom:1rem}.alert-box.success{background:#d1e7dd;color:#0a3622}.alert-box.error{background:#f8d7da;color:#842029}.empty{background:#fff;border:1px solid var(--light);border-radius:10px;padding:3rem;text-align:center;color:var(--muted)}
        @media(max-width:900px){.sidebar{display:none}.topbar,.content{padding-left:1rem;padding-right:1rem}.page-head{display:block}.filters input{min-width:100%}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <img src="<?= h(url('images/ytc-logo.png')) ?>" alt="YTC Logo">
            <div><strong>Sports Database</strong><span>Yashoda Technical Campus</span></div>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a class="side-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="side-link" href="../student-search.php"><i class="bi bi-search"></i> Search Students</a>
            <a class="side-link" href="provisional_list.php"><i class="bi bi-clipboard-check"></i> Provisional Players</a>
            <a class="side-link" href="final_list.php"><i class="bi bi-check-all"></i> Final Teams</a>
            <a class="side-link" href="jersey_dashboard.php"><i class="bi bi-person-badge"></i> Jersey Kit</a>
            <div class="nav-label">Site Content</div>
            <a class="side-link" href="notices_list.php"><i class="bi bi-megaphone"></i> Notices</a>
            <a class="side-link" href="achievements_list.php"><i class="bi bi-trophy"></i> Achievements</a>
            <a class="side-link active" href="contact_messages.php"><i class="bi bi-envelope"></i> Messages<?php if ($unread): ?><span class="count"><?= $unread ?></span><?php endif; ?></a>
            <div class="nav-label">Admin</div>
            <a class="side-link" href="faculty_manage.php"><i class="bi bi-people-fill"></i> Faculty Management</a>
            <a class="side-link" href="../index.php"><i class="bi bi-globe"></i> View Website</a>
        </nav>
    </aside>
    <main>
        <header class="topbar">
            <strong style="color:var(--navy)">Contact Messages</strong>
            <span class="meta">Signed in as <?= h($me['full_name']) ?></span>
        </header>
        <div class="content">
            <?php if ($flash): ?><div class="alert-box <?= $flash['level'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
            <div class="page-head">
                <div><h1>Public Contact Inbox</h1><p>Messages submitted through the public Sports Department page.</p></div>
                <span class="badge text-bg-warning"><?= $unread ?> unread</span>
            </div>
            <form class="filters" method="get" action="contact_messages.php">
                <input class="form-control" type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, email, subject, or message">
                <select class="form-select" name="status" style="max-width:160px">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All messages</option>
                    <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
                </select>
                <button class="btn btn-navy" type="submit"><i class="bi bi-search"></i> Filter</button>
                <?php if ($q !== '' || $status !== 'all'): ?><a class="btn btn-outline-secondary" href="contact_messages.php">Clear</a><?php endif; ?>
            </form>

            <?php if (!$messages): ?>
                <div class="empty"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No contact messages found.</div>
            <?php endif; ?>
            <?php foreach ($messages as $message): ?>
                <article class="message <?= (int)$message['is_read'] === 0 ? 'unread' : '' ?>">
                    <div class="message-head">
                        <div>
                            <span class="sender"><?= h($message['name']) ?></span>
                            <?php if ((int)$message['is_read'] === 0): ?><span class="badge text-bg-warning ms-1">New</span><?php endif; ?>
                            <div class="meta">
                                <a href="mailto:<?= h($message['email']) ?>"><?= h($message['email']) ?></a>
                                <?php if ($message['phone']): ?> | <?= h($message['phone']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="meta text-end">
                            <?= h(format_date($message['created_at'], 'd M Y, h:i A')) ?><br>
                            IP: <?= h($message['ip_address']) ?>
                        </div>
                    </div>
                    <div class="subject"><?= h($message['subject'] ?: 'No subject') ?></div>
                    <div class="body"><?= h($message['message']) ?></div>
                    <div class="actions">
                        <form method="post" action="contact_messages.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                            <input type="hidden" name="action" value="<?= (int)$message['is_read'] === 0 ? 'read' : 'unread' ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                <i class="bi <?= (int)$message['is_read'] === 0 ? 'bi-envelope-open' : 'bi-envelope' ?>"></i>
                                Mark <?= (int)$message['is_read'] === 0 ? 'read' : 'unread' ?>
                            </button>
                        </form>
                        <form method="post" action="contact_messages.php" onsubmit="return confirm('Delete this message permanently?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
