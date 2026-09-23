<?php

declare(strict_types=1);

/**
 * ============================================================
 * SWASTHYA SAARATHI
 * patients.php
 * ============================================================
 *
 * Patient record page
 *
 * Features:
 * - Search patient by permanent patient_uid
 * - Patient details
 * - Patient Unique ID
 * - QR Code generator
 * - Download Patient QR
 * - Triage history
 * - OCR text
 * - OCR medicines
 * - Uploaded medical document
 * - Prescription information
 * - Review information
 * - Complete review history
 *
 * Database tables:
 *
 * patients
 * triage_reports
 * reviews
 * users
 *
 * ============================================================
 */


/* ============================================================
   AUTHENTICATION
============================================================ */

require_once __DIR__ . '/includes/auth.php';

require_login();

require_once __DIR__ . '/includes/cache.php';

if (!isset($pdo)) {
    require_once __DIR__ . '/config/database.php';
}

$pageTitle = 'Patient Record | Swasthya Saarathi';


/* ============================================================
   HELPERS
============================================================ */

function h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function displayValue(
    $value,
    string $fallback = '—'
): string {

    $value = trim(
        (string)($value ?? '')
    );

    return $value !== ''
        ? h($value)
        : h($fallback);
}


function formatDateTime(
    $value,
    string $fallback = '—'
): string {

    if (empty($value)) {
        return $fallback;
    }

    $timestamp = strtotime(
        (string)$value
    );

    if ($timestamp === false) {
        return $fallback;
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


function normalizeStatus(
    string $status
): string {

    return strtolower(
        trim(
            str_replace(
                '-',
                '_',
                $status
            )
        )
    );
}


function normalizePriority(
    string $priority
): string {

    return strtolower(
        trim($priority)
    );
}


function badgeClass(
    string $value
): string {

    $value = normalizeStatus(
        $value
    );

    $allowed = [

        'high',
        'medium',
        'low',

        'waiting',
        'pending',

        'reviewed',
        'completed',
        'closed',

        'rejected',
        'cancelled',

        'in_review',
        'pending_review',
        'review_pending'

    ];

    return in_array(
        $value,
        $allowed,
        true
    )
        ? $value
        : 'default';
}


/**
 * Convert JSON / array medical values
 * into readable text.
 */
function readableMedicalValue(
    $value
): string {

    if ($value === null) {
        return '';
    }

    if (is_array($value)) {

        return implode(
            "\n",
            array_map(
                static function ($item): string {

                    if (is_array($item)) {

                        return json_encode(
                            $item,
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES |
                            JSON_PRETTY_PRINT
                        ) ?: '';
                    }

                    return (string)$item;
                },
                $value
            )
        );
    }

    $value = trim(
        (string)$value
    );

    if ($value === '') {
        return '';
    }

    $decoded = json_decode(
        $value,
        true
    );

    if (
        json_last_error() ===
        JSON_ERROR_NONE
    ) {

        if (is_array($decoded)) {

            return implode(
                "\n",
                array_map(
                    static function ($item): string {

                        if (is_array($item)) {

                            return json_encode(
                                $item,
                                JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES
                            ) ?: '';
                        }

                        return (string)$item;
                    },
                    $decoded
                )
            );
        }

        if (is_string($decoded)) {
            return $decoded;
        }
    }

    return $value;
}


/**
 * Convert relative upload paths
 * into safe browser URLs.
 */
function documentUrl(
    ?string $path
): string {

    $path = trim(
        (string)$path
    );

    if ($path === '') {
        return '';
    }

    if (
        preg_match(
            '/^(javascript|data|vbscript):/i',
            $path
        )
    ) {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $path
        )
    ) {
        return $path;
    }

    return ltrim(
        $path,
        '/'
    );
}


/**
 * Current logged-in role.
 */
function currentUserRole(): string
{
    $role =
        $_SESSION['user_role']
        ?? $_SESSION['user']['role']
        ?? '';

    return strtolower(
        trim(
            (string)$role
        )
    );
}


/**
 * Current logged-in user ID.
 */
function currentUserId(): ?int
{
    $id =
        $_SESSION['user_id']
        ?? $_SESSION['user']['id']
        ?? null;

    if (
        $id === null ||
        $id === ''
    ) {
        return null;
    }

    return (int)$id;
}


/* ============================================================
   INITIAL STATE
============================================================ */

$patientUid = trim(
    (string)(
        $_GET['id']
        ?? ''
    )
);

$patient = null;

$cases = [];

$reviewHistory = [];

$error = '';

$currentRole =
    currentUserRole();


$canCreateTriage =
    in_array(
        $currentRole,
        [
            'admin',
            'health_worker',
            'healthcare_worker',
            'staff',
            'nurse'
        ],
        true
    );


$canReview =
    in_array(
        $currentRole,
        [
            'admin',
            'doctor'
        ],
        true
    );


/* ============================================================
   LOAD PATIENT
============================================================ */

if ($patientUid !== '') {

    try {

        /* ----------------------------------------------------
           PATIENT
        ---------------------------------------------------- */

        $stmt = $pdo->prepare(
            "
            SELECT

                p.id,
                p.patient_uid,
                p.name,
                p.age,
                p.gender,
                p.phone,
                p.language,
                p.created_at

            FROM patients p

            WHERE p.patient_uid = ?

            LIMIT 1
            "
        );

        $stmt->execute([
            $patientUid
        ]);

        $patient =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        /* ----------------------------------------------------
           TRIAGE CASES
        ---------------------------------------------------- */

        if ($patient) {

            $stmt = $pdo->prepare(
                "
                SELECT

                    t.id,
                    t.triage_uid,
                    t.patient_id,

                    t.symptoms,
                    t.duration,
                    t.priority,

                    t.urgency_flags,
                    t.missing_information,
                    t.summary,

                    t.status,
                    t.report_path,
                    t.created_at,

                    t.ocr_text,
                    t.prescription_path,
                    t.medicine_summary,

                    t.reviewed_by,
                    t.reviewer_name,
                    t.reviewed_at,

                    t.ocr_medicines,
                    t.ocr_document_path,

                    t.review_notes,
                    t.prescription_text,

                    t.uploaded_document,

                    u.name AS reviewer_user_name,
                    u.email AS reviewer_email

                FROM triage_reports t

                LEFT JOIN users u
                    ON u.id = t.reviewed_by

                WHERE
                    t.patient_id = ?

                ORDER BY

                    t.created_at DESC,
                    t.id DESC
                "
            );

            $stmt->execute([
                (int)$patient['id']
            ]);

            $cases =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            /* ------------------------------------------------
               REVIEW HISTORY
            ------------------------------------------------ */

            $stmt = $pdo->prepare(
                "
                SELECT

                    r.id,
                    r.triage_id,
                    r.reviewer_id,
                    r.remarks,
                    r.reviewed_at,

                    t.triage_uid,

                    u.name AS reviewer_name,
                    u.email AS reviewer_email,
                    u.role AS reviewer_role

                FROM reviews r

                INNER JOIN triage_reports t
                    ON t.id = r.triage_id

                LEFT JOIN users u
                    ON u.id = r.reviewer_id

                WHERE
                    t.patient_id = ?

                ORDER BY

                    r.reviewed_at DESC,
                    r.id DESC
                "
            );

            $stmt->execute([
                (int)$patient['id']
            ]);

            $reviewHistory =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    } catch (PDOException $e) {

        error_log(
            'Patient page DB error: ' .
            $e->getMessage()
        );

        $error =
            'Unable to load the patient record right now.';
    }
}


/* ============================================================
   COUNTS
============================================================ */

$totalCases =
    count($cases);

$pendingCases = 0;

$reviewedCases = 0;

$highPriorityCases = 0;

$mediumPriorityCases = 0;

$lowPriorityCases = 0;


foreach ($cases as $case) {

    $status =
        normalizeStatus(
            (string)(
                $case['status']
                ?? ''
            )
        );

    $priority =
        normalizePriority(
            (string)(
                $case['priority']
                ?? ''
            )
        );


    if (
        in_array(
            $status,
            [
                'waiting',
                'pending',
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
}


/* ============================================================
   HEADER
============================================================ */

include __DIR__ . '/includes/header.php';

?>


<!-- ============================================================
     QR CODE LIBRARY
============================================================ -->

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
    defer
></script>


<style>

/* ============================================================
   ROOT
============================================================ */

:root {

    --ss-blue: #0768a9;

    --ss-blue-dark: #073e69;

    --ss-cyan: #00a4a4;

    --ss-text: #183f5c;

    --ss-muted: #7a8d9d;

    --ss-border: #dfebf2;

    --ss-bg: #f3f9fc;

    --ss-card: #ffffff;

    --ss-shadow:
        0 12px 32px
        rgba(13, 67, 102, .07);
}


/* ============================================================
   PAGE
============================================================ */

.patient-page {

    min-height:
        calc(100vh - 80px);

    padding:
        30px
        20px
        70px;

    background:

        radial-gradient(
            circle at 5% 0%,
            rgba(0,164,164,.09),
            transparent 27%
        ),

        radial-gradient(
            circle at 100% 5%,
            rgba(7,104,169,.10),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #f5fbff,
            #eef8fa
        );
}


.patient-container {

    width:
        min(1280px, 100%);

    margin:
        0 auto;
}


/* ============================================================
   SEARCH
============================================================ */

.patient-search {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;

    padding: 15px;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid
        var(--ss-border);

    border-radius: 18px;

    box-shadow:
        var(--ss-shadow);
}


.patient-search input {

    flex: 1;

    min-width: 0;

    height: 46px;

    padding:
        0 14px;

    border:
        1px solid
        #d7e5ed;

    border-radius: 11px;

    outline: none;

    background: #fbfdff;

    color: var(--ss-text);

    font-size: 14px;
}


.patient-search input:focus {

    border-color:
        var(--ss-cyan);

    box-shadow:
        0 0 0 4px
        rgba(0,164,164,.09);
}


.search-btn {

    height: 46px;

    padding:
        0 19px;

    border: 0;

    border-radius: 11px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0768a9,
            #00a4a4
        );

    font-weight: 800;

    cursor: pointer;
}


/* ============================================================
   PATIENT HEADER
============================================================ */

.patient-header {

    position: relative;

    overflow: hidden;

    margin-bottom: 18px;

    padding: 28px;

    color: white;

    border-radius: 25px;

    background:
        linear-gradient(
            135deg,
            #053866,
            #0767a5 50%,
            #00a4a4
        );

    box-shadow:
        0 22px 55px
        rgba(5,58,96,.20);
}


.patient-header::after {

    content: "";

    position: absolute;

    width: 420px;
    height: 420px;

    right: -190px;
    top: -280px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

    pointer-events: none;
}


.patient-header-inner {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 18px;
}


.patient-avatar {

    width: 76px;
    height: 76px;

    flex: 0 0 76px;

    display: grid;

    place-items: center;

    border-radius: 23px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid
        rgba(255,255,255,.20);

    font-size: 32px;
}


.patient-main-info {

    min-width: 0;
}


.eyebrow {

    display: block;

    margin-bottom: 5px;

    color: #7d94a5;

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1px;

    text-transform: uppercase;
}


.patient-header .eyebrow {

    color:
        rgba(255,255,255,.66);
}


.patient-header h1 {

    margin:
        0 0 9px;

    color: white;

    font-size:
        clamp(23px, 4vw, 31px);

    letter-spacing: -.5px;
}


.patient-uid {

    display: inline-flex;

    padding:
        6px 10px;

    border-radius: 8px;

    background:
        rgba(255,255,255,.12);

    color:
        rgba(255,255,255,.88);

    font-size: 11px;

    font-weight: 800;
}


.patient-header-right {

    margin-left: auto;

    display: flex;

    align-items: flex-end;

    flex-direction: column;

    gap: 10px;
}


.record-status {

    padding:
        8px 12px;

    border:
        1px solid
        rgba(255,255,255,.20);

    border-radius: 999px;

    background:
        rgba(255,255,255,.10);

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}


/* ============================================================
   PATIENT QR
============================================================ */

.patient-qr-card {

    position: relative;

    z-index: 3;

    display: flex;

    align-items: center;

    gap: 15px;

    margin-top: 24px;

    width: fit-content;

    max-width: 100%;

    padding:
        14px 16px;

    border-radius: 17px;

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.22);

    backdrop-filter:
        blur(8px);

    box-sizing: border-box;
}


.patient-qr-code {

    width: 100px;
    height: 100px;

    flex: 0 0 100px;

    display: grid;

    place-items: center;

    padding: 7px;

    box-sizing: border-box;

    background: white;

    border-radius: 11px;
}


.patient-qr-code canvas,
.patient-qr-code img {

    display: block;

    max-width: 100%;
    max-height: 100%;
}


.patient-qr-info {

    display: flex;

    flex-direction: column;

    align-items: flex-start;

    gap: 5px;

    min-width: 0;
}


.patient-qr-label {

    color:
        rgba(255,255,255,.65);

    font-size: 8px;

    font-weight: 900;

    letter-spacing: 1px;
}


.patient-qr-info strong {

    color: white;

    font-size: 13px;

    letter-spacing: .4px;

    word-break: break-all;
}


.patient-qr-info small {

    color:
        rgba(255,255,255,.72);

    font-size: 9px;

    margin-bottom: 5px;
}


/* ============================================================
   BUTTONS
============================================================ */

.ss-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 38px;

    padding:
        0 13px;

    border-radius: 9px;

    border: 0;

    text-decoration: none;

    font-size: 11px;

    font-weight: 900;

    cursor: pointer;

    transition:
        transform .15s ease,
        box-shadow .15s ease;
}


.ss-btn:hover {

    transform:
        translateY(-1px);
}


.ss-btn-primary {

    color: white;

    background:
        linear-gradient(
            135deg,
            #0878c5,
            #00a4a4
        );

    box-shadow:
        0 8px 20px
        rgba(0,120,170,.16);
}


.ss-btn-light {

    color: #225c7d;

    background: #edf7fa;

    border:
        1px solid
        #d8ebf0;
}


.ss-btn-white {

    color: #08649b;

    background: white;
}


/* ============================================================
   STATS
============================================================ */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(6, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 22px;
}


.stat-card {

    padding: 17px;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid
        var(--ss-border);

    border-radius: 16px;

    box-shadow:
        var(--ss-shadow);
}


.stat-label {

    display: block;

    margin-bottom: 8px;

    color: var(--ss-muted);

    font-size: 9px;

    font-weight: 900;

    letter-spacing: .5px;

    text-transform: uppercase;
}


.stat-number {

    display: block;

    color: var(--ss-blue-dark);

    font-size: 25px;

    line-height: 1;

    font-weight: 900;
}


/* ============================================================
   PATIENT INFORMATION
============================================================ */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 24px;
}


.info-card {

    padding: 17px;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid
        var(--ss-border);

    border-radius: 16px;

    box-shadow:
        var(--ss-shadow);
}


.info-label {

    display: block;

    margin-bottom: 7px;

    color: #8798a6;

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing: .6px;
}


.info-value {

    display: block;

    color: var(--ss-text);

    font-size: 14px;

    font-weight: 850;

    word-break: break-word;
}


/* ============================================================
   SECTION HEADING
============================================================ */

.section-heading {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 15px;

    margin:
        10px 0 14px;
}


.section-heading h2 {

    margin: 0;

    color: var(--ss-blue-dark);

    font-size: 21px;
}


.section-heading p {

    margin:
        4px 0 0;

    color: var(--ss-muted);

    font-size: 11px;
}


.section-actions {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


/* ============================================================
   CASE
============================================================ */

.case-card {

    overflow: hidden;

    margin-bottom: 17px;

    background:
        rgba(255,255,255,.98);

    border:
        1px solid
        var(--ss-border);

    border-radius: 20px;

    box-shadow:
        var(--ss-shadow);
}


.case-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding:
        18px 20px;

    border-bottom:
        1px solid
        #edf2f5;
}


.case-title strong {

    display: block;

    color: #13476c;

    font-size: 14px;
}


.case-date {

    display: block;

    margin-top: 5px;

    color: #8b9aa7;

    font-size: 10px;
}


.case-badges {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

    justify-content: flex-end;
}


/* ============================================================
   BADGES
============================================================ */

.pill {

    display: inline-flex;

    align-items: center;

    min-height: 25px;

    padding:
        0 9px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}


.pill.high {

    background: #ffe4e4;

    color: #b31e1e;
}


.pill.medium {

    background: #fff3d9;

    color: #94600b;
}


.pill.low {

    background: #e4f7ec;

    color: #18704a;
}


.pill.waiting,
.pill.pending,
.pill.pending_review,
.pill.review_pending,
.pill.in_review {

    background: #e8f4ff;

    color: #176398;
}


.pill.reviewed,
.pill.completed,
.pill.closed {

    background: #e4f7ed;

    color: #176e48;
}


.pill.rejected,
.pill.cancelled {

    background: #fce7e7;

    color: #9f2929;
}


.pill.default {

    background: #edf2f5;

    color: #607685;
}


/* ============================================================
   CASE BODY
============================================================ */

.case-body {

    padding: 20px;
}


.medical-block {

    margin-bottom: 18px;
}


.medical-block:last-child {

    margin-bottom: 0;
}


.medical-block h4 {

    display: flex;

    align-items: center;

    gap: 6px;

    margin:
        0 0 7px;

    color: #607686;

    font-size: 10px;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.medical-text {

    color: #294d66;

    font-size: 13px;

    line-height: 1.7;

    white-space: pre-line;

    overflow-wrap: anywhere;
}


.case-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 18px;
}


.detail-card {

    padding: 15px;

    background: #f7fbfd;

    border:
        1px solid
        #e4eff4;

    border-radius: 14px;
}


.detail-card h4 {

    margin:
        0 0 7px;

    color: #637989;

    font-size: 9px;

    font-weight: 900;

    letter-spacing: .5px;

    text-transform: uppercase;
}


.detail-card p {

    margin: 0;

    color: #244c67;

    font-size: 12px;

    line-height: 1.6;

    white-space: pre-line;

    overflow-wrap: anywhere;
}


/* ============================================================
   DOCUMENT / OCR
============================================================ */

.document-section {

    margin-top: 18px;

    padding-top: 18px;

    border-top:
        1px dashed
        #dce9ef;
}


.document-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 10px;
}


.document-heading h3 {

    margin: 0;

    color: #15486a;

    font-size: 14px;
}


.document-heading span {

    color: #8a9ba8;

    font-size: 10px;
}


.document-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;
}


.document-card {

    padding: 15px;

    background:
        linear-gradient(
            135deg,
            #f8fcfe,
            #f1f9fb
        );

    border:
        1px solid
        #deedf2;

    border-radius: 14px;
}


.document-card h4 {

    margin:
        0 0 8px;

    color: #527083;

    font-size: 9px;

    font-weight: 900;

    letter-spacing: .5px;

    text-transform: uppercase;
}


.document-card .document-text {

    max-height: 230px;

    overflow: auto;

    color: #31566d;

    font-size: 11px;

    line-height: 1.65;

    white-space: pre-line;

    overflow-wrap: anywhere;

    padding-right: 4px;
}


.document-actions {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    margin-top: 12px;
}


/* ============================================================
   REVIEW
============================================================ */

.review-box {

    margin-top: 18px;

    padding: 16px;

    background: #f8fbfd;

    border:
        1px solid
        #e0edf2;

    border-radius: 15px;
}


.review-box-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 10px;
}


.review-box-head h3 {

    margin: 0;

    color: #15476a;

    font-size: 13px;
}


.review-meta {

    color: #8394a1;

    font-size: 10px;
}


.review-notes {

    color: #31536a;

    font-size: 12px;

    line-height: 1.6;

    white-space: pre-line;
}


/* ============================================================
   CASE FOOTER
============================================================ */

.case-footer {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        13px 20px;

    background: #fbfdfe;

    border-top:
        1px solid
        #edf2f5;
}


.case-footer-status {

    color: #7c8e9c;

    font-size: 10px;
}


.case-footer-status strong {

    color: #45677c;
}


.case-actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

    justify-content: flex-end;
}


/* ============================================================
   HISTORY
============================================================ */

.history-card {

    margin-top: 25px;

    padding: 20px;

    background:
        rgba(255,255,255,.97);

    border:
        1px solid
        var(--ss-border);

    border-radius: 20px;

    box-shadow:
        var(--ss-shadow);
}


.history-list {

    display: grid;

    gap: 10px;
}


.history-item {

    position: relative;

    padding:
        14px
        16px
        14px
        19px;

    border:
        1px solid
        #e2edf2;

    border-radius: 13px;

    background: #f9fcfd;
}


.history-item::before {

    content: "";

    position: absolute;

    left: 0;

    top: 12px;

    bottom: 12px;

    width: 3px;

    border-radius: 5px;

    background:
        linear-gradient(
            #0878c5,
            #00a4a4
        );
}


.history-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 6px;
}


.history-reviewer {

    color: #205170;

    font-size: 12px;

    font-weight: 900;
}


.history-date {

    color: #8294a1;

    font-size: 9px;
}


.history-case {

    margin-bottom: 7px;

    color: #6e8492;

    font-size: 9px;

    font-weight: 800;
}


.history-remarks {

    color: #38596e;

    font-size: 12px;

    line-height: 1.55;

    white-space: pre-line;
}


/* ============================================================
   EMPTY
============================================================ */

.empty-card {

    padding:
        55px 25px;

    text-align: center;

    background:
        rgba(255,255,255,.97);

    border:
        1px solid
        var(--ss-border);

    border-radius: 20px;

    box-shadow:
        var(--ss-shadow);
}


.empty-icon {

    width: 60px;
    height: 60px;

    display: grid;

    place-items: center;

    margin:
        0 auto 15px;

    border-radius: 18px;

    background: #edf8fa;

    font-size: 27px;
}


.empty-card h2 {

    margin:
        0 0 7px;

    color: var(--ss-blue-dark);

    font-size: 20px;
}


.empty-card p {

    margin: 0;

    color: var(--ss-muted);

    font-size: 12px;
}


/* ============================================================
   NOTICE
============================================================ */

.medical-note {

    margin-top: 22px;

    padding:
        16px 18px;

    border:
        1px solid
        #f0e2bd;

    border-radius: 15px;

    background: #fffaf0;

    color: #74613a;

    font-size: 10px;

    line-height: 1.65;
}


.medical-note strong {

    color: #5e4a23;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1100px) {

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }


    .info-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }
}


@media (max-width: 800px) {

    .patient-page {

        padding:
            20px
            12px
            50px;
    }


    .patient-header-inner {

        align-items: flex-start;
    }


    .patient-header-right {

        align-items: flex-start;
    }


    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .document-grid,
    .case-grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 620px) {

    .patient-search {

        flex-direction: column;

        align-items: stretch;
    }


    .search-btn {

        width: 100%;
    }


    .patient-header {

        padding: 21px;
    }


    .patient-header-inner {

        flex-wrap: wrap;
    }


    .patient-avatar {

        width: 58px;
        height: 58px;

        flex-basis: 58px;

        font-size: 24px;
    }


    .patient-header-right {

        width: 100%;

        margin-left: 0;

        flex-direction: row;

        align-items: center;

        justify-content: space-between;
    }


    .record-status {

        display: none;
    }


    .patient-qr-card {

        width: 100%;

        box-sizing: border-box;

        align-items: center;
    }


    .patient-qr-code {

        width: 85px;
        height: 85px;

        flex-basis: 85px;
    }


    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .section-heading {

        align-items: flex-start;

        flex-direction: column;
    }


    .case-top {

        align-items: flex-start;

        flex-direction: column;
    }


    .case-badges {

        justify-content: flex-start;
    }


    .case-footer {

        align-items: flex-start;

        flex-direction: column;
    }


    .case-actions {

        justify-content: flex-start;
    }


    .history-top {

        align-items: flex-start;

        flex-direction: column;
    }
}

</style>


<main class="patient-page">

<div class="patient-container">


<!-- ==========================================================
     SEARCH
=========================================================== -->

<form
    method="GET"
    action=""
    class="patient-search"
>

    <input
        type="text"
        name="id"
        value="<?= h($patientUid) ?>"
        placeholder="Enter Patient Unique ID — e.g. SS-2026-000001"
        autocomplete="off"
        required
    >

    <button
        type="submit"
        class="search-btn"
    >
        🔎 Search Patient
    </button>

</form>


<?php if ($error): ?>


<section class="empty-card">

    <div class="empty-icon">
        ⚠️
    </div>

    <h2>
        Unable to load record
    </h2>

    <p>
        <?= h($error) ?>
    </p>

</section>


<?php elseif (
    $patientUid !== ''
    &&
    !$patient
): ?>


<section class="empty-card">

    <div class="empty-icon">
        🔎
    </div>

    <h2>
        Patient not found
    </h2>

    <p>

        No patient record was found for

        <strong>
            <?= h($patientUid) ?>
        </strong>.

    </p>

</section>


<?php elseif (!$patient): ?>


<section class="empty-card">

    <div class="empty-icon">
        🩺
    </div>

    <h2>
        Search a patient record
    </h2>

    <p>

        Enter the permanent Patient Unique ID
        to view complete triage, OCR,
        prescription and review history.

    </p>

</section>


<?php else: ?>


<!-- ==========================================================
     PATIENT HEADER
=========================================================== -->

<section class="patient-header">

    <div class="patient-header-inner">


        <div class="patient-avatar">
            👤
        </div>


        <div class="patient-main-info">

            <span class="eyebrow">
                PATIENT RECORD
            </span>

            <h1>

                <?= displayValue(
                    $patient['name'],
                    'Patient'
                ) ?>

            </h1>

            <span class="patient-uid">

                Patient Unique ID:

                &nbsp;

                <?= h(
                    $patient['patient_uid']
                ) ?>

            </span>

        </div>


        <div class="patient-header-right">


            <div class="record-status">

                ● RECORD AVAILABLE

            </div>


            <?php if ($canCreateTriage): ?>

                <a
                    href="new_triage.php?patient_id=<?= urlencode((string)$patient['id']) ?>"
                    class="ss-btn ss-btn-white"
                >

                    ➕ New Triage Case

                </a>

            <?php endif; ?>


        </div>

    </div>


    <!-- ======================================================
         PATIENT QR
    ======================================================= -->

    <div class="patient-qr-card">


        <div
            id="patientQrCode"
            class="patient-qr-code"
        ></div>


        <div class="patient-qr-info">

            <span class="patient-qr-label">
                PATIENT QR
            </span>


            <strong>

                <?= h(
                    $patient['patient_uid']
                ) ?>

            </strong>


            <small>
                Scan to open patient record
            </small>


            <button
                type="button"
                class="ss-btn ss-btn-white"
                onclick="downloadPatientQR()"
            >

                ⬇ Download QR

            </button>

        </div>

    </div>

</section>


<!-- ==========================================================
     STATISTICS
=========================================================== -->

<section class="stats-grid">


    <div class="stat-card">

        <span class="stat-label">
            Total Cases
        </span>

        <span class="stat-number">
            <?= $totalCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            Pending
        </span>

        <span class="stat-number">
            <?= $pendingCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            Reviewed
        </span>

        <span class="stat-number">
            <?= $reviewedCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            High Priority
        </span>

        <span class="stat-number">
            <?= $highPriorityCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            Medium
        </span>

        <span class="stat-number">
            <?= $mediumPriorityCases ?>
        </span>

    </div>


    <div class="stat-card">

        <span class="stat-label">
            Low
        </span>

        <span class="stat-number">
            <?= $lowPriorityCases ?>
        </span>

    </div>


</section>


<!-- ==========================================================
     PATIENT INFORMATION
=========================================================== -->

<section class="info-grid">


    <div class="info-card">

        <span class="info-label">
            Age
        </span>

        <span class="info-value">

            <?= displayValue(
                $patient['age']
            ) ?>

        </span>

    </div>


    <div class="info-card">

        <span class="info-label">
            Gender
        </span>

        <span class="info-value">

            <?= displayValue(
                $patient['gender']
            ) ?>

        </span>

    </div>


    <div class="info-card">

        <span class="info-label">
            Phone
        </span>

        <span class="info-value">

            <?= displayValue(
                $patient['phone']
            ) ?>

        </span>

    </div>


    <div class="info-card">

        <span class="info-label">
            Language
        </span>

        <span class="info-value">

            <?= displayValue(
                $patient['language']
            ) ?>

        </span>

    </div>


    <div class="info-card">

        <span class="info-label">
            Registered
        </span>

        <span class="info-value">

            <?= h(
                formatDateTime(
                    $patient['created_at']
                )
            ) ?>

        </span>

    </div>


</section>


<!-- ==========================================================
     TRIAGE HISTORY
=========================================================== -->

<div class="section-heading">

    <div>

        <span class="eyebrow">
            HEALTHCARE HISTORY
        </span>

        <h2>
            Triage & Medical Records
        </h2>

        <p>

            All triage cases associated with this permanent
            Patient Unique ID.

        </p>

    </div>


    <div class="section-actions">


        <?php if ($canCreateTriage): ?>

            <a
                href="new_triage.php?patient_id=<?= urlencode((string)$patient['id']) ?>"
                class="ss-btn ss-btn-primary"
            >

                ➕ New Case

            </a>

        <?php endif; ?>


    </div>

</div>


<?php if (empty($cases)): ?>


<section class="empty-card">

    <div class="empty-icon">
        📋
    </div>

    <h2>
        No triage cases yet
    </h2>

    <p>

        This patient is registered,
        but no triage case has been
        created yet.

    </p>


    <?php if ($canCreateTriage): ?>

        <div style="margin-top:18px;">

            <a
                href="new_triage.php?patient_id=<?= urlencode((string)$patient['id']) ?>"
                class="ss-btn ss-btn-primary"
            >

                ➕ Start First Triage

            </a>

        </div>

    <?php endif; ?>


</section>


<?php else: ?>


<?php foreach (
    $cases
    as $index => $case
): ?>


<?php

$priority =
    normalizePriority(
        (string)(
            $case['priority']
            ?? ''
        )
    );


$status =
    normalizeStatus(
        (string)(
            $case['status']
            ?? ''
        )
    );


$priorityClass =
    badgeClass(
        $priority
    );


$statusClass =
    badgeClass(
        $status
    );


$ocrText =
    readableMedicalValue(
        $case['ocr_text']
        ?? ''
    );


$ocrMedicines =
    readableMedicalValue(
        $case['ocr_medicines']
        ?? ''
    );


$medicineSummary =
    readableMedicalValue(
        $case['medicine_summary']
        ?? ''
    );


$prescriptionText =
    readableMedicalValue(
        $case['prescription_text']
        ?? ''
    );


$urgencyFlags =
    readableMedicalValue(
        $case['urgency_flags']
        ?? ''
    );


$missingInformation =
    readableMedicalValue(
        $case['missing_information']
        ?? ''
    );


$summary =
    readableMedicalValue(
        $case['summary']
        ?? ''
    );


$reviewNotes =
    readableMedicalValue(
        $case['review_notes']
        ?? ''
    );


$reportUrl =
    documentUrl(
        $case['report_path']
        ?? ''
    );


$prescriptionUrl =
    documentUrl(
        $case['prescription_path']
        ?? ''
    );


$ocrDocumentUrl =
    documentUrl(
        $case['ocr_document_path']
        ?? ''
    );


$uploadedDocumentUrl =
    documentUrl(
        $case['uploaded_document']
        ?? ''
    );


$reviewerName =
    trim(
        (string)(
            $case['reviewer_name']
            ??
            $case['reviewer_user_name']
            ??
            ''
        )
    );

?>


<article class="case-card">


    <!-- ======================================================
         CASE HEADER
    ======================================================= -->

    <div class="case-top">


        <div class="case-title">

            <span class="eyebrow">

                TRIAGE CASE #<?= count($cases) - $index ?>

            </span>


            <strong>

                <?= displayValue(
                    $case['triage_uid'],
                    'Triage Case'
                ) ?>

            </strong>


            <span class="case-date">

                Created:

                <?= h(
                    formatDateTime(
                        $case['created_at']
                    )
                ) ?>

            </span>

        </div>


        <div class="case-badges">


            <?php if (
                trim(
                    (string)(
                        $case['priority']
                        ?? ''
                    )
                ) !== ''
            ): ?>

                <span
                    class="pill <?= h($priorityClass) ?>"
                >

                    <?= h(
                        ucfirst(
                            $priority
                        )
                    ) ?>

                </span>

            <?php endif; ?>


            <?php if (
                trim(
                    (string)(
                        $case['status']
                        ?? ''
                    )
                ) !== ''
            ): ?>

                <span
                    class="pill <?= h($statusClass) ?>"
                >

                    <?= h(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $status
                            )
                        )
                    ) ?>

                </span>

            <?php endif; ?>


        </div>

    </div>


    <!-- ======================================================
         CASE BODY
    ======================================================= -->

    <div class="case-body">


        <!-- ==================================================
             SYMPTOMS
        =================================================== -->

        <?php if (

            trim(
                (string)(
                    $case['symptoms']
                    ?? ''
                )
            ) !== ''

            ||

            trim(
                (string)(
                    $case['duration']
                    ?? ''
                )
            ) !== ''

        ): ?>


            <div class="case-grid">


                <?php if (
                    trim(
                        (string)(
                            $case['symptoms']
                            ?? ''
                        )
                    ) !== ''
                ): ?>

                    <div class="detail-card">

                        <h4>
                            🩺 Reported Symptoms
                        </h4>

                        <p>

                            <?= nl2br(
                                h(
                                    $case['symptoms']
                                )
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


                <?php if (
                    trim(
                        (string)(
                            $case['duration']
                            ?? ''
                        )
                    ) !== ''
                ): ?>

                    <div class="detail-card">

                        <h4>
                            ⏱ Duration
                        </h4>

                        <p>

                            <?= nl2br(
                                h(
                                    $case['duration']
                                )
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


            </div>

        <?php endif; ?>


        <!-- ==================================================
             SUMMARY / URGENCY / MISSING
        =================================================== -->

        <?php if (

            $summary !== ''

            ||

            $urgencyFlags !== ''

            ||

            $missingInformation !== ''

        ): ?>


            <div class="case-grid">


                <?php if (
                    $summary !== ''
                ): ?>

                    <div class="detail-card">

                        <h4>
                            📋 Structured Summary
                        </h4>

                        <p>

                            <?= nl2br(
                                h($summary)
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


                <?php if (
                    $urgencyFlags !== ''
                ): ?>

                    <div class="detail-card">

                        <h4>
                            ⚠️ Urgency Indicators
                        </h4>

                        <p>

                            <?= nl2br(
                                h($urgencyFlags)
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


                <?php if (
                    $missingInformation !== ''
                ): ?>

                    <div class="detail-card">

                        <h4>
                            🔎 Missing / Follow-up Information
                        </h4>

                        <p>

                            <?= nl2br(
                                h($missingInformation)
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>


            </div>

        <?php endif; ?>


        <!-- ==================================================
             OCR
        =================================================== -->

        <?php if (

            $ocrText !== ''

            ||

            $ocrMedicines !== ''

            ||

            $ocrDocumentUrl !== ''

            ||

            $uploadedDocumentUrl !== ''

        ): ?>


            <section class="document-section">


                <div class="document-heading">

                    <h3>
                        📄 OCR / Uploaded Medical Document
                    </h3>

                    <span>
                        Extracted document information
                    </span>

                </div>


                <div class="document-grid">


                    <?php if (
                        $ocrText !== ''
                    ): ?>

                        <div class="document-card">

                            <h4>
                                OCR Extracted Text
                            </h4>

                            <div class="document-text">

                                <?= nl2br(
                                    h($ocrText)
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $ocrMedicines !== ''
                    ): ?>

                        <div class="document-card">

                            <h4>
                                💊 OCR Medicines
                            </h4>

                            <div class="document-text">

                                <?= nl2br(
                                    h($ocrMedicines)
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>


                <div class="document-actions">


                    <?php if (
                        $ocrDocumentUrl !== ''
                    ): ?>

                        <a
                            href="<?= h($ocrDocumentUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ss-btn ss-btn-light"
                        >

                            📄 Open OCR Document

                        </a>

                    <?php endif; ?>


                    <?php if (

                        $uploadedDocumentUrl !== ''

                        &&

                        $uploadedDocumentUrl
                            !==
                        $ocrDocumentUrl

                    ): ?>

                        <a
                            href="<?= h($uploadedDocumentUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ss-btn ss-btn-light"
                        >

                            📎 Open Uploaded Document

                        </a>

                    <?php endif; ?>


                    <?php if (
                        $reportUrl !== ''
                    ): ?>

                        <a
                            href="<?= h($reportUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ss-btn ss-btn-light"
                        >

                            📑 View Report

                        </a>

                    <?php endif; ?>


                </div>

            </section>

        <?php endif; ?>


        <!-- ==================================================
             PRESCRIPTION
        =================================================== -->

        <?php if (

            $prescriptionText !== ''

            ||

            $medicineSummary !== ''

            ||

            $prescriptionUrl !== ''

        ): ?>


            <section class="document-section">


                <div class="document-heading">

                    <h3>
                        💊 Prescription & Medicines
                    </h3>

                    <span>
                        Prescription information
                    </span>

                </div>


                <div class="document-grid">


                    <?php if (
                        $prescriptionText !== ''
                    ): ?>

                        <div class="document-card">

                            <h4>
                                Prescription Text
                            </h4>

                            <div class="document-text">

                                <?= nl2br(
                                    h($prescriptionText)
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $medicineSummary !== ''
                    ): ?>

                        <div class="document-card">

                            <h4>
                                Medicine Summary
                            </h4>

                            <div class="document-text">

                                <?= nl2br(
                                    h($medicineSummary)
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>


                <?php if (
                    $prescriptionUrl !== ''
                ): ?>

                    <div class="document-actions">

                        <a
                            href="<?= h($prescriptionUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ss-btn ss-btn-light"
                        >

                            💊 View Prescription

                        </a>

                    </div>

                <?php endif; ?>


            </section>

        <?php endif; ?>


        <!-- ==================================================
             LATEST REVIEW
        =================================================== -->

        <?php if (

            $reviewerName !== ''

            ||

            !empty(
                $case['reviewed_at']
            )

            ||

            $reviewNotes !== ''

        ): ?>


            <section class="review-box">


                <div class="review-box-head">

                    <h3>
                        👨‍⚕️ Latest Review
                    </h3>


                    <span class="review-meta">

                        <?php if (
                            !empty(
                                $case['reviewed_at']
                            )
                        ): ?>

                            <?= h(
                                formatDateTime(
                                    $case['reviewed_at']
                                )
                            ) ?>

                        <?php endif; ?>

                    </span>

                </div>


                <?php if (
                    $reviewerName !== ''
                ): ?>

                    <div
                        class="review-meta"
                        style="margin-bottom:7px;"
                    >

                        Reviewed by:

                        <strong>

                            <?= h(
                                $reviewerName
                            ) ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    $reviewNotes !== ''
                ): ?>

                    <div class="review-notes">

                        <?= nl2br(
                            h($reviewNotes)
                        ) ?>

                    </div>

                <?php endif; ?>


            </section>

        <?php endif; ?>


    </div>


    <!-- ======================================================
         CASE FOOTER
    ======================================================= -->

    <div class="case-footer">


        <div class="case-footer-status">

            Status:

            <strong>

                <?= h(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $status
                                ?: 'Unknown'
                        )
                    )
                ) ?>

            </strong>

        </div>


        <div class="case-actions">


            <?php if (
                $reportUrl !== ''
            ): ?>

                <a
                    href="<?= h($reportUrl) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ss-btn ss-btn-light"
                >

                    📄 Report

                </a>

            <?php endif; ?>


            <?php if ($canReview): ?>

                <a
                    href="review.php?id=<?= urlencode((string)$case['id']) ?>"
                    class="ss-btn ss-btn-primary"
                >

                    👨‍⚕️ Review Case

                </a>

            <?php endif; ?>


        </div>

    </div>


</article>


<?php endforeach; ?>


<?php endif; ?>


<!-- ==========================================================
     REVIEW HISTORY
=========================================================== -->

<section class="history-card">


    <div class="section-heading">

        <div>

            <span class="eyebrow">
                AUDIT / REVIEW TRAIL
            </span>

            <h2>
                Review History
            </h2>

            <p>

                All review entries linked to this patient's
                triage cases.

            </p>

        </div>

    </div>


    <?php if (
        empty($reviewHistory)
    ): ?>


        <div
            class="empty-card"
            style="
                padding:35px 20px;
                box-shadow:none;
                background:#f9fcfd;
            "
        >

            <div class="empty-icon">
                📝
            </div>

            <h2>
                No review history
            </h2>

            <p>

                No reviewer activity has been recorded
                for this patient yet.

            </p>

        </div>


    <?php else: ?>


        <div class="history-list">


            <?php foreach (
                $reviewHistory
                as $review
            ): ?>


                <?php

                $reviewer =
                    trim(
                        (string)(
                            $review[
                                'reviewer_name'
                            ]
                            ?? ''
                        )
                    );


                if (
                    $reviewer === ''
                ) {

                    $reviewer =
                        'Unknown reviewer';
                }


                $remarks =
                    trim(
                        (string)(
                            $review[
                                'remarks'
                            ]
                            ?? ''
                        )
                    );

                ?>


                <article
                    class="history-item"
                >


                    <div class="history-top">


                        <div
                            class="history-reviewer"
                        >

                            👨‍⚕️

                            <?= h(
                                $reviewer
                            ) ?>


                            <?php if (
                                !empty(
                                    $review[
                                        'reviewer_role'
                                    ]
                                )
                            ): ?>

                                <span
                                    style="
                                        color:#8a9ba7;
                                        font-size:9px;
                                        font-weight:700;
                                    "
                                >

                                    (

                                    <?= h(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string)(
                                                $review[
                                                    'reviewer_role'
                                                ]
                                            )
                                        )
                                    ) ?>

                                    )

                                </span>

                            <?php endif; ?>


                        </div>


                        <div class="history-date">

                            <?= h(
                                formatDateTime(
                                    $review[
                                        'reviewed_at'
                                    ]
                                )
                            ) ?>

                        </div>


                    </div>


                    <div class="history-case">

                        TRIAGE:

                        <?= displayValue(
                            $review[
                                'triage_uid'
                            ],
                            'Unknown'
                        ) ?>

                    </div>


                    <div class="history-remarks">

                        <?= $remarks !== ''

                            ? nl2br(
                                h($remarks)
                            )

                            : 'No remarks added.'
                        ?>

                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</section>


<!-- ==========================================================
     CREATE NEW CASE
=========================================================== -->

<?php if (
    $canCreateTriage
): ?>


<section
    class="history-card"
    style="
        background:
            linear-gradient(
                135deg,
                #f5fcff,
                #eefafa
            );
    "
>


    <div class="section-heading">


        <div>

            <span class="eyebrow">
                CONTINUE PATIENT CARE
            </span>

            <h2>
                New Health Concern?
            </h2>

            <p>

                Keep the same permanent Patient Unique ID
                and create a separate triage case.

            </p>

        </div>


        <div class="section-actions">


            <a
                href="new_triage.php?patient_id=<?= urlencode((string)$patient['id']) ?>"
                class="ss-btn ss-btn-primary"
            >

                ➕ Create New Triage Case

            </a>


        </div>


    </div>


</section>


<?php endif; ?>


<!-- ==========================================================
     MEDICAL NOTICE
=========================================================== -->

<div class="medical-note">

    <strong>
        Important healthcare notice:
    </strong>

    OCR-extracted text, medicine information,
    triage priority, urgency indicators and
    automatically structured information are
    decision-support information only.

    Review information shown here comes from the
    application's database and should be verified
    by an appropriately authorized healthcare
    professional.

    This page does not provide a diagnosis and
    does not replace professional medical assessment.

    For emergencies, use your local emergency
    medical service.

</div>


<?php endif; ?>


</div>

</main>


<!-- ============================================================
     PATIENT QR JAVASCRIPT
============================================================ -->

<?php if ($patient): ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const patientUid =
            <?= json_encode(
                (string)$patient['patient_uid'],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ) ?>;


        const qrContainer =
            document.getElementById(
                'patientQrCode'
            );


        if (
            !qrContainer ||
            !patientUid
        ) {
            return;
        }


        /*
         * Build current patient URL.
         *
         * Example:
         *
         * http://localhost/swasthya_saarthi/patients.php?id=SS-2026-000001
         */

        const patientUrl =
            new URL(
                'patients.php',
                window.location.href
            );


        patientUrl.searchParams.set(
            'id',
            patientUid
        );


        /*
         * Generate QR.
         */

        if (
            typeof QRCode !== 'undefined'
        ) {

            new QRCode(
                qrContainer,
                {

                    text:
                        patientUrl.toString(),

                    width: 86,

                    height: 86,

                    colorDark:
                        '#073e69',

                    colorLight:
                        '#ffffff',

                    correctLevel:
                        QRCode.CorrectLevel.M

                }
            );

        } else {

            qrContainer.innerHTML =
                '<span style="' +
                'font-size:8px;' +
                'color:#777;' +
                'text-align:center;' +
                '">' +
                'QR unavailable' +
                '</span>';
        }


        /*
         * Download QR.
         */

        window.downloadPatientQR =
            function () {

                const canvas =
                    qrContainer.querySelector(
                        'canvas'
                    );


                const image =
                    qrContainer.querySelector(
                        'img'
                    );


                let dataUrl = '';


                if (canvas) {

                    dataUrl =
                        canvas.toDataURL(
                            'image/png'
                        );

                } else if (image) {

                    dataUrl =
                        image.src;
                }


                if (!dataUrl) {

                    alert(
                        'QR code is not ready yet.'
                    );

                    return;
                }


                const link =
                    document.createElement(
                        'a'
                    );


                link.href =
                    dataUrl;


                link.download =
                    'patient-qr-' +
                    patientUid +
                    '.png';


                document.body.appendChild(
                    link
                );


                link.click();


                document.body.removeChild(
                    link
                );

            };

    }
);

</script>

<?php endif; ?>


<?php

include __DIR__ . '/includes/footer.php';

?>