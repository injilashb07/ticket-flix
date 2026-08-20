<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';


function sendBookingConfirmationEmail(
    $userEmail,
    $movieName,
    $showDate,
    $showTime,
    $theaterName,
    $screenName,
    $selectedSeats,
    $totalAmount,
    $bookingReference
) {

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

        $mail->Username = 'ticketflix40@gmail.com';

        /*
        |--------------------------------------------------------------------------
        | GMAIL APP PASSWORD
        |--------------------------------------------------------------------------
        |
        | Put your 16-character Gmail App Password here.
        | Do NOT use your normal Gmail password.
        |
        */

        $mail->Password = 'zkso vpca dspg krtl';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;


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
            $userEmail
        );


        /*
        |--------------------------------------------------------------------------
        | EMAIL FORMAT
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);


        $mail->Subject =
            'Booking Confirmed - ' .
            $movieName;


        /*
        |--------------------------------------------------------------------------
        | SEATS
        |--------------------------------------------------------------------------
        */

        $seatList = '';

        foreach ($selectedSeats as $seat) {

            $seatName =
                $seat['seat_row'] .
                $seat['seat_number'];

            $seatList .=
                '<span style="
                    display:inline-block;
                    background:#f4c430;
                    color:#24102e;
                    padding:7px 11px;
                    margin:3px;
                    border-radius:6px;
                    font-weight:bold;
                ">' .
                htmlspecialchars($seatName) .
                '</span>';

        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL BODY
        |--------------------------------------------------------------------------
        */

        $mail->Body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

body {

    margin:0;

    padding:0;

    background:#f5f2f8;

    font-family:Arial, Helvetica, sans-serif;

}

.container {

    width:90%;

    max-width:650px;

    margin:30px auto;

    background:#ffffff;

    border-radius:15px;

    overflow:hidden;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.12);

}

.header {

    background:
        linear-gradient(
            135deg,
            #351650,
            #1c0e2c
        );

    color:white;

    text-align:center;

    padding:28px 20px;

}

.header h1 {

    margin:0;

    font-size:30px;

}

.header span {

    color:#f4c430;

}

.header p {

    margin:8px 0 0;

    color:#d0c8d7;

}

.content {

    padding:30px;

    color:#333333;

}

.success {

    color:#5b2c83;

    font-size:21px;

    font-weight:bold;

}

.details {

    margin-top:22px;

    padding:20px;

    background:#faf7fc;

    border-left:
        5px solid #f4c430;

    border-radius:9px;

}

.details p {

    margin:11px 0;

}

.label {

    font-weight:bold;

    color:#5b2c83;

}

.reference {

    font-size:19px;

    font-weight:bold;

    color:#d4af37;

}

.seats {

    margin-top:18px;

}

.total {

    margin-top:20px;

    padding:15px;

    background:#351650;

    color:white;

    border-radius:8px;

    text-align:center;

}

.total strong {

    color:#f4c430;

    font-size:22px;

}

.footer {

    background:#f1edf5;

    padding:20px;

    text-align:center;

    color:#666666;

    font-size:13px;

}

</style>

</head>


<body>


<div class="container">


    <div class="header">

        <h1>
            🎬 Ticket<span>Flix</span>
        </h1>

        <p>
            Movie Ticket Booking
        </p>

    </div>


    <div class="content">


        <p>
            Hi,
        </p>


        <p class="success">

            🎉 Your booking is confirmed!

        </p>


        <p>

            Your payment was successful and your
            movie tickets have been booked successfully.

        </p>


        <div class="details">


            <p>

                <span class="label">
                    Booking Reference:
                </span>

                <span class="reference">

                    ' .
                    htmlspecialchars(
                        $bookingReference
                    ) .
                    '

                </span>

            </p>


            <p>

                <span class="label">
                    Movie:
                </span>

                ' .
                htmlspecialchars(
                    $movieName
                ) .
                '

            </p>


            <p>

                <span class="label">
                    Theater:
                </span>

                ' .
                htmlspecialchars(
                    $theaterName
                ) .
                '

            </p>


            <p>

                <span class="label">
                    Screen:
                </span>

                ' .
                htmlspecialchars(
                    $screenName
                ) .
                '

            </p>


            <p>

                <span class="label">
                    Date:
                </span>

                ' .
                htmlspecialchars(
                    $showDate
                ) .
                '

            </p>


            <p>

                <span class="label">
                    Time:
                </span>

                ' .
                htmlspecialchars(
                    $showTime
                ) .
                '

            </p>


            <div class="seats">

                <p>

                    <span class="label">
                        Selected Seats:
                    </span>

                </p>

                ' .
                $seatList .
                '

            </div>


        </div>


        <div class="total">

            Total Amount:

            <strong>

                ₹' .
                number_format(
                    (float)$totalAmount,
                    2
                ) .
                '

            </strong>

        </div>


        <p style="margin-top:25px;">

            🍿 Enjoy the show!

        </p>


        <p>

            Thank you for booking with
            <strong>TicketFlix</strong>.

        </p>


        <p>

            — TicketFlix Team

        </p>


    </div>


    <div class="footer">

        This is an automated confirmation email.
        Please do not reply to this email.

    </div>


</div>


</body>

</html>

';


        /*
        |--------------------------------------------------------------------------
        | PLAIN TEXT EMAIL
        |--------------------------------------------------------------------------
        */

        $plainSeats = '';

        foreach ($selectedSeats as $seat) {

            $plainSeats .=
                $seat['seat_row'] .
                $seat['seat_number'] .
                ' ';

        }


        $mail->AltBody =
            "TicketFlix Booking Confirmation\n\n" .

            "Your booking is confirmed!\n\n" .

            "Booking Reference: " .
            $bookingReference .
            "\n" .

            "Movie: " .
            $movieName .
            "\n" .

            "Theater: " .
            $theaterName .
            "\n" .

            "Screen: " .
            $screenName .
            "\n" .

            "Date: " .
            $showDate .
            "\n" .

            "Time: " .
            $showTime .
            "\n" .

            "Seats: " .
            $plainSeats .
            "\n" .

            "Total Amount: ₹" .
            number_format(
                (float)$totalAmount,
                2
            ) .
            "\n\n" .

            "Enjoy the show!\n\n" .

            "Thank you for booking with TicketFlix.";


        /*
        |--------------------------------------------------------------------------
        | SEND EMAIL
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;


    } catch (Exception $e) {

        error_log(
            "TicketFlix Mail Error: " .
            $mail->ErrorInfo
        );

        return false;

    }

}