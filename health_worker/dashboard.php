<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_guard.php';
require_once __DIR__ . '/../config/database.php';
dashboard_require_role(['health_worker','healthcare_worker','staff','nurse']);
$pageTitle='Health Worker Dashboard | Swasthya Saarthi'; $name=$_SESSION['user_name']??'Health Worker';
$patients=(int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$cases=(int)$pdo->query("SELECT COUNT(*) FROM triage_reports")->fetchColumn();
$pending=(int)$pdo->query("SELECT COUNT(*) FROM triage_reports WHERE LOWER(COALESCE(status,'')) IN ('pending','waiting','pending_review','under_review')")->fetchColumn();
$recent=$pdo->query("SELECT t.id,t.triage_uid,t.priority,t.status,t.created_at,p.name patient_name FROM triage_reports t LEFT JOIN patients p ON p.id=t.patient_id ORDER BY t.created_at DESC LIMIT 10")->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<style>
.db{min-height:calc(100vh - 100px);padding:42px 24px;background:#08111a;color:#eef7ff}.wrap{max-width:1150px;margin:auto}.hero{display:flex;justify-content:space-between;align-items:end;margin-bottom:28px}.hero h1{margin:6px 0;font-size:34px}.muted{color:#7890a5}.role{padding:10px 14px;border:1px solid #21445c;border-radius:12px;background:#0b1c2a;color:#64d8ff}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.card{background:#0b1722;border:1px solid #1d3446;border-radius:18px;padding:20px}.num{font-size:30px;font-weight:800;margin-top:8px}.label{font-size:11px;color:#7890a5}.actions{display:flex;gap:12px;margin:22px 0;flex-wrap:wrap}.btn{padding:12px 16px;border-radius:10px;background:#176d91;color:white;text-decoration:none;font-size:12px}.table{margin-top:22px;overflow:auto}.table table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:13px;border-bottom:1px solid #1b2d3b;font-size:12px}.badge{padding:5px 8px;border-radius:999px;background:#132738}@media(max-width:650px){.grid{grid-template-columns:1fr}.hero{flex-direction:column;align-items:start;gap:15px}}
</style>
<main class="db"><div class="wrap"><div class="hero"><div><div class="muted">SWASTHYA SAARATHI · FIELD OPERATIONS</div><h1>Welcome, <?=dashboard_escape($name)?></h1><p class="muted">Register patients, create triage cases and track the operational queue.</p></div><div class="role">Health Worker</div></div>
<div class="grid"><div class="card"><div class="label">PATIENTS</div><div class="num"><?=$patients?></div></div><div class="card"><div class="label">TOTAL CASES</div><div class="num"><?=$cases?></div></div><div class="card"><div class="label">WAITING</div><div class="num"><?=$pending?></div></div></div>
<div class="actions"><a class="btn" href="../new_triage.php">Create Triage</a><a class="btn" href="../patients.php">Patients</a><a class="btn" href="../queue.php">Case Queue</a></div>
<section class="card table"><h2>Recent Triage Activity</h2><table><thead><tr><th>Case</th><th>Patient</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=dashboard_escape($r['triage_uid'])?></td><td><?=dashboard_escape($r['patient_name']??'Unknown')?></td><td><?=dashboard_escape($r['priority']??'—')?></td><td><span class="badge"><?=dashboard_escape($r['status']??'—')?></span></td><td><a href="../review.php?id=<?=(int)$r['id']?>">View</a></td></tr><?php endforeach; ?></tbody></table></section>
</div></main><?php include __DIR__.'/../includes/footer.php'; ?>
