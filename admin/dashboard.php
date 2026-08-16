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

$admin_id = (int) $_SESSION['admin_id'];


/* =========================================================
   GET ADMIN DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        first_name,
        last_name,
        email
    FROM admins
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Admin query error: " . $conn->error);
}

$stmt->bind_param("i", $admin_id);

$stmt->execute();

$result = $stmt->get_result();

$admin = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CHECK ADMIN EXISTS
========================================================= */

if (!$admin) {

    session_unset();
    session_destroy();

    header("Location: ../admin_login.php");
    exit();

}


/* =========================================================
   1. TOTAL USERS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

$total_users = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $total_users = (int) $row['total'];

}


/* =========================================================
   2. TOTAL MOVIES
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM movies
");

$total_movies = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $total_movies = (int) $row['total'];

}


/* =========================================================
   3. TOTAL BOOKINGS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings
");

$total_bookings = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $total_bookings = (int) $row['total'];

}


/* =========================================================
   4. TOTAL CONFIRMED REVENUE
========================================================= */

$result = $conn->query("
    SELECT
        COALESCE(SUM(total_amount), 0) AS revenue
    FROM bookings
    WHERE booking_status = 'confirmed'
");

$total_revenue = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $total_revenue = (float) $row['revenue'];

}


/* =========================================================
   5. TODAY'S BOOKINGS
========================================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    WHERE st.show_date = CURDATE()
");

$today_bookings = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $today_bookings = (int) $row['total'];

}


/* =========================================================
   6. PROFIT SETTINGS
========================================================= */

/*
    For project/demo purpose:
    20% of confirmed revenue is considered profit.
*/

$profit_percentage = 20;


/* =========================================================
   7. TOTAL PROFIT
========================================================= */

$total_profit =
    $total_revenue *
    ($profit_percentage / 100);


/* =========================================================
   8. THEATER REVENUE
========================================================= */

/*
    Correct relationship:

    bookings
        ↓
    showtimes
        ↓
    screens
        ↓
    theaters

    IMPORTANT:
    showtimes DOES NOT contain theater_id.
    It contains screen_id.
*/

$theater_sql = "

    SELECT

        t.id AS theater_id,

        t.name AS theater_name,

        COALESCE(
            SUM(
                CASE
                    WHEN b.booking_status = 'confirmed'
                    THEN b.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM theaters t

    LEFT JOIN screens sc
        ON sc.theater_id = t.id

    LEFT JOIN showtimes st
        ON st.screen_id = sc.id

    LEFT JOIN bookings b
        ON b.showtime_id = st.id

    GROUP BY
        t.id,
        t.name

    ORDER BY revenue DESC

";


$theater_result = $conn->query($theater_sql);


/* =========================================================
   9. RECENT BOOKINGS
========================================================= */

$recent_sql = "

    SELECT

        b.id,

        b.booking_reference,

        b.total_amount,

        b.booking_status,

        m.name AS movie_name,

        u.first_name,

        u.last_name,

        st.show_date,

        st.show_time,

        t.name AS theater_name

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

    ORDER BY b.id DESC

    LIMIT 6

";


$recent_result = $conn->query($recent_sql);


/* =========================================================
   10. TOP THEATER
========================================================= */

$top_theater_name = "No Data";

$top_theater_revenue = 0;


if ($theater_result && $theater_result->num_rows > 0) {

    $theater_result->data_seek(0);

    $top_theater = $theater_result->fetch_assoc();

    if ($top_theater) {

        $top_theater_name =
            $top_theater['theater_name'];

        $top_theater_revenue =
            (float) $top_theater['revenue'];

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

<title>Admin Dashboard | TicketFlix</title>


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

    background:

        rgba(18,12,28,.98);

    border-right:

        1px solid
        rgba(212,175,55,.18);

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

    margin-bottom: 35px;

}


.welcome h1 {

    font-size: 28px;

}


.welcome h1 span {

    color: #d4af37;

}


.welcome p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;

}


.admin-profile {

    display: flex;

    align-items: center;

    gap: 12px;

}


.admin-profile strong {

    font-size: 13px;

}


.avatar {

    width: 45px;

    height: 45px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:

        linear-gradient(
            135deg,
            #d4af37,
            #8f6d18
        );

    color: #171020;

    font-weight: 800;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 22px;

}


.stat-card {

    padding: 22px;

    border-radius: 20px;

    background:

        rgba(255,255,255,.055);

    border:

        1px solid
        rgba(255,255,255,.08);

    transition: .3s;

}


.stat-card:hover {

    transform:
        translateY(-4px);

    border-color:
        rgba(212,175,55,.3);

}


.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;

}


.stat-icon {

    width: 45px;

    height: 45px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:

        rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 18px;

}


.stat-card small {

    color: #888;

}


.stat-card h2 {

    font-size: 27px;

    margin-top: 5px;

}


.stat-card p {

    color: #777;

    font-size: 11px;

}


/* =========================================================
   FINANCE CARDS
========================================================= */

.finance-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 30px;

}


.finance-card {

    padding: 23px;

    border-radius: 20px;

    background:

        linear-gradient(
            135deg,
            rgba(212,175,55,.12),
            rgba(126,87,194,.08)
        );

    border:
        1px solid
        rgba(212,175,55,.18);

}


.finance-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.finance-top small {

    color: #999;

}


.finance-icon {

    width: 43px;

    height: 43px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:

        rgba(212,175,55,.12);

    color: #d4af37;

}


.finance-card h2 {

    color: #d4af37;

    margin-top: 12px;

    font-size: 26px;

}


.finance-card p {

    color: #888;

    font-size: 11px;

    margin-top: 4px;

}


/* =========================================================
   PROFIT BAR
========================================================= */

.profit-progress {

    width: 100%;

    height: 7px;

    background:
        rgba(255,255,255,.08);

    border-radius: 20px;

    margin-top: 14px;

    overflow: hidden;

}


.profit-progress span {

    display: block;

    height: 100%;

    width:
        <?php echo min($profit_percentage,100); ?>%;

    background:
        #d4af37;

    border-radius: 20px;

}


/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        2fr 1fr;

    gap: 22px;

}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background:

        rgba(255,255,255,.05);

    border:

        1px solid
        rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 25px;

}


.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;

}


.panel-header h2 {

    font-size: 18px;

}


.panel-header h2 span {

    color: #d4af37;

}


.view-btn {

    color: #d4af37;

    text-decoration: none;

    font-size: 12px;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    text-align: left;

    color: #777;

    font-size: 11px;

    padding: 12px 8px;

    border-bottom:

        1px solid
        rgba(255,255,255,.08);

}


td {

    padding: 15px 8px;

    font-size: 12px;

    color: #ccc;

    border-bottom:

        1px solid
        rgba(255,255,255,.05);

}


.movie-name {

    color: white;

    font-weight: 600;

}


.customer {

    color: #aaa;

}


.amount {

    color: #d4af37;

    font-weight: 600;

}


.theater-name {

    color: #aaa;

    font-size: 10px;

    margin-top: 3px;

}


.status {

    padding: 5px 9px;

    border-radius: 15px;

    font-size: 10px;

}


.confirmed {

    color: #61e69b;

    background:

        rgba(46,204,113,.10);

}


.pending {

    color: #f1d46a;

    background:

        rgba(241,196,15,.10);

}


.cancelled {

    color: #ff8175;

    background:

        rgba(231,76,60,.10);

}


/* =========================================================
   THEATER PANEL
========================================================= */

.theater-list {

    max-height: 370px;

    overflow-y: auto;

}


.theater-item {

    padding: 15px 0;

    border-bottom:

        1px solid
        rgba(255,255,255,.06);

}


.theater-item:last-child {

    border-bottom: none;

}


.theater-info {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.theater-info strong {

    font-size: 12px;

}


.theater-info span {

    color: #d4af37;

    font-size: 12px;

    font-weight: 600;

}


.theater-bar {

    width: 100%;

    height: 5px;

    background:
        rgba(255,255,255,.07);

    margin-top: 9px;

    border-radius: 20px;

    overflow: hidden;

}


.theater-bar span {

    display: block;

    height: 100%;

    background: #d4af37;

    border-radius: 20px;

}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 12px;

}


.quick-action {

    text-decoration: none;

    color: white;

    padding: 18px 12px;

    border-radius: 15px;

    text-align: center;

    background:

        rgba(255,255,255,.04);

    border:

        1px solid
        rgba(255,255,255,.07);

    transition: .3s;

}


.quick-action:hover {

    border-color: #d4af37;

    transform:
        translateY(-3px);

}


.quick-action i {

    display: block;

    color: #d4af37;

    font-size: 22px;

    margin-bottom: 8px;

}


.quick-action span {

    font-size: 11px;

    color: #bbb;

}


/* =========================================================
   TOP THEATER CARD
========================================================= */

.top-theater {

    margin-top: 18px;

    padding: 22px;

    border-radius: 18px;

    background:

        linear-gradient(
            135deg,
            rgba(212,175,55,.15),
            rgba(126,87,194,.10)
        );

    border:

        1px solid
        rgba(212,175,55,.18);

}


.top-theater small {

    color: #999;

}


.top-theater h3 {

    color: #d4af37;

    font-size: 17px;

    margin-top: 8px;

}


.top-theater p {

    color: #888;

    font-size: 11px;

    margin-top: 3px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2,1fr);

    }

    .finance-grid {

        grid-template-columns:
            1fr 1fr;

    }

    .content-grid {

        grid-template-columns:
            1fr;

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


    .stats {

        grid-template-columns:
            1fr;

    }


    .finance-grid {

        grid-template-columns:
            1fr;

    }


    .topbar {

        align-items:
            flex-start;

    }


    .admin-profile strong {

        display: none;

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


    <!-- DASHBOARD -->

    <a
        href="dashboard.php"
        class="active"
    >

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>


    <!-- USERS -->

    <a href="users.php">

        <i class="fa-solid fa-users"></i>

        <span>Users</span>

    </a>


    <!-- BOOKINGS -->

    <a href="bookings.php">

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>


    <!-- MOVIES -->

    <a href="../movies.php">

        <i class="fa-solid fa-film"></i>

        <span>Movies</span>

    </a>


    <!-- THEATERS -->

    <a href="../theaters.php">

        <i class="fa-solid fa-building"></i>

        <span>Theaters</span>

    </a>


    <!-- SHOWTIMES -->

    <a href="../showtimes.php">

        <i class="fa-solid fa-clock"></i>

        <span>Showtimes</span>

    </a>


    <!-- WEBSITE -->

    <a href="../index.php">

        <i class="fa-solid fa-globe"></i>

        <span>View Website</span>

    </a>


    <!-- LOGOUT -->

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


    <!-- =================================================
         TOP BAR
    ================================================= -->

    <div class="topbar">


        <div class="welcome">

            <h1>

                Welcome,

                <span>

                    <?= htmlspecialchars(
                        $admin['first_name']
                    ); ?>

                </span>

                👑

            </h1>


            <p>

                Here's what's happening with
                TicketFlix today.

            </p>

        </div>


        <div class="admin-profile">


            <div>

                <strong>

                    <?= htmlspecialchars(

                        $admin['first_name']
                        . " "
                        . $admin['last_name']

                    ); ?>

                </strong>

            </div>


            <div class="avatar">

                <?= strtoupper(

                    substr(
                        $admin['first_name'],
                        0,
                        1
                    )

                ); ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         BASIC STATISTICS
    ================================================= -->

    <div class="stats">


        <!-- USERS -->

        <div class="stat-card">

            <div class="stat-top">

                <small>Total Users</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

            </div>


            <h2>

                <?= number_format(
                    $total_users
                ); ?>

            </h2>


            <p>

                Registered customers

            </p>

        </div>


        <!-- MOVIES -->

        <div class="stat-card">

            <div class="stat-top">

                <small>Total Movies</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-film"></i>

                </div>

            </div>


            <h2>

                <?= number_format(
                    $total_movies
                ); ?>

            </h2>


            <p>

                Movies in database

            </p>

        </div>


        <!-- BOOKINGS -->

        <div class="stat-card">

            <div class="stat-top">

                <small>Total Bookings</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-ticket"></i>

                </div>

            </div>


            <h2>

                <?= number_format(
                    $total_bookings
                ); ?>

            </h2>


            <p>

                All bookings

            </p>

        </div>


        <!-- TODAY -->

        <div class="stat-card">

            <div class="stat-top">

                <small>Today's Bookings</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-day"></i>

                </div>

            </div>


            <h2>

                <?= number_format(
                    $today_bookings
                ); ?>

            </h2>


            <p>

                Scheduled today

            </p>

        </div>


    </div>


    <!-- =================================================
         FINANCE CARDS
    ================================================= -->

    <div class="finance-grid">


        <!-- TOTAL REVENUE -->

        <div class="finance-card">

            <div class="finance-top">

                <small>
                    Total Revenue
                </small>

                <div class="finance-icon">

                    <i class="fa-solid fa-indian-rupee-sign"></i>

                </div>

            </div>


            <h2>

                ₹<?= number_format(
                    $total_revenue,
                    2
                ); ?>

            </h2>


            <p>
                From confirmed bookings
            </p>

        </div>


        <!-- TOTAL PROFIT -->

        <div class="finance-card">

            <div class="finance-top">

                <small>
                    Total Profit
                </small>

                <div class="finance-icon">

                    <i class="fa-solid fa-chart-line"></i>

                </div>

            </div>


            <h2>

                ₹<?= number_format(
                    $total_profit,
                    2
                ); ?>

            </h2>


            <p>

                <?= number_format(
                    $profit_percentage,
                    1
                ); ?>% of total revenue

            </p>


            <div class="profit-progress">

                <span></span>

            </div>

        </div>


        <!-- TOP THEATER -->

        <div class="finance-card">

            <div class="finance-top">

                <small>
                    Top Theater
                </small>

                <div class="finance-icon">

                    <i class="fa-solid fa-building"></i>

                </div>

            </div>


            <h2 style="font-size:18px;">

                <?= htmlspecialchars(
                    $top_theater_name
                ); ?>

            </h2>


            <p>

                Revenue:
                ₹<?= number_format(
                    $top_theater_revenue,
                    2
                ); ?>

            </p>

        </div>


    </div>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="content-grid">


        <!-- =================================================
             LEFT SIDE
        ================================================= -->

        <div>


            <!-- RECENT BOOKINGS -->

            <div class="panel">


                <div class="panel-header">

                    <h2>

                        Recent
                        <span>Bookings</span>

                    </h2>


                    <a
                        href="bookings.php"
                        class="view-btn"
                    >

                        View All →

                    </a>

                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Reference
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Movie
                                </th>

                                <th>
                                    Theater
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php

                        if (
                            $recent_result &&
                            $recent_result->num_rows > 0
                        ):

                        ?>


                            <?php

                            while (
                                $row =
                                $recent_result->fetch_assoc()
                            ):

                            ?>


                                <tr>


                                    <!-- REFERENCE -->

                                    <td>

                                        <strong
                                            style="
                                                color:#d4af37;
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $row['booking_reference']
                                            ); ?>

                                        </strong>

                                    </td>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div class="customer">

                                            <?= htmlspecialchars(

                                                $row['first_name']
                                                . " "
                                                . $row['last_name']

                                            ); ?>

                                        </div>

                                    </td>


                                    <!-- MOVIE -->

                                    <td>

                                        <div class="movie-name">

                                            <?= htmlspecialchars(
                                                $row['movie_name']
                                            ); ?>

                                        </div>

                                    </td>


                                    <!-- THEATER -->

                                    <td>

                                        <div class="theater-name">

                                            <?= htmlspecialchars(
                                                $row['theater_name']
                                            ); ?>

                                        </div>

                                    </td>


                                    <!-- AMOUNT -->

                                    <td>

                                        <div class="amount">

                                            ₹<?= number_format(

                                                $row['total_amount'],
                                                2

                                            ); ?>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="status
                                            <?= htmlspecialchars(
                                                $row['booking_status']
                                            ); ?>"
                                        >

                                            <?= ucfirst(

                                                htmlspecialchars(
                                                    $row['booking_status']
                                                )

                                            ); ?>

                                        </span>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="6"
                                    style="
                                        text-align:center;
                                        padding:35px;
                                        color:#777;
                                    "
                                >

                                    No bookings found.

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


            <!-- THEATER REVENUE -->

            <div
                class="panel"
                style="margin-top:22px;"
            >


                <div class="panel-header">

                    <h2>

                        Theater
                        <span>Revenue</span>

                    </h2>

                </div>


                <div class="theater-list">


                <?php

                if (
                    $theater_result &&
                    $theater_result->num_rows > 0
                ):

                    $theater_result->data_seek(0);

                ?>


                    <?php while (
                        $theater =
                        $theater_result->fetch_assoc()
                    ):

                        $revenue =
                            (float) $theater['revenue'];

                        $theater_profit =
                            $revenue *
                            ($profit_percentage / 100);

                        $revenue_percentage = 0;

                        if ($total_revenue > 0) {

                            $revenue_percentage =
                                ($revenue / $total_revenue) * 100;

                        }

                    ?>


                        <div class="theater-item">


                            <div class="theater-info">


                                <strong>

                                    <?= htmlspecialchars(
                                        $theater['theater_name']
                                    ); ?>

                                </strong>


                                <span>

                                    ₹<?= number_format(
                                        $revenue,
                                        2
                                    ); ?>

                                </span>


                            </div>


                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    margin-top:5px;
                                    font-size:10px;
                                    color:#777;
                                "
                            >

                                <span>

                                    Profit:
                                    ₹<?= number_format(
                                        $theater_profit,
                                        2
                                    ); ?>

                                </span>


                                <span>

                                    <?= number_format(
                                        $revenue_percentage,
                                        1
                                    ); ?>%

                                </span>

                            </div>


                            <div class="theater-bar">

                                <span
                                    style="
                                        width:
                                        <?= min(
                                            $revenue_percentage,
                                            100
                                        ); ?>%;
                                    "
                                ></span>

                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div
                        style="
                            color:#777;
                            text-align:center;
                            padding:25px;
                        "
                    >

                        No theater revenue data found.

                    </div>


                <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- =================================================
             RIGHT SIDE
        ================================================= -->

        <div>


            <!-- QUICK ACTIONS -->

            <div class="panel">


                <div class="panel-header">

                    <h2>

                        Quick
                        <span>Actions</span>

                    </h2>

                </div>


                <div class="quick-actions">


                    <!-- USERS -->

                    <a
                        href="users.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-users"
                        ></i>

                        <span>
                            Manage Users
                        </span>

                    </a>


                    <!-- BOOKINGS -->

                    <a
                        href="bookings.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-ticket"
                        ></i>

                        <span>
                            Manage Bookings
                        </span>

                    </a>


                    <!-- MOVIES -->

                    <a
                        href="../movies.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-film"
                        ></i>

                        <span>
                            View Movies
                        </span>

                    </a>


                    <!-- THEATERS -->

                    <a
                        href="../theaters.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-building"
                        ></i>

                        <span>
                            View Theaters
                        </span>

                    </a>


                    <!-- SHOWTIMES -->

                    <a
                        href="../showtimes.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-clock"
                        ></i>

                        <span>
                            View Showtimes
                        </span>

                    </a>


                    <!-- WEBSITE -->

                    <a
                        href="../index.php"
                        class="quick-action"
                    >

                        <i
                            class="fa-solid fa-globe"
                        ></i>

                        <span>
                            View Website
                        </span>

                    </a>


                </div>


            </div>


            <!-- PROFIT CARD -->

            <div class="top-theater">


                <small>

                    💰 Profit Summary

                </small>


                <h3>

                    ₹<?= number_format(
                        $total_profit,
                        2
                    ); ?>

                </h3>


                <p>

                    <?= number_format(
                        $profit_percentage,
                        1
                    ); ?>% estimated profit

                </p>


                <div class="profit-progress">

                    <span></span>

                </div>


            </div>


            <!-- TOP THEATER -->

            <div class="top-theater">


                <small>

                    🏆 Best Performing Theater

                </small>


                <h3>

                    <?= htmlspecialchars(
                        $top_theater_name
                    ); ?>

                </h3>


                <p>

                    Revenue:
                    ₹<?= number_format(
                        $top_theater_revenue,
                        2
                    ); ?>

                </p>


            </div>


        </div>


    </div>


</main>


</body>

</html>