document.addEventListener('DOMContentLoaded', () => {

    const input =
        document.querySelector('[data-ocr-input]');

    if (!input) {
        return;
    }

    const preview =
        document.getElementById(
            input.dataset.ocrPreview
        );

    const status =
        document.getElementById(
            input.dataset.ocrStatus
        );

    const symptoms =
        document.getElementById('symptoms');

    const resultBox =
        document.getElementById(
            'ocr-result-box'
        );

    const resultText =
        document.getElementById(
            'ocr-result'
        );


    input.addEventListener(
        'change',
        async () => {

            const file =
                input.files?.[0];

            if (!file) {
                return;
            }


            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            if (!allowedTypes.includes(file.type)) {

                status.textContent =
                    'Please select JPG, PNG or WebP.';

                input.value = '';

                return;
            }


            if (
                file.size >
                10 * 1024 * 1024
            ) {

                status.textContent =
                    'Maximum file size is 10 MB.';

                input.value = '';

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Preview
            |--------------------------------------------------------------------------
            */

            const reader =
                new FileReader();


            reader.onload =
                event => {

                    preview.src =
                        event.target.result;

                    preview.style.display =
                        'block';

                };


            reader.readAsDataURL(file);


            /*
            |--------------------------------------------------------------------------
            | Upload
            |--------------------------------------------------------------------------
            */

            status.textContent =
                'Uploading document for OCR…';


            input.disabled = true;


            const formData =
                new FormData();


            formData.append(
                'medical_document',
                file
            );


            try {

                const response =
                    await fetch(
                        'ocr/process.php',
                        {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept':
                                    'application/json'
                            }
                        }
                    );


                const result =
                    await response.json();


                if (!result.success) {

                    status.textContent =
                        result.message ||
                        'OCR processing failed.';

                    return;
                }


                const extractedText =
                    (result.text || '').trim();


                if (!extractedText) {

                    status.textContent =
                        'No readable text detected.';

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Display extracted text
                |--------------------------------------------------------------------------
                */

                if (resultText) {

                    resultText.value =
                        extractedText;
                }


                if (resultBox) {

                    resultBox.style.display =
                        'block';
                }


                /*
                |--------------------------------------------------------------------------
                | Add OCR text to symptoms
                |--------------------------------------------------------------------------
                */

                if (symptoms) {

                    const current =
                        symptoms.value.trim();


                    const separator =
                        current
                            ? '\n\n'
                            : '';


                    symptoms.value =
                        current +
                        separator +
                        '--- OCR FROM MEDICAL REPORT ---\n' +
                        extractedText;


                    symptoms.dispatchEvent(
                        new Event(
                            'input',
                            {
                                bubbles: true
                            }
                        )
                    );

                }


                status.textContent =
                    '✓ OCR completed. Extracted text added to symptoms.';


            } catch (error) {

                console.error(
                    'OCR error:',
                    error
                );


                status.textContent =
                    'Unable to connect to the OCR service.';

            } finally {

                input.disabled =
                    false;

            }

        }
    );

});