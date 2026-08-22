<?php

/**
 * generate_booking_qr.php
 *
 * Generates a QR code based on Booking Number
 *
 * Requires:
 * composer require chillerlan/php-qrcode
 */

require_once __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;


/**
 * Generate QR code PNG for booking number
 *
 * @param string $booking_number
 * @param string $file_path
 * @return bool
 */
function generateBookingQRCode(
    string $booking_number,
    string $file_path
): bool {

    try {

        /*
        |--------------------------------------------------------------------------
        | QR CONTENT
        |--------------------------------------------------------------------------
        | The QR will contain the booking number.
        |
        | Example:
        | 20260822153045123
        |
        */

        $qr_content = $booking_number;


        /*
        |--------------------------------------------------------------------------
        | QR OPTIONS
        |--------------------------------------------------------------------------
        */

        $options = new QROptions();

        $options->outputType = QRCode::OUTPUT_IMAGE_PNG;

        $options->eccLevel = QRCode::ECC_L;

        $options->scale = 8;


        /*
        |--------------------------------------------------------------------------
        | GENERATE QR
        |--------------------------------------------------------------------------
        */

        $qrCode = new QRCode($options);


        /*
        |--------------------------------------------------------------------------
        | SAVE QR IMAGE
        |--------------------------------------------------------------------------
        */

        $qrCode->render(
            $qr_content,
            $file_path
        );


        /*
        |--------------------------------------------------------------------------
        | CHECK FILE
        |--------------------------------------------------------------------------
        */

        return file_exists($file_path);

    }
    catch (Throwable $e) {

        error_log(
            "QR Generation Error: " .
            $e->getMessage()
        );

        return false;

    }

}