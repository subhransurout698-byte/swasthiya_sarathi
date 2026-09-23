/*
|--------------------------------------------------------------------------
| Swasthya Saarathi
| Multilingual Voice Input
|--------------------------------------------------------------------------
|
| Supported:
| English  -> en-IN
| Hindi    -> hi-IN
| Marathi  -> mr-IN
| Tamil    -> ta-IN
| Telugu   -> te-IN
| Bengali  -> bn-IN
| Odia     -> or-IN
|
|--------------------------------------------------------------------------
*/

(function () {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | Browser support
    |--------------------------------------------------------------------------
    */

    const SpeechRecognition =
        window.SpeechRecognition ||
        window.webkitSpeechRecognition;


    /*
    |--------------------------------------------------------------------------
    | Language map
    |--------------------------------------------------------------------------
    */

    const LANGUAGE_MAP = {

        'English': 'en-IN',

        'Hindi': 'hi-IN',

        'Marathi': 'mr-IN',

        'Tamil': 'ta-IN',

        'Telugu': 'te-IN',

        'Bengali': 'bn-IN',

        'Odia': 'or-IN'

    };


    /*
    |--------------------------------------------------------------------------
    | Human-readable names
    |--------------------------------------------------------------------------
    */

    const LANGUAGE_NAMES = {

        'en-IN': 'English',

        'hi-IN': 'Hindi',

        'mr-IN': 'Marathi',

        'ta-IN': 'Tamil',

        'te-IN': 'Telugu',

        'bn-IN': 'Bengali',

        'or-IN': 'Odia'

    };


    /*
    |--------------------------------------------------------------------------
    | Get language from select
    |--------------------------------------------------------------------------
    */

    function getSelectedLanguage(select) {

        if (!select) {
            return 'en-IN';
        }


        const option =
            select.options[
                select.selectedIndex
            ];


        if (!option) {
            return 'en-IN';
        }


        /*
        | Prefer data-speech-lang.
        */

        if (option.dataset.speechLang) {

            return option.dataset.speechLang;

        }


        /*
        | Otherwise use language map.
        */

        const value =
            option.value;


        return LANGUAGE_MAP[value] ||
            'en-IN';

    }


    /*
    |--------------------------------------------------------------------------
    | Initialize voice buttons
    |--------------------------------------------------------------------------
    */

    function initializeVoiceButtons() {

        const buttons =
            document.querySelectorAll(
                '[data-voice-button]'
            );


        if (!buttons.length) {
            return;
        }


        buttons.forEach(
            function (button) {

                setupVoiceButton(button);

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Setup individual voice button
    |--------------------------------------------------------------------------
    */

    function setupVoiceButton(button) {

        const targetId =
            button.dataset.voiceTarget;


        const target =
            document.getElementById(
                targetId
            );


        const languageSelect =
            document.getElementById(
                button.dataset.languageSelect
            );


        const statusId =
            button.dataset.voiceStatus;


        const status =
            document.getElementById(
                statusId
            );


        if (!target) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Browser support check
        |--------------------------------------------------------------------------
        */

        if (!SpeechRecognition) {

            button.disabled = true;

            button.title =
                'Voice input is not supported by this browser.';

            if (status) {

                status.textContent =
                    'Voice input is not supported in this browser.';

            }

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Create recognition instance
        |--------------------------------------------------------------------------
        */

        let recognition =
            null;


        let listening =
            false;


        let manuallyStopped =
            false;


        /*
        |--------------------------------------------------------------------------
        | Create recognizer
        |--------------------------------------------------------------------------
        */

        function createRecognition() {

            recognition =
                new SpeechRecognition();


            /*
            | Continuous speech allows longer input.
            */

            recognition.continuous =
                true;


            /*
            | Interim results show text while speaking.
            */

            recognition.interimResults =
                true;


            /*
            | Return several possible results.
            */

            recognition.maxAlternatives =
                1;


            /*
            | IMPORTANT:
            | Read currently selected language.
            */

            recognition.lang =
                getSelectedLanguage(
                    languageSelect
                );


            /*
            |--------------------------------------------------------------------------
            | Recognition started
            |--------------------------------------------------------------------------
            */

            recognition.onstart =
                function () {

                    listening =
                        true;

                    manuallyStopped =
                        false;


                    button.classList.add(
                        'is-listening'
                    );


                    button.setAttribute(
                        'aria-pressed',
                        'true'
                    );


                    button.textContent =
                        '⏹️';


                    const selectedLanguage =
                        recognition.lang;


                    const languageName =
                        LANGUAGE_NAMES[
                            selectedLanguage
                        ] ||
                        selectedLanguage;


                    if (status) {

                        status.textContent =
                            'Listening in ' +
                            languageName +
                            '…';

                    }

                };


            /*
            |--------------------------------------------------------------------------
            | Recognition result
            |--------------------------------------------------------------------------
            */

            recognition.onresult =
                function (event) {

                    let finalText =
                        '';


                    let interimText =
                        '';


                    for (
                        let i = event.resultIndex;
                        i < event.results.length;
                        i++
                    ) {

                        const result =
                            event.results[i];


                        const transcript =
                            result[0].transcript;


                        if (result.isFinal) {

                            finalText +=
                                transcript;

                        } else {

                            interimText +=
                                transcript;

                        }

                    }


                    /*
                    | Keep existing text.
                    */

                    const existingText =
                        target.value.trim();


                    /*
                    | Append final transcription.
                    */

                    if (finalText.trim()) {

                        if (existingText) {

                            target.value =
                                existingText +
                                ' ' +
                                finalText.trim();

                        } else {

                            target.value =
                                finalText.trim();

                        }


                        /*
                        | Trigger input event so
                        | character counters update.
                        */

                        target.dispatchEvent(
                            new Event(
                                'input',
                                {
                                    bubbles: true
                                }
                            )
                        );

                    }


                    /*
                    | Show interim result.
                    */

                    if (
                        status &&
                        interimText.trim()
                    ) {

                        status.textContent =
                            'Listening: ' +
                            interimText.trim();

                    }

                };


            /*
            |--------------------------------------------------------------------------
            | Recognition error
            |--------------------------------------------------------------------------
            */

            recognition.onerror =
                function (event) {

                    console.warn(
                        'Speech recognition error:',
                        event.error
                    );


                    let message =
                        'Voice input error.';


                    switch (event.error) {

                        case 'not-allowed':

                            message =
                                'Microphone permission was denied.';

                            break;


                        case 'audio-capture':

                            message =
                                'No microphone was detected.';

                            break;


                        case 'no-speech':

                            message =
                                'No speech was detected. Try again.';

                            break;


                        case 'network':

                            message =
                                'Speech recognition network error.';

                            break;


                        case 'aborted':

                            message =
                                'Voice input stopped.';

                            break;


                        case 'service-not-allowed':

                            message =
                                'Speech recognition service is unavailable.';

                            break;


                        default:

                            message =
                                'Voice input error: ' +
                                event.error;

                    }


                    if (status) {

                        status.textContent =
                            message;

                    }

                };


            /*
            |--------------------------------------------------------------------------
            | Recognition ended
            |--------------------------------------------------------------------------
            */

            recognition.onend =
                function () {

                    listening =
                        false;


                    button.classList.remove(
                        'is-listening'
                    );


                    button.setAttribute(
                        'aria-pressed',
                        'false'
                    );


                    button.textContent =
                        '🎤';


                    /*
                    | If the user didn't manually stop,
                    | do not automatically restart.
                    |
                    | This avoids endless microphone loops.
                    */

                    if (
                        !manuallyStopped &&
                        status
                    ) {

                        status.textContent =
                            'Voice input ready';

                    }

                };

        }


        /*
        |--------------------------------------------------------------------------
        | Start / stop
        |--------------------------------------------------------------------------
        */

        button.addEventListener(
            'click',
            function () {

                /*
                | Stop current recognition.
                */

                if (
                    listening &&
                    recognition
                ) {

                    manuallyStopped =
                        true;

                    recognition.stop();

                    return;
                }


                /*
                | Create a fresh recognition object.
                |
                | This is important because the user may
                | have changed the language before clicking.
                */

                createRecognition();


                /*
                | Get latest selected language.
                */

                const selectedLanguage =
                    getSelectedLanguage(
                        languageSelect
                    );


                recognition.lang =
                    selectedLanguage;


                try {

                    recognition.start();

                } catch (error) {

                    console.warn(
                        'Unable to start speech recognition:',
                        error
                    );


                    if (status) {

                        status.textContent =
                            'Unable to start microphone. Please try again.';

                    }

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Language changed
        |--------------------------------------------------------------------------
        |
        | If language changes while microphone is OFF,
        | next recognition automatically uses new language.
        |
        */

        if (languageSelect) {

            languageSelect.addEventListener(
                'change',
                function () {

                    const newLanguage =
                        getSelectedLanguage(
                            languageSelect
                        );


                    /*
                    | If currently listening, stop it.
                    | User can press microphone again to
                    | start using the new language.
                    */

                    if (
                        listening &&
                        recognition
                    ) {

                        manuallyStopped =
                            true;

                        recognition.stop();

                    }


                    if (status) {

                        const languageName =
                            LANGUAGE_NAMES[
                                newLanguage
                            ] ||
                            newLanguage;


                        status.textContent =
                            'Voice input ready — ' +
                            languageName +
                            ' selected.';

                    }

                }
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Initialize when DOM is ready
    |--------------------------------------------------------------------------
    */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initializeVoiceButtons
        );

    } else {

        initializeVoiceButtons();

    }


})();