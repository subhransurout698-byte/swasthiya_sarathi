<?php

/**
 * ============================================================================
 * Swasthya Saarathi
 * Medical OCR Helper
 * ============================================================================
 *
 * Uses:
 *   - Tesseract OCR through WSL/Ubuntu
 *   - pdftoppm through WSL/Ubuntu
 *
 * Supported:
 *   - PDF
 *   - JPG
 *   - JPEG
 *   - PNG
 *
 * IMPORTANT:
 * This helper only transcribes text from the uploaded document.
 * It does NOT diagnose, prescribe, or medically interpret the document.
 * ============================================================================
 */

if (!function_exists('run_medical_ocr')) {

    /**
     * Run OCR against a medical document.
     *
     * @param string $filePath
     * @param string $extension
     *
     * @return array
     *
     * @throws Exception
     */
    function run_medical_ocr(
        string $filePath,
        string $extension
    ): array {

        $extension = strtolower(
            trim($extension)
        );

        $allowedExtensions = [
            'pdf',
            'jpg',
            'jpeg',
            'png'
        ];

        /*
        |--------------------------------------------------------------------------
        | Validate extension
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {

            throw new Exception(
                'Only PDF, JPG, JPEG and PNG medical documents are allowed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate file
        |--------------------------------------------------------------------------
        */

        if (!file_exists($filePath)) {

            throw new Exception(
                'Uploaded medical document could not be found.'
            );
        }

        if (!is_file($filePath)) {

            throw new Exception(
                'Uploaded medical document is not a valid file.'
            );
        }

        if (!is_readable($filePath)) {

            throw new Exception(
                'Uploaded medical document cannot be read by the OCR system.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Temporary directory
        |--------------------------------------------------------------------------
        */

        $tempDirectory =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'swasthya_ocr_'
            . bin2hex(
                random_bytes(8)
            );

        if (
            !mkdir(
                $tempDirectory,
                0777,
                true
            )
        ) {

            throw new Exception(
                'Could not create temporary OCR directory.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Result defaults
        |--------------------------------------------------------------------------
        */

        $result = [

            'text' => '',

            'prescription' =>
                'No medication-related text could be automatically identified. Please review the original document.',

            'pages' => [],

            'page_count' => 0,

            'document_type' =>
                strtoupper($extension),

            'ocr_engine' =>
                'Tesseract OCR',

            'ocr_status' =>
                'pending',

            'warnings' => []

        ];

        try {

            /*
            |--------------------------------------------------------------------------
            | PDF
            |--------------------------------------------------------------------------
            */

            if ($extension === 'pdf') {

                $result = process_medical_pdf(
                    $filePath,
                    $tempDirectory,
                    $result
                );

            }

            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            else {

                $result = process_medical_image(
                    $filePath,
                    $extension,
                    $result
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Clean final OCR text
            |--------------------------------------------------------------------------
            */

            $result['text'] =
                clean_ocr_text(
                    $result['text']
                );

            /*
            |--------------------------------------------------------------------------
            | Prescription / medication extraction
            |--------------------------------------------------------------------------
            */

            $result['prescription'] =
                extract_prescription_text(
                    $result['text']
                );

            /*
            |--------------------------------------------------------------------------
            | OCR status
            |--------------------------------------------------------------------------
            */

            if (
                trim($result['text']) === ''
            ) {

                $result['ocr_status'] =
                    'completed_no_text';

                $result['warnings'][] =
                    'OCR completed but no readable text was detected. Please review the original document manually.';

            } else {

                $result['ocr_status'] =
                    'completed';
            }

            /*
            |--------------------------------------------------------------------------
            | Remove duplicate warnings
            |--------------------------------------------------------------------------
            */

            $result['warnings'] =
                array_values(
                    array_unique(
                        array_filter(
                            $result['warnings']
                        )
                    )
                );

            return $result;

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Preserve original exception
            |--------------------------------------------------------------------------
            */

            throw new Exception(
                'Medical OCR failed: '
                . $e->getMessage(),
                0,
                $e
            );

        } finally {

            /*
            |--------------------------------------------------------------------------
            | Always remove temporary files
            |--------------------------------------------------------------------------
            */

            delete_directory(
                $tempDirectory
            );
        }
    }


    /**
     * =========================================================================
     * Process PDF
     * =========================================================================
     */
    function process_medical_pdf(
        string $filePath,
        string $tempDirectory,
        array $result
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Convert Windows path to WSL path
        |--------------------------------------------------------------------------
        */

        $pdfWslPath =
            windows_path_to_wsl(
                $filePath
            );

        $outputPrefix =
            $tempDirectory
            . DIRECTORY_SEPARATOR
            . 'page';

        $outputPrefixWsl =
            windows_path_to_wsl(
                $outputPrefix
            );

        /*
        |--------------------------------------------------------------------------
        | Convert PDF pages to PNG
        |--------------------------------------------------------------------------
        |
        | -png          = PNG output
        | -r 200        = 200 DPI
        |
        */

        $command =
            'wsl.exe pdftoppm'
            . ' -png'
            . ' -r 200'
            . ' '
            . escapeshellarg(
                $pdfWslPath
            )
            . ' '
            . escapeshellarg(
                $outputPrefixWsl
            )
            . ' 2>&1';

        $output = [];

        $returnCode = 0;

        exec(
            $command,
            $output,
            $returnCode
        );

        if ($returnCode !== 0) {

            throw new Exception(
                "PDF conversion failed:\n"
                . implode(
                    "\n",
                    $output
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find generated pages
        |--------------------------------------------------------------------------
        */

        $pages =
            glob(
                $tempDirectory
                . DIRECTORY_SEPARATOR
                . 'page-*.png'
            );

        if (!$pages) {

            throw new Exception(
                'No pages could be extracted from the PDF.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Natural page sorting
        |--------------------------------------------------------------------------
        */

        sort(
            $pages,
            SORT_NATURAL
        );

        /*
        |--------------------------------------------------------------------------
        | Page count
        |--------------------------------------------------------------------------
        */

        $result['page_count'] =
            count($pages);

        /*
        |--------------------------------------------------------------------------
        | OCR each page
        |--------------------------------------------------------------------------
        */

        $allText = [];

        foreach (
            $pages as $index => $page
        ) {

            $pageNumber =
                $index + 1;

            try {

                $pageText =
                    run_tesseract(
                        $page
                    );

                $pageText =
                    clean_ocr_text(
                        $pageText
                    );

                /*
                |--------------------------------------------------------------------------
                | Store page-level OCR
                |--------------------------------------------------------------------------
                */

                $result['pages'][] = [

                    'page_number' =>
                        $pageNumber,

                    'text' =>
                        $pageText,

                    'status' =>
                        trim($pageText) !== ''
                            ? 'completed'
                            : 'no_text'
                ];

                /*
                |--------------------------------------------------------------------------
                | Preserve page separation
                |--------------------------------------------------------------------------
                */

                if (
                    trim($pageText) !== ''
                ) {

                    $allText[] =
                        "==============================\n"
                        . "PAGE "
                        . $pageNumber
                        . "\n"
                        . "==============================\n"
                        . $pageText;
                }

                else {

                    $result['warnings'][] =
                        'No readable text was detected on PDF page '
                        . $pageNumber
                        . '.';
                }

            } catch (Throwable $pageError) {

                /*
                |--------------------------------------------------------------------------
                | Do not destroy OCR from other pages
                |--------------------------------------------------------------------------
                */

                $result['pages'][] = [

                    'page_number' =>
                        $pageNumber,

                    'text' => '',

                    'status' =>
                        'failed',

                    'error' =>
                        $pageError->getMessage()
                ];

                $result['warnings'][] =
                    'OCR failed on PDF page '
                    . $pageNumber
                    . ': '
                    . $pageError->getMessage();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Combine all pages
        |--------------------------------------------------------------------------
        */

        $result['text'] =
            implode(
                "\n\n",
                $allText
            );

        return $result;
    }


    /**
     * =========================================================================
     * Process JPG / JPEG / PNG
     * =========================================================================
     */
    function process_medical_image(
        string $filePath,
        string $extension,
        array $result
    ): array {

        $text =
            run_tesseract(
                $filePath
            );

        $text =
            clean_ocr_text(
                $text
            );

        $result['page_count'] =
            1;

        $result['pages'][] = [

            'page_number' =>
                1,

            'text' =>
                $text,

            'status' =>
                trim($text) !== ''
                    ? 'completed'
                    : 'no_text'
        ];

        $result['text'] =
            $text;

        if (
            trim($text) === ''
        ) {

            $result['warnings'][] =
                'No readable text was detected in the uploaded image.';
        }

        return $result;
    }


    /**
     * =========================================================================
     * Windows path -> WSL path
     * =========================================================================
     *
     * Example:
     *
     * C:\xampp\htdocs\swasthya\uploads\file.pdf
     *
     * becomes:
     *
     * /mnt/c/xampp/htdocs/swasthya/uploads/file.pdf
     */
    function windows_path_to_wsl(
        string $path
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Normalize separators
        |--------------------------------------------------------------------------
        */

        $path =
            str_replace(
                '\\',
                '/',
                $path
            );

        /*
        |--------------------------------------------------------------------------
        | Remove duplicate slashes
        |--------------------------------------------------------------------------
        */

        $path =
            preg_replace(
                '#/+#',
                '/',
                $path
            );

        /*
        |--------------------------------------------------------------------------
        | Windows drive
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/^([A-Za-z]):\/?(.*)$/',
                $path,
                $matches
            )
        ) {

            $drive =
                strtolower(
                    $matches[1]
                );

            $rest =
                ltrim(
                    $matches[2],
                    '/'
                );

            return
                '/mnt/'
                . $drive
                . '/'
                . $rest;
        }

        /*
        |--------------------------------------------------------------------------
        | Already Linux / WSL path
        |--------------------------------------------------------------------------
        */

        return $path;
    }


    /**
     * =========================================================================
     * Run Tesseract
     * =========================================================================
     */
    function run_tesseract(
        string $filePath
    ): string {

        if (
            !file_exists($filePath)
        ) {

            throw new Exception(
                'OCR image file could not be found.'
            );
        }

        $wslPath =
            windows_path_to_wsl(
                $filePath
            );

        /*
        |--------------------------------------------------------------------------
        | Tesseract command
        |--------------------------------------------------------------------------
        |
        | --psm 3 = automatic page segmentation
        |
        */

        $command =
            'wsl.exe tesseract '
            . escapeshellarg(
                $wslPath
            )
            . ' stdout'
            . ' --psm 3'
            . ' 2>&1';

        $output = [];

        $returnCode = 0;

        exec(
            $command,
            $output,
            $returnCode
        );

        if ($returnCode !== 0) {

            throw new Exception(
                "Tesseract OCR failed:\n"
                . implode(
                    "\n",
                    $output
                )
            );
        }

        return implode(
            "\n",
            $output
        );
    }


    /**
     * =========================================================================
     * Clean OCR text
     * =========================================================================
     */
    function clean_ocr_text(
        string $text
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Remove NULL characters
        |--------------------------------------------------------------------------
        */

        $text =
            str_replace(
                "\0",
                '',
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Normalize line endings
        |--------------------------------------------------------------------------
        */

        $text =
            str_replace(
                [
                    "\r\n",
                    "\r"
                ],
                "\n",
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Remove tabs / excessive spaces
        |--------------------------------------------------------------------------
        */

        $text =
            preg_replace(
                '/[ \t]+/',
                ' ',
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Remove spaces from empty lines
        |--------------------------------------------------------------------------
        */

        $text =
            preg_replace(
                '/^[ \t]+$/m',
                '',
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Limit excessive blank lines
        |--------------------------------------------------------------------------
        */

        $text =
            preg_replace(
                "/\n{3,}/",
                "\n\n",
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Remove excessive whitespace at beginning/end
        |--------------------------------------------------------------------------
        */

        return trim(
            $text
        );
    }


    /**
     * =========================================================================
     * Extract prescription / medication related OCR lines
     * =========================================================================
     *
     * IMPORTANT:
     *
     * This function DOES NOT determine what medicine a patient should take.
     *
     * It only finds OCR lines that contain words commonly appearing in
     * prescriptions or medication documents.
     */
    function extract_prescription_text(
        string $text
    ): string {

        if (
            trim($text) === ''
        ) {

            return
                'No medication-related text could be automatically identified. Please review the original document.';
        }

        /*
        |--------------------------------------------------------------------------
        | Split into lines
        |--------------------------------------------------------------------------
        */

        $lines =
            preg_split(
                "/\r\n|\r|\n/",
                $text
            );

        /*
        |--------------------------------------------------------------------------
        | Keywords
        |--------------------------------------------------------------------------
        */

        $keywords = [

            /*
            | Medication forms
            */

            'tablet',
            'tablets',
            'tab',
            'capsule',
            'capsules',
            'cap',
            'syrup',
            'suspension',
            'solution',
            'injection',
            'inj',
            'cream',
            'ointment',
            'gel',
            'drops',
            'drop',
            'spray',
            'powder',
            'lotion',

            /*
            | Strength / units
            */

            'mg',
            'mcg',
            'g',
            'ml',
            'iu',
            'units',

            /*
            | Prescription wording
            */

            'dose',
            'dosage',
            'medicine',
            'medication',
            'prescription',
            'rx',
            'drug',

            /*
            | Frequency
            */

            'morning',
            'afternoon',
            'evening',
            'night',
            'daily',
            'once',
            'twice',
            'thrice',

            /*
            | Medical shorthand
            */

            'od',
            'bd',
            'bid',
            'tds',
            'tid',
            'qid',
            'hs',
            'sos',
            'stat',

            /*
            | Food instructions
            */

            'before food',
            'after food',
            'with food',
            'empty stomach',

            /*
            | Common prescription instructions
            */

            'for days',
            'for day',
            'continue',
            'discontinue',
            'take',
            'apply',
            'inhale',
            'instill'
        ];

        $matches = [];

        foreach (
            $lines as $line
        ) {

            $line =
                trim($line);

            if (
                $line === ''
            ) {
                continue;
            }

            $lower =
                strtolower(
                    $line
                );

            foreach (
                $keywords as $keyword
            ) {

                if (
                    strpos(
                        $lower,
                        strtolower($keyword)
                    ) !== false
                ) {

                    $matches[] =
                        $line;

                    break;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Remove duplicates
        |--------------------------------------------------------------------------
        */

        $matches =
            array_values(
                array_unique(
                    $matches
                )
            );

        /*
        |--------------------------------------------------------------------------
        | No matches
        |--------------------------------------------------------------------------
        */

        if (
            !$matches
        ) {

            return
                'No medication-related text could be automatically identified. Please review the original document.';
        }

        /*
        |--------------------------------------------------------------------------
        | Return extracted lines
        |--------------------------------------------------------------------------
        */

        return implode(
            "\n",
            $matches
        );
    }


    /**
     * =========================================================================
     * Delete temporary directory recursively
     * =========================================================================
     */
    function delete_directory(
        string $directory
    ): void {

        if (
            !is_dir($directory)
        ) {
            return;
        }

        $items =
            scandir(
                $directory
            );

        if (
            $items === false
        ) {
            return;
        }

        foreach (
            $items as $item
        ) {

            if (
                $item === '.'
                || $item === '..'
            ) {
                continue;
            }

            $path =
                $directory
                . DIRECTORY_SEPARATOR
                . $item;

            if (
                is_dir($path)
            ) {

                delete_directory(
                    $path
                );

            } else {

                @unlink(
                    $path
                );
            }
        }

        @rmdir(
            $directory
        );
    }
}