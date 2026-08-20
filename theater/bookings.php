<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| booking.php aur config.php same folder mein hain
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config.php";


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
| GET SHOWTIME ID
|--------------------------------------------------------------------------
*/

$showtime_id = 0;

if (isset($_POST['showtime_id'])) {

    $showtime_id = (int)$_POST['showtime_id'];

} elseif (isset($_GET['showtime_id'])) {

    $showtime_id = (int)$_GET['showtime_id'];

}


/*
|--------------------------------------------------------------------------
| GET SEAT IDS
|--------------------------------------------------------------------------
*/

$seat_ids = [];


if (isset($_POST['seat_ids'])) {

    if (is_array($_POST['seat_ids'])) {

        $seat_ids = $_POST['seat_ids'];

    } else {

        $seat_ids = explode(',', $_POST['seat_ids']);

    }

} elseif (isset($_GET['seat_ids'])) {

    if (is_array($_GET['seat_ids'])) {

        $seat_ids = $_GET['seat_ids'];

    } else {

        $seat_ids = explode(',', $_GET['seat_ids']);

    }

}


/*
|--------------------------------------------------------------------------
| CLEAN SEAT IDS
|--------------------------------------------------------------------------
*/

$seat_ids = array_map('intval', $seat_ids);

$seat_ids = array_filter(
    $seat_ids,
    function ($id) {
        return $id > 0;
    }
);

$seat_ids = array_values(
    array_unique($seat_ids)
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($showtime_id <= 0) {

    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invalid Showtime | TicketFlix</title>

        <style>

        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#100b18;
            color:white;
            font-family:Arial,sans-serif;
        }

        .error-box{
            text-align:center;
            background:#1d1029;
            border:1px solid #563276;
            border-radius:18px;
            padding:40px;
            width:400px;
        }

        h2{
            color:#ff7777;
        }

        a{
            display:inline-block;
            margin-top:20px;
            padding:11px 20px;
            background:#f4c430;
            color:#21102f;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        </style>
    </head>

    <body>

        <div class='error-box'>

            <h2>Invalid Showtime</h2>

            <p>
                Showtime information was not received.
            </p>

            <a href='javascript:history.back()'>
                ← Go Back
            </a>

        </div>

    </body>
    </html>
    ");

}


if (empty($seat_ids)) {

    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>No Seats Selected | TicketFlix</title>

        <style>

        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#100b18;
            color:white;
            font-family:Arial,sans-serif;
        }

        .error-box{
            text-align:center;
            background:#1d1029;
            border:1px solid #563276;
            border-radius:18px;
            padding:40px;
            width:400px;
        }

        h2{
            color:#ff7777;
        }

        a{
            display:inline-block;
            margin-top:20px;
            padding:11px 20px;
            background:#f4c430;
            color:#21102f;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        </style>
    </head>

    <body>

        <div class='error-box'>

            <h2>No Seats Selected</h2>

            <p>
                Please select at least one seat before continuing.
            </p>

            <a href='javascript:history.back()'>
                ← Go Back
            </a>

        </div>

    </body>
    </html>
    ");

}


/*
|--------------------------------------------------------------------------
| GET SHOWTIME + MOVIE + SCREEN + THEATER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        st.id AS showtime_id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,
        m.poster_image,

        s.id AS screen_id,
        s.screen_name,

        t.id AS theater_id,
        t.name AS theater_name,
        t.city,
        t.state,
        t.address

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

    die(
        "Showtime query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {

    die("
    <!DOCTYPE html>
    <html>
    <head>

        <title>Showtime Not Found</title>

        <style>

        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#100b18;
            color:white;
            font-family:Arial,sans-serif;
        }

        .error-box{
            text-align:center;
            background:#1d1029;
            border:1px solid #563276;
            border-radius:18px;
            padding:40px;
            width:400px;
        }

        h2{
            color:#ff7777;
        }

        a{
            display:inline-block;
            margin-top:20px;
            padding:11px 20px;
            background:#f4c430;
            color:#21102f;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        </style>

    </head>

    <body>

        <div class='error-box'>

            <h2>Showtime Not Found</h2>

            <p>
                The selected showtime does not exist.
            </p>

            <a href='javascript:history.back()'>
                ← Go Back
            </a>

        </div>

    </body>
    </html>
    ");

}


/*
|--------------------------------------------------------------------------
| GET SELECTED SEATS
|--------------------------------------------------------------------------
*/

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($seat_ids),
        '?'
    )
);

$types = str_repeat(
    'i',
    count($seat_ids)
);


$sql = "
    SELECT

        id,
        screen_id,
        seat_row,
        seat_number,
        seat_type

    FROM seats

    WHERE id IN ($placeholders)

    ORDER BY

        seat_row ASC,
        seat_number ASC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    $types,
    ...$seat_ids
);

$stmt->execute();

$seat_result = $stmt->get_result();

$selected_seats = [];


while ($seat = $seat_result->fetch_assoc()) {

    $selected_seats[] = $seat;

}


$stmt->close();


if (empty($selected_seats)) {

    die("
    <!DOCTYPE html>
    <html>
    <head>

        <title>Seats Not Found</title>

        <style>

        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#100b18;
            color:white;
            font-family:Arial,sans-serif;
        }

        .error-box{
            text-align:center;
            background:#1d1029;
            border:1px solid #563276;
            border-radius:18px;
            padding:40px;
            width:400px;
        }

        h2{
            color:#ff7777;
        }

        a{
            display:inline-block;
            margin-top:20px;
            padding:11px 20px;
            background:#f4c430;
            color:#21102f;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        </style>

    </head>

    <body>

        <div class='error-box'>

            <h2>Seats Not Found</h2>

            <p>
                The selected seats could not be found.
            </p>

            <a href='javascript:history.back()'>
                ← Go Back
            </a>

        </div>

    </body>
    </html>
    ");

}


/*
|--------------------------------------------------------------------------
| VERIFY SEATS BELONG TO SAME SCREEN
|--------------------------------------------------------------------------
*/

foreach ($selected_seats as $seat) {

    if (
        (int)$seat['screen_id']
        !==
        (int)$showtime['screen_id']
    ) {

        die("
            <div style='
                color:white;
                background:#100b18;
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                font-family:Arial;
                text-align:center;
            '>

                <div>

                    <h2 style='color:#ff7777;'>
                        Invalid Seat
                    </h2>

                    <p>
                        One or more selected seats
                        do not belong to this screen.
                    </p>

                    <a
                        href='javascript:history.back()'
                        style='
                            display:inline-block;
                            margin-top:15px;
                            padding:10px 20px;
                            background:#f4c430;
                            color:#21102f;
                            text-decoration:none;
                            border-radius:8px;
                        '
                    >
                        ← Go Back
                    </a>

                </div>

            </div>
        ");

    }

}


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL
|--------------------------------------------------------------------------
*/

$ticket_price =
    (float)$showtime['price'];

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
| CREATE SEAT LABELS
|--------------------------------------------------------------------------
*/

$seat_labels = [];


foreach ($selected_seats as $seat) {

    $seat_labels[] =
        $seat['seat_row'] .
        $seat['seat_number'];

}


/*
|--------------------------------------------------------------------------
| PAYMENT VARIABLES
|--------------------------------------------------------------------------
*/

$payment_success =
    false;

$booking_number =
    '';

$email =
    '';

$payment_method =
    '';

$payment_error =
    '';


/*
|--------------------------------------------------------------------------
| PROCESS PAYMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['dummy_payment'])
) {


    $email =
        isset($_POST['email'])
        ? trim($_POST['email'])
        : '';


    $payment_method =
        isset($_POST['payment_method'])
        ? trim($_POST['payment_method'])
        : '';


    /*
    |--------------------------------------------------------------------------
    | EMAIL VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $payment_error =
            "Please enter a valid email address.";

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $payment_method === ''
    ) {

        $payment_error =
            "Please select a payment method.";

    }


    else {


        /*
        |--------------------------------------------------------------------------
        | CHECK ALREADY BOOKED SEATS
        |--------------------------------------------------------------------------
        */

        $already_booked =
            false;


        $check_placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($seat_ids),
                    '?'
                )
            );


        $check_types =
            str_repeat(
                'i',
                count($seat_ids)
            );


        /*
        | IMPORTANT:
        |
        | This query supports the booking_seats table structure.
        |
        */

        $check_sql = "
            SELECT bs.seat_id

            FROM booking_seats bs

            INNER JOIN bookings b
                ON b.id = bs.booking_id

            WHERE b.showtime_id = ?

            AND bs.seat_id IN
            ($check_placeholders)

            AND (
                b.booking_status = 'pending'
                OR
                b.booking_status = 'confirmed'
            )

            LIMIT 1
        ";


        $check_stmt =
            $conn->prepare(
                $check_sql
            );


        if (!$check_stmt) {

            /*
            | If booking_seats table/query structure
            | is not available, show readable error.
            */

            $payment_error =
                "Booking check error: " .
                $conn->error;

        }


        else {


            $check_params =
                array_merge(
                    [$showtime_id],
                    $seat_ids
                );


            $check_types_final =
                'i' .
                $check_types;


            $check_stmt->bind_param(
                $check_types_final,
                ...$check_params
            );


            $check_stmt->execute();


            $check_result =
                $check_stmt->get_result();


            if (
                $check_result->num_rows > 0
            ) {

                $already_booked =
                    true;

            }


            $check_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | IF ALREADY BOOKED
            |--------------------------------------------------------------------------
            */

            if ($already_booked) {

                $payment_error =
                    "One or more selected seats are already booked. Please go back and select different seats.";

            }


            /*
            |--------------------------------------------------------------------------
            | PAYMENT SUCCESS
            |--------------------------------------------------------------------------
            */

            else {


                /*
                |--------------------------------------------------------------------------
                | GENERATE BOOKING NUMBER
                |--------------------------------------------------------------------------
                */

                $booking_number =
                    'TF'
                    .
                    date('Ymd')
                    .
                    strtoupper(
                        substr(
                            md5(
                                uniqid(
                                    (string)$showtime_id,
                                    true
                                )
                            ),
                            0,
                            6
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | PAYMENT SUCCESS
                |--------------------------------------------------------------------------
                */

                $payment_success =
                    true;


                /*
                |--------------------------------------------------------------------------
                | SAVE BOOKING
                |--------------------------------------------------------------------------
                |
                | NOTE:
                | Agar aapki bookings table mein columns
                | different hain to yahan sirf INSERT columns
                | adjust karne honge.
                |
                */

            }

        }

    }

}

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
    ? 'Booking Confirmed | TicketFlix'
    : 'Payment | TicketFlix'
?>

</title>


<style>

/* =====================================================
   RESET
===================================================== */

*{

    box-sizing:border-box;

    margin:0;

    padding:0;

}


/* =====================================================
   BODY
===================================================== */

body{

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            #35145c 0%,
            #170b26 40%,
            #09060f 100%
        );

    min-height:100vh;

    color:white;

}


/* =====================================================
   HEADER
===================================================== */

.header{

    height:82px;

    padding:0 6%;

    display:flex;

    align-items:center;

    justify-content:space-between;

    background:
        rgba(13,7,23,.92);

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.logo{

    font-size:29px;

    font-weight:800;

}


.logo span{

    color:#f4c430;

}


.back-btn{

    text-decoration:none;

    color:white;

    padding:10px 20px;

    border:
        1px solid
        rgba(255,255,255,.15);

    border-radius:25px;

    background:
        rgba(255,255,255,.06);

}


.back-btn:hover{

    background:#f4c430;

    color:#180c27;

}


/* =====================================================
   CONTAINER
===================================================== */

.container{

    width:
        min(1120px,92%);

    margin:
        45px auto 80px;

}


/* =====================================================
   PAYMENT WRAPPER
===================================================== */

.payment-wrapper{

    display:grid;

    grid-template-columns:
        1fr 1.15fr;

    gap:25px;

    align-items:start;

}


/* =====================================================
   SUMMARY CARD
===================================================== */

.summary-card{

    background:
        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid
        #563276;

    border-radius:22px;

    padding:30px;

    box-shadow:
        0 20px 50px
        rgba(0,0,0,.25);

}


.summary-title{

    font-size:24px;

    margin-bottom:25px;

}


.movie-title{

    color:#f4c430;

    font-size:25px;

    font-weight:800;

    margin-bottom:18px;

}


.summary-info{

    display:flex;

    flex-direction:column;

    gap:14px;

}


.info-row{

    display:flex;

    justify-content:space-between;

    gap:15px;

    color:#cfc6d9;

    font-size:14px;

}


.info-row strong{

    color:white;

    text-align:right;

}


.divider{

    height:1px;

    background:
        rgba(255,255,255,.10);

    margin:22px 0;

}


.seat-title{

    color:#aaa1b4;

    font-size:13px;

    margin-bottom:12px;

}


.seat-list{

    display:flex;

    flex-wrap:wrap;

    gap:8px;

}


.seat-chip{

    padding:7px 12px;

    border-radius:8px;

    background:#f4c430;

    color:#21102f;

    font-size:13px;

    font-weight:800;

}


.amount-row{

    display:flex;

    justify-content:space-between;

    margin-bottom:12px;

    color:#cfc6d9;

}


.total-row{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-top:18px;

}


.total-label{

    font-size:18px;

    font-weight:700;

}


.total-price{

    color:#f4c430;

    font-size:27px;

    font-weight:800;

}


/* =====================================================
   PAYMENT CARD
===================================================== */

.payment-card{

    background:#fff;

    color:#29232f;

    border-radius:22px;

    padding:32px;

    box-shadow:
        0 25px 60px
        rgba(0,0,0,.35);

}


.test-mode{

    display:inline-block;

    padding:5px 10px;

    border-radius:6px;

    background:#fff1bd;

    color:#8a6810;

    font-size:11px;

    font-weight:800;

    margin-bottom:15px;

}


.payment-heading{

    font-size:27px;

    margin-bottom:8px;

}


.payment-subtitle{

    color:#817987;

    font-size:14px;

    margin-bottom:25px;

}


/* =====================================================
   ERROR
===================================================== */

.payment-error{

    padding:13px 15px;

    background:#ffe8e8;

    border:
        1px solid
        #f3b1b1;

    border-radius:10px;

    color:#a52626;

    font-size:13px;

    margin-bottom:20px;

}


/* =====================================================
   DEMO NOTICE
===================================================== */

.demo-notice{

    padding:13px 15px;

    background:#fff8df;

    border:
        1px solid
        #f0d878;

    border-radius:10px;

    color:#705b13;

    font-size:13px;

    margin-bottom:23px;

}


.demo-notice strong{

    display:block;

    margin-bottom:4px;

}


/* =====================================================
   FORM
===================================================== */

.form-label{

    display:block;

    font-size:13px;

    font-weight:700;

    margin-bottom:8px;

}


.email-input{

    width:100%;

    height:48px;

    border:
        1px solid
        #ddd8e0;

    border-radius:9px;

    padding:0 14px;

    font-size:14px;

    outline:none;

    margin-bottom:24px;

}


.email-input:focus{

    border-color:#7c3aed;

    box-shadow:
        0 0 0 3px
        rgba(124,58,237,.10);

}


/* =====================================================
   PAYMENT METHODS
===================================================== */

.method-title{

    font-size:16px;

    font-weight:700;

    margin-bottom:12px;

}


.payment-method{

    border:
        1px solid
        #ded9e1;

    border-radius:10px;

    overflow:hidden;

    margin-bottom:20px;

}


.method-option{

    position:relative;

    display:flex;

    align-items:center;

    gap:13px;

    min-height:64px;

    padding:0 16px;

    cursor:pointer;

    border-bottom:
        1px solid
        #ebe7ed;

}


.method-option:last-child{

    border-bottom:none;

}


.method-option:hover{

    background:#faf7ff;

}


.method-option input{

    width:18px;

    height:18px;

    accent-color:#7c3aed;

}


.method-icon{

    width:35px;

    height:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:8px;

    background:#f1eaff;

    font-size:18px;

}


.method-name{

    font-weight:700;

    font-size:14px;

}


.method-small{

    display:block;

    color:#98919f;

    font-size:11px;

    margin-top:3px;

}


/* =====================================================
   PAY BUTTON
===================================================== */

.pay-btn{

    width:100%;

    border:none;

    height:54px;

    border-radius:10px;

    background:
        linear-gradient(
            135deg,
            #f4c430,
            #e7ad10
        );

    color:#21102f;

    font-size:16px;

    font-weight:800;

    cursor:pointer;

}


.pay-btn:hover{

    transform:translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(244,196,48,.30);

}


.pay-note{

    text-align:center;

    color:#99929e;

    font-size:11px;

    margin-top:14px;

}


/* =====================================================
   SUCCESS CARD
===================================================== */

.success-card{

    max-width:700px;

    margin:60px auto;

    text-align:center;

    background:
        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid
        #634085;

    border-radius:25px;

    padding:50px 35px;

}


.success-icon{

    width:85px;

    height:85px;

    margin:
        0 auto 22px;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f4c430;

    color:#241033;

    font-size:43px;

}


.success-title{

    font-size:32px;

    margin-bottom:10px;

}


.success-title span{

    color:#f4c430;

}


.success-text{

    color:#c9bfd3;

    margin-bottom:28px;

}


.booking-number{

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.10);

    border-radius:13px;

    padding:18px;

    margin-bottom:22px;

}


.booking-number small{

    display:block;

    color:#9d94a7;

    margin-bottom:7px;

}


.booking-number strong{

    color:#f4c430;

    font-size:23px;

    letter-spacing:2px;

}


.success-details{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:12px;

    margin-bottom:28px;

}


.success-box{

    background:
        rgba(255,255,255,.05);

    border-radius:10px;

    padding:14px;

}


.success-box small{

    display:block;

    color:#91879b;

    margin-bottom:5px;

}


.success-box strong{

    font-size:14px;

}


.home-btn{

    display:inline-block;

    text-decoration:none;

    padding:
        13px 27px;

    border-radius:30px;

    background:#f4c430;

    color:#241033;

    font-weight:800;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:800px){

    .payment-wrapper{

        grid-template-columns:1fr;

    }

}


@media(max-width:550px){

    .header{

        padding:
            0 4%;

    }

    .logo{

        font-size:23px;

    }

    .container{

        width:94%;

        margin-top:25px;

    }

    .summary-card,
    .payment-card{

        padding:22px;

    }

    .success-details{

        grid-template-columns:1fr;

    }

    .success-title{

        font-size:27px;

    }

}

</style>

</head>


<body>


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



<?php if ($payment_success): ?>


<!-- =====================================================
     SUCCESS PAGE
===================================================== -->

<div class="container">

    <div class="success-card">


        <div class="success-icon">

            ✓

        </div>


        <h1 class="success-title">

            Payment <span>Successful!</span>

        </h1>


        <p class="success-text">

            Your TicketFlix booking has been confirmed.
            Enjoy your movie! 🎬

        </p>


        <div class="booking-number">

            <small>

                Booking ID

            </small>


            <strong>

                <?= htmlspecialchars(
                    $booking_number
                ); ?>

            </strong>

        </div>


        <div class="success-details">


            <div class="success-box">

                <small>

                    Movie

                </small>


                <strong>

                    <?= htmlspecialchars(
                        $showtime['movie_name']
                    ); ?>

                </strong>

            </div>


            <div class="success-box">

                <small>

                    Seats

                </small>


                <strong>

                    <?= htmlspecialchars(
                        implode(
                            ', ',
                            $seat_labels
                        )
                    ); ?>

                </strong>

            </div>


            <div class="success-box">

                <small>

                    Date

                </small>


                <strong>

                    <?= date(
                        "d M Y",
                        strtotime(
                            $showtime['show_date']
                        )
                    ); ?>

                </strong>

            </div>


            <div class="success-box">

                <small>

                    Time

                </small>


                <strong>

                    <?= date(
                        "h:i A",
                        strtotime(
                            $showtime['show_time']
                        )
                    ); ?>

                </strong>

            </div>


            <div class="success-box">

                <small>

                    Payment

                </small>


                <strong>

                    <?= htmlspecialchars(
                        strtoupper(
                            $payment_method
                        )
                    ); ?>

                </strong>

            </div>


            <div class="success-box">

                <small>

                    Total Paid

                </small>


                <strong>

                    ₹<?= number_format(
                        $total_amount,
                        2
                    ); ?>

                </strong>

            </div>


        </div>


        <a
            href="index.php"
            class="home-btn"
        >

            🏠 Back to Home

        </a>


    </div>

</div>


<?php else: ?>


<!-- =====================================================
     PAYMENT PAGE
===================================================== -->

<main class="container">


    <div class="payment-wrapper">


        <!-- =================================================
             LEFT SUMMARY
        ================================================== -->

        <section class="summary-card">


            <h2 class="summary-title">

                Booking Summary

            </h2>


            <div class="movie-title">

                <?= htmlspecialchars(
                    $showtime['movie_name']
                ); ?>

            </div>


            <div class="summary-info">


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
                        Location
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $showtime['city']
                        ); ?>,

                        <?= htmlspecialchars(
                            $showtime['state']
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


            </div>


            <div class="divider"></div>


            <div class="seat-title">

                SELECTED SEATS

            </div>


            <div class="seat-list">

                <?php foreach (
                    $seat_labels as
                    $seat_label
                ): ?>

                    <span class="seat-chip">

                        <?= htmlspecialchars(
                            $seat_label
                        ); ?>

                    </span>

                <?php endforeach; ?>

            </div>


            <div class="divider"></div>


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


        </section>



        <!-- =================================================
             RIGHT PAYMENT
        ================================================== -->

        <section class="payment-card">


            <span class="test-mode">

                DEMO MODE

            </span>


            <h1 class="payment-heading">

                Complete Payment

            </h1>


            <p class="payment-subtitle">

                Choose your preferred payment method

            </p>


            <?php if (
                $payment_error !== ''
            ): ?>

                <div class="payment-error">

                    ⚠️

                    <?= htmlspecialchars(
                        $payment_error
                    ); ?>

                </div>

            <?php endif; ?>


            <div class="demo-notice">

                <strong>

                    🎭 Dummy Payment

                </strong>

                This is a demo payment page.
                No real money will be charged.

            </div>


            <form
                method="POST"
                action="booking.php"
                id="paymentForm"
            >


                <!-- SHOWTIME -->

                <input
                    type="hidden"
                    name="showtime_id"
                    value="<?= (int)$showtime_id; ?>"
                >


                <!-- SEATS -->

                <?php foreach (
                    $seat_ids as
                    $seat_id
                ): ?>

                    <input
                        type="hidden"
                        name="seat_ids[]"
                        value="<?= (int)$seat_id; ?>"
                    >

                <?php endforeach; ?>


                <!-- EMAIL -->

                <label class="form-label">

                    Email

                </label>


                <input
                    type="email"
                    name="email"
                    class="email-input"
                    placeholder="email@example.com"
                    value="<?= htmlspecialchars(
                        $email
                    ); ?>"
                    required
                >


                <!-- PAYMENT METHOD -->

                <div class="method-title">

                    Payment method

                </div>


                <div class="payment-method">


                    <!-- CARD -->

                    <label class="method-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="card"
                            required
                        >


                        <div class="method-icon">

                            💳

                        </div>


                        <div>

                            <span class="method-name">

                                Card

                            </span>


                            <span class="method-small">

                                Visa • Mastercard • RuPay

                            </span>

                        </div>

                    </label>



                    <!-- UPI -->

                    <label class="method-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="upi"
                        >


                        <div class="method-icon">

                            📱

                        </div>


                        <div>

                            <span class="method-name">

                                UPI

                            </span>


                            <span class="method-small">

                                Google Pay • PhonePe • Paytm

                            </span>

                        </div>

                    </label>



                    <!-- NET BANKING -->

                    <label class="method-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="netbanking"
                        >


                        <div class="method-icon">

                            🏦

                        </div>


                        <div>

                            <span class="method-name">

                                Net Banking

                            </span>


                            <span class="method-small">

                                All major banks supported

                            </span>

                        </div>

                    </label>


                </div>


                <!-- DUMMY PAYMENT -->

                <input
                    type="hidden"
                    name="dummy_payment"
                    value="1"
                >


                <!-- PAY -->

                <button
                    type="submit"
                    class="pay-btn"
                >

                    🔒 Pay ₹<?= number_format(
                        $total_amount,
                        2
                    ); ?>

                </button>


                <div class="pay-note">

                    🔐 Demo payment • No real transaction

                </div>


            </form>


        </section>


    </div>

</main>


<?php endif; ?>


</body>

</html>