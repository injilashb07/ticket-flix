
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
   SHOWTIME DETAILS
   NOTE:
   t.location REMOVED because theaters table does not
   contain a location column.
========================================================= */

$stmt = $conn->prepare("
    SELECT
        st.id,
        st.show_date,
        st.show_time,
        st.price,

        m.id AS movie_id,
        m.name AS movie_name,
        m.poster_image,

        s.id AS screen_id,
        s.screen_name,

        t.id AS theater_id,
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
");

$stmt->bind_param("i", $showtime_id);
$stmt->execute();

$result = $stmt->get_result();
$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {
    die("Showtime not found.");
}


/* =========================================================
   GET ALL SEATS
========================================================= */

$seats = [];

$stmt = $conn->prepare("
    SELECT
        `id`,
        `row_number`,
        `seat_number`,
        `seat_type`,
        `is_active`
    FROM `seats`
    WHERE `screen_id` = ?
      AND `is_active` = 1
    ORDER BY
        `row_number` ASC,
        `seat_number` ASC
");

if (!$stmt) {
    die("Seat query error: " . $conn->error);
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

$stmt = $conn->prepare("
    SELECT
        bs.seat_id

    FROM booking_seats bs

    INNER JOIN bookings b
        ON bs.booking_id = b.id

    WHERE b.showtime_id = ?

    AND b.booking_status IN ('confirmed', 'pending')
");

$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $booked_seats[] = (int) $row['seat_id'];
}

$stmt->close();


/* =========================================================
   GROUP SEATS BY ROW
========================================================= */

$seat_rows = [];

foreach ($seats as $seat) {

    $row_name = strtoupper(
        trim((string) $seat['row_number'])
    );

    $seat_rows[$row_name][] = $seat;
}


/* =========================================================
   POSTER
========================================================= */

$poster = !empty($showtime['poster_image'])
    ? $showtime['poster_image']
    : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=400&q=80";


/* =========================================================
   DATE & TIME
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

    color: white;

    background:
        radial-gradient(
            circle at 15% 20%,
            rgba(126,63,242,.20),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 80%,
            rgba(212,175,55,.12),
            transparent 30%
        ),
        #0c0712;
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
        rgba(12,7,18,.92);

    backdrop-filter: blur(12px);

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


.back-btn {

    text-decoration: none;

    color: #ccc;

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
   MAIN
========================================================= */

.page {

    max-width: 1450px;

    margin: auto;

    padding: 35px 4% 50px;
}


/* =========================================================
   MOVIE INFO
========================================================= */

.movie-info {

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 35px;

    padding: 18px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid
        rgba(255,255,255,.07);
}


.movie-poster {

    width: 70px;

    height: 95px;

    object-fit: cover;

    border-radius: 10px;

    border:
        1px solid
        rgba(212,175,55,.35);
}


.movie-details h1 {

    font-size: 22px;

    margin-bottom: 7px;
}


.movie-details p {

    color: #999;

    font-size: 12px;

    margin: 3px 0;
}


.movie-details i {

    color: #d4af37;

    margin-right: 6px;
}


/* =========================================================
   CONTENT LAYOUT
========================================================= */

.booking-layout {

    display: grid;

    grid-template-columns: 250px 1fr;

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
            rgba(126,63,242,.12),
            rgba(212,175,55,.05)
        );

    border:
        1px solid
        rgba(212,175,55,.18);

    border-radius: 20px;

    padding: 22px;

    position: sticky;

    top: 100px;
}


.side-panel h3 {

    font-size: 15px;

    margin-bottom: 18px;

    color: #fff;
}


.timing {

    padding: 13px;

    border-radius: 10px;

    background:
        rgba(255,255,255,.04);

    margin-bottom: 18px;

    border:
        1px solid
        rgba(255,255,255,.06);
}


.timing p {

    color: #999;

    font-size: 10px;

    margin-bottom: 4px;
}


.timing strong {

    color: #d4af37;

    font-size: 15px;
}


.legend {

    margin-top: 20px;
}


.legend-title {

    color: #aaa;

    font-size: 11px;

    margin-bottom: 12px;
}


.legend-item {

    display: flex;

    align-items: center;

    gap: 9px;

    font-size: 10px;

    color: #aaa;

    margin: 10px 0;
}


.legend-seat {

    width: 19px;

    height: 19px;

    border-radius: 5px;

    border: 1px solid;
}


.legend-regular {

    border-color: #8f68d8;

    background:
        rgba(126,63,242,.12);
}


.legend-vip {

    border-color: #d4af37;

    background:
        rgba(212,175,55,.12);
}


.legend-selected {

    background: #d4af37;

    border-color: #d4af37;
}


.legend-booked {

    background: #333;

    border-color: #444;
}


/* =========================================================
   SEAT AREA
========================================================= */

.seat-area {

    min-width: 0;

    text-align: center;
}


.seat-area h2 {

    font-size: 22px;

    margin-bottom: 4px;
}


.seat-subtitle {

    color: #777;

    font-size: 11px;

    margin-bottom: 20px;
}


/* =========================================================
   SCREEN
========================================================= */

.screen-container {

    width: 80%;

    margin: 0 auto 45px;

    perspective: 500px;
}


.screen {

    height: 35px;

    border-top:
        6px solid
        #d4af37;

    border-radius: 50%;

    box-shadow:
        0 -5px 30px
        rgba(212,175,55,.25);

    transform:
        rotateX(-15deg);

    position: relative;
}


.screen::after {

    content: "SCREEN";

    position: absolute;

    top: 13px;

    left: 50%;

    transform:
        translateX(-50%);

    color: #777;

    font-size: 9px;

    letter-spacing: 4px;
}


/* =========================================================
   SEAT MAP
========================================================= */

.seat-map {

    display: flex;

    flex-direction: column;

    gap: 12px;

    align-items: center;

    overflow-x: auto;

    padding: 10px 5px 30px;
}


.seat-row {

    display: flex;

    align-items: center;

    gap: 7px;

    min-width: max-content;
}


.row-label {

    width: 25px;

    color: #d4af37;

    font-size: 10px;

    font-weight: 700;

    text-align: center;

    margin-right: 5px;
}


/* =========================================================
   AISLE
========================================================= */

.aisle {

    width: 28px;

    height: 5px;
}


/* =========================================================
   SEAT
========================================================= */

.seat {

    width: 38px;

    height: 34px;

    border-radius: 7px 7px 9px 9px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 9px;

    font-weight: 600;

    position: relative;

    transition:
        transform .2s,
        box-shadow .2s,
        background .2s;
}


.seat::before {

    content: "";

    position: absolute;

    left: 4px;

    right: 4px;

    bottom: -4px;

    height: 4px;

    border-radius: 0 0 5px 5px;

    background: inherit;

    opacity: .7;
}


/* REGULAR */

.seat.regular {

    background:
        rgba(126,63,242,.12);

    border:
        1px solid
        #8157c5;

    color: #cdbbff;
}


/* VIP */

.seat.vip {

    background:
        rgba(212,175,55,.10);

    border:
        1px solid
        #d4af37;

    color: #f1d978;
}


/* RECLINER */

.seat.recliner {

    background:
        rgba(255,255,255,.08);

    border:
        1px solid
        #bcbcbc;

    color: #eee;
}


/* HOVER */

.seat:not(.booked):hover {

    transform: translateY(-3px) scale(1.05);

    box-shadow:
        0 5px 15px
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

    color: #1a1022 !important;

    border-color: #ffe58a !important;

    box-shadow:
        0 0 15px
        rgba(212,175,55,.5);

    transform:
        translateY(-3px);
}


/* BOOKED */

.seat.booked {

    background: #29242d !important;

    border-color: #403b44 !important;

    color: #666 !important;

    cursor: not-allowed;

    opacity: .65;
}


/* =========================================================
   BOTTOM BOOKING BAR
========================================================= */

.booking-bar {

    margin-top: 25px;

    padding: 20px 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(212,175,55,.15);

    border-radius: 18px;

    box-shadow:
        0 10px 40px
        rgba(0,0,0,.25);
}


.selected-info {

    text-align: left;
}


.selected-info small {

    display: block;

    color: #777;

    font-size: 10px;
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


.total-box small {

    display: block;

    color: #888;

    font-size: 10px;
}


.total-price {

    color: #d4af37;

    font-size: 22px;

    font-weight: 800;
}


.continue-btn {

    border: none;

    cursor: pointer;

    padding: 13px 25px;

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

    transform: translateY(-2px);

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
   NO SEATS
========================================================= */

.no-seats {

    padding: 50px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.03);

    color: #888;
}


.no-seats i {

    font-size: 40px;

    color: #d4af37;

    margin-bottom: 15px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .booking-layout {

        grid-template-columns: 1fr;
    }


    .side-panel {

        position: static;

        display: grid;

        grid-template-columns: 1fr 1fr;

        gap: 20px;
    }


    .legend {

        margin-top: 0;
    }

}


@media(max-width:700px) {

    .page {

        padding: 20px 10px 35px;
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

        width: 31px;

        height: 29px;

        font-size: 7px;
    }


    .seat-row {

        gap: 5px;
    }


    .aisle {

        width: 15px;
    }


    .booking-bar {

        flex-direction: column;

        align-items: stretch;

        text-align: center;
    }


    .selected-info,
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



<!-- =========================================================
     PAGE
========================================================= -->

<div class="page">


    <!-- MOVIE INFORMATION -->

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
                echo $formatted_date;
                ?>

                &nbsp;&nbsp;

                <i class="fa-solid fa-clock"></i>

                <?php
                echo $formatted_time;
                ?>

            </p>

        </div>

    </div>



    <!-- BOOKING LAYOUT -->

    <div class="booking-layout">


        <!-- =================================================
             SIDE PANEL
        ================================================= -->

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

                    <p>
                        DATE
                    </p>

                    <strong>
                        <?php echo $formatted_date; ?>
                    </strong>

                </div>


                <div class="timing">

                    <p>
                        SHOW TIME
                    </p>

                    <strong>
                        <?php echo $formatted_time; ?>
                    </strong>

                </div>


                <div class="timing">

                    <p>
                        SCREEN
                    </p>

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
                    Seat Types
                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat legend-regular"
                    ></span>

                    Regular

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat legend-vip"
                    ></span>

                    VIP

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat"
                        style="
                            border-color:#aaa;
                            background:rgba(255,255,255,.08);
                        "
                    ></span>

                    Recliner

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat legend-selected"
                    ></span>

                    Selected

                </div>


                <div class="legend-item">

                    <span
                        class="legend-seat legend-booked"
                    ></span>

                    Booked

                </div>

            </div>

        </aside>



        <!-- =================================================
             SEAT AREA
        ================================================= -->

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



            <!-- SEATS -->

            <?php if (!empty($seat_rows)) { ?>

                <div class="seat-map">

                    <?php

                    foreach (
                        $seat_rows
                        as $row_name => $row_seats
                    ) {

                    ?>

                        <div class="seat-row">


                            <span class="row-label">

                                <?php
                                echo htmlspecialchars(
                                    $row_name
                                );
                                ?>

                            </span>


                            <?php

                            foreach (
                                $row_seats
                                as $index => $seat
                            ) {

                                /*
                                 * Create middle aisle.
                                 */

                                if ($index == 6) {
                                ?>

                                    <span class="aisle"></span>

                                <?php
                                }


                                $seat_id =
                                    (int) $seat['id'];


                                $seat_type =
                                    strtolower(
                                        trim(
                                            $seat['seat_type']
                                            ?? 'regular'
                                        )
                                    );


                                if (
                                    $seat_type === 'recliner'
                                ) {

                                    $type_class =
                                        'recliner';

                                } elseif (
                                    $seat_type === 'vip'
                                ) {

                                    $type_class =
                                        'vip';

                                } else {

                                    $type_class =
                                        'regular';
                                }


                                $is_booked =
                                    in_array(
                                        $seat_id,
                                        $booked_seats
                                    );

                            ?>

                                <button
                                    type="button"
                                    class="seat <?php echo $type_class; ?> <?php echo $is_booked ? 'booked' : ''; ?>"
                                    data-id="<?php echo $seat_id; ?>"
                                    data-seat="<?php
                                    echo htmlspecialchars(
                                        $row_name
                                    );
                                    ?>-<?php
                                    echo htmlspecialchars(
                                        $seat['seat_number']
                                    );
                                    ?>"
                                    data-type="<?php
                                    echo htmlspecialchars(
                                        $seat_type
                                    );
                                    ?>"
                                    data-price="<?php

                                    /*
                                     * Seat price according to type.
                                     */

                                    $base_price =
                                        (float)
                                        $showtime['price'];

                                    if (
                                        $seat_type === 'vip'
                                    ) {

                                        $seat_price =
                                            $base_price + 50;

                                    } elseif (
                                        $seat_type === 'recliner'
                                    ) {

                                        $seat_price =
                                            $base_price + 80;

                                    } else {

                                        $seat_price =
                                            $base_price;
                                    }

                                    echo $seat_price;
                                    ?>"
                                    <?php
                                    echo $is_booked
                                        ? 'disabled'
                                        : '';
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $seat['seat_number']
                                    );
                                    ?>

                                </button>

                            <?php } ?>

                        </div>

                    <?php } ?>

                </div>

            <?php } else { ?>


                <div class="no-seats">

                    <i
                        class="fa-solid fa-chair"
                    ></i>


                    <h3>
                        No Seats Available
                    </h3>


                    <p>
                        No seats have been added
                        for this screen yet.
                    </p>

                </div>


            <?php } ?>



            <!-- =================================================
                 BOOKING BAR
            ================================================= -->

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


                <input
                    type="hidden"
                    name="seat_ids"
                    id="seatIds"
                >


                <input
                    type="hidden"
                    name="total_amount"
                    id="totalAmountInput"
                >


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

                        <i
                            class="fa-solid fa-arrow-right"
                        ></i>

                    </button>

                </div>

            </form>


        </section>

    </div>

</div>



<script>

/* =========================================================
   SEAT SELECTION
========================================================= */

const seats =
    document.querySelectorAll(".seat:not(.booked)");

const selectedSeatsText =
    document.getElementById(
        "selectedSeatsText"
    );

const totalAmount =
    document.getElementById(
        "totalAmount"
    );

const seatIds =
    document.getElementById(
        "seatIds"
    );

const totalAmountInput =
    document.getElementById(
        "totalAmountInput"
    );

const continueBtn =
    document.getElementById(
        "continueBtn"
    );


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
                this.dataset.id;

            const seatName =
                this.dataset.seat;

            const price =
                parseFloat(
                    this.dataset.price
                );


            const existing =
                selectedSeats.find(
                    function(item) {

                        return item.id == id;

                    }
                );


            /* REMOVE */

            if (existing) {

                selectedSeats =
                    selectedSeats.filter(
                        function(item) {

                            return item.id != id;

                        }
                    );


                total -= price;

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

    /* SEAT NAMES */

    if (
        selectedSeats.length === 0
    ) {

        selectedSeatsText.innerHTML =
            "No seats selected";

    } else {

        selectedSeatsText.innerHTML =
            selectedSeats
            .map(
                function(seat) {

                    return seat.name;

                }
            )
            .join(" • ");

    }


    /* TOTAL */

    totalAmount.innerText =
        total.toFixed(2);


    totalAmountInput.value =
        total.toFixed(2);


    /* IDS */

    seatIds.value =
        selectedSeats
        .map(
            function(seat) {

                return seat.id;

            }
        )
        .join(",");


    /* BUTTON */

    continueBtn.disabled =
        selectedSeats.length === 0;

}


/* =========================================================
   FORM VALIDATION
========================================================= */

document
    .getElementById("bookingForm")
    .addEventListener(
        "submit",
        function(event) {

            if (
                selectedSeats.length === 0
            ) {

                event.preventDefault();

                alert(
                    "Please select at least one seat."
                );

            }

        }
    );

</script>


</body>

</html>
