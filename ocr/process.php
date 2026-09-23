<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/ocr.php';


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function ocr_response(
    bool $success,
    string $message = '',
    array $data = []
): never {

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Request method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    ocr_response(
        false,
        'Only POST requests are allowed.'
    );
}


/*
|--------------------------------------------------------------------------
| Check upload
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['medical_document']) ||
    !is_array($_FILES['medical_document'])
) {

    ocr_response(
        false,
        'No image was uploaded.'
    );
}


$file = $_FILES['medical_document'];


/*
|--------------------------------------------------------------------------
| Upload errors
|--------------------------------------------------------------------------
*/

if ($file['error'] !== UPLOAD_ERR_OK) {

    $errors = [
        UPLOAD_ERR_INI_SIZE =>
            'The uploaded image exceeds the server upload limit.',

        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded image is too large.',

        UPLOAD_ERR_PARTIAL =>
            'The image upload was incomplete.',

        UPLOAD_ERR_NO_FILE =>
            'No image was uploaded.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Server temporary directory is unavailable.',

        UPLOAD_ERR_CANT_WRITE =>
            'Server could not save the uploaded image.',

        UPLOAD_ERR_EXTENSION =>
            'A PHP extension stopped the upload.'
    ];

    ocr_response(
        false,
        $errors[$file['error']] ??
        'Unknown upload error.'
    );
}


/*
|--------------------------------------------------------------------------
| Size validation
|--------------------------------------------------------------------------
*/

if ($file['size'] > 10 * 1024 * 1024) {

    ocr_response(
        false,
        'Image must be 10 MB or smaller.'
    );
}


/*
|--------------------------------------------------------------------------
| MIME validation
|--------------------------------------------------------------------------
*/

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file(
    $file['tmp_name']
);

$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mime, $allowed, true)) {

    ocr_response(
        false,
        'Only JPG, PNG and WebP images are supported.'
    );
}


/*
|--------------------------------------------------------------------------
| cURL
|--------------------------------------------------------------------------
*/

if (!function_exists('curl_init')) {

    ocr_response(
        false,
        'PHP cURL is not enabled.'
    );
}


/*
|--------------------------------------------------------------------------
| Prepare OCR request
|--------------------------------------------------------------------------
*/

$curl = curl_init();

$postFields = [
    'apikey' => OCR_API_KEY,

    'language' => 'eng',

    'isOverlayRequired' => 'false',

    'detectOrientation' => 'true',

    'scale' => 'true',

    'OCREngine' => '2',

    'file' => new CURLFile(
        $file['tmp_name'],
        $mime,
        $file['name']
    )
];


curl_setopt_array(
    $curl,
    [

        CURLOPT_URL =>
            OCR_API_URL,

        CURLOPT_POST =>
            true,

        CURLOPT_POSTFIELDS =>
            $postFields,

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_FOLLOWLOCATION =>
            true,

        CURLOPT_CONNECTTIMEOUT =>
            20,

        CURLOPT_TIMEOUT =>
            120,

        CURLOPT_SSL_VERIFYPEER =>
            true,

        CURLOPT_SSL_VERIFYHOST =>
            2,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]

    ]
);


$response = curl_exec($curl);

$curlErrorNumber =
    curl_errno($curl);

$curlError =
    curl_error($curl);

$httpCode =
    (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

$contentType =
    curl_getinfo(
        $curl,
        CURLINFO_CONTENT_TYPE
    );

curl_close($curl);


/*
|--------------------------------------------------------------------------
| cURL failure
|--------------------------------------------------------------------------
*/

if ($response === false) {

    ocr_response(
        false,
        'Unable to connect to the OCR service.',
        [
            'curl_error' =>
                $curlError,

            'curl_error_number' =>
                $curlErrorNumber,

            'http_code' =>
                $httpCode
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Empty response
|--------------------------------------------------------------------------
*/

if (trim($response) === '') {

    ocr_response(
        false,
        'OCR service returned an empty response.',
        [
            'http_code' =>
                $httpCode
        ]
    );
}


/*
|--------------------------------------------------------------------------
| JSON response
|--------------------------------------------------------------------------
*/

$json = json_decode(
    $response,
    true
);


/*
|--------------------------------------------------------------------------
| Non-JSON response
|--------------------------------------------------------------------------
|
| This catches the exact problem you previously showed:
| the provider returned HTML with HTTP 200.
|
*/

if (!is_array($json)) {

    $preview =
        trim(
            preg_replace(
                '/\s+/',
                ' ',
                strip_tags($response)
            )
        );

    if (strlen($preview) > 500) {
        $preview =
            substr($preview, 0, 500) . '...';
    }

    ocr_response(
        false,
        'OCR service returned a non-JSON response.',
        [
            'http_code' =>
                $httpCode,

            'content_type' =>
                $contentType,

            'response_preview' =>
                $preview
        ]
    );
}


/*
|--------------------------------------------------------------------------
| OCR.Space-style response
|--------------------------------------------------------------------------
*/

if (
    isset($json['IsErroredOnProcessing']) &&
    $json['IsErroredOnProcessing']
) {

    $errorMessage =
        'OCR processing failed.';

    if (
        isset($json['ErrorMessage']) &&
        is_array($json['ErrorMessage'])
    ) {

        $errorMessage =
            implode(
                ' ',
                $json['ErrorMessage']
            );
    }

    ocr_response(
        false,
        $errorMessage,
        [
            'provider_response' =>
                $json
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Extract text
|--------------------------------------------------------------------------
*/

$extractedText = '';


if (
    isset($json['ParsedResults']) &&
    is_array($json['ParsedResults'])
) {

    foreach (
        $json['ParsedResults']
        as $result
    ) {

        if (
            isset($result['ParsedText'])
        ) {

            $extractedText .=
                $result['ParsedText'] .
                "\n";
        }
    }
}


$extractedText =
    trim($extractedText);


/*
|--------------------------------------------------------------------------
| No text
|--------------------------------------------------------------------------
*/

if ($extractedText === '') {

    ocr_response(
        false,
        'The image was processed, but no readable text was detected.',
        [
            'provider_response' =>
                $json
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

ocr_response(
    true,
    'OCR completed successfully.',
    [
        'text' =>
            $extractedText,

        'http_code' =>
            $httpCode
    ]
);