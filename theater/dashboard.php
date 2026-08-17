<?php

/* =========================================================
   START SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CONNECTION
========================================================= */

require_once "../config.php";


/* =========================================================
   THEATER LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION['theater_user_id']) ||
    !isset($_SESSION['theater_id'])
) {
    header("Location: login.php");
    exit();
}

$theater_id = (int) $_SESSION['theater_id'];

$theater_user_name =
    $_SESSION['theater_user_name'] ?? "Theater User";


/* =========================================================
   GET THEATER DETAILS
========================================================= */

$theater = null;

$stmt = $conn->prepare("
    SELECT *
    FROM theaters
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $theater_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $theater = $result->fetch_assoc();
}

$stmt->close();


if (!$theater) {

    session_destroy();

    header("Location: login.php");

    exit();
}


$theater_name =
    $theater['name'] ?? "My Theater";

$theater_location =
    $theater['location']
    ?? ($theater['address'] ?? "Location not available");


/* =========================================================
   TOTAL SCREENS
========================================================= */

$total_screens = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM screens
    WHERE theater_id = ?
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $total_screens = (int) $row['total'];
}

$stmt->close();


/* =========================================================
   TOTAL SHOWTIMES
========================================================= */

$total_showtimes = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM showtimes st

    INNER JOIN screens s
        ON st.screen_id = s.id

    WHERE s.theater_id = ?
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $total_showtimes = (int) $row['total'];
}

$stmt->close();


/* =========================================================
   TOTAL BOOKINGS
========================================================= */

$total_bookings = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total

    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    WHERE s.theater_id = ?
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $total_bookings = (int) $row['total'];
}

$stmt->close();


/* =========================================================
   TOTAL REVENUE
========================================================= */

$total_revenue = 0;

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(b.total_amount), 0) AS revenue

    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    WHERE s.theater_id = ?
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $total_revenue = (float) $row['revenue'];
}

$stmt->close();


/* =========================================================
   TODAY'S SHOWTIMES
========================================================= */

$today_showtimes = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total

    FROM showtimes st

    INNER JOIN screens s
        ON st.screen_id = s.id

    WHERE s.theater_id = ?

    AND DATE(st.show_date) = CURDATE()
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $today_showtimes = (int) $row['total'];
}

$stmt->close();


/* =========================================================
   UPCOMING SHOWTIMES
========================================================= */

$upcoming_showtimes = [];

$stmt = $conn->prepare("
    SELECT

        st.id,

        st.show_date,

        st.show_time,

        m.name AS movie_name,

        m.poster_image,

        s.screen_name AS screen_name

    FROM showtimes st

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    WHERE s.theater_id = ?

    AND (
        st.show_date > CURDATE()

        OR (
            st.show_date = CURDATE()

            AND st.show_time >= CURTIME()
        )
    )

    ORDER BY
        st.show_date ASC,
        st.show_time ASC

    LIMIT 6
");

$stmt->bind_param("i", $theater_id);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $upcoming_showtimes[] = $row;
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

<title>
    Theater Dashboard | TicketFlix
</title>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


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
            rgba(126,87,194,.25),
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

    padding: 28px 18px;

    z-index: 100;
}


.logo {

    text-align: center;

    font-size: 26px;

    font-weight: 800;

    margin-bottom: 8px;
}


.logo i,
.logo span {

    color: #d4af37;
}


.portal {

    text-align: center;

    color: #777;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 2px;

    margin-bottom: 30px;
}


/* =========================================================
   THEATER BOX
========================================================= */

.theater-box {

    background:
        rgba(212,175,55,.08);

    border:
        1px solid rgba(212,175,55,.15);

    padding: 14px;

    border-radius: 14px;

    margin-bottom: 25px;
}


.theater-box .icon {

    width: 40px;
    height: 40px;

    border-radius: 10px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    margin-bottom: 9px;
}


.theater-box strong {

    display: block;

    font-size: 13px;

    color: white;
}


.theater-box small {

    display: block;

    color: #777;

    font-size: 10px;

    margin-top: 3px;
}


/* =========================================================
   SIDEBAR LINKS
========================================================= */

.sidebar a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 13px 15px;

    margin-bottom: 7px;

    color: #aaa;

    text-decoration: none;

    border-radius: 11px;

    font-size: 13px;

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
   TOP HEADER
========================================================= */

.top-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}


.top-header h1 {

    font-size: 28px;
}


.top-header h1 span {

    color: #d4af37;
}


.top-header p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;
}


.user-box {

    display: flex;

    align-items: center;

    gap: 10px;
}


.user-icon {

    width: 42px;
    height: 42px;

    border-radius: 50%;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #7e3ff2,
            #a35cff
        );

    color: white;
}


.user-box small {

    display: block;

    color: #777;

    font-size: 10px;
}


.user-box strong {

    font-size: 12px;
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.stat-card {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 18px;

    padding: 20px;

    position: relative;

    overflow: hidden;
}


.stat-card::after {

    content: "";

    position: absolute;

    width: 80px;
    height: 80px;

    right: -25px;
    bottom: -25px;

    border-radius: 50%;

    background:
        rgba(212,175,55,.05);
}


.stat-icon {

    width: 45px;
    height: 45px;

    border-radius: 12px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    margin-bottom: 15px;
}


.stat-card h3 {

    font-size: 25px;

    margin-bottom: 3px;
}


.stat-card p {

    color: #888;

    font-size: 11px;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.section-title {

    margin-bottom: 15px;
}


.section-title h2 {

    font-size: 19px;
}


.section-title h2 span {

    color: #d4af37;
}


.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 30px;
}


.quick-card {

    text-decoration: none;

    color: white;

    padding: 20px;

    border-radius: 16px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    transition: .3s;
}


.quick-card:hover {

    transform: translateY(-4px);

    border-color:
        rgba(212,175,55,.35);

    background:
        rgba(212,175,55,.07);
}


.quick-card i {

    font-size: 23px;

    color: #d4af37;

    margin-bottom: 12px;
}


.quick-card h3 {

    font-size: 13px;

    margin-bottom: 4px;
}


.quick-card p {

    color: #777;

    font-size: 10px;
}


/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}


.panel {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 20px;

    padding: 22px;
}


.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}


.panel-header h2 {

    font-size: 17px;
}


.panel-header h2 span {

    color: #d4af37;
}


.panel-header a {

    color: #d4af37;

    text-decoration: none;

    font-size: 11px;
}


/* =========================================================
   UPCOMING SHOWS
========================================================= */

.show-item {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 0;

    border-bottom:
        1px solid rgba(255,255,255,.06);
}


.show-item:last-child {

    border-bottom: none;
}


.show-poster {

    width: 45px;
    height: 60px;

    object-fit: cover;

    border-radius: 7px;
}


.show-details {

    flex: 1;
}


.show-details strong {

    display: block;

    font-size: 12px;

    margin-bottom: 3px;
}


.show-details span {

    display: block;

    color: #777;

    font-size: 10px;
}


.show-time {

    color: #d4af37;

    font-size: 11px;

    font-weight: 600;

    text-align: right;
}


.no-data {

    text-align: center;

    padding: 35px 10px;

    color: #777;

    font-size: 12px;
}


.no-data i {

    font-size: 30px;

    color: #d4af37;

    margin-bottom: 10px;
}


/* =========================================================
   THEATER INFO
========================================================= */

.info-row {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 14px 0;

    border-bottom:
        1px solid rgba(255,255,255,.06);
}


.info-row:last-child {

    border-bottom: none;
}


.info-icon {

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        rgba(126,87,194,.12);

    color: #b58cff;
}


.info-row small {

    display: block;

    color: #777;

    font-size: 9px;
}


.info-row strong {

    display: block;

    font-size: 12px;

    margin-top: 2px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .quick-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media(max-width:800px) {

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


    .portal,
    .theater-box,
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


    .content-grid {

        grid-template-columns: 1fr;
    }
}


@media(max-width:550px) {

    .stats-grid,
    .quick-grid {

        grid-template-columns: 1fr;
    }


    .top-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </div>


    <div class="portal">

        Theater Portal

    </div>


    <div class="theater-box">

        <div class="icon">

            <i class="fa-solid fa-building"></i>

        </div>


        <strong>

            <?php
            echo htmlspecialchars($theater_name);
            ?>

        </strong>


        <small>

            <?php
            echo htmlspecialchars($theater_location);
            ?>

        </small>

    </div>


    <a href="dashboard.php" class="active">

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>


    <a href="showtimes.php">

        <i class="fa-solid fa-clock"></i>

        <span>Showtimes</span>

    </a>


    <a href="add_showtime.php">

        <i class="fa-solid fa-circle-plus"></i>

        <span>Add Showtime</span>

    </a>


    <a href="screens.php">

        <i class="fa-solid fa-tv"></i>

        <span>Screens</span>

    </a>


    <a href="bookings.php">

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>


    <a href="../index.php">

        <i class="fa-solid fa-globe"></i>

        <span>View Website</span>

    </a>


    <a href="logout.php" class="logout">

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>Logout</span>

    </a>


</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- HEADER -->

    <div class="top-header">


        <div>

            <h1>

                Welcome,

                <span>

                    <?php
                    echo htmlspecialchars($theater_user_name);
                    ?>

                </span>

                👋

            </h1>


            <p>

                Manage your theater and showtimes from here.

            </p>

        </div>


        <div class="user-box">


            <div class="user-icon">

                <i class="fa-solid fa-user"></i>

            </div>


            <div>

                <small>

                    Logged in as

                </small>


                <strong>

                    <?php
                    echo htmlspecialchars($theater_name);
                    ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon">

                <i class="fa-solid fa-tv"></i>

            </div>


            <h3>

                <?php
                echo $total_screens;
                ?>

            </h3>


            <p>

                Total Screens

            </p>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="fa-solid fa-clock"></i>

            </div>


            <h3>

                <?php
                echo $total_showtimes;
                ?>

            </h3>


            <p>

                Total Showtimes

            </p>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="fa-solid fa-ticket"></i>

            </div>


            <h3>

                <?php
                echo $total_bookings;
                ?>

            </h3>


            <p>

                Total Bookings

            </p>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="fa-solid fa-indian-rupee-sign"></i>

            </div>


            <h3>

                ₹<?php
                echo number_format(
                    $total_revenue,
                    2
                );
                ?>

            </h3>


            <p>

                Total Revenue

            </p>

        </div>

    </div>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="section-title">

        <h2>

            Quick <span>Actions</span>

        </h2>

    </div>


    <div class="quick-grid">


        <a
            href="add_showtime.php"
            class="quick-card"
        >

            <i class="fa-solid fa-plus"></i>

            <h3>

                Add Showtime

            </h3>

            <p>

                Create a new movie show

            </p>

        </a>


        <a
            href="showtimes.php"
            class="quick-card"
        >

            <i class="fa-solid fa-clock"></i>

            <h3>

                Manage Showtimes

            </h3>

            <p>

                Edit or remove your shows

            </p>

        </a>


        <a
            href="screens.php"
            class="quick-card"
        >

            <i class="fa-solid fa-tv"></i>

            <h3>

                Manage Screens

            </h3>

            <p>

                View your theater screens

            </p>

        </a>


        <a
            href="bookings.php"
            class="quick-card"
        >

            <i class="fa-solid fa-ticket"></i>

            <h3>

                View Bookings

            </h3>

            <p>

                Check customer bookings

            </p>

        </a>


    </div>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content-grid">


        <!-- UPCOMING SHOWTIMES -->

        <div class="panel">


            <div class="panel-header">

                <h2>

                    Upcoming <span>Shows</span>

                </h2>


                <a href="showtimes.php">

                    View All

                </a>

            </div>


            <?php if (count($upcoming_showtimes) > 0) { ?>


                <?php foreach ($upcoming_showtimes as $show) { ?>


                    <div class="show-item">


                        <?php

                        $show_poster =
                            !empty($show['poster_image'])

                            ? $show['poster_image']

                            : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=200&q=80";

                        ?>


                        <img
                            src="<?php
                            echo htmlspecialchars($show_poster);
                            ?>"
                            class="show-poster"
                            alt="Movie"
                        >


                        <div class="show-details">


                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $show['movie_name']
                                );

                                ?>

                            </strong>


                            <span>

                                <?php

                                echo htmlspecialchars(
                                    $show['screen_name']
                                );

                                ?>

                            </span>


                            <span>

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $show['show_date']
                                    )
                                );

                                ?>

                            </span>


                        </div>


                        <div class="show-time">

                            <?php

                            echo date(
                                "h:i A",
                                strtotime(
                                    $show['show_time']
                                )
                            );

                            ?>

                        </div>


                    </div>


                <?php } ?>


            <?php } else { ?>


                <div class="no-data">


                    <i
                        class="fa-solid fa-calendar-xmark"
                    ></i>


                    <br>


                    No upcoming showtimes.


                </div>


            <?php } ?>


        </div>


        <!-- THEATER INFORMATION -->

        <div class="panel">


            <div class="panel-header">

                <h2>

                    Theater <span>Information</span>

                </h2>

            </div>


            <div class="info-row">


                <div class="info-icon">

                    <i
                        class="fa-solid fa-building"
                    ></i>

                </div>


                <div>

                    <small>

                        Theater Name

                    </small>


                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $theater_name
                        );

                        ?>

                    </strong>

                </div>

            </div>


            <div class="info-row">


                <div class="info-icon">

                    <i
                        class="fa-solid fa-location-dot"
                    ></i>

                </div>


                <div>

                    <small>

                        Location

                    </small>


                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $theater_location
                        );

                        ?>

                    </strong>

                </div>

            </div>


            <div class="info-row">


                <div class="info-icon">

                    <i
                        class="fa-solid fa-calendar-day"
                    ></i>

                </div>


                <div>

                    <small>

                        Today's Shows

                    </small>


                    <strong>

                        <?php
                        echo $today_showtimes;
                        ?>

                        Showtime(s)

                    </strong>

                </div>

            </div>


            <div class="info-row">


                <div class="info-icon">

                    <i
                        class="fa-solid fa-user"
                    ></i>

                </div>


                <div>

                    <small>

                        Logged-in User

                    </small>


                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $theater_user_name
                        );

                        ?>

                    </strong>

                </div>

            </div>


        </div>


    </div>


</main>


</body>

</html>