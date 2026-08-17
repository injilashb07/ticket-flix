<?php

session_start();
require_once "config.php";

/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   GET SHOWTIME
========================================================= */

$showtime_id = isset($_GET['showtime_id'])
    ? (int) $_GET['showtime_id']
    : 0;

if ($showtime_id <= 0) {
    die("Invalid showtime.");
}


/* =========================================================
   GET SHOWTIME DETAILS
========================================================= */

$sql = "
    SELECT
        st.id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,
        m.poster_image,

        s.id AS screen_id,
        s.screen_name,

        t.name AS theater_name

    FROM showtimes st

    INNER JOIN movies m
        ON st.movie_id = m.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    WHERE st.id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Showtime query error: " . $conn->error);
}

$stmt->bind_param("i", $showtime_id);
$stmt->execute();

$result = $stmt->get_result();
$showtime = $result->fetch_assoc();

$stmt->close();

if (!$showtime) {
    die("Showtime not found.");
}


/* =========================================================
   GET SEATS
   IMPORTANT:
   Database column = seat_row
========================================================= */

$seats = [];

$seat_sql = "
    SELECT
        id,
        screen_id,
        seat_row,
        seat_number,
        seat_type,
        is_active

    FROM seats

    WHERE screen_id = ?
      AND is_active = 1

    ORDER BY
        seat_row ASC,
        seat_number ASC
";

$stmt = $conn->prepare($seat_sql);

if (!$stmt) {
    die(
        "Seat query error: " .
        $conn->error .
        "<br><br>SQL:<br>" .
        htmlspecialchars($seat_sql)
    );
}

$stmt->bind_param(
    "i",
    $showtime['screen_id']
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $seats[] = $row;
}

$stmt->close();


/* =========================================================
   GET BOOKED SEATS
========================================================= */

$booked_seats = [];

$booked_sql = "
    SELECT bs.seat_id

    FROM booking_seats bs

    INNER JOIN bookings b
        ON bs.booking_id = b.id

    WHERE b.showtime_id = ?

      AND b.booking_status IN (
          'confirmed',
          'pending'
      )
";

$stmt = $conn->prepare($booked_sql);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $showtime_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $booked_seats[] =
            (int) $row['seat_id'];
    }

    $stmt->close();
}


/* =========================================================
   GROUP SEATS BY ROW
========================================================= */

$seat_rows = [];

foreach ($seats as $seat) {

    $row_name = strtoupper(
        trim(
            (string) $seat['seat_row']
        )
    );

    if ($row_name === '') {
        continue;
    }

    $seat_rows[$row_name][] = $seat;
}


/* =========================================================
   SEAT PRICE
========================================================= */

function getSeatPrice($seat_type, $base_price)
{
    $seat_type = strtolower(
        trim(
            (string) $seat_type
        )
    );

    $base_price = (float) $base_price;

    if ($seat_type === 'vip') {
        return $base_price * 2;
    }

    if ($seat_type === 'recliner') {
        return $base_price * 1.5;
    }

    return $base_price;
}


/* =========================================================
   POSTER
========================================================= */

$poster = !empty($showtime['poster_image'])
    ? $showtime['poster_image']
    : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=400&q=80";


/* =========================================================
   DATE / TIME
========================================================= */

$formatted_date = date(
    "d M Y",
    strtotime($showtime['show_date'])
);

$formatted_time = date(
    "h:i A",
    strtotime($showtime['show_time'])
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Select Seats | TicketFlix</title>


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

    color: #fff;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(126, 63, 242, .22),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(212, 175, 55, .12),
            transparent 30%
        ),
        #0b0711;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 75px;

    padding: 0 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid
        rgba(212,175,55,.15);

    background:
        rgba(11,7,17,.94);

    backdrop-filter: blur(15px);

    position: sticky;

    top: 0;

    z-index: 100;

}


.logo {

    color: #fff;

    text-decoration: none;

    font-size: 25px;

    font-weight: 800;

}


.logo i {

    color: #d4af37;

    margin-right: 6px;

}


.logo span {

    color: #d4af37;

}


.back-btn {

    text-decoration: none;

    color: #ddd;

    padding: 9px 16px;

    border:
        1px solid
        rgba(255,255,255,.1);

    border-radius: 10px;

    font-size: 12px;

    transition: .3s;

}


.back-btn:hover {

    color: #d4af37;

    border-color: #d4af37;

    background:
        rgba(212,175,55,.08);

}


/* =========================================================
   PAGE
========================================================= */

.page {

    max-width: 1450px;

    margin: auto;

    padding:
        32px 4% 60px;

}


/* =========================================================
   MOVIE INFO
========================================================= */

.movie-info {

    display: flex;

    align-items: center;

    gap: 18px;

    padding: 18px;

    margin-bottom: 32px;

    border-radius: 18px;

    background:
        linear-gradient(
            100deg,
            rgba(126,63,242,.12),
            rgba(255,255,255,.035)
        );

    border:
        1px solid
        rgba(212,175,55,.12);

}


.movie-poster {

    width: 72px;

    height: 95px;

    object-fit: cover;

    border-radius: 10px;

    border:
        1px solid
        rgba(212,175,55,.4);

}


.movie-details h1 {

    font-size: 23px;

    margin-bottom: 8px;

}


.movie-details p {

    color: #999;

    font-size: 12px;

    margin: 4px 0;

}


.movie-details i {

    color: #d4af37;

    margin-right: 6px;

}


/* =========================================================
   LAYOUT
========================================================= */

.booking-layout {

    display: grid;

    grid-template-columns: 240px 1fr;

    gap: 35px;

    align-items: start;

}


/* =========================================================
   SIDE PANEL
========================================================= */

.side-panel {

    background:
        linear-gradient(
            145deg,
            rgba(126,63,242,.14),
            rgba(212,175,55,.045)
        );

    border:
        1px solid
        rgba(212,175,55,.18);

    border-radius: 20px;

    padding: 20px;

    position: sticky;

    top: 100px;

}


.side-panel h3 {

    font-size: 15px;

    margin-bottom: 18px;

}


.timing {

    padding: 13px;

    margin-bottom: 12px;

    border-radius: 11px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.06);

}


.timing p {

    color: #777;

    font-size: 9px;

    margin-bottom: 4px;

}


.timing strong {

    color: #d4af37;

    font-size: 14px;

}


.legend {

    margin-top: 22px;

}


.legend-title {

    color: #aaa;

    font-size: 11px;

    margin-bottom: 13px;

}


.legend-item {

    display: flex;

    align-items: center;

    gap: 9px;

    color: #aaa;

    font-size: 10px;

    margin: 10px 0;

}


.legend-seat {

    width: 18px;

    height: 18px;

    border-radius: 5px;

    border: 1px solid;

}


.legend-regular {

    background:
        rgba(126,63,242,.12);

    border-color:
        #8157c5;

}


.legend-vip {

    background:
        rgba(212,175,55,.12);

    border-color:
        #d4af37;

}


.legend-recliner {

    background:
        rgba(255,255,255,.08);

    border-color:
        #aaa;

}


.legend-selected {

    background:
        #d4af37;

    border-color:
        #ffe58a;

}


.legend-booked {

    background:
        #302c34;

    border-color:
        #444;

}


/* =========================================================
   SEAT AREA
========================================================= */

.seat-area {

    min-width: 0;

    text-align: center;

}


.seat-area h2 {

    font-size: 23px;

    margin-bottom: 3px;

}


.seat-subtitle {

    color: #777;

    font-size: 11px;

    margin-bottom: 22px;

}


/* =========================================================
   SCREEN
========================================================= */

.screen-container {

    width: 76%;

    margin: 0 auto 48px;

}


.screen {

    height: 38px;

    border-top:
        6px solid
        #d4af37;

    border-radius: 50%;

    box-shadow:
        0 -5px 30px
        rgba(212,175,55,.28);

    position: relative;

}


.screen::after {

    content: "SCREEN";

    position: absolute;

    top: 14px;

    left: 50%;

    transform:
        translateX(-50%);

    color: #777;

    font-size: 8px;

    letter-spacing: 5px;

}


/* =========================================================
   SEAT MAP
========================================================= */

.seat-map {

    display: flex;

    flex-direction: column;

    align-items: center;

    gap: 12px;

    overflow-x: auto;

    padding:
        5px 10px 30px;

}


.seat-row {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-width: max-content;

}


.row-label {

    width: 22px;

    margin-right: 5px;

    color: #d4af37;

    font-size: 10px;

    font-weight: 700;

}


.aisle {

    width: 35px;

    height: 1px;

}


/* =========================================================
   SEAT
========================================================= */

.seat {

    width: 36px;

    height: 32px;

    border-radius:
        7px 7px 9px 9px;

    position: relative;

    display: flex;

    align-items: center;

    justify-content: center;

    font-family: 'Poppins';

    font-size: 9px;

    font-weight: 600;

    cursor: pointer;

    transition:
        .2s;

}


.seat::before {

    content: "";

    position: absolute;

    left: 4px;

    right: 4px;

    bottom: -4px;

    height: 4px;

    border-radius:
        0 0 5px 5px;

    background: inherit;

    opacity: .65;

}


/* REGULAR */

.seat.regular {

    color: #cdbbff;

    background:
        rgba(126,63,242,.09);

    border:
        1px solid
        #8157c5;

}


/* VIP */

.seat.vip {

    color: #f4da7b;

    background:
        rgba(212,175,55,.08);

    border:
        1px solid
        #d4af37;

}


/* RECLINER */

.seat.recliner {

    color: #eee;

    background:
        rgba(255,255,255,.07);

    border:
        1px solid
        #aaa;

}


/* HOVER */

.seat:not(.booked):hover {

    transform:
        translateY(-3px)
        scale(1.06);

    box-shadow:
        0 5px 18px
        rgba(212,175,55,.25);

}


/* SELECTED */

.seat.selected {

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f4d76b
        ) !important;

    border-color:
        #ffe58a !important;

    color:
        #1a1022 !important;

    box-shadow:
        0 0 17px
        rgba(212,175,55,.55);

    transform:
        translateY(-3px);

}


/* BOOKED */

.seat.booked {

    background:
        #2b2730 !important;

    border-color:
        #403b44 !important;

    color:
        #666 !important;

    opacity:
        .55;

    cursor:
        not-allowed;

}


/* =========================================================
   NO SEATS
========================================================= */

.no-seats {

    padding: 55px 20px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.06);

    color: #888;

}


.no-seats i {

    display: block;

    color: #d4af37;

    font-size: 42px;

    margin-bottom: 15px;

}


.no-seats h3 {

    margin-bottom: 5px;

}


/* =========================================================
   BOOKING BAR
========================================================= */

.booking-bar {

    margin-top: 18px;

    padding: 20px 24px;

    display: grid;

    grid-template-columns:
        1fr auto auto;

    align-items: center;

    gap: 25px;

    text-align: left;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        rgba(212,175,55,.15);

    border-radius: 18px;

    box-shadow:
        0 10px 40px
        rgba(0,0,0,.25);

}


.selected-info small,
.total-box small {

    display: block;

    color: #777;

    font-size: 9px;

}


.selected-seats-text {

    color: #d4af37;

    font-size: 12px;

    font-weight: 600;

    margin-top: 4px;

}


.total-box {

    text-align: right;

}


.total-price {

    color: #d4af37;

    font-size: 23px;

    font-weight: 800;

}


.continue-btn {

    border: none;

    cursor: pointer;

    padding: 13px 22px;

    border-radius: 10px;

    font-family: 'Poppins';

    font-weight: 700;

    color: #1a1022;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f5db73
        );

    transition: .3s;

}


.continue-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 25px
        rgba(212,175,55,.3);

}


.continue-btn:disabled {

    opacity: .4;

    cursor: not-allowed;

    transform: none;

    box-shadow: none;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 1000px) {

    .booking-layout {

        grid-template-columns: 1fr;

    }

    .side-panel {

        position: static;

        display: grid;

        grid-template-columns: 1fr 1fr;

        gap: 15px;

    }

    .legend {

        margin-top: 0;

    }

}


@media(max-width: 700px) {

    .page {

        padding:
            20px 10px 35px;

    }

    .movie-info {

        padding: 12px;

    }

    .movie-poster {

        width: 55px;

        height: 75px;

    }

    .movie-details h1 {

        font-size: 17px;

    }

    .side-panel {

        grid-template-columns: 1fr;

    }

    .screen-container {

        width: 95%;

    }

    .seat {

        width: 30px;

        height: 28px;

        font-size: 7px;

    }

    .seat-row {

        gap: 5px;

    }

    .aisle {

        width: 15px;

    }

    .booking-bar {

        grid-template-columns: 1fr;

        text-align: center;

    }

    .total-box {

        text-align: center;

    }

    .continue-btn {

        width: 100%;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">

    <a href="index.php" class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </a>


    <a
        href="showtimes.php"
        class="back-btn"
    >

        <i class="fa-solid fa-arrow-left"></i>

        Back

    </a>

</header>


<div class="page">


<!-- =========================================================
     MOVIE INFO
========================================================= -->

<div class="movie-info">

    <img
        src="<?php echo htmlspecialchars($poster); ?>"
        class="movie-poster"
        alt="Movie Poster"
    >


    <div class="movie-details">

        <h1>
            <?php
            echo htmlspecialchars(
                $showtime['movie_name']
            );
            ?>
        </h1>


        <p>

            <i class="fa-solid fa-building"></i>

            <?php
            echo htmlspecialchars(
                $showtime['theater_name']
            );
            ?>

        </p>


        <p>

            <i class="fa-solid fa-calendar"></i>

            <?php
            echo htmlspecialchars(
                $formatted_date
            );
            ?>

            &nbsp;&nbsp;

            <i class="fa-solid fa-clock"></i>

            <?php
            echo htmlspecialchars(
                $formatted_time
            );
            ?>

        </p>

    </div>

</div>


<!-- =========================================================
     BOOKING LAYOUT
========================================================= -->

<div class="booking-layout">


<!-- =========================================================
     SIDE PANEL
========================================================= -->

<aside class="side-panel">

    <div>

        <h3>

            <i
                class="fa-solid fa-clock"
                style="color:#d4af37;"
            ></i>

            Show Details

        </h3>


        <div class="timing">

            <p>DATE</p>

            <strong>
                <?php
                echo htmlspecialchars(
                    $formatted_date
                );
                ?>
            </strong>

        </div>


        <div class="timing">

            <p>SHOW TIME</p>

            <strong>
                <?php
                echo htmlspecialchars(
                    $formatted_time
                );
                ?>
            </strong>

        </div>


        <div class="timing">

            <p>SCREEN</p>

            <strong>
                <?php
                echo htmlspecialchars(
                    $showtime['screen_name']
                );
                ?>
            </strong>

        </div>

    </div>


    <!-- LEGEND -->

    <div class="legend">

        <div class="legend-title">
            SEAT TYPES
        </div>


        <div class="legend-item">

            <span class="legend-seat legend-regular"></span>

            Regular

        </div>


        <div class="legend-item">

            <span class="legend-seat legend-vip"></span>

            VIP

        </div>


        <div class="legend-item">

            <span class="legend-seat legend-recliner"></span>

            Recliner

        </div>


        <div class="legend-item">

            <span class="legend-seat legend-selected"></span>

            Selected

        </div>


        <div class="legend-item">

            <span class="legend-seat legend-booked"></span>

            Booked

        </div>

    </div>

</aside>


<!-- =========================================================
     SEAT AREA
========================================================= -->

<section class="seat-area">


    <h2>
        Select Your Seats
    </h2>


    <p class="seat-subtitle">
        Choose your preferred seats
    </p>


    <!-- SCREEN -->

    <div class="screen-container">

        <div class="screen"></div>

    </div>


    <!-- =====================================================
         SEAT MAP
    ===================================================== -->

    <?php if (!empty($seat_rows)): ?>

        <div class="seat-map">

        <?php

        /*
         * Reference design:
         *
         * A B
         *
         * C D     E F
         *
         * G H     I J
         */

        $left_rows = [
            'A',
            'B',
            'C',
            'D',
            'G',
            'H'
        ];

        $right_rows = [
            'E',
            'F',
            'I',
            'J'
        ];


        foreach ($seat_rows as $row_name => $row_seats):

        ?>

            <div class="seat-row">

                <span class="row-label">
                    <?php echo htmlspecialchars($row_name); ?>
                </span>


                <?php

                foreach ($row_seats as $index => $seat):

                    /*
                     * Center aisle for rows
                     * having 9 seats.
                     */

                    if ($index == 5 && in_array(
                        $row_name,
                        ['C','D','E','F','G','H','I','J']
                    )):

                    ?>

                        <span class="aisle"></span>

                    <?php

                    endif;


                    $seat_id =
                        (int) $seat['id'];


                    $seat_type =
                        strtolower(
                            trim(
                                (string)
                                $seat['seat_type']
                            )
                        );


                    if ($seat_type === 'vip') {

                        $type_class = 'vip';

                    } elseif ($seat_type === 'recliner') {

                        $type_class = 'recliner';

                    } else {

                        $type_class = 'regular';

                    }


                    $is_booked =
                        in_array(
                            $seat_id,
                            $booked_seats,
                            true
                        );


                    $seat_price =
                        getSeatPrice(
                            $seat_type,
                            $showtime['price']
                        );

                ?>

                    <button
                        type="button"

                        class="
                            seat
                            <?php echo $type_class; ?>
                            <?php
                            echo $is_booked
                                ? 'booked'
                                : '';
                            ?>
                        "

                        data-id="<?php
                            echo $seat_id;
                        ?>"

                        data-seat="<?php
                            echo htmlspecialchars(
                                $row_name .
                                $seat['seat_number']
                            );
                        ?>"

                        data-type="<?php
                            echo htmlspecialchars(
                                $seat_type
                            );
                        ?>"

                        data-price="<?php
                            echo number_format(
                                $seat_price,
                                2,
                                '.',
                                ''
                            );
                        ?>"

                        <?php
                        echo $is_booked
                            ? 'disabled'
                            : '';
                        ?>
                    >

                        <?php
                        echo htmlspecialchars(
                            $row_name .
                            $seat['seat_number']
                        );
                        ?>

                    </button>

                <?php endforeach; ?>

            </div>

        <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="no-seats">

            <i class="fa-solid fa-chair"></i>

            <h3>
                No Seats Available
            </h3>

            <p>
                No seats have been added
                for this screen yet.
            </p>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         BOOKING FORM
    ===================================================== -->

    <form
        method="POST"
        action="booking.php"
        id="bookingForm"
    >

        <input
            type="hidden"
            name="showtime_id"
            value="<?php echo $showtime_id; ?>"
        >


        <div id="seatInputs"></div>


        <input
            type="hidden"
            name="total_amount"
            id="totalAmountInput"
            value="0.00"
        >


        <!-- BOOKING BAR -->

        <div class="booking-bar">

            <div class="selected-info">

                <small>
                    SELECTED SEATS
                </small>

                <div
                    class="selected-seats-text"
                    id="selectedSeatsText"
                >
                    No seats selected
                </div>

            </div>


            <div class="total-box">

                <small>
                    TOTAL AMOUNT
                </small>

                <div class="total-price">

                    ₹<span id="totalAmount">
                        0.00
                    </span>

                </div>

            </div>


            <button
                type="submit"
                class="continue-btn"
                id="continueBtn"
                disabled
            >

                Continue to Booking

                <i class="fa-solid fa-arrow-right"></i>

            </button>

        </div>

    </form>

</section>

</div>

</div>


<script>

/* =========================================================
   ELEMENTS
========================================================= */

const seats =
    document.querySelectorAll(
        ".seat:not(.booked)"
    );


const selectedSeatsText =
    document.getElementById(
        "selectedSeatsText"
    );


const totalAmount =
    document.getElementById(
        "totalAmount"
    );


const seatInputs =
    document.getElementById(
        "seatInputs"
    );


const totalAmountInput =
    document.getElementById(
        "totalAmountInput"
    );


const continueBtn =
    document.getElementById(
        "continueBtn"
    );


const bookingForm =
    document.getElementById(
        "bookingForm"
    );


/* =========================================================
   VARIABLES
========================================================= */

let selectedSeats = [];

let total = 0;


/* =========================================================
   SEAT CLICK
========================================================= */

seats.forEach(function(seat) {

    seat.addEventListener(
        "click",
        function() {

            const id =
                String(
                    this.dataset.id
                );


            const seatName =
                this.dataset.seat;


            const price =
                parseFloat(
                    this.dataset.price
                ) || 0;


            const existing =
                selectedSeats.find(
                    function(item) {

                        return item.id === id;

                    }
                );


            /* REMOVE */

            if (existing) {

                selectedSeats =
                    selectedSeats.filter(
                        function(item) {

                            return item.id !== id;

                        }
                    );


                total -= existing.price;


                total =
                    Math.max(
                        0,
                        Math.round(
                            total * 100
                        ) / 100
                    );


                this.classList.remove(
                    "selected"
                );

            }


            /* ADD */

            else {

                selectedSeats.push({

                    id: id,

                    name: seatName,

                    price: price

                });


                total += price;


                total =
                    Math.round(
                        total * 100
                    ) / 100;


                this.classList.add(
                    "selected"
                );

            }


            updateBooking();

        }
    );

});


/* =========================================================
   UPDATE BOOKING
========================================================= */

function updateBooking() {

    if (
        selectedSeats.length === 0
    ) {

        selectedSeatsText.textContent =
            "No seats selected";

    } else {

        selectedSeatsText.textContent =
            selectedSeats
                .map(function(seat) {

                    return seat.name;

                })
                .join(" • ");

    }


    totalAmount.textContent =
        total.toFixed(2);


    totalAmountInput.value =
        total.toFixed(2);


    seatInputs.innerHTML = "";


    selectedSeats.forEach(
        function(seat) {

            const input =
                document.createElement(
                    "input"
                );


            input.type =
                "hidden";


            input.name =
                "seat_ids[]";


            input.value =
                seat.id;


            seatInputs.appendChild(
                input
            );

        }
    );


    continueBtn.disabled =
        selectedSeats.length === 0;

}


/* =========================================================
   FORM VALIDATION
========================================================= */

bookingForm.addEventListener(
    "submit",
    function(event) {

        if (
            selectedSeats.length === 0
        ) {

            event.preventDefault();

            alert(
                "Please select at least one seat."
            );

            return;

        }


        if (
            !Number.isFinite(total) ||
            total <= 0
        ) {

            event.preventDefault();

            alert(
                "Invalid booking amount."
            );

            return;

        }


        totalAmountInput.value =
            total.toFixed(2);

    }
);


/* =========================================================
   INITIAL
========================================================= */

updateBooking();

</script>


</body>

</html>