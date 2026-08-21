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

        // SMTP
        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'ticketflix40@gmail.com';

        // IMPORTANT:
        // Yaha apna NEW Gmail App Password daalo
        $mail->Password = 'wxkz veds hvdt mjhx';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;


        // Sender
        $mail->setFrom(
            'ticketflix40@gmail.com',
            'TicketFlix'
        );


        // Registered user's email
        $mail->addAddress($userEmail);


        $mail->isHTML(true);

        $mail->Subject =
            'Booking Confirmed - ' . $movieName;


        // Seat HTML
        $seatList = '';

        foreach ($selectedSeats as $seat) {

            $seatName =
                $seat['seat_row'] .
                $seat['seat_number'];

            $seatList .= '
                <span style="
                    display:inline-block;
                    background:#f4c430;
                    color:#24102e;
                    padding:7px 11px;
                    margin:3px;
                    border-radius:6px;
                    font-weight:bold;
                ">
                    ' .
                    htmlspecialchars($seatName) .
                '
                </span>
            ';
        }


        // Email body
        $mail->Body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

</head>

<body style="
    margin:0;
    padding:0;
    background:#f5f2f8;
    font-family:Arial,Helvetica,sans-serif;
">

<div style="
    width:90%;
    max-width:650px;
    margin:30px auto;
    background:#ffffff;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 5px 20px rgba(0,0,0,0.12);
">


    <!-- HEADER -->

    <div style="
        background:linear-gradient(135deg,#351650,#1c0e2c);
        color:white;
        text-align:center;
        padding:28px 20px;
    ">

        <h1 style="margin:0;font-size:30px;">
            🎬 Ticket<span style="color:#f4c430;">Flix</span>
        </h1>

        <p style="color:#d0c8d7;">
            Movie Ticket Booking
        </p>

    </div>


    <!-- CONTENT -->

    <div style="
        padding:30px;
        color:#333333;
    ">

        <p>Hi,</p>

        <h2 style="color:#5b2c83;">
            🎉 Your booking is confirmed!
        </h2>

        <p>
            Your payment was successful and your
            movie tickets have been booked successfully.
        </p>


        <!-- DETAILS -->

        <div style="
            margin-top:22px;
            padding:20px;
            background:#faf7fc;
            border-left:5px solid #f4c430;
            border-radius:9px;
        ">

            <p>
                <strong style="color:#5b2c83;">
                    Booking Reference:
                </strong>

                <strong style="
                    color:#d4af37;
                    font-size:19px;
                ">
                    ' .
                    htmlspecialchars($bookingReference) .
                '
                </strong>
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Movie:
                </strong>

                ' .
                htmlspecialchars($movieName) .
                '
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Theater:
                </strong>

                ' .
                htmlspecialchars($theaterName) .
                '
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Screen:
                </strong>

                ' .
                htmlspecialchars($screenName) .
                '
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Date:
                </strong>

                ' .
                htmlspecialchars($showDate) .
                '
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Time:
                </strong>

                ' .
                htmlspecialchars($showTime) .
                '
            </p>


            <p>
                <strong style="color:#5b2c83;">
                    Selected Seats:
                </strong>
            </p>

            ' .
            $seatList .
            '

        </div>


        <!-- TOTAL -->

        <div style="
            margin-top:20px;
            padding:15px;
            background:#351650;
            color:white;
            border-radius:8px;
            text-align:center;
        ">

            Total Amount:

            <strong style="
                color:#f4c430;
                font-size:22px;
            ">

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


    <!-- FOOTER -->

    <div style="
        background:#f1edf5;
        padding:20px;
        text-align:center;
        color:#666666;
        font-size:13px;
    ">

        This is an automated confirmation email.
        Please do not reply to this email.

    </div>

</div>

</body>

</html>

';


        // Plain text version
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
            $bookingReference . "\n" .

            "Movie: " .
            $movieName . "\n" .

            "Theater: " .
            $theaterName . "\n" .

            "Screen: " .
            $screenName . "\n" .

            "Date: " .
            $showDate . "\n" .

            "Time: " .
            $showTime . "\n" .

            "Seats: " .
            $plainSeats . "\n" .

            "Total Amount: ₹" .
            number_format(
                (float)$totalAmount,
                2
            ) . "\n\n" .

            "Enjoy the show!\n\n" .

            "Thank you for booking with TicketFlix.";


        // SEND
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