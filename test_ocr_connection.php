<?php

echo '<pre>';

echo "PHP version: ";
echo PHP_VERSION;
echo "\n\n";

echo "cURL: ";
echo function_exists('curl_init')
    ? 'ENABLED'
    : 'DISABLED';

echo "\n\n";

echo "OpenSSL: ";
echo extension_loaded('openssl')
    ? 'ENABLED'
    : 'DISABLED';

echo "\n\n";

if (function_exists('curl_init')) {

    $ch = curl_init(
        'https://api.ocr.space/'
    );

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]
    );

    $response = curl_exec($ch);

    echo "cURL error number: ";
    echo curl_errno($ch);

    echo "\n\n";

    echo "cURL error: ";
    echo curl_error($ch);

    echo "\n\n";

    echo "HTTP code: ";
    echo curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    echo "\n\n";

    echo "Response:\n";
    echo htmlspecialchars(
        (string)$response
    );

    curl_close($ch);
}

echo '</pre>';