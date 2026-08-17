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
   VARIABLES
========================================================= */

$error = "";
$success = "";


/* =========================================================
   GET MOVIES
========================================================= */

$movies = [];

$stmt = $conn->prepare("
    SELECT id, name
    FROM movies
    ORDER BY name ASC
");

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $movies[] = $row;
}

$stmt->close();


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


/* =========================================================
   ADD SHOWTIME
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $movie_id = (int) ($_POST['movie_id'] ?? 0);

    $screen_id = (int) ($_POST['screen_id'] ?? 0);

    $show_date = trim($_POST['show_date'] ?? '');

    $show_time = trim($_POST['show_time'] ?? '');


    /* -----------------------------------------------------
       VALIDATION
    ----------------------------------------------------- */

    if (
        $movie_id <= 0 ||
        $screen_id <= 0 ||
        $show_date === "" ||
        $show_time === ""
    ) {

        $error = "Please fill all the fields.";

    } else {

        /* -------------------------------------------------
           CHECK SCREEN BELONGS TO LOGGED-IN THEATER
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT id
            FROM screens
            WHERE id = ?
            AND theater_id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $screen_id,
            $theater_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $screen_exists = $result->num_rows === 1;

        $stmt->close();


        if (!$screen_exists) {

            $error =
                "Invalid screen selected for this theater.";

        } else {

            /* ---------------------------------------------
               CHECK MOVIE EXISTS
            --------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT id
                FROM movies
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $movie_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $movie_exists = $result->num_rows === 1;

            $stmt->close();


            if (!$movie_exists) {

                $error = "Invalid movie selected.";

            } else {

                /* -----------------------------------------
                   CHECK DUPLICATE SHOWTIME
                ----------------------------------------- */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM showtimes
                    WHERE screen_id = ?
                    AND show_date = ?
                    AND show_time = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "iss",
                    $screen_id,
                    $show_date,
                    $show_time
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $duplicate =
                    $result->num_rows > 0;

                $stmt->close();


                if ($duplicate) {

                    $error =
                        "A showtime already exists for this screen at the selected date and time.";

                } else {

                    /* -------------------------------------
                       INSERT SHOWTIME
                    ------------------------------------- */

                    $stmt = $conn->prepare("
                        INSERT INTO showtimes
                        (
                            movie_id,
                            screen_id,
                            show_date,
                            show_time
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "iiss",
                        $movie_id,
                        $screen_id,
                        $show_date,
                        $show_time
                    );


                    if ($stmt->execute()) {

                        $success =
                            "Showtime added successfully!";

                        /*
                         * Clear selected values
                         */

                        $movie_id = 0;
                        $screen_id = 0;
                        $show_date = "";
                        $show_time = "";

                    } else {

                        $error =
                            "Unable to add showtime. Please try again.";
                    }

                    $stmt->close();
                }
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
    Add Showtime | TicketFlix
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

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    margin-bottom: 9px;
}

.theater-box strong {

    display: block;

    font-size: 13px;
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

    min-height: 100vh;
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
   FORM CONTAINER
========================================================= */

.form-wrapper {

    max-width: 850px;

    margin: 0 auto;
}

.form-card {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 30px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.25);
}


/* =========================================================
   FORM HEADER
========================================================= */

.form-header {

    display: flex;

    align-items: center;

    gap: 15px;

    padding-bottom: 22px;

    margin-bottom: 25px;

    border-bottom:
        1px solid rgba(255,255,255,.08);
}

.form-header-icon {

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

.form-header h2 {

    font-size: 20px;
}

.form-header p {

    color: #777;

    font-size: 11px;

    margin-top: 3px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 13px 15px;

    border-radius: 11px;

    margin-bottom: 20px;

    font-size: 12px;

    display: flex;

    align-items: center;

    gap: 10px;
}

.alert-error {

    background:
        rgba(231,76,60,.12);

    border:
        1px solid rgba(231,76,60,.25);

    color: #ff8175;
}

.alert-success {

    background:
        rgba(46,204,113,.12);

    border:
        1px solid rgba(46,204,113,.25);

    color: #6ee7a0;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}

.form-group {

    margin-bottom: 5px;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    display: block;

    color: #aaa;

    font-size: 12px;

    margin-bottom: 8px;
}

.input-box {

    position: relative;
}

.input-box i {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    color: #d4af37;

    pointer-events: none;
}

.input-box input,
.input-box select {

    width: 100%;

    padding: 14px 15px 14px 43px;

    border-radius: 11px;

    border:
        1px solid rgba(255,255,255,.10);

    background:
        rgba(0,0,0,.28);

    color: white;

    outline: none;

    font-family: inherit;

    font-size: 12px;
}

.input-box select {

    cursor: pointer;

    appearance: auto;
}

.input-box select option {

    background: #18111f;

    color: white;
}

.input-box input:focus,
.input-box select:focus {

    border-color: #d4af37;

    box-shadow:
        0 0 0 3px rgba(212,175,55,.07);
}


/* =========================================================
   THEATER INFO
========================================================= */

.theater-info {

    margin-top: 25px;

    padding: 16px;

    border-radius: 13px;

    background:
        rgba(126,87,194,.08);

    border:
        1px solid rgba(126,87,194,.15);
}

.theater-info-title {

    color: #b58cff;

    font-size: 11px;

    margin-bottom: 7px;
}

.theater-info p {

    color: #aaa;

    font-size: 11px;
}

.theater-info strong {

    color: white;
}


/* =========================================================
   BUTTONS
========================================================= */

.button-row {

    display: flex;

    gap: 12px;

    margin-top: 25px;
}

.btn {

    border: none;

    padding: 13px 22px;

    border-radius: 11px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    transition: .3s;
}

.btn-primary {

    flex: 1;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1cf5b
        );

    color: #171020;
}

.btn-primary:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px rgba(212,175,55,.20);
}

.btn-secondary {

    background:
        rgba(255,255,255,.07);

    color: #aaa;

    border:
        1px solid rgba(255,255,255,.08);
}

.btn-secondary:hover {

    color: white;

    background:
        rgba(255,255,255,.10);
}


/* =========================================================
   NO DATA
========================================================= */

.no-data {

    text-align: center;

    padding: 30px;

    border-radius: 15px;

    background:
        rgba(231,76,60,.07);

    border:
        1px solid rgba(231,76,60,.15);

    color: #aaa;

    font-size: 12px;
}

.no-data i {

    color: #d4af37;

    font-size: 28px;

    margin-bottom: 10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

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

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }
}

@media(max-width:550px) {

    .top-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .form-card {

        padding: 20px;
    }

    .button-row {

        flex-direction: column;
    }

    .btn {

        width: 100%;
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


    <a href="add_showtime.php" class="active">

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

                Add <span>Showtime</span>

            </h1>

            <p>

                Create a new movie show for your theater.

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
         FORM
    ====================================================== -->

    <div class="form-wrapper">

        <div class="form-card">


            <!-- FORM HEADER -->

            <div class="form-header">

                <div class="form-header-icon">

                    <i class="fa-solid fa-calendar-plus"></i>

                </div>

                <div>

                    <h2>

                        Create New Showtime

                    </h2>

                    <p>

                        Select movie, screen, date and time.

                    </p>

                </div>

            </div>


            <!-- ERROR -->

            <?php if ($error !== "") { ?>

                <div class="alert alert-error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>

                        <?php
                        echo htmlspecialchars($error);
                        ?>

                    </span>

                </div>

            <?php } ?>


            <!-- SUCCESS -->

            <?php if ($success !== "") { ?>

                <div class="alert alert-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>

                        <?php
                        echo htmlspecialchars($success);
                        ?>

                    </span>

                </div>

            <?php } ?>


            <?php if (count($screens) === 0) { ?>


                <div class="no-data">

                    <i class="fa-solid fa-tv"></i>

                    <br>

                    No screens are available for this theater.

                    <br><br>

                    Please add a screen first.

                </div>


            <?php } elseif (count($movies) === 0) { ?>


                <div class="no-data">

                    <i class="fa-solid fa-film"></i>

                    <br>

                    No movies are available.

                    <br><br>

                    Please add movies from the admin panel first.

                </div>


            <?php } else { ?>


                <form method="POST">


                    <div class="form-grid">


                        <!-- MOVIE -->

                        <div class="form-group">

                            <label>

                                Movie

                            </label>

                            <div class="input-box">

                                <i class="fa-solid fa-film"></i>

                                <select
                                    name="movie_id"
                                    required
                                >

                                    <option value="">

                                        Select Movie

                                    </option>


                                    <?php foreach ($movies as $movie) { ?>

                                        <option
                                            value="<?php
                                            echo (int)$movie['id'];
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $movie['name']
                                            );
                                            ?>

                                        </option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>


                        <!-- SCREEN -->

                        <div class="form-group">

                            <label>

                                Screen

                            </label>

                            <div class="input-box">

                                <i class="fa-solid fa-tv"></i>

                                <select
                                    name="screen_id"
                                    required
                                >

                                    <option value="">

                                        Select Screen

                                    </option>


                                    <?php foreach ($screens as $index => $screen) { ?>

                                        <?php

                                        if (!empty($screen['name'])) {

                                            $screen_display =
                                                $screen['name'];

                                        } elseif (!empty($screen['screen_name'])) {

                                            $screen_display =
                                                $screen['screen_name'];

                                        } elseif (!empty($screen['screen_number'])) {

                                            $screen_display =
                                                "Screen "
                                                . $screen['screen_number'];

                                        } else {

                                            $screen_display =
                                                "Screen "
                                                . ($index + 1);
                                        }

                                        ?>


                                        <option
                                            value="<?php
                                            echo (int)$screen['id'];
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $screen_display
                                            );
                                            ?>

                                        </option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>


                        <!-- DATE -->

                        <div class="form-group">

                            <label>

                                Show Date

                            </label>

                            <div class="input-box">

                                <i class="fa-solid fa-calendar"></i>

                                <input
                                    type="date"
                                    name="show_date"
                                    min="<?php
                                    echo date('Y-m-d');
                                    ?>"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $show_date ?? ''
                                    );
                                    ?>"
                                    required
                                >

                            </div>

                        </div>


                        <!-- TIME -->

                        <div class="form-group">

                            <label>

                                Show Time

                            </label>

                            <div class="input-box">

                                <i class="fa-solid fa-clock"></i>

                                <input
                                    type="time"
                                    name="show_time"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $show_time ?? ''
                                    );
                                    ?>"
                                    required
                                >

                            </div>

                        </div>


                    </div>


                    <!-- THEATER INFO -->

                    <div class="theater-info">

                        <div class="theater-info-title">

                            <i class="fa-solid fa-building"></i>

                            THEATER

                        </div>

                        <p>

                            This showtime will be added to:

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $theater_name
                                );
                                ?>

                            </strong>

                            —

                            <?php
                            echo htmlspecialchars(
                                $theater_location
                            );
                            ?>

                        </p>

                    </div>


                    <!-- BUTTONS -->

                    <div class="button-row">

                        <a
                            href="showtimes.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Back to Showtimes

                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-plus"></i>

                            Add Showtime

                        </button>

                    </div>


                </form>


            <?php } ?>


        </div>

    </div>


</main>


</body>

</html>