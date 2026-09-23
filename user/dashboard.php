<?php

declare(strict_types=1);

/**
 * ============================================================
 * SWASTHYA SAARTHI
 * user/dashboard.php
 * ============================================================
 *
 * Patient Dashboard
 *
 * Patient information ALWAYS comes from:
 *
 *      patients
 *
 * Login user comes from:
 *
 *      users
 *
 * Supported session values:
 *
 *      user_id
 *      user_name
 *      patient_id
 *      patient_uid
 *
 * ============================================================
 */

require_once __DIR__ . '/../includes/dashboard_guard.php';
require_once __DIR__ . '/../config/database.php';


/* ============================================================
   PATIENT ROLE
============================================================ */

dashboard_require_role([
    'patient',
    'user',
    'user_patient',
    'beneficiary'
]);


$pageTitle = 'My Dashboard | Swasthya Saarthi';


/* ============================================================
   SESSION
============================================================ */

$userId = $_SESSION['user_id'] ?? null;

$name = trim(
    (string)($_SESSION['user_name'] ?? '')
);

$sessionPatientId = $_SESSION['patient_id'] ?? null;

$sessionPatientUid = trim(
    (string)($_SESSION['patient_uid'] ?? '')
);


$patient = null;

$cases = [];

$error = '';


/* ============================================================
   HELPERS
============================================================ */

function userDashboardEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function userDashboardDate($value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return userDashboardEscape($value);
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


function userDashboardStatusClass($status): string
{
    $status = strtolower(
        trim(
            str_replace(
                '-',
                '_',
                (string)$status
            )
        )
    );

    switch ($status) {

        case 'completed':
        case 'reviewed':
        case 'closed':
            return 'success';

        case 'high':
        case 'urgent':
        case 'critical':
            return 'danger';

        case 'medium':
        case 'in_review':
        case 'pending_review':
        case 'review_pending':
            return 'warning';

        case 'waiting':
        case 'pending':
            return 'info';

        default:
            return 'default';
    }
}


function userDashboardReadable($value): string
{
    if ($value === null) {
        return '';
    }

    if (is_array($value)) {

        $parts = [];

        foreach ($value as $item) {

            if (is_array($item)) {

                $parts[] = json_encode(
                    $item,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

            } else {

                $parts[] = (string)$item;
            }
        }

        return implode("\n", $parts);
    }

    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $decoded = json_decode(
        $value,
        true
    );

    if (json_last_error() === JSON_ERROR_NONE) {

        if (is_array($decoded)) {

            $parts = [];

            foreach ($decoded as $item) {

                if (is_array($item)) {

                    $parts[] = json_encode(
                        $item,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );

                } else {

                    $parts[] = (string)$item;
                }
            }

            return implode("\n", $parts);
        }

        if (is_string($decoded)) {
            return $decoded;
        }
    }

    return $value;
}


/* ============================================================
   LOAD PATIENT FROM patients TABLE
============================================================ */

try {

    /*
     * --------------------------------------------------------
     * STEP 1
     *
     * Session patient_id available?
     * --------------------------------------------------------
     */

    if (
        $sessionPatientId !== null
        &&
        $sessionPatientId !== ''
        &&
        is_numeric($sessionPatientId)
    ) {

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                patient_uid,
                name,
                age,
                gender,
                phone,
                language,
                created_at
            FROM patients
            WHERE id = ?
            LIMIT 1
            "
        );

        $stmt->execute([
            (int)$sessionPatientId
        ]);

        $patient = $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }


    /*
     * --------------------------------------------------------
     * STEP 2
     *
     * Session patient_uid available?
     * --------------------------------------------------------
     */

    if (
        !$patient
        &&
        $sessionPatientUid !== ''
    ) {

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                patient_uid,
                name,
                age,
                gender,
                phone,
                language,
                created_at
            FROM patients
            WHERE patient_uid = ?
            LIMIT 1
            "
        );

        $stmt->execute([
            $sessionPatientUid
        ]);

        $patient = $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }


    /*
     * --------------------------------------------------------
     * STEP 3
     *
     * Try to get patient mapping from users table.
     *
     * This works if your users table has either:
     *
     *      patient_id
     *
     * or
     *
     *      patient_uid
     *
     * --------------------------------------------------------
     */

    if (
        !$patient
        &&
        $userId !== null
        &&
        is_numeric($userId)
    ) {

        /*
         * Check users columns safely.
         */

        $columnStmt = $pdo->query(
            "SHOW COLUMNS FROM users"
        );

        $userColumns = $columnStmt->fetchAll(
            PDO::FETCH_COLUMN
        );


        /*
         * ----------------------------------------------------
         * users.patient_id
         * ----------------------------------------------------
         */

        if (
            in_array(
                'patient_id',
                $userColumns,
                true
            )
        ) {

            $stmt = $pdo->prepare(
                "
                SELECT patient_id
                FROM users
                WHERE id = ?
                LIMIT 1
                "
            );

            $stmt->execute([
                (int)$userId
            ]);

            $mappedPatientId =
                $stmt->fetchColumn();


            if (
                $mappedPatientId !== false
                &&
                $mappedPatientId !== null
                &&
                $mappedPatientId !== ''
            ) {

                $stmt = $pdo->prepare(
                    "
                    SELECT
                        id,
                        patient_uid,
                        name,
                        age,
                        gender,
                        phone,
                        language,
                        created_at
                    FROM patients
                    WHERE id = ?
                    LIMIT 1
                    "
                );

                $stmt->execute([
                    (int)$mappedPatientId
                ]);

                $patient = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );
            }
        }


        /*
         * ----------------------------------------------------
         * users.patient_uid
         * ----------------------------------------------------
         */

        if (
            !$patient
            &&
            in_array(
                'patient_uid',
                $userColumns,
                true
            )
        ) {

            $stmt = $pdo->prepare(
                "
                SELECT patient_uid
                FROM users
                WHERE id = ?
                LIMIT 1
                "
            );

            $stmt->execute([
                (int)$userId
            ]);

            $mappedPatientUid =
                trim(
                    (string)$stmt->fetchColumn()
                );


            if ($mappedPatientUid !== '') {

                $stmt = $pdo->prepare(
                    "
                    SELECT
                        id,
                        patient_uid,
                        name,
                        age,
                        gender,
                        phone,
                        language,
                        created_at
                    FROM patients
                    WHERE patient_uid = ?
                    LIMIT 1
                    "
                );

                $stmt->execute([
                    $mappedPatientUid
                ]);

                $patient = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );
            }
        }
    }


    /*
     * --------------------------------------------------------
     * STEP 4
     *
     * LAST FALLBACK:
     *
     * Match logged-in user's name with patients.name
     *
     * NOTE:
     * Same names can exist, so this should only be a temporary
     * fallback until users.patient_id is added.
     * --------------------------------------------------------
     */

    if (
        !$patient
        &&
        $name !== ''
    ) {

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                patient_uid,
                name,
                age,
                gender,
                phone,
                language,
                created_at
            FROM patients
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
            ORDER BY
                id DESC
            LIMIT 1
            "
        );

        $stmt->execute([
            $name
        ]);

        $patient = $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }


    /*
     * --------------------------------------------------------
     * STEP 5
     *
     * Patient found.
     *
     * Put correct patient values back into session.
     * --------------------------------------------------------
     */

    if ($patient) {

        $_SESSION['patient_id'] =
            (int)$patient['id'];

        $_SESSION['patient_uid'] =
            (string)$patient['patient_uid'];

        /*
         * Use actual patient name.
         */

        if (
            trim(
                (string)($patient['name'] ?? '')
            ) !== ''
        ) {

            $_SESSION['user_name'] =
                $patient['name'];

            $name =
                $patient['name'];
        }
    }


    /*
     * --------------------------------------------------------
     * STEP 6
     *
     * LOAD TRIAGE CASES
     * --------------------------------------------------------
     */

    if ($patient) {

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                triage_uid,
                patient_id,
                symptoms,
                duration,
                priority,
                status,
                urgency_flags,
                missing_information,
                summary,

                ocr_text,
                ocr_medicines,
                medicine_summary,

                prescription_text,
                prescription_path,

                review_notes,
                reviewer_name,
                reviewed_by,
                reviewed_at,

                report_path,
                ocr_document_path,
                uploaded_document,

                created_at

            FROM triage_reports

            WHERE patient_id = ?

            ORDER BY
                created_at DESC,
                id DESC

            LIMIT 20
            "
        );

        $stmt->execute([
            (int)$patient['id']
        ]);

        $cases = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

} catch (PDOException $e) {

    /*
     * Do not expose database error to patient.
     */

    $error =
        'Unable to load your healthcare record right now.';

    /*
     * For development only:
     *
     * error_log($e->getMessage());
     */
}


/* ============================================================
   STATISTICS
============================================================ */

$totalCases = count($cases);

$pendingCases = 0;

$reviewedCases = 0;

$highPriorityCases = 0;

$mediumPriorityCases = 0;

$lowPriorityCases = 0;

$ocrCases = 0;

$prescriptionCases = 0;


foreach ($cases as $case) {

    $status = strtolower(
        trim(
            str_replace(
                '-',
                '_',
                (string)($case['status'] ?? '')
            )
        )
    );


    $priority = strtolower(
        trim(
            (string)($case['priority'] ?? '')
        )
    );


    if (
        in_array(
            $status,
            [
                'pending',
                'waiting',
                'pending_review',
                'review_pending',
                'in_review'
            ],
            true
        )
    ) {

        $pendingCases++;
    }


    if (
        in_array(
            $status,
            [
                'reviewed',
                'completed',
                'closed'
            ],
            true
        )
    ) {

        $reviewedCases++;
    }


    if ($priority === 'high') {
        $highPriorityCases++;
    }


    if ($priority === 'medium') {
        $mediumPriorityCases++;
    }


    if ($priority === 'low') {
        $lowPriorityCases++;
    }


    if (
        trim(
            (string)($case['ocr_text'] ?? '')
        ) !== ''
        ||
        trim(
            (string)($case['ocr_medicines'] ?? '')
        ) !== ''
    ) {

        $ocrCases++;
    }


    if (
        trim(
            (string)($case['prescription_text'] ?? '')
        ) !== ''
        ||
        trim(
            (string)($case['prescription_path'] ?? '')
        ) !== ''
        ||
        trim(
            (string)($case['medicine_summary'] ?? '')
        ) !== ''
    ) {

        $prescriptionCases++;
    }
}


/* ============================================================
   PATIENT UID
============================================================ */

$patientUid = '';

if ($patient) {

    $patientUid = trim(
        (string)(
            $patient['patient_uid'] ?? ''
        )
    );
}


/* ============================================================
   QR
============================================================ */

/*
 * IMPORTANT:
 *
 * QR always uses permanent patient_uid.
 *
 * Example:
 *
 * SS-2026-000001
 *
 */

$qrBaseUrl =
    'http://localhost/swasthya_saarthi/patients.php?id=';


$qrContent =
    $qrBaseUrl .
    rawurlencode($patientUid);


/*
 * Only generate QR when UID exists.
 */

$qrImageUrl = '';

if ($patientUid !== '') {

    $qrImageUrl =
        'https://api.qrserver.com/v1/create-qr-code/'
        . '?size=320x320'
        . '&margin=10'
        . '&data='
        . rawurlencode($qrContent);
}


/* ============================================================
   HEADER
============================================================ */

include __DIR__ . '/../includes/header.php';

?>

<style>

/* ============================================================
   ROOT
============================================================ */

:root {

    --ud-bg: #080b12;

    --ud-card: #101722;

    --ud-card-2: #131c29;

    --ud-border: #263344;

    --ud-text: #f5f8fc;

    --ud-muted: #8d9bae;

    --ud-blue: #1684d8;

    --ud-cyan: #00b8b8;

    --ud-purple: #765cff;

    --ud-green: #28b77a;

    --ud-yellow: #e6a83d;

    --ud-red: #e15c67;

    --ud-shadow:
        0 20px 50px rgba(0,0,0,.22);
}


/* ============================================================
   PAGE
============================================================ */

.db {

    min-height:
        calc(100vh - 100px);

    padding:
        40px 20px 70px;

    background:

        radial-gradient(
            circle at 0% 0%,
            rgba(0,184,184,.12),
            transparent 28%
        ),

        radial-gradient(
            circle at 100% 0%,
            rgba(118,92,255,.12),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #080b12,
            #0b111a
        );

    color: var(--ud-text);
}


.wrap {

    width:
        min(1180px, 100%);

    margin:
        0 auto;
}


/* ============================================================
   HERO
============================================================ */

.hero {

    display: flex;

    align-items: flex-end;

    justify-content:
        space-between;

    gap: 20px;

    margin-bottom:
        24px;
}

.hero-kicker {

    color:
        #7f91a5;

    font-size:
        10px;

    font-weight:
        900;

    letter-spacing:
        1.5px;
}

.hero h1 {

    margin:
        7px 0;

    font-size:
        clamp(27px, 5vw, 39px);

    letter-spacing:
        -.7px;
}

.hero p {

    margin:
        0;

    color:
        var(--ud-muted);

    font-size:
        13px;
}

.role {

    padding:
        10px 14px;

    border:
        1px solid #3d3560;

    border-radius:
        12px;

    background:
        #171329;

    color:
        #c5b8ff;

    font-size:
        11px;

    font-weight:
        900;

    white-space:
        nowrap;
}


/* ============================================================
   ERROR
============================================================ */

.error-card {

    margin-bottom:
        18px;

    padding:
        16px 18px;

    border:
        1px solid #62333a;

    border-radius:
        15px;

    background:
        #211217;

    color:
        #ffb5bc;

    font-size:
        12px;
}


/* ============================================================
   CARD
============================================================ */

.card {

    background:
        linear-gradient(
            145deg,
            rgba(16,23,34,.98),
            rgba(13,19,29,.98)
        );

    border:
        1px solid var(--ud-border);

    border-radius:
        19px;

    padding:
        20px;

    box-shadow:
        var(--ud-shadow);
}


/* ============================================================
   PROFILE
============================================================ */

.profile {

    display:
        grid;

    grid-template-columns:
        1.5fr
        repeat(4, 1fr);

    gap:
        12px;

    margin-bottom:
        14px;
}

.profile-item {

    min-width:
        0;

    padding:
        14px;

    border:
        1px solid #263344;

    border-radius:
        13px;

    background:
        rgba(255,255,255,.018);
}

.label {

    display:
        block;

    color:
        #78889a;

    font-size:
        9px;

    font-weight:
        900;

    letter-spacing:
        .8px;
}

.value {

    margin-top:
        7px;

    color:
        #edf4fb;

    font-size:
        14px;

    font-weight:
        800;

    word-break:
        break-word;
}


/* ============================================================
   PATIENT UID
============================================================ */

.uid-card {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    margin-bottom:
        18px;

    padding:
        17px 19px;

    border:
        1px solid #294b66;

    border-radius:
        17px;

    background:
        linear-gradient(
            135deg,
            rgba(22,132,216,.12),
            rgba(0,184,184,.08)
        );
}

.uid-label {

    color:
        #8ba5bb;

    font-size:
        9px;

    font-weight:
        900;

    letter-spacing:
        1px;
}

.uid-value {

    margin-top:
        5px;

    color:
        #72d9ff;

    font-size:
        19px;

    font-weight:
        950;

    letter-spacing:
        .4px;
}

.uid-actions {

    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;
}


/* ============================================================
   BUTTON
============================================================ */

.btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-height:
        38px;

    padding:
        0 14px;

    border:
        0;

    border-radius:
        10px;

    background:
        linear-gradient(
            135deg,
            #765cff,
            #1684d8
        );

    color:
        white;

    text-decoration:
        none;

    font-size:
        11px;

    font-weight:
        900;

    cursor:
        pointer;
}

.btn-secondary {

    background:
        #182331;

    border:
        1px solid #314153;

    color:
        #bdd0e1;
}


/* ============================================================
   STAT GRID
============================================================ */

.stats-grid {

    display:
        grid;

    grid-template-columns:
        repeat(6, minmax(0, 1fr));

    gap:
        12px;

    margin-bottom:
        20px;
}

.stat-card {

    padding:
        17px;

    background:
        #101722;

    border:
        1px solid #263344;

    border-radius:
        15px;
}

.stat-label {

    display:
        block;

    margin-bottom:
        9px;

    color:
        #78889a;

    font-size:
        8px;

    font-weight:
        900;

    letter-spacing:
        .7px;
}

.stat-number {

    display:
        block;

    color:
        #f1f6fb;

    font-size:
        25px;

    line-height:
        1;

    font-weight:
        950;
}


/* ============================================================
   QR
============================================================ */

.qr-section {

    display:
        grid;

    grid-template-columns:
        1fr
        190px;

    gap:
        20px;

    align-items:
        center;

    margin-bottom:
        20px;
}

.qr-content h2 {

    margin:
        0 0 7px;

    font-size:
        19px;
}

.qr-content p {

    margin:
        0;

    max-width:
        650px;

    color:
        var(--ud-muted);

    font-size:
        12px;

    line-height:
        1.6;
}

.qr-box {

    display:
        flex;

    flex-direction:
        column;

    align-items:
        center;

    gap:
        10px;

    padding:
        13px;

    background:
        white;

    border-radius:
        15px;
}

.qr-box img {

    display:
        block;

    width:
        160px;

    height:
        160px;

    object-fit:
        contain;
}

.qr-caption {

    color:
        #273747;

    font-size:
        9px;

    font-weight:
        900;

    text-align:
        center;
}


/* ============================================================
   CASE HEADER
============================================================ */

.section-heading {

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        15px;

    margin:
        24px 0 13px;
}

.section-heading h2 {

    margin:
        4px 0 5px;

    font-size:
        21px;
}

.section-heading p {

    margin:
        0;

    color:
        var(--ud-muted);

    font-size:
        11px;
}

.section-kicker {

    color:
        #718398;

    font-size:
        9px;

    font-weight:
        900;

    letter-spacing:
        1px;
}


/* ============================================================
   CASE LIST
============================================================ */

.case-list {

    display:
        grid;

    gap:
        13px;
}

.case-card {

    overflow:
        hidden;

    background:
        #101722;

    border:
        1px solid #263344;

    border-radius:
        18px;
}

.case-top {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        17px 19px;

    border-bottom:
        1px solid #202c3b;
}

.case-id {

    color:
        #ecf5fd;

    font-size:
        14px;

    font-weight:
        900;
}

.case-date {

    margin-top:
        5px;

    color:
        #718296;

    font-size:
        9px;
}

.case-badges {

    display:
        flex;

    gap:
        6px;

    flex-wrap:
        wrap;

    justify-content:
        flex-end;
}


/* ============================================================
   BADGES
============================================================ */

.badge {

    display:
        inline-flex;

    align-items:
        center;

    min-height:
        24px;

    padding:
        0 9px;

    border-radius:
        999px;

    background:
        #211b37;

    color:
        #c7baff;

    font-size:
        9px;

    font-weight:
        900;

    text-transform:
        uppercase;
}

.badge.success {

    background:
        #123728;

    color:
        #62dba4;
}

.badge.info {

    background:
        #112d43;

    color:
        #66c8ff;
}

.badge.warning {

    background:
        #3c2e16;

    color:
        #f1c667;
}

.badge.danger {

    background:
        #401d23;

    color:
        #ff8d98;
}

.badge.default {

    background:
        #202b39;

    color:
        #aebccc;
}


/* ============================================================
   CASE BODY
============================================================ */

.case-body {

    padding:
        18px 19px;
}

.case-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap:
        10px;
}

.case-info {

    padding:
        13px;

    background:
        #0d141e;

    border:
        1px solid #202d3d;

    border-radius:
        13px;
}

.case-info-label {

    color:
        #6f8193;

    font-size:
        8px;

    font-weight:
        900;

    letter-spacing:
        .7px;

    text-transform:
        uppercase;
}

.case-info-value {

    margin-top:
        6px;

    color:
        #cbd8e4;

    font-size:
        11px;

    line-height:
        1.55;

    white-space:
        pre-line;

    overflow-wrap:
        anywhere;
}


/* ============================================================
   OCR MINI
============================================================ */

.case-extras {

    display:
        grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap:
        10px;

    margin-top:
        11px;
}

.extra {

    padding:
        12px;

    background:
        #111a26;

    border:
        1px solid #253344;

    border-radius:
        12px;
}

.extra-title {

    margin-bottom:
        6px;

    color:
        #718397;

    font-size:
        8px;

    font-weight:
        900;

    letter-spacing:
        .6px;

    text-transform:
        uppercase;
}

.extra-text {

    color:
        #b9c8d7;

    font-size:
        10px;

    line-height:
        1.55;

    white-space:
        pre-line;

    max-height:
        85px;

    overflow:
        auto;
}


/* ============================================================
   CASE FOOTER
============================================================ */

.case-footer {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    padding:
        12px 19px;

    background:
        #0d141d;

    border-top:
        1px solid #202c3b;
}

.reviewed {

    color:
        #7890a4;

    font-size:
        9px;
}

.case-actions {

    display:
        flex;

    gap:
        7px;

    flex-wrap:
        wrap;

    justify-content:
        flex-end;
}


/* ============================================================
   EMPTY
============================================================ */

.empty {

    padding:
        45px 20px;

    text-align:
        center;
}

.empty-icon {

    font-size:
        34px;

    margin-bottom:
        12px;
}

.empty h2 {

    margin:
        0 0 7px;

    font-size:
        20px;
}

.empty p {

    margin:
        0;

    color:
        var(--ud-muted);

    font-size:
        11px;
}


/* ============================================================
   NOTICE
============================================================ */

.notice {

    margin-top:
        18px;

    padding:
        15px 17px;

    border:
        1px solid #3b344b;

    border-radius:
        13px;

    background:
        #12101a;

    color:
        #9e96ae;

    font-size:
        10px;

    line-height:
        1.65;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1050px) {

    .profile {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }
}


@media (max-width: 800px) {

    .qr-section {

        grid-template-columns:
            1fr;
    }

    .qr-box {

        width:
            190px;

        justify-self:
            center;
    }

    .case-grid {

        grid-template-columns:
            1fr;
    }

    .case-extras {

        grid-template-columns:
            1fr;
    }
}


@media (max-width: 650px) {

    .db {

        padding:
            25px 12px 50px;
    }

    .hero {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

    .profile {

        grid-template-columns:
            1fr;
    }

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .uid-card {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

    .case-top {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

    .case-badges {

        justify-content:
            flex-start;
    }

    .case-footer {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

    .case-actions {

        justify-content:
            flex-start;
    }
}

</style>


<main class="db">

<div class="wrap">


<!-- ============================================================
     HERO
============================================================ -->

<div class="hero">

    <div>

        <div class="hero-kicker">
            SWASTHYA SAARTHI · PERSONAL HEALTHCARE
        </div>

        <h1>
            Hello,
            <?= userDashboardEscape($name) ?>
        </h1>

        <p>
            Your personal healthcare and triage workspace.
        </p>

    </div>

    <div class="role">
        👤 Patient
    </div>

</div>


<?php if ($error !== ''): ?>

    <div class="error-card">
        ⚠️ <?= userDashboardEscape($error) ?>
    </div>

<?php endif; ?>


<?php if (!$patient): ?>


<!-- ============================================================
     PROFILE NOT LINKED
============================================================ -->

<section class="card empty">

    <div class="empty-icon">
        🔗
    </div>

    <h2>
        Profile not linked
    </h2>

    <p>
        Your account is logged in, but it is not linked
        to a patient profile yet.
    </p>

    <p style="margin-top:8px;">
        Please contact an administrator to link your
        Patient Unique ID with this account.
    </p>

</section>


<?php else: ?>


<!-- ============================================================
     PATIENT PROFILE
============================================================ -->

<section class="card profile">

    <div class="profile-item">

        <span class="label">
            PATIENT UNIQUE ID
        </span>

        <span class="value">
            <?= userDashboardEscape(
                $patient['patient_uid']
            ) ?>
        </span>

    </div>


    <div class="profile-item">

        <span class="label">
            NAME
        </span>

        <span class="value">
            <?= userDashboardEscape(
                $patient['name'] ?? '—'
            ) ?>
        </span>

    </div>


    <div class="profile-item">

        <span class="label">
            AGE
        </span>

        <span class="value">
            <?= userDashboardEscape(
                $patient['age'] ?? '—'
            ) ?>
        </span>

    </div>


    <div class="profile-item">

        <span class="label">
            GENDER
        </span>

        <span class="value">
            <?= userDashboardEscape(
                $patient['gender'] ?? '—'
            ) ?>
        </span>

    </div>


    <div class="profile-item">

        <span class="label">
            LANGUAGE
        </span>

        <span class="value">
            <?= userDashboardEscape(
                $patient['language'] ?? '—'
            ) ?>
        </span>

    </div>

</section>


<!-- ============================================================
     PERMANENT UID
============================================================ -->

<section class="uid-card">

    <div>

        <div class="uid-label">
            YOUR PERMANENT PATIENT UNIQUE ID
        </div>

        <div class="uid-value">
            <?= userDashboardEscape(
                $patientUid
            ) ?>
        </div>

    </div>


</section>


<!-- ============================================================
     STATISTICS
============================================================ -->

<section class="stats-grid">

    <div class="stat-card">

        <span class="stat-label">
            MY CASES
        </span>

        <span class="stat-number">
            <?= $totalCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            PENDING
        </span>

        <span class="stat-number">
            <?= $pendingCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            REVIEWED
        </span>

        <span class="stat-number">
            <?= $reviewedCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            HIGH PRIORITY
        </span>

        <span class="stat-number">
            <?= $highPriorityCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            OCR CASES
        </span>

        <span class="stat-number">
            <?= $ocrCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            PRESCRIPTIONS
        </span>

        <span class="stat-number">
            <?= $prescriptionCases ?>
        </span>

    </div>

</section>


<!-- ============================================================
     QR CODE
============================================================ -->

<section class="card qr-section">

    <div class="qr-content">

        <div class="section-kicker">
            PATIENT IDENTIFICATION
        </div>

        <h2>
            Your Patient QR Code
        </h2>

        <p>
            This QR code represents your permanent Patient
            Unique ID. Healthcare staff can scan it and use
            the Patient Unique ID to open your patient record.
        </p>


        <?php if ($patientUid !== ''): ?>

            <p style="margin-top:10px;">

                <strong>
                    Patient ID:
                </strong>

                <?= userDashboardEscape($patientUid) ?>

            </p>


            <div
                style="
                    margin-top:15px;
                    display:flex;
                    gap:8px;
                    flex-wrap:wrap;
                "
            >

                <a
                    href="../patients.php?id=<?= urlencode($patientUid) ?>"
                    class="btn btn-secondary"
                >
                    👁 View Full Record
                </a>


                <?php if ($qrImageUrl !== ''): ?>

                    <a
                        href="<?= userDashboardEscape($qrImageUrl) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn"
                    >
                        🔳 Open QR
                    </a>


                    <a
                        href="<?= userDashboardEscape($qrImageUrl) ?>"
                        download="patient-<?= userDashboardEscape($patientUid) ?>-qr.png"
                        class="btn btn-secondary"
                    >
                        ⬇ Download QR
                    </a>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <p
                style="
                    margin-top:15px;
                    color:#ff9ca5;
                "
            >
                Patient Unique ID is not available.
                Please contact the administrator.
            </p>

        <?php endif; ?>

    </div>


    <?php if ($qrImageUrl !== ''): ?>

        <div class="qr-box">

            <img
                src="<?= userDashboardEscape($qrImageUrl) ?>"
                alt="QR Code for <?= userDashboardEscape($patientUid) ?>"
                loading="lazy"
            >

            <div class="qr-caption">

                <?= userDashboardEscape($patientUid) ?>

            </div>

        </div>

    <?php endif; ?>

</section>

<!-- ============================================================
     TRIAGE CASES
============================================================ -->

<div class="section-heading">

    <div>

        <div class="section-kicker">
            HEALTHCARE HISTORY
        </div>

        <h2>
            My Triage Cases
        </h2>

        <p>
            Your recent healthcare and triage records.
        </p>

    </div>

</div>


<?php if (empty($cases)): ?>


<section class="card empty">

    <div class="empty-icon">
        📋
    </div>

    <h2>
        No triage cases yet
    </h2>

    <p>
        You do not have any triage cases in your
        patient record yet.
    </p>

    <div style="margin-top:18px;">

        <a
            href="../new_triage.php?patient_id=<?= (int)$patient['id'] ?>"
            class="btn"
        >
            ➕ Start New Triage
        </a>

    </div>

</section>


<?php else: ?>


<div class="case-list">


<?php foreach ($cases as $case): ?>

<?php

$caseStatus =
    strtolower(
        trim(
            str_replace(
                '-',
                '_',
                (string)($case['status'] ?? '')
            )
        )
    );

$casePriority =
    strtolower(
        trim(
            (string)($case['priority'] ?? '')
        )
    );

$statusClass =
    userDashboardStatusClass(
        $caseStatus
    );

$priorityClass =
    userDashboardStatusClass(
        $casePriority
    );

$summary =
    userDashboardReadable(
        $case['summary'] ?? ''
    );

$ocrText =
    userDashboardReadable(
        $case['ocr_text'] ?? ''
    );

$ocrMedicines =
    userDashboardReadable(
        $case['ocr_medicines'] ?? ''
    );

$medicineSummary =
    userDashboardReadable(
        $case['medicine_summary'] ?? ''
    );

$prescriptionText =
    userDashboardReadable(
        $case['prescription_text'] ?? ''
    );

?>


<article class="case-card">


<!-- ========================================================
     CASE TOP
========================================================= -->

<div class="case-top">

    <div>

        <div class="case-id">

            <?= userDashboardEscape(
                $case['triage_uid'] ?? 'Triage Case'
            ) ?>

        </div>

        <div class="case-date">

            Created:
            <?= userDashboardDate(
                $case['created_at'] ?? ''
            ) ?>

        </div>

    </div>


    <div class="case-badges">

        <?php if ($casePriority !== ''): ?>

            <span
                class="badge <?= userDashboardEscape(
                    $priorityClass
                ) ?>"
            >
                <?= userDashboardEscape(
                    ucfirst($casePriority)
                ) ?>
            </span>

        <?php endif; ?>


        <?php if ($caseStatus !== ''): ?>

            <span
                class="badge <?= userDashboardEscape(
                    $statusClass
                ) ?>"
            >
                <?= userDashboardEscape(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $caseStatus
                        )
                    )
                ) ?>
            </span>

        <?php endif; ?>

    </div>

</div>


<!-- ========================================================
     CASE BODY
========================================================= -->

<div class="case-body">

    <div class="case-grid">


        <div class="case-info">

            <div class="case-info-label">
                Symptoms
            </div>

            <div class="case-info-value">

                <?= userDashboardEscape(
                    $case['symptoms'] ?? 'Not recorded'
                ) ?>

            </div>

        </div>


        <div class="case-info">

            <div class="case-info-label">
                Duration
            </div>

            <div class="case-info-value">

                <?= userDashboardEscape(
                    $case['duration'] ?? 'Not recorded'
                ) ?>

            </div>

        </div>


        <div class="case-info">

            <div class="case-info-label">
                Summary
            </div>

            <div class="case-info-value">

                <?= $summary !== ''
                    ? nl2br(
                        userDashboardEscape($summary)
                    )
                    : 'No summary available.' ?>

            </div>

        </div>


    </div>


<?php if (
    $ocrText !== ''
    ||
    $ocrMedicines !== ''
): ?>


<div class="case-extras">


    <?php if ($ocrText !== ''): ?>

        <div class="extra">

            <div class="extra-title">
                📄 OCR Extracted Text
            </div>

            <div class="extra-text">

                <?= nl2br(
                    userDashboardEscape($ocrText)
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <?php if ($ocrMedicines !== ''): ?>

        <div class="extra">

            <div class="extra-title">
                💊 OCR Medicines
            </div>

            <div class="extra-text">

                <?= nl2br(
                    userDashboardEscape($ocrMedicines)
                ) ?>

            </div>

        </div>

    <?php endif; ?>


</div>


<?php endif; ?>


<?php if (
    $medicineSummary !== ''
    ||
    $prescriptionText !== ''
): ?>


<div class="case-extras">


    <?php if ($medicineSummary !== ''): ?>

        <div class="extra">

            <div class="extra-title">
                💊 Medicine Summary
            </div>

            <div class="extra-text">

                <?= nl2br(
                    userDashboardEscape(
                        $medicineSummary
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <?php if ($prescriptionText !== ''): ?>

        <div class="extra">

            <div class="extra-title">
                📝 Prescription
            </div>

            <div class="extra-text">

                <?= nl2br(
                    userDashboardEscape(
                        $prescriptionText
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>


</div>


<?php endif; ?>

</div>


<!-- ========================================================
     CASE FOOTER
========================================================= -->

<div class="case-footer">

    <div class="reviewed">

        <?php if (
            !empty($case['reviewed_at'])
        ): ?>

            ✓ Reviewed:
            <?= userDashboardDate(
                $case['reviewed_at']
            ) ?>

        <?php elseif (
            !empty($case['reviewer_name'])
        ): ?>

            ✓ Reviewer:
            <?= userDashboardEscape(
                $case['reviewer_name']
            ) ?>

        <?php else: ?>

            No review recorded yet.

        <?php endif; ?>

    </div>


    <div class="case-actions">

        <a
            href="../review.php?id=<?= (int)$case['id'] ?>"
            class="btn btn-secondary"
        >
            👁 View Full Record
        </a>


        <a
            href="../patients.php?id=<?= urlencode($patientUid) ?>"
            class="btn"
        >
            📋 Patient Record
        </a>

    </div>

</div>


</article>


<?php endforeach; ?>


</div>


<?php endif; ?>


<!-- ============================================================
     NEW TRIAGE
============================================================ -->

<section
    class="card"
    style="
        margin-top:18px;
        background:
            linear-gradient(
                135deg,
                #111c2b,
                #111829
            );
    "
>

    <div
        style="
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:15px;
            flex-wrap:wrap;
        "
    >

        <div>

            <div class="section-kicker">
                CONTINUE YOUR CARE
            </div>

            <h2
                style="
                    margin:6px 0;
                    font-size:19px;
                "
            >
                Have a new health concern?
            </h2>

            <p
                style="
                    margin:0;
                    color:#8d9bae;
                    font-size:11px;
                "
            >
                Your permanent Patient Unique ID
                remains the same. A new triage case
                will be created separately.
            </p>

        </div>


        <a
            href="../new_triage.php?patient_id=<?= (int)$patient['id'] ?>"
            class="btn"
        >
            ➕ Start New Triage
        </a>

    </div>

</section>


<!-- ============================================================
     NOTICE
============================================================ -->

<div class="notice">

    <strong>
        Healthcare notice:
    </strong>

    Triage information, OCR-extracted text,
    medicine information and automatically
    structured information are supporting
    information only.

    They are not a final diagnosis and should
    be reviewed by an appropriately authorized
    healthcare professional.

    Your Patient Unique ID is a permanent identifier
    for your patient record. A new triage case does
    not create a new Patient Unique ID.

</div>


<?php endif; ?>


</div>

</main>


<?php



?>