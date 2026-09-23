<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Swasthya Saarathi OCR Configuration
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Keep your API key on the server.
| Do NOT put it inside ocr.js or HTML.
|
*/

const OCR_API_KEY = 'K88228591688957';

/*
 * Replace this with the actual API endpoint supplied by
 * the provider that issued your key.
 *
 * Your previous endpoint appears to be returning an HTML
 * informational page instead of the OCR JSON response.
 */
const OCR_API_URL = 'https://api.ocr.space/parse/image';