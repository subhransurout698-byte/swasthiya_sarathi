<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Swasthya Saarathi - Header
|--------------------------------------------------------------------------
*/

$pageTitle = $pageTitle ?? 'Swasthya Saarathi';

/*
 * Start session only if it has not already been started.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$loggedIn = !empty($_SESSION['user_id']);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$folder = basename($scriptDir);
$appBase = in_array($folder, ['admin', 'doctor', 'health_worker', 'user'], true)
    ? dirname($scriptDir)
    : $scriptDir;
$appBase = rtrim($appBase, '/');
if ($appBase === '') {
    $appBase = '';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Swasthya Saarathi - Digital Healthcare Triage Support Portal"
    >

    <meta
        name="keywords"
        content="Swasthya Saarathi, healthcare, digital healthcare, triage support, OCR, medical reports, patient support"
    >

    <meta
        name="author"
        content="Swasthya Saarathi"
    >

    <meta
        name="theme-color"
        content="#075985"
    >

    <!-- Open Graph -->

    <meta
        property="og:title"
        content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>"
    >

    <meta
        property="og:description"
        content="Digital healthcare triage support with text, voice, OCR and human review."
    >

    <meta
        property="og:type"
        content="website"
    >

    <meta
        property="og:image"
        content="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/images/swasthya-saarathi-logo.png"
    >

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
    </title>


    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css"
    >

</head>


<body>


<!-- =========================================================
     PREMIUM HEADER
========================================================= -->

<header class="site-header premium-header">

    <div class="container nav-wrap">


        <!-- =================================================
             BRAND / LOGO
        ================================================== -->

        <a
            href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php"
            class="brand"
            aria-label="Swasthya Saarathi Home"
        >

            <span class="brand-logo-wrap">

                <img
                    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/images/swasthya-saarathi-logo.png"
                    alt="Swasthya Saarathi"
                    class="brand-logo-image"
                    width="72"
                    height="72"
                >

            </span>


            <span class="brand-copy">

                <strong>
                    SWASTHYA
                    <span>SAARATHI</span>
                </strong>

                <small>
                    Digital Healthcare Triage Support
                </small>

            </span>

        </a>


        <!-- =================================================
             MOBILE MENU BUTTON
        ================================================== -->

        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="mainNavigation"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>


        <!-- =================================================
             NAVIGATION
        ================================================== -->

        <nav
            class="main-nav"
            id="mainNavigation"
            aria-label="Main navigation"
        >

            <a
                href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php"
                class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
            >
                Home
            </a>


            <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php#services">
                Services
            </a>


            <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php#about">
                About
            </a>


            <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php#help">
                Help
            </a>


            <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php#contact">
                Contact
            </a>


            <?php if ($loggedIn): ?>


                <!-- Dashboard -->

                <a
                    href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/dashboard.php"
                    class="nav-login"
                >

                    <span class="nav-icon">
                        ◉
                    </span>

                    Dashboard

                </a>


                <!-- Sign Out -->

                <a
                    href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/logout.php"
                    class="nav-register"
                >
                    Sign out
                </a>


            <?php else: ?>


                <!-- Login -->

                <a
                    href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/login.php"
                    class="nav-login"
                >

                    <span class="nav-icon">
                        ⇥
                    </span>

                    Login

                </a>


                <!-- Register -->

                <a
                    href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/register.php"
                    class="nav-register"
                >
                    Register
                </a>


            <?php endif; ?>

        </nav>

    </div>

</header>


<!-- =========================================================
     PREMIUM HEADER STYLES
========================================================= -->

<style>

    /* -------------------------------------------------------
       HEADER
    ------------------------------------------------------- */

    .premium-header {
        position: sticky;
        top: 0;
        z-index: 9999;

        background:
            rgba(255, 255, 255, 0.88);

        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);

        border-bottom:
            1px solid rgba(15, 118, 110, 0.10);

        box-shadow:
            0 8px 35px rgba(15, 76, 92, 0.07);

        transition:
            background .25s ease,
            box-shadow .25s ease;
    }


    .premium-header::before {

        content: "";

        position: absolute;

        left: 0;
        right: 0;
        top: 0;

        height: 3px;

        background:
            linear-gradient(
                90deg,
                #075985,
                #0ea5a0,
                #22c55e,
                #075985
            );

    }


    /* -------------------------------------------------------
       NAV WRAP
    ------------------------------------------------------- */

    .premium-header .nav-wrap {

        min-height: 82px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 28px;

    }


    /* -------------------------------------------------------
       BRAND
    ------------------------------------------------------- */

    .premium-header .brand {

        display: flex;
        align-items: center;

        gap: 13px;

        text-decoration: none;

        min-width: 250px;

        transition:
            transform .25s ease;

    }


    .premium-header .brand:hover {

        transform:
            translateY(-1px);

    }


    /* -------------------------------------------------------
       LOGO
    ------------------------------------------------------- */

    .brand-logo-wrap {

        width: 62px;
        height: 62px;

        flex: 0 0 62px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 18px;

        background:
            linear-gradient(
                145deg,
                #ffffff,
                #eefbfd
            );

        box-shadow:
            0 10px 25px rgba(7, 89, 133, .12),
            inset 0 0 0 1px rgba(14, 165, 160, .12);

        overflow: hidden;

    }


    .brand-logo-image {

        width: 56px;
        height: 56px;

        object-fit: contain;

        display: block;

        mix-blend-mode: multiply;

    }


    /* -------------------------------------------------------
       BRAND TEXT
    ------------------------------------------------------- */

    .brand-copy {

        display: flex;
        flex-direction: column;

        line-height: 1.1;

    }


    .brand-copy strong {

        font-family:
            "Plus Jakarta Sans",
            Inter,
            sans-serif;

        font-size: 17px;

        font-weight: 800;

        letter-spacing: .6px;

        color: #075985;

    }


    .brand-copy strong span {

        color: #079b88;

    }


    .brand-copy small {

        margin-top: 6px;

        font-family:
            Inter,
            sans-serif;

        font-size: 9px;

        font-weight: 600;

        letter-spacing: .35px;

        color: #64748b;

        white-space: nowrap;

    }


    /* -------------------------------------------------------
       NAVIGATION
    ------------------------------------------------------- */

    .premium-header .main-nav {

        display: flex;
        align-items: center;

        gap: 5px;

    }


    .premium-header .main-nav > a {

        position: relative;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 42px;

        padding:
            0 14px;

        border-radius: 12px;

        color: #334155;

        text-decoration: none;

        font-size: 13px;
        font-weight: 600;

        transition:
            color .2s ease,
            background .2s ease,
            transform .2s ease;

    }


    .premium-header .main-nav > a:hover {

        color: #075985;

        background:
            rgba(14, 165, 160, .08);

        transform:
            translateY(-1px);

    }


    .premium-header .main-nav > a.active {

        color: #075985;

        background:
            rgba(14, 165, 160, .09);

    }


    /* -------------------------------------------------------
       ACTIVE LINE
    ------------------------------------------------------- */

    .premium-header .main-nav > a.active::after {

        content: "";

        position: absolute;

        bottom: 5px;

        left: 50%;

        width: 18px;
        height: 3px;

        border-radius: 10px;

        transform:
            translateX(-50%);

        background:
            linear-gradient(
                90deg,
                #075985,
                #0ea5a0
            );

    }


    /* -------------------------------------------------------
       LOGIN
    ------------------------------------------------------- */

    .premium-header .main-nav .nav-login {

        margin-left: 8px;

        border:
            1px solid rgba(7, 89, 133, .18);

        color: #075985;

        background:
            rgba(255,255,255,.8);

    }


    .premium-header .main-nav .nav-login:hover {

        border-color:
            rgba(7, 89, 133, .35);

        background:
            #effaff;

    }


    .nav-icon {

        margin-right: 7px;

        font-size: 14px;

    }


    /* -------------------------------------------------------
       REGISTER BUTTON
    ------------------------------------------------------- */

    .premium-header .main-nav .nav-register {

        padding-left: 19px;
        padding-right: 19px;

        color: #ffffff;

        background:
            linear-gradient(
                135deg,
                #075985,
                #079b88
            );

        box-shadow:
            0 8px 20px rgba(7, 89, 133, .20);

    }


    .premium-header .main-nav .nav-register:hover {

        color: #ffffff;

        background:
            linear-gradient(
                135deg,
                #064e78,
                #078777
            );

        box-shadow:
            0 12px 26px rgba(7, 89, 133, .28);

        transform:
            translateY(-2px);

    }


    /* -------------------------------------------------------
       MOBILE MENU BUTTON
    ------------------------------------------------------- */

    .mobile-menu-button {

        display: none;

        width: 45px;
        height: 45px;

        border: 0;

        border-radius: 13px;

        background:
            #eff9fb;

        cursor: pointer;

        padding: 10px;

    }


    .mobile-menu-button span {

        display: block;

        width: 100%;
        height: 2px;

        margin: 5px 0;

        border-radius: 10px;

        background: #075985;

        transition:
            transform .25s ease,
            opacity .25s ease;

    }


    /* -------------------------------------------------------
       MOBILE
    ------------------------------------------------------- */

    @media (max-width: 1050px) {

        .premium-header .main-nav {

            gap: 2px;

        }

        .premium-header .main-nav > a {

            padding-left: 10px;
            padding-right: 10px;

            font-size: 12px;

        }

    }


    @media (max-width: 850px) {

        .premium-header .nav-wrap {

            min-height: 72px;

        }


        .mobile-menu-button {

            display: block;

        }


        .premium-header .main-nav {

            display: none;

            position: absolute;

            left: 15px;
            right: 15px;
            top: calc(100% + 8px);

            padding: 12px;

            flex-direction: column;
            align-items: stretch;

            gap: 5px;

            border-radius: 20px;

            background:
                rgba(255,255,255,.97);

            backdrop-filter:
                blur(20px);

            box-shadow:
                0 20px 50px rgba(15, 76, 92, .15);

            border:
                1px solid rgba(7, 89, 133, .10);

        }


        .premium-header .main-nav.mobile-open {

            display: flex;

        }


        .premium-header .main-nav > a {

            justify-content: flex-start;

            width: 100%;

            padding:
                13px 15px;

        }


        .premium-header .main-nav .nav-login {

            margin-left: 0;

            margin-top: 6px;

        }


        .premium-header .main-nav .nav-register {

            justify-content: center;

            margin-top: 3px;

        }


        .brand-copy small {

            display: none;

        }


        .brand-logo-wrap {

            width: 54px;
            height: 54px;

            flex-basis: 54px;

        }


        .brand-logo-image {

            width: 49px;
            height: 49px;

        }


        .brand-copy strong {

            font-size: 15px;

        }

    }


    @media (max-width: 480px) {

        .premium-header .nav-wrap {

            padding-left: 12px;
            padding-right: 12px;

        }


        .brand-logo-wrap {

            width: 48px;
            height: 48px;

            flex-basis: 48px;

            border-radius: 14px;

        }


        .brand-logo-image {

            width: 44px;
            height: 44px;

        }


        .brand-copy strong {

            font-size: 13px;

            letter-spacing: .3px;

        }

    }

</style>


<!-- =========================================================
     MOBILE MENU SCRIPT
========================================================= -->

<script>

(function () {

    const menuButton =
        document.getElementById('mobileMenuButton');

    const navigation =
        document.getElementById('mainNavigation');


    if (!menuButton || !navigation) {
        return;
    }


    menuButton.addEventListener('click', function () {

        const isOpen =
            navigation.classList.toggle('mobile-open');

        menuButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

    });


    /*
     * Close mobile menu after clicking a navigation link.
     */

    navigation
        .querySelectorAll('a')
        .forEach(function (link) {

            link.addEventListener('click', function () {

                navigation.classList.remove(
                    'mobile-open'
                );

                menuButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            });

        });

})();

</script>