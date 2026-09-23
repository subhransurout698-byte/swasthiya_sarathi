/*
|--------------------------------------------------------------------------
| Swasthya Saarthi
| Main Application JavaScript
|--------------------------------------------------------------------------
*/

(function () {

    "use strict";


    /*
    |--------------------------------------------------------------------------
    | DOM Ready
    |--------------------------------------------------------------------------
    */

    document.addEventListener("DOMContentLoaded", function () {

        initMobileMenu();
        initPasswordToggle();
        initFileInputs();
        initAutoHideAlerts();
        initConfirmActions();
        initCharacterCounters();

    });


    /*
    |--------------------------------------------------------------------------
    | Mobile Navigation
    |--------------------------------------------------------------------------
    */

    function initMobileMenu() {

        const toggle =
            document.querySelector("[data-mobile-toggle]");

        const menu =
            document.querySelector("[data-mobile-menu]");

        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener("click", function () {

            const opened =
                menu.classList.toggle("is-open");

            toggle.setAttribute(
                "aria-expanded",
                opened ? "true" : "false"
            );

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Password Visibility
    |--------------------------------------------------------------------------
    */

    function initPasswordToggle() {

        document
            .querySelectorAll("[data-password-toggle]")
            .forEach(function (button) {

                button.addEventListener("click", function () {

                    const targetId =
                        button.getAttribute("data-password-toggle");

                    const input =
                        document.getElementById(targetId);

                    if (!input) {
                        return;
                    }

                    if (input.type === "password") {

                        input.type = "text";

                        button.textContent =
                            "Hide";

                    } else {

                        input.type = "password";

                        button.textContent =
                            "Show";
                    }

                });

            });

    }


    /*
    |--------------------------------------------------------------------------
    | File Input UI
    |--------------------------------------------------------------------------
    */

    function initFileInputs() {

        document
            .querySelectorAll('input[type="file"]')
            .forEach(function (input) {

                input.addEventListener("change", function () {

                    const outputId =
                        input.getAttribute("data-file-name");

                    if (!outputId) {
                        return;
                    }

                    const output =
                        document.getElementById(outputId);

                    if (!output) {
                        return;
                    }

                    if (!input.files.length) {

                        output.textContent =
                            "No file selected";

                        return;
                    }

                    if (input.files.length === 1) {

                        output.textContent =
                            input.files[0].name;

                    } else {

                        output.textContent =
                            input.files.length +
                            " files selected";
                    }

                });

            });

    }


    /*
    |--------------------------------------------------------------------------
    | Alert Auto Hide
    |--------------------------------------------------------------------------
    */

    function initAutoHideAlerts() {

        document
            .querySelectorAll("[data-auto-hide]")
            .forEach(function (alert) {

                const delay =
                    parseInt(
                        alert.getAttribute("data-auto-hide"),
                        10
                    ) || 5000;

                setTimeout(function () {

                    alert.classList.add("fade-out");

                }, delay);

            });

    }


    /*
    |--------------------------------------------------------------------------
    | Confirmation Buttons
    |--------------------------------------------------------------------------
    */

    function initConfirmActions() {

        document
            .querySelectorAll("[data-confirm]")
            .forEach(function (element) {

                element.addEventListener("click", function (event) {

                    const message =
                        element.getAttribute("data-confirm");

                    if (
                        message &&
                        !window.confirm(message)
                    ) {
                        event.preventDefault();
                    }

                });

            });

    }


    /*
    |--------------------------------------------------------------------------
    | Character Counter
    |--------------------------------------------------------------------------
    */

    function initCharacterCounters() {

        document
            .querySelectorAll("[data-character-count]")
            .forEach(function (input) {

                const outputId =
                    input.getAttribute(
                        "data-character-count"
                    );

                const output =
                    document.getElementById(outputId);

                if (!output) {
                    return;
                }

                function updateCounter() {

                    output.textContent =
                        input.value.length;

                }

                input.addEventListener(
                    "input",
                    updateCounter
                );

                updateCounter();

            });

    }


    /*
    |--------------------------------------------------------------------------
    | Loading State
    |--------------------------------------------------------------------------
    */

    window.SwasthyaSaarathi = {

        setLoading: function (button, loadingText) {

            if (!button) {
                return;
            }

            if (
                !button.dataset.originalText
            ) {
                button.dataset.originalText =
                    button.textContent;
            }

            button.disabled = true;

            button.textContent =
                loadingText || "Processing...";

        },


        removeLoading: function (button) {

            if (!button) {
                return;
            }

            button.disabled = false;

            if (button.dataset.originalText) {

                button.textContent =
                    button.dataset.originalText;

            }

        }

    };

})();