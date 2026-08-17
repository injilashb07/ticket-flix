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
   GET BOOKINGS FOR THIS THEATER
========================================================= */

$bookings = [];

$sql = "

    SELECT

        b.id AS booking_id,
        b.user_id,
        b.showtime_id,
        b.total_amount,
        b.booking_status,
        b.payment_status,
        b.booking_reference,

        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,
        m.poster_image,

        s.screen_name,

        CONCAT(
            COALESCE(u.first_name, ''),
            ' ',
            COALESCE(u.last_name, '')
        ) AS customer_name,

        u.email AS customer_email

    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    LEFT JOIN users u
        ON b.user_id = u.id

    WHERE s.theater_id = ?

    ORDER BY b.id DESC

";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $theater_id
);

$stmt->execute();

$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();


/* =========================================================
   BOOKING STATISTICS
========================================================= */

$total_bookings = count($bookings);

$confirmed_bookings = 0;

$pending_bookings = 0;

$total_revenue = 0;


foreach ($bookings as $booking) {

    $status = strtolower(
        trim($booking['booking_status'] ?? '')
    );


    if ($status === "confirmed") {

        $confirmed_bookings++;

        $total_revenue +=
            (float) $booking['total_amount'];

    }


    if ($status === "pending") {

        $pending_bookings++;

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

<title>Bookings | TicketFlix</title>


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
   STATISTICS
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

    transition: .3s;
}


.stat-card:hover {

    transform: translateY(-3px);

    border-color:
        rgba(212,175,55,.25);
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

    font-size: 23px;

    margin-bottom: 3px;
}


.stat-card p {

    color: #888;

    font-size: 11px;
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


/* =========================================================
   SEARCH
========================================================= */

.search-box {

    width: 280px;

    position: relative;
}


.search-box i {

    position: absolute;

    left: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #777;
}


.search-box input {

    width: 100%;

    padding:
        11px 12px 11px 38px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 9px;

    outline: none;

    color: white;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;
}


.search-box input:focus {

    border-color:
        rgba(212,175,55,.5);
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

    min-width: 1000px;
}


thead {

    background:
        rgba(212,175,55,.08);
}


th {

    text-align: left;

    padding: 14px 12px;

    color: #d4af37;

    font-size: 11px;

    font-weight: 600;

    white-space: nowrap;
}


td {

    padding: 14px 12px;

    border-bottom:
        1px solid rgba(255,255,255,.06);

    font-size: 11px;

    color: #ddd;

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
   MOVIE
========================================================= */

.movie-cell {

    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 180px;
}


.movie-poster {

    width: 38px;
    height: 52px;

    object-fit: cover;

    border-radius: 6px;
}


.movie-name {

    font-weight: 600;

    color: white;

    font-size: 11px;
}


/* =========================================================
   CUSTOMER
========================================================= */

.customer-name {

    color: white;

    font-weight: 500;

    display: block;
}


.customer-email {

    color: #777;

    font-size: 9px;

    display: block;

    margin-top: 2px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-block;

    padding:
        5px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 600;

    text-transform: capitalize;
}


.confirmed {

    background:
        rgba(76,175,80,.12);

    color: #70df70;

    border:
        1px solid rgba(76,175,80,.2);
}


.pending {

    background:
        rgba(255,193,7,.12);

    color: #ffd45c;

    border:
        1px solid rgba(255,193,7,.2);
}


.cancelled {

    background:
        rgba(244,67,54,.12);

    color: #ff7777;

    border:
        1px solid rgba(244,67,54,.2);
}


.completed {

    background:
        rgba(76,175,80,.12);

    color: #70df70;
}


.failed {

    background:
        rgba(244,67,54,.12);

    color: #ff7777;
}


/* =========================================================
   REFERENCE
========================================================= */

.reference {

    color: #d4af37;

    font-weight: 600;

    font-size: 10px;

    white-space: nowrap;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 60px 20px;

    color: #777;
}


.empty i {

    font-size: 45px;

    color: #d4af37;

    margin-bottom: 15px;
}


.empty h3 {

    color: #aaa;

    margin-bottom: 5px;

    font-size: 16px;
}


.empty p {

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats-grid {

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


    .top-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .search-box {

        width: 100%;
    }


    .panel-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

}


@media(max-width:550px) {

    .stats-grid {

        grid-template-columns: 1fr;
    }


    .main {

        padding: 15px;
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


    <a href="screens.php">

        <i class="fa-solid fa-tv"></i>

        <span>Screens</span>

    </a>


    <a href="bookings.php" class="active">

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

                Theater
                <span>Bookings</span>

            </h1>


            <p>

                View and manage bookings for your theater.

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

                <i class="fa-solid fa-circle-check"></i>

            </div>


            <h3>

                <?php
                echo $confirmed_bookings;
                ?>

            </h3>


            <p>
                Confirmed
            </p>

        </div>



        <div class="stat-card">

            <div class="stat-icon">

                <i class="fa-solid fa-clock"></i>

            </div>


            <h3>

                <?php
                echo $pending_bookings;
                ?>

            </h3>


            <p>
                Pending
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
                Confirmed Revenue
            </p>

        </div>

    </div>



    <!-- =====================================================
         BOOKINGS PANEL
    ====================================================== -->

    <div class="panel">


        <div class="panel-header">

            <h2>

                Recent
                <span>Bookings</span>

            </h2>


            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    id="bookingSearch"
                    placeholder="Search booking..."
                    onkeyup="searchBookings()"
                >

            </div>

        </div>



        <?php if (!empty($bookings)) { ?>


            <div class="table-wrapper">


                <table id="bookingTable">


                    <thead>

                        <tr>

                            <th>
                                Booking
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Movie
                            </th>

                            <th>
                                Show
                            </th>

                            <th>
                                Seats
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Booking Status
                            </th>

                            <th>
                                Payment
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($bookings as $booking) { ?>


                        <?php

                        /* =================================
                           GET BOOKED SEATS
                        ================================= */

                        $booking_seats = [];


                        $seat_stmt = $conn->prepare("

                            SELECT

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

                        ");


                        if ($seat_stmt) {

                            $seat_stmt->bind_param(
                                "i",
                                $booking['booking_id']
                            );

                            $seat_stmt->execute();

                            $seat_result =
                                $seat_stmt->get_result();


                            while (
                                $seat_row =
                                $seat_result->fetch_assoc()
                            ) {

                                $booking_seats[] =
                                    $seat_row;

                            }

                            $seat_stmt->close();

                        }


                        /* =================================
                           POSTER
                        ================================= */

                        $poster =
                            !empty(
                                $booking['poster_image']
                            )
                            ? $booking['poster_image']
                            : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=200&q=80";


                        /* =================================
                           STATUS
                        ================================= */

                        $booking_status =
                            strtolower(
                                trim(
                                    $booking['booking_status']
                                    ?? ''
                                )
                            );


                        $payment_status =
                            strtolower(
                                trim(
                                    $booking['payment_status']
                                    ?? ''
                                )
                            );

                        ?>


                        <tr>


                            <!-- BOOKING -->

                            <td>

                                <span class="reference">

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            'booking_reference'
                                        ]
                                    );
                                    ?>

                                </span>


                                <br>


                                <small
                                    style="color:#666;"
                                >

                                    #<?php
                                    echo (int)
                                        $booking[
                                            'booking_id'
                                        ];
                                    ?>

                                </small>

                            </td>



                            <!-- CUSTOMER -->

                            <td>

                                <span
                                    class="customer-name"
                                >

                                    <?php

                                    $customer_name =
                                        trim(
                                            $booking[
                                                'customer_name'
                                            ] ?? ''
                                        );


                                    if (
                                        $customer_name === ''
                                    ) {

                                        $customer_name =
                                            "Customer";

                                    }


                                    echo htmlspecialchars(
                                        $customer_name
                                    );

                                    ?>

                                </span>


                                <span
                                    class="customer-email"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            'customer_email'
                                        ] ?? ''
                                    );
                                    ?>

                                </span>

                            </td>



                            <!-- MOVIE -->

                            <td>

                                <div class="movie-cell">


                                    <img
                                        src="<?php
                                        echo htmlspecialchars(
                                            $poster
                                        );
                                        ?>"
                                        class="movie-poster"
                                        alt="Movie"
                                    >


                                    <span
                                        class="movie-name"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $booking[
                                                'movie_name'
                                            ]
                                        );
                                        ?>

                                    </span>


                                </div>

                            </td>



                            <!-- SHOW -->

                            <td>

                                <strong>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $booking[
                                                'show_date'
                                            ]
                                        )
                                    );
                                    ?>

                                </strong>


                                <br>


                                <small
                                    style="color:#888;"
                                >

                                    <?php
                                    echo date(
                                        "h:i A",
                                        strtotime(
                                            $booking[
                                                'show_time'
                                            ]
                                        )
                                    );
                                    ?>

                                </small>


                                <br>


                                <small
                                    style="color:#777;"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            'screen_name'
                                        ] ?? 'Screen'
                                    );
                                    ?>

                                </small>

                            </td>



                            <!-- SEATS -->

                            <td>

                                <?php if (
                                    !empty($booking_seats)
                                ) { ?>


                                    <?php
                                    foreach (
                                        $booking_seats
                                        as $seat
                                    ) {
                                    ?>


                                        <span
                                            style="
                                                display:inline-block;
                                                padding:4px 7px;
                                                margin:2px;
                                                border-radius:5px;
                                                background:rgba(212,175,55,.1);
                                                color:#d4af37;
                                                font-size:9px;
                                            "
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $seat[
                                                    'row_number'
                                                ]
                                            );
                                            ?>-<?php
                                            echo htmlspecialchars(
                                                $seat[
                                                    'seat_number'
                                                ]
                                            );
                                            ?>

                                        </span>


                                    <?php } ?>


                                <?php } else { ?>

                                    <span
                                        style="color:#666;"
                                    >
                                        No seats
                                    </span>

                                <?php } ?>

                            </td>



                            <!-- AMOUNT -->

                            <td>

                                <strong
                                    style="
                                        color:#d4af37;
                                        white-space:nowrap;
                                    "
                                >

                                    ₹<?php
                                    echo number_format(
                                        (float)
                                        $booking[
                                            'total_amount'
                                        ],
                                        2
                                    );
                                    ?>

                                </strong>

                            </td>



                            <!-- BOOKING STATUS -->

                            <td>

                                <span
                                    class="badge <?php
                                    echo htmlspecialchars(
                                        $booking_status
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            'booking_status'
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>



                            <!-- PAYMENT -->

                            <td>

                                <span
                                    class="badge <?php
                                    echo htmlspecialchars(
                                        $payment_status
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $booking[
                                            'payment_status'
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>


                        </tr>


                    <?php } ?>


                    </tbody>


                </table>


            </div>


        <?php } else { ?>


            <div class="empty">

                <i class="fa-solid fa-ticket"></i>


                <h3>
                    No Bookings Yet
                </h3>


                <p>

                    There are no bookings
                    for this theater yet.

                </p>

            </div>


        <?php } ?>


    </div>


</main>



<script>

/* =========================================================
   SEARCH BOOKINGS
========================================================= */

function searchBookings() {

    const input =
        document
        .getElementById("bookingSearch")
        .value
        .toLowerCase();


    const rows =
        document.querySelectorAll(
            "#bookingTable tbody tr"
        );


    rows.forEach(function(row) {

        const text =
            row.textContent.toLowerCase();


        if (text.includes(input)) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    });

}

</script>


</body>

</html>