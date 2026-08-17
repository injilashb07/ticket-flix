
<?php

session_start();

require_once "config.php";

/* =========================================================
   USER LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   GET USER DETAILS
========================================================= */

$user_name = "User";

$stmt = $conn->prepare("
    SELECT first_name, last_name, email
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    $user_name = trim(
        ($user['first_name'] ?? '') . " " .
        ($user['last_name'] ?? '')
    );

    if ($user_name === "") {
        $user_name = "User";
    }
}

$stmt->close();


/* =========================================================
   GET USER BOOKINGS
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

        t.name AS theater_name,
        t.location AS theater_location

    FROM bookings b

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    WHERE b.user_id = ?

    ORDER BY b.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();


/* =========================================================
   GET SEATS FOR EACH BOOKING
========================================================= */

foreach ($bookings as &$booking) {

    $booking['seats'] = [];

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

    $seat_stmt->bind_param(
        "i",
        $booking['booking_id']
    );

    $seat_stmt->execute();

    $seat_result = $seat_stmt->get_result();

    while ($seat = $seat_result->fetch_assoc()) {
        $booking['seats'][] = $seat;
    }

    $seat_stmt->close();
}

unset($booking);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

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
            rgba(126, 63, 242, .25),
            transparent 35%
        ),
        radial-gradient(
            circle at bottom left,
            rgba(212, 175, 55, .10),
            transparent 35%
        ),
        #100b18;

    color: white;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    height: 75px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 6%;

    background: rgba(15, 10, 24, .96);

    border-bottom:
        1px solid
        rgba(212, 175, 55, .15);

    position: sticky;

    top: 0;

    z-index: 100;
}

.logo {

    font-size: 25px;

    font-weight: 800;

    color: white;

    text-decoration: none;
}

.logo i {

    color: #d4af37;

    margin-right: 5px;
}

.logo span {

    color: #d4af37;
}


/* NAV LINKS */

.nav-links {

    display: flex;

    align-items: center;

    gap: 25px;
}

.nav-links a {

    color: #aaa;

    text-decoration: none;

    font-size: 13px;

    transition: .3s;
}

.nav-links a:hover,
.nav-links a.active {

    color: #d4af37;
}


/* USER */

.user-area {

    display: flex;

    align-items: center;

    gap: 10px;
}

.user-icon {

    width: 38px;

    height: 38px;

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

.user-name {

    font-size: 12px;

    color: #ddd;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    width: 90%;

    max-width: 1250px;

    margin: auto;

    padding: 45px 0 60px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}

.page-header h1 {

    font-size: 30px;

    font-weight: 700;
}

.page-header h1 span {

    color: #d4af37;
}

.page-header p {

    color: #888;

    font-size: 12px;

    margin-top: 6px;
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

    transform: translateY(-50%);

    color: #777;
}

.search-box input {

    width: 100%;

    padding: 12px 15px 12px 38px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.1);

    border-radius: 10px;

    outline: none;

    color: white;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;
}

.search-box input:focus {

    border-color: #d4af37;
}

.search-box input::placeholder {

    color: #777;
}


/* =========================================================
   BOOKING CARD
========================================================= */

.booking-list {

    display: grid;

    grid-template-columns: 1fr;

    gap: 20px;
}

.booking-card {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 20px;

    overflow: hidden;

    transition: .3s;
}

.booking-card:hover {

    transform: translateY(-3px);

    border-color:
        rgba(212,175,55,.3);

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.25);
}


/* =========================================================
   CARD TOP
========================================================= */

.booking-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 15px 20px;

    background:
        rgba(212,175,55,.07);

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}

.reference {

    color: #d4af37;

    font-size: 12px;

    font-weight: 700;
}

.booking-id {

    color: #777;

    font-size: 10px;

    margin-left: 8px;
}


/* =========================================================
   STATUS
========================================================= */

.badges {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.badge {

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 600;

    text-transform: capitalize;
}

.confirmed {

    color: #72e672;

    background:
        rgba(76,175,80,.12);

    border:
        1px solid
        rgba(76,175,80,.2);
}

.pending {

    color: #ffd45c;

    background:
        rgba(255,193,7,.12);

    border:
        1px solid
        rgba(255,193,7,.2);
}

.cancelled {

    color: #ff7777;

    background:
        rgba(244,67,54,.12);

    border:
        1px solid
        rgba(244,67,54,.2);
}

.completed {

    color: #72e672;

    background:
        rgba(76,175,80,.12);
}

.failed {

    color: #ff7777;

    background:
        rgba(244,67,54,.12);
}


/* =========================================================
   CARD CONTENT
========================================================= */

.booking-content {

    display: flex;

    gap: 22px;

    padding: 22px;
}


/* =========================================================
   POSTER
========================================================= */

.poster {

    width: 125px;

    height: 175px;

    object-fit: cover;

    border-radius: 12px;

    flex-shrink: 0;

    background: #21182b;
}


/* =========================================================
   DETAILS
========================================================= */

.booking-details {

    flex: 1;
}

.movie-title {

    font-size: 20px;

    font-weight: 700;

    margin-bottom: 15px;
}

.detail-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(180px, 1fr));

    gap: 14px;

    margin-bottom: 18px;
}

.detail {

    display: flex;

    align-items: flex-start;

    gap: 10px;
}

.detail i {

    width: 25px;

    color: #d4af37;

    margin-top: 2px;
}

.detail small {

    display: block;

    color: #777;

    font-size: 9px;

    margin-bottom: 2px;

    text-transform: uppercase;

    letter-spacing: .5px;
}

.detail strong {

    display: block;

    color: #ddd;

    font-size: 11px;

    font-weight: 500;
}


/* =========================================================
   SEATS
========================================================= */

.seats-title {

    color: #777;

    font-size: 9px;

    text-transform: uppercase;

    margin-bottom: 7px;
}

.seats {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;
}

.seat {

    padding: 5px 9px;

    border-radius: 6px;

    color: #d4af37;

    background:
        rgba(212,175,55,.1);

    border:
        1px solid
        rgba(212,175,55,.15);

    font-size: 9px;

    font-weight: 600;
}


/* =========================================================
   PRICE
========================================================= */

.price-box {

    min-width: 150px;

    display: flex;

    flex-direction: column;

    align-items: flex-end;

    justify-content: center;

    padding-left: 20px;

    border-left:
        1px solid
        rgba(255,255,255,.07);
}

.price-label {

    color: #777;

    font-size: 10px;

    margin-bottom: 4px;
}

.price {

    color: #d4af37;

    font-size: 23px;

    font-weight: 700;
}


/* =========================================================
   CARD BOTTOM
========================================================= */

.booking-bottom {

    padding: 14px 22px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-top:
        1px solid
        rgba(255,255,255,.06);
}

.booked-date {

    color: #777;

    font-size: 10px;
}

.ticket-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 15px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f0cc5b
        );

    color: #171017;

    text-decoration: none;

    border-radius: 8px;

    font-size: 10px;

    font-weight: 700;

    transition: .3s;
}

.ticket-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 5px 18px
        rgba(212,175,55,.25);
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 80px 20px;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.07);

    border-radius: 20px;
}

.empty-icon {

    width: 75px;

    height: 75px;

    margin: auto auto 20px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(212,175,55,.1);

    color: #d4af37;

    font-size: 30px;
}

.empty h2 {

    font-size: 20px;

    margin-bottom: 7px;
}

.empty p {

    color: #777;

    font-size: 12px;

    margin-bottom: 20px;
}

.browse-btn {

    display: inline-block;

    padding: 11px 20px;

    border-radius: 9px;

    background: #d4af37;

    color: #171017;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   FOOTER
========================================================= */

footer {

    text-align: center;

    padding: 25px;

    color: #555;

    font-size: 10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 850px) {

    .navbar {

        padding: 0 4%;
    }

    .nav-links {

        display: none;
    }

    .main {

        width: 94%;

        padding-top: 30px;
    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 18px;
    }

    .search-box {

        width: 100%;
    }

    .booking-content {

        flex-wrap: wrap;
    }

    .price-box {

        width: 100%;

        border-left: none;

        border-top:
            1px solid
            rgba(255,255,255,.07);

        padding:
            15px 0 0;

        align-items: flex-start;
    }
}


@media(max-width:600px) {

    .user-name {

        display: none;
    }

    .booking-top {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;
    }

    .booking-content {

        padding: 16px;

        gap: 15px;
    }

    .poster {

        width: 90px;

        height: 130px;
    }

    .movie-title {

        font-size: 17px;
    }

    .detail-grid {

        grid-template-columns: 1fr;
    }

    .booking-bottom {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <a href="index.php" class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </a>


    <div class="nav-links">

        <a href="movies.php">
            Movies
        </a>

        <a href="theaters.php">
            Theaters
        </a>

        <a href="my_bookings.php" class="active">
            My Bookings
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>


    <div class="user-area">

        <div class="user-icon">

            <i class="fa-solid fa-user"></i>

        </div>

        <span class="user-name">

            <?php
            echo htmlspecialchars($user_name);
            ?>

        </span>

    </div>

</nav>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <div class="page-header">

        <div>

            <h1>
                My <span>Bookings</span>
            </h1>

            <p>
                View all your TicketFlix movie bookings.
            </p>

        </div>


        <?php if (!empty($bookings)) { ?>

            <div class="search-box">

                <i class="fa-solid fa-search"></i>

                <input
                    type="text"
                    id="bookingSearch"
                    placeholder="Search booking, movie..."
                    onkeyup="searchBookings()"
                >

            </div>

        <?php } ?>

    </div>


    <!-- =====================================================
         BOOKINGS
    ====================================================== -->

    <?php if (!empty($bookings)) { ?>

        <div class="booking-list" id="bookingList">


            <?php foreach ($bookings as $booking) { ?>

                <?php

                /* POSTER */

                $poster =
                    !empty($booking['poster_image'])
                    ? $booking['poster_image']
                    : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=400&q=80";


                /* STATUS */

                $booking_status =
                    strtolower(
                        trim(
                            $booking['booking_status'] ?? ''
                        )
                    );

                $payment_status =
                    strtolower(
                        trim(
                            $booking['payment_status'] ?? ''
                        )
                    );


                /* DATE */

                $show_date = "N/A";

                if (!empty($booking['show_date'])) {

                    $show_date = date(
                        "d M Y",
                        strtotime(
                            $booking['show_date']
                        )
                    );
                }


                /* TIME */

                $show_time = "N/A";

                if (!empty($booking['show_time'])) {

                    $show_time = date(
                        "h:i A",
                        strtotime(
                            $booking['show_time']
                        )
                    );
                }

                ?>


                <div
                    class="booking-card"
                    data-search="<?php

                        echo htmlspecialchars(
                            strtolower(
                                $booking['booking_reference']
                                . " "
                                . $booking['movie_name']
                                . " "
                                . $booking['theater_name']
                                . " "
                                . $booking['booking_status']
                            )
                        );

                    ?>"
                >


                    <!-- BOOKING TOP -->

                    <div class="booking-top">

                        <div>

                            <span class="reference">

                                <i class="fa-solid fa-ticket"></i>

                                <?php
                                echo htmlspecialchars(
                                    $booking['booking_reference']
                                );
                                ?>

                            </span>


                            <span class="booking-id">

                                #<?php
                                echo (int)
                                    $booking['booking_id'];
                                ?>

                            </span>

                        </div>


                        <div class="badges">

                            <span
                                class="badge <?php
                                echo htmlspecialchars(
                                    $booking_status
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $booking['booking_status']
                                );
                                ?>

                            </span>


                            <span
                                class="badge <?php
                                echo htmlspecialchars(
                                    $payment_status
                                );
                                ?>"
                            >

                                Payment:
                                <?php
                                echo htmlspecialchars(
                                    $booking['payment_status']
                                );
                                ?>

                            </span>

                        </div>

                    </div>


                    <!-- BOOKING CONTENT -->

                    <div class="booking-content">


                        <!-- POSTER -->

                        <img
                            src="<?php
                            echo htmlspecialchars($poster);
                            ?>"
                            class="poster"
                            alt="Movie Poster"
                        >


                        <!-- DETAILS -->

                        <div class="booking-details">


                            <div class="movie-title">

                                <?php
                                echo htmlspecialchars(
                                    $booking['movie_name']
                                );
                                ?>

                            </div>


                            <div class="detail-grid">


                                <!-- THEATER -->

                                <div class="detail">

                                    <i class="fa-solid fa-building"></i>

                                    <div>

                                        <small>
                                            Theater
                                        </small>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['theater_name']
                                            );
                                            ?>

                                        </strong>

                                    </div>

                                </div>


                                <!-- LOCATION -->

                                <div class="detail">

                                    <i class="fa-solid fa-location-dot"></i>

                                    <div>

                                        <small>
                                            Location
                                        </small>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['theater_location']
                                                ?? "N/A"
                                            );
                                            ?>

                                        </strong>

                                    </div>

                                </div>


                                <!-- DATE -->

                                <div class="detail">

                                    <i class="fa-solid fa-calendar"></i>

                                    <div>

                                        <small>
                                            Date
                                        </small>

                                        <strong>

                                            <?php
                                            echo $show_date;
                                            ?>

                                        </strong>

                                    </div>

                                </div>


                                <!-- TIME -->

                                <div class="detail">

                                    <i class="fa-solid fa-clock"></i>

                                    <div>

                                        <small>
                                            Time
                                        </small>

                                        <strong>

                                            <?php
                                            echo $show_time;
                                            ?>

                                        </strong>

                                    </div>

                                </div>


                                <!-- SCREEN -->

                                <div class="detail">

                                    <i class="fa-solid fa-tv"></i>

                                    <div>

                                        <small>
                                            Screen
                                        </small>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $booking['screen_name']
                                            );
                                            ?>

                                        </strong>

                                    </div>

                                </div>


                            </div>


                            <!-- SEATS -->

                            <div class="seats-title">

                                Selected Seats

                            </div>


                            <div class="seats">

                                <?php

                                if (
                                    !empty(
                                        $booking['seats']
                                    )
                                ) {

                                    foreach (
                                        $booking['seats']
                                        as $seat
                                    ) {

                                ?>

                                    <span class="seat">

                                        <?php
                                        echo htmlspecialchars(
                                            $seat['row_number']
                                        );
                                        ?>-<?php
                                        echo htmlspecialchars(
                                            $seat['seat_number']
                                        );
                                        ?>

                                    </span>

                                <?php

                                    }

                                } else {

                                ?>

                                    <span
                                        style="
                                            color:#666;
                                            font-size:10px;
                                        "
                                    >
                                        No seat information
                                    </span>

                                <?php } ?>

                            </div>


                        </div>


                        <!-- PRICE -->

                        <div class="price-box">

                            <span class="price-label">
                                Total Amount
                            </span>

                            <span class="price">

                                ₹<?php
                                echo number_format(
                                    (float)
                                    $booking['total_amount'],
                                    2
                                );
                                ?>

                            </span>

                        </div>


                    </div>


                    <!-- BOOKING BOTTOM -->

                    <div class="booking-bottom">

                        <span class="booked-date">

                            <i class="fa-solid fa-receipt"></i>

                            Booking Reference:
                            <?php
                            echo htmlspecialchars(
                                $booking['booking_reference']
                            );
                            ?>

                        </span>


                        <a
                            href="ticket.php?booking_id=<?php
                            echo (int)
                                $booking['booking_id'];
                            ?>"
                            class="ticket-btn"
                        >

                            <i class="fa-solid fa-ticket"></i>

                            View Ticket

                        </a>

                    </div>


                </div>


            <?php } ?>


        </div>


    <?php } else { ?>


        <!-- =================================================
             NO BOOKINGS
        ================================================== -->

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
                href="movies.php"
                class="browse-btn"
            >

                <i class="fa-solid fa-film"></i>

                Browse Movies

            </a>

        </div>

    <?php } ?>


</main>


<footer>

    © <?php echo date("Y"); ?> TicketFlix.
    All Rights Reserved.

</footer>


<script>

/* =========================================================
   SEARCH BOOKINGS
========================================================= */

function searchBookings() {

    const input =
        document
        .getElementById("bookingSearch")
        .value
        .toLowerCase()
        .trim();


    const cards =
        document.querySelectorAll(
            ".booking-card"
        );


    cards.forEach(function(card) {

        const text =
            card
            .getAttribute("data-search")
            .toLowerCase();


        if (text.includes(input)) {

            card.style.display = "";

        } else {

            card.style.display = "none";

        }

    });

}

</script>


</body>
</html>

