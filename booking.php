<?php

session_start();

require_once 'config.php';
require_once 'send_booking_email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

$user_id = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| USER EMAIL
|--------------------------------------------------------------------------
*/

$user_email = '';

if (
    isset($_SESSION['email']) &&
    filter_var($_SESSION['email'], FILTER_VALIDATE_EMAIL)
) {

    $user_email = trim($_SESSION['email']);

} elseif (
    isset($_SESSION['user_email']) &&
    filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)
) {

    $user_email = trim($_SESSION['user_email']);

} else {

    $user_stmt = $conn->prepare("
        SELECT email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($user_stmt) {

        $user_stmt->bind_param(
            "i",
            $user_id
        );

        $user_stmt->execute();

        $user_result =
            $user_stmt->get_result();

        $user_data =
            $user_result->fetch_assoc();

        if (
            $user_data &&
            !empty($user_data['email']) &&
            filter_var(
                $user_data['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $user_email =
                trim($user_data['email']);

            $_SESSION['email'] =
                $user_email;

        }

        $user_stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| BASIC VARIABLES
|--------------------------------------------------------------------------
*/

$showtime_id = 0;

$seat_ids = [];

$payment_success = false;

$payment_method = '';

$payment_id = '';

$booking_reference = '';

$booking_id = 0;

$error_message = '';

$email_sent = false;


/*
|--------------------------------------------------------------------------
| GET SHOWTIME ID
|--------------------------------------------------------------------------
*/

$showtime_id =
    isset($_POST['showtime_id'])
        ? (int) $_POST['showtime_id']
        : (
            isset($_SESSION['payment_showtime_id'])
                ? (int) $_SESSION['payment_showtime_id']
                : 0
        );


/*
|--------------------------------------------------------------------------
| GET SEAT IDS
|--------------------------------------------------------------------------
*/

if (
    isset($_POST['seat_ids']) &&
    is_array($_POST['seat_ids'])
) {

    $seat_ids =
        array_map(
            'intval',
            $_POST['seat_ids']
        );

} elseif (
    isset($_SESSION['payment_seat_ids']) &&
    is_array($_SESSION['payment_seat_ids'])
) {

    $seat_ids =
        array_map(
            'intval',
            $_SESSION['payment_seat_ids']
        );

}


$seat_ids =
    array_values(
        array_unique(
            array_filter(
                $seat_ids,
                function ($id) {

                    return $id > 0;

                }
            )
        )
    );


/*
|--------------------------------------------------------------------------
| VALIDATE BOOKING INFORMATION
|--------------------------------------------------------------------------
*/

if (
    $showtime_id <= 0 ||
    empty($seat_ids)
) {

    die("Invalid booking information.");

}


/*
|--------------------------------------------------------------------------
| SAVE BOOKING DATA IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION['payment_showtime_id'] =
    $showtime_id;

$_SESSION['payment_seat_ids'] =
    $seat_ids;


/*
|--------------------------------------------------------------------------
| GET SHOWTIME DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        st.id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,

        s.screen_name,

        t.name AS theater_name,
        t.city,
        t.state

    FROM showtimes st

    INNER JOIN movies m
        ON st.movie_id = m.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    WHERE st.id = ?

    LIMIT 1
");


if (!$stmt) {

    die("Database error.");

}


$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$showtime =
    $result->fetch_assoc();

$stmt->close();


if (!$showtime) {

    die("Showtime not found.");

}


/*
|--------------------------------------------------------------------------
| GET SELECTED SEATS
|--------------------------------------------------------------------------
*/

$placeholders =
    implode(
        ',',
        array_fill(
            0,
            count($seat_ids),
            '?'
        )
    );

$types =
    str_repeat(
        'i',
        count($seat_ids)
    );


$sql = "
    SELECT

        id,
        seat_row,
        seat_number,
        seat_type

    FROM seats

    WHERE id IN ($placeholders)

    ORDER BY
        seat_row ASC,
        seat_number ASC
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die("Seat query error.");

}


$stmt->bind_param(
    $types,
    ...$seat_ids
);

$stmt->execute();

$seat_result =
    $stmt->get_result();


$selected_seats = [];


while (
    $row =
        $seat_result->fetch_assoc()
) {

    $selected_seats[] =
        $row;

}


$stmt->close();


if (empty($selected_seats)) {

    die("Selected seats not found.");

}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$ticket_price =
    (float) $showtime['price'];

$seat_count =
    count($selected_seats);

$subtotal =
    $ticket_price * $seat_count;

$booking_fee =
    0;

$total_amount =
    $subtotal + $booking_fee;


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $payment_method =
        isset($_POST['payment_method'])
            ? trim($_POST['payment_method'])
            : '';


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['pay_now']) &&
        $_POST['pay_now'] === '1'
    ) {

        if (
            !in_array(
                $payment_method,
                [
                    'card',
                    'upi',
                    'netbanking'
                ],
                true
            )
        ) {

            $error_message =
                "Please select a payment method.";

        } elseif (
            empty($user_email) ||
            !filter_var(
                $user_email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error_message =
                "Registered user email not found.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | LOCAL TEST PAYMENT SUCCESS
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | This is still TEST payment logic.
            | Real payment gateway should be connected later.
            |
            */

            $payment_success = true;


            /*
            |--------------------------------------------------------------------------
            | PAYMENT ID
            |--------------------------------------------------------------------------
            */

            $payment_id =
                'TFX' .
                date('YmdHis') .
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(4)
                        ),
                        0,
                        6
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | BOOKING REFERENCE
            |--------------------------------------------------------------------------
            */

            $booking_reference =
                'TF' .
                date('ymd') .
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(4)
                        ),
                        0,
                        6
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | CHECK WHETHER BOOKING ALREADY EXISTS
            |--------------------------------------------------------------------------
            |
            | Prevent duplicate booking on accidental refresh.
            |
            */

            $existing_booking_id = 0;

            if (
                isset(
                    $_SESSION['current_booking_id']
                )
            ) {

                $existing_booking_id =
                    (int)
                    $_SESSION['current_booking_id'];

            }


            /*
            |--------------------------------------------------------------------------
            | CREATE BOOKING
            |--------------------------------------------------------------------------
            */

            if ($existing_booking_id <= 0) {

                $booking_stmt =
                    $conn->prepare("
                        INSERT INTO bookings
                        (
                            user_id,
                            showtime_id,
                            total_amount,
                            booking_status,
                            payment_status,
                            booking_reference
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            'confirmed',
                            'completed',
                            ?
                        )
                    ");


                if (!$booking_stmt) {

                    $payment_success = false;

                    $error_message =
                        "Booking database error: " .
                        $conn->error;

                } else {

                    $booking_stmt->bind_param(
                        "iids",
                        $user_id,
                        $showtime_id,
                        $total_amount,
                        $booking_reference
                    );


                    if (
                        !$booking_stmt->execute()
                    ) {

                        $payment_success = false;

                        $error_message =
                            "Booking could not be saved: " .
                            $booking_stmt->error;

                    } else {

                        $booking_id =
                            $conn->insert_id;

                    }


                    $booking_stmt->close();

                }

            } else {

                $booking_id =
                    $existing_booking_id;

                /*
                |--------------------------------------------------------------------------
                | GET EXISTING BOOKING REFERENCE
                |--------------------------------------------------------------------------
                */

                $reference_stmt =
                    $conn->prepare("
                        SELECT booking_reference
                        FROM bookings
                        WHERE id = ?
                        LIMIT 1
                    ");


                if ($reference_stmt) {

                    $reference_stmt->bind_param(
                        "i",
                        $booking_id
                    );

                    $reference_stmt->execute();

                    $reference_result =
                        $reference_stmt->get_result();

                    $reference_data =
                        $reference_result->fetch_assoc();

                    if (
                        $reference_data &&
                        !empty(
                            $reference_data[
                                'booking_reference'
                            ]
                        )
                    ) {

                        $booking_reference =
                            $reference_data[
                                'booking_reference'
                            ];

                    }

                    $reference_stmt->close();

                }

            }


            /*
            |--------------------------------------------------------------------------
            | SAVE BOOKING SEATS
            |--------------------------------------------------------------------------
            */

            if (
                $payment_success &&
                $booking_id > 0
            ) {

                /*
                |--------------------------------------------------------------------------
                | CHECK IF SEATS ARE ALREADY SAVED
                |--------------------------------------------------------------------------
                */

                $seat_check_stmt =
                    $conn->prepare("
                        SELECT COUNT(*)
                        AS total
                        FROM booking_seats
                        WHERE booking_id = ?
                    ");


                $existing_seat_count = 0;


                if ($seat_check_stmt) {

                    $seat_check_stmt->bind_param(
                        "i",
                        $booking_id
                    );

                    $seat_check_stmt->execute();

                    $seat_check_result =
                        $seat_check_stmt->get_result();

                    $seat_check_data =
                        $seat_check_result->fetch_assoc();

                    if ($seat_check_data) {

                        $existing_seat_count =
                            (int)
                            $seat_check_data['total'];

                    }

                    $seat_check_stmt->close();

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT SEATS ONLY ONCE
                |--------------------------------------------------------------------------
                */

                if (
                    $existing_seat_count === 0
                ) {

                    $seat_stmt =
                        $conn->prepare("
                            INSERT INTO booking_seats
                            (
                                booking_id,
                                seat_id
                            )
                            VALUES
                            (
                                ?,
                                ?
                            )
                        ");


                    if (!$seat_stmt) {

                        $payment_success = false;

                        $error_message =
                            "Seat booking database error: " .
                            $conn->error;

                    } else {

                        foreach (
                            $seat_ids
                            as $seat_id
                        ) {

                            $seat_stmt->bind_param(
                                "ii",
                                $booking_id,
                                $seat_id
                            );


                            if (
                                !$seat_stmt->execute()
                            ) {

                                $payment_success = false;

                                $error_message =
                                    "Seat could not be saved.";

                                break;

                            }

                        }


                        $seat_stmt->close();

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | SAVE SESSION
            |--------------------------------------------------------------------------
            */

            if ($payment_success) {

                $_SESSION['payment_id'] =
                    $payment_id;

                $_SESSION['payment_method'] =
                    $payment_method;

                $_SESSION['booking_email'] =
                    $user_email;

                $_SESSION['booking_reference'] =
                    $booking_reference;

                $_SESSION['current_booking_id'] =
                    $booking_id;


                /*
                |--------------------------------------------------------------------------
                | SEND EMAIL
                |--------------------------------------------------------------------------
                */

                $email_sent =
                    sendBookingConfirmationEmail(

                        $user_email,

                        $showtime['movie_name'],

                        $showtime['show_date'],

                        $showtime['show_time'],

                        $showtime['theater_name'],

                        $showtime['screen_name'],

                        $selected_seats,

                        $total_amount,

                        $booking_reference

                    );

            }

        }

    }

}

?>

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

<?= $payment_success
    ? 'Payment Successful | TicketFlix'
    : 'Checkout | TicketFlix'
?>

</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    color: #24202a;

    background:
        radial-gradient(
            circle at top left,
            #341252 0%,
            #180b25 45%,
            #09060e 100%
        );

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 78px;

    padding:
        0 6%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    background:
        rgba(10,7,15,.94);

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.logo {

    color:
        white;

    font-size:
        29px;

    font-weight:
        800;

}


.logo span {

    color:
        #f4c430;

}


.back-btn {

    text-decoration:
        none;

    color:
        white;

    border:
        1px solid
        rgba(255,255,255,.16);

    padding:
        10px 19px;

    border-radius:
        25px;

    transition:
        .25s;

}


.back-btn:hover {

    background:
        #f4c430;

    color:
        #21102d;

}


/* =========================================================
   MAIN
========================================================= */

.container {

    width:
        min(1120px, 92%);

    margin:
        45px auto 80px;

}


/* =========================================================
   CHECKOUT HEADER
========================================================= */

.checkout-header {

    margin-bottom:
        28px;

}


.checkout-header h1 {

    color:
        white;

    font-size:
        31px;

    margin-bottom:
        7px;

}


.checkout-header p {

    color:
        #aaa2b0;

    font-size:
        14px;

}


/* =========================================================
   GRID
========================================================= */

.checkout-grid {

    display:
        grid;

    grid-template-columns:
        .95fr 1.2fr;

    gap:
        24px;

    align-items:
        start;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-card {

    background:
        linear-gradient(
            145deg,
            #351650,
            #1c0e2c
        );

    color:
        white;

    border:
        1px solid
        rgba(244,196,48,.28);

    border-radius:
        22px;

    padding:
        29px;

    box-shadow:
        0 25px 60px
        rgba(0,0,0,.28);

}


.summary-heading {

    font-size:
        21px;

    margin-bottom:
        23px;

}


.movie-label {

    color:
        #aaa1b3;

    font-size:
        12px;

    text-transform:
        uppercase;

    letter-spacing:
        1px;

    margin-bottom:
        8px;

}


.movie-name {

    color:
        #f4c430;

    font-size:
        26px;

    line-height:
        1.2;

    font-weight:
        800;

    margin-bottom:
        22px;

}


.info-row {

    display:
        flex;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        13px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

    color:
        #bdb5c6;

    font-size:
        13px;

}


.info-row strong {

    color:
        white;

    text-align:
        right;

}


.seat-heading {

    color:
        #aaa1b3;

    font-size:
        12px;

    margin:
        23px 0 12px;

    letter-spacing:
        .8px;

}


.seats {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        8px;

}


.seat {

    background:
        #f4c430;

    color:
        #21102d;

    font-weight:
        800;

    font-size:
        13px;

    padding:
        8px 12px;

    border-radius:
        8px;

}


.amount-section {

    margin-top:
        24px;

    padding-top:
        20px;

    border-top:
        1px solid
        rgba(255,255,255,.10);

}


.amount-row {

    display:
        flex;

    justify-content:
        space-between;

    color:
        #bdb5c6;

    margin-bottom:
        12px;

    font-size:
        14px;

}


.total-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-top:
        16px;

}


.total-label {

    color:
        white;

    font-size:
        17px;

    font-weight:
        700;

}


.total-price {

    color:
        #f4c430;

    font-size:
        27px;

    font-weight:
        800;

}


/* =========================================================
   PAYMENT CARD
========================================================= */

.payment-card {

    background:
        #ffffff;

    border-radius:
        22px;

    padding:
        32px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.38);

}


.payment-title {

    font-size:
        27px;

    margin-bottom:
        7px;

    color:
        #211c26;

}


.payment-subtitle {

    color:
        #85808a;

    font-size:
        14px;

    margin-bottom:
        25px;

}


/* =========================================================
   BOOKING EMAIL
========================================================= */

.email-box {

    border:
        1px solid
        #e3dee7;

    background:
        #faf8fc;

    border-radius:
        11px;

    padding:
        13px 15px;

    margin-bottom:
        24px;

}


.email-label {

    display:
        block;

    color:
        #8d8791;

    font-size:
        10px;

    font-weight:
        800;

    letter-spacing:
        .7px;

    margin-bottom:
        5px;

}


.email-value {

    color:
        #29232f;

    font-size:
        14px;

    font-weight:
        700;

    word-break:
        break-word;

}


/* =========================================================
   ERROR
========================================================= */

.error-box {

    background:
        #fff2f2;

    border:
        1px solid
        #f0b7b7;

    color:
        #a42121;

    padding:
        12px 14px;

    border-radius:
        9px;

    margin-bottom:
        18px;

    font-size:
        13px;

    font-weight:
        600;

}


/* =========================================================
   PAYMENT METHOD
========================================================= */

.section-title {

    font-size:
        16px;

    font-weight:
        800;

    color:
        #2a2530;

    margin-bottom:
        12px;

}


.methods {

    border:
        1px solid
        #dfd9e2;

    border-radius:
        12px;

    overflow:
        hidden;

    margin-bottom:
        22px;

}


.method {

    position:
        relative;

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    min-height:
        66px;

    padding:
        0 15px;

    cursor:
        pointer;

    border-bottom:
        1px solid
        #ebe7ed;

    transition:
        .2s;

}


.method:last-child {

    border-bottom:
        none;

}


.method:hover {

    background:
        #faf8ff;

}


.method input {

    width:
        18px;

    height:
        18px;

    accent-color:
        #7137c8;

}


.method-icon {

    width:
        37px;

    height:
        37px;

    border-radius:
        9px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #f0e8ff;

    font-size:
        18px;

}


.method-content {

    flex:
        1;

}


.method-name {

    display:
        block;

    color:
        #2c2730;

    font-size:
        14px;

    font-weight:
        800;

}


.method-desc {

    display:
        block;

    color:
        #97919c;

    font-size:
        11px;

    margin-top:
        3px;

}


/* =========================================================
   PAYMENT DETAILS
========================================================= */

.details {

    display:
        none;

}


.details.active {

    display:
        block;

}


.input-group {

    margin-bottom:
        16px;

}


.input-group label {

    display:
        block;

    color:
        #4c4650;

    font-size:
        12px;

    font-weight:
        700;

    margin-bottom:
        7px;

}


.input-group input {

    width:
        100%;

    height:
        46px;

    padding:
        0 13px;

    border:
        1px solid
        #ddd7e0;

    border-radius:
        9px;

    outline:
        none;

    font-size:
        13px;

    color:
        #28222d;

    background:
        #fff;

}


.input-group input:focus {

    border-color:
        #8052c9;

    box-shadow:
        0 0 0 3px
        rgba(128,82,201,.10);

}


.input-row {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        12px;

}


/* =========================================================
   PAYABLE
========================================================= */

.payable {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        18px 0;

    margin-top:
        7px;

    border-top:
        1px solid
        #ebe7ed;

}


.payable-label {

    color:
        #77717b;

    font-size:
        14px;

}


.payable-price {

    color:
        #241c29;

    font-size:
        22px;

    font-weight:
        800;

}


/* =========================================================
   PAY BUTTON
========================================================= */

.pay-btn {

    width:
        100%;

    height:
        54px;

    border:
        none;

    border-radius:
        10px;

    cursor:
        pointer;

    background:
        linear-gradient(
            135deg,
            #f4c430,
            #e8b415
        );

    color:
        #24102e;

    font-size:
        16px;

    font-weight:
        800;

    transition:
        .25s;

}


.pay-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 25px
        rgba(244,196,48,.28);

}


.secure-text {

    text-align:
        center;

    color:
        #9b959f;

    font-size:
        11px;

    margin-top:
        13px;

}


/* =========================================================
   SUCCESS
========================================================= */

.success-card {

    max-width:
        700px;

    margin:
        55px auto;

    padding:
        45px 35px;

    text-align:
        center;

    background:
        linear-gradient(
            145deg,
            #351650,
            #1c0e2c
        );

    color:
        white;

    border:
        1px solid
        rgba(244,196,48,.28);

    border-radius:
        24px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.35);

}


.success-icon {

    width:
        82px;

    height:
        82px;

    margin:
        0 auto 22px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #f4c430;

    color:
        #24102e;

    font-size:
        42px;

    font-weight:
        800;

}


.success-card h1 {

    font-size:
        32px;

    margin-bottom:
        10px;

}


.success-card h1 span {

    color:
        #f4c430;

}


.success-card p {

    color:
        #c7becf;

    font-size:
        14px;

    line-height:
        1.6;

    margin-bottom:
        25px;

}


.payment-id {

    padding:
        17px;

    border-radius:
        11px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.09);

    margin-bottom:
        24px;

}


.payment-id small {

    display:
        block;

    color:
        #9890a3;

    margin-bottom:
        6px;

}


.payment-id strong {

    color:
        #f4c430;

    letter-spacing:
        1.5px;

}


.done-btn {

    display:
        inline-block;

    padding:
        13px 26px;

    border-radius:
        28px;

    background:
        #f4c430;

    color:
        #24102e;

    text-decoration:
        none;

    font-weight:
        800;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .checkout-grid {

        grid-template-columns:
            1fr;

    }

}


@media(max-width:550px) {

    .header {

        padding:
            0 4%;

    }

    .logo {

        font-size:
            24px;

    }

    .container {

        width:
            94%;

        margin-top:
            28px;

    }

    .payment-card,
    .summary-card {

        padding:
            22px;

    }

    .checkout-header h1 {

        font-size:
            27px;

    }

    .payment-title {

        font-size:
            24px;

    }

    .input-row {

        grid-template-columns:
            1fr;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">

    <div class="logo">

        Ticket<span>Flix</span>

    </div>


    <?php if (!$payment_success): ?>

        <a
            href="javascript:history.back()"
            class="back-btn"
        >

            ← Back

        </a>

    <?php endif; ?>

</header>



<main class="container">


<?php if ($payment_success): ?>


<!-- =========================================================
     SUCCESS PAGE
========================================================= -->

<div class="success-card">

    <div class="success-icon">

        ✓

    </div>


    <h1>

        Payment <span>Successful!</span>

    </h1>


    <p>

        Your TicketFlix booking has been confirmed successfully.
        Your seats are reserved for the selected show.

    </p>


    <div class="payment-id">

    <small>
        BOOKING REFERENCE
    </small>

    <strong>
        <?= htmlspecialchars($booking_reference); ?>
    </strong>

</div>


<div class="payment-id">

    <small>
        PAYMENT ID
    </small>

    <strong>
        <?= htmlspecialchars($payment_id); ?>
    </strong>

</div>


    <a
        href="my_bookings.php"
        class="done-btn"
    >

        🎟 View My Bookings

    </a>

</div>


<?php else: ?>


<!-- =========================================================
     CHECKOUT HEADER
========================================================= -->

<div class="checkout-header">

    <h1>

        Complete Your Payment

    </h1>

    <p>

        Review your booking details and choose a payment method.

    </p>

</div>



<div class="checkout-grid">


<!-- =========================================================
     LEFT - BOOKING SUMMARY
========================================================= -->

<section class="summary-card">


    <h2 class="summary-heading">

        Booking Summary

    </h2>


    <div class="movie-label">

        Movie

    </div>


    <div class="movie-name">

        <?= htmlspecialchars(
            $showtime['movie_name']
        ); ?>

    </div>


    <div class="info-row">

        <span>

            Date

        </span>

        <strong>

            <?= date(
                "D, d M Y",
                strtotime(
                    $showtime['show_date']
                )
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Time

        </span>

        <strong>

            <?= date(
                "h:i A",
                strtotime(
                    $showtime['show_time']
                )
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Theater

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['theater_name']
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Screen

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['screen_name']
            ); ?>

        </strong>

    </div>


    <?php if (!empty($showtime['city'])): ?>

    <div class="info-row">

        <span>

            Location

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['city']
            ); ?>

            <?php if (!empty($showtime['state'])): ?>

                , <?= htmlspecialchars(
                    $showtime['state']
                ); ?>

            <?php endif; ?>

        </strong>

    </div>

    <?php endif; ?>


    <div class="seat-heading">

        SELECTED SEATS

    </div>


    <div class="seats">

        <?php foreach (
            $selected_seats
            as $seat
        ): ?>

            <div class="seat">

                <?= htmlspecialchars(
                    $seat['seat_row']
                ); ?><?= (int)$seat['seat_number']; ?>

            </div>

        <?php endforeach; ?>

    </div>


    <div class="amount-section">


        <div class="amount-row">

            <span>

                Tickets

            </span>

            <strong>

                <?= $seat_count; ?>
                ×
                ₹<?= number_format(
                    $ticket_price,
                    2
                ); ?>

            </strong>

        </div>


        <div class="amount-row">

            <span>

                Convenience Fee

            </span>

            <strong>

                ₹<?= number_format(
                    $booking_fee,
                    2
                ); ?>

            </strong>

        </div>


        <div class="total-row">

            <span class="total-label">

                Total

            </span>

            <span class="total-price">

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </span>

        </div>


    </div>

</section>



<!-- =========================================================
     RIGHT - PAYMENT
========================================================= -->

<section class="payment-card">


    <h2 class="payment-title">

        Payment Details

    </h2>


    <p class="payment-subtitle">

        Select your preferred payment method

    </p>


    <!-- EMAIL FROM SESSION -->

    <div class="email-box">

        <span class="email-label">

            BOOKING EMAIL

        </span>


        <span class="email-value">

            <?= $user_email !== ''
                ? htmlspecialchars($user_email)
                : 'Email not available'
            ?>

        </span>

    </div>


    <?php if ($error_message !== ''): ?>

        <div class="error-box">

            <?= htmlspecialchars(
                $error_message
            ); ?>

        </div>

    <?php endif; ?>


    <div class="section-title">

        Payment Method

    </div>


    <form
        method="POST"
        id="paymentForm"
    >


        <!-- SHOWTIME -->

        <input
            type="hidden"
            name="showtime_id"
            value="<?= $showtime_id; ?>"
        >


        <!-- SEATS -->

        <?php foreach (
            $seat_ids
            as $seat_id
        ): ?>

            <input
                type="hidden"
                name="seat_ids[]"
                value="<?= (int)$seat_id; ?>"
            >

        <?php endforeach; ?>


        <!-- PAYMENT METHODS -->

        <div class="methods">


            <!-- CARD -->

            <label class="method">

                <input
                    type="radio"
                    name="payment_method"
                    value="card"
                    required
                    onchange="selectMethod('card')"
                >


                <div class="method-icon">

                    💳

                </div>


                <div class="method-content">

                    <span class="method-name">

                        Credit / Debit Card

                    </span>

                    <span class="method-desc">

                        Visa • Mastercard • RuPay

                    </span>

                </div>

            </label>



            <!-- UPI -->

            <label class="method">

                <input
                    type="radio"
                    name="payment_method"
                    value="upi"
                    onchange="selectMethod('upi')"
                >


                <div class="method-icon">

                    📱

                </div>


                <div class="method-content">

                    <span class="method-name">

                        UPI

                    </span>

                    <span class="method-desc">

                        Google Pay • PhonePe • Paytm

                    </span>

                </div>

            </label>



            <!-- NET BANKING -->

            <label class="method">

                <input
                    type="radio"
                    name="payment_method"
                    value="netbanking"
                    onchange="selectMethod('netbanking')"
                >


                <div class="method-icon">

                    🏦

                </div>


                <div class="method-content">

                    <span class="method-name">

                        Net Banking

                    </span>

                    <span class="method-desc">

                        All major banks supported

                    </span>

                </div>

            </label>


        </div>



        <!-- CARD DETAILS -->

        <div
            class="details"
            id="cardDetails"
        >

            <div class="input-group">

                <label>

                    Cardholder Name

                </label>

                <input
                    type="text"
                    placeholder="Enter cardholder name"
                    autocomplete="off"
                >

            </div>


            <div class="input-group">

                <label>

                    Card Number

                </label>

                <input
                    type="text"
                    placeholder="XXXX XXXX XXXX XXXX"
                    maxlength="19"
                    inputmode="numeric"
                    autocomplete="off"
                >

            </div>


            <div class="input-row">

                <div class="input-group">

                    <label>

                        Expiry Date

                    </label>

                    <input
                        type="text"
                        placeholder="MM / YY"
                        maxlength="7"
                        autocomplete="off"
                    >

                </div>


                <div class="input-group">

                    <label>

                        CVV

                    </label>

                    <input
                        type="password"
                        placeholder="•••"
                        maxlength="3"
                        inputmode="numeric"
                        autocomplete="off"
                    >

                </div>

            </div>

        </div>



        <!-- UPI DETAILS -->

        <div
            class="details"
            id="upiDetails"
        >

            <div class="input-group">

                <label>

                    UPI ID

                </label>

                <input
                    type="text"
                    placeholder="example@upi"
                    autocomplete="off"
                >

            </div>

        </div>



        <!-- NET BANKING DETAILS -->

        <div
            class="details"
            id="bankDetails"
        >

            <div class="input-group">

                <label>

                    Select Bank

                </label>

                <select
                    style="
                        width:100%;
                        height:46px;
                        border:1px solid #ddd7e0;
                        border-radius:9px;
                        padding:0 13px;
                        background:#fff;
                        color:#28222d;
                        font-size:13px;
                    "
                >

                    <option value="">

                        Select your bank

                    </option>

                    <option>

                        State Bank of India

                    </option>

                    <option>

                        HDFC Bank

                    </option>

                    <option>

                        ICICI Bank

                    </option>

                    <option>

                        Axis Bank

                    </option>

                    <option>

                        Kotak Mahindra Bank

                    </option>

                </select>

            </div>

        </div>



        <!-- PAYABLE -->

        <div class="payable">

            <span class="payable-label">

                Payable Amount

            </span>

            <span class="payable-price">

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </span>

        </div>


        <input
            type="hidden"
            name="pay_now"
            value="1"
        >


        <!-- PAY -->

        <button
            type="submit"
            class="pay-btn"
        >

            Pay ₹<?= number_format(
                $total_amount,
                2
            ); ?>

        </button>


        <div class="secure-text">

            🔒 Secure checkout

        </div>


    </form>


</section>


</div>


<?php endif; ?>


</main>



<script>

/*
|--------------------------------------------------------------------------
| PAYMENT METHOD SWITCH
|--------------------------------------------------------------------------
*/

function selectMethod(method) {

    const card =
        document.getElementById('cardDetails');

    const upi =
        document.getElementById('upiDetails');

    const bank =
        document.getElementById('bankDetails');


    card.classList.remove('active');

    upi.classList.remove('active');

    bank.classList.remove('active');


    if (method === 'card') {

        card.classList.add('active');

    }


    if (method === 'upi') {

        upi.classList.add('active');

    }


    if (method === 'netbanking') {

        bank.classList.add('active');

    }

}


/*
|--------------------------------------------------------------------------
| CARD NUMBER FORMAT
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'input',
    function(e) {

        if (
            e.target.placeholder ===
            'XXXX XXXX XXXX XXXX'
        ) {

            let value =
                e.target.value
                .replace(/\D/g, '')
                .substring(0, 16);

            value =
                value.match(/.{1,4}/g);

            e.target.value =
                value
                    ? value.join(' ')
                    : '';

        }

    }
);


/*
|--------------------------------------------------------------------------
| EXPIRY FORMAT
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'input',
    function(e) {

        if (
            e.target.placeholder ===
            'MM / YY'
        ) {

            let value =
                e.target.value
                .replace(/\D/g, '')
                .substring(0, 4);

            if (value.length > 2) {

                value =
                    value.substring(0, 2)
                    + ' / '
                    + value.substring(2);

            }

            e.target.value = value;

        }

    }
);

</script>


</body>

</html>





