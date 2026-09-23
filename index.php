<?php

declare(strict_types=1);

session_start();

$pageTitle = 'Swasthya Saarathi | Digital Healthcare Triage Support';

require_once __DIR__ . '/includes/cache.php';

public_cache(300);
security_headers();


/*
|--------------------------------------------------------------------------
| LIVE PORTAL STATISTICS
|--------------------------------------------------------------------------
*/

$registeredPatients = 0;
$totalTriageCases   = 0;
$reviewedCases      = 0;

try {

    require_once __DIR__ . '/config/database.php';


    /*
    |--------------------------------------------------------------------------
    | Registered Patients
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM patients"
    );

    $registeredPatients = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Total Triage Cases
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM triage_reports"
    );

    $totalTriageCases = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Human Reviewed Cases
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM triage_reports
         WHERE status IN ('reviewed', 'completed')"
    );

    $reviewedCases = (int) $stmt->fetchColumn();


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Never break homepage because of statistics
    |--------------------------------------------------------------------------
    */

    $registeredPatients = 0;
    $totalTriageCases   = 0;
    $reviewedCases      = 0;
}




?>

<style>

/* ============================================================
   SWASTHYA SAARATHI PREMIUM HOMEPAGE
============================================================ */

:root {

    --ss-navy: #06172d;
    --ss-navy-2: #0a2340;

    --ss-blue: #087ec4;
    --ss-blue-dark: #075b91;

    --ss-cyan: #18c6d8;
    --ss-teal: #13b89f;

    --ss-white: #ffffff;

    --ss-text: #17324d;
    --ss-muted: #6c7e91;

    --ss-border: rgba(18, 75, 110, .12);

    --ss-shadow:
        0 30px 80px
        rgba(3, 31, 58, .16);

    --ss-radius: 28px;
}


/* ============================================================
   RESET
============================================================ */

.ss-home {

    overflow: hidden;

    background:
        linear-gradient(
            180deg,
            #f7fbff 0%,
            #ffffff 42%,
            #f4faff 100%
        );

    color: var(--ss-text);
}


.ss-home *,
.ss-home *::before,
.ss-home *::after {

    box-sizing: border-box;
}


/* ============================================================
   CONTAINER
============================================================ */

.ss-container {

    width:
        min(
            1240px,
            calc(100% - 40px)
        );

    margin: 0 auto;
}


/* ============================================================
   TOP STRIP
============================================================ */

.ss-top-strip {

    background:
        #041225;

    color:
        rgba(255,255,255,.82);

    font-size: 12px;

    letter-spacing: .04em;
}


.ss-top-strip-inner {

    min-height: 38px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.ss-top-strip-right {

    color: #7ee7ef;

    font-weight: 700;
}


/* ============================================================
   HERO
============================================================ */

.ss-hero {

    position: relative;

    min-height: 720px;

    display: flex;

    align-items: center;

    overflow: hidden;

    background:

        radial-gradient(
            circle at 82% 20%,
            rgba(28, 207, 226, .22),
            transparent 28%
        ),

        radial-gradient(
            circle at 15% 80%,
            rgba(20, 184, 159, .15),
            transparent 25%
        ),

        linear-gradient(
            135deg,
            #041426 0%,
            #082846 48%,
            #075a78 100%
        );

    color: white;
}


/* ============================================================
   HERO GRID
============================================================ */

.ss-hero-grid {

    position: relative;

    z-index: 2;

    display: grid;

    grid-template-columns:
        minmax(0, 1.02fr)
        minmax(450px, .98fr);

    align-items: center;

    gap: 60px;

    padding:
        80px 0
        105px;
}


/* ============================================================
   GRID BACKGROUND
============================================================ */

.ss-hero::before {

    content: "";

    position: absolute;

    inset: 0;

    background-image:

        linear-gradient(
            rgba(255,255,255,.035) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,.035) 1px,
            transparent 1px
        );

    background-size: 55px 55px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent
        );

    pointer-events: none;
}


/* ============================================================
   HERO GLOW
============================================================ */

.ss-hero-glow {

    position: absolute;

    width: 650px;
    height: 650px;

    right: -200px;
    top: -180px;

    border-radius: 50%;

    background:

        radial-gradient(
            circle,
            rgba(26,202,220,.20),
            transparent 65%
        );

    filter: blur(10px);

    pointer-events: none;
}


/* ============================================================
   HERO BRAND
============================================================ */

.ss-hero-brand {

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 28px;
}


.ss-hero-brand img {

    width: 88px;
    height: 88px;

    object-fit: contain;

    filter:
        drop-shadow(
            0 12px 30px
            rgba(0,0,0,.28)
        );

    animation:
        ssLogoFloat 5s
        ease-in-out
        infinite;
}


.ss-hero-brand-text {

    display: flex;

    flex-direction: column;
}


.ss-hero-brand-text strong {

    font-family:
        'Plus Jakarta Sans',
        sans-serif;

    font-size: 24px;

    font-weight: 800;

    letter-spacing: -.035em;

    color: white;
}


.ss-hero-brand-text span {

    margin-top: 5px;

    color:
        rgba(255,255,255,.62);

    font-size: 12px;

    letter-spacing: .07em;
}


@keyframes ssLogoFloat {

    0%,100% {

        transform:
            translateY(0);
    }

    50% {

        transform:
            translateY(-7px);
    }
}


/* ============================================================
   EYEBROW
============================================================ */

.ss-eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 9px;

    padding:
        9px 14px;

    border:
        1px solid
        rgba(125,232,239,.24);

    border-radius: 999px;

    background:
        rgba(255,255,255,.06);

    color:
        #8feef4;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: .12em;

    text-transform: uppercase;
}


.ss-eyebrow::before {

    content: "";

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #4fe4d3;

    box-shadow:
        0 0 18px
        rgba(79,228,211,.9);
}


/* ============================================================
   HERO HEADING
============================================================ */

.ss-hero h1 {

    margin:
        24px 0
        20px;

    max-width: 760px;

    font-size:
        clamp(
            44px,
            5.8vw,
            76px
        );

    line-height: .98;

    letter-spacing: -.05em;
}


.ss-hero h1 span {

    display: block;

    background:

        linear-gradient(
            90deg,
            #ffffff,
            #7de9ef,
            #69d8ff
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;
}


/* ============================================================
   HERO DESCRIPTION
============================================================ */

.ss-hero-description {

    max-width: 650px;

    margin:
        0 0 28px;

    color:
        rgba(255,255,255,.76);

    font-size: 17px;

    line-height: 1.8;
}


/* ============================================================
   HERO ACTIONS
============================================================ */

.ss-hero-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 13px;

    margin-top: 30px;
}


.ss-primary-btn,
.ss-secondary-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    min-height: 54px;

    padding:
        0 24px;

    border-radius: 15px;

    text-decoration: none;

    font-size: 14px;

    font-weight: 800;

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        background .25s ease;
}


.ss-primary-btn {

    color: white;

    background:

        linear-gradient(
            135deg,
            #19c7d8,
            #078ac6
        );

    box-shadow:

        0 15px 35px
        rgba(7,153,201,.30);
}


.ss-primary-btn:hover {

    transform:
        translateY(-3px);

    box-shadow:

        0 20px 45px
        rgba(7,153,201,.42);
}


.ss-secondary-btn {

    color: white;

    border:
        1px solid
        rgba(255,255,255,.20);

    background:
        rgba(255,255,255,.07);

    backdrop-filter:
        blur(15px);
}


.ss-secondary-btn:hover {

    transform:
        translateY(-3px);

    background:
        rgba(255,255,255,.13);
}


/* ============================================================
   MINI FEATURES
============================================================ */

.ss-mini-features {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 25px;
}


.ss-mini-feature {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        8px 12px;

    border-radius: 999px;

    border:
        1px solid
        rgba(255,255,255,.10);

    background:
        rgba(255,255,255,.045);

    color:
        rgba(255,255,255,.68);

    font-size: 12px;
}


.ss-mini-feature b {

    color:
        #65e6d6;
}


/* ============================================================
   HERO VISUAL
============================================================ */

.ss-visual {

    position: relative;

    min-height: 540px;

    display: grid;

    place-items: center;

    perspective: 1200px;
}


/* ============================================================
   ORB
============================================================ */

.ss-orb {

    position: absolute;

    width: 430px;
    height: 430px;

    border-radius: 50%;

    background:

        radial-gradient(
            circle at 32% 25%,
            rgba(255,255,255,.72),
            rgba(116,230,239,.28) 20%,
            rgba(8,120,166,.18) 48%,
            rgba(0,0,0,.04) 70%
        );

    border:
        1px solid
        rgba(255,255,255,.22);

    box-shadow:

        inset -40px -50px 100px
        rgba(0,50,90,.18),

        inset 25px 20px 60px
        rgba(255,255,255,.20),

        0 50px 120px
        rgba(0,0,0,.24);

    transform:
        rotateX(8deg)
        rotateY(-15deg);

    animation:
        ssFloat 7s
        ease-in-out
        infinite;
}


.ss-orb::before,
.ss-orb::after {

    content: "";

    position: absolute;

    inset: 35px;

    border-radius: 50%;

    border:
        1px solid
        rgba(127,237,244,.28);

    transform:
        rotateX(62deg)
        rotateZ(18deg);
}


.ss-orb::after {

    inset: 70px;

    transform:
        rotateY(68deg)
        rotateZ(-18deg);
}


@keyframes ssFloat {

    0%,100% {

        transform:
            translateY(0)
            rotateX(8deg)
            rotateY(-15deg);
    }

    50% {

        transform:
            translateY(-16px)
            rotateX(10deg)
            rotateY(-8deg);
    }
}


/* ============================================================
   LOGO CORE
============================================================ */

.ss-logo-core {

    position: relative;

    z-index: 5;

    width: 220px;
    height: 220px;

    display: grid;

    place-items: center;

    border-radius: 55px;

    background:

        linear-gradient(
            145deg,
            rgba(255,255,255,.95),
            rgba(215,248,251,.72)
        );

    border:
        1px solid
        rgba(255,255,255,.78);

    box-shadow:

        20px 25px 70px
        rgba(0,20,40,.25),

        inset 0 1px 0
        rgba(255,255,255,.95);

    backdrop-filter:
        blur(18px);

    transform:
        rotateX(10deg)
        rotateY(-10deg);

    animation:
        ssCoreFloat 6s
        ease-in-out
        infinite;
}


.ss-logo-core img {

    width: 175px;
    height: 175px;

    object-fit: contain;

    filter:
        drop-shadow(
            0 18px 30px
            rgba(0,90,130,.22)
        );
}


@keyframes ssCoreFloat {

    0%,100% {

        transform:
            translateY(0)
            rotateX(10deg)
            rotateY(-10deg);
    }

    50% {

        transform:
            translateY(-12px)
            rotateX(12deg)
            rotateY(-6deg);
    }
}


/* ============================================================
   FLOATING CARDS
============================================================ */

.ss-float-card {

    position: absolute;

    z-index: 8;

    padding:
        16px 18px;

    min-width: 175px;

    border:
        1px solid
        rgba(255,255,255,.35);

    border-radius: 18px;

    background:
        rgba(255,255,255,.12);

    backdrop-filter:
        blur(22px);

    box-shadow:
        0 25px 55px
        rgba(0,0,0,.20);

    color: white;
}


.ss-float-card small {

    display: block;

    margin-bottom: 5px;

    color:
        rgba(255,255,255,.58);

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .08em;
}


.ss-float-card strong {

    font-size: 23px;
}


.ss-float-card.one {

    top: 45px;
    right: 5px;

    animation:
        ssFloatSmall 6s
        ease-in-out
        infinite;
}


.ss-float-card.two {

    left: 0;
    bottom: 85px;

    animation:
        ssFloatSmall 7s
        ease-in-out
        infinite reverse;
}


.ss-float-card.three {

    right: 45px;
    bottom: 20px;

    animation:
        ssFloatSmall 5.5s
        ease-in-out
        infinite;
}


@keyframes ssFloatSmall {

    0%,100% {

        transform:
            translateY(0);
    }

    50% {

        transform:
            translateY(-10px);
    }
}


/* ============================================================
   STATISTICS
============================================================ */

.ss-stats-wrap {

    position: relative;

    z-index: 10;

    margin-top: -65px;
}


.ss-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    padding: 16px;

    border:
        1px solid
        rgba(255,255,255,.55);

    border-radius: 28px;

    background:
        rgba(255,255,255,.88);

    backdrop-filter:
        blur(24px);

    box-shadow:
        0 30px 80px
        rgba(4,38,67,.18);
}


.ss-stat {

    position: relative;

    padding:
        27px 24px;

    border-radius: 21px;

    background:

        linear-gradient(
            145deg,
            #ffffff,
            #f3faff
        );

    border:
        1px solid
        rgba(11,83,125,.07);
}


.ss-stat-icon {

    width: 43px;
    height: 43px;

    display: grid;

    place-items: center;

    margin-bottom: 15px;

    border-radius: 13px;

    background:
        #e7f8fc;

    font-size: 20px;
}


.ss-stat strong {

    display: block;

    font-size: 34px;

    line-height: 1;

    color: #082b48;
}


.ss-stat span {

    display: block;

    margin-top: 8px;

    color:
        var(--ss-muted);

    font-size: 13px;

    font-weight: 600;
}


/* ============================================================
   GENERAL SECTION
============================================================ */

.ss-section {

    padding:
        105px 0;
}


.ss-section-heading {

    max-width: 760px;

    margin-bottom: 42px;
}


.ss-section-heading .ss-eyebrow {

    color: #087eaa;

    background:
        #e9f9fc;

    border-color:
        #d1eef3;
}


.ss-section-heading h2 {

    margin:
        15px 0;

    color:
        #092943;

    font-size:
        clamp(
            32px,
            4vw,
            52px
        );

    line-height: 1.05;

    letter-spacing:
        -.035em;
}


.ss-section-heading p {

    color:
        var(--ss-muted);

    font-size: 16px;

    line-height: 1.8;
}


/* ============================================================
   SERVICES
============================================================ */

.ss-services {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;
}


.ss-service {

    position: relative;

    min-height: 285px;

    padding: 27px;

    display: flex;

    flex-direction: column;

    border-radius: 25px;

    border:
        1px solid
        var(--ss-border);

    background: white;

    text-decoration: none;

    color: inherit;

    overflow: hidden;

    box-shadow:
        0 15px 40px
        rgba(12,55,85,.055);

    transition:
        transform .3s,
        box-shadow .3s,
        border-color .3s;
}


.ss-service:hover {

    transform:
        translateY(-9px);

    box-shadow:
        0 28px 60px
        rgba(10,65,100,.13);

    border-color:
        rgba(8,142,194,.22);
}


.ss-service::after {

    content: "";

    position: absolute;

    width: 150px;
    height: 150px;

    right: -70px;
    bottom: -70px;

    border-radius: 50%;

    background:
        rgba(21,178,197,.08);
}


.ss-service-icon {

    width: 58px;
    height: 58px;

    display: grid;

    place-items: center;

    margin-bottom: 25px;

    border-radius: 18px;

    background:

        linear-gradient(
            145deg,
            #e8faff,
            #d8f1fa
        );

    font-size: 25px;
}


.ss-service h3 {

    margin:
        0 0 10px;

    color:
        #092c47;

    font-size: 20px;
}


.ss-service p {

    margin: 0;

    color:
        var(--ss-muted);

    font-size: 14px;

    line-height: 1.7;
}


.ss-service-arrow {

    margin-top: auto;

    padding-top: 22px;

    color:
        #0889b9;

    font-size: 20px;

    font-weight: 800;
}


/* ============================================================
   WORKFLOW
============================================================ */

.ss-workflow-section {

    position: relative;

    background:

        linear-gradient(
            135deg,
            #061a31,
            #092e4b
        );

    color: white;

    overflow: hidden;
}


.ss-workflow-section::before {

    content: "";

    position: absolute;

    width: 500px;
    height: 500px;

    right: -180px;
    top: -220px;

    border-radius: 50%;

    background:

        radial-gradient(
            circle,
            rgba(21,201,215,.18),
            transparent 68%
        );
}


.ss-workflow-grid {

    display: grid;

    grid-template-columns:
        .8fr 1.2fr;

    gap: 70px;

    align-items: center;
}


.ss-workflow-copy h2 {

    margin:
        16px 0;

    font-size:
        clamp(
            34px,
            4vw,
            52px
        );

    line-height: 1.04;
}


.ss-workflow-copy p {

    color:
        rgba(255,255,255,.68);

    line-height: 1.8;
}


.ss-steps {

    display: grid;

    gap: 13px;
}


.ss-step {

    display: grid;

    grid-template-columns:
        55px 1fr;

    gap: 18px;

    padding: 21px;

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    backdrop-filter:
        blur(15px);
}


.ss-step-number {

    width: 48px;
    height: 48px;

    display: grid;

    place-items: center;

    border-radius: 15px;

    background:

        linear-gradient(
            145deg,
            #16c6c1,
            #0784c0
        );

    font-weight: 900;
}


.ss-step strong {

    display: block;

    margin-bottom: 5px;

    font-size: 16px;
}


.ss-step span {

    color:
        rgba(255,255,255,.58);

    font-size: 13px;

    line-height: 1.6;
}


/* ============================================================
   SUPPORT
============================================================ */

.ss-support {

    padding:
        90px 0;
}


.ss-support-card {

    position: relative;

    display: grid;

    grid-template-columns:
        1fr auto;

    align-items: center;

    gap: 30px;

    padding: 50px;

    border-radius: 32px;

    overflow: hidden;

    background:

        linear-gradient(
            135deg,
            #e7fbff,
            #f4fffc
        );

    border:
        1px solid
        #d6f0f3;

    box-shadow:
        0 25px 65px
        rgba(12,91,113,.09);
}


.ss-support-card h2 {

    margin:
        12px 0;

    color:
        #072d49;

    font-size:
        clamp(
            30px,
            4vw,
            46px
        );

    line-height: 1.05;
}


.ss-support-card p {

    max-width: 650px;

    margin: 0;

    color:
        #647b8d;

    line-height: 1.8;
}


.ss-support-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    min-height: 54px;

    padding:
        0 25px;

    border-radius: 15px;

    background:
        #087ec4;

    color: white;

    text-decoration: none;

    font-weight: 800;

    box-shadow:
        0 14px 30px
        rgba(8,126,196,.20);

    transition:
        transform .2s,
        box-shadow .2s;
}


.ss-support-btn:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 18px 38px
        rgba(8,126,196,.30);
}


/* ============================================================
   SAFETY
============================================================ */

.ss-safety {

    padding-bottom:
        100px;
}


.ss-safety-card {

    display: flex;

    align-items: flex-start;

    gap: 20px;

    padding:
        25px 28px;

    border:
        1px solid
        #d8e8f0;

    border-radius: 20px;

    background:
        #f8fcfe;
}


.ss-safety-icon {

    width: 44px;
    height: 44px;

    flex:
        0 0 44px;

    display: grid;

    place-items: center;

    border-radius: 13px;

    background:
        #e6f7fc;

    color:
        #087eac;

    font-weight: 900;
}


.ss-safety-card strong {

    display: block;

    margin-bottom: 5px;

    color:
        #163b56;
}


.ss-safety-card p {

    margin: 0;

    color:
        #65798a;

    line-height: 1.7;

    font-size: 14px;
}


/* ============================================================
   COUNTERS
============================================================ */

.ss-counter {

    font-variant-numeric:
        tabular-nums;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1050px) {

    .ss-hero-grid {

        grid-template-columns: 1fr;

    }


    .ss-visual {

        min-height: 470px;

    }


    .ss-services {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .ss-workflow-grid {

        grid-template-columns:
            1fr;

    }


    .ss-stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 700px) {

    .ss-container {

        width:
            min(
                100% - 28px,
                1240px
            );

    }


    .ss-top-strip-right {

        display: none;

    }


    .ss-hero {

        min-height: auto;

    }


    .ss-hero-grid {

        padding:
            60px 0
            100px;

        gap: 25px;

    }


    .ss-hero-brand img {

        width: 70px;
        height: 70px;

    }


    .ss-hero-brand-text strong {

        font-size: 19px;

    }


    .ss-hero-brand-text span {

        font-size: 10px;

    }


    .ss-hero h1 {

        font-size: 45px;

    }


    .ss-hero-description {

        font-size: 15px;

    }


    .ss-hero-actions {

        flex-direction: column;

    }


    .ss-primary-btn,
    .ss-secondary-btn {

        width: 100%;

    }


    .ss-mini-features {

        gap: 7px;

    }


    .ss-mini-feature {

        font-size: 11px;

    }


    .ss-visual {

        min-height: 390px;

        transform:
            scale(.82);

        transform-origin:
            center;

        margin-top: -25px;

        margin-bottom: -30px;

    }


    .ss-logo-core {

        width: 180px;
        height: 180px;

        border-radius: 45px;

    }


    .ss-logo-core img {

        width: 145px;
        height: 145px;

    }


    .ss-orb {

        width: 390px;
        height: 390px;

    }


    .ss-stats {

        grid-template-columns:
            1fr;

        padding: 10px;

    }


    .ss-stat {

        padding: 21px;

    }


    .ss-section {

        padding:
            75px 0;

    }


    .ss-services {

        grid-template-columns:
            1fr;

    }


    .ss-support-card {

        grid-template-columns:
            1fr;

        padding: 30px;

    }


    .ss-support-btn {

        width: 100%;

    }


    .ss-safety-card {

        flex-direction:
            column;

    }

}

</style>


<div class="ss-home">


    <!-- =====================================================
         TOP STRIP
    ====================================================== -->

    <div class="ss-top-strip">

        <div class="ss-container ss-top-strip-inner">

            <span>
                Digital Healthcare Support Portal
            </span>

            <span class="ss-top-strip-right">
                ● Secure
                &nbsp; • &nbsp;
                Human Reviewed
                &nbsp; • &nbsp;
                Accessible
            </span>

        </div>

    </div>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="ss-hero">

        <div class="ss-hero-glow"></div>


        <div class="ss-container ss-hero-grid">


            <!-- =================================================
                 HERO CONTENT
            ================================================= -->

            <div class="ss-hero-content">


                <!-- LOGO -->

                <div class="ss-hero-brand">

                    <img
                        src="assets/images/swasthya-saarathi-logo.png"
                        alt="Swasthya Saarathi Logo"
                    >

                    <div class="ss-hero-brand-text">

                        <strong>
                            Swasthya Saarathi
                        </strong>

                        <span>
                            Better Health • Brighter Tomorrow
                        </span>

                    </div>

                </div>


                <!-- EYEBROW -->

                <div class="ss-eyebrow">

                    Digital Healthcare Triage

                </div>


                <!-- HEADING -->

                <h1>

                    Better information.

                    <span>
                        Better healthcare.
                    </span>

                </h1>


                <!-- DESCRIPTION -->

                <p class="ss-hero-description">

                    Swasthya Saarathi brings patient information,
                    symptoms and medical reports together into one
                    secure digital healthcare workflow designed for
                    structured triage and qualified human review.

                </p>


                <!-- =================================================
                     ACTION BUTTONS
                ================================================= -->

                <div class="ss-hero-actions">


                    <?php if (!empty($_SESSION['user_id'])): ?>

                        <a
                            href="dashboard.php"
                            class="ss-primary-btn"
                        >

                            Open Dashboard

                            <span>
                                →
                            </span>

                        </a>


                    <?php else: ?>

                        <a
                            href="register.php"
                            class="ss-primary-btn"
                        >

                            Get Started

                            <span>
                                →
                            </span>

                        </a>


                        <a
                            href="login.php"
                            class="ss-secondary-btn"
                        >

                            Login to Portal

                        </a>

                    <?php endif; ?>


                </div>


                <!-- =================================================
                     TRUST FEATURES
                ================================================= -->

                <div class="ss-mini-features">


                    <span class="ss-mini-feature">

                        <b>✓</b>

                        Secure

                    </span>


                    <span class="ss-mini-feature">

                        <b>✓</b>

                        Private

                    </span>


                    <span class="ss-mini-feature">

                        <b>✓</b>

                        Human Oversight

                    </span>


                    <span class="ss-mini-feature">

                        <b>✓</b>

                        Multilingual

                    </span>


                </div>


            </div>


            <!-- =================================================
                 RIGHT MEDICAL VISUAL
            ================================================= -->

            <div class="ss-visual">


                <!-- LARGE ORB -->

                <div class="ss-orb"></div>


                <!-- ACTUAL LOGO -->

                <div class="ss-logo-core">

                    <img
                        src="assets/images/swasthya-saarathi-logo.png"
                        alt="Swasthya Saarathi"
                    >

                </div>


                <!-- =================================================
                     FLOATING STAT CARD 1
                ================================================= -->

                <div class="ss-float-card one">

                    <small>
                        Patient Network
                    </small>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $registeredPatients ?>"
                        >
                            <?= number_format($registeredPatients) ?>
                        </span>

                        +

                    </strong>

                </div>


                <!-- =================================================
                     FLOATING STAT CARD 2
                ================================================= -->

                <div class="ss-float-card two">

                    <small>
                        Triage Cases
                    </small>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $totalTriageCases ?>"
                        >
                            <?= number_format($totalTriageCases) ?>
                        </span>

                    </strong>

                </div>


                <!-- =================================================
                     FLOATING STAT CARD 3
                ================================================= -->

                <div class="ss-float-card three">

                    <small>
                        Human Review
                    </small>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $reviewedCases ?>"
                        >
                            <?= number_format($reviewedCases) ?>
                        </span>

                    </strong>

                </div>


            </div>


        </div>

    </section>


    <!-- =====================================================
         LIVE STATISTICS
    ====================================================== -->

    <section class="ss-stats-wrap">

        <div class="ss-container">

            <div class="ss-stats">


                <!-- PATIENTS -->

                <div class="ss-stat">

                    <div class="ss-stat-icon">
                        👥
                    </div>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $registeredPatients ?>"
                        >
                            <?= number_format($registeredPatients) ?>
                        </span>

                    </strong>

                    <span>
                        Registered Patients
                    </span>

                </div>


                <!-- TRIAGE -->

                <div class="ss-stat">

                    <div class="ss-stat-icon">
                        📋
                    </div>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $totalTriageCases ?>"
                        >
                            <?= number_format($totalTriageCases) ?>
                        </span>

                    </strong>

                    <span>
                        Triage Cases Created
                    </span>

                </div>


                <!-- REVIEW -->

                <div class="ss-stat">

                    <div class="ss-stat-icon">
                        🩺
                    </div>

                    <strong>

                        <span
                            class="ss-counter"
                            data-count="<?= $reviewedCases ?>"
                        >
                            <?= number_format($reviewedCases) ?>
                        </span>

                    </strong>

                    <span>
                        Cases Human Reviewed
                    </span>

                </div>


                <!-- ACCESS -->

                <div class="ss-stat">

                    <div class="ss-stat-icon">
                        🔐
                    </div>

                    <strong>
                        24/7
                    </strong>

                    <span>
                        Digital Portal Access
                    </span>

                </div>


            </div>

        </div>

    </section>


    <!-- =====================================================
         SERVICES
    ====================================================== -->

    <section
        class="ss-section"
        id="services"
    >

        <div class="ss-container">


            <div class="ss-section-heading">

                <div class="ss-eyebrow">

                    Platform Capabilities

                </div>


                <h2>

                    Everything needed to organize a healthcare case.

                </h2>


                <p>

                    Capture information naturally through voice,
                    text and documents, then keep everything organized
                    inside the patient's digital case.

                </p>

            </div>


            <div class="ss-services">


                <!-- VOICE -->

                <a
                    href="new_triage.php"
                    class="ss-service"
                >

                    <div class="ss-service-icon">
                        🎤
                    </div>

                    <h3>
                        Voice Symptoms
                    </h3>

                    <p>

                        Record symptoms using multilingual voice
                        input and organize the information into
                        a structured case.

                    </p>

                    <div class="ss-service-arrow">
                        Explore →
                    </div>

                </a>


                <!-- OCR -->

                <a
                    href="new_triage.php#ocr"
                    class="ss-service"
                >

                    <div class="ss-service-icon">
                        📷
                    </div>

                    <h3>
                        Smart OCR
                    </h3>

                    <p>

                        Upload medical document images and extract
                        readable information for verification.

                    </p>

                    <div class="ss-service-arrow">
                        Explore →
                    </div>

                </a>


                <!-- REPORT -->

                <a
                    href="new_triage.php#report"
                    class="ss-service"
                >

                    <div class="ss-service-icon">
                        📄
                    </div>

                    <h3>
                        Medical Reports
                    </h3>

                    <p>

                        Keep supporting medical documents connected
                        with the patient's triage workflow.

                    </p>

                    <div class="ss-service-arrow">
                        Explore →
                    </div>

                </a>


                <!-- TEXT -->

                <a
                    href="new_triage.php#text"
                    class="ss-service"
                >

                    <div class="ss-service-icon">
                        ✍️
                    </div>

                    <h3>
                        Text Symptoms
                    </h3>

                    <p>

                        Enter symptoms, duration and relevant
                        information directly into a structured form.

                    </p>

                    <div class="ss-service-arrow">
                        Explore →
                    </div>

                </a>


            </div>

        </div>

    </section>


    <!-- =====================================================
         WORKFLOW
    ====================================================== -->

    <section
        class="ss-section ss-workflow-section"
        id="about"
    >

        <div class="ss-container ss-workflow-grid">


            <div class="ss-workflow-copy">


                <div class="ss-eyebrow">

                    Simple Workflow

                </div>


                <h2>

                    From patient registration to human review.

                </h2>


                <p>

                    Every visit can begin with a fresh triage case
                    while the Patient Unique ID keeps the patient's
                    overall record connected.

                </p>


            </div>


            <div class="ss-steps">


                <!-- STEP 1 -->

                <div class="ss-step">

                    <div class="ss-step-number">
                        01
                    </div>

                    <div>

                        <strong>
                            Patient Registration
                        </strong>

                        <span>

                            Register the patient and generate a
                            Patient Unique ID.

                        </span>

                    </div>

                </div>


                <!-- STEP 2 -->

                <div class="ss-step">

                    <div class="ss-step-number">
                        02
                    </div>

                    <div>

                        <strong>
                            Capture Information
                        </strong>

                        <span>

                            Add symptoms, voice information and
                            supporting medical documents.

                        </span>

                    </div>

                </div>


                <!-- STEP 3 -->

                <div class="ss-step">

                    <div class="ss-step-number">
                        03
                    </div>

                    <div>

                        <strong>
                            Create Triage Case
                        </strong>

                        <span>

                            A new case is created for the current
                            hospital visit.

                        </span>

                    </div>

                </div>


                <!-- STEP 4 -->

                <div class="ss-step">

                    <div class="ss-step-number">
                        04
                    </div>

                    <div>

                        <strong>
                            Human Review
                        </strong>

                        <span>

                            Authorized healthcare personnel can
                            review the submitted information.

                        </span>

                    </div>

                </div>


            </div>

        </div>

    </section>


    <!-- =====================================================
         SUPPORT CTA
    ====================================================== -->

    <section
        class="ss-support"
        id="help"
    >

        <div class="ss-container">

            <div class="ss-support-card">


                <div>

                    <div class="ss-eyebrow">

                        Patient Support

                    </div>


                    <h2>

                        Need help using the portal?

                    </h2>


                    <p>

                        Contact the Swasthya Saarathi support team
                        for general questions about account access,
                        patient IDs and submitting information.

                    </p>

                </div>


                <div>


                    <?php if (!empty($_SESSION['user_id'])): ?>

                        <a
                            href="dashboard.php"
                            class="ss-support-btn"
                        >

                            Open Dashboard →

                        </a>


                    <?php else: ?>

                        <a
                            href="login.php"
                            class="ss-support-btn"
                        >

                            Login to Portal →

                        </a>

                    <?php endif; ?>


                </div>


            </div>

        </div>

    </section>


    <!-- =====================================================
         SAFETY NOTICE
    ====================================================== -->

    <section class="ss-safety">

        <div class="ss-container">

            <div class="ss-safety-card">


                <div class="ss-safety-icon">
                    !
                </div>


                <div>

                    <strong>
                        Important healthcare notice
                    </strong>


                    <p>

                        Swasthya Saarathi provides information
                        organization and triage-support functionality.
                        Automated indicators are advisory and are not
                        a diagnosis. This service does not replace
                        emergency care or assessment by a qualified
                        healthcare professional.

                    </p>

                </div>


            </div>

        </div>

    </section>


</div>


<script>

/* ============================================================
   ANIMATED STATISTICS
============================================================ */

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const counters =
            document.querySelectorAll(
                '.ss-counter'
            );


        counters.forEach(
            function (counter) {


                const target =
                    parseInt(
                        counter.dataset.count || '0',
                        10
                    );


                const duration = 1200;

                const start = 0;

                const startTime =
                    performance.now();


                function updateCounter(
                    currentTime
                ) {


                    const progress =
                        Math.min(
                            (
                                currentTime -
                                startTime
                            ) / duration,
                            1
                        );


                    const eased =
                        1 -
                        Math.pow(
                            1 - progress,
                            3
                        );


                    const value =
                        Math.floor(
                            start +
                            (
                                target -
                                start
                            ) *
                            eased
                        );


                    counter.textContent =
                        value.toLocaleString(
                            'en-IN'
                        );


                    if (progress < 1) {

                        requestAnimationFrame(
                            updateCounter
                        );

                    } else {

                        counter.textContent =
                            target.toLocaleString(
                                'en-IN'
                            );

                    }

                }


                requestAnimationFrame(
                    updateCounter
                );

            }
        );

    }
);

</script>


<?php

include __DIR__ . '/includes/footer.php';

?>