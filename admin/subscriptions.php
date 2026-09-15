<?php

declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['Active','Expired','Cancelled'];
if (!in_array($status, $allowedStatuses, true)) $status = '';

$conditions = [];
$params = [];

if ($status !== '') { $conditions[] = 's.status = ?'; $params[] = $status; }
if ($search !== '') {
    $like = '%' . $search . '%';
    $conditions[] = '(st.full_name LIKE ? OR st.email LIKE ? OR p.name LIKE ?)';
    array_push($params, $like, $like, $like);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $stmt = $conn->prepare("SELECT s.id,s.student_id,s.plan_id,s.start_date,s.end_date,s.status,s.created_at,st.full_name,st.email,p.name AS plan_name,p.duration_months,p.price AS plan_price FROM subscriptions s INNER JOIN students st ON st.id=s.student_id INNER JOIN subscription_plans p ON p.id=s.plan_id $where ORDER BY s.created_at DESC,s.id DESC");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    $counts = $conn->query("SELECT status,COUNT(*) total FROM subscriptions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $planCount = (int)$conn->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
} catch (Throwable $e) {
    error_log('Admin subscription listing failed: ' . $e->getMessage());
    $items = [];
    $counts = [];
    $planCount = 0;
}

function asub_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function asub_date($v): string { if (!$v) return '—'; $t=strtotime((string)$v); return $t?date('d M Y',$t):'—'; }
function asub_class($v): string { return $v==='Active'?'active':($v==='Expired'?'expired':'cancelled'); }

$page_title='Subscription Management | ExamSphere';
$page_css='admin-subjects.css';
include 'includes/header.php';
?>
<div class="dashboard-wrapper">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
<?php include 'includes/navbar.php'; ?>
<main class="dashboard-content">
<style>
.subscription-admin{--brown:#5D4037;--dark:#3E2723;--olive:#556B2F;--cream:#F5F5DC;--border:#E5DED3;--muted:#81776F}
.subscription-admin .sa-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:20px}
.subscription-admin .sa-kicker{font-size:10px;letter-spacing:1.6px;font-weight:900;color:var(--olive)}
.subscription-admin h1{margin:5px 0;font-weight:900;color:var(--dark)}
.subscription-admin .sa-head p{margin:0;color:var(--muted);font-size:12px}
.sa-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border-radius:10px;text-decoration:none;border:0;font-weight:800;font-size:11px}.sa-primary{background:var(--olive);color:white}.sa-secondary{background:#fff;color:var(--dark);border:1px solid var(--border)}
.sa-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}.sa-stat{background:rgba(255,255,255,.86);border:1px solid var(--border);border-radius:16px;padding:15px;box-shadow:0 10px 28px rgba(62,39,35,.05)}.sa-stat span{font-size:10px;color:var(--muted);display:block}.sa-stat strong{display:block;font-size:25px;color:var(--dark);margin-top:4px}
.sa-filter{display:flex;flex-wrap:wrap;gap:8px;padding:14px;background:#fff;border:1px solid var(--border);border-radius:16px;margin-bottom:16px}.sa-filter input{flex:1;min-width:220px;height:42px;border:1px solid var(--border);border-radius:10px;padding:0 12px;outline:0}.sa-filter select{height:42px;border:1px solid var(--border);border-radius:10px;padding:0 12px;background:#fff}.sa-table{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden}.sa-table table{width:100%;border-collapse:collapse}.sa-table th,.sa-table td{padding:13px 12px;border-bottom:1px solid #EEE9E1;text-align:left;font-size:10px}.sa-table th{background:#FAF7F1;color:#6B625A;font-size:9px;text-transform:uppercase;letter-spacing:.6px}.sa-table tbody tr:hover{background:#FCFAF5}.sa-name{font-weight:800;color:var(--dark)}.sa-sub{font-size:8px;color:var(--muted);margin-top:2px}.sa-status{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:8px;font-weight:900}.sa-status.active{background:#EEF4E7;color:#4F672C}.sa-status.expired{background:#FFF2D9;color:#87631C}.sa-status.cancelled{background:#F9ECEA;color:#9F4B44}.sa-actions{display:flex;gap:6px}.sa-icon{width:30px;height:30px;display:grid;place-items:center;border-radius:8px;text-decoration:none;background:#F6F2EB;color:var(--brown)}.sa-empty{padding:45px;text-align:center;color:var(--muted)}
@media(max-width:1000px){.sa-stats{grid-template-columns:repeat(2,1fr)}.sa-head{align-items:flex-start;flex-direction:column}.sa-table{overflow:auto}.sa-table table{min-width:850px}}
@media(max-width:560px){.sa-stats{grid-template-columns:1fr}.sa-filter input{min-width:100%}}
</style>
<div class="subscription-admin">
<div class="sa-head"><div><div class="sa-kicker"><i class="fa-solid fa-gem"></i> ACCESS CONTROL</div><h1>Subscription Management</h1><p>Manage student memberships without any online payment gateway.</p></div><a class="sa-btn sa-primary" href="subscriptions/add.php"><i class="fa-solid fa-plus"></i> Assign Subscription</a></div>
<div class="sa-stats">
<div class="sa-stat"><span>Total subscriptions</span><strong><?= (int)array_sum($counts) ?></strong></div>
<div class="sa-stat"><span>Active</span><strong><?= (int)($counts['Active']??0) ?></strong></div>
<div class="sa-stat"><span>Expired</span><strong><?= (int)($counts['Expired']??0) ?></strong></div>
<div class="sa-stat"><span>Subscription plans</span><strong><?= $planCount ?></strong></div>
</div>
<form class="sa-filter" method="get"><input name="search" value="<?= asub_e($search) ?>" placeholder="Search student, email or plan..."><select name="status"><option value="">All statuses</option><?php foreach($allowedStatuses as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button class="sa-btn sa-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button><a class="sa-btn sa-secondary" href="subscriptions.php">Reset</a></form>
<div class="sa-table">
<table><thead><tr><th>Student</th><th>Plan</th><th>Validity</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php if(!$items): ?><tr><td colspan="6"><div class="sa-empty"><i class="fa-regular fa-folder-open fa-2x mb-2"></i><br>No subscription records found.</div></td></tr><?php else: foreach($items as $item): ?>
<tr>
<td><div class="sa-name"><?= asub_e($item['full_name']) ?></div><div class="sa-sub"><?= asub_e($item['email']) ?></div></td>
<td><div class="sa-name"><?= asub_e($item['plan_name']) ?></div><div class="sa-sub"><?= (int)$item['duration_months'] ?> month(s)</div></td>
<td><div><?= asub_date($item['start_date']) ?> → <?= asub_date($item['end_date']) ?></div></td>
<td><span class="sa-status <?= asub_class($item['status']) ?>"><?= asub_e($item['status']) ?></span></td>
<td><?= asub_date($item['created_at']) ?></td>
<td><div class="sa-actions"><a class="sa-icon" title="View" href="subscriptions/view.php?id=<?= (int)$item['id'] ?>"><i class="fa-regular fa-eye"></i></a><a class="sa-icon" title="Edit" href="subscriptions/edit.php?id=<?= (int)$item['id'] ?>"><i class="fa-solid fa-pen"></i></a><a class="sa-icon" title="Status" href="subscriptions/status.php?id=<?= (int)$item['id'] ?>"><i class="fa-solid fa-toggle-on"></i></a><a class="sa-icon" title="Delete" href="subscriptions/delete.php?id=<?= (int)$item['id'] ?>" onclick="return confirm('Delete this subscription?')"><i class="fa-solid fa-trash"></i></a></div></td>
</tr>
<?php endforeach; endif; ?></tbody></table></div>
</div>
</main></div></div>
<script src="assets/js/admin-shell.js"></script>
</body></html>
