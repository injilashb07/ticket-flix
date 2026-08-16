<?php
session_start();

require_once 'config.php';


/* =========================================================
   1. CHECK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {
    die("User session not found. Please login again.");
}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   2. CHECK POST DATA
========================================================= */

if (
    !isset($_POST['showtime_id']) ||
    !isset($_POST['seat_ids']) ||
    !is_numeric($_POST['showtime_id']) ||
    !is_array($_POST['seat_ids'])
) {

    header("Location: showtimes.php");
    exit();

}


$showtime_id = (int) $_POST['showtime_id'];

$seat_ids = array_map(
    'intval',
    $_POST['seat_ids']
);


/* Remove duplicate seat IDs */

$seat_ids = array_unique($seat_ids);


/* Remove invalid IDs */

$seat_ids = array_filter(
    $seat_ids,
    function ($id) {
        return $id > 0;
    }
);


if (empty($seat_ids)) {

    header(
        "Location: seats.php?showtime_id=" .
        $showtime_id
    );

    exit();

}


/* =========================================================
   3. GET SHOWTIME
========================================================= */

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
    die("Showtime query error: " . $conn->error);
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


/* =========================================================
   4. GET MOVIE
========================================================= */

$movie_name = "Movie";

$sql = "
    SELECT *
    FROM movies
    WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Movie query error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $showtime['movie_id']
);

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


/* =========================================================
   5. GET SCREEN
========================================================= */

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

$stmt->bind_param(
    "i",
    $showtime['screen_id']
);

$stmt->execute();

$result = $stmt->get_result();

$screen = $result->fetch_assoc();

$stmt->close();


if (!$screen) {

    die("Screen not found.");

}


/* =========================================================
   6. GET THEATER
========================================================= */

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

$stmt->bind_param(
    "i",
    $screen['theater_id']
);

$stmt->execute();

$result = $stmt->get_result();

$theater = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   7. GET SELECTED SEATS
========================================================= */

$placeholders = implode(
    ',',
    array_fill(0, count($seat_ids), '?')
);

$sql = "
    SELECT
        `id`,
        `screen_id`,
        `row_number`,
        `seat_number`,
        `seat_type`,
        `is_active`
    FROM `seats`
    WHERE `id` IN ($placeholders)
      AND `screen_id` = ?
      AND `is_active` = 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Seat query error: " . $conn->error);
}


/* ---------------------------------------------------------
   Bind seat IDs + screen ID
--------------------------------------------------------- */

$params = $seat_ids;

$params[] = (int)$showtime['screen_id'];

$types = str_repeat('i', count($seat_ids)) . 'i';

$bind_params = [];

$bind_params[] = $types;

foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}

call_user_func_array(
    [$stmt, 'bind_param'],
    $bind_params
);


/* ---------------------------------------------------------
   Execute
--------------------------------------------------------- */

$stmt->execute();

$result = $stmt->get_result();

$selected_seats = [];

while ($seat = $result->fetch_assoc()) {
    $selected_seats[] = $seat;
}

$stmt->close();


/* =========================================================
   8. VALIDATE SEAT COUNT
========================================================= */

if (
    count($selected_seats)
    !== count($seat_ids)
) {

    die(
        "One or more selected seats are not available."
    );

}


/* =========================================================
   9. CHECK ALREADY BOOKED SEATS
========================================================= */


/*
   Check whether selected seats are already booked
   for this particular showtime.
*/

$booked_check_query = "
    SELECT
        bs.seat_id

    FROM booking_seats bs

    INNER JOIN bookings b
        ON bs.booking_id = b.id

    WHERE b.showtime_id = ?

    AND bs.seat_id IN ($placeholders)

    AND (
        b.booking_status = 'pending'
        OR b.booking_status = 'confirmed'
    )
";


$stmt = $conn->prepare(
    $booked_check_query
);

if (!$stmt) {
    die(
        "Booking check error: " .
        $conn->error
    );
}


/*
   showtime_id + seat IDs
*/

$params2 = [];

$params2[] =
    $showtime_id;


foreach ($seat_ids as $id) {

    $params2[] = $id;

}


$types2 =
    'i' .
    str_repeat(
        'i',
        count($seat_ids)
    );


$bind_params2 = [];

$bind_params2[] = $types2;


foreach ($params2 as $key => $value) {

    $bind_params2[] = &$params2[$key];

}


call_user_func_array(
    [$stmt, 'bind_param'],
    $bind_params2
);


$stmt->execute();

$result = $stmt->get_result();


$already_booked = [];


while ($row = $result->fetch_assoc()) {

    $already_booked[] =
        (int) $row['seat_id'];

}


$stmt->close();


if (!empty($already_booked)) {

    die(
        "Sorry! One or more selected seats have just been booked. Please go back and select different seats."
    );

}


/* =========================================================
   10. CALCULATE SEAT PRICES
========================================================= */

$base_price =
    (float) $showtime['price'];


function calculateSeatPrice(
    $seat_type,
    $base_price
) {

    $seat_type =
        strtolower(
            trim($seat_type)
        );


    if ($seat_type === 'vip') {

        return $base_price * 2;

    }


    if ($seat_type === 'recliner') {

        return $base_price * 1.5;

    }


    return $base_price;

}


$total_amount = 0;


foreach (
    $selected_seats
    as &$seat
) {

    $seat['calculated_price'] =
        calculateSeatPrice(
            $seat['seat_type'],
            $base_price
        );


    $total_amount +=
        $seat['calculated_price'];

}

unset($seat);


/* =========================================================
   11. CREATE BOOKING REFERENCE
========================================================= */

$booking_reference =
    'TF' .
    date('ymd') .
    strtoupper(
        substr(
            uniqid(),
            -6
        )
    );


/* =========================================================
   12. CONFIRM BOOKING
========================================================= */

if (
    isset($_POST['confirm_booking']) &&
    $_POST['confirm_booking'] === '1'
) {


    /*
       START TRANSACTION
    */

    $conn->begin_transaction();


    try {


        /* -------------------------------------------------
           INSERT BOOKING
        ------------------------------------------------- */

        $booking_status =
            'confirmed';

        $payment_status =
            'completed';


        $insert_booking = "
            INSERT INTO bookings
            (
                user_id,
                showtime_id,
                total_amount,
                booking_status,
                payment_status,
                booking_reference
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";


        $stmt =
            $conn->prepare(
                $insert_booking
            );


        if (!$stmt) {

            throw new Exception(
                "Booking insert error: " .
                $conn->error
            );

        }


        $stmt->bind_param(
            "iidsss",
            $user_id,
            $showtime_id,
            $total_amount,
            $booking_status,
            $payment_status,
            $booking_reference
        );


        $stmt->execute();


        $booking_id =
            $conn->insert_id;


        $stmt->close();


        /* -------------------------------------------------
           INSERT BOOKING SEATS
        ------------------------------------------------- */

        $insert_seat = "
            INSERT INTO booking_seats
            (
                booking_id,
                seat_id,
                seat_price
            )

            VALUES
            (
                ?,
                ?,
                ?
            )
        ";


        $seat_stmt =
            $conn->prepare(
                $insert_seat
            );


        if (!$seat_stmt) {

            throw new Exception(
                "Booking seat error: " .
                $conn->error
            );

        }


        foreach (
            $selected_seats
            as $seat
        ) {


            $seat_id =
                (int) $seat['id'];


            $seat_price =
                (float)
                $seat['calculated_price'];


            $seat_stmt->bind_param(
                "iid",
                $booking_id,
                $seat_id,
                $seat_price
            );


            $seat_stmt->execute();

        }


        $seat_stmt->close();


        /* -------------------------------------------------
           UPDATE AVAILABLE SEATS
        ------------------------------------------------- */

        $seat_count =
            count($selected_seats);


        $update_showtime = "
            UPDATE showtimes

            SET available_seats =
                GREATEST(
                    available_seats - ?,
                    0
                )

            WHERE id = ?
        ";


        $update_stmt =
            $conn->prepare(
                $update_showtime
            );


        if (!$update_stmt) {

            throw new Exception(
                "Showtime update error: " .
                $conn->error
            );

        }


        $update_stmt->bind_param(
            "ii",
            $seat_count,
            $showtime_id
        );


        $update_stmt->execute();

        $update_stmt->close();


        /* -------------------------------------------------
           COMMIT
        ------------------------------------------------- */

        $conn->commit();


        /* -------------------------------------------------
           SUCCESS PAGE
        ------------------------------------------------- */

        ?>

        <!DOCTYPE html>

        <html lang="en">

        <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Booking Confirmed | TicketFlix</title>


        <link
            href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        >


        <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            font-family: Poppins, sans-serif;

            background:
                radial-gradient(
                    circle at top right,
                    rgba(126,87,194,.25),
                    transparent 35%
                ),
                #100b18;

            color: white;

            padding: 25px;
        }


        .success-card {

            width: 100%;

            max-width: 650px;

            text-align: center;

            padding: 50px 35px;

            border-radius: 30px;

            background:
                rgba(255,255,255,.06);

            border:
                1px solid rgba(212,175,55,.25);

            box-shadow:
                0 25px 70px
                rgba(0,0,0,.45);
        }


        .success-icon {

            width: 90px;

            height: 90px;

            margin: auto;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #d4af37;

            color: #171020;

            font-size: 45px;

            margin-bottom: 25px;
        }


        h1 {

            font-size: 32px;

            margin-bottom: 10px;
        }


        .gold {

            color: #d4af37;
        }


        .reference {

            margin: 25px auto;

            padding: 18px;

            border-radius: 15px;

            background:
                rgba(212,175,55,.10);

            border:
                1px dashed #d4af37;
        }


        .reference small {

            display: block;

            color: #aaa;

            margin-bottom: 5px;
        }


        .reference strong {

            font-size: 23px;

            color: #d4af37;

            letter-spacing: 2px;
        }


        .details {

            color: #ccc;

            line-height: 2;

            margin-bottom: 25px;
        }


        .buttons {

            display: flex;

            gap: 12px;

            justify-content: center;

            flex-wrap: wrap;
        }


        .btn {

            text-decoration: none;

            padding: 12px 23px;

            border-radius: 25px;

            font-weight: 600;

            transition: .3s;
        }


        .primary {

            background: #d4af37;

            color: #171020;
        }


        .secondary {

            border:
                1px solid rgba(255,255,255,.2);

            color: white;
        }


        .btn:hover {

            transform:
                translateY(-2px);
        }

        </style>

        </head>


        <body>


        <div class="success-card">


            <div class="success-icon">

                ✓

            </div>


            <h1>

                Booking Confirmed! 🎉

            </h1>


            <p>

                Your TicketFlix movie tickets
                have been successfully booked.

            </p>


            <div class="reference">

                <small>
                    Booking Reference
                </small>

                <strong>

                    <?= htmlspecialchars(
                        $booking_reference
                    ); ?>

                </strong>

            </div>


            <div class="details">

                🎬
                <?= htmlspecialchars(
                    $movie_name
                ); ?>

                <br>


                📅
                <?= date(
                    "d M Y",
                    strtotime(
                        $showtime['show_date']
                    )
                ); ?>

                &nbsp; | &nbsp;

                🕐
                <?= date(
                    "h:i A",
                    strtotime(
                        $showtime['show_time']
                    )
                ); ?>

                <br>


                💺
                <?= count(
                    $selected_seats
                ); ?>

                Seat(s)


                <br>


                💰
                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </div>


            <div class="buttons">


                <a
                    href="my_bookings.php"
                    class="btn primary"
                >

                    My Bookings

                </a>


                <a
                    href="showtimes.php"
                    class="btn secondary"
                >

                    Book Another Movie

                </a>


            </div>


        </div>


        </body>

        </html>

        <?php

        exit();


    }

    catch (Exception $e) {


        $conn->rollback();


        die(
            "Booking failed: " .
            htmlspecialchars(
                $e->getMessage()
            )
        );

    }

}


/* =========================================================
   13. DISPLAY BOOKING CONFIRMATION PAGE
========================================================= */

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Confirm Booking | TicketFlix</title>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
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

    min-height: 100vh;

    font-family: Poppins, sans-serif;

    background:

        radial-gradient(
            circle at top right,
            rgba(126,87,194,.25),
            transparent 35%
        ),

        radial-gradient(
            circle at bottom left,
            rgba(255,193,7,.10),
            transparent 35%
        ),

        #100b18;

    color: white;

    padding-bottom: 50px;
}


/* ==================================================
   NAVBAR
================================================== */

.navbar {

    height: 75px;

    padding: 0 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(18,12,28,.97);

    border-bottom:
        1px solid rgba(212,175,55,.20);
}


.logo {

    font-size: 27px;

    font-weight: 800;
}


.logo span {

    color: #d4af37;
}


.back {

    color: white;

    text-decoration: none;

    border:
        1px solid rgba(255,255,255,.2);

    padding: 9px 18px;

    border-radius: 25px;
}


/* ==================================================
   CONTAINER
================================================== */

.container {

    width: 92%;

    max-width: 900px;

    margin: 40px auto;
}


/* ==================================================
   HEADER
================================================== */

.page-title {

    text-align: center;

    margin-bottom: 30px;
}


.page-title h1 {

    font-size: 32px;

    margin-bottom: 8px;
}


.gold {

    color: #d4af37;
}


.page-title p {

    color: #aaa;
}


/* ==================================================
   CARD
================================================== */

.card {

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(212,175,55,.18);

    border-radius: 25px;

    padding: 30px;

    margin-bottom: 20px;

    backdrop-filter: blur(15px);
}


.card-title {

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 20px;

    color: #d4af37;
}


/* ==================================================
   MOVIE INFO
================================================== */

.movie-box {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    flex-wrap: wrap;
}


.movie-name {

    font-size: 25px;

    font-weight: 700;

    margin-bottom: 12px;
}


.info {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}


.info span {

    padding: 7px 13px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.07);

    color: #ccc;

    font-size: 13px;
}


/* ==================================================
   SEATS
================================================== */

.seats-list {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}


.seat-tag {

    padding: 8px 14px;

    border-radius: 10px;

    background:
        rgba(212,175,55,.12);

    border:
        1px solid rgba(212,175,55,.35);

    color: #f1d46a;

    font-size: 13px;
}


/* ==================================================
   PRICE
================================================== */

.price-row {

    display: flex;

    justify-content: space-between;

    padding: 12px 0;

    border-bottom:
        1px solid rgba(255,255,255,.08);

    color: #ccc;
}


.price-row:last-child {

    border-bottom: none;
}


.price-row.total {

    margin-top: 10px;

    padding-top: 18px;

    font-size: 21px;

    font-weight: 700;

    color: white;
}


.price-row.total span:last-child {

    color: #d4af37;
}


/* ==================================================
   CONFIRM BUTTON
================================================== */

.confirm-btn {

    width: 100%;

    border: none;

    padding: 16px;

    border-radius: 30px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1d46a
        );

    color: #171020;

    font-size: 16px;

    font-weight: 800;

    cursor: pointer;

    transition: .3s;

    box-shadow:
        0 10px 30px
        rgba(212,175,55,.25);
}


.confirm-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 15px 35px
        rgba(212,175,55,.40);
}


.note {

    text-align: center;

    color: #888;

    font-size: 12px;

    margin-top: 15px;
}


@media(max-width:600px) {

    .container {

        width: 94%;

        margin-top: 25px;
    }


    .card {

        padding: 22px;
    }


    .page-title h1 {

        font-size: 26px;
    }

}

</style>

</head>


<body>


<nav class="navbar">


    <div class="logo">

        Ticket<span>Flix</span> 🎬

    </div>


    <a
        href="seats.php?showtime_id=<?= $showtime_id; ?>"
        class="back"
    >

        ← Back

    </a>


</nav>



<div class="container">


    <div class="page-title">

        <h1>

            Confirm Your
            <span class="gold">
                Booking
            </span>

        </h1>

        <p>

            Review your tickets before confirming.

        </p>

    </div>



    <!-- MOVIE -->

    <div class="card">


        <div class="card-title">

            🎬 Movie Details

        </div>


        <div class="movie-box">


            <div>


                <div class="movie-name">

                    <?= htmlspecialchars(
                        $movie_name
                    ); ?>

                </div>


                <div class="info">


                    <span>

                        📅
                        <?= date(
                            "d M Y",
                            strtotime(
                                $showtime['show_date']
                            )
                        ); ?>

                    </span>


                    <span>

                        🕐
                        <?= date(
                            "h:i A",
                            strtotime(
                                $showtime['show_time']
                            )
                        ); ?>

                    </span>


                    <span>

                        🎭
                        <?= htmlspecialchars(
                            $screen['screen_name']
                        ); ?>

                    </span>


                    <?php if ($theater): ?>

                        <span>

                            📍
                            <?= htmlspecialchars(
                                $theater['name']
                            ); ?>

                        </span>

                    <?php endif; ?>


                </div>


            </div>


        </div>


    </div>



    <!-- SEATS -->

    <div class="card">


        <div class="card-title">

            💺 Selected Seats

        </div>


        <div class="seats-list">


            <?php foreach (
                $selected_seats
                as $seat
            ): ?>


                <div class="seat-tag">

                    <?= htmlspecialchars(
                        $seat['row_number'] .
                        $seat['seat_number']
                    ); ?>

                    ·

                    ₹<?= number_format(
                        $seat['calculated_price'],
                        2
                    ); ?>

                </div>


            <?php endforeach; ?>


        </div>


    </div>



    <!-- PRICE -->

    <div class="card">


        <div class="card-title">

            💰 Price Summary

        </div>


        <?php foreach (
            $selected_seats
            as $seat
        ): ?>


            <div class="price-row">


                <span>

                    Seat
                    <?= htmlspecialchars(
                        $seat['row_number'] .
                        $seat['seat_number']
                    ); ?>

                    (<?= htmlspecialchars(
                        ucfirst(
                            $seat['seat_type']
                        )
                    ); ?>)

                </span>


                <span>

                    ₹<?= number_format(
                        $seat['calculated_price'],
                        2
                    ); ?>

                </span>


            </div>


        <?php endforeach; ?>


        <div class="price-row total">


            <span>

                Total Amount

            </span>


            <span>

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </span>


        </div>


    </div>



    <!-- CONFIRM -->

    <div class="card">


        <form
            method="POST"
            action="booking.php"
        >


            <input
                type="hidden"
                name="showtime_id"
                value="<?= $showtime_id; ?>"
            >


            <?php foreach (
                $seat_ids
                as $seat_id
            ): ?>


                <input
                    type="hidden"
                    name="seat_ids[]"
                    value="<?= (int) $seat_id; ?>"
                >


            <?php endforeach; ?>


            <input
                type="hidden"
                name="confirm_booking"
                value="1"
            >


            <button
                type="submit"
                class="confirm-btn"
            >

                🎟️ Confirm Booking →
                
            </button>


            <div class="note">

                By confirming, your selected seats
                will be reserved for this show.

            </div>


        </form>


    </div>


</div>


</body>

</html>