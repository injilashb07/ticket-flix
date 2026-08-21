<?php

session_start();

require_once __DIR__ . '/../config.php';

/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* =========================================================
   GET THEATER ID
   If your session already contains theater_id, it will be used.
========================================================= */

$theater_id = isset($_SESSION['theater_id'])
    ? (int)$_SESSION['theater_id']
    : 0;


/* =========================================================
   SEARCH / FILTER
========================================================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';


/* =========================================================
   BUILD QUERY
========================================================= */

$sql = "

    SELECT

        b.id,

        b.booking_number,

        b.booking_reference,

        b.user_id,

        b.showtime_id,

        b.total_amount,

        b.booking_status,

        b.payment_status,

        m.name AS movie_name,

        st.show_date,

        st.show_time,

        s.screen_name,

        t.name AS theater_name,

        t.city,

        u.email AS customer_email,

        GROUP_CONCAT(
            CONCAT(
                bs_seat.seat_row,
                bs_seat.seat_number
            )
            ORDER BY
                bs_seat.seat_row,
                bs_seat.seat_number
            SEPARATOR ', '
        ) AS seats

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.id

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    LEFT JOIN booking_seats bs
        ON b.id = bs.booking_id

    LEFT JOIN seats bs_seat
        ON bs.seat_id = bs_seat.id

    WHERE 1 = 1

";


/* =========================================================
   THEATER FILTER
========================================================= */

if ($theater_id > 0) {

    $sql .= "
        AND t.id = ?
    ";

}


/* =========================================================
   SEARCH FILTER
========================================================= */

if ($search !== '') {

    $sql .= "

        AND (

            b.booking_number LIKE ?
            OR b.booking_reference LIKE ?
            OR u.email LIKE ?
            OR m.name LIKE ?

        )

    ";

}


/* =========================================================
   STATUS FILTER
========================================================= */

if (
    $status !== '' &&
    in_array(
        $status,
        [
            'pending',
            'confirmed',
            'cancelled'
        ],
        true
    )
) {

    $sql .= "

        AND b.booking_status = ?

    ";

}


/* =========================================================
   GROUP
========================================================= */

$sql .= "

    GROUP BY

        b.id,
        b.booking_number,
        b.booking_reference,
        b.user_id,
        b.showtime_id,
        b.total_amount,
        b.booking_status,
        b.payment_status,

        m.name,

        st.show_date,
        st.show_time,

        s.screen_name,

        t.name,
        t.city,

        u.email

    ORDER BY b.id DESC

";


/* =========================================================
   PREPARE
========================================================= */

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "<div style='
            padding:40px;
            font-family:Arial;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>
            <h2 style='color:#ff7777;'>
                Database Error
            </h2>

            <p>" .
            htmlspecialchars($conn->error)
            . "</p>

        </div>"
    );

}


/* =========================================================
   BIND PARAMETERS
========================================================= */

$params = [];
$types = '';


if ($theater_id > 0) {

    $types .= 'i';

    $params[] = $theater_id;

}


if ($search !== '') {

    $search_value =
        '%' . $search . '%';

    $types .= 'ssss';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

}


if (
    $status !== '' &&
    in_array(
        $status,
        [
            'pending',
            'confirmed',
            'cancelled'
        ],
        true
    )
) {

    $types .= 's';

    $params[] = $status;

}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


/* =========================================================
   EXECUTE
========================================================= */

if (!$stmt->execute()) {

    die(
        "Booking query failed: " .
        htmlspecialchars(
            $stmt->error
        )
    );

}


$result =
    $stmt->get_result();


/* =========================================================
   COUNT BOOKINGS
========================================================= */

$total_bookings =
    $result->num_rows;


/* =========================================================
   HELPER FUNCTION
========================================================= */

function statusClass($status)
{

    switch ($status) {

        case 'confirmed':
            return 'confirmed';

        case 'cancelled':
            return 'cancelled';

        default:
            return 'pending';

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
    Bookings | TicketFlix Theater
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {

    margin:0;

    padding:0;

    box-sizing:border-box;

}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:#0e0915;

    color:#ffffff;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position:fixed;

    left:0;

    top:0;

    width:270px;

    height:100vh;

    background:
        linear-gradient(
            180deg,
            #110b19,
            #0d0814
        );

    border-right:
        1px solid
        #33253d;

    padding:28px 20px;

    overflow-y:auto;

}


.logo {

    text-align:center;

    margin-bottom:8px;

}


.logo h1 {

    font-size:31px;

    font-weight:800;

}


.logo span {

    color:#f4c430;

}


.portal {

    text-align:center;

    color:#827789;

    font-size:11px;

    letter-spacing:3px;

    margin-bottom:35px;

}


.theater-box {

    background:
        linear-gradient(
            145deg,
            #241a2c,
            #1b1322
        );

    border:
        1px solid
        #493650;

    border-radius:15px;

    padding:18px;

    margin-bottom:25px;

}


.theater-icon {

    width:43px;

    height:43px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#342c31;

    border-radius:10px;

    color:#f4c430;

    font-size:21px;

    margin-bottom:12px;

}


.theater-box h3 {

    font-size:15px;

    margin-bottom:6px;

}


.theater-box p {

    color:#8e8495;

    font-size:12px;

}


/* =========================================================
   NAVIGATION
========================================================= */

.nav {

    display:flex;

    flex-direction:column;

    gap:8px;

}


.nav a {

    display:flex;

    align-items:center;

    gap:14px;

    padding:14px 16px;

    color:#aaa2b1;

    text-decoration:none;

    border-radius:11px;

    font-size:14px;

    transition:.2s;

}


.nav a:hover {

    background:#261c29;

    color:#f4c430;

}


.nav a.active {

    background:#302326;

    color:#f4c430;

}


.nav-icon {

    width:20px;

    text-align:center;

    font-size:17px;

}


.logout {

    margin-top:100px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left:270px;

    min-height:100vh;

    padding:35px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:30px;

}


.page-header h1 {

    font-size:29px;

}


.page-header h1 span {

    color:#f4c430;

}


.page-header p {

    color:#8f8696;

    margin-top:7px;

    font-size:14px;

}


.total-box {

    background:#241b2a;

    border:1px solid #403249;

    padding:14px 20px;

    border-radius:12px;

}


.total-box strong {

    color:#f4c430;

    font-size:21px;

}


/* =========================================================
   FILTER AREA
========================================================= */

.filter-box {

    background:
        linear-gradient(
            145deg,
            #1b1421,
            #17101d
        );

    border:
        1px solid
        #33263c;

    border-radius:16px;

    padding:20px;

    margin-bottom:25px;

}


.filter-form {

    display:flex;

    gap:12px;

    flex-wrap:wrap;

}


.search-input {

    flex:1;

    min-width:230px;

    background:#100b16;

    border:
        1px solid
        #42344a;

    color:white;

    padding:13px 15px;

    border-radius:9px;

    outline:none;

}


.search-input:focus {

    border-color:#f4c430;

}


.status-select {

    background:#100b16;

    border:
        1px solid
        #42344a;

    color:white;

    padding:13px 15px;

    border-radius:9px;

    outline:none;

}


.filter-btn {

    background:#f4c430;

    color:#21102f;

    border:none;

    padding:13px 22px;

    border-radius:9px;

    font-weight:bold;

    cursor:pointer;

}


.clear-btn {

    background:#302537;

    color:white;

    text-decoration:none;

    padding:13px 20px;

    border-radius:9px;

}


/* =========================================================
   BOOKING CARDS
========================================================= */

.bookings-container {

    display:flex;

    flex-direction:column;

    gap:15px;

}


.booking-card {

    background:
        linear-gradient(
            145deg,
            #1c1523,
            #17101d
        );

    border:
        1px solid
        #35283e;

    border-radius:17px;

    padding:20px;

    transition:.2s;

}


.booking-card:hover {

    border-color:#59436a;

    transform:translateY(-1px);

}


/* =========================================================
   BOOKING TOP
========================================================= */

.booking-top {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:18px;

}


.booking-ref {

    display:flex;

    align-items:center;

    gap:12px;

}


.ticket-icon {

    width:43px;

    height:43px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    background:#2d2430;

    color:#f4c430;

    font-size:20px;

}


.booking-ref small {

    display:block;

    color:#81778a;

    font-size:11px;

    margin-bottom:4px;

}


.booking-ref strong {

    color:#f4c430;

    letter-spacing:1px;

}


.status {

    padding:7px 12px;

    border-radius:20px;

    font-size:11px;

    font-weight:bold;

    text-transform:uppercase;

}


.status.confirmed {

    background:#173c2c;

    color:#5de0a2;

}


.status.pending {

    background:#453619;

    color:#ffd65a;

}


.status.cancelled {

    background:#451d25;

    color:#ff7888;

}


/* =========================================================
   BOOKING DETAILS
========================================================= */

.booking-grid {

    display:grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:12px;

}


.info {

    background:
        rgba(255,255,255,.035);

    border-radius:10px;

    padding:13px;

}


.info small {

    display:block;

    color:#81778a;

    font-size:10px;

    text-transform:uppercase;

    margin-bottom:6px;

}


.info strong {

    display:block;

    color:#eee9f0;

    font-size:13px;

    line-height:1.4;

    word-break:break-word;

}


.info strong.gold {

    color:#f4c430;

    font-size:15px;

}


/* =========================================================
   PAYMENT
========================================================= */

.payment-completed {

    color:#5de0a2 !important;

}


.payment-pending {

    color:#ffd65a !important;

}


.payment-failed {

    color:#ff7888 !important;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background:
        linear-gradient(
            145deg,
            #1b1421,
            #15101c
        );

    border:
        1px solid
        #33263c;

    border-radius:18px;

    padding:70px 20px;

    text-align:center;

}


.empty-icon {

    font-size:50px;

    margin-bottom:15px;

}


.empty h2 {

    margin-bottom:8px;

}


.empty p {

    color:#877d8d;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .booking-grid {

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:800px) {

    .sidebar {

        width:220px;

    }

    .main {

        margin-left:220px;

        padding:20px;

    }

}


@media(max-width:650px) {

    .sidebar {

        position:relative;

        width:100%;

        height:auto;

    }

    .main {

        margin-left:0;

    }

    .logout {

        margin-top:10px;

    }

    .page-header {

        flex-direction:column;

        align-items:flex-start;

        gap:15px;

    }

    .booking-grid {

        grid-template-columns:1fr;

    }

    .booking-top {

        align-items:flex-start;

        flex-direction:column;

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

        <h1>
            🎟️Ticket<span>Flix</span>
        </h1>

    </div>


    <div class="portal">

        THEATER PORTAL

    </div>


    <div class="theater-box">

        <div class="theater-icon">
            🏢
        </div>

        <h3>
            Grand Cinemas
        </h3>

        <p>
            123 Main Street
        </p>

    </div>


    <nav class="nav">


        <a href="dashboard.php">

            <span class="nav-icon">
                📊
            </span>

            Dashboard

        </a>


        <a href="showtimes.php">

            <span class="nav-icon">
                ◷
            </span>

            Showtimes

        </a>


        <a href="add_showtime.php">

            <span class="nav-icon">
                ⊕
            </span>

            Add Showtime

        </a>


        <a href="screens.php">

            <span class="nav-icon">
                ▣
            </span>

            Screens

        </a>


        <a
            href="bookings.php"
            class="active"
        >

            <span class="nav-icon">
                🎟
            </span>

            Bookings

        </a>


        <a href="../index.php">

            <span class="nav-icon">
                🌐
            </span>

            View Website

        </a>


        <a
            href="../logout.php"
            class="logout"
        >

            <span class="nav-icon">
                ↪
            </span>

            Logout

        </a>


    </nav>


</aside>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main">


    <div class="page-header">


        <div>

            <h1>

                Customer
                <span>Bookings</span>

            </h1>

            <p>

                View and manage movie ticket bookings

            </p>

        </div>


        <div class="total-box">

            Total Bookings:

            <strong>

                <?= $total_bookings ?>

            </strong>

        </div>


    </div>


    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="filter-box">


        <form
            method="GET"
            class="filter-form"
        >


            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search booking, movie or email..."
                value="<?= htmlspecialchars($search); ?>"
            >


            <select
                name="status"
                class="status-select"
            >

                <option value="">
                    All Status
                </option>

                <option
                    value="confirmed"
                    <?= $status === 'confirmed'
                        ? 'selected'
                        : ''; ?>
                >
                    Confirmed
                </option>

                <option
                    value="pending"
                    <?= $status === 'pending'
                        ? 'selected'
                        : ''; ?>
                >
                    Pending
                </option>

                <option
                    value="cancelled"
                    <?= $status === 'cancelled'
                        ? 'selected'
                        : ''; ?>
                >
                    Cancelled
                </option>

            </select>


            <button
                type="submit"
                class="filter-btn"
            >

                🔍 Search

            </button>


            <a
                href="bookings.php"
                class="clear-btn"
            >

                Clear

            </a>


        </form>


    </div>


    <!-- =================================================
         BOOKINGS
    ================================================== -->

    <div class="bookings-container">


<?php if ($result->num_rows > 0): ?>


<?php while ($booking = $result->fetch_assoc()): ?>


        <div class="booking-card">


            <!-- TOP -->

            <div class="booking-top">


                <div class="booking-ref">


                    <div class="ticket-icon">

                        🎟️

                    </div>


                    <div>

                        <small>
                            Booking Reference
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $booking['booking_reference']
                            ); ?>

                        </strong>

                    </div>


                </div>


                <div class="status
                    <?= statusClass(
                        $booking['booking_status']
                    ); ?>"
                >

                    <?= htmlspecialchars(
                        $booking['booking_status']
                    ); ?>

                </div>


            </div>


            <!-- DETAILS -->

            <div class="booking-grid">


                <!-- CUSTOMER -->

                <div class="info">

                    <small>
                        Customer Email
                    </small>

                    <strong>

                        <?= htmlspecialchars(
                            $booking['customer_email']
                        ); ?>

                    </strong>

                </div>


                <!-- MOVIE -->

                <div class="info">

                    <small>
                        Movie
                    </small>

                    <strong>

                        <?= htmlspecialchars(
                            $booking['movie_name']
                        ); ?>

                    </strong>

                </div>


                <!-- THEATER -->

                <div class="info">

                    <small>
                        Theater
                    </small>

                    <strong>

                        <?= htmlspecialchars(
                            $booking['theater_name']
                        ); ?>

                    </strong>

                </div>


                <!-- SCREEN -->

                <div class="info">

                    <small>
                        Screen
                    </small>

                    <strong>

                        <?= htmlspecialchars(
                            $booking['screen_name']
                        ); ?>

                    </strong>

                </div>


                <!-- SEATS -->

                <div class="info">

                    <small>
                        Seats
                    </small>

                    <strong class="gold">

                        <?= htmlspecialchars(
                            $booking['seats'] ?: 'N/A'
                        ); ?>

                    </strong>

                </div>


                <!-- DATE -->

                <div class="info">

                    <small>
                        Show Date
                    </small>

                    <strong>

                        <?= date(
                            'd M Y',
                            strtotime(
                                $booking['show_date']
                            )
                        ); ?>

                    </strong>

                </div>


                <!-- TIME -->

                <div class="info">

                    <small>
                        Show Time
                    </small>

                    <strong>

                        <?= date(
                            'h:i A',
                            strtotime(
                                $booking['show_time']
                            )
                        ); ?>

                    </strong>

                </div>


                <!-- AMOUNT -->

                <div class="info">

                    <small>
                        Total Amount
                    </small>

                    <strong class="gold">

                        ₹<?= number_format(
                            (float)$booking['total_amount'],
                            2
                        ); ?>

                    </strong>

                </div>


                <!-- PAYMENT -->

                <div class="info">

                    <small>
                        Payment
                    </small>


                    <strong
                        class="<?php

                        if (
                            $booking['payment_status']
                            === 'completed'
                        ) {

                            echo 'payment-completed';

                        }
                        elseif (
                            $booking['payment_status']
                            === 'failed'
                        ) {

                            echo 'payment-failed';

                        }
                        else {

                            echo 'payment-pending';

                        }

                        ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                $booking['payment_status']
                            )
                        ); ?>

                    </strong>

                </div>


                <!-- BOOKING NUMBER -->

                <div class="info">

                    <small>
                        Booking Number
                    </small>

                    <strong>

                        <?= htmlspecialchars(
                            $booking['booking_number']
                        ); ?>

                    </strong>

                </div>


            </div>


        </div>


<?php endwhile; ?>


<?php else: ?>


        <div class="empty">


            <div class="empty-icon">

                🎟️

            </div>


            <h2>

                No Bookings Found

            </h2>


            <p>

                There are no bookings matching your search.

            </p>


        </div>


<?php endif; ?>


    </div>


</main>


</body>

</html>

<?php

$stmt->close();

?>