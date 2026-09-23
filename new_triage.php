<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Swasthya Saarathi - New Triage Case
 |--------------------------------------------------------------------------
 |
 | Existing patient:
 |   Patient UID -> AJAX lookup -> master data becomes read-only -> new visit
 |
 | New patient:
 |   New UID -> AJAX says not found -> fields stay editable -> patient + visit
 |
 | Scanner:
 |   QR / barcode camera scanner fills Patient Unique ID and triggers lookup.
 |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

require_login();

$user = $_SESSION['user'] ?? [];
$userRole = $user['role'] ?? $_SESSION['user_role'] ?? '';

/* --------------------------------------------------------------------------
 | Access Control
 * -------------------------------------------------------------------------- */
if (!in_array($userRole, ['admin', 'health_worker'], true)) {
    http_response_code(403);
    $pageTitle = 'Access Restricted | Swasthya Saarathi';
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="page-shell">
        <div class="container">
            <section class="card access-denied-card">
                <div class="service-number">!</div>
                <span class="eyebrow">ACCESS RESTRICTED</span>
                <h1>Triage access unavailable</h1>
                <p>Your account does not have permission to create a new triage case.</p>
                <p>Current role: <strong><?= htmlspecialchars($userRole ?: 'Not assigned', ENT_QUOTES, 'UTF-8') ?></strong></p>
                <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            </section>
        </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* --------------------------------------------------------------------------
 | Helpers
 * -------------------------------------------------------------------------- */
function valid_patient_uid(string $uid): bool
{
    return (bool) preg_match('/^SS-[0-9]{4}-[A-Z0-9]{6}$/i', $uid);
}

function generate_triage_uid(PDO $pdo): string
{
    do {
        $uid = 'TR-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $check = $pdo->prepare(
            'SELECT id FROM triage_reports WHERE triage_uid = ? LIMIT 1'
        );
        $check->execute([$uid]);
    } while ($check->fetchColumn());

    return $uid;
}

/* --------------------------------------------------------------------------
 | AJAX patient lookup
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lookup_patient') {
    header('Content-Type: application/json; charset=UTF-8');

    if (empty($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired security token.'
        ]);
        exit;
    }

    $patientUid = strtoupper(trim((string) ($_POST['patient_uid'] ?? '')));

    if ($patientUid === '' || !valid_patient_uid($patientUid)) {
        echo json_encode([
            'success' => true,
            'found' => false,
            'valid_uid' => false,
            'message' => 'Enter a valid Patient Unique ID.'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, patient_uid, name, age, gender, language, phone
             FROM patients
             WHERE patient_uid = ?
             LIMIT 1'
        );
        $stmt->execute([$patientUid]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            echo json_encode([
                'success' => true,
                'found' => false,
                'valid_uid' => true,
                'message' => 'Patient not found. This can be registered as a new patient.'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'found' => true,
            'valid_uid' => true,
            'message' => 'Existing patient found.',
            'patient' => [
                'patient_uid' => $patient['patient_uid'],
                'name' => $patient['name'] ?? '',
                'age' => $patient['age'] ?? '',
                'gender' => $patient['gender'] ?? '',
                'language' => $patient['language'] ?? 'English',
                'phone' => $patient['phone'] ?? ''
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to look up the patient.'
        ]);
        exit;
    }
}

/* --------------------------------------------------------------------------
 | Page state
 * -------------------------------------------------------------------------- */
$pageTitle = 'New Triage | Swasthya Saarathi';
$error = '';
$formPatientUid = '';
$formName = '';
$formAge = '';
$formGender = '';
$formLanguage = 'English';
$formPhone = '';
$formSymptoms = '';

/* --------------------------------------------------------------------------
 | Process form
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'lookup_patient') {
    if (empty($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        $patientUid = strtoupper(trim((string) ($_POST['patient_uid'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $ageRaw = $_POST['age'] ?? null;
        $age = filter_var($ageRaw, FILTER_VALIDATE_INT);
        $gender = trim((string) ($_POST['gender'] ?? ''));
        $language = trim((string) ($_POST['language'] ?? 'English')) ?: 'English';
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $symptoms = trim((string) ($_POST['symptoms'] ?? ''));

        $formPatientUid = $patientUid;
        $formName = $name;
        $formAge = $age !== false ? (string) $age : '';
        $formGender = $gender;
        $formLanguage = $language;
        $formPhone = $phone;
        $formSymptoms = $symptoms;

        if ($patientUid === '') {
            $error = 'Please enter the Patient Unique ID.';
        } elseif (!valid_patient_uid($patientUid)) {
            $error = 'Please enter a valid Patient Unique ID, for example SS-2026-49CA69.';
        } elseif ($symptoms === '') {
            $error = 'Please enter the patient symptoms.';
        } elseif (mb_strlen($symptoms) > 5000) {
            $error = 'Symptoms must not exceed 5000 characters.';
        } else {
            $uploadedFilePath = null;

            try {
                $pdo->beginTransaction();

                $patientStmt = $pdo->prepare(
                    'SELECT id, patient_uid, name, age, gender, language, phone
                     FROM patients
                     WHERE patient_uid = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $patientStmt->execute([$patientUid]);
                $existingPatient = $patientStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingPatient) {
                    $patientId = (int) $existingPatient['id'];
                    $patientUid = $existingPatient['patient_uid'];
                } else {
                    if ($name === '') {
                        throw new RuntimeException('Please enter the patient name for a new patient.');
                    }
                    if ($age === false || $age < 0 || $age > 120) {
                        throw new RuntimeException('Please enter a valid patient age for a new patient.');
                    }

                    $insertPatient = $pdo->prepare(
                        'INSERT INTO patients
                            (patient_uid, name, age, gender, language, phone)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insertPatient->execute([
                        $patientUid,
                        $name,
                        $age,
                        $gender ?: null,
                        $language,
                        $phone ?: null
                    ]);
                    $patientId = (int) $pdo->lastInsertId();
                }

                /* --------------------------------------------------------------
                 | Medical report upload
                 * -------------------------------------------------------------- */
                $reportPath = null;

                if (
                    isset($_FILES['medical_document']) &&
                    $_FILES['medical_document']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    $file = $_FILES['medical_document'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('The medical report could not be uploaded.');
                    }

                    if ((int) $file['size'] > 10 * 1024 * 1024) {
                        throw new RuntimeException('Medical report must be 10 MB or smaller.');
                    }

                    if (!is_uploaded_file($file['tmp_name'])) {
                        throw new RuntimeException('Invalid upload source.');
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);

                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowed[$mime])) {
                        throw new RuntimeException('Only JPG, PNG and WebP medical report images are allowed.');
                    }

                    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'medical_reports';

                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true)) {
                        throw new RuntimeException('Unable to create the medical report directory.');
                    }

                    $filename = 'report_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        throw new RuntimeException('Unable to save the medical report.');
                    }

                    $uploadedFilePath = $destination;
                    $reportPath = 'uploads/medical_reports/' . $filename;
                }

                $triageUid = generate_triage_uid($pdo);

                $triageStmt = $pdo->prepare(
                    'INSERT INTO triage_reports
                        (triage_uid, patient_id, symptoms, summary, urgency_flags,
                         missing_information, priority, status, report_path, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $triageStmt->execute([
                    $triageUid,
                    $patientId,
                    $symptoms,
                    'Pending structured review.',
                    'Pending human review.',
                    'Human reviewer to assess available information.',
                    'routine',
                    'pending',
                    $reportPath
                ]);

                $pdo->commit();

                header('Location: patients.php?id=' . urlencode($patientUid));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($uploadedFilePath && is_file($uploadedFilePath)) {
                    @unlink($uploadedFilePath);
                }

                $message = $e->getMessage();
                $error = stripos($message, 'duplicate') !== false
                    ? 'This Patient Unique ID was registered by another user. Please enter the ID again.'
                    : $message;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
:root {
    --ss-primary: #075ea8;
    --ss-primary-dark: #063d70;
    --ss-cyan: #13a7d8;
    --ss-ink: #102d43;
    --ss-muted: #6c7f90;
    --ss-border: #dce8f0;
    --ss-soft: #f5faff;
    --ss-success: #197044;
    --ss-warning: #8b6b16;
    --ss-danger: #a62e2e;
    --ss-shadow: 0 18px 55px rgba(10, 47, 78, .09);
}

.triage-page {
    padding: 34px 0 80px;
    background:
        radial-gradient(circle at 10% 5%, rgba(19,167,216,.06), transparent 24%),
        radial-gradient(circle at 95% 18%, rgba(7,94,168,.06), transparent 26%);
}

.triage-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(28px, 5vw, 48px);
    margin-bottom: 24px;
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 30px;
    color: #fff;
    background:
        radial-gradient(circle at 88% 15%, rgba(64,211,255,.25), transparent 28%),
        radial-gradient(circle at 68% 110%, rgba(0,176,255,.16), transparent 34%),
        linear-gradient(135deg, #052d58 0%, #075ea8 58%, #087cae 100%);
    box-shadow: 0 28px 70px rgba(3, 45, 82, .20);
}

.triage-hero::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    right: -70px;
    bottom: -110px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 50%;
    box-shadow: 0 0 0 28px rgba(255,255,255,.025), 0 0 0 56px rgba(255,255,255,.02);
}

.triage-badge {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border: 1px solid rgba(255,255,255,.20);
    border-radius: 999px;
    background: rgba(255,255,255,.10);
    backdrop-filter: blur(8px);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .04em;
}

.triage-hero h1 {
    position: relative;
    z-index: 1;
    margin: 11px 0 8px;
    font-size: clamp(31px, 5vw, 50px);
    line-height: 1.05;
    letter-spacing: -.025em;
}

.triage-hero p {
    position: relative;
    z-index: 1;
    max-width: 760px;
    margin: 0;
    color: rgba(255,255,255,.88);
    line-height: 1.7;
}

.triage-card {
    margin-bottom: 22px;
    padding: clamp(21px, 3vw, 31px);
    border: 1px solid var(--ss-border);
    border-radius: 25px;
    background: rgba(255,255,255,.97);
    box-shadow: var(--ss-shadow);
}

.triage-section-title {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
}

.triage-number {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
    flex: 0 0 45px;
    border: 1px solid #cfe7f7;
    border-radius: 15px;
    background: linear-gradient(145deg, #eff9ff, #e2f2fb);
    color: var(--ss-primary);
    font-weight: 900;
    box-shadow: inset 0 1px 0 #fff;
}

.triage-section-title h2 { margin: 0; color: var(--ss-ink); }
.triage-section-title p { margin: 4px 0 0; color: var(--ss-muted); }

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.form-group { margin-bottom: 18px; }
.form-group label { display:block; margin-bottom: 8px; color: #24435a; font-weight: 800; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--ss-border);
    border-radius: 14px;
    padding: 13px 14px;
    background: #fbfdff;
    color: var(--ss-ink);
    font: inherit;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s, transform .2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--ss-cyan);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(19,167,216,.10);
}

.form-group input:disabled,
.form-group select:disabled { opacity: 1; }

/* UID + scanner */
.uid-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: stretch;
}

.uid-input-wrap { position: relative; }
.uid-input-wrap input { padding-right: 48px; }

.uid-search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #89a0b1;
    pointer-events: none;
}

.scanner-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-width: 128px;
    padding: 0 17px;
    border: 1px solid #0b6ea8;
    border-radius: 14px;
    background: linear-gradient(135deg, #0876b9, #075b9d);
    color: #fff;
    font: inherit;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 9px 20px rgba(7,94,168,.18);
    transition: transform .18s, box-shadow .18s, filter .18s;
}

.scanner-button:hover { transform: translateY(-1px); filter: brightness(1.04); box-shadow: 0 12px 24px rgba(7,94,168,.22); }
.scanner-button:active { transform: translateY(0); }
.scanner-button svg { width: 20px; height: 20px; }

.patient-id-help { display:block; margin-top:8px; color:var(--ss-muted); font-size:13px; line-height:1.5; }

.patient-status {
    display:none;
    margin-top:11px;
    padding:12px 14px;
    border-radius:13px;
    font-size:14px;
    line-height:1.5;
}
.patient-status.loading { display:block; background:#eef8ff; border:1px solid #cfe8f7; color:#216487; }
.patient-status.existing { display:block; background:#edf9f2; border:1px solid #c8ead7; color:#21643b; }
.patient-status.new { display:block; background:#fff8e8; border:1px solid #f0dfad; color:#755d16; }
.patient-status.error { display:block; background:#fff0f0; border:1px solid #f0c7c7; color:#9b2929; }

.existing-field {
    background: #f2f7fb !important;
    color: #587080 !important;
    cursor: not-allowed;
}

.patient-id-note, .notice {
    margin-top: 17px;
    padding: 14px 16px;
    border-radius: 14px;
    background: #f1f8fc;
    border: 1px solid #d6eaf5;
    color: #526c7e;
    line-height: 1.6;
    font-size: 13px;
}

/* Voice */
.voice-box { position:relative; }
.voice-box textarea { min-height:190px; padding-right:76px; resize:vertical; }
.voice-button {
    position:absolute;
    right:13px;
    top:13px;
    width:48px;
    height:48px;
    border:0;
    border-radius:15px;
    background:#0876b9;
    color:#fff;
    font-size:19px;
    cursor:pointer;
    box-shadow:0 8px 20px rgba(11,114,185,.22);
}
.voice-button.is-listening { background:#d83c3c; animation:voicePulse 1.2s infinite; }
@keyframes voicePulse { 0%,100%{box-shadow:0 0 0 0 rgba(216,60,60,.35)} 50%{box-shadow:0 0 0 12px rgba(216,60,60,0)} }
.voice-meta { display:flex; justify-content:space-between; gap:12px; margin-top:8px; color:#68798a; font-size:13px; }
.language-hint { margin-top:8px; color:#0876b9; font-size:13px; font-weight:700; }

/* OCR */
.ocr-box { padding:22px; border:2px dashed #cfe0ec; border-radius:21px; background:#f7fbfe; }
.ocr-upload-label { display:inline-flex; align-items:center; gap:9px; padding:12px 17px; border-radius:13px; background:#0876b9; color:#fff; cursor:pointer; font-weight:800; box-shadow:0 7px 17px rgba(7,118,185,.16); }
.ocr-box input[type=file] { display:none; }
.ocr-help { margin:11px 0 0; color:#667789; font-size:13px; }
.ocr-status { margin-top:14px; padding:11px 13px; border:1px solid #e1ebf2; border-radius:12px; background:#fff; color:#53697b; }
.ocr-preview { display:none; max-width:100%; max-height:420px; margin-top:18px; border-radius:17px; box-shadow:0 15px 35px rgba(0,0,0,.12); }
.ocr-result { display:none; margin-top:18px; }
.ocr-result textarea { min-height:180px; }

/* Review */
.review-flow { display:flex; align-items:center; gap:11px; flex-wrap:wrap; }
.review-step { display:flex; align-items:center; gap:9px; padding:11px 14px; border:1px solid #e0eaf1; border-radius:14px; background:#f8fbfd; color:#29475d; }
.review-step span { display:grid; place-items:center; width:31px; height:31px; border-radius:50%; background:#dff1fb; color:#0876b9; font-weight:900; }
.review-arrow { color:#8ca2b3; font-size:18px; }

.form-error { margin-bottom:21px; padding:16px 19px; border:1px solid #f0c7c7; border-radius:15px; background:#fff0f0; color:#9b2929; }

.submit-bar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    padding:22px 24px;
    margin-top:24px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:23px;
    background:linear-gradient(135deg,#052d58,#075b9d);
    color:#fff;
    box-shadow:0 22px 50px rgba(4,45,82,.18);
}
.submit-bar strong { font-size:18px; }
.submit-bar p { margin:5px 0 0; opacity:.82; }
.submit-button { border:0; cursor:pointer; white-space:nowrap; }
.submit-button:disabled { opacity:.6; cursor:wait; }

/* Scanner modal */
.scanner-modal {
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(2,20,36,.72);
    backdrop-filter:blur(9px);
}
.scanner-modal.is-open { display:flex; }
.scanner-dialog {
    width:min(560px,100%);
    overflow:hidden;
    border:1px solid rgba(255,255,255,.15);
    border-radius:25px;
    background:#fff;
    box-shadow:0 35px 90px rgba(0,0,0,.28);
}
.scanner-dialog-head { display:flex; justify-content:space-between; align-items:center; gap:15px; padding:18px 20px; border-bottom:1px solid #e5edf3; }
.scanner-dialog-head h3 { margin:0; color:#102d43; }
.scanner-close { width:38px; height:38px; border:1px solid #dce8f0; border-radius:11px; background:#f7fafc; color:#496477; font-size:20px; cursor:pointer; }
.scanner-body { padding:18px; }
.scanner-frame { position:relative; overflow:hidden; aspect-ratio:4/3; border-radius:18px; background:#061b2b; }
#patient-scanner-video { display:block; width:100%; height:100%; object-fit:cover; }
.scanner-frame::before {
    content:"";
    position:absolute;
    z-index:2;
    inset:18% 13%;
    border:2px solid rgba(86,220,255,.95);
    border-radius:18px;
    box-shadow:0 0 0 999px rgba(0,0,0,.16);
    pointer-events:none;
}
.scanner-line { position:absolute; z-index:3; left:16%; right:16%; top:50%; height:2px; background:#49d7ff; box-shadow:0 0 15px #49d7ff; animation:scanLine 1.8s ease-in-out infinite; }
@keyframes scanLine { 0%,100%{transform:translateY(-65px)} 50%{transform:translateY(65px)} }
.scanner-status { margin:13px 0 0; padding:11px 13px; border-radius:12px; background:#f4f9fc; color:#526b7d; font-size:13px; }
.scanner-footnote { margin:12px 0 0; color:#718595; font-size:12px; line-height:1.5; }

@media (max-width:700px) {
    .triage-page { padding-top:18px; }
    .triage-hero { padding:27px 21px; border-radius:23px; }
    .triage-card { padding:20px; border-radius:20px; }
    .form-grid { grid-template-columns:1fr; gap:0; }
    .uid-search-row { grid-template-columns:1fr; }
    .scanner-button { min-height:49px; }
    .review-arrow { display:none; }
    .submit-bar { align-items:stretch; flex-direction:column; }
    .submit-button { width:100%; }
}
</style>

<main class="page-shell triage-page">
<div class="container">

<section class="triage-hero">
    <span class="triage-badge">● HUMAN REVIEW WORKFLOW</span>
    <h1>Start a new triage case</h1>
    <p>Search an existing Patient Unique ID or scan its QR/barcode. New patients can be registered directly from this visit.</p>
</section>

<?php if ($error): ?>
<div class="form-error" role="alert">
    <strong>Unable to create case</strong><br>
    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="triage-form" autocomplete="off">
<input type="hidden" name="csrf_token" id="csrf-token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

<section class="triage-card">
<div class="triage-section-title">
    <span class="triage-number">01</span>
    <div><h2>Patient information</h2><p>Search or scan the Patient Unique ID to load registered details.</p></div>
</div>

<div class="form-group">
<label for="patient_uid">Patient Unique ID <span aria-hidden="true">*</span></label>
<div class="uid-search-row">
    <div class="uid-input-wrap">
        <input type="text" id="patient_uid" name="patient_uid" maxlength="30" required autocomplete="off" inputmode="text" placeholder="SS-2026-49CA69" value="<?= htmlspecialchars($formPatientUid, ENT_QUOTES, 'UTF-8') ?>">
        <span class="uid-search-icon" aria-hidden="true">⌕</span>
    </div>
    <button type="button" class="scanner-button" id="open-scanner" aria-label="Scan Patient Unique ID">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2"/>
            <path d="M7 9h2v6H7zM11 9v6M14 9v6M17 9v6"/>
        </svg>
        Scan ID
    </button>
</div>
<small class="patient-id-help">Format: SS-YYYY-XXXXXX (last 6 characters can be letters or numbers). The scanner accepts QR codes and supported barcodes containing the Patient Unique ID.</small>
<div id="patient-status" class="patient-status" role="status" aria-live="polite"></div>
</div>

<div class="form-grid">
<div class="form-group"><label for="name">Patient name *</label><input type="text" id="name" name="name" maxlength="150" required placeholder="Enter patient name" value="<?= htmlspecialchars($formName, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="form-group"><label for="age">Age *</label><input type="number" id="age" name="age" min="0" max="120" required placeholder="Age" value="<?= htmlspecialchars($formAge, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="form-group"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Select</option><option value="Female" <?= $formGender === 'Female' ? 'selected' : '' ?>>Female</option><option value="Male" <?= $formGender === 'Male' ? 'selected' : '' ?>>Male</option><option value="Other" <?= $formGender === 'Other' ? 'selected' : '' ?>>Other</option><option value="Prefer not to say" <?= $formGender === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option></select></div>
<div class="form-group"><label for="language">Preferred language</label><select id="language" name="language" data-voice-language><option value="English" data-speech-lang="en-IN" <?= $formLanguage === 'English' ? 'selected' : '' ?>>English</option><option value="Hindi" data-speech-lang="hi-IN" <?= $formLanguage === 'Hindi' ? 'selected' : '' ?>>Hindi</option><option value="Marathi" data-speech-lang="mr-IN" <?= $formLanguage === 'Marathi' ? 'selected' : '' ?>>Marathi</option><option value="Tamil" data-speech-lang="ta-IN" <?= $formLanguage === 'Tamil' ? 'selected' : '' ?>>Tamil</option><option value="Telugu" data-speech-lang="te-IN" <?= $formLanguage === 'Telugu' ? 'selected' : '' ?>>Telugu</option><option value="Bengali" data-speech-lang="bn-IN" <?= $formLanguage === 'Bengali' ? 'selected' : '' ?>>Bengali</option><option value="Odia" data-speech-lang="or-IN" <?= $formLanguage === 'Odia' ? 'selected' : '' ?>>Odia</option></select><div class="language-hint" id="language-hint">🎤 Voice recognition: English (India)</div></div>
<div class="form-group"><label for="phone">Phone number</label><input type="tel" id="phone" name="phone" maxlength="20" placeholder="Enter phone number" value="<?= htmlspecialchars($formPhone, ENT_QUOTES, 'UTF-8') ?>"></div>
</div>

<div class="patient-id-note"><strong>Existing patient protection:</strong> When an existing Patient Unique ID is found, master patient information is loaded for reference and protected from editing. The new triage visit is stored separately.</div>
</section>

<section class="triage-card">
<div class="triage-section-title"><span class="triage-number">02</span><div><h2>Symptoms</h2><p>Enter symptoms manually or use the microphone.</p></div></div>
<div class="form-group">
<label for="symptoms">Patient symptoms *</label>
<div class="voice-box">
<textarea id="symptoms" name="symptoms" rows="8" maxlength="5000" required placeholder="Describe symptoms, duration, severity and relevant information..."><?= htmlspecialchars($formSymptoms, ENT_QUOTES, 'UTF-8') ?></textarea>
<button type="button" class="voice-button" data-voice-button data-voice-target="symptoms" data-language-select="language" data-voice-status="voice-status" aria-pressed="false" title="Start voice input">🎤</button>
</div>
<div class="voice-meta"><small id="voice-status">Voice input ready</small><small><span id="symptoms-count">0</span> / 5000</small></div>
</div>
<div class="notice"><strong>Multilingual voice:</strong> Select the patient's preferred language. The microphone recognition language will automatically change to the corresponding Indian language code.</div>
</section>

<section class="triage-card">
<div class="triage-section-title"><span class="triage-number">03</span><div><h2>Medical report & OCR</h2><p>Upload a photograph or scan and extract readable text.</p></div></div>
<div class="ocr-box">
<label for="medical_document" class="ocr-upload-label">📷 Choose medical report</label>
<input type="file" id="medical_document" name="medical_document" accept="image/jpeg,image/png,image/webp" data-ocr-input data-ocr-preview="ocr-preview" data-ocr-status="ocr-status">
<p class="ocr-help">JPG, PNG or WebP • Maximum 10 MB</p>
<div id="ocr-status" class="ocr-status">No document selected.</div>
<img id="ocr-preview" class="ocr-preview" src="" alt="Medical document preview">
<div id="ocr-result-box" class="ocr-result"><label for="ocr-result">Extracted text</label><textarea id="ocr-result" rows="8" readonly></textarea></div>
</div>
<div class="notice"><strong>OCR verification:</strong> OCR can make recognition mistakes. Always compare extracted information with the original medical document before using it.</div>
</section>

<section class="triage-card">
<div class="triage-section-title"><span class="triage-number">04</span><div><h2>Human review</h2><p>The submitted case enters the review workflow.</p></div></div>
<div class="review-flow"><div class="review-step"><span>1</span><strong>Information captured</strong></div><div class="review-arrow">→</div><div class="review-step"><span>2</span><strong>Case created</strong></div><div class="review-arrow">→</div><div class="review-step"><span>3</span><strong>Human review</strong></div></div>
<div class="notice"><strong>Important:</strong> Swasthya Saarathi is an information organization and triage-support system. It does not replace professional medical assessment or provide a diagnosis.</div>
</section>

<div class="submit-bar"><div><strong>Ready to create the case?</strong><p>This visit will be linked to the entered Patient Unique ID.</p></div><button type="submit" class="btn btn-primary submit-button" id="submit-triage">Create Triage Case</button></div>
</form>
</div>
</main>

<!-- Premium camera scanner -->
<div class="scanner-modal" id="scanner-modal" aria-hidden="true">
    <div class="scanner-dialog" role="dialog" aria-modal="true" aria-labelledby="scanner-title">
        <div class="scanner-dialog-head">
            <div><h3 id="scanner-title">Scan Patient ID</h3><small>Place the QR code or barcode inside the frame.</small></div>
            <button type="button" class="scanner-close" id="close-scanner" aria-label="Close scanner">×</button>
        </div>
        <div class="scanner-body">
            <div class="scanner-frame">
                <video id="patient-scanner-video" autoplay muted playsinline></video>
                <div class="scanner-line"></div>
            </div>
            <div class="scanner-status" id="scanner-status">Starting camera…</div>
            <p class="scanner-footnote">Camera scanning works when your browser supports the Barcode Detection API and the page is served over HTTPS (or localhost). If scanning is unavailable, enter the ID manually.</p>
        </div>
    </div>
</div>

<script src="js/app.js" defer></script>
<script src="js/voice.js" defer></script>
<script src="js/ocr.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('triage-form');
    const uidInput = document.getElementById('patient_uid');
    const statusBox = document.getElementById('patient-status');
    const csrfToken = document.getElementById('csrf-token');
    const nameInput = document.getElementById('name');
    const ageInput = document.getElementById('age');
    const genderInput = document.getElementById('gender');
    const languageInput = document.getElementById('language');
    const phoneInput = document.getElementById('phone');
    const symptoms = document.getElementById('symptoms');
    const counter = document.getElementById('symptoms-count');
    const languageHint = document.getElementById('language-hint');
    const submitButton = document.getElementById('submit-triage');

    /* ----------------------------------------------------------------------
     | Character counter
     * ---------------------------------------------------------------------- */
    if (symptoms && counter) {
        const updateCounter = () => { counter.textContent = symptoms.value.length; };
        symptoms.addEventListener('input', updateCounter);
        updateCounter();
    }

    /* ----------------------------------------------------------------------
     | Language hint
     * ---------------------------------------------------------------------- */
    const languageNames = {
        'en-IN': 'English (India)',
        'hi-IN': 'Hindi (India)',
        'mr-IN': 'Marathi (India)',
        'ta-IN': 'Tamil (India)',
        'te-IN': 'Telugu (India)',
        'bn-IN': 'Bengali (India)',
        'or-IN': 'Odia (India)'
    };

    function updateLanguageHint() {
        if (!languageInput || !languageHint) return;
        const selected = languageInput.options[languageInput.selectedIndex];
        const speechLanguage = selected?.dataset?.speechLang || 'en-IN';
        languageHint.textContent = '🎤 Voice recognition: ' + (languageNames[speechLanguage] || speechLanguage);
    }

    languageInput?.addEventListener('change', updateLanguageHint);
    updateLanguageHint();

    /* ----------------------------------------------------------------------
     | Patient modes
     * ---------------------------------------------------------------------- */
    function patientFields() {
        return [nameInput, ageInput, genderInput, languageInput, phoneInput].filter(Boolean);
    }

    function setExistingMode() {
        patientFields().forEach(function (element) {
            element.readOnly = element.tagName !== 'SELECT';
            element.disabled = element.tagName === 'SELECT';
            element.classList.add('existing-field');
        });
        nameInput.required = false;
        ageInput.required = false;
    }

    function setNewMode() {
        patientFields().forEach(function (element) {
            element.readOnly = false;
            element.disabled = false;
            element.classList.remove('existing-field');
        });
        nameInput.required = true;
        ageInput.required = true;
    }

    function clearPatientFields() {
        nameInput.value = '';
        ageInput.value = '';
        genderInput.value = '';
        languageInput.value = 'English';
        phoneInput.value = '';
        updateLanguageHint();
    }

    function showStatus(type, message) {
        statusBox.className = 'patient-status ' + type;
        statusBox.innerHTML = message;
    }

    /* ----------------------------------------------------------------------
     | AJAX lookup
     * ---------------------------------------------------------------------- */
    let lookupTimer = null;
    let lookupController = null;

    async function lookupPatient() {
        const uid = uidInput.value.trim().toUpperCase();
        uidInput.value = uid;

        if (!uid) {
            clearPatientFields();
            setNewMode();
            statusBox.className = 'patient-status';
            statusBox.textContent = '';
            return;
        }

        if (!/^SS-[0-9]{4}-[A-Z0-9]{6}$/i.test(uid)) {
            clearPatientFields();
            setNewMode();
            showStatus('error', '<strong>Invalid Patient ID.</strong> Use format SS-2026-XXXXXX, for example SS-2026-49CA69.');
            return;
        }

        showStatus('loading', '🔎 Checking Patient Unique ID…');

        lookupController?.abort();
        lookupController = new AbortController();

        try {
            const body = new URLSearchParams({
                action: 'lookup_patient',
                patient_uid: uid,
                csrf_token: csrfToken.value
            });

            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString(),
                credentials: 'same-origin',
                signal: lookupController.signal
            });

            if (!response.ok) throw new Error('Lookup request failed.');
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Unable to look up patient.');

            if (data.found && data.patient) {
                const patient = data.patient;
                nameInput.value = patient.name || '';
                ageInput.value = patient.age ?? '';
                genderInput.value = patient.gender || '';
                languageInput.value = patient.language || 'English';
                phoneInput.value = patient.phone || '';
                setExistingMode();
                updateLanguageHint();
                showStatus('existing', '✓ <strong>Existing patient found.</strong> Details loaded automatically. Master patient information is protected from editing.');
            } else {
                clearPatientFields();
                setNewMode();
                showStatus('new', '＋ <strong>New patient.</strong> This Patient Unique ID is not registered yet. Enter the patient details below.');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            clearPatientFields();
            setNewMode();
            showStatus('error', '⚠ <strong>Patient lookup failed.</strong> Please check your connection and try again.');
        }
    }

    uidInput?.addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
        clearTimeout(lookupTimer);
        if (/^SS-[0-9]{4}-[A-Z0-9]{6}$/i.test(this.value)) {
            lookupTimer = setTimeout(lookupPatient, 350);
        }
    });

    uidInput?.addEventListener('blur', function () {
        clearTimeout(lookupTimer);
        lookupPatient();
    });

    /* ----------------------------------------------------------------------
     | Premium QR / barcode scanner
     * ---------------------------------------------------------------------- */
    const openScannerButton = document.getElementById('open-scanner');
    const closeScannerButton = document.getElementById('close-scanner');
    const scannerModal = document.getElementById('scanner-modal');
    const scannerVideo = document.getElementById('patient-scanner-video');
    const scannerStatus = document.getElementById('scanner-status');
    let scannerStream = null;
    let scannerFrame = null;
    let barcodeDetector = null;

    function stopScanner() {
        if (scannerFrame) {
            cancelAnimationFrame(scannerFrame);
            scannerFrame = null;
        }
        if (scannerStream) {
            scannerStream.getTracks().forEach(track => track.stop());
            scannerStream = null;
        }
        if (scannerVideo) scannerVideo.srcObject = null;
        scannerModal.classList.remove('is-open');
        scannerModal.setAttribute('aria-hidden', 'true');
    }

    function normalizeScannedUid(value) {
        return String(value || '').trim().toUpperCase().replace(/\s+/g, '');
    }

    async function scanFrame() {
        if (!barcodeDetector || !scannerVideo.srcObject) return;
        try {
            const codes = await barcodeDetector.detect(scannerVideo);
            if (codes.length) {
                const value = normalizeScannedUid(codes[0].rawValue);
                if (/^SS-[0-9]{4}-[A-Z0-9]{6}$/i.test(value)) {
                    uidInput.value = value;
                    scannerStatus.textContent = '✓ Patient ID detected. Checking record…';
                    stopScanner();
                    lookupPatient();
                    uidInput.focus();
                    return;
                }
                scannerStatus.textContent = 'Code detected, but it is not a valid Patient Unique ID.';
            }
        } catch (e) {
            /* Camera frames can occasionally fail while the camera adjusts focus. */
        }
        scannerFrame = requestAnimationFrame(scanFrame);
    }

    async function startScanner() {
        scannerModal.classList.add('is-open');
        scannerModal.setAttribute('aria-hidden', 'false');

        if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
            scannerStatus.textContent = 'Camera access is not available in this browser. Please enter the ID manually.';
            return;
        }

        if (!('BarcodeDetector' in window)) {
            scannerStatus.textContent = 'This browser does not support built-in QR/barcode scanning. Please enter the ID manually.';
            return;
        }

        try {
            const supported = BarcodeDetector.getSupportedFormats
                ? await BarcodeDetector.getSupportedFormats()
                : [];
            const preferred = ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'data_matrix'];
            const formats = supported.length ? preferred.filter(f => supported.includes(f)) : ['qr_code', 'code_128'];
            barcodeDetector = new BarcodeDetector({ formats });

            scannerStatus.textContent = 'Requesting camera permission…';
            scannerStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            });

            scannerVideo.srcObject = scannerStream;
            await scannerVideo.play();
            scannerStatus.textContent = 'Point the rear camera at the Patient QR/barcode.';
            scannerFrame = requestAnimationFrame(scanFrame);
        } catch (error) {
            scannerStatus.textContent = error.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access and try again, or enter the ID manually.'
                : 'Unable to start the camera. Please enter the ID manually.';
            stopScanner();
            scannerModal.classList.add('is-open');
            scannerModal.setAttribute('aria-hidden', 'false');
        }
    }

    openScannerButton?.addEventListener('click', startScanner);
    closeScannerButton?.addEventListener('click', stopScanner);
    scannerModal?.addEventListener('click', function (event) {
        if (event.target === scannerModal) stopScanner();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && scannerModal.classList.contains('is-open')) stopScanner();
    });

    /* ----------------------------------------------------------------------
     | Submit protection
     * ---------------------------------------------------------------------- */
    form?.addEventListener('submit', function () {
        /* Disabled SELECTs are not submitted. Existing master values are not
           needed server-side, but enabling them keeps the request complete. */
        genderInput.disabled = false;
        languageInput.disabled = false;
        submitButton.disabled = true;
        submitButton.textContent = 'Creating case…';
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
