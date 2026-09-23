<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_guard.php';
require_once __DIR__ . '/../config/database.php';
dashboard_require_role(['doctor']);
$pageTitle='Doctor Dashboard | Swasthya Saarthi'; $name=$_SESSION['user_name']??'Doctor';
$pending=(int)$pdo->query("SELECT COUNT(*) FROM triage_reports WHERE LOWER(COALESCE(status,'')) IN
('pending','waiting','pending_review','under_review')")->fetchColumn(); $high=(int)$pdo->query("SELECT COUNT(*) FROM
triage_reports WHERE UPPER(COALESCE(priority,''))='HIGH'")->fetchColumn(); $reviewed=(int)$pdo->query("SELECT COUNT(*)
FROM triage_reports WHERE LOWER(COALESCE(status,'')) IN ('reviewed','closed')")->fetchColumn();
$recent=$pdo->query("SELECT t.id,t.triage_uid,t.priority,t.status,t.created_at,p.name patient_name FROM triage_reports t
LEFT JOIN patients p ON p.id=t.patient_id WHERE LOWER(COALESCE(t.status,'')) NOT IN ('reviewed','closed') ORDER BY
t.created_at ASC LIMIT 10")->fetchAll(); include __DIR__.'/../includes/header.php'; ?>
<style>
    .db {
        min-height: calc(100vh - 100px);
        padding: 42px 24px;
        background: #07110f;
        color: #effff9;
    }
    .wrap {
        max-width: 1150px;
        margin: auto;
    }
    .hero {
        display: flex;
        justify-content: space-between;
        align-items: end;
        margin-bottom: 28px;
    }
    .hero h1 {
        margin: 6px 0;
        font-size: 34px;
    }
    .muted {
        color: #78958c;
    }
    .role {
        padding: 10px 14px;
        border: 1px solid #20483e;
        border-radius: 12px;
        background: #0d201b;
        color: #62e5bd;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }
    .card {
        background: #0b1916;
        border: 1px solid #1b3932;
        border-radius: 18px;
        padding: 20px;
    }
    .num {
        font-size: 30px;
        font-weight: 800;
        margin-top: 8px;
    }
    .label {
        font-size: 11px;
        color: #78958c;
    }
    .actions {
        display: flex;
        gap: 12px;
        margin: 22px 0;
        flex-wrap: wrap;
    }
    .btn {
        padding: 12px 16px;
        border-radius: 10px;
        background: #1a8b70;
        color: white;
        text-decoration: none;
        font-size: 12px;
    }
    .table {
        margin-top: 22px;
        overflow: auto;
    }
    .table table {
        width: 100%;
        border-collapse: collapse;
    }
    .table th,
    .table td {
        text-align: left;
        padding: 13px;
        border-bottom: 1px solid #19312b;
        font-size: 12px;
    }
    .badge {
        padding: 5px 8px;
        border-radius: 999px;
        background: #142d26;
    }
    .danger {
        color: #ff9aa9;
    }
    @media (max-width: 650px) {
        .grid {
            grid-template-columns: 1fr;
        }
        .hero {
            flex-direction: column;
            align-items: start;
            gap: 15px;
        }
    }
</style>
<main class="db">
    <div class="wrap">
        <div class="hero">
            <div>
                <div class="muted">SWASTHYA SAARATHI · CLINICAL</div>
                <h1>Welcome, Dr. <?=dashboard_escape($name)?></h1>
                <p class="muted">Review triage cases and provide human clinical oversight.</p>
            </div>
            <div class="role">Doctor</div>
        </div>
        <div class="grid">
            <div class="card">
                <div class="label">PENDING REVIEW</div>
                <div class="num"><?=$pending?></div>
            </div>
            <div class="card">
                <div class="label">HIGH PRIORITY</div>
                <div class="num danger"><?=$high?></div>
            </div>
            <div class="card">
                <div class="label">REVIEWED</div>
                <div class="num"><?=$reviewed?></div>
            </div>
        </div>
        <div class="actions">
            <a class="btn" href="../queue.php?status=pending">Open Review Queue</a
            ><a class="btn" href="../patients.php">Patient Records</a
            >
        </div>
        <section class="card table">
            <h2>Cases Requiring Attention</h2>
            <table>
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Patient</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent as $r): ?>
                    <tr>
                        <td><?=dashboard_escape($r['triage_uid'])?></td>
                        <td><?=dashboard_escape($r['patient_name']??'Unknown')?></td>
                        <td><?=dashboard_escape($r['priority']??'—')?></td>
                        <td>
                            <span class="badge"><?=dashboard_escape($r['status']??'—')?></span>
                        </td>
                        <td><a href="../review.php?id=<?=(int)$r['id']?>">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>
