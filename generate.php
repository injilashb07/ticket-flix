//generate.php

<?php
/**
 * generate.php
 * QR Code generator for TicketFlix Booking Number
 * Works with chillerlan/php-qrcode
 */

require_once __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/* =========================================================
   GET BOOKING NUMBER
   Example:
   generate.php?booking_no=TF20260823A12345
========================================================= */

$bookingNo = $_GET['booking_no'] ?? '';

$bookingNo = trim($bookingNo);

/* =========================================================
   VALIDATE BOOKING NUMBER
========================================================= */

if ($bookingNo === '') {
    die("
        <div style='
            font-family: Arial;
            text-align: center;
            margin-top: 80px;
        '>
            <h2 style='color:red;'>Booking Number Missing</h2>
            <p>Please provide a booking number.</p>
            <p>
                Example:
                <br>
                <b>generate.php?booking_no=TF20260823A12345</b>
            </p>
        </div>
    ");
}

/* =========================================================
   QR CONTENT
========================================================= */

/*
   This is what will be stored inside the QR code.

   Example:
   BOOKING NO: TF20260823A12345
*/

$qrContent = "BOOKING NO: " . $bookingNo;

/* =========================================================
   QR OPTIONS
========================================================= */

$options = new QROptions;

$options->outputType = 'png';
$options->scale = 10;
$options->eccLevel = 'L';

/* Optional colors */
// $options->bgColor = [255, 255, 255];
// $options->fgColor = [0, 0, 0];

/* =========================================================
   GENERATE QR
========================================================= */

$qrCode = new QRCode($options);

$base64DataUri = $qrCode->render($qrContent);

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>TicketFlix Booking QR</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
            background: #f5f5f5;
        }

        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }

        h1 {
            color: #6f42c1;
            margin-bottom: 10px;
        }

        .booking-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }

        .booking-number {
            font-size: 22px;
            font-weight: bold;
            color: #d4af37;
            margin-bottom: 20px;
        }

        img {
            border: 2px solid #ddd;
            padding: 10px;
            background: white;
            max-width: 100%;
        }

        .scan-text {
            margin-top: 20px;
            color: #555;
            font-size: 14px;
        }

        .footer {
            margin-top: 20px;
            color: #888;
            font-size: 13px;
        }

    </style>

</head>

<body>

    <div class="container">

        <h1>🎟️ TicketFlix</h1>

        <div class="booking-label">
            Booking Number
        </div>

        <div class="booking-number">
            <?php echo htmlspecialchars($bookingNo); ?>
        </div>

        <img
            src="<?php echo htmlspecialchars($base64DataUri); ?>"
            alt="QR Code for Booking <?php echo htmlspecialchars($bookingNo); ?>"
        >

        <p class="scan-text">
            Scan this QR code to verify your booking number.
        </p>

        <p class="footer">
            TicketFlix • Your Movie Ticket
        </p>

    </div>

</body>

</html>