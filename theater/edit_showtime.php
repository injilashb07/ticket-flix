
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

$showtime_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($showtime_id <= 0) {
    header("Location: showtimes.php");
    exit();
}


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

$theater_name = $theater['name'] ?? "My Theater";
$theater_location =
    $theater['location']
    ?? ($theater['address'] ?? "Location not available");


/* =========================================================
   GET SHOWTIME
   IMPORTANT: CHECK THEATER ID
========================================================= */

$showtime = null;

$stmt = $conn->prepare("
    SELECT
        st.id,
        st.movie_id,
        st.screen_id,
        st.show_date,
        st.show_time,
        st.price,
        s.screen_name,
        m.name AS movie_name
    FROM showtimes st

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    WHERE st.id = ?
    AND s.theater_id = ?

    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $showtime_id,
    $theater_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $showtime = $result->fetch_assoc();
}

$stmt->close();


if (!$showtime) {

    header("Location: showtimes.php");
    exit();

}


/* =========================================================
   GET MOVIES
========================================================= */

$movies = [];

$result = $conn->query("
    SELECT id, name
    FROM movies
    ORDER BY name ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }

}


/* =========================================================
   GET SCREENS OF THIS THEATER
========================================================= */

$screens = [];

$stmt = $conn->prepare("
    SELECT
        id,
        screen_name
    FROM screens
    WHERE theater_id = ?
    ORDER BY screen_name ASC
");

$stmt->bind_param(
    "i",
    $theater_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $screens[] = $row;
}

$stmt->close();


/* =========================================================
   UPDATE SHOWTIME
========================================================= */

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $movie_id = isset($_POST['movie_id'])
        ? (int) $_POST['movie_id']
        : 0;

    $screen_id = isset($_POST['screen_id'])
        ? (int) $_POST['screen_id']
        : 0;

    $show_date = trim($_POST['show_date'] ?? "");
    $show_time = trim($_POST['show_time'] ?? "");
    $price = isset($_POST['price'])
        ? (float) $_POST['price']
        : 0;


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $movie_id <= 0 ||
        $screen_id <= 0 ||
        empty($show_date) ||
        empty($show_time) ||
        $price <= 0
    ) {

        $error = "Please fill all fields correctly.";

    } else {

        /* ================================================
           CHECK SCREEN BELONGS TO THIS THEATER
        ================================================ */

        $screen_check = $conn->prepare("
            SELECT id
            FROM screens
            WHERE id = ?
            AND theater_id = ?
            LIMIT 1
        ");

        $screen_check->bind_param(
            "ii",
            $screen_id,
            $theater_id
        );

        $screen_check->execute();

        $screen_result =
            $screen_check->get_result();

        if ($screen_result->num_rows === 0) {

            $error =
                "Invalid screen selected.";

        }

        $screen_check->close();


        /* ================================================
           CHECK MOVIE EXISTS
        ================================================ */

        if (empty($error)) {

            $movie_check = $conn->prepare("
                SELECT id
                FROM movies
                WHERE id = ?
                LIMIT 1
            ");

            $movie_check->bind_param(
                "i",
                $movie_id
            );

            $movie_check->execute();

            $movie_result =
                $movie_check->get_result();

            if ($movie_result->num_rows === 0) {

                $error =
                    "Invalid movie selected.";

            }

            $movie_check->close();
        }


        /* ================================================
           CHECK DUPLICATE SHOWTIME
        ================================================ */

        if (empty($error)) {

            $duplicate_check = $conn->prepare("
                SELECT st.id
                FROM showtimes st

                INNER JOIN screens s
                    ON st.screen_id = s.id

                WHERE st.movie_id = ?
                AND st.screen_id = ?
                AND st.show_date = ?
                AND st.show_time = ?
                AND s.theater_id = ?
                AND st.id != ?

                LIMIT 1
            ");

            $duplicate_check->bind_param(
                "iissii",
                $movie_id,
                $screen_id,
                $show_date,
                $show_time,
                $theater_id,
                $showtime_id
            );

            $duplicate_check->execute();

            $duplicate_result =
                $duplicate_check->get_result();

            if ($duplicate_result->num_rows > 0) {

                $error =
                    "This movie already has a showtime at this date, time and screen.";

            }

            $duplicate_check->close();
        }


        /* ================================================
           UPDATE
        ================================================ */

        if (empty($error)) {

            $update = $conn->prepare("
                UPDATE showtimes st

                INNER JOIN screens s
                    ON st.screen_id = s.id

                SET
                    st.movie_id = ?,
                    st.screen_id = ?,
                    st.show_date = ?,
                    st.show_time = ?,
                    st.price = ?

                WHERE st.id = ?
                AND s.theater_id = ?
            ");

            $update->bind_param(
                "iissdii",
                $movie_id,
                $screen_id,
                $show_date,
                $show_time,
                $price,
                $showtime_id,
                $theater_id
            );

            if ($update->execute()) {

                $update->close();

                header(
                    "Location: showtimes.php?updated=1"
                );

                exit();

            } else {

                $error =
                    "Unable to update showtime. Please try again.";

                $update->close();
            }
        }
    }
}


/* =========================================================
   DISPLAY VALUES
========================================================= */

$current_movie_id =
    $_POST['movie_id']
    ?? $showtime['movie_id'];

$current_screen_id =
    $_POST['screen_id']
    ?? $showtime['screen_id'];

$current_date =
    $_POST['show_date']
    ?? $showtime['show_date'];

$current_time =
    $_POST['show_time']
    ?? $showtime['show_time'];

$current_price =
    $_POST['price']
    ?? $showtime['price'];

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
    Edit Showtime | TicketFlix
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
   FORM PANEL
========================================================= */

.form-panel {

    max-width: 850px;

    margin: 0 auto;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 30px;
}


/* =========================================================
   FORM HEADER
========================================================= */

.form-title {

    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 28px;

    padding-bottom: 20px;

    border-bottom:
        1px solid rgba(255,255,255,.07);
}

.form-title-icon {

    width: 50px;
    height: 50px;

    border-radius: 13px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 21px;
}

.form-title h2 {

    font-size: 19px;
}

.form-title p {

    color: #777;

    font-size: 11px;

    margin-top: 3px;
}


/* =========================================================
   ERROR
========================================================= */

.error-message {

    background:
        rgba(244,67,54,.10);

    border:
        1px solid rgba(244,67,54,.25);

    color: #ff7777;

    padding: 12px 15px;

    border-radius: 10px;

    font-size: 11px;

    margin-bottom: 20px;
}

.error-message i {

    margin-right: 7px;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}

.form-group {

    display: flex;

    flex-direction: column;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    color: #aaa;

    font-size: 11px;

    margin-bottom: 8px;

    font-weight: 500;
}

.form-group label i {

    color: #d4af37;

    margin-right: 5px;
}

.form-group input,
.form-group select {

    width: 100%;

    padding: 13px 14px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 10px;

    color: white;

    outline: none;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;
}

.form-group input:focus,
.form-group select:focus {

    border-color:
        rgba(212,175,55,.6);

    box-shadow:
        0 0 0 3px
        rgba(212,175,55,.06);
}

.form-group select option {

    background: #1b1325;

    color: white;
}


/* =========================================================
   BUTTONS
========================================================= */

.buttons {

    display: flex;

    gap: 12px;

    margin-top: 28px;

    padding-top: 20px;

    border-top:
        1px solid rgba(255,255,255,.07);
}

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 12px 22px;

    border-radius: 10px;

    border: none;

    text-decoration: none;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    transition: .3s;
}

.btn-primary {

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #b8941f
        );

    color: #160f20;
}

.btn-primary:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(212,175,55,.18);
}

.btn-secondary {

    background:
        rgba(255,255,255,.07);

    border:
        1px solid rgba(255,255,255,.1);

    color: #aaa;
}

.btn-secondary:hover {

    color: white;

    background:
        rgba(255,255,255,.1);
}


/* =========================================================
   CURRENT SHOW INFO
========================================================= */

.current-info {

    margin-top: 20px;

    padding: 15px;

    border-radius: 12px;

    background:
        rgba(126,87,194,.08);

    border:
        1px solid rgba(126,87,194,.15);

    color: #aaa;

    font-size: 10px;
}

.current-info i {

    color: #b58cff;

    margin-right: 6px;
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

    .top-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }
}

@media(max-width:550px) {

    .main {

        padding: 15px;
    }

    .form-panel {

        padding: 20px;
    }

    .buttons {

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
            <?php echo htmlspecialchars($theater_name); ?>
        </strong>

        <small>
            <?php echo htmlspecialchars($theater_location); ?>
        </small>

    </div>


    <a href="dashboard.php">

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>


    <a href="showtimes.php" class="active">

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
                Edit <span>Showtime</span>
            </h1>

            <p>
                Update movie, screen, date, time and ticket price.
            </p>

        </div>


        <div class="user-box">

            <div class="user-icon">

                <i class="fa-solid fa-user"></i>

            </div>

            <div>

                <small>Logged in as</small>

                <strong>
                    <?php echo htmlspecialchars($theater_name); ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- FORM -->

    <div class="form-panel">


        <div class="form-title">

            <div class="form-title-icon">

                <i class="fa-solid fa-pen-to-square"></i>

            </div>

            <div>

                <h2>
                    Update Showtime
                </h2>

                <p>
                    Make changes to this show's details.
                </p>

            </div>

        </div>


        <?php if (!empty($error)) { ?>

            <div class="error-message">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php } ?>


        <form method="POST">


            <div class="form-grid">


                <!-- MOVIE -->

                <div class="form-group full">

                    <label>

                        <i class="fa-solid fa-film"></i>

                        Movie

                    </label>

                    <select
                        name="movie_id"
                        required
                    >

                        <option value="">
                            Select Movie
                        </option>

                        <?php foreach ($movies as $movie) { ?>

                            <option
                                value="<?php echo (int) $movie['id']; ?>"
                                <?php
                                echo (
                                    (int) $current_movie_id ===
                                    (int) $movie['id']
                                )
                                ? 'selected'
                                : '';
                                ?>
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


                <!-- SCREEN -->

                <div class="form-group">

                    <label>

                        <i class="fa-solid fa-tv"></i>

                        Screen

                    </label>

                    <select
                        name="screen_id"
                        required
                    >

                        <option value="">
                            Select Screen
                        </option>

                        <?php foreach ($screens as $screen) { ?>

                            <option
                                value="<?php echo (int) $screen['id']; ?>"
                                <?php
                                echo (
                                    (int) $current_screen_id ===
                                    (int) $screen['id']
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $screen['screen_name']
                                );
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>


                <!-- PRICE -->

                <div class="form-group">

                    <label>

                        <i class="fa-solid fa-indian-rupee-sign"></i>

                        Ticket Price

                    </label>

                    <input
                        type="number"
                        name="price"
                        min="1"
                        step="0.01"
                        value="<?php echo htmlspecialchars($current_price); ?>"
                        required
                    >

                </div>


                <!-- DATE -->

                <div class="form-group">

                    <label>

                        <i class="fa-solid fa-calendar"></i>

                        Show Date

                    </label>

                    <input
                        type="date"
                        name="show_date"
                        value="<?php echo htmlspecialchars($current_date); ?>"
                        required
                    >

                </div>


                <!-- TIME -->

                <div class="form-group">

                    <label>

                        <i class="fa-solid fa-clock"></i>

                        Show Time

                    </label>

                    <input
                        type="time"
                        name="show_time"
                        value="<?php echo htmlspecialchars($current_time); ?>"
                        required
                    >

                </div>


            </div>


            <div class="current-info">

                <i class="fa-solid fa-circle-info"></i>

                You are editing Showtime
                #<?php echo $showtime_id; ?>

                for

                <strong>
                    <?php echo htmlspecialchars($theater_name); ?>
                </strong>.

            </div>


            <div class="buttons">

                <a
                    href="showtimes.php"
                    class="btn btn-secondary"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Update Showtime

                </button>

            </div>


        </form>


    </div>


</main>

</body>

</html>
