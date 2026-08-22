<?php

session_start();

require_once __DIR__ . "/config.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   GET SHOWTIME ID
========================================================= */

$showtime_id = 0;

if (isset($_POST['showtime_id'])) {

    $showtime_id = (int) $_POST['showtime_id'];

} elseif (isset($_GET['showtime_id'])) {

    $showtime_id = (int) $_GET['showtime_id'];

} elseif (isset($_GET['id'])) {

    $showtime_id = (int) $_GET['id'];

}


/* =========================================================
   INVALID SHOWTIME
========================================================= */

if ($showtime_id <= 0) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:80px;
        '>

            <h2 style='color:red;'>
                Invalid Showtime
            </h2>

            <p>
                Showtime information was not received.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    display:inline-block;
                    padding:12px 25px;
                    background:#f4c430;
                    color:#21102f;
                    text-decoration:none;
                    border-radius:8px;
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

$user_email = '';

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

if (!$user_stmt->execute()) {

    die(
        "User query failed: " .
        htmlspecialchars($user_stmt->error)
    );

}

$user_result = $user_stmt->get_result();

$user_data = $user_result->fetch_assoc();

$user_stmt->close();


if (!$user_data) {

    die("Registered user not found.");

}

$user_email = trim($user_data['email']);


/* =========================================================
   GET SHOWTIME + MOVIE + SCREEN + THEATER
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
        m.poster_image,

        s.screen_name,

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

if (!$stmt->execute()) {

    die(
        "Showtime query failed: " .
        htmlspecialchars($stmt->error)
    );

}

$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   SHOWTIME NOT FOUND
========================================================= */

if (!$showtime) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:80px;
        '>

            <h2 style='color:red;'>
                Showtime Not Found
            </h2>

            <p>
                Showtime ID:
                " . htmlspecialchars($showtime_id) . "
                does not exist.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    display:inline-block;
                    padding:12px 25px;
                    background:#f4c430;
                    color:#21102f;
                    text-decoration:none;
                    border-radius:8px;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   GET SELECTED SEAT IDS
========================================================= */

$seat_ids = [];


if (isset($_POST['seat_ids'])) {

    if (is_array($_POST['seat_ids'])) {

        $seat_ids = $_POST['seat_ids'];

    } else {

        $seat_ids = explode(
            ',',
            $_POST['seat_ids']
        );

    }

} elseif (isset($_GET['seat_ids'])) {

    if (is_array($_GET['seat_ids'])) {

        $seat_ids = $_GET['seat_ids'];

    } else {

        $seat_ids = explode(
            ',',
            $_GET['seat_ids']
        );

    }

}


/* =========================================================
   CLEAN SEAT IDS
========================================================= */

$seat_ids = array_map(
    'intval',
    $seat_ids
);


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
   NO SEATS SELECTED
========================================================= */

if (empty($seat_ids)) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:80px;
        '>

            <h2 style='color:red;'>
                No Seats Selected
            </h2>

            <p>
                Please select at least one seat.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    display:inline-block;
                    padding:12px 25px;
                    background:#f4c430;
                    color:#21102f;
                    text-decoration:none;
                    border-radius:8px;
                    font-weight:bold;
                '
            >
                ← Select Seats
            </a>

        </div>
    ");

}


/* =========================================================
   GET SELECTED SEATS FROM DATABASE
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


$seat_sql = "

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


$seat_stmt =
    $conn->prepare($seat_sql);


if (!$seat_stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );

}


$params = $seat_ids;

$params[] =
    (int)$showtime['screen_id'];


$final_types =
    $types . "i";


$seat_stmt->bind_param(
    $final_types,
    ...$params
);


if (!$seat_stmt->execute()) {

    die(
        "Seat query failed: " .
        htmlspecialchars($seat_stmt->error)
    );

}


$seat_result =
    $seat_stmt->get_result();


$selected_seats = [];


while (
    $seat =
    $seat_result->fetch_assoc()
) {

    $selected_seats[] =
        $seat;

}


$seat_stmt->close();


/* =========================================================
   VALIDATE ALL SEATS
========================================================= */

if (
    count($selected_seats)
    !==
    count($seat_ids)
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:80px;
        '>

            <h2 style='color:red;'>
                Invalid Seats
            </h2>

            <p>
                One or more selected seats do not belong
                to this screen.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    display:inline-block;
                    padding:12px 25px;
                    background:#f4c430;
                    color:#21102f;
                    text-decoration:none;
                    border-radius:8px;
                    font-weight:bold;
                '
            >
                ← Select Seats Again
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

    SELECT
        bs.seat_id

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


$check_stmt =
    $conn->prepare($check_sql);


if (!$check_stmt) {

    die(
        "Booking check error: " .
        htmlspecialchars($conn->error)
    );

}


$check_params =
    array_merge(
        [$showtime_id],
        $seat_ids
    );


$check_final_types =
    'i' . $check_types;


$check_stmt->bind_param(
    $check_final_types,
    ...$check_params
);


if (!$check_stmt->execute()) {

    die(
        "Booking check failed: " .
        htmlspecialchars(
            $check_stmt->error
        )
    );

}


$check_result =
    $check_stmt->get_result();


if (
    $check_result->num_rows > 0
) {

    $check_stmt->close();

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:80px;
        '>

            <h2 style='color:red;'>
                Seat Already Booked
            </h2>

            <p>
                One or more selected seats have already
                been booked for this showtime.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    display:inline-block;
                    padding:12px 25px;
                    background:#f4c430;
                    color:#21102f;
                    text-decoration:none;
                    border-radius:8px;
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
   SEAT LABELS
========================================================= */

$seat_labels = [];


foreach (
    $selected_seats
    as $seat
) {

    $seat_labels[] =
        $seat['seat_row']
        .
        $seat['seat_number'];

}


/* =========================================================
   PRICE CALCULATION
========================================================= */

$ticket_price =
    (float)$showtime['price'];


$seat_count =
    count($selected_seats);


$subtotal =
    $ticket_price *
    $seat_count;


$booking_fee = 0;


$total_amount =
    $subtotal +
    $booking_fee;


/* =========================================================
   CONVERT SEAT IDS FOR PAYMENT PAGE
========================================================= */

$seat_ids_string =
    implode(
        ',',
        $seat_ids
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
    Booking | TicketFlix
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {

    box-sizing:border-box;

    margin:0;

    padding:0;

}


/* =========================================================
   BODY
========================================================= */

body {

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

    min-height:100vh;

    color:white;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height:80px;

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


.logo {

    font-size:29px;

    font-weight:800;

}


.logo span {

    color:#f4c430;

}


.back-btn {

    text-decoration:none;

    color:white;

    padding:10px 20px;

    border-radius:25px;

    background:
        rgba(255,255,255,.07);

    border:
        1px solid
        rgba(255,255,255,.15);

}


.back-btn:hover {

    background:#f4c430;

    color:#21102f;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width:min(1100px,92%);

    margin:45px auto 70px;

}


/* =========================================================
   GRID
========================================================= */

.booking-wrapper {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:25px;

}


/* =========================================================
   CARD
========================================================= */

.card {

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


/* =========================================================
   MOVIE
========================================================= */

.movie-title {

    color:#f4c430;

    font-size:28px;

    font-weight:800;

    margin-bottom:25px;

}


.info {

    display:flex;

    justify-content:space-between;

    gap:20px;

    padding:12px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

    color:#cfc6d9;

}


.info strong {

    color:white;

    text-align:right;

}


/* =========================================================
   SEATS
========================================================= */

.seat-heading {

    margin-top:25px;

    margin-bottom:12px;

    color:#aaa1b4;

    font-size:13px;

}


.seats {

    display:flex;

    flex-wrap:wrap;

    gap:8px;

}


.seat {

    background:#f4c430;

    color:#21102f;

    padding:8px 13px;

    border-radius:8px;

    font-weight:bold;

}


/* =========================================================
   PRICE
========================================================= */

.price-box {

    margin-top:25px;

    padding-top:20px;

    border-top:
        1px solid
        rgba(255,255,255,.1);

}


.price-row {

    display:flex;

    justify-content:space-between;

    margin:12px 0;

    color:#ccc;

}


.total {

    font-size:24px;

    color:#f4c430;

    font-weight:bold;

}


/* =========================================================
   PAYMENT CARD
========================================================= */

.payment-card {

    background:white;

    color:#29232f;

    border-radius:22px;

    padding:30px;

}


.payment-card h1 {

    font-size:28px;

    margin-bottom:8px;

}


.payment-card p {

    color:#817987;

    margin-bottom:25px;

}


/* =========================================================
   EMAIL
========================================================= */

.email-box {

    padding:14px;

    background:#f4effa;

    border-radius:10px;

    margin-bottom:22px;

}


.email-box small {

    display:block;

    color:#817987;

    margin-bottom:5px;

}


.email-box strong {

    color:#5b2c83;

    word-break:break-all;

}


/* =========================================================
   PAYMENT METHOD
========================================================= */

.method-title {

    font-weight:bold;

    margin-bottom:12px;

}


.method {

    border:
        1px solid
        #ddd8e0;

    border-radius:10px;

    overflow:hidden;

    margin-bottom:22px;

}


.method label {

    display:flex;

    align-items:center;

    gap:12px;

    min-height:62px;

    padding:0 15px;

    cursor:pointer;

    border-bottom:
        1px solid
        #eee;

}


.method label:last-child {

    border-bottom:none;

}


.method label:hover {

    background:#faf7ff;

}


.method input {

    width:18px;

    height:18px;

    accent-color:#7c3aed;

}


.icon {

    font-size:20px;

}


/* =========================================================
   PAY BUTTON
========================================================= */

.pay-btn {

    width:100%;

    height:55px;

    border:none;

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

    transition:.2s;

}


.pay-btn:hover {

    transform:translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(244,196,48,.3);

}


.note {

    text-align:center;

    font-size:11px;

    color:#999;

    margin-top:12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .booking-wrapper {

        grid-template-columns:1fr;

    }

}


@media(max-width:500px) {

    .container {

        width:94%;

        margin-top:25px;

    }


    .card,
    .payment-card {

        padding:22px;

    }


    .logo {

        font-size:23px;

    }


    .info {

        flex-direction:column;

        gap:5px;

    }


    .info strong {

        text-align:left;

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


    <a
        href="javascript:history.back()"
        class="back-btn"
    >

        ← Back

    </a>

</header>



<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


<div class="booking-wrapper">


<!-- =====================================================
     BOOKING SUMMARY
====================================================== -->

<section class="card">


    <div class="movie-title">

        <?= htmlspecialchars(
            $showtime['movie_name']
        ); ?>

    </div>


    <div class="info">

        <span>
            Theater
        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['theater_name']
            ); ?>

        </strong>

    </div>


    <div class="info">

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


    <div class="info">

        <span>
            Screen
        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['screen_name']
            ); ?>

        </strong>

    </div>


    <div class="info">

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


    <div class="info">

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


    <!-- SELECTED SEATS -->

    <div class="seat-heading">

        SELECTED SEATS

    </div>


    <div class="seats">

        <?php foreach (
            $seat_labels
            as $seat
        ): ?>

            <span class="seat">

                <?= htmlspecialchars(
                    $seat
                ); ?>

            </span>

        <?php endforeach; ?>

    </div>


    <!-- PRICE -->

    <div class="price-box">


        <div class="price-row">

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


        <div class="price-row">

            <span>
                Convenience Fee
            </span>

            <strong>
                ₹0.00
            </strong>

        </div>


        <div class="price-row total">

            <span>
                Total
            </span>

            <span>

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </span>

        </div>


    </div>


</section>



<!-- =====================================================
     PAYMENT SECTION
====================================================== -->

<section class="payment-card">


    <h1>
        Complete Payment
    </h1>


    <p>
        Select your payment method and continue.
    </p>


    <!-- REGISTERED EMAIL -->

    <div class="email-box">

        <small>
            Booking confirmation will be sent to:
        </small>


        <strong>

            <?= htmlspecialchars(
                $user_email
            ); ?>

        </strong>

    </div>



    <!-- =================================================
         PAYMENT FORM
    ================================================== -->

    <form
        method="POST"
        action="payment.php"
    >


        <!-- SHOWTIME -->

        <input
            type="hidden"
            name="showtime_id"
            value="<?= (int)$showtime_id; ?>"
        >


        <!-- SEATS -->

        <input
            type="hidden"
            name="seat_ids"
            value="<?= htmlspecialchars(
                $seat_ids_string
            ); ?>"
        >


        <!-- PAYMENT METHOD -->

        <div class="method-title">

            Payment Method

        </div>


        <div class="method">


            <label>

                <input
                    type="radio"
                    name="payment_method"
                    value="card"
                    required
                >

                <span class="icon">
                    💳
                </span>

                <span>
                    Card
                </span>

            </label>


            <label>

                <input
                    type="radio"
                    name="payment_method"
                    value="upi"
                >

                <span class="icon">
                    📱
                </span>

                <span>
                    UPI
                </span>

            </label>


            <label>

                <input
                    type="radio"
                    name="payment_method"
                    value="netbanking"
                >

                <span class="icon">
                    🏦
                </span>

                <span>
                    Net Banking
                </span>

            </label>


        </div>


        <!-- DEMO PAYMENT -->

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


        <div class="note">

            🔐 Demo Payment —
            No real money will be charged.

        </div>


    </form>


</section>


</div>


</main>


</body>

</html>