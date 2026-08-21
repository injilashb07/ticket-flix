<?php

session_start();

require_once __DIR__ . "/config.php";

/*
|--------------------------------------------------------------------------
| PHPMailer Booking Email Function
|--------------------------------------------------------------------------
| IMPORTANT:
| Agar aapke mail wale code ka filename alag hai,
| to neeche filename change karo.
|
| Example:
| require_once __DIR__ . "/send_mail.php";
|
*/
require_once __DIR__ . "/send_mail.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   ONLY POST REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit();

}


/* =========================================================
   GET FORM DATA
========================================================= */

$showtime_id = isset($_POST['showtime_id'])
    ? (int) $_POST['showtime_id']
    : 0;


$seat_ids = $_POST['seat_ids'] ?? [];


$payment_method = isset($_POST['payment_method'])
    ? trim($_POST['payment_method'])
    : '';


/* =========================================================
   CLEAN SEAT IDS
========================================================= */

if (!is_array($seat_ids)) {

    $seat_ids = explode(',', $seat_ids);

}


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


/* =========================================================
   BASIC VALIDATION
========================================================= */

if (
    $showtime_id <= 0 ||
    empty($seat_ids) ||
    $payment_method === ''
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Payment Error
            </h2>

            <p>
                Showtime, seats or payment method is missing.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   GET REGISTERED USER EMAIL
========================================================= */

$user_stmt = $conn->prepare("
    SELECT email
    FROM users
    WHERE id = ?
    LIMIT 1
");


if (!$user_stmt) {

    die(
        "User query error: " .
        htmlspecialchars($conn->error)
    );

}


$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


if (!$user || empty($user['email'])) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Email Not Found
            </h2>

            <p>
                Registered user's email address was not found.
            </p>

        </div>
    ");

}


$user_email = trim($user['email']);


/* =========================================================
   GET SHOWTIME DETAILS
========================================================= */

$stmt = $conn->prepare("

    SELECT

        st.id AS showtime_id,
        st.movie_id,
        st.screen_id,
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

    die("Showtime not found.");

}


/* =========================================================
   GET SELECTED SEATS
========================================================= */

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
        seat_type,
        is_active

    FROM seats

    WHERE id IN ($placeholders)

    AND screen_id = ?

    AND is_active = 1

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


$params = $seat_ids;

$params[] = (int)$showtime['screen_id'];

$final_types = $types . "i";


$stmt->bind_param(
    $final_types,
    ...$params
);


$stmt->execute();

$seat_result = $stmt->get_result();


$selected_seats = [];


while ($seat = $seat_result->fetch_assoc()) {

    $selected_seats[] = $seat;

}


$stmt->close();


/* =========================================================
   VALIDATE SEATS
========================================================= */

if (
    count($selected_seats) !== count($seat_ids)
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Invalid Seats
            </h2>

            <p>
                One or more selected seats are invalid.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   CHECK ALREADY BOOKED SEATS
========================================================= */

$check_placeholders = implode(
    ',',
    array_fill(
        0,
        count($seat_ids),
        '?'
    )
);


$check_types = str_repeat(
    'i',
    count($seat_ids)
);


$check_sql = "

    SELECT bs.seat_id

    FROM booking_seats bs

    INNER JOIN bookings b
        ON b.id = bs.booking_id

    WHERE b.showtime_id = ?

    AND bs.seat_id IN ($check_placeholders)

    AND (
        b.booking_status = 'pending'
        OR
        b.booking_status = 'confirmed'
    )

    LIMIT 1

";


$check_stmt = $conn->prepare($check_sql);


if (!$check_stmt) {

    die(
        "Booking check error: " .
        htmlspecialchars($conn->error)
    );

}


$check_params = array_merge(
    [$showtime_id],
    $seat_ids
);


$check_final_types =
    'i' . $check_types;


$check_stmt->bind_param(
    $check_final_types,
    ...$check_params
);


$check_stmt->execute();

$check_result = $check_stmt->get_result();


if ($check_result->num_rows > 0) {

    $check_stmt->close();

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Seat Already Booked
            </h2>

            <p>
                One or more selected seats have already been booked.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Select Different Seats
            </a>

        </div>
    ");

}


$check_stmt->close();


/* =========================================================
   CALCULATE PRICE
========================================================= */

$ticket_price =
    (float)$showtime['price'];


$seat_count =
    count($selected_seats);


$subtotal =
    $ticket_price * $seat_count;


$booking_fee = 0;


$total_amount =
    $subtotal + $booking_fee;


/* =========================================================
   GENERATE UNIQUE BOOKING REFERENCE
========================================================= */

$booking_reference =
    'TF'
    . date('Ymd')
    . strtoupper(
        substr(
            bin2hex(random_bytes(4)),
            0,
            8
        )
    );


/* =========================================================
   PAYMENT SUCCESS
========================================================= */

$payment_status = 'success';

$booking_status = 'confirmed';


/* =========================================================
   DATABASE TRANSACTION
========================================================= */

$conn->begin_transaction();


try {

    /* =====================================================
       INSERT BOOKING

       IMPORTANT:
       Your database has booking_reference,
       NOT booking_number.
    ===================================================== */

    $booking_sql = "

        INSERT INTO bookings

        (
            booking_reference,
            user_id,
            showtime_id,
            total_amount,
            booking_status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )

    ";


    $booking_stmt =
        $conn->prepare($booking_sql);


    if (!$booking_stmt) {

        throw new Exception(
            "Booking insert error: " .
            $conn->error
        );

    }


    $booking_stmt->bind_param(
        "siids",
        $booking_reference,
        $user_id,
        $showtime_id,
        $total_amount,
        $booking_status
    );


    if (!$booking_stmt->execute()) {

        throw new Exception(
            "Booking could not be saved: " .
            $booking_stmt->error
        );

    }


    $booking_id =
        $conn->insert_id;


    $booking_stmt->close();


    /* =====================================================
       INSERT BOOKING SEATS
    ===================================================== */

    $seat_stmt = $conn->prepare("

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

        throw new Exception(
            "Booking seats error: " .
            $conn->error
        );

    }


    foreach ($selected_seats as $seat) {

        $seat_id =
            (int)$seat['id'];


        $seat_stmt->bind_param(
            "ii",
            $booking_id,
            $seat_id
        );


        if (!$seat_stmt->execute()) {

            throw new Exception(
                "Seat booking failed: " .
                $seat_stmt->error
            );

        }

    }


    $seat_stmt->close();


    /* =====================================================
       COMMIT DATABASE
    ===================================================== */

    $conn->commit();


}
catch (Exception $e) {

    $conn->rollback();


    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Booking Failed
            </h2>

            <p>
                " .
                htmlspecialchars(
                    $e->getMessage()
                )
                . "
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   SEND BOOKING CONFIRMATION EMAIL
========================================================= */

$mail_sent = false;


/*
|--------------------------------------------------------------------------
| Create readable seat labels
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
| Send Email
|--------------------------------------------------------------------------
*/

$mail_sent = sendBookingConfirmationEmail(

    $user_email,

    $showtime['movie_name'],

    date(
        "d M Y",
        strtotime($showtime['show_date'])
    ),

    date(
        "h:i A",
        strtotime($showtime['show_time'])
    ),

    $showtime['theater_name'],

    $showtime['screen_name'],

    $selected_seats,

    $total_amount,

    $booking_reference

);

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
    Payment Successful | TicketFlix
</title>


<style>

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}


body {

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            #35145c,
            #170b26 45%,
            #09060f
        );

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 25px;

}


.success-card {

    width: min(700px, 100%);

    background:
        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid
        #634085;

    border-radius: 25px;

    padding: 45px 35px;

    text-align: center;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.4);

}


.success-icon {

    width: 90px;

    height: 90px;

    margin: 0 auto 22px;

    border-radius: 50%;

    background: #f4c430;

    color: #241033;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 48px;

    font-weight: bold;

}


h1 {

    font-size: 32px;

    margin-bottom: 12px;

}


h1 span {

    color: #f4c430;

}


.success-text {

    color: #c9bfd3;

    margin-bottom: 28px;

    line-height: 1.6;

}


.booking-number {

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.1);

    border-radius: 13px;

    padding: 18px;

    margin-bottom: 22px;

}


.booking-number small {

    display: block;

    color: #9d94a7;

    margin-bottom: 7px;

}


.booking-number strong {

    color: #f4c430;

    font-size: 23px;

    letter-spacing: 2px;

}


.details {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

    margin-bottom: 22px;

}


.detail-box {

    background:
        rgba(255,255,255,.05);

    border-radius: 10px;

    padding: 14px;

    text-align: left;

}


.detail-box small {

    display: block;

    color: #91879b;

    margin-bottom: 6px;

    font-size: 11px;

}


.detail-box strong {

    font-size: 14px;

}


.email-box {

    padding: 13px;

    background:
        rgba(244,196,48,.08);

    border:
        1px solid
        rgba(244,196,48,.25);

    border-radius: 10px;

    color: #d8cfdf;

    margin-bottom: 12px;

    font-size: 13px;

}


.email-box strong {

    color: #f4c430;

}


.mail-status {

    padding: 11px;

    border-radius: 9px;

    margin-bottom: 25px;

    font-size: 13px;

}


.mail-success {

    background: rgba(46,204,113,.12);

    border: 1px solid rgba(46,204,113,.3);

    color: #72e6a0;

}


.mail-failed {

    background: rgba(255,100,100,.10);

    border: 1px solid rgba(255,100,100,.25);

    color: #ff9999;

}


.home-btn {

    display: inline-block;

    padding: 13px 28px;

    border-radius: 30px;

    background: #f4c430;

    color: #241033;

    text-decoration: none;

    font-weight: 800;

}


.home-btn:hover {

    background: #ffd84f;

}


@media(max-width:550px) {

    .success-card {

        padding: 35px 20px;

    }


    h1 {

        font-size: 27px;

    }


    .details {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<div class="success-card">


    <div class="success-icon">
        ✓
    </div>


    <h1>

        Payment
        <span>Successful!</span>

    </h1>


    <p class="success-text">

        Your TicketFlix booking has been
        successfully confirmed. 🎬🍿

    </p>


    <!-- BOOKING REFERENCE -->

    <div class="booking-number">

        <small>
            Booking Reference
        </small>

        <strong>

            <?= htmlspecialchars(
                $booking_reference
            ); ?>

        </strong>

    </div>


    <!-- DETAILS -->

    <div class="details">


        <div class="detail-box">

            <small>
                Movie
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['movie_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

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


        <div class="detail-box">

            <small>
                Theater
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['theater_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Screen
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['screen_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

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


        <div class="detail-box">

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


        <div class="detail-box">

            <small>
                Payment Method
            </small>

            <strong>

                <?= htmlspecialchars(
                    strtoupper(
                        $payment_method
                    )
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

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


    <!-- REGISTERED EMAIL -->

    <div class="email-box">

        📧 Confirmation email sent to:

        <strong>

            <?= htmlspecialchars(
                $user_email
            ); ?>

        </strong>

    </div>


    <?php if ($mail_sent): ?>

        <div class="mail-status mail-success">

            ✅ Booking confirmation email sent successfully!

        </div>

    <?php else: ?>

        <div class="mail-status mail-failed">

            ⚠️ Booking successful, but confirmation email
            could not be sent. Please check PHPMailer/SMTP settings.

        </div>

    <?php endif; ?>


    <a
        href="index.php"
        class="home-btn"
    >

        🏠 Back to Home

    </a>


</div>


</body>

</html>