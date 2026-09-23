<?php
/*
|--------------------------------------------------------------------------
| SWASTHYA SAARATHI — PREMIUM FOOTER
|--------------------------------------------------------------------------
*/
?>

<style>

/* =========================================================
   PREMIUM FOOTER
========================================================= */

.ss-footer {
    position: relative;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;

    background:
        radial-gradient(
            circle at 12% 15%,
            rgba(0, 210, 205, .17),
            transparent 30%
        ),
        radial-gradient(
            circle at 88% 8%,
            rgba(0, 130, 220, .16),
            transparent 32%
        ),
        linear-gradient(
            135deg,
            #042f49 0%,
            #064b66 45%,
            #07566e 100%
        );

    color: #fff;
}


/* =========================================================
   DECORATIVE BACKGROUND
========================================================= */

.ss-footer::before {
    content: "";
    position: absolute;

    width: 440px;
    height: 440px;

    right: -190px;
    top: -230px;

    border-radius: 50%;

    background: rgba(0, 215, 215, .07);

    pointer-events: none;
}

.ss-footer::after {
    content: "";
    position: absolute;

    width: 330px;
    height: 330px;

    left: -170px;
    bottom: -190px;

    border-radius: 50%;

    background: rgba(0, 160, 230, .07);

    pointer-events: none;
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.ss-footer-container {
    position: relative;
    z-index: 2;

    width: min(1180px, calc(100% - 40px));

    margin: 0 auto;

    padding: 58px 0 38px;

    display: grid;

    grid-template-columns:
        minmax(280px, 1.5fr)
        minmax(150px, .8fr)
        minmax(220px, 1fr);

    gap: 55px;

    box-sizing: border-box;
}


/* =========================================================
   BRAND
========================================================= */

.ss-footer-brand {
    min-width: 0;
}

.ss-footer-brand-top {
    display: flex;
    align-items: center;

    gap: 16px;

    margin-bottom: 21px;
}


/* =========================================================
   ACTUAL SWASTHYA SAARATHI LOGO
========================================================= */

.ss-footer-logo-wrap {
    position: relative;

    width: 70px;
    height: 70px;

    flex: 0 0 70px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 20px;

    background: #ffffff;

    border: 1px solid rgba(255,255,255,.55);

    box-shadow:
        0 15px 35px rgba(0,0,0,.20),
        0 0 0 1px rgba(255,255,255,.10);

    overflow: hidden;
}


/*
|--------------------------------------------------------------------------
| ACTUAL LOGO FILE
|--------------------------------------------------------------------------
|
| Your logo:
|
| assets/images/Swasthya-Saarathi-logo.png
|
*/

.ss-footer-logo {
    width: 100%;
    height: 100%;

    display: block;

    object-fit: contain;

    padding: 5px;

    box-sizing: border-box;
}


/* =========================================================
   BRAND NAME
========================================================= */

.ss-footer-brand-name {
    margin: 0;

    font-size: 22px;

    line-height: 1.1;

    font-weight: 900;

    letter-spacing: -.4px;
}

.ss-footer-brand-name span {
    color: #31ddd2;
}

.ss-footer-tagline {
    margin: 7px 0 0;

    color: rgba(255,255,255,.63);

    font-size: 12px;

    font-weight: 600;

    letter-spacing: .3px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.ss-footer-description {
    max-width: 520px;

    margin: 0;

    color: rgba(255,255,255,.73);

    font-size: 14px;

    line-height: 1.8;
}


/* =========================================================
   TRUST BADGES
========================================================= */

.ss-footer-badges {
    display: flex;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 22px;
}

.ss-footer-badge {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 8px 12px;

    border-radius: 999px;

    border: 1px solid rgba(255,255,255,.14);

    background: rgba(255,255,255,.07);

    color: rgba(255,255,255,.80);

    font-size: 11px;

    font-weight: 700;

    backdrop-filter: blur(8px);

    transition: .2s ease;
}

.ss-footer-badge:hover {
    background: rgba(255,255,255,.11);

    transform: translateY(-1px);
}


/* =========================================================
   FOOTER COLUMNS
========================================================= */

.ss-footer-column {
    min-width: 0;
}

.ss-footer-title {
    margin: 0 0 19px;

    color: #fff;

    font-size: 16px;

    font-weight: 800;
}


/* =========================================================
   QUICK LINKS
========================================================= */

.ss-footer-links {
    list-style: none;

    margin: 0;

    padding: 0;

    display: grid;

    gap: 12px;
}

.ss-footer-links li {
    margin: 0;
    padding: 0;
}

.ss-footer-links a {
    display: inline-flex;

    align-items: center;

    gap: 10px;

    color: rgba(255,255,255,.68);

    font-size: 14px;

    text-decoration: none;

    transition:
        color .2s ease,
        transform .2s ease;
}

.ss-footer-links a::before {
    content: "→";

    color: #35d9d0;

    font-size: 15px;

    transition: transform .2s ease;
}

.ss-footer-links a:hover {
    color: #fff;

    transform: translateX(3px);
}

.ss-footer-links a:hover::before {
    transform: translateX(3px);
}


/* =========================================================
   HEALTHCARE SUPPORT
========================================================= */

.ss-support-list {
    display: grid;

    gap: 12px;
}

.ss-support-item {
    display: flex;

    align-items: center;

    gap: 11px;

    color: rgba(255,255,255,.68);

    font-size: 14px;
}

.ss-support-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: grid;

    place-items: center;

    border-radius: 10px;

    background: rgba(255,255,255,.08);

    border: 1px solid rgba(255,255,255,.09);

    font-size: 15px;

    transition: .2s ease;
}

.ss-support-item:hover .ss-support-icon {
    background: rgba(255,255,255,.14);

    transform: translateY(-2px);
}


/* =========================================================
   HELP SECTION
========================================================= */

.ss-footer-help {
    position: relative;

    z-index: 2;

    width: min(1180px, calc(100% - 40px));

    margin: 0 auto;

    padding: 22px 0;

    border-top: 1px solid rgba(255,255,255,.09);

    box-sizing: border-box;
}

.ss-footer-help-box {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 21px 23px;

    border-radius: 18px;

    background: rgba(255,255,255,.06);

    border: 1px solid rgba(255,255,255,.10);

    backdrop-filter: blur(10px);

    box-shadow:
        0 15px 40px rgba(0,0,0,.08);
}

.ss-footer-help-title {
    margin: 0 0 4px;

    font-size: 15px;

    font-weight: 800;
}

.ss-footer-help-text {
    margin: 0;

    color: rgba(255,255,255,.63);

    font-size: 12px;

    line-height: 1.5;
}

.ss-footer-help-button {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 11px 18px;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #079bd1,
            #00b3a8
        );

    color: #fff;

    font-size: 12px;

    font-weight: 800;

    text-decoration: none;

    white-space: nowrap;

    box-shadow:
        0 8px 20px rgba(0,150,190,.20);

    transition: .2s ease;
}

.ss-footer-help-button:hover {
    transform: translateY(-2px);

    box-shadow:
        0 12px 26px rgba(0,150,190,.30);
}


/* =========================================================
   BOTTOM BAR
========================================================= */

.ss-footer-bottom {
    position: relative;

    z-index: 2;

    border-top: 1px solid rgba(255,255,255,.08);

    background: rgba(0,0,0,.12);
}

.ss-footer-bottom-inner {
    width: min(1180px, calc(100% - 40px));

    min-height: 58px;

    margin: 0 auto;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    box-sizing: border-box;
}

.ss-footer-copyright {
    margin: 0;

    color: rgba(255,255,255,.55);

    font-size: 11px;
}

.ss-footer-status {
    display: flex;

    align-items: center;

    gap: 7px;

    color: rgba(255,255,255,.58);

    font-size: 11px;
}

.ss-footer-status-dot {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #36d7a4;

    box-shadow:
        0 0 0 4px rgba(54,215,164,.10);
}


/* =========================================================
   RESPONSIVE — TABLET
========================================================= */

@media (max-width: 900px) {

    .ss-footer-container {
        grid-template-columns: 1fr 1fr;

        gap: 40px;
    }

    .ss-footer-brand {
        grid-column: 1 / -1;
    }
}


/* =========================================================
   RESPONSIVE — MOBILE
========================================================= */

@media (max-width: 620px) {

    .ss-footer-container {

        width: min(
            calc(100% - 28px),
            500px
        );

        grid-template-columns: 1fr;

        gap: 32px;

        padding: 42px 0 28px;
    }

    .ss-footer-brand {
        grid-column: auto;
    }

    .ss-footer-logo-wrap {

        width: 58px;
        height: 58px;

        flex-basis: 58px;

        border-radius: 17px;
    }

    .ss-footer-brand-name {
        font-size: 19px;
    }

    .ss-footer-help {

        width: min(
            calc(100% - 28px),
            500px
        );
    }

    .ss-footer-help-box {

        align-items: flex-start;

        flex-direction: column;
    }

    .ss-footer-help-button {
        width: 100%;
    }

    .ss-footer-bottom-inner {

        width: min(
            calc(100% - 28px),
            500px
        );

        min-height: auto;

        padding: 18px 0;

        align-items: flex-start;

        flex-direction: column;
    }
}

</style>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="ss-footer">


    <!-- =====================================================
         MAIN FOOTER
    ====================================================== -->

    <div class="ss-footer-container">


        <!-- =================================================
             BRAND
        ================================================== -->

        <div class="ss-footer-brand">


            <div class="ss-footer-brand-top">


                <!-- =================================================
                     ACTUAL LOGO
                ================================================== -->

                <div class="ss-footer-logo-wrap">

                    <img
                        src="assets/images/Swasthya-Saarathi-logo.png"
                        alt="Swasthya Saarathi Logo"
                        class="ss-footer-logo"
                        loading="eager"
                        decoding="async"
                    >

                </div>


                <!-- =================================================
                     BRAND TEXT
                ================================================== -->

                <div>

                    <h3 class="ss-footer-brand-name">

                        SWASTHYA

                        <span>
                            SAARATHI
                        </span>

                    </h3>


                    <p class="ss-footer-tagline">
                        Digital Healthcare Triage Support
                    </p>

                </div>

            </div>


            <!-- =================================================
                 DESCRIPTION
            ================================================== -->

            <p class="ss-footer-description">

                A digital healthcare support platform designed to
                organize patient information, symptoms and medical
                documents for structured triage and qualified
                human review.

            </p>


            <!-- =================================================
                 TRUST BADGES
            ================================================== -->

            <div class="ss-footer-badges">

                <span class="ss-footer-badge">
                    ✓ Secure
                </span>

                <span class="ss-footer-badge">
                    ✓ Private
                </span>

                <span class="ss-footer-badge">
                    ✓ Human Reviewed
                </span>

            </div>

        </div>


        <!-- =================================================
             QUICK LINKS
        ================================================== -->

        <div class="ss-footer-column">

            <h3 class="ss-footer-title">
                Quick Links
            </h3>


            <ul class="ss-footer-links">

                <li>
                    <a href="index.php">
                        Home
                    </a>
                </li>

                <li>
                    <a href="services.php">
                        Services
                    </a>
                </li>

                <li>
                    <a href="about.php">
                        About Us
                    </a>
                </li>

                <li>
                    <a href="help.php">
                        Help &amp; Support
                    </a>
                </li>

                <li>
                    <a href="contact.php">
                        Contact
                    </a>
                </li>

            </ul>

        </div>


        <!-- =================================================
             HEALTHCARE SUPPORT
        ================================================== -->

        <div class="ss-footer-column">

            <h3 class="ss-footer-title">
                Healthcare Support
            </h3>


            <div class="ss-support-list">


                <div class="ss-support-item">

                    <span class="ss-support-icon">
                        🎤
                    </span>

                    <span>
                        Voice Symptoms
                    </span>

                </div>


                <div class="ss-support-item">

                    <span class="ss-support-icon">
                        ▣
                    </span>

                    <span>
                        OCR Document Scan
                    </span>

                </div>


                <div class="ss-support-item">

                    <span class="ss-support-icon">
                        📄
                    </span>

                    <span>
                        Medical Reports
                    </span>

                </div>


                <div class="ss-support-item">

                    <span class="ss-support-icon">
                        📝
                    </span>

                    <span>
                        Text Symptoms
                    </span>

                </div>


                <div class="ss-support-item">

                    <span class="ss-support-icon">
                        📋
                    </span>

                    <span>
                        Case History
                    </span>

                </div>


            </div>

        </div>

    </div>


    <!-- =====================================================
         HELP SECTION
    ====================================================== -->

    <div class="ss-footer-help">

        <div class="ss-footer-help-box">

            <div>

                <h3 class="ss-footer-help-title">
                    Need Help?
                </h3>

                <p class="ss-footer-help-text">
                    Our support team is available to help you
                    with portal access and general assistance.
                </p>

            </div>


            <a
                href="contact.php"
                class="ss-footer-help-button"
            >
                Contact Support →
            </a>

        </div>

    </div>


    <!-- =====================================================
         BOTTOM BAR
    ====================================================== -->

    <div class="ss-footer-bottom">

        <div class="ss-footer-bottom-inner">


            <p class="ss-footer-copyright">

                © <?= date('Y') ?>

                Swasthya Saarathi.

                All rights reserved.

            </p>


            <div class="ss-footer-status">

                <span class="ss-footer-status-dot"></span>

                <span>
                    Healthcare Support Portal
                </span>

            </div>

        </div>

    </div>

</footer>