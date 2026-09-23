<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function create_csrf_token(): string
{
    if (empty($_SESSION['register_csrf'])) {
        $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['register_csrf'];
}


function verify_register_csrf(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['register_csrf'])
        && hash_equals($_SESSION['register_csrf'], $token);
}


/*
|--------------------------------------------------------------------------
| PATIENT UID
|--------------------------------------------------------------------------
|
| Examples:
|
| SS-2026-49CA69
| SS-2026-7A0DED
| SS-2026-304C18
| SS-2026-000001
|
*/

function generate_patient_uid(PDO $pdo): string
{
    $year = date('Y');

    do {

        /*
         * 6-character uppercase alphanumeric code.
         */
        $code = strtoupper(
            substr(
                bin2hex(random_bytes(4)),
                0,
                6
            )
        );

        $patientUid = 'SS-' . $year . '-' . $code;

        $check = $pdo->prepare(
            "SELECT id
             FROM patients
             WHERE patient_uid = ?
             LIMIT 1"
        );

        $check->execute([$patientUid]);

    } while ($check->fetch());

    return $patientUid;
}


/*
|--------------------------------------------------------------------------
| PATIENT UID VALIDATION
|--------------------------------------------------------------------------
|
| Accepts:
|
| SS-2026-49CA69
| SS-2026-7A0DED
| SS-2026-000001
|
*/

function is_valid_patient_uid(string $patientUid): bool
{
    return (bool) preg_match(
        '/^SS-\d{4}-[A-Z0-9]{6}$/',
        strtoupper(trim($patientUid))
    );
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

$name = '';
$email = '';
$phone = '';
$age = '';
$gender = '';
$role = 'patient';

$password = '';
$confirmPassword = '';

$createdPatientUid = '';


/*
|--------------------------------------------------------------------------
| REGISTRATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (!verify_register_csrf($_POST['csrf_token'] ?? null)) {

        $error = 'Your session expired. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | INPUT
        |--------------------------------------------------------------------------
        */

        $name = trim($_POST['name'] ?? '');

        $email = strtolower(
            trim($_POST['email'] ?? '')
        );

        $phone = trim(
            $_POST['phone'] ?? ''
        );

        $age = trim(
            $_POST['age'] ?? ''
        );

        $gender = trim(
            $_POST['gender'] ?? ''
        );

        $role = strtolower(
            trim($_POST['role'] ?? 'patient')
        );

        $password = $_POST['password'] ?? '';

        $confirmPassword = $_POST['confirm_password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            $error = 'Please enter your full name.';

        } elseif (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 100
        ) {

            $error = 'Please enter a valid name.';

        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $error = 'Please enter a valid email address.';

        } elseif (
            $phone === ''
        ) {

            $error = 'Please enter your phone number.';

        } elseif (
            !preg_match(
                '/^[0-9+\-\s()]{7,20}$/',
                $phone
            )
        ) {

            $error = 'Please enter a valid phone number.';

        } elseif (
            $age === '' ||
            !ctype_digit($age)
        ) {

            $error = 'Please enter a valid age.';

        } elseif (
            (int)$age < 1 ||
            (int)$age > 120
        ) {

            $error = 'Age must be between 1 and 120.';

        } elseif ($gender === '') {

            $error = 'Please select your gender.';

        } elseif (
            !in_array(
                $gender,
                ['Male', 'Female', 'Other', 'Prefer not to say'],
                true
            )
        ) {

            $error = 'Please select a valid gender.';

        } elseif (
            strlen($password) < 8
        ) {

            $error = 'Password must contain at least 8 characters.';

        } elseif (
            !preg_match('/[A-Z]/', $password)
        ) {

            $error = 'Password must contain at least one uppercase letter.';

        } elseif (
            !preg_match('/[a-z]/', $password)
        ) {

            $error = 'Password must contain at least one lowercase letter.';

        } elseif (
            !preg_match('/[0-9]/', $password)
        ) {

            $error = 'Password must contain at least one number.';

        } elseif (
            $password !== $confirmPassword
        ) {

            $error = 'Passwords do not match.';
        }


        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */

        if (!$error) {

            /*
             * Public registration is only for patients.
             */

            $role = 'patient';
        }


        /*
        |--------------------------------------------------------------------------
        | DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        if (!$error) {

            $check = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $check->execute([$email]);

            if ($check->fetch()) {

                $error =
                    'An account with this email address already exists.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE PATIENT + USER
        |--------------------------------------------------------------------------
        */

        if (!$error) {

            try {

                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | GENERATE UNIQUE PATIENT ID
                |--------------------------------------------------------------------------
                */

                $patientUid = generate_patient_uid($pdo);


                /*
                |--------------------------------------------------------------------------
                | CREATE PATIENT RECORD
                |--------------------------------------------------------------------------
                */

                $patientStmt = $pdo->prepare(
                    "INSERT INTO patients
                    (
                        patient_uid,
                        name,
                        age,
                        gender,
                        phone,
                        language,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW()
                    )"
                );


                $patientStmt->execute([
                    $patientUid,
                    $name,
                    (int)$age,
                    $gender,
                    $phone,
                    'Odia'
                ]);


                /*
                |--------------------------------------------------------------------------
                | GET PATIENT DATABASE ID
                |--------------------------------------------------------------------------
                */

                $patientId = (int)$pdo->lastInsertId();


                /*
                |--------------------------------------------------------------------------
                | PASSWORD HASH
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | CREATE USER
                |--------------------------------------------------------------------------
                */

                $userStmt = $pdo->prepare(
                    "INSERT INTO users
                    (
                        name,
                        email,
                        password_hash,
                        role,
                        phone,
                        age,
                        gender,
                        patient_id,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW()
                    )"
                );


                $userStmt->execute([
                    $name,
                    $email,
                    $passwordHash,
                    'patient',
                    $phone,
                    (int)$age,
                    $gender,
                    $patientId
                ]);


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                $createdPatientUid = $patientUid;

                $success =
                    'Your account has been created successfully.';


                /*
                |--------------------------------------------------------------------------
                | CLEAR FORM
                |--------------------------------------------------------------------------
                */

                $name = '';
                $email = '';
                $phone = '';
                $age = '';
                $gender = '';
                $role = 'patient';
                $password = '';
                $confirmPassword = '';


                /*
                |--------------------------------------------------------------------------
                | NEW CSRF
                |--------------------------------------------------------------------------
                */

                unset($_SESSION['register_csrf']);


            } catch (PDOException $e) {

                /*
                |--------------------------------------------------------------------------
                | ROLLBACK
                |--------------------------------------------------------------------------
                */

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }


                /*
                |--------------------------------------------------------------------------
                | DUPLICATE PATIENT UID
                |--------------------------------------------------------------------------
                */

                if (
                    stripos(
                        $e->getMessage(),
                        'patient_uid'
                    ) !== false
                ) {

                    $error =
                        'Unable to generate a unique Patient ID. Please try again.';

                } elseif (
                    stripos(
                        $e->getMessage(),
                        'Duplicate'
                    ) !== false
                ) {

                    $error =
                        'This account information already exists.';

                } else {

                    /*
                     * Development-friendly error.
                     * You can hide $e->getMessage() in production.
                     */

                    $error =
                        'Unable to create your account. Database error: '
                        . $e->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken = create_csrf_token();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Create Account | Swasthya Saarathi';

include __DIR__ . '/includes/header.php';

?>

<style>

/* =========================================================
   PREMIUM REGISTRATION PAGE
========================================================= */

.register-page {
    min-height: calc(100vh - 80px);

    padding: 55px 20px;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(25,190,190,.15),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 15%,
            rgba(0,105,190,.14),
            transparent 32%
        ),
        linear-gradient(
            135deg,
            #f5fbff,
            #edf9f8
        );
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.register-container {

    max-width: 1180px;

    margin: auto;

    display: grid;

    grid-template-columns:
        .82fr
        1.18fr;

    gap: 30px;

    align-items: stretch;
}


/* =========================================================
   LEFT PREMIUM PANEL
========================================================= */

.register-info {

    position: relative;

    overflow: hidden;

    border-radius: 30px;

    padding: 45px;

    color: white;

    background:
        linear-gradient(
            145deg,
            #062f5b 0%,
            #075b9d 48%,
            #00a6a6 100%
        );

    box-shadow:
        0 30px 70px
        rgba(4,52,94,.25),

        inset 0 1px
        rgba(255,255,255,.25);
}


.register-info::before {

    content: "";

    position: absolute;

    width: 390px;
    height: 390px;

    right: -160px;
    top: -120px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);
}


.register-info::after {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    left: -120px;
    bottom: -130px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);
}


.info-content {

    position: relative;

    z-index: 2;
}


.info-badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 9px 14px;

    border:
        1px solid
        rgba(255,255,255,.25);

    border-radius: 999px;

    background:
        rgba(255,255,255,.10);

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1px;
}


.info-title {

    margin: 28px 0 15px;

    font-size: 43px;

    line-height: 1.08;

    letter-spacing: -1px;
}


.info-title span {

    display: block;

    margin-top: 5px;

    color: #7df0e8;
}


.info-description {

    color:
        rgba(255,255,255,.82);

    line-height: 1.7;

    font-size: 15px;
}


.info-features {

    margin-top: 35px;

    display: grid;

    gap: 17px;
}


.info-feature {

    display: flex;

    align-items: center;

    gap: 14px;
}


.feature-icon {

    width: 45px;
    height: 45px;

    display: grid;

    place-items: center;

    flex: 0 0 45px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid
        rgba(255,255,255,.17);

    font-size: 20px;
}


.feature-text strong {

    display: block;

    font-size: 14px;
}


.feature-text span {

    display: block;

    margin-top: 3px;

    color:
        rgba(255,255,255,.68);

    font-size: 12px;
}


/* =========================================================
   REGISTER CARD
========================================================= */

.register-card {

    background:
        rgba(255,255,255,.96);

    border:
        1px solid
        rgba(20,90,130,.10);

    border-radius: 30px;

    padding: 42px;

    box-shadow:
        0 30px 75px
        rgba(13,65,105,.13),

        0 4px 12px
        rgba(13,65,105,.05);

    backdrop-filter: blur(18px);
}


.register-heading {

    margin-bottom: 28px;
}


.register-heading h1 {

    margin: 0 0 8px;

    color: #073b70;

    font-size: 32px;

    letter-spacing: -.5px;
}


.register-heading p {

    margin: 0;

    color: #64788d;

    line-height: 1.6;
}


/* =========================================================
   PATIENT ID SUCCESS
========================================================= */

.patient-id-box {

    margin-bottom: 24px;

    padding: 20px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #effdfb,
            #f3f9ff
        );

    border:
        1px solid
        #c7ebe7;

    box-shadow:
        0 10px 25px
        rgba(0,130,140,.08);
}


.patient-id-label {

    color: #567180;

    font-size: 11px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.patient-id-value {

    margin-top: 7px;

    color: #073b70;

    font-size: 24px;

    font-weight: 900;

    letter-spacing: 1px;

    font-family:
        "Courier New",
        monospace;
}


.patient-id-note {

    margin-top: 7px;

    color: #6c8292;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   ALERTS
========================================================= */

.register-error,
.register-success {

    padding: 15px 17px;

    border-radius: 14px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.5;
}


.register-error {

    background: #fff1f1;

    color: #9d2424;

    border:
        1px solid
        #ffd1d1;
}


.register-success {

    background: #ecfbf5;

    color: #13744e;

    border:
        1px solid
        #bdebd5;
}


.register-success a {

    color: #0877c9;

    font-weight: 800;

    text-decoration: none;
}


/* =========================================================
   FORM
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr
        1fr;

    gap: 18px;
}


.form-group {

    margin-bottom: 18px;
}


.form-group.full {

    grid-column: 1 / -1;
}


.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #173d63;

    font-size: 13px;

    font-weight: 800;
}


.form-group input,
.form-group select {

    width: 100%;

    box-sizing: border-box;

    padding: 14px 15px;

    border:
        1px solid
        #d6e4ef;

    border-radius: 13px;

    background: #fbfdff;

    color: #173d63;

    font-size: 14px;

    outline: none;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}


.form-group input:hover,
.form-group select:hover {

    border-color: #a9c8dc;
}


.form-group input:focus,
.form-group select:focus {

    border-color: #079fc0;

    box-shadow:
        0 0 0 4px
        rgba(7,159,192,.10);

    background: #fff;

    transform: translateY(-1px);
}


.password-help {

    margin-top: 7px;

    color: #8090a0;

    font-size: 11px;

    line-height: 1.5;
}


/* =========================================================
   BUTTON
========================================================= */

.register-submit {

    width: 100%;

    border: 0;

    border-radius: 14px;

    padding: 16px 20px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0877c9,
            #00a5a5
        );

    font-size: 15px;

    font-weight: 900;

    cursor: pointer;

    box-shadow:
        0 13px 28px
        rgba(0,125,180,.23);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.register-submit:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 18px 34px
        rgba(0,125,180,.30);
}


.register-submit:active {

    transform:
        translateY(0);
}


/* =========================================================
   LOGIN
========================================================= */

.login-link {

    text-align: center;

    margin-top: 22px;

    color: #718396;

    font-size: 14px;
}


.login-link a {

    color: #086bb5;

    font-weight: 900;

    text-decoration: none;
}


.login-link a:hover {

    text-decoration: underline;
}


/* =========================================================
   PRIVACY
========================================================= */

.privacy-note {

    margin-top: 24px;

    padding: 14px;

    border-radius: 14px;

    background: #f3f9fd;

    border:
        1px solid
        #dfedf6;

    color: #617487;

    font-size: 11px;

    line-height: 1.6;
}


.privacy-note strong {

    color: #19496d;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 900px) {

    .register-container {

        grid-template-columns: 1fr;
    }

    .register-info {

        padding: 34px;
    }
}


@media (max-width: 560px) {

    .register-page {

        padding: 25px 12px;
    }

    .register-info {

        border-radius: 22px;

        padding: 25px;
    }

    .register-card {

        border-radius: 22px;

        padding: 24px;
    }

    .form-grid {

        grid-template-columns: 1fr;

        gap: 0;
    }

    .form-group.full {

        grid-column: auto;
    }

    .info-title {

        font-size: 31px;
    }

    .register-heading h1 {

        font-size: 27px;
    }
}

</style>


<main class="register-page">

    <div class="register-container">


        <!-- =====================================================
             LEFT PANEL
        ====================================================== -->

        <section class="register-info">

            <div class="info-content">

                <div class="info-badge">

                    🛡️ SECURE HEALTHCARE PORTAL

                </div>


                <h2 class="info-title">

                    Welcome to

                    <span>
                        Swasthya Saarathi
                    </span>

                </h2>


                <p class="info-description">

                    Create your secure patient account and access
                    organized healthcare triage support, case
                    management and human-reviewed workflows.

                </p>


                <div class="info-features">


                    <div class="info-feature">

                        <div class="feature-icon">
                            🔐
                        </div>

                        <div class="feature-text">

                            <strong>
                                Secure Account
                            </strong>

                            <span>
                                Protected authentication and sessions
                            </span>

                        </div>

                    </div>


                    <div class="info-feature">

                        <div class="feature-icon">
                            🪪
                        </div>

                        <div class="feature-text">

                            <strong>
                                Unique Patient ID
                            </strong>

                            <span>
                                Automatic SS-YYYY-XXXXXX identification
                            </span>

                        </div>

                    </div>


                    <div class="info-feature">

                        <div class="feature-icon">
                            🩺
                        </div>

                        <div class="feature-text">

                            <strong>
                                Healthcare Support
                            </strong>

                            <span>
                                Organize symptoms and health information
                            </span>

                        </div>

                    </div>


                    <div class="info-feature">

                        <div class="feature-icon">
                            👨‍⚕️
                        </div>

                        <div class="feature-text">

                            <strong>
                                Human Review
                            </strong>

                            <span>
                                Professional review remains part of workflow
                            </span>

                        </div>

                    </div>


                    <div class="info-feature">

                        <div class="feature-icon">
                            📄
                        </div>

                        <div class="feature-text">

                            <strong>
                                Case Management
                            </strong>

                            <span>
                                Patient information stays linked to cases
                            </span>

                        </div>

                    </div>


                </div>

            </div>

        </section>


        <!-- =====================================================
             REGISTRATION CARD
        ====================================================== -->

        <section class="register-card">


            <div class="register-heading">

                <h1>
                    Create your account
                </h1>

                <p>
                    Register securely to access the
                    Swasthya Saarathi healthcare portal.
                </p>

            </div>


            <?php if ($error): ?>

                <div
                    class="register-error"
                    role="alert"
                >

                    <strong>
                        Registration error
                    </strong>

                    <br>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div
                    class="register-success"
                    role="status"
                >

                    <strong>
                        Account created successfully.
                    </strong>

                    <br><br>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <?php if ($createdPatientUid): ?>

                    <div class="patient-id-box">

                        <div class="patient-id-label">

                            Your Patient Unique ID

                        </div>

                        <div class="patient-id-value">

                            <?= htmlspecialchars(
                                $createdPatientUid,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                        <div class="patient-id-note">

                            Please save this Patient ID.
                            You will use it when creating or accessing
                            healthcare cases.

                        </div>

                    </div>

                <?php endif; ?>


                <div class="login-link">

                    <a href="login.php">
                        Continue to sign in →
                    </a>

                </div>


            <?php else: ?>


                <!-- =================================================
                     FORM
                ================================================== -->

                <form
                    method="POST"
                    autocomplete="on"
                    novalidate
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $csrfToken,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >


                    <div class="form-grid">


                        <!-- NAME -->

                        <div class="form-group full">

                            <label for="name">
                                Full name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?= htmlspecialchars(
                                    $name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                maxlength="100"
                                required
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label for="email">
                                Email address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                            >

                        </div>


                        <!-- PHONE -->

                        <div class="form-group">

                            <label for="phone">
                                Phone number
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars(
                                    $phone,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="+91 98765 43210"
                                autocomplete="tel"
                                maxlength="20"
                                required
                            >

                        </div>


                        <!-- AGE -->

                        <div class="form-group">

                            <label for="age">
                                Age
                            </label>

                            <input
                                type="number"
                                id="age"
                                name="age"
                                value="<?= htmlspecialchars(
                                    $age,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="Enter age"
                                min="1"
                                max="120"
                                required
                            >

                        </div>


                        <!-- GENDER -->

                        <div class="form-group">

                            <label for="gender">
                                Gender
                            </label>

                            <select
                                id="gender"
                                name="gender"
                                required
                            >

                                <option value="">
                                    Select gender
                                </option>

                                <option
                                    value="Male"
                                    <?= $gender === 'Male'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= $gender === 'Female'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Female
                                </option>

                                <option
                                    value="Other"
                                    <?= $gender === 'Other'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Other
                                </option>

                                <option
                                    value="Prefer not to say"
                                    <?= $gender === 'Prefer not to say'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Prefer not to say
                                </option>

                            </select>

                        </div>


                        <!-- ROLE -->

                        <div class="form-group full">

                            <label for="role">
                                Account type
                            </label>

                            <select
                                id="role"
                                name="role"
                                disabled
                            >

                                <option selected>
                                    Patient
                                </option>

                            </select>

                            <div class="password-help">

                                Patient accounts are available for public
                                registration. Doctor, health worker and
                                administrator accounts are managed separately.

                            </div>

                        </div>


                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                placeholder="Create a strong password"
                                minlength="8"
                                required
                            >

                            <div class="password-help">

                                Minimum 8 characters with uppercase,
                                lowercase and number.

                            </div>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                placeholder="Re-enter your password"
                                minlength="8"
                                required
                            >

                        </div>


                    </div>


                    <button
                        type="submit"
                        class="register-submit"
                    >

                        Create Secure Patient Account

                    </button>


                </form>


                <div class="login-link">

                    Already have an account?

                    <a href="login.php">
                        Sign in
                    </a>

                </div>


            <?php endif; ?>


            <div class="privacy-note">

                <strong>
                    Privacy & security
                </strong>

                <br>

                Please do not enter emergency information or highly
                sensitive medical information during account registration.
                Swasthya Saarathi is a triage-support system and does not
                replace emergency medical services or professional medical
                assessment.

            </div>


        </section>

    </div>

</main>


<?php

include __DIR__ . '/includes/footer.php';

?>