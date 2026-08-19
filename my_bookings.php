<?php

/*
|--------------------------------------------------------------------------
| SESSION + DATABASE
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$user_id = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| GET USER BOOKINGS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| bookings table mein created_at use nahi kiya gaya hai.
|
*/

$sql = "
    SELECT
        b.id AS booking_id,
        b.booking_reference,
        b.total_amount,
        b.booking_status,
        b.payment_status,

        s.id AS showtime_id,
        s.show_date,
        s.show_time,
        s.price AS base_price,

        m.name AS movie_name,

        sc.screen_name,

        t.name AS theater_name,
        t.address AS theater_address,
        t.city AS theater_city

    FROM bookings b

    INNER JOIN showtimes s
        ON b.showtime_id = s.id

    LEFT JOIN movies m
        ON s.movie_id = m.id

    LEFT JOIN screens sc
        ON s.screen_id = sc.id

    LEFT JOIN theaters t
        ON sc.theater_id = t.id

    WHERE b.user_id = ?

    ORDER BY b.id DESC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Booking query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();


$bookings = [];


while ($row = $result->fetch_assoc()) {

    $bookings[] = $row;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| FUNCTION - SEAT PRICE
|--------------------------------------------------------------------------
*/

function calculateSeatPrice($seat_type, $base_price)
{

    $seat_type = strtolower(
        trim((string) $seat_type)
    );


    if ($seat_type === 'vip') {

        return (float) $base_price * 2;

    }


    if ($seat_type === 'recliner') {

        return (float) $base_price * 1.5;

    }


    return (float) $base_price;

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

    <title>My Bookings | TicketFlix</title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #ffffff;

            background:

                radial-gradient(
                    circle at top right,
                    rgba(126, 87, 194, 0.25),
                    transparent 35%
                ),

                radial-gradient(
                    circle at bottom left,
                    rgba(255, 49, 95, 0.10),
                    transparent 35%
                ),

                #100b18;

            padding-bottom: 60px;
        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {

            min-height: 75px;

            padding: 15px 6%;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            background:
                rgba(18, 12, 28, 0.97);

            border-bottom:
                1px solid rgba(255,255,255,0.08);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .logo {

            font-size: 27px;

            font-weight: 800;

            white-space: nowrap;
        }


        .logo i {

            color: #ff315f;

            margin-right: 6px;
        }


        .logo span {

            color: #ffffff;
        }


        .logo span span {

            color: #ff315f;
        }


        .nav-right {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

            justify-content: flex-end;
        }


        .nav-btn {

            color: #ffffff;

            text-decoration: none;

            padding: 9px 16px;

            border-radius: 22px;

            border:
                1px solid rgba(255,255,255,0.15);

            background:
                rgba(255,255,255,0.05);

            transition: 0.3s;

            font-size: 14px;
        }


        .nav-btn:hover {

            background: #ff315f;

            border-color: #ff315f;
        }


        /* =====================================================
           CONTAINER
        ===================================================== */

        .container {

            width: min(1050px, 92%);

            margin: auto;

            padding: 45px 0 70px;
        }


        .page-title {

            text-align: center;

            margin-bottom: 10px;

            font-size: 34px;

            font-weight: 800;
        }


        .page-title span {

            color: #ff315f;
        }


        .subtitle {

            text-align: center;

            color: #aaa4b0;

            margin-bottom: 35px;

            font-size: 15px;
        }


        /* =====================================================
           EMPTY BOOKINGS
        ===================================================== */

        .empty {

            text-align: center;

            padding: 70px 20px;

            border-radius: 25px;

            background:
                rgba(255,255,255,0.05);

            border:
                1px solid rgba(255,255,255,0.08);
        }


        .empty-icon {

            font-size: 55px;

            color: #ff315f;

            margin-bottom: 20px;
        }


        .empty h2 {

            margin-bottom: 10px;
        }


        .empty p {

            color: #aaa4b0;

            margin-bottom: 25px;
        }


        .book-btn {

            display: inline-block;

            text-decoration: none;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #ff315f,
                    #d91647
                );

            padding: 13px 25px;

            border-radius: 28px;

            font-weight: 700;
        }


        /* =====================================================
           BOOKING CARD
        ===================================================== */

        .booking-card {

            background:
                rgba(255,255,255,0.055);

            border:
                1px solid rgba(255,255,255,0.09);

            border-radius: 25px;

            margin-bottom: 25px;

            overflow: hidden;

            box-shadow:
                0 20px 50px rgba(0,0,0,0.25);
        }


        .booking-top {

            padding: 22px 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            flex-wrap: wrap;

            border-bottom:
                1px solid rgba(255,255,255,0.08);
        }


        .booking-reference small {

            display: block;

            color: #8f8997;

            font-size: 11px;

            margin-bottom: 5px;

            text-transform: uppercase;

            letter-spacing: 1px;
        }


        .booking-reference strong {

            color: #d4af37;

            font-size: 18px;

            letter-spacing: 1px;
        }


        .status {

            padding: 7px 14px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;
        }


        .status.confirmed {

            background:
                rgba(46, 204, 113, 0.12);

            border:
                1px solid rgba(46, 204, 113, 0.35);

            color: #5ee58c;
        }


        .status.pending {

            background:
                rgba(241, 196, 15, 0.12);

            border:
                1px solid rgba(241, 196, 15, 0.35);

            color: #f5d45d;
        }


        .status.cancelled {

            background:
                rgba(255, 49, 95, 0.12);

            border:
                1px solid rgba(255, 49, 95, 0.35);

            color: #ff6b89;
        }


        /* =====================================================
           BOOKING BODY
        ===================================================== */

        .booking-body {

            padding: 25px;
        }


        .movie-title {

            font-size: 25px;

            font-weight: 800;

            margin-bottom: 15px;
        }


        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 12px;

            margin-bottom: 22px;
        }


        .detail {

            padding: 13px 15px;

            border-radius: 12px;

            background:
                rgba(255,255,255,0.045);

            border:
                1px solid rgba(255,255,255,0.06);
        }


        .detail-label {

            display: block;

            color: #85808b;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.7px;

            margin-bottom: 5px;
        }


        .detail-value {

            color: #eeeeee;

            font-size: 14px;

            font-weight: 600;
        }


        /* =====================================================
           SEATS
        ===================================================== */

        .seat-section {

            margin-top: 20px;
        }


        .seat-title {

            color: #d4af37;

            font-weight: 700;

            margin-bottom: 12px;
        }


        .seats-list {

            display: flex;

            flex-wrap: wrap;

            gap: 9px;
        }


        .seat-tag {

            padding: 8px 13px;

            border-radius: 10px;

            background:
                rgba(255,49,95,0.10);

            border:
                1px solid rgba(255,49,95,0.25);

            color: #ff7b98;

            font-size: 13px;

            font-weight: 700;
        }


        .seat-tag.vip {

            background:
                rgba(212,175,55,0.10);

            border-color:
                rgba(212,175,55,0.3);

            color: #f1d46a;
        }


        .seat-tag.recliner {

            background:
                rgba(126,87,194,0.12);

            border-color:
                rgba(126,87,194,0.35);

            color: #c5a6ff;
        }


        /* =====================================================
           FOOTER PRICE
        ===================================================== */

        .booking-bottom {

            padding: 18px 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-wrap: wrap;

            border-top:
                1px solid rgba(255,255,255,0.08);
        }


        .booking-status-info {

            color: #85808b;

            font-size: 12px;
        }


        .total-price {

            color: #d4af37;

            font-size: 21px;

            font-weight: 800;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 650px) {

            .navbar {

                padding: 15px 4%;

                align-items: flex-start;

                flex-direction: column;
            }


            .nav-right {

                width: 100%;

                justify-content: flex-start;
            }


            .container {

                width: 94%;

                padding-top: 30px;
            }


            .page-title {

                font-size: 28px;
            }


            .details-grid {

                grid-template-columns: 1fr;
            }


            .booking-body {

                padding: 20px;
            }


            .booking-top {

                padding: 18px 20px;
            }


            .booking-bottom {

                padding: 16px 20px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="navbar">


    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        <span>
            Ticket<span>Flix</span>
        </span>

    </div>


    <div class="nav-right">


        <a
            href="index.php"
            class="nav-btn"
        >

            <i class="fa-solid fa-house"></i>

            Home

        </a>


        <a
            href="showtimes.php"
            class="nav-btn"
        >

            <i class="fa-solid fa-clock"></i>

            Showtimes

        </a>


        <a
            href="logout.php"
            class="nav-btn"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>


    </div>

</header>



<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


    <h1 class="page-title">

        My <span>Bookings</span>

    </h1>


    <p class="subtitle">

        View all your TicketFlix movie bookings

    </p>



    <?php if (empty($bookings)): ?>


        <!-- =================================================
             NO BOOKINGS
        ================================================= -->

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
                href="showtimes.php"
                class="book-btn"
            >

                <i class="fa-solid fa-film"></i>

                Book a Movie

            </a>


        </div>


    <?php else: ?>


        <?php foreach ($bookings as $booking): ?>


            <?php

            /*
            |--------------------------------------------------------------------------
            | GET SEATS FOR THIS BOOKING
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | seats table ke actual columns:
            |
            | id
            | screen_id
            | seat_row
            | seat_number
            | seat_type
            | is_active
            |
            */

            $seat_sql = "
                SELECT
                    s.id,
                    s.seat_row,
                    s.seat_number,
                    s.seat_type,
                    s.is_active

                FROM booking_seats bs

                INNER JOIN seats s
                    ON bs.seat_id = s.id

                WHERE bs.booking_id = ?

                ORDER BY
                    s.seat_row ASC,
                    s.seat_number ASC
            ";


            $seat_stmt =
                $conn->prepare($seat_sql);


            $booking_seats = [];


            if ($seat_stmt) {

                $seat_stmt->bind_param(
                    "i",
                    $booking['booking_id']
                );


                $seat_stmt->execute();


                $seat_result =
                    $seat_stmt->get_result();


                while (
                    $seat =
                    $seat_result->fetch_assoc()
                ) {

                    $booking_seats[] = $seat;

                }


                $seat_stmt->close();

            }


            /*
            |--------------------------------------------------------------------------
            | STATUS CLASS
            |--------------------------------------------------------------------------
            */

            $status =
                strtolower(
                    trim(
                        (string)
                        $booking['booking_status']
                    )
                );


            if ($status === '') {

                $status = 'confirmed';

            }

            ?>


            <!-- =================================================
                 BOOKING CARD
            ================================================= -->

            <div class="booking-card">


                <!-- TOP -->

                <div class="booking-top">


                    <div class="booking-reference">


                        <small>
                            Booking Reference
                        </small>


                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking['booking_reference']
                            );

                            ?>

                        </strong>


                    </div>



                    <div
                        class="status <?php echo htmlspecialchars($status); ?>"
                    >

                        <?php

                        echo htmlspecialchars(
                            ucfirst($status)
                        );

                        ?>

                    </div>


                </div>



                <!-- BODY -->

                <div class="booking-body">


                    <!-- MOVIE -->

                    <div class="movie-title">

                        <i class="fa-solid fa-film"></i>

                        <?php

                        echo htmlspecialchars(
                            $booking['movie_name']
                            ?: 'Movie'
                        );

                        ?>

                    </div>



                    <!-- DETAILS -->

                    <div class="details-grid">


                        <div class="detail">


                            <span class="detail-label">

                                Date

                            </span>


                            <span class="detail-value">

                                <i class="fa-regular fa-calendar"></i>

                                <?php

                                if (
                                    !empty(
                                        $booking['show_date']
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $booking['show_date']
                                            )
                                        )
                                    );

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </span>


                        </div>



                        <div class="detail">


                            <span class="detail-label">

                                Time

                            </span>


                            <span class="detail-value">

                                <i class="fa-regular fa-clock"></i>

                                <?php

                                if (
                                    !empty(
                                        $booking['show_time']
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        date(
                                            "h:i A",
                                            strtotime(
                                                $booking['show_time']
                                            )
                                        )
                                    );

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </span>


                        </div>



                        <div class="detail">


                            <span class="detail-label">

                                Screen

                            </span>


                            <span class="detail-value">

                                <i class="fa-solid fa-tv"></i>

                                <?php

                                echo htmlspecialchars(
                                    $booking['screen_name']
                                    ?: 'Screen'
                                );

                                ?>

                            </span>


                        </div>



                        <div class="detail">


                            <span class="detail-label">

                                Theater

                            </span>


                            <span class="detail-value">

                                <i class="fa-solid fa-location-dot"></i>

                                <?php

                                echo htmlspecialchars(
                                    $booking['theater_name']
                                    ?: 'Theater'
                                );

                                ?>

                            </span>


                        </div>


                    </div>



                    <!-- SEATS -->

                    <div class="seat-section">


                        <div class="seat-title">

                            <i class="fa-solid fa-couch"></i>

                            Selected Seats

                        </div>


                        <div class="seats-list">


                            <?php if (empty($booking_seats)): ?>


                                <span
                                    style="color:#888;"
                                >

                                    No seat information found.

                                </span>


                            <?php else: ?>


                                <?php foreach (
                                    $booking_seats
                                    as $seat
                                ): ?>


                                    <?php

                                    $seat_type =
                                        strtolower(
                                            trim(
                                                (string)
                                                $seat['seat_type']
                                            )
                                        );


                                    $seat_price =
                                        calculateSeatPrice(
                                            $seat_type,
                                            (float)
                                            $booking['base_price']
                                        );


                                    if ($seat_type === 'vip') {

                                        $seat_class =
                                            'seat-tag vip';

                                    } elseif (
                                        $seat_type === 'recliner'
                                    ) {

                                        $seat_class =
                                            'seat-tag recliner';

                                    } else {

                                        $seat_class =
                                            'seat-tag';

                                    }

                                    ?>


                                    <div
                                        class="<?php echo $seat_class; ?>"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            strtoupper(
                                                $seat['seat_row']
                                            )
                                        );

                                        echo (int)
                                            $seat['seat_number'];

                                        ?>

                                        ·

                                        ₹<?php

                                        echo number_format(
                                            $seat_price,
                                            2
                                        );

                                        ?>

                                    </div>


                                <?php endforeach; ?>


                            <?php endif; ?>


                        </div>


                    </div>


                </div>



                <!-- BOTTOM -->

                <div class="booking-bottom">


                    <div class="booking-status-info">

                        <i class="fa-solid fa-circle-check"></i>

                        Payment:

                        <?php

                        echo htmlspecialchars(
                            ucfirst(
                                (string)
                                $booking['payment_status']
                            )
                        );

                        ?>

                    </div>



                    <div class="total-price">

                        Total:

                        ₹<?php

                        echo number_format(
                            (float)
                            $booking['total_amount'],
                            2
                        );

                        ?>

                    </div>


                </div>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</main>


</body>

</html>