<?php

declare(strict_types=1);

/**
 * Swasthya Saarathi
 *
 * Patient UID Generator
 *
 * Example:
 * SS-PAT-7F4K9M2Q
 */

if (!function_exists('generate_patient_uid')) {

    function generate_patient_uid(): string
    {
        $characters =
            'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $length =
            8;

        $random =
            '';

        for ($i = 0; $i < $length; $i++) {

            $random .=
                $characters[
                    random_int(
                        0,
                        strlen($characters) - 1
                    )
                ];
        }

        return 'SS-PAT-' . $random;
    }
}


/**
 * Generate a UID which does not already exist.
 */
if (!function_exists('generate_unique_patient_uid')) {

    function generate_unique_patient_uid(
        PDO $pdo
    ): string {

        for ($attempt = 0; $attempt < 20; $attempt++) {

            $uid =
                generate_patient_uid();

            $stmt =
                $pdo->prepare(
                    "SELECT id
                     FROM patients
                     WHERE patient_uid = ?
                     LIMIT 1"
                );

            $stmt->execute([
                $uid
            ]);

            if (!$stmt->fetch()) {

                return $uid;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique patient ID. Please try again.'
        );
    }
}