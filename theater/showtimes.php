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
    $theater['address'] ?? "Location not available";


/* =========================================================
   SEARCH
========================================================= */

$search = trim($_GET['search'] ?? "");


/* =========================================================
   GET SHOWTIMES
========================================================= */

$showtimes = [];

$sql = "
    SELECT
        st.id,
        st.show_date,
        st.show_time,
        st.price,

        m.id AS movie_id,
        m.name AS movie_name,
        m.poster_image,

        s.id AS screen_id,
        s.screen_name

    FROM showtimes st

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    WHERE s.theater_id = ?
";


/* =========================================================
   SEARCH FILTER
========================================================= */

if ($search !== "") {

    $sql .= "
        AND (
            m.name LIKE ?
            OR s.screen_name LIKE ?
            OR DATE_FORMAT(
                st.show_date,
                '%d %M %Y'
            ) LIKE ?
        )
    ";
}


$sql .= "
    ORDER BY
        st.show_date ASC,
        st.show_time ASC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {
    die("Database Error: " . $conn->error);
}


if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "isss",
        $theater_id,
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt->bind_param(
        "i",
        $theater_id
    );
}


$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $showtimes[] = $row;
}


$stmt->close();


/* =========================================================
   SUCCESS / ERROR MESSAGE
========================================================= */

$message = "";
$message_type = "";


if (isset($_GET['success'])) {

    if ($_GET['success'] === "deleted") {

        $message =
            "Showtime deleted successfully.";

        $message_type =
            "success";
    }
}


if (isset($_GET['error'])) {

    if ($_GET['error'] === "has_bookings") {

        $message =
            "This showtime cannot be deleted because bookings already exist.";

        $message_type =
            "error";

    } elseif ($_GET['error'] === "not_allowed") {

        $message =
            "You are not allowed to delete this showtime.";

        $message_type =
            "error";

    } elseif ($_GET['error'] === "delete_failed") {

        $message =
            "Unable to delete the showtime.";

        $message_type =
            "error";

    } elseif ($_GET['error'] === "invalid_showtime") {

        $message =
            "Invalid showtime selected.";

        $message_type =
            "error";
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

<title>Showtimes | TicketFlix</title>


<!-- =====================================================
     GOOGLE FONT
===================================================== -->

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<!-- =====================================================
     FONT AWESOME
===================================================== -->

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

    min-height: 100vh;
}


/* =========================================================
   HEADER
========================================================= */

.top-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
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
   ACTION BAR
========================================================= */

.action-bar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;
}


.search-form {

    display: flex;

    gap: 8px;

    width: 430px;
}


.search-input {

    flex: 1;

    position: relative;
}


.search-input i {

    position: absolute;

    left: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #777;
}


.search-input input {

    width: 100%;

    padding:
        12px 12px 12px 38px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 10px;

    color: white;

    outline: none;

    font-family: Poppins, sans-serif;

    font-size: 12px;
}


.search-input input:focus {

    border-color:
        rgba(212,175,55,.5);
}


.search-btn {

    border: none;

    padding: 0 18px;

    border-radius: 10px;

    background: #d4af37;

    color: #100b18;

    font-weight: 700;

    cursor: pointer;
}


.clear-btn {

    display: flex;

    align-items: center;

    justify-content: center;

    width: 45px;

    border-radius: 10px;

    background:
        rgba(255,255,255,.06);

    color: #aaa;

    text-decoration: none;
}


.add-btn {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 12px 18px;

    border-radius: 10px;

    background: #d4af37;

    color: #100b18;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

    transition: .3s;
}


.add-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(212,175,55,.2);
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 12px;

    display: flex;

    align-items: center;

    gap: 10px;
}


.message.success {

    background:
        rgba(76,175,80,.12);

    border:
        1px solid rgba(76,175,80,.25);

    color: #70df70;
}


.message.error {

    background:
        rgba(244,67,54,.12);

    border:
        1px solid rgba(244,67,54,.25);

    color: #ff7777;
}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 20px;

    padding: 22px;

    overflow: hidden;
}


.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.panel-header h2 {

    font-size: 19px;
}


.panel-header h2 span {

    color: #d4af37;
}


.count {

    color: #888;

    font-size: 11px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}


thead {

    background:
        rgba(212,175,55,.08);
}


th {

    padding: 14px 12px;

    text-align: left;

    color: #d4af37;

    font-size: 11px;

    font-weight: 600;

    white-space: nowrap;
}


td {

    padding: 14px 12px;

    border-bottom:
        1px solid rgba(255,255,255,.06);

    color: #ddd;

    font-size: 11px;

    vertical-align: middle;
}


tbody tr {

    transition: .2s;
}


tbody tr:hover {

    background:
        rgba(212,175,55,.04);
}


/* =========================================================
   MOVIE CELL
========================================================= */

.movie-cell {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 210px;
}


/* =========================================================
   MOVIE POSTER
========================================================= */

.movie-poster {

    width: 48px;

    height: 65px;

    object-fit: cover;

    object-position: center;

    border-radius: 7px;

    display: block;

    flex-shrink: 0;

    background: #21172c;

    border:
        1px solid rgba(212,175,55,.18);

    box-shadow:
        0 4px 12px rgba(0,0,0,.25);
}


/* =========================================================
   MOVIE NAME
========================================================= */

.movie-name {

    color: white;

    font-weight: 600;

    font-size: 11px;

    line-height: 1.4;
}


/* =========================================================
   SCREEN
========================================================= */

.screen-name {

    color: #b58cff;

    font-weight: 600;

    white-space: nowrap;
}


/* =========================================================
   DATE/TIME
========================================================= */

.show-date {

    color: white;

    font-weight: 600;

    display: block;

    white-space: nowrap;
}


.show-time {

    color: #888;

    font-size: 10px;

    margin-top: 3px;

    display: block;
}


/* =========================================================
   PRICE
========================================================= */

.price {

    color: #d4af37;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;
}


.action-btn {

    width: 34px;
    height: 34px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 8px;

    text-decoration: none;

    transition: .2s;
}


.edit-btn {

    background:
        rgba(126,87,194,.12);

    color: #b58cff;

    border:
        1px solid rgba(126,87,194,.2);
}


.delete-btn {

    background:
        rgba(244,67,54,.10);

    color: #ff7777;

    border:
        1px solid rgba(244,67,54,.2);
}


.action-btn:hover {

    transform: translateY(-2px);
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 65px 20px;

    color: #777;
}


.empty i {

    font-size: 45px;

    color: #d4af37;

    margin-bottom: 15px;
}


.empty h3 {

    color: #aaa;

    font-size: 17px;

    margin-bottom: 6px;
}


.empty p {

    font-size: 11px;

    margin-bottom: 18px;
}


.empty a {

    display: inline-block;

    padding: 10px 16px;

    background: #d4af37;

    color: #100b18;

    text-decoration: none;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .main {

        padding: 25px;
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


    .top-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .action-bar {

        flex-direction: column;

        align-items: stretch;
    }


    .search-form {

        width: 100%;
    }


    .add-btn {

        justify-content: center;
    }
}


@media(max-width:550px) {

    .main {

        padding: 15px;
    }


    .search-form {

        flex-wrap: wrap;
    }


    .search-input {

        width: 100%;

        flex: none;
    }


    .search-btn {

        height: 40px;

        padding: 0 15px;
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


    <!-- THEATER DETAILS -->

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


    <!-- DASHBOARD -->

    <a href="dashboard.php">

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>


    <!-- SHOWTIMES -->

    <a
        href="showtimes.php"
        class="active"
    >

        <i class="fa-solid fa-clock"></i>

        <span>Showtimes</span>

    </a>


    <!-- ADD SHOWTIME -->

    <a href="add_showtime.php">

        <i class="fa-solid fa-circle-plus"></i>

        <span>Add Showtime</span>

    </a>


    <!-- SCREENS -->

    <a href="screens.php">

        <i class="fa-solid fa-tv"></i>

        <span>Screens</span>

    </a>


    <!-- BOOKINGS -->

    <a href="bookings.php">

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>


    <!-- WEBSITE -->

    <a href="../index.php">

        <i class="fa-solid fa-globe"></i>

        <span>View Website</span>

    </a>


    <!-- LOGOUT -->

    <a
        href="logout.php"
        class="logout"
    >

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

                Manage
                <span>Showtimes</span>

            </h1>


            <p>

                Search, edit and manage showtimes for your theater.

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
         ACTION BAR
    ====================================================== -->

    <div class="action-bar">


        <!-- SEARCH -->

        <form
            method="GET"
            action="showtimes.php"
            class="search-form"
        >

            <div class="search-input">

                <i class="fa-solid fa-search"></i>


                <input
                    type="text"
                    name="search"
                    placeholder="Search movie, screen..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

            </div>


            <button
                type="submit"
                class="search-btn"
            >

                Search

            </button>


            <?php if ($search !== "") { ?>

                <a
                    href="showtimes.php"
                    class="clear-btn"
                    title="Clear Search"
                >

                    <i class="fa-solid fa-xmark"></i>

                </a>

            <?php } ?>


        </form>


        <!-- ADD SHOWTIME -->

        <a
            href="add_showtime.php"
            class="add-btn"
        >

            <i class="fa-solid fa-plus"></i>

            Add Showtime

        </a>


    </div>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== "") { ?>

        <div
            class="message <?php echo $message_type; ?>"
        >

            <?php if ($message_type === "success") { ?>

                <i class="fa-solid fa-circle-check"></i>

            <?php } else { ?>

                <i class="fa-solid fa-circle-exclamation"></i>

            <?php } ?>


            <?php

            echo htmlspecialchars($message);

            ?>

        </div>

    <?php } ?>


    <!-- =====================================================
         SHOWTIMES PANEL
    ====================================================== -->

    <div class="panel">


        <div class="panel-header">

            <h2>

                Your
                <span>Showtimes</span>

            </h2>


            <div class="count">

                <?php echo count($showtimes); ?>

                showtime(s)

            </div>

        </div>


        <?php if (!empty($showtimes)) { ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Movie
                            </th>

                            <th>
                                Screen
                            </th>

                            <th>
                                Date & Time
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($showtimes as $show) { ?>


                        <?php

                        /*
                         * =================================================
                         * POSTER PATH FIX
                         * =================================================
                         *
                         * Database example:
                         *
                         * uploads/the_godfather_1787219774.jpg
                         *
                         * Current file:
                         *
                         * theater/showtimes.php
                         *
                         * So browser needs:
                         *
                         * ../uploads/the_godfather_1787219774.jpg
                         *
                         */

                        $poster = "";

                        if (!empty($show['poster_image'])) {

                            $posterPath =
                                trim($show['poster_image']);


                            /*
                             * If database contains full URL
                             */

                            if (
                                preg_match(
                                    '/^https?:\/\//i',
                                    $posterPath
                                )
                            ) {

                                $poster =
                                    $posterPath;

                            }


                            /*
                             * If database contains uploads/...
                             */

                            else {

                                $poster =
                                    "../" .
                                    ltrim(
                                        $posterPath,
                                        "/"
                                    );
                            }

                        }


                        /*
                         * Fallback image
                         */

                        if (empty($poster)) {

                            $poster =
                                "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=200&q=80";
                        }

                        ?>


                        <tr>


                            <!-- =================================================
                                 MOVIE
                            ================================================= -->

                            <td>

                                <div class="movie-cell">


                                    <img
                                        src="<?php echo htmlspecialchars($poster); ?>"
                                        class="movie-poster"
                                        alt="<?php echo htmlspecialchars($show['movie_name']); ?>"
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=200&q=80';"
                                    >


                                    <span class="movie-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $show['movie_name']
                                        );

                                        ?>

                                    </span>


                                </div>

                            </td>


                            <!-- =================================================
                                 SCREEN
                            ================================================= -->

                            <td>

                                <span class="screen-name">

                                    <i class="fa-solid fa-tv"></i>

                                    <?php

                                    echo htmlspecialchars(
                                        $show['screen_name']
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- =================================================
                                 DATE + TIME
                            ================================================= -->

                            <td>

                                <span class="show-date">

                                    <?php

                                    if (!empty($show['show_date'])) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $show['show_date']
                                            )
                                        );

                                    } else {

                                        echo "N/A";

                                    }

                                    ?>

                                </span>


                                <span class="show-time">

                                    <i class="fa-regular fa-clock"></i>

                                    <?php

                                    if (!empty($show['show_time'])) {

                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $show['show_time']
                                            )
                                        );

                                    } else {

                                        echo "N/A";

                                    }

                                    ?>

                                </span>

                            </td>


                            <!-- =================================================
                                 PRICE
                            ================================================= -->

                            <td>

                                <span class="price">

                                    ₹<?php

                                    echo number_format(
                                        (float) $show['price'],
                                        2
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- =================================================
                                 ACTIONS
                            ================================================= -->

                            <td>

                                <div class="actions">


                                    <!-- EDIT -->

                                    <a
                                        href="edit_showtime.php?id=<?php echo (int) $show['id']; ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Showtime"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                    </a>


                                    <!-- DELETE -->

                                    <a
                                        href="delete_showtime.php?id=<?php echo (int) $show['id']; ?>"
                                        class="action-btn delete-btn"
                                        title="Delete Showtime"
                                        onclick="return confirm('Are you sure you want to delete this showtime?');"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php } ?>


                    </tbody>

                </table>


            </div>


        <?php } else { ?>


            <!-- =================================================
                 EMPTY
            ================================================= -->

            <div class="empty">


                <i class="fa-solid fa-calendar-xmark"></i>


                <h3>

                    <?php

                    if ($search !== "") {

                        echo "No Showtimes Found";

                    } else {

                        echo "No Showtimes Yet";

                    }

                    ?>

                </h3>


                <p>

                    <?php

                    if ($search !== "") {

                        echo "Try another movie or screen name.";

                    } else {

                        echo "Add your first movie showtime to get started.";

                    }

                    ?>

                </p>


                <?php if ($search === "") { ?>

                    <a href="add_showtime.php">

                        <i class="fa-solid fa-plus"></i>

                        Add Showtime

                    </a>

                <?php } ?>


            </div>


        <?php } ?>


    </div>


</main>


</body>

</html>