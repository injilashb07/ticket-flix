<?php

session_start();

require_once 'config.php';


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| GET SHOWTIME ID
|--------------------------------------------------------------------------
*/

$showtime_id = isset($_GET['showtime_id'])
    ? (int)$_GET['showtime_id']
    : (isset($_GET['id']) ? (int)$_GET['id'] : 0);


if ($showtime_id <= 0) {

    die("Invalid showtime ID.");

}


/*
|--------------------------------------------------------------------------
| GET SHOWTIME
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        movie_id,
        screen_id,
        show_date,
        show_time,
        price
    FROM showtimes
    WHERE id = ?
    LIMIT 1
");


if (!$stmt) {

    die(
        "Showtime query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {

    die("Showtime not found.");

}


$screen_id = (int)$showtime['screen_id'];


/*
|--------------------------------------------------------------------------
| GET SCREEN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        screen_name,
        total_seats
    FROM screens
    WHERE id = ?
    LIMIT 1
");


if (!$stmt) {

    die(
        "Screen query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $screen_id
);

$stmt->execute();

$result = $stmt->get_result();

$screen = $result->fetch_assoc();

$stmt->close();


if (!$screen) {

    die("Screen not found.");

}


$total_screen_seats = (int)$screen['total_seats'];


/*
|--------------------------------------------------------------------------
| CHECK EXISTING SEATS
|--------------------------------------------------------------------------
*/

$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM seats
    WHERE screen_id = ?
");


if (!$count_stmt) {

    die(
        "Seat count query error: " .
        htmlspecialchars($conn->error)
    );

}


$count_stmt->bind_param(
    "i",
    $screen_id
);

$count_stmt->execute();

$count_result = $count_stmt->get_result();

$count_data = $count_result->fetch_assoc();

$count_stmt->close();


$existing_seat_count =
    (int)$count_data['total'];


/*
|--------------------------------------------------------------------------
| AUTO CREATE SEATS
|--------------------------------------------------------------------------
*/

if (
    $existing_seat_count === 0 &&
    $total_screen_seats > 0
) {

    $seats_per_row = 10;


    $insert_seat_stmt = $conn->prepare("
        INSERT INTO seats
        (
            screen_id,
            seat_row,
            seat_number,
            seat_type,
            is_active
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'regular',
            1
        )
    ");


    if (!$insert_seat_stmt) {

        die(
            "Seat creation error: " .
            htmlspecialchars($conn->error)
        );

    }


    for (
        $seat_index = 0;
        $seat_index < $total_screen_seats;
        $seat_index++
    ) {

        $row_number =
            intdiv(
                $seat_index,
                $seats_per_row
            );


        $row_name = '';

        $n = $row_number + 1;


        while ($n > 0) {

            $remainder =
                ($n - 1) % 26;

            $row_name =
                chr(
                    65 + $remainder
                ) . $row_name;

            $n =
                intdiv(
                    $n - 1,
                    26
                );

        }


        $seat_number =
            ($seat_index % $seats_per_row) + 1;


        $insert_seat_stmt->bind_param(
            "isi",
            $screen_id,
            $row_name,
            $seat_number
        );


        if (!$insert_seat_stmt->execute()) {

            $error =
                $insert_seat_stmt->error;

            $insert_seat_stmt->close();

            die(
                "Seat creation failed: " .
                htmlspecialchars($error)
            );

        }

    }


    $insert_seat_stmt->close();

}


/*
|--------------------------------------------------------------------------
| GET SEATS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.screen_id,
        s.seat_row,
        s.seat_number,
        s.seat_type,
        s.is_active,

        CASE
            WHEN b.id IS NOT NULL
            THEN 1
            ELSE 0
        END AS is_booked

    FROM seats s

    LEFT JOIN booking_seats bs
        ON bs.seat_id = s.id

    LEFT JOIN bookings b
        ON b.id = bs.booking_id
        AND b.showtime_id = ?
        AND (
            b.booking_status = 'pending'
            OR
            b.booking_status = 'confirmed'
        )

    WHERE s.screen_id = ?
      AND s.is_active = 1

    GROUP BY
        s.id,
        s.screen_id,
        s.seat_row,
        s.seat_number,
        s.seat_type,
        s.is_active,
        b.id

    ORDER BY
        s.seat_row ASC,
        s.seat_number ASC
");


if (!$stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "ii",
    $showtime_id,
    $screen_id
);

$stmt->execute();

$seat_result = $stmt->get_result();


$seats = [];


while (
    $row = $seat_result->fetch_assoc()
) {

    $seats[] = $row;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| GROUP SEATS BY ROW
|--------------------------------------------------------------------------
*/

$seat_rows = [];


foreach (
    $seats as $seat
) {

    $row_name =
        strtoupper(
            trim(
                $seat['seat_row']
            )
        );


    if (
        !isset(
            $seat_rows[$row_name]
        )
    ) {

        $seat_rows[$row_name] = [];

    }


    $seat_rows[$row_name][] = $seat;

}


/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

$ticket_price =
    (float)$showtime['price'];


/*
|--------------------------------------------------------------------------
| MOVIE NAME
|--------------------------------------------------------------------------
*/

$movie_name = "Movie";


$movie_stmt = $conn->prepare("
    SELECT *
    FROM movies
    WHERE id = ?
    LIMIT 1
");


if ($movie_stmt) {

    $movie_stmt->bind_param(
        "i",
        $showtime['movie_id']
    );

    $movie_stmt->execute();

    $movie_result =
        $movie_stmt->get_result();

    $movie = $movie_result->fetch_assoc();

    $movie_stmt->close();


    if ($movie) {

        if (isset($movie['name'])) {

            $movie_name = $movie['name'];

        }

        elseif (isset($movie['title'])) {

            $movie_name = $movie['title'];

        }

        elseif (isset($movie['movie_name'])) {

            $movie_name = $movie['movie_name'];

        }

    }

}


/*
|--------------------------------------------------------------------------
| COUNT AVAILABLE / BOOKED
|--------------------------------------------------------------------------
*/

$available_count = 0;

$booked_count = 0;


foreach (
    $seats as $seat
) {

    if (
        (int)$seat['is_booked'] === 1
    ) {

        $booked_count++;

    }

    else {

        $available_count++;

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
    Select Seats | TicketFlix
</title>


<style>

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top,
            #32143f 0%,
            #170b20 42%,
            #09070d 100%
        );

    min-height: 100vh;

    color: #fff;

}


/* HEADER */

.header {

    width: 100%;

    padding: 20px 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

    background:
        rgba(10,7,14,.80);

    backdrop-filter:
        blur(12px);

    position: sticky;

    top: 0;

    z-index: 100;

}


.logo {

    font-size: 28px;

    font-weight: 800;

}


.logo span {

    color: #f4c430;

}


.back-btn {

    color: white;

    text-decoration: none;

    padding: 10px 18px;

    border-radius: 25px;

    border:
        1px solid
        rgba(255,255,255,.15);

    background:
        rgba(255,255,255,.06);

}


.back-btn:hover {

    background: #f4c430;

    color: #21102f;

}


/* MAIN */

.container {

    width: min(1200px,92%);

    margin: auto;

    padding:
        45px 0 130px;

}


.page-title {

    text-align: center;

    margin-bottom: 8px;

    font-size: 34px;

    font-weight: 800;

}


.subtitle {

    text-align: center;

    color: #aaa4b0;

    margin-bottom: 20px;

}


.movie-name {

    text-align: center;

    color: #f4c430;

    font-size: 19px;

    font-weight: 700;

    margin-bottom: 25px;

}


/* INFO */

.show-info {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 12px;

    flex-wrap: wrap;

    margin-bottom: 30px;

}


.info-box {

    padding:
        12px 20px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 12px;

    color: #ddd;

}


.info-box strong {

    color: #fff;

}


.price {

    color: #f4c430 !important;

}


/* AVAILABILITY */

.availability {

    text-align: center;

    margin-bottom: 30px;

    color: #aaa;

}


.availability strong {

    color: #fff;

}


/* SCREEN */

.screen-area {

    width:
        min(800px,100%);

    margin:
        0 auto 45px;

    text-align: center;

}


.screen {

    height: 45px;

    width: 82%;

    margin: auto;

    border-radius: 50%;

    background:
        linear-gradient(
            to bottom,
            #fff,
            rgba(255,255,255,.1)
        );

    box-shadow:
        0 10px 35px
        rgba(255,255,255,.25);

    transform:
        perspective(150px)
        rotateX(-8deg);

}


.screen-label {

    margin-top: 13px;

    color: #9c96a4;

    font-size: 13px;

    letter-spacing: 2px;

    text-transform: uppercase;

}


/* SEATS */

.seat-area {

    width: 100%;

    overflow-x: auto;

    padding:
        10px 5px 30px;

}


.seat-layout {

    width: max-content;

    min-width: 650px;

    margin: auto;

}


.seat-row {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    margin-bottom: 14px;

}


.row-label {

    width: 32px;

    color: #8e8995;

    font-weight: bold;

    font-size: 13px;

    text-align: center;

}


/* SEAT */

.seat {

    width: 43px;

    height: 38px;

    border-radius:
        10px 10px 7px 7px;

    border:
        1px solid
        rgba(255,255,255,.12);

    background: #24202a;

    color: #cfcbd3;

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 11px;

    font-weight: 700;

    transition: .2s;

    position: relative;

}


.seat::after {

    content: "";

    position: absolute;

    bottom: -5px;

    left: 7px;

    right: 7px;

    height: 5px;

    border-radius:
        0 0 5px 5px;

    background:
        rgba(255,255,255,.08);

}


.seat:hover {

    transform:
        translateY(-3px);

    border-color:
        #f4c430;

}


/* VIP */

.seat.vip {

    border-color:
        rgba(255,180,60,.25);

    background:
        linear-gradient(
            145deg,
            #4b3420,
            #2a211b
        );

    color: #ffd98a;

}


/* BOOKED */

.seat.booked {

    background:
        #111;

    color:
        #666;

    border-color:
        #333;

    cursor:
        not-allowed;

    opacity:
        .55;

}


.seat.booked::before {

    content: "×";

    position: absolute;

    font-size: 22px;

    color: #ff7777;

    z-index: 2;

}


.seat.booked:hover {

    transform: none;

    border-color: #333;

}


/* SELECTED */

.seat.selected {

    background:
        linear-gradient(
            145deg,
            #f4c430,
            #dba900
        );

    border-color:
        #ffe17a;

    color: #21102f;

    box-shadow:
        0 7px 20px
        rgba(244,196,48,.4);

    transform:
        translateY(-3px);

}


.seat.selected::after {

    background:
        #b58a00;

}


/* LEGEND */

.legend {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 25px;

    flex-wrap: wrap;

    margin:
        25px 0 45px;

}


.legend-item {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #aaa4b0;

    font-size: 13px;

}


.legend-seat {

    width: 20px;

    height: 18px;

    border-radius: 5px;

    background: #24202a;

    border:
        1px solid
        rgba(255,255,255,.12);

}


.legend-seat.vip {

    background: #4b3420;

}


.legend-seat.selected {

    background: #f4c430;

}


.legend-seat.booked {

    background: #111;

    border-color: #333;

}


/* EMPTY */

.empty {

    text-align: center;

    padding: 60px 20px;

    color: #aaa4b0;

}


/* BOTTOM BAR */

.bottom-bar {

    position: fixed;

    left: 0;

    right: 0;

    bottom: 0;

    z-index: 90;

    background:
        rgba(12,8,16,.94);

    border-top:
        1px solid
        rgba(255,255,255,.1);

    backdrop-filter:
        blur(15px);

    padding:
        15px 6%;

}


.bottom-inner {

    max-width: 1200px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

}


.selection-info {

    display: flex;

    flex-direction: column;

    gap: 4px;

}


.selection-info .small {

    color: #99939f;

    font-size: 12px;

}


.selected-count {

    font-size: 17px;

    font-weight: 700;

}


.total {

    color: #f4c430;

}


.continue-btn {

    border: none;

    padding:
        14px 28px;

    border-radius: 30px;

    background:
        linear-gradient(
            135deg,
            #f4c430,
            #dfa900
        );

    color: #21102f;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;

    min-width: 180px;

}


.continue-btn:disabled {

    opacity: .45;

    cursor: not-allowed;

}


@media(max-width:650px) {

    .header {

        padding:
            17px 4%;

    }

    .logo {

        font-size: 23px;

    }

    .container {

        width: 94%;

        padding-top: 30px;

    }

    .page-title {

        font-size: 27px;

    }

    .seat {

        width: 38px;

        height: 35px;

        font-size: 10px;

    }

    .seat-row {

        gap: 6px;

    }

    .bottom-inner {

        align-items: center;

    }

    .continue-btn {

        min-width: 140px;

        padding:
            12px 18px;

    }

}

</style>

</head>


<body>


<header class="header">

    <div class="logo">

        Ticket<span>Flix</span>

    </div>


    <a
        href="javascript:history.back()"
        class="back-btn"
    >

        ← Back

    </a>

</header>



<main class="container">


    <h1 class="page-title">

        Select Your Seats

    </h1>


    <p class="subtitle">

        Choose your favourite seats
        and enjoy the show

    </p>


    <div class="movie-name">

        <?= htmlspecialchars($movie_name); ?>

    </div>



    <div class="show-info">


        <div class="info-box">

            Screen

            <strong>

                <?= htmlspecialchars(
                    $screen['screen_name']
                ); ?>

            </strong>

        </div>


        <div class="info-box">

            Date

            <strong>

                <?= htmlspecialchars(
                    date(
                        "d M Y",
                        strtotime(
                            $showtime['show_date']
                        )
                    )
                ); ?>

            </strong>

        </div>


        <div class="info-box">

            Time

            <strong>

                <?= htmlspecialchars(
                    date(
                        "h:i A",
                        strtotime(
                            $showtime['show_time']
                        )
                    )
                ); ?>

            </strong>

        </div>


        <div class="info-box">

            Price

            <strong class="price">

                ₹<?= number_format(
                    $ticket_price,
                    2
                ); ?>

            </strong>

        </div>


    </div>



    <div class="availability">

        Available:

        <strong>
            <?= $available_count; ?>
        </strong>

        &nbsp; | &nbsp;

        Booked:

        <strong>
            <?= $booked_count; ?>
        </strong>

        &nbsp; | &nbsp;

        Total:

        <strong>
            <?= count($seats); ?>
        </strong>

    </div>



    <div class="screen-area">

        <div class="screen"></div>

        <div class="screen-label">

            Screen

        </div>

    </div>



    <?php if (count($seat_rows) > 0): ?>


        <div class="seat-area">


            <div class="seat-layout">


                <?php foreach (
                    $seat_rows as
                    $row_name => $row_seats
                ): ?>


                    <div class="seat-row">


                        <div class="row-label">

                            <?= htmlspecialchars(
                                $row_name
                            ); ?>

                        </div>



                        <?php foreach (
                            $row_seats as
                            $index => $seat
                        ): ?>


                            <?php if (
                                $index === 5
                            ): ?>

                                <div
                                    style="width:28px;"
                                ></div>

                            <?php endif; ?>


                            <?php

                            $seat_type =
                                strtolower(
                                    trim(
                                        $seat['seat_type']
                                    )
                                );


                            $is_booked =
                                (int)$seat['is_booked']
                                === 1;


                            $seat_class =
                                'seat';


                            if (
                                $seat_type === 'vip'
                            ) {

                                $seat_class .= ' vip';

                            }


                            if ($is_booked) {

                                $seat_class .= ' booked';

                            }

                            ?>


                            <button
                                type="button"
                                class="<?= $seat_class; ?>"
                                data-seat-id="<?= (int)$seat['id']; ?>"
                                data-seat-row="<?= htmlspecialchars($row_name); ?>"
                                data-seat-number="<?= (int)$seat['seat_number']; ?>"
                                data-seat-type="<?= htmlspecialchars($seat_type); ?>"
                                data-price="<?= htmlspecialchars($ticket_price); ?>"
                                <?= $is_booked ? 'disabled' : ''; ?>
                            >

                                <?= htmlspecialchars(
                                    $row_name
                                ); ?><?= (int)$seat['seat_number']; ?>

                            </button>


                        <?php endforeach; ?>


                    </div>


                <?php endforeach; ?>


            </div>

        </div>


    <?php else: ?>


        <div class="empty">

            <h3>
                No seats available
            </h3>

            <p>
                This screen has no seats configured.
            </p>

        </div>


    <?php endif; ?>



    <div class="legend">


        <div class="legend-item">

            <div class="legend-seat"></div>

            Available

        </div>


        <div class="legend-item">

            <div class="legend-seat vip"></div>

            VIP

        </div>


        <div class="legend-item">

            <div class="legend-seat selected"></div>

            Selected

        </div>


        <div class="legend-item">

            <div class="legend-seat booked"></div>

            Booked

        </div>


    </div>


</main>



<!-- =====================================================
     BOTTOM BAR
====================================================== -->

<div class="bottom-bar">


    <div class="bottom-inner">


        <div class="selection-info">


            <span class="small">

                Selected Seats

            </span>


            <span class="selected-count">

                <span id="selectedCount">
                    0
                </span>

                seat(s)

                &nbsp; • &nbsp;

                <span class="total">

                    ₹<span id="totalPrice">
                        0.00
                    </span>

                </span>

            </span>


        </div>



        <!-- IMPORTANT:
             This button is handled by JavaScript.
        -->

        <button
            type="button"
            class="continue-btn"
            id="continueBtn"
            disabled
        >

            Continue

        </button>


    </div>

</div>



<script>

/*
|--------------------------------------------------------------------------
| TICKETFLIX SEAT SELECTION JAVASCRIPT
|--------------------------------------------------------------------------
*/


document.addEventListener(
    "DOMContentLoaded",
    function () {


        /*
        |--------------------------------------------------------------------------
        | GET ALL AVAILABLE SEATS
        |--------------------------------------------------------------------------
        */

        const seats =
            document.querySelectorAll(
                ".seat:not(.booked)"
            );


        /*
        |--------------------------------------------------------------------------
        | GET UI ELEMENTS
        |--------------------------------------------------------------------------
        */

        const selectedCount =
            document.getElementById(
                "selectedCount"
            );


        const totalPrice =
            document.getElementById(
                "totalPrice"
            );


        const continueBtn =
            document.getElementById(
                "continueBtn"
            );


        /*
        |--------------------------------------------------------------------------
        | STORE SELECTED SEATS
        |--------------------------------------------------------------------------
        */

        let selectedSeats = [];


        /*
        |--------------------------------------------------------------------------
        | SEAT CLICK
        |--------------------------------------------------------------------------
        */

        seats.forEach(
            function (seat) {


                seat.addEventListener(
                    "click",
                    function () {


                        /*
                        | Get seat ID
                        */

                        const seatId =
                            String(
                                this.dataset.seatId
                            );


                        const seatRow =
                            this.dataset.seatRow;


                        const seatNumber =
                            this.dataset.seatNumber;


                        const seatType =
                            this.dataset.seatType;


                        const price =
                            parseFloat(
                                this.dataset.price
                            ) || 0;


                        /*
                        | Check if already selected
                        */

                        const existingIndex =
                            selectedSeats.findIndex(
                                function (item) {

                                    return (
                                        item.id ===
                                        seatId
                                    );

                                }
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | UNSELECT SEAT
                        |--------------------------------------------------------------------------
                        */

                        if (
                            existingIndex !== -1
                        ) {


                            selectedSeats.splice(
                                existingIndex,
                                1
                            );


                            this.classList.remove(
                                "selected"
                            );


                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SELECT SEAT
                        |--------------------------------------------------------------------------
                        */

                        else {


                            selectedSeats.push({

                                id:
                                    seatId,

                                row:
                                    seatRow,

                                number:
                                    seatNumber,

                                type:
                                    seatType,

                                price:
                                    price

                            });


                            this.classList.add(
                                "selected"
                            );


                        }


                        /*
                        | Update bottom bar
                        */

                        updateSelection();


                    }
                );


            }
        );


        /*
        |--------------------------------------------------------------------------
        | UPDATE SELECTION
        |--------------------------------------------------------------------------
        */

        function updateSelection() {


            /*
            | Number of selected seats
            */

            selectedCount.textContent =
                selectedSeats.length;


            /*
            | Calculate total
            */

            let total = 0;


            selectedSeats.forEach(
                function (seat) {

                    total +=
                        parseFloat(
                            seat.price
                        ) || 0;

                }
            );


            /*
            | Display total
            */

            totalPrice.textContent =
                total.toFixed(2);


            /*
            | Enable / Disable continue button
            */

            continueBtn.disabled =
                selectedSeats.length === 0;


        }


        /*
        |--------------------------------------------------------------------------
        | CONTINUE BUTTON
        |--------------------------------------------------------------------------
        */

        continueBtn.addEventListener(
            "click",
            function () {


                /*
                | No seat selected
                */

                if (
                    selectedSeats.length === 0
                ) {

                    alert(
                        "Please select at least one seat."
                    );

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | CREATE FORM
                |--------------------------------------------------------------------------
                */

                const form =
                    document.createElement(
                        "form"
                    );


                form.method =
                    "POST";


                form.action =
                    "booking.php";


                /*
                |--------------------------------------------------------------------------
                | SHOWTIME ID
                |--------------------------------------------------------------------------
                */

                const showtimeInput =
                    document.createElement(
                        "input"
                    );


                showtimeInput.type =
                    "hidden";


                showtimeInput.name =
                    "showtime_id";


                showtimeInput.value =
                    "<?=
                        (int)$showtime_id;
                    ?>";


                form.appendChild(
                    showtimeInput
                );


                /*
                |--------------------------------------------------------------------------
                | SELECTED SEAT IDS
                |--------------------------------------------------------------------------
                */

                selectedSeats.forEach(
                    function (seat) {


                        const seatInput =
                            document.createElement(
                                "input"
                            );


                        seatInput.type =
                            "hidden";


                        seatInput.name =
                            "seat_ids[]";


                        seatInput.value =
                            seat.id;


                        form.appendChild(
                            seatInput
                        );


                    }
                );


                /*
                |--------------------------------------------------------------------------
                | ADD FORM TO PAGE
                |--------------------------------------------------------------------------
                */

                document.body.appendChild(
                    form
                );


                /*
                |--------------------------------------------------------------------------
                | SUBMIT TO booking.php
                |--------------------------------------------------------------------------
                */

                form.submit();


            }
        );


    }

);

</script>


</body>

</html>