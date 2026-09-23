<?php
/* ============================================================
   SECURE SESSION
   ============================================================ */

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';


/* ============================================================
   AUTHENTICATION
   ============================================================ */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* --------------------------------------------------------
       CSRF CHECK
    -------------------------------------------------------- */

    if (
        empty($_POST['csrf_token']) ||
        !verify_csrf_token($_POST['csrf_token'])
    ) {
        $error = 'Your session expired. Please try again.';
    } else {

        $email = trim(
            strtolower($_POST['email'] ?? '')
        );

        $password = $_POST['password'] ?? '';


        /* ----------------------------------------------------
           VALIDATE EMAIL
        ---------------------------------------------------- */

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Invalid email or password.';

        } else {

            /* ------------------------------------------------
               FIND USER
            ------------------------------------------------ */

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch();


            /* ------------------------------------------------
               CHECK ACCOUNT LOCK
            ------------------------------------------------ */

            $locked =
                $user &&
                !empty($user['locked_until']) &&
                strtotime($user['locked_until']) > time();


            if ($locked) {

                $error =
                    'Too many unsuccessful attempts. Please try again later.';


            /* ------------------------------------------------
               PASSWORD VERIFIED
            ------------------------------------------------ */

            } elseif (
                $user &&
                password_verify(
                    $password,
                    $user['password_hash']
                )
            ) {

                /*
                 * Prevent session fixation.
                 */

                session_regenerate_id(true);


                $_SESSION['user_id'] =
                    $user['id'];

                $_SESSION['user_name'] =
                    $user['name'];

                $_SESSION['user_role'] =
                    $user['role'];

                $_SESSION['login_time'] =
                    time();


                /* --------------------------------------------
                   RESET LOGIN ATTEMPTS
                -------------------------------------------- */

                $update = $pdo->prepare(
                    "UPDATE users
                     SET failed_attempts = 0,
                         locked_until = NULL,
                         last_login = NOW()
                     WHERE id = ?"
                );

                $update->execute([
                    $user['id']
                ]);


                /* --------------------------------------------
                   REDIRECT
                -------------------------------------------- */

                header('Location: dashboard.php');
                exit;


            /* ------------------------------------------------
               INVALID LOGIN
            ------------------------------------------------ */

            } else {

                if ($user) {

                    $attempts =
                        (int)($user['failed_attempts'] ?? 0) + 1;


                    /* ----------------------------------------
                       LOCK AFTER 5 FAILED ATTEMPTS
                    ---------------------------------------- */

                    if ($attempts >= 5) {

                        $lockUntil =
                            date(
                                'Y-m-d H:i:s',
                                time() + 900
                            );


                        $update = $pdo->prepare(
                            "UPDATE users
                             SET failed_attempts = ?,
                                 locked_until = ?
                             WHERE id = ?"
                        );

                        $update->execute([
                            $attempts,
                            $lockUntil,
                            $user['id']
                        ]);

                    } else {

                        $update = $pdo->prepare(
                            "UPDATE users
                             SET failed_attempts = ?
                             WHERE id = ?"
                        );

                        $update->execute([
                            $attempts,
                            $user['id']
                        ]);
                    }
                }


                /*
                 * Keep authentication errors generic.
                 */

                $error =
                    'Invalid email or password.';
            }
        }
    }
}


$pageTitle =
    'Secure Sign In | Healthcare Triage Support';

?>


<style>

/* ============================================================
   LOGIN PAGE
   ============================================================ */

:root {
    --login-bg: #03050d;
    --login-panel: rgba(12,18,38,.80);
    --login-border: rgba(255,255,255,.09);
    --login-text: #f7f9ff;
    --login-muted: #8995b1;
    --login-purple: #8066ff;
    --login-blue: #4d8dff;
    --login-cyan: #3ddcff;
    --login-green: #38e2a4;
}


/* ------------------------------------------------------------
   PAGE
------------------------------------------------------------ */

.auth-page {
    min-height: calc(100vh - 110px);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 80px 20px;
    overflow: hidden;

    background:
        radial-gradient(
            circle at 15% 25%,
            rgba(128,102,255,.13),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 75%,
            rgba(61,220,255,.09),
            transparent 30%
        );
}


/* ------------------------------------------------------------
   BACKGROUND ORBS
------------------------------------------------------------ */

.auth-page::before {
    content: "";
    position: absolute;
    width: 520px;
    height: 520px;
    left: -230px;
    top: 50px;
    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(128,102,255,.16),
            transparent 68%
        );

    filter: blur(15px);
    pointer-events: none;
}


.auth-page::after {
    content: "";
    position: absolute;
    width: 450px;
    height: 450px;
    right: -190px;
    bottom: -100px;
    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(61,220,255,.11),
            transparent 68%
        );

    filter: blur(15px);
    pointer-events: none;
}


/* ------------------------------------------------------------
   LOGIN LAYOUT
------------------------------------------------------------ */

.auth-wrapper {
    width: min(1050px, 100%);

    display: grid;
    grid-template-columns: 1fr 440px;

    gap: 70px;
    align-items: center;

    position: relative;
    z-index: 2;
}


/* ------------------------------------------------------------
   LEFT INFORMATION
------------------------------------------------------------ */

.auth-intro {
    max-width: 520px;
}


.auth-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    color: var(--login-cyan);

    font-size: 10px;
    font-weight: 850;
    letter-spacing: 2px;
}


.auth-eyebrow::before {
    content: "";

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--login-cyan);

    box-shadow:
        0 0 15px var(--login-cyan);
}


.auth-intro h2 {
    margin: 18px 0;

    color: var(--login-text);

    font-size:
        clamp(38px, 5vw, 62px);

    line-height: 1.03;
    letter-spacing: -3px;
}


.auth-intro h2 span {
    display: block;

    background:
        linear-gradient(
            100deg,
            #ffffff,
            #a996ff,
            #3ddcff
        );

    -webkit-background-clip: text;
    background-clip: text;

    color: transparent;
}


.auth-intro > p {
    color: var(--login-muted);

    font-size: 14px;
    line-height: 1.85;

    max-width: 490px;
}


/* ------------------------------------------------------------
   BENEFITS
------------------------------------------------------------ */

.auth-benefits {
    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

    margin-top: 30px;
}


.auth-benefit {
    display: flex;

    gap: 11px;

    padding: 14px;

    border:
        1px solid
        rgba(255,255,255,.055);

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);
}


.benefit-icon {
    width: 31px;
    height: 31px;

    flex: 0 0 31px;

    display: grid;
    place-items: center;

    border-radius: 8px;

    color: var(--login-cyan);

    background:
        rgba(61,220,255,.07);

    border:
        1px solid
        rgba(61,220,255,.10);

    font-size: 11px;
}


.auth-benefit strong {
    display: block;

    color: #dce3f2;

    font-size: 10px;
}


.auth-benefit span {
    display: block;

    margin-top: 3px;

    color: #6f7c98;

    font-size: 9px;
    line-height: 1.5;
}


/* ------------------------------------------------------------
   LOGIN CARD
------------------------------------------------------------ */

.auth-card {
    position: relative;

    width: 100%;

    padding: 34px;

    border-radius: 25px;

    background:
        linear-gradient(
            145deg,
            rgba(20,29,58,.92),
            rgba(7,11,25,.94)
        );

    border:
        1px solid
        var(--login-border);

    box-shadow:
        0 40px 90px rgba(0,0,0,.55),
        0 0 70px rgba(128,102,255,.08),
        inset 0 1px 0 rgba(255,255,255,.10);

    backdrop-filter: blur(25px);

    transform:
        perspective(1200px)
        rotateY(-3deg);

    transition:
        transform .4s ease;
}


.auth-card:hover {
    transform:
        perspective(1200px)
        rotateY(0deg)
        translateY(-4px);
}


.auth-card::before {
    content: "";

    position: absolute;

    width: 130px;
    height: 130px;

    right: -55px;
    top: -55px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(61,220,255,.14),
            transparent 70%
        );

    pointer-events: none;
}


/* ------------------------------------------------------------
   BRAND
------------------------------------------------------------ */

.auth-heading {
    text-align: center;
}


.brand-mark {
    width: 58px;
    height: 58px;

    margin: 0 auto 17px;

    display: grid;
    place-items: center;

    border-radius: 16px;

    color: white;

    font-size: 14px;
    font-weight: 900;

    background:
        linear-gradient(
            135deg,
            var(--login-purple),
            var(--login-blue)
        );

    border:
        1px solid
        rgba(255,255,255,.18);

    box-shadow:
        0 15px 35px
        rgba(90,80,255,.28);
}


.auth-heading h1 {
    margin: 0;

    color: white;

    font-size: 28px;

    letter-spacing: -1px;
}


.auth-heading p {
    margin: 8px 0 28px;

    color: #77839e;

    font-size: 11px;
    line-height: 1.6;
}


/* ------------------------------------------------------------
   ERROR
------------------------------------------------------------ */

.form-error {
    display: flex;

    gap: 10px;

    margin-bottom: 20px;

    padding: 12px 14px;

    border-radius: 11px;

    background:
        rgba(255,83,112,.07);

    border:
        1px solid
        rgba(255,83,112,.18);

    color: #ff9aaa;

    font-size: 10px;
    line-height: 1.5;
}


.form-error::before {
    content: "!";

    width: 18px;
    height: 18px;

    flex: 0 0 18px;

    display: grid;
    place-items: center;

    border-radius: 50%;

    background:
        rgba(255,83,112,.14);

    font-weight: 900;
}


/* ------------------------------------------------------------
   FORM
------------------------------------------------------------ */

.form-group {
    margin-bottom: 18px;
}


.form-group label {
    display: block;

    margin-bottom: 8px;

    color: #b7c0d4;

    font-size: 10px;
    font-weight: 750;
}


.input-wrapper {
    position: relative;
}


.form-group input {
    width: 100%;
    height: 48px;

    padding: 0 14px;

    border-radius: 10px;

    border:
        1px solid
        rgba(255,255,255,.08);

    outline: none;

    color: white;

    background:
        rgba(0,0,0,.20);

    font-family: inherit;
    font-size: 12px;

    transition:
        border-color .2s,
        box-shadow .2s,
        background .2s;

    box-sizing: border-box;
}


.form-group input:hover {
    background:
        rgba(255,255,255,.025);
}


.form-group input:focus {
    border-color:
        rgba(128,102,255,.65);

    background:
        rgba(128,102,255,.035);

    box-shadow:
        0 0 0 3px
        rgba(128,102,255,.09);
}


.form-group input::placeholder {
    color: #4e5b76;
}


/* ------------------------------------------------------------
   PASSWORD TOGGLE
------------------------------------------------------------ */

.password-toggle {
    position: absolute;

    right: 10px;
    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    color: #74819c;

    cursor: pointer;

    padding: 6px;

    font-size: 10px;
}


.password-toggle:hover {
    color: white;
}


/* ------------------------------------------------------------
   BUTTON
------------------------------------------------------------ */

.btn-full {
    width: 100%;
    min-height: 50px;

    border: 0;

    border-radius: 10px;

    cursor: pointer;

    color: white;

    font-family: inherit;

    font-size: 12px;
    font-weight: 800;

    background:
        linear-gradient(
            135deg,
            var(--login-purple),
            var(--login-blue)
        );

    box-shadow:
        0 14px 30px
        rgba(92,85,255,.25);

    transition:
        transform .2s,
        box-shadow .2s,
        opacity .2s;
}


.btn-full:hover {
    transform:
        translateY(-2px);

    box-shadow:
        0 20px 38px
        rgba(92,85,255,.38);
}


.btn-full:active {
    transform:
        translateY(0);
}


.btn-full.loading {
    opacity: .7;

    pointer-events: none;
}


/* ------------------------------------------------------------
   SECURITY NOTICE
------------------------------------------------------------ */

.auth-notice {
    display: flex;

    gap: 11px;

    margin-top: 22px;

    padding-top: 20px;

    border-top:
        1px solid
        rgba(255,255,255,.06);
}


.auth-notice-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: grid;
    place-items: center;

    border-radius: 8px;

    color: var(--login-green);

    background:
        rgba(56,226,164,.07);

    border:
        1px solid
        rgba(56,226,164,.10);

    font-size: 11px;
}


.auth-notice strong {
    display: block;

    color: #dbe2ef;

    font-size: 10px;
}


.auth-notice p {
    margin: 4px 0 0;

    color: #69758f;

    font-size: 9px;
    line-height: 1.6;
}


/* ------------------------------------------------------------
   SYSTEM STATUS
------------------------------------------------------------ */

.system-status {
    display: flex;

    justify-content: center;

    gap: 7px;

    margin-top: 20px;

    color: #64718d;

    font-size: 8px;
}


.system-status-dot {
    width: 6px;
    height: 6px;

    margin-top: 1px;

    border-radius: 50%;

    background:
        var(--login-green);

    box-shadow:
        0 0 9px
        rgba(56,226,164,.7);
}


/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width: 900px) {

    .auth-wrapper {
        grid-template-columns: 1fr;

        max-width: 520px;
    }


    .auth-intro {
        text-align: center;

        max-width: none;
    }


    .auth-intro > p {
        margin-left: auto;
        margin-right: auto;
    }


    .auth-benefits {
        text-align: left;
    }


    .auth-card {
        transform: none;
    }


    .auth-card:hover {
        transform:
            translateY(-3px);
    }
}


@media (max-width: 560px) {

    .auth-page {
        padding: 45px 14px;
    }


    .auth-wrapper {
        gap: 35px;
    }


    .auth-intro h2 {
        font-size: 40px;

        letter-spacing: -2px;
    }


    .auth-benefits {
        grid-template-columns: 1fr;
    }


    .auth-card {
        padding: 25px 20px;

        border-radius: 20px;
    }
}


@media (prefers-reduced-motion: reduce) {

    .auth-card,
    .btn-full {
        transition: none;
    }
}

</style>


<main class="auth-page">

    <div class="auth-wrapper">


        <!-- ==================================================
             LEFT INFORMATION
        ================================================== -->

        <section class="auth-intro">

            <div class="auth-eyebrow">
                SECURE HEALTHCARE ACCESS
            </div>


            <h2>
                Your healthcare
                <span>
                    workspace.
                </span>
            </h2>


            <p>
                Sign in to securely access the healthcare triage
                support application. Capture patient information,
                organize supporting reports and prepare structured
                information for qualified human review.
            </p>


            <div class="auth-benefits">


                <div class="auth-benefit">

                    <div class="benefit-icon">
                        ✓
                    </div>

                    <div>
                        <strong>
                            Protected access
                        </strong>

                        <span>
                            Authenticated application sessions.
                        </span>
                    </div>

                </div>


                <div class="auth-benefit">

                    <div class="benefit-icon">
                        ◇
                    </div>

                    <div>
                        <strong>
                            Multimodal input
                        </strong>

                        <span>
                            Text, voice and report workflows.
                        </span>
                    </div>

                </div>


                <div class="auth-benefit">

                    <div class="benefit-icon">
                        ◎
                    </div>

                    <div>
                        <strong>
                            Human oversight
                        </strong>

                        <span>
                            Advisory information is reviewed.
                        </span>
                    </div>

                </div>


                <div class="auth-benefit">

                    <div class="benefit-icon">
                        +
                    </div>

                    <div>
                        <strong>
                            Controlled access
                        </strong>

                        <span>
                            Access can be managed by user role.
                        </span>
                    </div>

                </div>


            </div>

        </section>


        <!-- ==================================================
             LOGIN CARD
        ================================================== -->

        <section class="auth-card">


            <div class="auth-heading">

                <div class="brand-mark">
                    HT
                </div>


                <h1>
                    Sign in
                </h1>


                <p>
                    Access your secure application workspace.
                </p>

            </div>


            <?php if ($error): ?>

                <div
                    class="form-error"
                    role="alert"
                    aria-live="polite"
                >
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="on"
                id="loginForm"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrf_token(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="username"
                        placeholder="name@example.com"
                        maxlength="254"
                        required
                        autofocus
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >
                            SHOW
                        </button>

                    </div>

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="btn-full"
                    id="loginButton"
                >

                    <span id="buttonText">
                        Sign in securely →
                    </span>

                </button>


            </form>


            <!-- SECURITY -->

            <div class="auth-notice">

                <div class="auth-notice-icon">
                    ✓
                </div>


                <div>

                    <strong>
                        Security notice
                    </strong>

                    <p>
                        Never share your password. Authorized users
                        only. If you believe your account has been
                        compromised, contact your system administrator.
                    </p>

                </div>

            </div>


            <div class="system-status">

                <span class="system-status-dot"></span>

                Secure authentication service

            </div>


        </section>

    </div>

</main>


<script>

/* ============================================================
   PASSWORD SHOW / HIDE
   ============================================================ */

const password =
    document.getElementById('password');

const passwordToggle =
    document.getElementById('passwordToggle');


if (password && passwordToggle) {

    passwordToggle.addEventListener(
        'click',
        function () {

            const isPassword =
                password.type === 'password';


            password.type =
                isPassword
                    ? 'text'
                    : 'password';


            passwordToggle.textContent =
                isPassword
                    ? 'HIDE'
                    : 'SHOW';


            passwordToggle.setAttribute(
                'aria-label',
                isPassword
                    ? 'Hide password'
                    : 'Show password'
            );

        }
    );

}


/* ============================================================
   LOGIN LOADING STATE
   ============================================================ */

const loginForm =
    document.getElementById('loginForm');

const loginButton =
    document.getElementById('loginButton');

const buttonText =
    document.getElementById('buttonText');


if (
    loginForm &&
    loginButton &&
    buttonText
) {

    loginForm.addEventListener(
        'submit',
        function () {

            if (!loginForm.checkValidity()) {
                return;
            }


            loginButton.classList.add(
                'loading'
            );


            buttonText.textContent =
                'Authenticating…';

        }
    );

}

</script>


<?php

include 'includes/footer.php';

?>