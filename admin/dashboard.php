<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_guard.php';
require_once __DIR__ . '/../config/database.php';
dashboard_require_role(['admin', 'administrator', 'super_admin']);

$pageTitle = 'Admin Dashboard | Swasthya Saarathi';
$name = $_SESSION['user_name'] ?? 'Administrator';

$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalCases = (int)$pdo->query("SELECT COUNT(*) FROM triage_reports")->fetchColumn();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM triage_reports WHERE LOWER(COALESCE(status,'')) IN ('pending','waiting','pending_review','under_review')")->fetchColumn();
$reviewed = (int)$pdo->query("SELECT COUNT(*) FROM triage_reports WHERE LOWER(COALESCE(status,'')) IN ('reviewed','closed')")->fetchColumn();
$users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$high = (int)$pdo->query("SELECT COUNT(*) FROM triage_reports WHERE UPPER(COALESCE(priority,''))='HIGH'")->fetchColumn();

$recent = $pdo->query("SELECT t.id,t.triage_uid,t.priority,t.status,t.created_at,p.name patient_name FROM triage_reports t LEFT JOIN patients p ON p.id=t.patient_id ORDER BY t.created_at DESC LIMIT 8")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<style>
.db{min-height:calc(100vh - 100px);padding:42px 24px;background:#050714;color:#eef2ff}.wrap{max-width:1200px;margin:auto}.hero{display:flex;justify-content:space-between;gap:20px;align-items:end;margin-bottom:28px}.hero h1{font-size:34px;margin:0}.muted{color:#7f89a5}.role{padding:10px 14px;border:1px solid #26304d;border-radius:12px;background:#0c1124;color:#a99aff}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card{background:linear-gradient(145deg,#10162d,#090d1d);border:1px solid #202946;border-radius:18px;padding:20px}.num{font-size:30px;font-weight:800;margin:8px 0}.label{font-size:11px;color:#7f89a5}.actions{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}.action{display:block;text-decoration:none;color:#dfe5ff}.action strong{display:block;margin-bottom:7px}.action span{font-size:11px;color:#707b98}.table{margin-top:22px;overflow:auto}.table table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:13px;border-bottom:1px solid #1d253b;font-size:12px}.badge{padding:5px 8px;border-radius:999px;background:#171e36}.danger{color:#ff8ca1}@media(max-width:850px){.grid,.actions{grid-template-columns:repeat(2,1fr)}.hero{align-items:start;flex-direction:column}}@media(max-width:520px){.grid,.actions{grid-template-columns:1fr}.db{padding:28px 14px}}
</style>
<main class="db"><div class="wrap">
<div class="hero"><div><div class="muted">SWASTHYA SAARATHI · ADMIN</div><h1>Welcome, <?=dashboard_escape($name)?></h1><p class="muted">System-wide administration and healthcare operations.</p></div><div class="role">Administrator</div></div>
<div class="grid">
<div class="card"><div class="label">USERS</div><div class="num"><?=number_format($users)?></div></div>
<div class="card"><div class="label">PATIENTS</div><div class="num"><?=number_format($totalPatients)?></div></div>
<div class="card"><div class="label">TRIAGE CASES</div><div class="num"><?=number_format($totalCases)?></div></div>
<div class="card"><div class="label">PENDING REVIEWS</div><div class="num"><?=number_format($pending)?></div></div>
</div>
<div class="actions">
<a class="card action" href="../patients.php"><strong>Patients</strong><span>Manage registered patient records.</span></a>
<a class="card action" href="../queue.php"><strong>All Triage</strong><span>View the complete case queue.</span></a>
<a class="card action" href="../queue.php?status=pending"><strong>Pending Reviews</strong><span><?=number_format($pending)?> cases waiting.</span></a>
<a class="card action" href="../register.php"><strong>Create Patient Account</strong><span>Open public registration.</span></a>
</div>
<div class="grid"><div class="card"><div class="label">REVIEWED</div><div class="num"><?=number_format($reviewed)?></div></div><div class="card"><div class="label">HIGH PRIORITY</div><div class="num danger"><?=number_format($high)?></div></div></div>
<section class="card table"><h2>Recent Cases</h2><table><thead><tr><th>ID</th><th>Patient</th><th>Priority</th><th>Status</th><th>Date</th><th></th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=dashboard_escape($r['triage_uid'])?></td><td><?=dashboard_escape($r['patient_name'] ?? 'Unknown')?></td><td><?=dashboard_escape($r['priority'] ?? '—')?></td><td><span class="badge"><?=dashboard_escape($r['status'] ?? '—')?></span></td><td><?=dashboard_escape($r['created_at'])?></td><td><a href="../review.php?id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></section>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
