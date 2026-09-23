<?php

declare(strict_types=1);

/**
 * Swasthya Saarathi
 *
 * Premium Human Case Review
 * - Patient information
 * - Triage information
 * - Medical document upload
 * - OCR processing
 * - Full OCR report
 * - Prescription extraction
 * - Human review
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/config/database.php';

if (file_exists(__DIR__ . '/includes/csrf.php')) {
    require_once __DIR__ . '/includes/csrf.php';
}

require_once __DIR__ . '/includes/ocr.php';

$pageTitle = 'Case Review | Swasthya Saarathi';

$error   = '';
$success = '';

$userId = $_SESSION['user_id']
    ?? ($_SESSION['user']['id'] ?? null);

$userName = $_SESSION['user_name']
    ?? ($_SESSION['user']['name'] ?? 'Reviewer');

$userRole = strtolower(
    $_SESSION['user_role']
    ?? ($_SESSION['user']['role'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('review_h')) {
    function review_h(mixed $value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('review_json')) {
    function review_json(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        return $json !== false
            ? $json
            : '{}';
    }
}

if (!function_exists('review_status_label')) {
    function review_status_label(string $status): string
    {
        return ucfirst(
            str_replace('_', ' ', $status)
        );
    }
}

if (!function_exists('review_priority_class')) {
    function review_priority_class(string $priority): string
    {
        $allowed = [
            'pending',
            'low',
            'medium',
            'high',
            'critical'
        ];

        return in_array(
            $priority,
            $allowed,
            true
        )
            ? $priority
            : 'pending';
    }
}


/*
|--------------------------------------------------------------------------
| Access Control
|--------------------------------------------------------------------------
*/

if (!in_array($userRole, ['doctor', 'admin'], true)) {

    http_response_code(403);

    include __DIR__ . '/includes/header.php';

    ?>

    <main class="review-page">

        <div class="review-container">

            <div class="review-error-card">

                <div class="big-icon">🔒</div>

                <h1>Access Restricted</h1>

                <p>
                    Only authorized doctors and administrators can
                    review triage cases.
                </p>

                <a
                    href="dashboard.php"
                    class="review-btn primary"
                >
                    Return to Dashboard
                </a>

            </div>

        </div>

    </main>

    <?php

    include __DIR__ . '/includes/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Case ID
|--------------------------------------------------------------------------
*/

$caseId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$caseId) {

    http_response_code(400);

    $error = 'Invalid triage case ID.';
}


/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $caseId
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        function_exists('verify_csrf_token')
        &&
        (
            empty($_POST['csrf_token'])
            ||
            !verify_csrf_token(
                $_POST['csrf_token']
            )
        )
    ) {

        $error =
            'Your session expired. Please refresh the page and try again.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Form Values
            |--------------------------------------------------------------------------
            */

            $reviewNotes = trim(
                $_POST['review_notes'] ?? ''
            );

            $priority = strtolower(
                trim(
                    $_POST['priority'] ?? 'pending'
                )
            );

            $status = strtolower(
                trim(
                    $_POST['status'] ?? 'pending_review'
                )
            );

            $allowedPriorities = [
                'pending',
                'low',
                'medium',
                'high',
                'critical'
            ];

            $allowedStatuses = [
                'pending_review',
                'under_review',
                'reviewed',
                'referred',
                'closed'
            ];


            if (
                !in_array(
                    $priority,
                    $allowedPriorities,
                    true
                )
            ) {

                throw new Exception(
                    'Invalid priority selected.'
                );
            }


            if (
                !in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                throw new Exception(
                    'Invalid review status selected.'
                );
            }


            if ($reviewNotes === '') {

                throw new Exception(
                    'Please enter reviewer notes.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Check Case
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare(
                "SELECT id
                 FROM triage_reports
                 WHERE id = ?
                 LIMIT 1"
            );

            $check->execute([
                $caseId
            ]);

            if (!$check->fetch()) {

                throw new Exception(
                    'The requested triage case was not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Upload Variables
            |--------------------------------------------------------------------------
            */

            $uploadedDocument = null;
            $ocrText = null;
            $prescriptionText = null;
            $ocrReport = null;


            /*
            |--------------------------------------------------------------------------
            | Medical Document Upload
            |--------------------------------------------------------------------------
            */

            if (
                isset($_FILES['medical_document'])
                &&
                $_FILES['medical_document']['error']
                    !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES['medical_document'];


                if (
                    $file['error']
                    !== UPLOAD_ERR_OK
                ) {

                    throw new Exception(
                        'Medical document upload failed.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | File Size
                |--------------------------------------------------------------------------
                */

                $maxSize = 10 * 1024 * 1024;

                if ($file['size'] > $maxSize) {

                    throw new Exception(
                        'Medical document must be smaller than 10 MB.'
                    );
                }


                if ($file['size'] <= 0) {

                    throw new Exception(
                        'The uploaded medical document is empty.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Extension
                |--------------------------------------------------------------------------
                */

                $originalName =
                    (string)$file['name'];

                $extension = strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );

                $allowedExtensions = [
                    'pdf',
                    'jpg',
                    'jpeg',
                    'png'
                ];


                if (
                    !in_array(
                        $extension,
                        $allowedExtensions,
                        true
                    )
                ) {

                    throw new Exception(
                        'Only PDF, JPG, JPEG and PNG medical documents are allowed.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | MIME Validation
                |--------------------------------------------------------------------------
                */

                $finfo = new finfo(
                    FILEINFO_MIME_TYPE
                );

                $mimeType = $finfo->file(
                    $file['tmp_name']
                );

                $allowedMimes = [

                    'pdf' => [
                        'application/pdf'
                    ],

                    'jpg' => [
                        'image/jpeg'
                    ],

                    'jpeg' => [
                        'image/jpeg'
                    ],

                    'png' => [
                        'image/png'
                    ]

                ];


                if (
                    !isset(
                        $allowedMimes[$extension]
                    )
                    ||
                    !in_array(
                        $mimeType,
                        $allowedMimes[$extension],
                        true
                    )
                ) {

                    throw new Exception(
                        'The uploaded file type does not match the selected document format.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Upload Directory
                |--------------------------------------------------------------------------
                */

                $uploadDirectory =
                    __DIR__
                    . DIRECTORY_SEPARATOR
                    . 'uploads'
                    . DIRECTORY_SEPARATOR
                    . 'medical_reports';


                if (
                    !is_dir(
                        $uploadDirectory
                    )
                ) {

                    if (
                        !mkdir(
                            $uploadDirectory,
                            0755,
                            true
                        )
                    ) {

                        throw new Exception(
                            'Could not create medical report upload directory.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Safe Filename
                |--------------------------------------------------------------------------
                */

                $safeFileName =
                    'case_'
                    . $caseId
                    . '_'
                    . date('Ymd_His')
                    . '_'
                    . bin2hex(
                        random_bytes(8)
                    )
                    . '.'
                    . $extension;


                $destination =
                    $uploadDirectory
                    . DIRECTORY_SEPARATOR
                    . $safeFileName;


                if (
                    !move_uploaded_file(
                        $file['tmp_name'],
                        $destination
                    )
                ) {

                    throw new Exception(
                        'Could not save the uploaded medical document.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Database Relative Path
                |--------------------------------------------------------------------------
                */

                $uploadedDocument =
                    'uploads/medical_reports/'
                    . $safeFileName;


                /*
                |--------------------------------------------------------------------------
                | OCR
                |--------------------------------------------------------------------------
                */

                try {

                    $ocrResult =
                        run_medical_ocr(
                            $destination,
                            $extension
                        );


                    $ocrText =
                        (string)(
                            $ocrResult['text']
                            ?? ''
                        );


                    $prescriptionText =
                        (string)(
                            $ocrResult['prescription']
                            ?? ''
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Full Structured OCR Report
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset(
                            $ocrResult['report']
                        )
                        &&
                        is_array(
                            $ocrResult['report']
                        )
                    ) {

                        $ocrReport =
                            $ocrResult['report'];

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Backward Compatibility
                        |--------------------------------------------------------------------------
                        */

                        $ocrReport = [

                            'status' => 'completed',

                            'engine' =>
                                'Tesseract OCR',

                            'method' =>
                                'Legacy OCR result',

                            'file_type' =>
                                strtoupper($extension),

                            'page_count' =>
                                $extension === 'pdf'
                                    ? null
                                    : 1,

                            'character_count' =>
                                function_exists('mb_strlen')
                                    ? mb_strlen(
                                        $ocrText,
                                        'UTF-8'
                                    )
                                    : strlen(
                                        $ocrText
                                    ),

                            'word_count' =>
                                $ocrText === ''
                                    ? 0
                                    : count(
                                        preg_split(
                                            '/\s+/u',
                                            trim($ocrText),
                                            -1,
                                            PREG_SPLIT_NO_EMPTY
                                        )
                                    ),

                            'prescription_line_count' =>
                                $prescriptionText === ''
                                    ? 0
                                    : count(
                                        preg_split(
                                            "/\r\n|\r|\n/",
                                            trim(
                                                $prescriptionText
                                            )
                                        )
                                    ),

                            'warnings' => [],

                            'errors' => [],

                            'pages' => [],

                            'generated_at' =>
                                date(
                                    'Y-m-d H:i:s'
                                )
                        ];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Ensure Report Metadata
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $ocrReport['generated_at']
                        )
                    ) {

                        $ocrReport['generated_at'] =
                            date(
                                'Y-m-d H:i:s'
                            );
                    }

                } catch (Throwable $ocrError) {

                    /*
                    |--------------------------------------------------------------------------
                    | Keep Uploaded Document Even If OCR Fails
                    |--------------------------------------------------------------------------
                    */

                    $ocrText =
                        'OCR failed: '
                        . $ocrError->getMessage();


                    $prescriptionText =
                        'Automatic prescription extraction was not available. Please review the uploaded document manually.';


                    $ocrReport = [

                        'status' => 'failed',

                        'engine' =>
                            'Tesseract OCR',

                        'method' =>
                            'OCR processing failed',

                        'file_type' =>
                            strtoupper($extension),

                        'page_count' => null,

                        'character_count' => 0,

                        'word_count' => 0,

                        'prescription_line_count' => 0,

                        'warnings' => [],

                        'errors' => [
                            $ocrError->getMessage()
                        ],

                        'pages' => [],

                        'generated_at' =>
                            date(
                                'Y-m-d H:i:s'
                            )

                    ];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Get Available Database Columns
            |--------------------------------------------------------------------------
            */

            $columns = [];

            $columnQuery = $pdo->query(
                "SHOW COLUMNS FROM triage_reports"
            );

            while (
                $column =
                    $columnQuery->fetch(
                        PDO::FETCH_ASSOC
                    )
            ) {

                $columns[] =
                    $column['Field'];
            }


            /*
            |--------------------------------------------------------------------------
            | Build UPDATE
            |--------------------------------------------------------------------------
            */

            $updates = [

                'priority = ?',

                'status = ?'

            ];

            $params = [

                $priority,

                $status

            ];


            /*
            |--------------------------------------------------------------------------
            | Review Notes
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'review_notes',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'review_notes = ?';

                $params[] =
                    $reviewNotes;
            }


            /*
            |--------------------------------------------------------------------------
            | Reviewer
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'reviewed_by',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'reviewed_by = ?';

                $params[] =
                    $userId;
            }


            if (
                in_array(
                    'reviewer_name',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'reviewer_name = ?';

                $params[] =
                    $userName;
            }


            if (
                in_array(
                    'reviewed_at',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'reviewed_at = NOW()';
            }


            /*
            |--------------------------------------------------------------------------
            | Uploaded Document
            |--------------------------------------------------------------------------
            */

            if (
                $uploadedDocument !== null
                &&
                in_array(
                    'uploaded_document',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'uploaded_document = ?';

                $params[] =
                    $uploadedDocument;
            }


            /*
            |--------------------------------------------------------------------------
            | OCR Text
            |--------------------------------------------------------------------------
            */

            if (
                $ocrText !== null
                &&
                in_array(
                    'ocr_text',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'ocr_text = ?';

                $params[] =
                    $ocrText;
            }


            /*
            |--------------------------------------------------------------------------
            | Prescription Text
            |--------------------------------------------------------------------------
            */

            if (
                $prescriptionText !== null
                &&
                in_array(
                    'prescription_text',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'prescription_text = ?';

                $params[] =
                    $prescriptionText;
            }


            /*
            |--------------------------------------------------------------------------
            | Full OCR Report
            |--------------------------------------------------------------------------
            */

            if (
                $ocrReport !== null
                &&
                in_array(
                    'ocr_report',
                    $columns,
                    true
                )
            ) {

                $updates[] =
                    'ocr_report = ?';

                $params[] =
                    review_json(
                        $ocrReport
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | Execute Update
            |--------------------------------------------------------------------------
            */

            $params[] =
                $caseId;


            $sql =
                "UPDATE triage_reports
                 SET "
                . implode(
                    ', ',
                    $updates
                )
                . "
                 WHERE id = ?";


            $stmt =
                $pdo->prepare(
                    $sql
                );


            $stmt->execute(
                $params
            );


            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */

            if (
                $uploadedDocument !== null
            ) {

                if (
                    $ocrReport !== null
                    &&
                    ($ocrReport['status'] ?? '')
                        === 'completed'
                ) {

                    $success =
                        'Medical document uploaded, OCR completed and full human review saved successfully.';

                } elseif (
                    $ocrReport !== null
                    &&
                    ($ocrReport['status'] ?? '')
                        === 'completed_with_warning'
                ) {

                    $success =
                        'Medical document uploaded. OCR completed with warnings. Please verify the extracted report against the original document.';

                } else {

                    $success =
                        'Medical document uploaded, but OCR could not be completed. Please verify the original document manually.';
                }

            } else {

                $success =
                    'Case review saved successfully.';
            }


        } catch (Throwable $e) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Case
|--------------------------------------------------------------------------
*/

$case = null;


if ($caseId) {

    $stmt = $pdo->prepare(
        "SELECT
            tr.*,
            p.patient_uid,
            p.name AS patient_name,
            p.age AS patient_age,
            p.language AS patient_language
         FROM triage_reports tr
         INNER JOIN patients p
            ON p.id = tr.patient_id
         WHERE tr.id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $caseId
    ]);


    $case =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$case) {

        $error =
            'Triage case not found.';
    }
}


/*
|--------------------------------------------------------------------------
| Decode Stored OCR Report
|--------------------------------------------------------------------------
*/

$storedOcrReport = null;


if (
    $case
    &&
    !empty(
        $case['ocr_report']
        ?? ''
    )
) {

    $decoded =
        json_decode(
            (string)$case['ocr_report'],
            true
        );


    if (
        is_array($decoded)
    ) {

        $storedOcrReport =
            $decoded;
    }
}


/*
|--------------------------------------------------------------------------
| Existing OCR Data
|--------------------------------------------------------------------------
*/

$existingOcrText =
    trim(
        (string)(
            $case['ocr_text']
            ?? ''
        )
    );


$existingPrescriptionText =
    trim(
        (string)(
            $case['prescription_text']
            ?? ''
        )
    );


include __DIR__ . '/includes/header.php';

?>

<style>

/* =========================================================
   PREMIUM REVIEW PAGE
========================================================= */

.review-page {
    min-height: calc(100vh - 80px);
    padding: 30px 18px 70px;

    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(0, 164, 164, .10),
            transparent 28%
        ),
        radial-gradient(
            circle at 100% 0%,
            rgba(0, 105, 190, .10),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #f5fbff 0%,
            #edf8f8 100%
        );
}

.review-container {
    width: 100%;
    max-width: 1280px;
    margin: auto;
}


/* =========================================================
   HERO
========================================================= */

.review-header {
    position: relative;
    overflow: hidden;

    padding: 32px;

    margin-bottom: 20px;

    border-radius: 26px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #052f5f 0%,
            #086fa5 55%,
            #00a4a4 100%
        );

    box-shadow:
        0 25px 65px
        rgba(5, 65, 100, .20);
}

.review-header::after {
    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    right: -90px;
    top: -110px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);
}

.review-eyebrow {
    position: relative;
    z-index: 1;

    margin-bottom: 8px;

    font-size: 9px;
    font-weight: 900;

    letter-spacing: 1.7px;

    opacity: .72;
}

.review-header h1 {
    position: relative;
    z-index: 1;

    margin: 0 0 8px;

    font-size: 30px;
    line-height: 1.15;
}

.review-header p {
    position: relative;
    z-index: 1;

    max-width: 780px;

    margin: 0;

    color:
        rgba(255,255,255,.76);

    font-size: 12px;
    line-height: 1.7;
}


/* =========================================================
   ALERTS
========================================================= */

.review-alert {
    padding: 15px 18px;

    margin-bottom: 18px;

    border-radius: 14px;

    font-size: 11px;
    font-weight: 800;
}

.review-alert.error {
    color: #9c2828;

    background: #fff0f0;

    border:
        1px solid #f0cccc;
}

.review-alert.success {
    color: #08744d;

    background: #ecfaf4;

    border:
        1px solid #c9eadb;
}


/* =========================================================
   MAIN GRID
========================================================= */

.review-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        350px;

    gap: 20px;

    align-items: start;
}


/* =========================================================
   CARDS
========================================================= */

.review-card {
    margin-bottom: 18px;

    padding: 24px;

    border:
        1px solid #dceaf0;

    border-radius: 21px;

    background:
        rgba(255,255,255,.97);

    box-shadow:
        0 14px 38px
        rgba(15,65,100,.065);
}

.review-card-title {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 15px;

    margin-bottom: 18px;
    padding-bottom: 14px;

    border-bottom:
        1px solid #edf2f5;
}

.review-card-title h2 {
    margin: 0;

    color: #123f5e;

    font-size: 15px;
}

.review-card-title span {
    color: #8b9aa4;

    font-size: 9px;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 10px;
}

.patient-item {
    padding: 14px;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #f8fbfd,
            #f3f9fb
        );

    border:
        1px solid #e5eef2;
}

.patient-item label {
    display: block;

    margin-bottom: 6px;

    color: #8998a3;

    font-size: 8px;
    font-weight: 900;

    text-transform: uppercase;
    letter-spacing: .6px;
}

.patient-item strong {
    display: block;

    color: #1c4c68;

    font-size: 11px;

    word-break: break-word;
}

.case-id {
    display: inline-flex;

    padding: 7px 10px;

    border-radius: 9px;

    color: #096e8d !important;

    background: #eaf7fa;

    font-family: monospace;

    font-size: 10px !important;
    font-weight: 900;
}


/* =========================================================
   CONTENT
========================================================= */

.content-block {
    margin-bottom: 18px;
}

.content-block:last-child {
    margin-bottom: 0;
}

.content-block h3 {
    margin: 0 0 8px;

    color: #31556d;

    font-size: 11px;
}

.content-block p {
    margin: 0;

    padding: 14px;

    border-radius: 11px;

    background: #f8fbfc;

    border:
        1px solid #e7eef2;

    color: #4e6879;

    font-size: 11px;
    line-height: 1.75;

    white-space: pre-wrap;
    word-break: break-word;
}


/* =========================================================
   PRIORITY
========================================================= */

.priority-pill {
    display: inline-flex;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 9px !important;
    font-weight: 900 !important;

    text-transform: uppercase;
}

.priority-pending {
    color: #765e16 !important;
    background: #fff8dc;
}

.priority-low {
    color: #176a49 !important;
    background: #e8f8f0;
}

.priority-medium {
    color: #876000 !important;
    background: #fff3d6;
}

.priority-high {
    color: #a64c18 !important;
    background: #ffeadc;
}

.priority-critical {
    color: #a32222 !important;
    background: #ffe5e5;
}


/* =========================================================
   DOCUMENT
========================================================= */

.document-panel {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 15px;

    padding: 17px;

    margin-bottom: 18px;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #f3faff,
            #eefafa
        );

    border:
        1px solid #d8eaf0;
}

.document-icon {
    width: 43px;
    height: 43px;

    display: grid;
    place-items: center;

    flex: 0 0 auto;

    border-radius: 12px;

    background: white;

    box-shadow:
        0 5px 15px
        rgba(15,70,100,.08);

    font-size: 21px;
}

.document-info {
    flex: 1;
}

.document-info strong {
    display: block;

    color: #28536c;

    font-size: 11px;
}

.document-info span {
    display: block;

    margin-top: 4px;

    color: #8798a3;

    font-size: 9px;
}


/* =========================================================
   OCR REPORT
========================================================= */

.ocr-report {
    overflow: hidden;

    margin-top: 18px;

    border:
        1px solid #d2e8ee;

    border-radius: 18px;

    background: #f8fcfd;
}

.ocr-report-header {
    padding: 19px;

    background:
        linear-gradient(
            135deg,
            #edfaff,
            #effafa
        );

    border-bottom:
        1px solid #d8e9ee;
}

.ocr-report-title {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 12px;
}

.ocr-report-title h3 {
    margin: 0;

    color: #15546d;

    font-size: 14px;
}

.ocr-report-subtitle {
    margin-top: 5px;

    color: #80929c;

    font-size: 9px;
}

.ocr-status {
    display: inline-flex;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 8px;
    font-weight: 900;

    text-transform: uppercase;
}

.ocr-status.completed {
    color: #08744d;
    background: #dff6eb;
}

.ocr-status.completed_with_warning {
    color: #876000;
    background: #fff2d0;
}

.ocr-status.failed {
    color: #a32222;
    background: #ffe5e5;
}

.ocr-status.processing {
    color: #17658a;
    background: #e4f5fb;
}


/* =========================================================
   OCR METRICS
========================================================= */

.ocr-metrics {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 9px;

    padding: 14px;
}

.ocr-metric {
    padding: 12px;

    border-radius: 12px;

    background: white;

    border:
        1px solid #e1edf1;
}

.ocr-metric label {
    display: block;

    margin-bottom: 5px;

    color: #8a9ba5;

    font-size: 8px;
    font-weight: 900;

    text-transform: uppercase;
}

.ocr-metric strong {
    display: block;

    color: #1e526d;

    font-size: 15px;
}


/* =========================================================
   OCR DETAILS
========================================================= */

.ocr-details {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    padding: 0 14px 14px;
}

.ocr-detail {
    padding: 12px;

    border-radius: 11px;

    background: white;

    border:
        1px solid #e2edf1;
}

.ocr-detail label {
    display: block;

    margin-bottom: 5px;

    color: #8a9ba5;

    font-size: 8px;
    font-weight: 900;

    text-transform: uppercase;
}

.ocr-detail strong {
    color: #315a70;

    font-size: 10px;
}


/* =========================================================
   OCR NOTICE
========================================================= */

.ocr-notice {
    margin: 0 14px 14px;

    padding: 12px 14px;

    border-radius: 11px;

    color: #725d34;

    background: #fffaf0;

    border:
        1px solid #f0e2bd;

    font-size: 9px;

    line-height: 1.6;
}


/* =========================================================
   OCR SECTION
========================================================= */

.ocr-section {
    padding: 16px;

    border-top:
        1px solid #e1edf1;
}

.ocr-section-title {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 10px;

    margin-bottom: 10px;
}

.ocr-section-title h4 {
    margin: 0;

    color: #28536c;

    font-size: 11px;
}

.ocr-section-title span {
    color: #899ba5;

    font-size: 8px;
}


/* =========================================================
   OCR TEXT
========================================================= */

.ocr-text {
    max-height: 360px;

    overflow: auto;

    padding: 15px;

    border-radius: 11px;

    background: white;

    border:
        1px solid #dcebef;

    color: #405e70;

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Consolas,
        monospace;

    font-size: 10px;

    line-height: 1.75;

    white-space: pre-wrap;

    word-break: break-word;
}


/* =========================================================
   PAGE RESULTS
========================================================= */

.ocr-page-list {
    display: grid;

    gap: 10px;
}

.ocr-page-card {
    overflow: hidden;

    border-radius: 12px;

    background: white;

    border:
        1px solid #dfecef;
}

.ocr-page-header {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 10px;

    padding: 11px 13px;

    background: #f4fafc;

    border-bottom:
        1px solid #e5eef1;
}

.ocr-page-header strong {
    color: #28536c;

    font-size: 10px;
}

.ocr-page-header span {
    color: #83949e;

    font-size: 8px;
}

.ocr-page-body {
    padding: 12px;
}

.ocr-page-error {
    padding: 11px;

    border-radius: 9px;

    color: #9c2828;

    background: #fff0f0;

    font-size: 9px;
}


/* =========================================================
   WARNING / ERROR LIST
========================================================= */

.ocr-list {
    display: grid;

    gap: 8px;
}

.ocr-list-item {
    padding: 11px 13px;

    border-radius: 10px;

    font-size: 9px;

    line-height: 1.5;
}

.ocr-list-item.warning {
    color: #735c2c;

    background: #fff9ed;

    border:
        1px solid #f0dfb8;
}

.ocr-list-item.error {
    color: #982d2d;

    background: #fff0f0;

    border:
        1px solid #efcccc;
}


/* =========================================================
   UPLOAD
========================================================= */

.upload-box {
    padding: 19px;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #f8fbfd,
            #f3fafb
        );

    border:
        1px dashed #bcd9e2;
}

.upload-box label {
    display: block;

    margin-bottom: 9px;

    color: #28536c;

    font-size: 11px;
    font-weight: 900;
}

.upload-box input[type="file"] {
    width: 100%;

    box-sizing: border-box;

    padding: 12px;

    border:
        1px solid #d6e5eb;

    border-radius: 10px;

    background: white;

    font-size: 10px;
}

.upload-help {
    margin-top: 8px;

    color: #7a8f9b;

    font-size: 9px;

    line-height: 1.55;
}


/* =========================================================
   FORM
========================================================= */

.review-form-group {
    margin-bottom: 17px;
}

.review-form-group label {
    display: block;

    margin-bottom: 7px;

    color: #35546c;

    font-size: 10px;
    font-weight: 900;
}

.review-control {
    width: 100%;

    box-sizing: border-box;

    padding: 12px;

    border:
        1px solid #d7e4eb;

    border-radius: 10px;

    outline: none;

    color: #214b65;

    background: #fbfdff;

    font-family: inherit;

    font-size: 11px;
}

.review-control:focus {
    border-color: #079db8;

    box-shadow:
        0 0 0 4px
        rgba(7,157,184,.08);

    background: white;
}

textarea.review-control {
    min-height: 155px;

    resize: vertical;

    line-height: 1.65;
}


/* =========================================================
   BUTTONS
========================================================= */

.review-actions {
    display: flex;

    gap: 9px;

    flex-wrap: wrap;
}

.review-btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 11px 15px;

    border-radius: 10px;

    border: 0;

    text-decoration: none;

    font-size: 10px;
    font-weight: 900;

    cursor: pointer;

    transition:
        transform .15s ease,
        box-shadow .15s ease,
        opacity .15s ease;
}

.review-btn:hover {
    transform: translateY(-1px);
}

.review-btn:disabled {
    opacity: .65;

    cursor: wait;

    transform: none;
}

.review-btn.primary {
    color: white;

    background:
        linear-gradient(
            135deg,
            #0878c5,
            #00a4a4
        );

    box-shadow:
        0 9px 20px
        rgba(0,130,170,.15);
}

.review-btn.secondary {
    color: #31566e;

    background: #edf5f8;

    border:
        1px solid #dce8ee;
}

.review-btn.report {
    color: #096d8d;

    background: white;

    border:
        1px solid #cfe4eb;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar-card {
    padding: 20px;

    margin-bottom: 16px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.96);

    border:
        1px solid #deebf1;

    box-shadow:
        0 10px 28px
        rgba(15,65,100,.05);
}

.sidebar-card h3 {
    margin: 0 0 14px;

    color: #174766;

    font-size: 13px;
}

.status-line {
    display: flex;

    justify-content: space-between;
    align-items: center;

    gap: 10px;

    padding: 9px 0;

    border-bottom:
        1px solid #edf2f5;
}

.status-line:last-child {
    border-bottom: 0;
}

.status-line span {
    color: #80919e;

    font-size: 9px;
}

.status-line strong {
    color: #31576f;

    font-size: 9px;

    text-align: right;

    word-break: break-word;
}


/* =========================================================
   REVIEWER
========================================================= */

.reviewer-card {
    background:
        linear-gradient(
            135deg,
            #eef8ff,
            #eefafa
        );
}

.reviewer-avatar {
    width: 46px;
    height: 46px;

    display: grid;
    place-items: center;

    margin-bottom: 10px;

    border-radius: 50%;

    color: white;

    background:
        linear-gradient(
            135deg,
            #086fa5,
            #00a4a4
        );

    font-weight: 900;

    box-shadow:
        0 8px 20px
        rgba(0,120,160,.16);
}


/* =========================================================
   SAFETY
========================================================= */

.safety-box {
    padding: 17px;

    border-radius: 14px;

    background: #fffaf0;

    border:
        1px solid #f0e2bd;

    color: #75623c;

    font-size: 9px;

    line-height: 1.65;
}

.safety-box strong {
    display: block;

    margin-bottom: 5px;
}


/* =========================================================
   ERROR CARD
========================================================= */

.review-error-card {
    max-width: 550px;

    margin: 80px auto;

    padding: 40px;

    text-align: center;

    background: white;

    border-radius: 22px;

    border:
        1px solid #dfebf0;

    box-shadow:
        0 20px 50px
        rgba(20,70,100,.08);
}

.review-error-card .big-icon {
    font-size: 40px;

    margin-bottom: 10px;
}

.review-error-card h1 {
    margin: 0 0 10px;

    color: #174766;

    font-size: 22px;
}

.review-error-card p {
    color: #738693;

    font-size: 12px;

    line-height: 1.6;

    margin-bottom: 22px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .review-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 760px) {

    .patient-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .ocr-metrics {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .review-page {
        padding:
            16px 12px 40px;
    }

    .review-header {
        padding: 23px;

        border-radius: 19px;
    }

    .review-header h1 {
        font-size: 24px;
    }

    .review-card {
        padding: 17px;

        border-radius: 17px;
    }

    .patient-grid,
    .ocr-details {
        grid-template-columns: 1fr;
    }

    .ocr-metrics {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .ocr-report-title {
        align-items: flex-start;

        flex-direction: column;
    }

    .document-panel {
        align-items: flex-start;

        flex-direction: column;
    }

    .document-panel .review-btn {
        width: 100%;
    }

    .review-actions {
        flex-direction: column;
    }

    .review-btn {
        width: 100%;

        box-sizing: border-box;
    }

}

</style>


<main class="review-page">

<div class="review-container">


<?php if ($error && !$case): ?>

    <div class="review-error-card">

        <div class="big-icon">⚠️</div>

        <h1>Case unavailable</h1>

        <p>
            <?= review_h($error) ?>
        </p>

        <a
            href="queue.php"
            class="review-btn primary"
        >
            Return to Review Queue
        </a>

    </div>


<?php else: ?>


<section class="review-header">

    <div class="review-eyebrow">
        SWASTHYA SAARATHI • CLINICAL HUMAN REVIEW
    </div>

    <h1>
        Triage Case Review
    </h1>

    <p>
        Review patient information, triage indicators and uploaded
        medical documents. OCR provides document transcription only;
        extracted information must be verified against the original
        document by an authorized reviewer.
    </p>

</section>


<?php if ($error): ?>

    <div class="review-alert error">
        ⚠️ <?= review_h($error) ?>
    </div>

<?php endif; ?>


<?php if ($success): ?>

    <div class="review-alert success">
        ✓ <?= review_h($success) ?>
    </div>

<?php endif; ?>


<div class="review-grid">


<div>


<!-- =====================================================
     PATIENT INFORMATION
====================================================== -->

<section class="review-card">

    <div class="review-card-title">

        <div>

            <h2>
                Patient Information
            </h2>

            <span>
                Patient record
            </span>

        </div>

        <span class="case-id">
            <?= review_h(
                $case['triage_uid'] ?? ''
            ) ?>
        </span>

    </div>


    <div class="patient-grid">

        <div class="patient-item">

            <label>
                Patient
            </label>

            <strong>
                <?= review_h(
                    $case['patient_name'] ?? ''
                ) ?>
            </strong>

        </div>


        <div class="patient-item">

            <label>
                Patient UID
            </label>

            <strong>
                <?= review_h(
                    $case['patient_uid'] ?? ''
                ) ?>
            </strong>

        </div>


        <div class="patient-item">

            <label>
                Age
            </label>

            <strong>
                <?= review_h(
                    $case['patient_age']
                    ?? 'Not recorded'
                ) ?>
            </strong>

        </div>


        <div class="patient-item">

            <label>
                Language
            </label>

            <strong>
                <?= review_h(
                    $case['patient_language']
                    ?? 'Not recorded'
                ) ?>
            </strong>

        </div>

    </div>

</section>


<!-- =====================================================
     CASE INFORMATION
====================================================== -->

<section class="review-card">

    <div class="review-card-title">

        <div>

            <h2>
                Case Information
            </h2>

            <span>
                Patient-reported and system-organized information
            </span>

        </div>


        <?php

        $currentPriority =
            strtolower(
                (string)(
                    $case['priority']
                    ?? 'pending'
                )
            );

        $priorityClass =
            review_priority_class(
                $currentPriority
            );

        ?>

        <span
            class="priority-pill priority-<?= review_h(
                $priorityClass
            ) ?>"
        >
            <?= review_h(
                ucfirst($currentPriority)
            ) ?>
        </span>

    </div>


    <div class="content-block">

        <h3>
            Patient-reported symptoms
        </h3>

        <p>
            <?= review_h(
                $case['symptoms']
                ?? 'No symptoms recorded.'
            ) ?>
        </p>

    </div>


    <div class="content-block">

        <h3>
            Structured summary
        </h3>

        <p>
            <?= review_h(
                $case['summary']
                ?? 'No summary recorded.'
            ) ?>
        </p>

    </div>


    <div class="content-block">

        <h3>
            Advisory urgency indicators
        </h3>

        <p>
            <?= review_h(
                $case['urgency_flags']
                ?: 'No urgency indicators recorded.'
            ) ?>
        </p>

    </div>


    <div class="content-block">

        <h3>
            Information requiring follow-up
        </h3>

        <p>
            <?= review_h(
                $case['missing_information']
                ?: 'No additional information recorded.'
            ) ?>
        </p>

    </div>

</section>


<!-- =====================================================
     MEDICAL DOCUMENT + OCR
====================================================== -->

<section class="review-card">

    <div class="review-card-title">

        <div>

            <h2>
                Medical Document & OCR
            </h2>

            <span>
                Complete extraction report
            </span>

        </div>

    </div>


    <?php if (!empty($case['uploaded_document'])): ?>

        <div class="document-panel">

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:12px;
                    flex:1;
                "
            >

                <div class="document-icon">
                    📄
                </div>

                <div class="document-info">

                    <strong>
                        Uploaded Medical Document
                    </strong>

                    <span>
                        Original source document •
                        <?= review_h(
                            strtoupper(
                                pathinfo(
                                    $case['uploaded_document'],
                                    PATHINFO_EXTENSION
                                )
                            )
                        ) ?>
                    </span>

                </div>

            </div>


            <a
                href="<?= review_h(
                    $case['uploaded_document']
                ) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="review-btn report"
            >
                Open Original ↗
            </a>

        </div>


        <?php

        /*
        |--------------------------------------------------------------------------
        | OCR Report Rendering
        |--------------------------------------------------------------------------
        */

        $ocrReport =
            $storedOcrReport;

        $ocrStatus =
            strtolower(
                (string)(
                    $ocrReport['status']
                    ?? 'completed'
                )
            );

        $ocrEngine =
            $ocrReport['engine']
            ?? 'Tesseract OCR';

        $ocrMethod =
            $ocrReport['method']
            ?? 'OCR extraction';

        $ocrFileType =
            $ocrReport['file_type']
            ?? strtoupper(
                pathinfo(
                    $case['uploaded_document'],
                    PATHINFO_EXTENSION
                )
            );

        $ocrPageCount =
            $ocrReport['page_count']
            ?? null;

        $ocrCharacters =
            $ocrReport['character_count']
            ?? (
                function_exists('mb_strlen')
                    ? mb_strlen(
                        $existingOcrText,
                        'UTF-8'
                    )
                    : strlen(
                        $existingOcrText
                    )
            );

        $ocrWords =
            $ocrReport['word_count']
            ?? (
                $existingOcrText === ''
                    ? 0
                    : count(
                        preg_split(
                            '/\s+/u',
                            trim(
                                $existingOcrText
                            ),
                            -1,
                            PREG_SPLIT_NO_EMPTY
                        )
                    )
            );

        $ocrPrescriptionLines =
            $ocrReport['prescription_line_count']
            ?? (
                $existingPrescriptionText === ''
                    ? 0
                    : count(
                        preg_split(
                            "/\r\n|\r|\n/",
                            trim(
                                $existingPrescriptionText
                            )
                        )
                    )
            );

        $ocrProcessingSeconds =
            $ocrReport['processing_seconds']
            ?? null;

        $ocrWarnings =
            isset($ocrReport['warnings'])
            && is_array($ocrReport['warnings'])
                ? $ocrReport['warnings']
                : [];

        $ocrErrors =
            isset($ocrReport['errors'])
            && is_array($ocrReport['errors'])
                ? $ocrReport['errors']
                : [];

        $ocrPages =
            isset($ocrReport['pages'])
            && is_array($ocrReport['pages'])
                ? $ocrReport['pages']
                : [];

        ?>


        <div class="ocr-report">

            <!-- =================================================
                 REPORT HEADER
            ================================================== -->

            <div class="ocr-report-header">

                <div class="ocr-report-title">

                    <div>

                        <h3>
                            🔎 OCR Processing Report
                        </h3>

                        <div class="ocr-report-subtitle">
                            Complete machine-generated document
                            extraction information
                        </div>

                    </div>


                    <span
                        class="ocr-status <?= review_h(
                            $ocrStatus
                        ) ?>"
                    >
                        <?= review_h(
                            review_status_label(
                                $ocrStatus
                            )
                        ) ?>
                    </span>

                </div>

            </div>


            <!-- =================================================
                 METRICS
            ================================================== -->

            <div class="ocr-metrics">

                <div class="ocr-metric">

                    <label>
                        Pages
                    </label>

                    <strong>
                        <?= $ocrPageCount !== null
                            ? review_h($ocrPageCount)
                            : '—'
                        ?>
                    </strong>

                </div>


                <div class="ocr-metric">

                    <label>
                        Characters
                    </label>

                    <strong>
                        <?= review_h(
                            number_format(
                                (int)$ocrCharacters
                            )
                        ) ?>
                    </strong>

                </div>


                <div class="ocr-metric">

                    <label>
                        Words
                    </label>

                    <strong>
                        <?= review_h(
                            number_format(
                                (int)$ocrWords
                            )
                        ) ?>
                    </strong>

                </div>


                <div class="ocr-metric">

                    <label>
                        Medication Lines
                    </label>

                    <strong>
                        <?= review_h(
                            number_format(
                                (int)$ocrPrescriptionLines
                            )
                        ) ?>
                    </strong>

                </div>

            </div>


            <!-- =================================================
                 TECHNICAL DETAILS
            ================================================== -->

            <div class="ocr-details">

                <div class="ocr-detail">

                    <label>
                        OCR Engine
                    </label>

                    <strong>
                        <?= review_h(
                            $ocrEngine
                        ) ?>
                    </strong>

                </div>


                <div class="ocr-detail">

                    <label>
                        Document Type
                    </label>

                    <strong>
                        <?= review_h(
                            strtoupper(
                                (string)$ocrFileType
                            )
                        ) ?>
                    </strong>

                </div>


                <div class="ocr-detail">

                    <label>
                        Processing Method
                    </label>

                    <strong>
                        <?= review_h(
                            $ocrMethod
                        ) ?>
                    </strong>

                </div>


                <div class="ocr-detail">

                    <label>
                        Processing Time
                    </label>

                    <strong>

                        <?php if (
                            $ocrProcessingSeconds !== null
                        ): ?>

                            <?= review_h(
                                $ocrProcessingSeconds
                            ) ?> sec

                        <?php else: ?>

                            Not recorded

                        <?php endif; ?>

                    </strong>

                </div>

            </div>


            <!-- =================================================
                 SAFETY NOTICE
            ================================================== -->

            <div class="ocr-notice">

                <strong>
                    ⚕️ Human verification required
                </strong>

                OCR is a transcription aid. Recognition can
                contain spelling, number, dosage or formatting
                errors. Verify extracted medication information
                against the original document before relying on it.

            </div>


            <!-- =================================================
                 WARNINGS
            ================================================== -->

            <?php if (!empty($ocrWarnings)): ?>

                <div class="ocr-section">

                    <div class="ocr-section-title">

                        <h4>
                            ⚠️ OCR Warnings
                        </h4>

                        <span>
                            <?= count($ocrWarnings) ?> warning(s)
                        </span>

                    </div>


                    <div class="ocr-list">

                        <?php foreach (
                            $ocrWarnings
                            as $warning
                        ): ?>

                            <div class="ocr-list-item warning">

                                <?= review_h(
                                    $warning
                                ) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ERRORS
            ================================================== -->

            <?php if (!empty($ocrErrors)): ?>

                <div class="ocr-section">

                    <div class="ocr-section-title">

                        <h4>
                            ❌ OCR Errors
                        </h4>

                        <span>
                            <?= count($ocrErrors) ?> error(s)
                        </span>

                    </div>


                    <div class="ocr-list">

                        <?php foreach (
                            $ocrErrors
                            as $ocrError
                        ): ?>

                            <div class="ocr-list-item error">

                                <?= review_h(
                                    $ocrError
                                ) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 COMPLETE OCR TEXT
            ================================================== -->

            <?php if ($existingOcrText !== ''): ?>

                <div class="ocr-section">

                    <div class="ocr-section-title">

                        <h4>
                            📄 Complete Extracted OCR Text
                        </h4>

                        <span>
                            Full transcription
                        </span>

                    </div>


                    <div class="ocr-text">
<?= review_h(
    $existingOcrText
) ?>
                    </div>

                </div>

            <?php else: ?>

                <div class="ocr-section">

                    <div class="ocr-section-title">

                        <h4>
                            📄 Extracted OCR Text
                        </h4>

                    </div>

                    <div class="ocr-list-item warning">

                        No readable OCR text is currently
                        available. Please inspect the original
                        document manually.

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 PRESCRIPTION EXTRACTION
            ================================================== -->

            <div class="ocr-section">

                <div class="ocr-section-title">

                    <h4>
                        💊 Medication / Prescription Extraction
                    </h4>

                    <span>
                        Automatic keyword-based extraction
                    </span>

                </div>


                <div class="ocr-text">

<?= review_h(
    $existingPrescriptionText !== ''
        ? $existingPrescriptionText
        : 'No medication-related text could be automatically identified. Please review the original document.'
) ?>

                </div>

            </div>


            <!-- =================================================
                 PER-PAGE REPORT
            ================================================== -->

            <?php if (!empty($ocrPages)): ?>

                <div class="ocr-section">

                    <div class="ocr-section-title">

                        <h4>
                            📑 Page-by-Page OCR Results
                        </h4>

                        <span>
                            <?= count($ocrPages) ?> page result(s)
                        </span>

                    </div>


                    <div class="ocr-page-list">

                        <?php foreach (
                            $ocrPages
                            as $pageData
                        ): ?>

                            <?php

                            $pageNumber =
                                $pageData['page']
                                ?? '?';

                            $pageStatus =
                                strtolower(
                                    (string)(
                                        $pageData['status']
                                        ?? 'unknown'
                                    )
                                );

                            $pageText =
                                (string)(
                                    $pageData['text']
                                    ?? ''
                                );

                            $pageError =
                                (string)(
                                    $pageData['error']
                                    ?? ''
                                );

                            $pageCharacters =
                                $pageData['character_count']
                                ?? 0;

                            $pageWords =
                                $pageData['word_count']
                                ?? 0;

                            $pageTime =
                                $pageData[
                                    'processing_seconds'
                                ]
                                ?? null;

                            ?>

                            <div class="ocr-page-card">

                                <div class="ocr-page-header">

                                    <strong>
                                        Page
                                        <?= review_h(
                                            $pageNumber
                                        ) ?>
                                    </strong>

                                    <span>

                                        <?= review_h(
                                            ucfirst(
                                                $pageStatus
                                            )
                                        ) ?>

                                        •

                                        <?= review_h(
                                            $pageCharacters
                                        ) ?>
                                        chars

                                        •

                                        <?= review_h(
                                            $pageWords
                                        ) ?>
                                        words

                                        <?php if (
                                            $pageTime !== null
                                        ): ?>

                                            •
                                            <?= review_h(
                                                $pageTime
                                            ) ?> sec

                                        <?php endif; ?>

                                    </span>

                                </div>


                                <div class="ocr-page-body">

                                    <?php if (
                                        $pageStatus ===
                                        'failed'
                                    ): ?>

                                        <div class="ocr-page-error">

                                            ❌

                                            <?= review_h(
                                                $pageError
                                                    !== ''
                                                    ? $pageError
                                                    : 'OCR failed for this page.'
                                            ) ?>

                                        </div>

                                    <?php elseif (
                                        trim(
                                            $pageText
                                        ) !== ''
                                    ): ?>

                                        <div class="ocr-text">

<?= review_h(
    $pageText
) ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="ocr-page-error">

                                            No readable text detected
                                            on this page.

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 RAW REPORT
            ================================================== -->

            <?php if (
                is_array(
                    $ocrReport
                )
            ): ?>

                <div class="ocr-section">

                    <details>

                        <summary
                            style="
                                cursor:pointer;
                                color:#28536c;
                                font-size:10px;
                                font-weight:900;
                            "
                        >
                            🔧 View Complete Technical OCR Report
                        </summary>


                        <div
                            class="ocr-text"
                            style="
                                margin-top:10px;
                                max-height:450px;
                            "
                        ><?= review_h(
                            review_json(
                                $ocrReport
                            )
                        ) ?></div>

                    </details>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         UPLOAD / REVIEW FORM
    ====================================================== -->

    <form
        method="POST"
        enctype="multipart/form-data"
        action="review.php?id=<?= (int)$caseId ?>"
        id="reviewForm"
    >

        <?php if (
            function_exists('csrf_token')
        ): ?>

            <input
                type="hidden"
                name="csrf_token"
                value="<?= review_h(
                    csrf_token()
                ) ?>"
            >

        <?php endif; ?>


        <div class="upload-box">

            <label for="medical_document">

                📎 Upload New Prescription / Medical Report

            </label>


            <input
                type="file"
                name="medical_document"
                id="medical_document"
                accept="
                    .pdf,
                    .jpg,
                    .jpeg,
                    .png,
                    application/pdf,
                    image/jpeg,
                    image/png
                "
            >


            <div class="upload-help">

                PDF, JPG, JPEG or PNG only.
                Maximum file size: 10 MB.

                <br>

                The uploaded document will be processed by
                Tesseract OCR. The complete OCR report will be
                displayed above after processing.

            </div>

        </div>


        <br>


        <div class="review-form-group">

            <label for="status">
                Case Status
            </label>

            <select
                name="status"
                id="status"
                class="review-control"
            >

                <?php

                $statuses = [

                    'pending_review' =>
                        'Pending Review',

                    'under_review' =>
                        'Under Review',

                    'reviewed' =>
                        'Reviewed',

                    'referred' =>
                        'Referred',

                    'closed' =>
                        'Closed'

                ];

                foreach (
                    $statuses
                    as $value => $label
                ):

                ?>

                    <option
                        value="<?= review_h(
                            $value
                        ) ?>"
                        <?= (
                            $case['status']
                            ?? ''
                        ) === $value
                            ? 'selected'
                            : '' ?>
                    >

                        <?= review_h(
                            $label
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="review-form-group">

            <label for="priority">
                Final Recorded Priority
            </label>

            <select
                name="priority"
                id="priority"
                class="review-control"
            >

                <?php

                $priorities = [

                    'pending' =>
                        'Pending human assessment',

                    'low' =>
                        'Low',

                    'medium' =>
                        'Medium',

                    'high' =>
                        'High',

                    'critical' =>
                        'Critical'

                ];

                foreach (
                    $priorities
                    as $value => $label
                ):

                ?>

                    <option
                        value="<?= review_h(
                            $value
                        ) ?>"
                        <?= (
                            $case['priority']
                            ?? ''
                        ) === $value
                            ? 'selected'
                            : '' ?>
                    >

                        <?= review_h(
                            $label
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="review-form-group">

            <label for="review_notes">

                Reviewer Notes

                <span style="color:#c33">
                    *
                </span>

            </label>


            <textarea
                name="review_notes"
                id="review_notes"
                class="review-control"
                required
                placeholder="Record your professional observations, document verification, follow-up instructions or referral information..."
            ><?= review_h(
                $case['review_notes'] ?? ''
            ) ?></textarea>

        </div>


        <div class="review-actions">

            <a
                href="queue.php"
                class="review-btn secondary"
            >
                ← Back to Queue
            </a>


            <button
                type="submit"
                class="review-btn primary"
                id="saveReviewButton"
            >
                ✓ Upload & Save Human Review
            </button>

        </div>

    </form>

</section>

</div>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside>


<div class="sidebar-card reviewer-card">

    <div class="reviewer-avatar">

        <?= review_h(
            strtoupper(
                substr(
                    (string)$userName,
                    0,
                    1
                )
            )
        ) ?>

    </div>


    <h3>
        Authorized Reviewer
    </h3>


    <div class="status-line">

        <span>
            Reviewer
        </span>

        <strong>
            <?= review_h(
                $userName
            ) ?>
        </strong>

    </div>


    <div class="status-line">

        <span>
            Role
        </span>

        <strong>
            <?= review_h(
                ucfirst(
                    $userRole
                )
            ) ?>
        </strong>

    </div>

</div>


<div class="sidebar-card">

    <h3>
        Case Status
    </h3>


    <div class="status-line">

        <span>
            Case ID
        </span>

        <strong>
            <?= review_h(
                $case['triage_uid']
                ?? ''
            ) ?>
        </strong>

    </div>


    <div class="status-line">

        <span>
            Current Status
        </span>

        <strong>
            <?= review_h(
                review_status_label(
                    strtolower(
                        (string)(
                            $case['status']
                            ?? 'pending'
                        )
                    )
                )
            ) ?>
        </strong>

    </div>


    <div class="status-line">

        <span>
            Priority
        </span>

        <strong>
            <?= review_h(
                ucfirst(
                    (string)(
                        $case['priority']
                        ?? 'pending'
                    )
                )
            ) ?>
        </strong>

    </div>


    <div class="status-line">

        <span>
            Created
        </span>

        <strong>
            <?= review_h(
                $case['created_at']
                ?? '—'
            ) ?>
        </strong>

    </div>

</div>


<div class="sidebar-card">

    <div class="safety-box">

        <strong>
            ⚕️ Human Oversight
        </strong>

        OCR only transcribes information found in the
        uploaded document. It does not independently validate
        a prescription or determine treatment. Extracted
        medication text must be checked against the original
        document by an authorized professional.

    </div>

</div>


<div class="sidebar-card">

    <h3>
        Case Navigation
    </h3>


    <div class="review-actions">

        <a
            href="patients.php?id=<?= urlencode(
                (string)(
                    $case['patient_uid']
                    ?? ''
                )
            ) ?>"
            class="review-btn secondary"
        >
            View Patient Record
        </a>


        <a
            href="queue.php"
            class="review-btn secondary"
        >
            Review Queue
        </a>


        <a
            href="dashboard.php"
            class="review-btn secondary"
        >
            Dashboard
        </a>

    </div>

</div>


</aside>


</div>


<?php endif; ?>


</div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| Review Form
|--------------------------------------------------------------------------
*/

const reviewForm =
    document.getElementById(
        'reviewForm'
    );


if (reviewForm) {

    reviewForm.addEventListener(
        'submit',
        function () {

            const button =
                document.getElementById(
                    'saveReviewButton'
                );


            if (button) {

                button.disabled = true;

                button.textContent =
                    'Uploading & Running OCR…';

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| File Validation
|--------------------------------------------------------------------------
*/

const documentInput =
    document.getElementById(
        'medical_document'
    );


if (documentInput) {

    documentInput.addEventListener(
        'change',
        function () {

            const file =
                this.files[0];


            if (!file) {
                return;
            }


            const allowedMimeTypes = [

                'application/pdf',

                'image/jpeg',

                'image/png'

            ];


            const extension =
                file.name
                    .split('.')
                    .pop()
                    .toLowerCase();


            const allowedExtensions = [

                'pdf',

                'jpg',

                'jpeg',

                'png'

            ];


            if (
                !allowedMimeTypes.includes(
                    file.type
                )
                &&
                !allowedExtensions.includes(
                    extension
                )
            ) {

                alert(
                    'Only PDF, JPG, JPEG and PNG medical documents are allowed.'
                );

                this.value = '';

                return;
            }


            const maxSize =
                10 * 1024 * 1024;


            if (
                file.size > maxSize
            ) {

                alert(
                    'The medical document must be smaller than 10 MB.'
                );

                this.value = '';

                return;
            }


            if (
                file.size === 0
            ) {

                alert(
                    'The selected document is empty.'
                );

                this.value = '';

                return;
            }

        }
    );

}

</script>


<?php

include __DIR__ . '/includes/footer.php';

?>