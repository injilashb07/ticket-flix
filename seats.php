<?php
session_start();

require_once 'config.php';

/*
|--------------------------------------------------------------------------
| TicketFlix - Seat Selection Page
|--------------------------------------------------------------------------
| URL:
| seats.php?showtime_id=1
|--------------------------------------------------------------------------
*/


/* ----------------------------------------------------
   1. CHECK SHOWTIME ID
---------------------------------------------------- */

if (!isset($_GET['showtime_id']) || !is_numeric($_GET['showtime_id'])) {
    header("Location: showtimes.php");
    exit();
}

$showtime_id = (int) $_GET['showtime_id'];


/* ----------------------------------------------------
   2. GET SHOWTIME DETAILS
---------------------------------------------------- */

$sql = "
    SELECT
        id,
        movie_id,
        screen_id,
        show_date,
        show_time,
        end_time,
        price,
        available_seats
    FROM showtimes
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $showtime_id);
$stmt->execute();

$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {
    die("Showtime not found.");
}


/* ----------------------------------------------------
   3. GET SCREEN DETAILS
---------------------------------------------------- */

$screen_id = (int) $showtime['screen_id'];

$sql = "
    SELECT
        id,
        theater_id,
        screen_name,
        total_seats
    FROM screens
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Screen query error: " . $conn->error);
}

$stmt->bind_param("i", $screen_id);
$stmt->execute();

$result = $stmt->get_result();

$screen = $result->fetch_assoc();

$stmt->close();


if (!$screen) {
    die("Screen not found.");
}


/* ----------------------------------------------------
   4. GET THEATER DETAILS
---------------------------------------------------- */

$theater_id = (int) $screen['theater_id'];

$sql = "
    SELECT
        id,
        name,
        address,
        city,
        state,
        zip_code
    FROM theaters
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Theater query error: " . $conn->error);
}

$stmt->bind_param("i", $theater_id);
$stmt->execute();

$result = $stmt->get_result();

$theater = $result->fetch_assoc();

$stmt->close();


/* ----------------------------------------------------
   5. GET MOVIE DETAILS
---------------------------------------------------- */

$movie_name = "Movie";
$movie = null;

$sql = "
    SELECT *
    FROM movies
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Movie query error: " . $conn->error);
}

$stmt->bind_param("i", $showtime['movie_id']);
$stmt->execute();

$result = $stmt->get_result();

$movie = $result->fetch_assoc();

$stmt->close();


if ($movie) {

    if (isset($movie['name'])) {
        $movie_name = $movie['name'];
    } elseif (isset($movie['title'])) {
        $movie_name = $movie['title'];
    } elseif (isset($movie['movie_name'])) {
        $movie_name = $movie['movie_name'];
    }
}


/* ----------------------------------------------------
   6. GET SEATS
---------------------------------------------------- */

/*
   IMPORTANT:
   Do NOT overwrite this query with $sql again.
*/

$seat_query = "
    SELECT
        `id`,
        `screen_id`,
        `row_number`,
        `seat_number`,
        `seat_type`,
        `is_active`
    FROM `seats`
    WHERE `screen_id` = ?
      AND `is_active` = 1
    ORDER BY `row_number`, `seat_number`
";

$seat_stmt = $conn->prepare($seat_query);

if (!$seat_stmt) {
    die("Seat query error: " . $conn->error);
}

$seat_stmt->bind_param("i", $screen_id);

$seat_stmt->execute();

$seat_result = $seat_stmt->get_result();

$seats = [];

while ($seat = $seat_result->fetch_assoc()) {
    $seats[] = $seat;
}

$seat_stmt->close();


/* ----------------------------------------------------
   7. GET BOOKED SEATS
---------------------------------------------------- */

$booked_seats = [];

$booked_query = "
    SELECT
        bs.seat_id
    FROM booking_seats bs

    INNER JOIN bookings b
        ON bs.booking_id = b.id

    WHERE b.showtime_id = ?

    AND (
        b.booking_status = 'confirmed'
        OR b.booking_status = 'pending'
    )
";

$booked_stmt = $conn->prepare($booked_query);

if ($booked_stmt) {

    $booked_stmt->bind_param(
        "i",
        $showtime_id
    );

    $booked_stmt->execute();

    $booked_result = $booked_stmt->get_result();

    while ($booked = $booked_result->fetch_assoc()) {

        $booked_seats[] =
            (int) $booked['seat_id'];
    }

    $booked_stmt->close();
}


/* ----------------------------------------------------
   8. SEAT PRICE
---------------------------------------------------- */

$base_price = (float) $showtime['price'];

function getSeatPrice($type, $base_price)
{
    $type = strtolower(trim($type));

    if ($type === 'recliner') {
        return $base_price * 1.50;
    }

    if ($type === 'vip') {
        return $base_price * 2;
    }

    return $base_price;
}


/* ----------------------------------------------------
   9. GROUP SEATS BY ROW
---------------------------------------------------- */

$seat_rows = [];

foreach ($seats as $seat) {

    /*
       Prevent undefined array warning
    */

    $row = isset($seat['row_number'])
        ? $seat['row_number']
        : '';


    if ($row === '') {
        continue;
    }


    if (!isset($seat_rows[$row])) {
        $seat_rows[$row] = [];
    }


    $seat_rows[$row][] = $seat;
}


/* ----------------------------------------------------
   10. SORT ROWS
---------------------------------------------------- */

uksort($seat_rows, function ($a, $b) {

    return strnatcasecmp(
        (string) $a,
        (string) $b
    );

});


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
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<style>

/* ==================================================
   GLOBAL
================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    font-family: 'Poppins', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(126, 87, 194, 0.25),
            transparent 35%
        ),

        radial-gradient(
            circle at bottom left,
            rgba(255, 193, 7, 0.10),
            transparent 35%
        ),

        #100b18;

    color: white;

    min-height: 100vh;
}


/* ==================================================
   NAVBAR
================================================== */

.navbar {

    height: 75px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 6%;

    background:
        rgba(18, 12, 28, 0.97);

    border-bottom:
        1px solid rgba(255, 193, 7, 0.20);

    position: sticky;

    top: 0;

    z-index: 100;
}


.logo {

    font-size: 27px;

    font-weight: 800;

    color: white;

    letter-spacing: 1px;
}


.logo span {

    color: #d4af37;
}


.back-btn {

    text-decoration: none;

    color: white;

    border:
        1px solid rgba(255,255,255,0.20);

    padding: 9px 18px;

    border-radius: 25px;

    transition: 0.3s;

    font-size: 14px;
}


.back-btn:hover {

    background: #d4af37;

    color: #171020;

    border-color: #d4af37;
}


/* ==================================================
   MAIN
================================================== */

.container {

    width: 92%;

    max-width: 1200px;

    margin: auto;

    padding:
        35px 0 130px;
}


/* ==================================================
   SHOW INFORMATION
================================================== */

.show-info {

    background:
        rgba(255,255,255,0.06);

    border:
        1px solid rgba(212,175,55,0.18);

    border-radius: 22px;

    padding: 22px 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    backdrop-filter: blur(15px);

    margin-bottom: 30px;
}


.movie-title {

    font-size: 26px;

    font-weight: 700;
}


.movie-title span {

    color: #d4af37;
}


.show-details {

    display: flex;

    flex-wrap: wrap;

    gap: 12px;

    margin-top: 10px;
}


.info-pill {

    background:
        rgba(255,255,255,0.08);

    border:
        1px solid rgba(255,255,255,0.10);

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 13px;

    color: #ddd;
}


.price-box {

    text-align: right;
}


.price-box small {

    color: #aaa;

    display: block;
}


.price-box strong {

    color: #d4af37;

    font-size: 26px;
}


/* ==================================================
   SCREEN
================================================== */

.screen-section {

    text-align: center;

    margin-bottom: 35px;
}


.screen {

    width: 75%;

    max-width: 700px;

    height: 55px;

    margin: auto;

    background:
        linear-gradient(
            to bottom,
            #ffffff,
            rgba(255,255,255,0.15)
        );

    border-radius: 50%;

    transform:
        perspective(200px)
        rotateX(-15deg);

    box-shadow:
        0 12px 35px
        rgba(255,255,255,0.25);
}


.screen-text {

    margin-top: 12px;

    color: #aaa;

    font-size: 12px;

    letter-spacing: 4px;

    text-transform: uppercase;
}


/* ==================================================
   LEGEND
================================================== */

.legend {

    display: flex;

    justify-content: center;

    flex-wrap: wrap;

    gap: 25px;

    margin: 30px 0;
}


.legend-item {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #bbb;

    font-size: 13px;
}


.legend-seat {

    width: 22px;

    height: 22px;

    border-radius: 6px;

    border: 1px solid #aaa;
}


.legend-available {

    background: #332641;
}


.legend-selected {

    background: #d4af37;

    border-color: #d4af37;
}


.legend-booked {

    background: #522338;

    border-color: #522338;
}


.legend-vip {

    background: #654c12;

    border-color: #d4af37;
}


/* ==================================================
   SEAT CONTAINER
================================================== */

.seat-container {

    background:
        rgba(255,255,255,0.035);

    border:
        1px solid rgba(255,255,255,0.08);

    border-radius: 25px;

    padding: 35px 20px;

    overflow-x: auto;
}


.seat-row {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    margin-bottom: 16px;

    min-width: 600px;
}


.row-label {

    width: 30px;

    color: #d4af37;

    font-weight: 700;

    text-align: center;

    margin-right: 8px;
}


/* ==================================================
   SEATS
================================================== */

.seat {

    width: 42px;

    height: 38px;

    border-radius:
        10px 10px 7px 7px;

    background: #30203d;

    border:
        1px solid #654c72;

    color: #ddd;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;

    transition: 0.25s;

    display: flex;

    align-items: center;

    justify-content: center;
}


.seat:hover:not(:disabled) {

    transform:
        translateY(-4px);

    border-color:
        #d4af37;

    box-shadow:
        0 6px 18px
        rgba(212,175,55,0.20);
}


/* VIP */

.seat.vip {

    background: #4b3a13;

    border-color: #b9962e;

    color: #ffe8a0;
}


/* RECLINER */

.seat.recliner {

    background: #392447;

    border-color: #9568ad;
}


/* SELECTED */

.seat.selected {

    background: #d4af37;

    color: #171020;

    border-color: #ffe58a;

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 20px
        rgba(212,175,55,0.40);
}


/* BOOKED */

.seat.booked {

    background: #4a2334;

    border-color: #633047;

    color: #8f7180;

    cursor: not-allowed;

    opacity: 0.65;
}


/* ==================================================
   NO SEATS
================================================== */

.no-seats {

    text-align: center;

    padding: 60px 20px;

    color: #aaa;
}


.no-seats-icon {

    font-size: 55px;

    margin-bottom: 15px;
}


/* ==================================================
   BOOKING BAR
================================================== */

.booking-bar {

    position: fixed;

    bottom: 0;

    left: 0;

    width: 100%;

    background:
        rgba(15,9,23,0.97);

    border-top:
        1px solid rgba(212,175,55,0.25);

    backdrop-filter: blur(15px);

    padding: 17px 5%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    z-index: 100;
}


.selected-info {

    display: flex;

    flex-direction: column;
}


.selected-info small {

    color: #999;

    font-size: 12px;
}


.selected-info strong {

    color: white;

    font-size: 16px;
}


.total-box {

    display: flex;

    align-items: center;

    gap: 25px;
}


.total {

    text-align: right;
}


.total small {

    color: #aaa;

    display: block;

    font-size: 12px;
}


.total strong {

    color: #d4af37;

    font-size: 25px;
}


.continue-btn {

    border: none;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1d46a
        );

    color: #171020;

    padding: 13px 27px;

    border-radius: 30px;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition: 0.3s;

    box-shadow:
        0 8px 25px
        rgba(212,175,55,0.25);
}


.continue-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(212,175,55,0.40);
}


.continue-btn:disabled {

    opacity: 0.45;

    cursor: not-allowed;

    transform: none;

    box-shadow: none;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media(max-width:700px) {

    .show-info {

        flex-direction: column;

        align-items: flex-start;
    }


    .price-box {

        text-align: left;
    }


    .screen {

        width: 90%;
    }


    .booking-bar {

        padding: 13px 4%;
    }


    .total-box {

        gap: 10px;
    }


    .continue-btn {

        padding: 11px 16px;
    }


    .seat {

        width: 37px;

        height: 34px;
    }

}

</style>

</head>


<body>


<!-- ==================================================
     NAVBAR
================================================== -->

<nav class="navbar">

    <div class="logo">

        Ticket<span>Flix</span> 🎬

    </div>


    <a
        href="showtimes.php"
        class="back-btn"
    >

        ← Back to Showtimes

    </a>

</nav>



<!-- ==================================================
     MAIN
================================================== -->

<div class="container">


    <!-- SHOW INFORMATION -->

    <div class="show-info">


        <div>

            <div class="movie-title">

                <?= htmlspecialchars($movie_name); ?>

            </div>


            <div class="show-details">


                <div class="info-pill">

                    📅
                    <?= date(
                        "d M Y",
                        strtotime($showtime['show_date'])
                    ); ?>

                </div>


                <div class="info-pill">

                    🕐
                    <?= date(
                        "h:i A",
                        strtotime($showtime['show_time'])
                    ); ?>

                </div>


                <div class="info-pill">

                    🎭
                    <?= htmlspecialchars(
                        $screen['screen_name']
                    ); ?>

                </div>


                <?php if ($theater): ?>

                    <div class="info-pill">

                        📍
                        <?= htmlspecialchars(
                            $theater['name']
                        ); ?>

                    </div>

                <?php endif; ?>


            </div>

        </div>


        <div class="price-box">

            <small>
                Starting from
            </small>

            <strong>

                ₹<?= number_format(
                    $base_price,
                    2
                ); ?>

            </strong>

        </div>


    </div>



    <!-- SCREEN -->

    <div class="screen-section">

        <div class="screen"></div>

        <div class="screen-text">

            Screen this way

        </div>

    </div>



    <!-- LEGEND -->

    <div class="legend">


        <div class="legend-item">

            <div class="legend-seat legend-available"></div>

            Available

        </div>


        <div class="legend-item">

            <div class="legend-seat legend-selected"></div>

            Selected

        </div>


        <div class="legend-item">

            <div class="legend-seat legend-booked"></div>

            Booked

        </div>


        <div class="legend-item">

            <div class="legend-seat legend-vip"></div>

            VIP

        </div>


    </div>



    <!-- SEATS -->

    <div class="seat-container">


        <?php if (empty($seat_rows)): ?>


            <div class="no-seats">

                <div class="no-seats-icon">
                    💺
                </div>

                <h3>
                    No Seats Available
                </h3>

                <p>
                    This screen currently has no active seats.
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($seat_rows as $row => $row_seats): ?>


                <div class="seat-row">


                    <div class="row-label">

                        <?= htmlspecialchars($row); ?>

                    </div>


                    <?php foreach ($row_seats as $seat): ?>


                        <?php

                        $seat_id =
                            (int) $seat['id'];


                        $seat_type =
                            strtolower(
                                trim(
                                    $seat['seat_type'] ?? 'regular'
                                )
                            );


                        $is_booked =
                            in_array(
                                $seat_id,
                                $booked_seats
                            );


                        $seat_price =
                            getSeatPrice(
                                $seat_type,
                                $base_price
                            );


                        $classes = "seat";


                        if ($seat_type === 'vip') {

                            $classes .= " vip";

                        } elseif ($seat_type === 'recliner') {

                            $classes .= " recliner";

                        }


                        if ($is_booked) {

                            $classes .= " booked";

                        }

                        ?>


                        <button

                            type="button"

                            class="<?= $classes; ?>"

                            data-seat-id="<?= $seat_id; ?>"

                            data-price="<?= number_format(
                                $seat_price,
                                2,
                                '.',
                                ''
                            ); ?>"

                            data-seat="<?= htmlspecialchars(
                                $row . $seat['seat_number']
                            ); ?>"

                            <?= $is_booked
                                ? 'disabled'
                                : ''; ?>

                        >

                            <?= htmlspecialchars(
                                $row . $seat['seat_number']
                            ); ?>

                        </button>


                    <?php endforeach; ?>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>


</div>



<!-- ==================================================
     BOOKING FORM
================================================== -->

<form
    action="booking.php"
    method="POST"
    id="bookingForm"
>


    <input
        type="hidden"
        name="showtime_id"
        value="<?= $showtime_id; ?>"
    >


    <div id="hiddenSeats"></div>


    <div class="booking-bar">


        <div class="selected-info">

            <small>
                Selected Seats
            </small>

            <strong id="selectedSeatsText">

                None

            </strong>

        </div>


        <div class="total-box">


            <div class="total">

                <small>
                    Total Amount
                </small>

                <strong id="totalAmount">

                    ₹0.00

                </strong>

            </div>


            <button
                type="submit"
                class="continue-btn"
                id="continueBtn"
                disabled
            >

                Continue →

            </button>


        </div>


    </div>


</form>



<script>

/* ==================================================
   SEAT SELECTION
================================================== */


const seats =
    document.querySelectorAll(
        '.seat:not(:disabled)'
    );


const selectedSeatsText =
    document.getElementById(
        'selectedSeatsText'
    );


const totalAmount =
    document.getElementById(
        'totalAmount'
    );


const continueBtn =
    document.getElementById(
        'continueBtn'
    );


const hiddenSeats =
    document.getElementById(
        'hiddenSeats'
    );


const bookingForm =
    document.getElementById(
        'bookingForm'
    );


let selectedSeats = [];



/* ==================================================
   SEAT CLICK
================================================== */


seats.forEach(function(seat) {


    seat.addEventListener(
        'click',
        function()
        {


            const seatId =
                this.dataset.seatId;


            const seatName =
                this.dataset.seat;


            const price =
                parseFloat(
                    this.dataset.price
                );


            const existingIndex =
                selectedSeats.findIndex(
                    function(item) {

                        return item.id === seatId;

                    }
                );


            /* REMOVE */

            if (existingIndex !== -1) {


                selectedSeats.splice(
                    existingIndex,
                    1
                );


                this.classList.remove(
                    'selected'
                );


            }


            /* ADD */

            else {


                selectedSeats.push({

                    id: seatId,

                    name: seatName,

                    price: price

                });


                this.classList.add(
                    'selected'
                );

            }


            updateBooking();

        }
    );

});



/* ==================================================
   UPDATE BOOKING
================================================== */

function updateBooking()
{


    /* SEAT NAMES */

    if (selectedSeats.length === 0) {


        selectedSeatsText.innerText =
            "None";


    } else {


        selectedSeatsText.innerText =
            selectedSeats
                .map(
                    function(seat) {

                        return seat.name;

                    }
                )
                .join(', ');

    }



    /* TOTAL */

    let total = 0;


    selectedSeats.forEach(
        function(seat) {

            total += seat.price;

        }
    );


    totalAmount.innerText =
        "₹" + total.toFixed(2);



    /* BUTTON */

    continueBtn.disabled =
        selectedSeats.length === 0;



    /* HIDDEN SEAT INPUTS */

    hiddenSeats.innerHTML = "";


    selectedSeats.forEach(
        function(seat)
        {


            const input =
                document.createElement(
                    'input'
                );


            input.type = 'hidden';


            input.name =
                'seat_ids[]';


            input.value =
                seat.id;


            hiddenSeats.appendChild(
                input
            );

        }
    );

}



/* ==================================================
   FORM VALIDATION
================================================== */

bookingForm.addEventListener(
    'submit',
    function(event)
    {


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