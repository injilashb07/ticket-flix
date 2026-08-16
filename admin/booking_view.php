<?php

session_start();

require_once "../config.php";

/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION['admin_id'])) {

    header("Location: ../admin_login.php");
    exit();

}


/* =========================================================
   GET BOOKING ID
========================================================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: bookings.php");
    exit();

}

$booking_id = (int) $_GET['id'];


/* =========================================================
   GET BOOKING DETAILS
========================================================= */

$sql = "

SELECT

    b.id,
    b.booking_reference,
    b.total_amount,
    b.booking_status,
    b.payment_status,

    u.first_name,
    u.last_name,
    u.email,
    u.phone,

    m.name AS movie_name,

    t.name AS theater_name,
    t.address AS theater_address,
    t.city AS theater_city,
    t.state AS theater_state,

    sc.screen_name,
    sc.total_seats,

    st.show_date,
    st.show_time,
    st.end_time,
    st.price AS show_price

FROM bookings b

INNER JOIN users u
    ON b.user_id = u.id

INNER JOIN showtimes st
    ON b.showtime_id = st.id

INNER JOIN movies m
    ON st.movie_id = m.id

INNER JOIN screens sc
    ON st.screen_id = sc.id

INNER JOIN theaters t
    ON sc.theater_id = t.id

WHERE b.id = ?

LIMIT 1

";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die("Booking query error: " . $conn->error);

}


$stmt->bind_param("i", $booking_id);

$stmt->execute();

$result = $stmt->get_result();

$booking = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CHECK BOOKING EXISTS
========================================================= */

if (!$booking) {

    header("Location: bookings.php");
    exit();

}


/* =========================================================
   GET BOOKING SEATS
========================================================= */

$seat_sql = "

SELECT

    s.row_number,
    s.seat_number,
    s.seat_type,
    bs.seat_price

FROM booking_seats bs

INNER JOIN seats s
    ON bs.seat_id = s.id

WHERE bs.booking_id = ?

ORDER BY
    s.row_number ASC,
    s.seat_number ASC

";


$seat_stmt = $conn->prepare($seat_sql);


if (!$seat_stmt) {

    die("Seat query error: " . $conn->error);

}


$seat_stmt->bind_param("i", $booking_id);

$seat_stmt->execute();

$seat_result = $seat_stmt->get_result();


/* =========================================================
   STATUS CLASS
========================================================= */

$booking_status = strtolower($booking['booking_status']);

$payment_status = strtolower($booking['payment_status']);


/* =========================================================
   FORMAT DATE
========================================================= */

$show_date = date(
    "d M Y",
    strtotime($booking['show_date'])
);


/* =========================================================
   FORMAT TIME
========================================================= */

$show_time = date(
    "h:i A",
    strtotime($booking['show_time'])
);

$end_time = date(
    "h:i A",
    strtotime($booking['end_time'])
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

<title>Booking Details | TicketFlix</title>


<!-- GOOGLE FONT -->

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<!-- FONT AWESOME -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {

    margin: 0;
    padding: 0;
    box-sizing: border-box;

}

body {

    min-height: 100vh;

    font-family: 'Poppins', sans-serif;

    background:

        radial-gradient(
            circle at top right,
            rgba(126,87,194,.22),
            transparent 35%
        ),

        radial-gradient(
            circle at bottom left,
            rgba(212,175,55,.08),
            transparent 35%
        ),

        #100b18;

    color: white;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    height: 100vh;

    position: fixed;

    left: 0;
    top: 0;

    background: rgba(18,12,28,.98);

    border-right:
        1px solid rgba(212,175,55,.18);

    padding: 30px 18px;

    z-index: 100;

}


.logo {

    text-align: center;

    font-size: 26px;

    font-weight: 800;

    margin-bottom: 40px;

}


.logo i {

    color: #d4af37;

}


.logo span {

    color: #d4af37;

}


.admin-label {

    text-align: center;

    color: #888;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 2px;

    margin-bottom: 20px;

}


.sidebar a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 13px 15px;

    margin-bottom: 8px;

    color: #bbb;

    text-decoration: none;

    border-radius: 12px;

    font-size: 14px;

    transition: .3s;

}


.sidebar a i {

    width: 20px;

    text-align: center;

}


.sidebar a:hover,

.sidebar a.active {

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

}


.logout {

    position: absolute;

    bottom: 25px;

    left: 18px;
    right: 18px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    padding: 35px;

}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.page-title h1 {

    font-size: 28px;

}


.page-title h1 span {

    color: #d4af37;

}


.page-title p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;

}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 11px 18px;

    border-radius: 12px;

    text-decoration: none;

    color: #d4af37;

    background: rgba(212,175,55,.10);

    border:
        1px solid rgba(212,175,55,.20);

    font-size: 13px;

    transition: .3s;

}


.back-btn:hover {

    background:
        rgba(212,175,55,.18);

}


/* =========================================================
   BOOKING HEADER CARD
========================================================= */

.booking-header {

    background:
        linear-gradient(
            135deg,
            rgba(212,175,55,.14),
            rgba(126,87,194,.10)
        );

    border:
        1px solid rgba(212,175,55,.20);

    border-radius: 22px;

    padding: 25px;

    margin-bottom: 22px;

}


.booking-reference {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.reference-label {

    color: #888;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.reference {

    color: #d4af37;

    font-size: 24px;

    font-weight: 700;

    margin-top: 5px;

}


.status-area {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}


.status {

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

    text-transform: capitalize;

}


.confirmed {

    color: #61e69b;

    background:
        rgba(46,204,113,.12);

}


.pending {

    color: #f1d46a;

    background:
        rgba(241,196,15,.12);

}


.cancelled {

    color: #ff8175;

    background:
        rgba(231,76,60,.12);

}


.completed {

    color: #61e69b;

    background:
        rgba(46,204,113,.12);

}


.failed {

    color: #ff8175;

    background:
        rgba(231,76,60,.12);

}


/* =========================================================
   GRID
========================================================= */

.details-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 22px;

}


/* =========================================================
   CARD
========================================================= */

.card {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 25px;

}


.card-title {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 17px;

    margin-bottom: 20px;

}


.card-title i {

    color: #d4af37;

}


.card-title span {

    color: #d4af37;

}


/* =========================================================
   DETAIL ROW
========================================================= */

.detail-row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 12px 0;

    border-bottom:
        1px solid rgba(255,255,255,.06);

}


.detail-row:last-child {

    border-bottom: none;

}


.detail-label {

    color: #777;

    font-size: 12px;

}


.detail-value {

    color: #eee;

    font-size: 12px;

    font-weight: 500;

    text-align: right;

}


/* =========================================================
   MOVIE
========================================================= */

.movie-box {

    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 18px;

}


.movie-icon {

    width: 55px;

    height: 55px;

    border-radius: 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 23px;

}


.movie-name {

    font-size: 18px;

    font-weight: 600;

}


.movie-subtitle {

    color: #777;

    font-size: 11px;

    margin-top: 3px;

}


/* =========================================================
   SEATS
========================================================= */

.seats-container {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

}


.seat {

    min-width: 80px;

    padding: 10px 12px;

    text-align: center;

    border-radius: 11px;

    background:
        rgba(212,175,55,.10);

    border:
        1px solid rgba(212,175,55,.20);

}


.seat-number {

    color: #fff;

    font-size: 13px;

    font-weight: 600;

}


.seat-type {

    color: #888;

    font-size: 9px;

    text-transform: capitalize;

    margin-top: 2px;

}


.seat-price {

    color: #d4af37;

    font-size: 10px;

    margin-top: 3px;

}


/* =========================================================
   TOTAL
========================================================= */

.total-card {

    margin-top: 22px;

    padding: 20px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            rgba(212,175,55,.15),
            rgba(126,87,194,.10)
        );

    border:
        1px solid rgba(212,175,55,.18);

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.total-label {

    color: #999;

    font-size: 12px;

}


.total-amount {

    color: #d4af37;

    font-size: 25px;

    font-weight: 700;

}


/* =========================================================
   EMPTY SEATS
========================================================= */

.no-seats {

    text-align: center;

    padding: 25px;

    color: #777;

    font-size: 12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .details-grid {

        grid-template-columns: 1fr;

    }

}


@media(max-width:700px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;

    }


    .logo {

        font-size: 0;

    }


    .logo i {

        font-size: 23px;

    }


    .admin-label,

    .sidebar a span {

        display: none;

    }


    .sidebar a {

        justify-content: center;

    }


    .logout {

        left: 10px;

        right: 10px;

    }


    .main {

        margin-left: 70px;

        padding: 20px;

    }


    .topbar {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;

    }


    .booking-reference {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </div>


    <div class="admin-label">

        Admin Panel

    </div>


    <a href="dashboard.php">

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>


    <a href="users.php">

        <i class="fa-solid fa-users"></i>

        <span>Users</span>

    </a>


    <a
        href="bookings.php"
        class="active"
    >

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>


    <a href="../movies.php">

        <i class="fa-solid fa-film"></i>

        <span>Movies</span>

    </a>


    <a href="../theaters.php">

        <i class="fa-solid fa-building"></i>

        <span>Theaters</span>

    </a>


    <a href="../showtimes.php">

        <i class="fa-solid fa-clock"></i>

        <span>Showtimes</span>

    </a>


    <a href="../index.php">

        <i class="fa-solid fa-globe"></i>

        <span>View Website</span>

    </a>


    <a
        href="../logout.php"
        class="logout"
    >

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>Logout</span>

    </a>


</aside>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOP BAR -->

    <div class="topbar">


        <div class="page-title">

            <h1>

                Booking <span>Details</span>

            </h1>

            <p>

                Complete information about this booking.

            </p>

        </div>


        <a
            href="bookings.php"
            class="back-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Back to Bookings

        </a>


    </div>



    <!-- =================================================
         BOOKING HEADER
    ================================================= -->

    <div class="booking-header">


        <div class="booking-reference">


            <div>

                <div class="reference-label">

                    Booking Reference

                </div>


                <div class="reference">

                    <?= htmlspecialchars(
                        $booking['booking_reference']
                    ); ?>

                </div>

            </div>


            <div class="status-area">


                <span
                    class="status <?= htmlspecialchars(
                        $booking_status
                    ); ?>"
                >

                    <i class="fa-solid fa-circle-check"></i>

                    <?= htmlspecialchars(
                        ucfirst($booking_status)
                    ); ?>

                </span>


                <span
                    class="status <?= htmlspecialchars(
                        $payment_status
                    ); ?>"
                >

                    <i class="fa-solid fa-credit-card"></i>

                    Payment:
                    <?= htmlspecialchars(
                        ucfirst($payment_status)
                    ); ?>

                </span>


            </div>


        </div>


    </div>



    <!-- =================================================
         DETAILS GRID
    ================================================= -->

    <div class="details-grid">


        <!-- =============================================
             CUSTOMER DETAILS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-user"></i>

                Customer <span>Details</span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Full Name
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(

                        $booking['first_name']
                        . " "
                        . $booking['last_name']

                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Email
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['email']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Phone
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['phone']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Booking ID
                </span>

                <span class="detail-value">

                    #<?= htmlspecialchars(
                        $booking['id']
                    ); ?>

                </span>

            </div>


        </div>



        <!-- =============================================
             MOVIE DETAILS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-film"></i>

                Movie <span>Details</span>

            </div>


            <div class="movie-box">


                <div class="movie-icon">

                    <i class="fa-solid fa-film"></i>

                </div>


                <div>

                    <div class="movie-name">

                        <?= htmlspecialchars(
                            $booking['movie_name']
                        ); ?>

                    </div>


                    <div class="movie-subtitle">

                        TicketFlix Movie

                    </div>

                </div>


            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Theater
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['theater_name']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Screen
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['screen_name']
                    ); ?>

                </span>

            </div>


        </div>



        <!-- =============================================
             SHOWTIME DETAILS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-clock"></i>

                Showtime <span>Details</span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Date
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $show_date
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Start Time
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $show_time
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    End Time
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $end_time
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Ticket Price
                </span>

                <span class="detail-value">

                    ₹<?= number_format(
                        $booking['show_price'],
                        2
                    ); ?>

                </span>

            </div>


        </div>



        <!-- =============================================
             THEATER DETAILS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-building"></i>

                Theater <span>Details</span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Theater
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['theater_name']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Address
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['theater_address']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    City
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['theater_city']
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    State
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        $booking['theater_state']
                    ); ?>

                </span>

            </div>


        </div>



        <!-- =============================================
             SEATS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-couch"></i>

                Selected <span>Seats</span>

            </div>


            <div class="seats-container">


                <?php if ($seat_result->num_rows > 0): ?>


                    <?php while (
                        $seat = $seat_result->fetch_assoc()
                    ): ?>


                        <div class="seat">


                            <div class="seat-number">

                                <?= htmlspecialchars(
                                    $seat['row_number']
                                ); ?>

                                <?= htmlspecialchars(
                                    $seat['seat_number']
                                ); ?>

                            </div>


                            <div class="seat-type">

                                <?= htmlspecialchars(
                                    $seat['seat_type']
                                ); ?>

                            </div>


                            <div class="seat-price">

                                ₹<?= number_format(
                                    $seat['seat_price'],
                                    2
                                ); ?>

                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="no-seats">

                        No seat information found.

                    </div>


                <?php endif; ?>


            </div>


        </div>



        <!-- =============================================
             PAYMENT DETAILS
        ============================================== -->

        <div class="card">


            <div class="card-title">

                <i class="fa-solid fa-wallet"></i>

                Payment <span>Details</span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Payment Status
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        ucfirst($payment_status)
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Booking Status
                </span>

                <span class="detail-value">

                    <?= htmlspecialchars(
                        ucfirst($booking_status)
                    ); ?>

                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Number of Seats
                </span>

                <span class="detail-value">

                    <?= $seat_result->num_rows; ?>

                </span>

            </div>


            <div class="total-card">

                <div class="total-label">

                    Total Amount

                </div>


                <div class="total-amount">

                    ₹<?= number_format(

                        $booking['total_amount'],
                        2

                    ); ?>

                </div>

            </div>


        </div>


    </div>


</main>


</body>

</html>