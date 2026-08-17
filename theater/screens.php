<?php

session_start();
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
   GET ONLY THIS THEATER'S SCREENS
========================================================= */

$screens = [];

$stmt = $conn->prepare("
    SELECT *
    FROM screens
    WHERE theater_id = ?
    ORDER BY id ASC
");

$stmt->bind_param("i", $theater_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $screens[] = $row;
}

$stmt->close();


$total_screens = count($screens);

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
    Screens | <?php echo htmlspecialchars($theater_name); ?> | TicketFlix
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


/* THEATER BOX */

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

    background: rgba(212,175,55,.12);

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


/* SIDEBAR LINKS */

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
   HEADER
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
   SUMMARY CARD
========================================================= */

.summary {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 22px;

    margin-bottom: 25px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            rgba(212,175,55,.10),
            rgba(126,87,194,.10)
        );

    border:
        1px solid rgba(212,175,55,.18);
}

.summary-left {

    display: flex;

    align-items: center;

    gap: 15px;
}

.summary-icon {

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

.summary h2 {

    font-size: 18px;
}

.summary p {

    color: #888;

    font-size: 11px;

    margin-top: 3px;
}

.screen-count {

    font-size: 28px;

    font-weight: 700;

    color: #d4af37;
}


/* =========================================================
   SCREEN GRID
========================================================= */

.screen-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


/* =========================================================
   SCREEN CARD
========================================================= */

.screen-card {

    position: relative;

    overflow: hidden;

    padding: 24px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    transition: .3s;
}

.screen-card:hover {

    transform: translateY(-5px);

    border-color:
        rgba(212,175,55,.35);

    box-shadow:
        0 15px 35px rgba(0,0,0,.25);
}

.screen-card::after {

    content: "";

    position: absolute;

    width: 100px;
    height: 100px;

    right: -35px;
    bottom: -35px;

    border-radius: 50%;

    background:
        rgba(212,175,55,.05);
}


/* SCREEN ICON */

.screen-icon {

    width: 55px;
    height: 55px;

    border-radius: 15px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        linear-gradient(
            135deg,
            rgba(126,87,194,.20),
            rgba(212,175,55,.12)
        );

    color: #d4af37;

    font-size: 23px;

    margin-bottom: 18px;
}


/* SCREEN TITLE */

.screen-card h3 {

    font-size: 17px;

    margin-bottom: 8px;
}


/* SCREEN DETAILS */

.screen-details {

    margin-top: 15px;

    padding-top: 14px;

    border-top:
        1px solid rgba(255,255,255,.07);
}

.detail {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 8px;

    font-size: 11px;
}

.detail:last-child {

    margin-bottom: 0;
}

.detail span:first-child {

    color: #777;
}

.detail span:last-child {

    color: #ddd;

    font-weight: 500;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    text-align: center;

    padding: 70px 20px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.07);
}

.empty i {

    font-size: 55px;

    color: #d4af37;

    margin-bottom: 18px;
}

.empty h2 {

    font-size: 20px;

    margin-bottom: 7px;
}

.empty p {

    color: #777;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .screen-grid {

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
}

@media(max-width:600px) {

    .screen-grid {

        grid-template-columns: 1fr;
    }

    .top-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .summary {

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


    <a href="dashboard.php">

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


    <a href="screens.php" class="active">

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

                Theater <span>Screens</span>

            </h1>

            <p>

                View all screens available in your theater.

            </p>

        </div>


        <div class="user-box">

            <div class="user-icon">

                <i class="fa-solid fa-user"></i>

            </div>

            <div>

                <small>Logged in as</small>

                <strong>

                    <?php
                    echo htmlspecialchars($theater_name);
                    ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary">

        <div class="summary-left">

            <div class="summary-icon">

                <i class="fa-solid fa-tv"></i>

            </div>

            <div>

                <h2>

                    Your Theater Screens

                </h2>

                <p>

                    Only screens belonging to
                    <?php echo htmlspecialchars($theater_name); ?>

                    are shown here.

                </p>

            </div>

        </div>


        <div class="screen-count">

            <?php echo $total_screens; ?>

        </div>

    </div>


    <!-- =====================================================
         SCREENS
    ====================================================== -->

    <?php if ($total_screens > 0) { ?>


        <div class="screen-grid">


            <?php foreach ($screens as $index => $screen) { ?>


                <?php

                /*
                 * Different screen table structures ko handle
                 * karne ke liye possible column names check kar rahe hain.
                 */

                $screen_name = "";

                if (!empty($screen['name'])) {

                    $screen_name = $screen['name'];

                } elseif (!empty($screen['screen_name'])) {

                    $screen_name = $screen['screen_name'];

                } elseif (!empty($screen['screen_number'])) {

                    $screen_name =
                        "Screen " . $screen['screen_number'];

                } else {

                    $screen_name =
                        "Screen " . ($index + 1);
                }


                $capacity = null;

                if (isset($screen['capacity'])) {

                    $capacity = $screen['capacity'];

                } elseif (isset($screen['total_seats'])) {

                    $capacity = $screen['total_seats'];
                }


                $screen_type = "";

                if (!empty($screen['screen_type'])) {

                    $screen_type =
                        $screen['screen_type'];

                } elseif (!empty($screen['type'])) {

                    $screen_type =
                        $screen['type'];
                }

                ?>


                <div class="screen-card">


                    <div class="screen-icon">

                        <i class="fa-solid fa-tv"></i>

                    </div>


                    <h3>

                        <?php
                        echo htmlspecialchars($screen_name);
                        ?>

                    </h3>


                    <div class="screen-details">


                        <div class="detail">

                            <span>

                                Screen ID

                            </span>

                            <span>

                                #<?php
                                echo (int)$screen['id'];
                                ?>

                            </span>

                        </div>


                        <?php if ($capacity !== null) { ?>

                            <div class="detail">

                                <span>

                                    Capacity

                                </span>

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $capacity
                                    );
                                    ?>

                                    seats

                                </span>

                            </div>

                        <?php } ?>


                        <?php if ($screen_type !== "") { ?>

                            <div class="detail">

                                <span>

                                    Type

                                </span>

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $screen_type
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php } ?>


                        <div class="detail">

                            <span>

                                Status

                            </span>

                            <span style="color:#6ee7a0;">

                                <i class="fa-solid fa-circle"
                                   style="font-size:7px;">
                                </i>

                                Active

                            </span>

                        </div>


                    </div>


                </div>


            <?php } ?>


        </div>


    <?php } else { ?>


        <div class="empty">

            <i class="fa-solid fa-tv"></i>

            <h2>

                No Screens Found

            </h2>

            <p>

                No screens have been added to
                <?php echo htmlspecialchars($theater_name); ?>
                yet.

            </p>

        </div>


    <?php } ?>


</main>


</body>

</html>