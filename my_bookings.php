<?php
session_start();

require_once "config.php";

/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];


/* =========================================================
   GET USER BOOKINGS
========================================================= */

$sql = "
    SELECT
        b.id AS booking_id,
        b.booking_reference,
        b.total_amount,
        b.booking_status,
        b.payment_status,

        st.show_date,
        st.show_time,
        st.end_time,

        m.name AS movie_name,
        m.poster_image,

        s.screen_name,

        t.name AS theater_name,
        t.address,
        t.city

    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    WHERE b.user_id = ?

    ORDER BY
        st.show_date DESC,
        st.show_time DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Booking query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$bookings = [];


/* =========================================================
   GET BOOKINGS
========================================================= */

while ($booking = $result->fetch_assoc()) {

    $booking_id = (int)$booking['booking_id'];

    /* ---------------------------------------------
       GET SEATS FOR THIS BOOKING
    --------------------------------------------- */

    $seat_sql = "
        SELECT
            bs.seat_id,
            bs.seat_price,
            s.row_number,
            s.seat_number,
            s.seat_type

        FROM booking_seats bs

        INNER JOIN seats s
            ON bs.seat_id = s.id

        WHERE bs.booking_id = ?

        ORDER BY
            s.row_number,
            s.seat_number
    ";

    $seat_stmt = $conn->prepare($seat_sql);

    if ($seat_stmt) {

        $seat_stmt->bind_param(
            "i",
            $booking_id
        );

        $seat_stmt->execute();

        $seat_result = $seat_stmt->get_result();

        $booking['seats'] = [];

        while ($seat = $seat_result->fetch_assoc()) {

            $booking['seats'][] = $seat;

        }

        $seat_stmt->close();

    } else {

        $booking['seats'] = [];

    }

    $bookings[] = $booking;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Bookings | TicketFlix</title>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    min-height: 100vh;

    font-family: 'Poppins', sans-serif;

    color: white;

    background:

        radial-gradient(
            circle at top right,
            rgba(126,87,194,.25),
            transparent 35%
        ),

        radial-gradient(
            circle at bottom left,
            rgba(212,175,55,.10),
            transparent 35%
        ),

        #100b18;

}


/* =====================================================
   NAVBAR
===================================================== */

.navbar {

    height: 75px;

    padding: 0 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(18,12,28,.97);

    border-bottom:
        1px solid rgba(212,175,55,.20);

    position: sticky;

    top: 0;

    z-index: 100;

}


.logo {

    font-size: 27px;

    font-weight: 800;

}


.logo span {

    color: #d4af37;

}


.back-btn {

    text-decoration: none;

    color: white;

    padding: 9px 18px;

    border-radius: 25px;

    border:
        1px solid rgba(255,255,255,.2);

    transition: .3s;

}


.back-btn:hover {

    background: #d4af37;

    color: #171020;

}


/* =====================================================
   CONTAINER
===================================================== */

.container {

    width: 92%;

    max-width: 1150px;

    margin: auto;

    padding: 45px 0 70px;

}


/* =====================================================
   PAGE TITLE
===================================================== */

.page-header {

    text-align: center;

    margin-bottom: 40px;

}


.page-header h1 {

    font-size: 36px;

    margin-bottom: 8px;

}


.page-header h1 span {

    color: #d4af37;

}


.page-header p {

    color: #aaa;

}


/* =====================================================
   BOOKING CARD
===================================================== */

.booking-card {

    display: flex;

    gap: 25px;

    padding: 25px;

    margin-bottom: 25px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(212,175,55,.18);

    border-radius: 25px;

    backdrop-filter: blur(15px);

    transition: .3s;

}


.booking-card:hover {

    transform: translateY(-4px);

    border-color:
        rgba(212,175,55,.45);

    box-shadow:
        0 15px 40px rgba(0,0,0,.3);

}


/* =====================================================
   MOVIE POSTER
===================================================== */

.poster {

    width: 150px;

    height: 215px;

    flex-shrink: 0;

    border-radius: 15px;

    overflow: hidden;

    background: #24182d;

}


.poster img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}


/* =====================================================
   BOOKING DETAILS
===================================================== */

.booking-details {

    flex: 1;

}


.movie-title {

    font-size: 25px;

    font-weight: 700;

    margin-bottom: 12px;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(200px,1fr));

    gap: 12px;

    margin-bottom: 18px;

}


.info-item {

    color: #ccc;

    font-size: 14px;

}


.info-item i {

    color: #d4af37;

    width: 22px;

}


/* =====================================================
   SEATS
===================================================== */

.seats-title {

    color: #aaa;

    font-size: 13px;

    margin-bottom: 8px;

}


.seats {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-bottom: 18px;

}


.seat {

    padding: 6px 11px;

    border-radius: 8px;

    background:
        rgba(212,175,55,.12);

    border:
        1px solid rgba(212,175,55,.35);

    color: #f1d46a;

    font-size: 12px;

}


/* =====================================================
   BOTTOM
===================================================== */

.booking-bottom {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding-top: 15px;

    border-top:
        1px solid rgba(255,255,255,.08);

}


.reference {

    color: #999;

    font-size: 12px;

}


.reference strong {

    color: #d4af37;

    letter-spacing: 1px;

}


.amount {

    font-size: 21px;

    font-weight: 700;

    color: #d4af37;

}


/* =====================================================
   STATUS
===================================================== */

.status {

    display: inline-block;

    padding: 5px 12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

}


.status.confirmed {

    background:
        rgba(46,204,113,.12);

    color: #5ee89a;

    border:
        1px solid rgba(46,204,113,.25);

}


.status.pending {

    background:
        rgba(241,196,15,.12);

    color: #f1d46a;

}


.status.cancelled {

    background:
        rgba(231,76,60,.12);

    color: #ff7d70;

}


/* =====================================================
   EMPTY
===================================================== */

.empty {

    text-align: center;

    padding: 80px 20px;

    background:
        rgba(255,255,255,.04);

    border-radius: 25px;

    border:
        1px solid rgba(255,255,255,.08);

}


.empty-icon {

    font-size: 65px;

    color: #d4af37;

    margin-bottom: 20px;

}


.empty h2 {

    margin-bottom: 10px;

}


.empty p {

    color: #999;

    margin-bottom: 25px;

}


.book-btn {

    display: inline-block;

    text-decoration: none;

    padding: 12px 25px;

    border-radius: 25px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1d46a
        );

    color: #171020;

    font-weight: 700;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:700px) {

    .booking-card {

        flex-direction: column;

    }

    .poster {

        width: 100%;

        height: 300px;

    }

    .info-grid {

        grid-template-columns: 1fr;

    }

    .booking-bottom {

        flex-direction: column;

        align-items: flex-start;

    }

    .page-header h1 {

        font-size: 28px;

    }

}

</style>

</head>


<body>


<nav class="navbar">

    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </div>


    <a
        href="index.php"
        class="back-btn"
    >

        <i class="fa-solid fa-house"></i>
        Home

    </a>

</nav>


<div class="container">


    <div class="page-header">

        <h1>

            My <span>Bookings</span> 🎟️

        </h1>

        <p>

            Your movie tickets are all in one place.

        </p>

    </div>


    <?php if (empty($bookings)): ?>


        <div class="empty">

            <div class="empty-icon">

                <i class="fa-solid fa-ticket"></i>

            </div>

            <h2>

                No Bookings Yet

            </h2>

            <p>

                You haven't booked any movie tickets yet.

            </p>

            <a
                href="showtimes.php"
                class="book-btn"
            >

                🎬 Browse Showtimes

            </a>

        </div>


    <?php else: ?>


        <?php foreach ($bookings as $booking): ?>


            <div class="booking-card">


                <!-- POSTER -->

                <div class="poster">

                    <?php

                    $poster =
                        !empty($booking['poster_image'])

                        ? $booking['poster_image']

                        : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=600&q=80";

                    ?>

                    <img
                        src="<?= htmlspecialchars($poster); ?>"
                        alt="<?= htmlspecialchars($booking['movie_name']); ?>"
                    >

                </div>


                <!-- DETAILS -->

                <div class="booking-details">


                    <div class="movie-title">

                        <?= htmlspecialchars(
                            $booking['movie_name']
                        ); ?>

                    </div>


                    <div class="info-grid">


                        <div class="info-item">

                            <i class="fa-solid fa-calendar"></i>

                            <?= date(
                                "d M Y",
                                strtotime(
                                    $booking['show_date']
                                )
                            ); ?>

                        </div>


                        <div class="info-item">

                            <i class="fa-solid fa-clock"></i>

                            <?= date(
                                "h:i A",
                                strtotime(
                                    $booking['show_time']
                                )
                            ); ?>

                        </div>


                        <div class="info-item">

                            <i class="fa-solid fa-building"></i>

                            <?= htmlspecialchars(
                                $booking['theater_name']
                            ); ?>

                        </div>


                        <div class="info-item">

                            <i class="fa-solid fa-tv"></i>

                            <?= htmlspecialchars(
                                $booking['screen_name']
                            ); ?>

                        </div>


                    </div>


                    <div class="seats-title">

                        💺 Selected Seats

                    </div>


                    <div class="seats">


                        <?php foreach (
                            $booking['seats']
                            as $seat
                        ): ?>


                            <div class="seat">

                                <?= htmlspecialchars(
                                    $seat['row_number'] .
                                    $seat['seat_number']
                                ); ?>

                            </div>


                        <?php endforeach; ?>


                    </div>


                    <div class="booking-bottom">


                        <div>

                            <div class="reference">

                                Booking ID:
                                <strong>

                                    <?= htmlspecialchars(
                                        $booking['booking_reference']
                                    ); ?>

                                </strong>

                            </div>


                            <br>


                            <span
                                class="status
                                <?= htmlspecialchars(
                                    $booking['booking_status']
                                ); ?>"
                            >

                                <?= ucfirst(
                                    htmlspecialchars(
                                        $booking['booking_status']
                                    )
                                ); ?>

                            </span>

                        </div>


                        <div class="amount">

                            ₹<?= number_format(
                                $booking['total_amount'],
                                2
                            ); ?>

                        </div>


                    </div>


                </div>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</div>


</body>

</html>