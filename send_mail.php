<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;


/*
|--------------------------------------------------------------------------
| LOAD COMPOSER
|--------------------------------------------------------------------------
| Your project should have:
|
| Ticket Flix/
| ├── vendor/
| ├── payment.php
| ├── booking.php
| ├── send_mail.php
| └── config.php
|
*/

require_once __DIR__ . '/vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| SEND BOOKING CONFIRMATION EMAIL
|--------------------------------------------------------------------------
*/

function sendBookingConfirmationEmail(
    $toEmail,
    $movieName,
    $showDate,
    $showTime,
    $theaterName,
    $screenName,
    $selectedSeats,
    $totalAmount,
    $bookingReference,
    $transactionId = ''
) {

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($toEmail) ||
        empty($bookingReference)
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE QR CODE
    |--------------------------------------------------------------------------
    |
    | QR contains the booking number/reference.
    |
    | Example:
    |
    | TF20260822CD312C5E
    |
    */

    $qrData = $bookingReference;


    /*
    |--------------------------------------------------------------------------
    | QR CODE OPTIONS
    |--------------------------------------------------------------------------
    */

    $options = new QROptions();

    /*
     * PNG output
     */
    $options->outputType = 'png';

    /*
     * QR size
     */
    $options->scale = 8;

    /*
     * Error correction
     */
    $options->eccLevel = 'M';


    /*
    |--------------------------------------------------------------------------
    | CREATE TEMPORARY QR FILE
    |--------------------------------------------------------------------------
    */

    $qrFile = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'ticketflix_qr_'
        . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            $bookingReference
        )
        . '.png';


    try {

        /*
        |--------------------------------------------------------------------------
        | GENERATE QR
        |--------------------------------------------------------------------------
        */

        $qrCode = new QRCode($options);

        $qrCode->render(
            $qrData,
            $qrFile
        );


        /*
        |--------------------------------------------------------------------------
        | CHECK QR FILE
        |--------------------------------------------------------------------------
        */

        if (
            !file_exists($qrFile) ||
            filesize($qrFile) <= 0
        ) {

            return false;

        }


    } catch (Throwable $e) {

        error_log(
            "TicketFlix QR Error: "
            . $e->getMessage()
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PHPMailer
    |--------------------------------------------------------------------------
    */

    $mail = new PHPMailer(true);


    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP SETTINGS
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        /*
         * YOUR GMAIL
         */
        $mail->Username = 'ticketflix40@gmail.com';


        /*
         * YOUR GMAIL APP PASSWORD
         *
         * IMPORTANT:
         * Use 16-character Gmail App Password.
         *
         * Example:
         *
         * abcd efgh ijkl mnop
         *
         * Remove spaces.
         */

        $mail->Password = 'whus euir nvbo tbbu';


        /*
         * Encryption
         */

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;


        /*
        |--------------------------------------------------------------------------
        | EMAIL CHARACTER SET
        |--------------------------------------------------------------------------
        */

        $mail->CharSet = 'UTF-8';


        /*
        |--------------------------------------------------------------------------
        | SENDER
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            'ticketflix40@gmail.com',
            'TicketFlix'
        );


        /*
        |--------------------------------------------------------------------------
        | RECEIVER
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $toEmail
        );


        /*
        |--------------------------------------------------------------------------
        | REPLY TO
        |--------------------------------------------------------------------------
        */

        $mail->addReplyTo(
            'ticketflix40@gmail.com',
            'TicketFlix Support'
        );


        /*
        |--------------------------------------------------------------------------
        | SUBJECT
        |--------------------------------------------------------------------------
        */

        $mail->Subject =
            'TicketFlix Booking Confirmation - '
            . $bookingReference;


        /*
        |--------------------------------------------------------------------------
        | SEAT LABELS
        |--------------------------------------------------------------------------
        */

        $seatLabels = [];


        foreach ($selectedSeats as $seat) {

            if (
                isset($seat['seat_row']) &&
                isset($seat['seat_number'])
            ) {

                $seatLabels[] =
                    $seat['seat_row']
                    . $seat['seat_number'];

            }

        }


        $seatText = !empty($seatLabels)
            ? implode(', ', $seatLabels)
            : 'N/A';


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION ID TEXT
        |--------------------------------------------------------------------------
        */

        if (!empty($transactionId)) {

            $transactionHtml = '
                <tr>
                    <td style="
                        padding:10px;
                        border-bottom:1px solid #eeeeee;
                        color:#777777;
                    ">
                        Transaction ID
                    </td>

                    <td style="
                        padding:10px;
                        border-bottom:1px solid #eeeeee;
                        font-weight:bold;
                    ">
                        ' . htmlspecialchars(
                            $transactionId
                        ) . '
                    </td>
                </tr>
            ';

        } else {

            $transactionHtml = '';

        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL BODY
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);


        $mail->Body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
    TicketFlix Booking Confirmation
</title>

</head>


<body style="
    margin:0;
    padding:0;
    background:#f4f1f7;
    font-family:Arial,Helvetica,sans-serif;
">


<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        background:#f4f1f7;
        padding:30px 10px;
    "
>

<tr>

<td align="center">


<table
    width="600"
    cellpadding="0"
    cellspacing="0"
    style="
        max-width:600px;
        width:100%;
        background:#ffffff;
        border-radius:15px;
        overflow:hidden;
        box-shadow:0 5px 20px rgba(0,0,0,0.10);
    "
>


<!-- HEADER -->

<tr>

<td
    style="
        background:#321452;
        padding:28px;
        text-align:center;
    "
>

<h1
    style="
        margin:0;
        color:#ffffff;
        font-size:30px;
    "
>

Ticket<span style="color:#f4c430;">
Flix
</span>

</h1>

<p
    style="
        margin:8px 0 0;
        color:#dddddd;
        font-size:14px;
    "
>

Movie Ticket Booking Confirmation

</p>

</td>

</tr>


<!-- CONTENT -->

<tr>

<td
    style="
        padding:30px;
        color:#333333;
    "
>


<h2
    style="
        color:#321452;
        margin-top:0;
    "
>

🎉 Booking Confirmed!

</h2>


<p
    style="
        font-size:15px;
        line-height:1.6;
    "
>

Your TicketFlix movie ticket has been
successfully booked.

</p>


<!-- BOOKING NUMBER -->

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        background:#f7f1fb;
        border:1px solid #e3d4ef;
        border-radius:10px;
        margin:20px 0;
    "
>

<tr>

<td
    style="
        padding:18px;
        text-align:center;
    "
>

<div
    style="
        color:#777777;
        font-size:12px;
        margin-bottom:7px;
    "
>

BOOKING NUMBER

</div>


<div
    style="
        color:#321452;
        font-size:24px;
        font-weight:bold;
        letter-spacing:2px;
    "
>

' . htmlspecialchars(
    $bookingReference
) . '

</div>

</td>

</tr>

</table>


<!-- DETAILS -->

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        border-collapse:collapse;
        font-size:14px;
    "
>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Movie

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $movieName
) . '

</td>

</tr>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Theater

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $theaterName
) . '

</td>

</tr>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Screen

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $screenName
) . '

</td>

</tr>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Date

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $showDate
) . '

</td>

</tr>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Time

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $showTime
) . '

</td>

</tr>


<tr>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        color:#777777;
    "
>

Seats

</td>

<td
    style="
        padding:10px;
        border-bottom:1px solid #eeeeee;
        font-weight:bold;
    "
>

' . htmlspecialchars(
    $seatText
) . '

</td>

</tr>


' . $transactionHtml . '


<tr>

<td
    style="
        padding:12px 10px;
        color:#777777;
    "
>

Total Paid

</td>

<td
    style="
        padding:12px 10px;
        color:#321452;
        font-size:18px;
        font-weight:bold;
    "
>

₹' . number_format(
    (float)$totalAmount,
    2
) . '

</td>

</tr>


</table>


<!-- QR -->

<div
    style="
        margin-top:30px;
        text-align:center;
        padding:20px;
        background:#fafafa;
        border-radius:12px;
    "
>

<h3
    style="
        margin-top:0;
        color:#321452;
    "
>

🎟️ Your Booking QR Code

</h3>


<p
    style="
        color:#777777;
        font-size:13px;
    "
>

Show this QR code at the theater.

</p>


<img
    src="cid:ticketflixqr"
    alt="TicketFlix Booking QR Code"
    width="220"
    style="
        display:block;
        margin:15px auto;
        border:8px solid #ffffff;
    "
>


<p
    style="
        color:#321452;
        font-weight:bold;
        margin-bottom:0;
    "
>

' . htmlspecialchars(
    $bookingReference
) . '

</p>

</div>


<!-- FOOTER -->

<p
    style="
        margin-top:30px;
        color:#777777;
        font-size:13px;
        line-height:1.6;
    "
>

Thank you for booking with
<strong>TicketFlix</strong> 🎬🍿

<br>

Please keep this email and QR code safe
until you enter the theater.

</p>


</td>

</tr>


<!-- FOOTER -->

<tr>

<td
    style="
        background:#321452;
        padding:18px;
        text-align:center;
        color:#dddddd;
        font-size:12px;
    "
>

© ' . date('Y') . ' TicketFlix.
All rights reserved.

</td>

</tr>


</table>

</td>

</tr>

</table>


</body>

</html>
';


        /*
        |--------------------------------------------------------------------------
        | ALT TEXT
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =
            "TicketFlix Booking Confirmation\n\n"
            . "Booking Number: "
            . $bookingReference
            . "\n"
            . "Movie: "
            . $movieName
            . "\n"
            . "Theater: "
            . $theaterName
            . "\n"
            . "Screen: "
            . $screenName
            . "\n"
            . "Date: "
            . $showDate
            . "\n"
            . "Time: "
            . $showTime
            . "\n"
            . "Seats: "
            . $seatText
            . "\n"
            . "Total Paid: ₹"
            . number_format(
                (float)$totalAmount,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | ATTACH QR CODE
        |--------------------------------------------------------------------------
        |
        | cid:ticketflixqr
        | is used by the HTML email to display
        | the QR image.
        |
        */

        $mail->addEmbeddedImage(
            $qrFile,
            'ticketflixqr',
            'ticketflix-booking-qr.png',
            'base64',
            'image/png'
        );


        /*
        |--------------------------------------------------------------------------
        | ALSO ATTACH QR AS DOWNLOADABLE FILE
        |--------------------------------------------------------------------------
        */

        $mail->addAttachment(
            $qrFile,
            'TicketFlix-' . $bookingReference . '-QR.png'
        );


        /*
        |--------------------------------------------------------------------------
        | SEND EMAIL
        |--------------------------------------------------------------------------
        */

        $mail->send();


        /*
        |--------------------------------------------------------------------------
        | DELETE TEMPORARY QR FILE
        |--------------------------------------------------------------------------
        */

        if (file_exists($qrFile)) {

            unlink($qrFile);

        }


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        return true;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | DELETE TEMP QR IF MAIL FAILED
        |--------------------------------------------------------------------------
        */

        if (file_exists($qrFile)) {

            unlink($qrFile);

        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ERROR TO PHP ERROR LOG
        |--------------------------------------------------------------------------
        */

        error_log(
            "TicketFlix Mail Error: "
            . $mail->ErrorInfo
        );


        return false;

    } catch (Throwable $e) {

        if (file_exists($qrFile)) {

            unlink($qrFile);

        }


        error_log(
            "TicketFlix General Mail Error: "
            . $e->getMessage()
        );


        return false;

    }

}

?>