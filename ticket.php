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
| GET BOOKING
|--------------------------------------------------------------------------
*/

$booking_reference = '';

if (isset($_GET['reference'])) {

    $booking_reference = trim(
        (string) $_GET['reference']
    );

}


/*
|--------------------------------------------------------------------------
| IF NO REFERENCE
|--------------------------------------------------------------------------
*/

if ($booking_reference === '') {

    die("Booking reference not found.");

}


/*
|--------------------------------------------------------------------------
| GET BOOKING DETAILS
|--------------------------------------------------------------------------
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

    WHERE b.booking_reference = ?
      AND b.user_id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Booking query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "si",
    $booking_reference,
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();


$booking = $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| CHECK BOOKING
|--------------------------------------------------------------------------
*/

if (!$booking) {

    die("Booking not found.");

}


/*
|--------------------------------------------------------------------------
| GET BOOKING SEATS
|--------------------------------------------------------------------------
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


$seat_stmt = $conn->prepare($seat_sql);


if (!$seat_stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );

}


$seat_stmt->bind_param(
    "i",
    $booking['booking_id']
);


$seat_stmt->execute();


$seat_result =
    $seat_stmt->get_result();


$booking_seats = [];


while (
    $seat =
    $seat_result->fetch_assoc()
) {

    $booking_seats[] = $seat;

}


$seat_stmt->close();


/*
|--------------------------------------------------------------------------
| CALCULATE SEAT PRICE
|--------------------------------------------------------------------------
*/

function calculateSeatPrice(
    $seat_type,
    $base_price
) {

    $seat_type =
        strtolower(
            trim(
                (string)
                $seat_type
            )
        );


    if ($seat_type === 'vip') {

        return
            (float)
            $base_price * 2;

    }


    if ($seat_type === 'recliner') {

        return
            (float)
            $base_price * 1.5;

    }


    return
        (float)
        $base_price;

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$booking_status =
    strtolower(
        trim(
            (string)
            $booking['booking_status']
        )
    );


if ($booking_status === '') {

    $booking_status = 'confirmed';

}


$payment_status =
    strtolower(
        trim(
            (string)
            $booking['payment_status']
        )
    );


/*
|--------------------------------------------------------------------------
| MOVIE NAME
|--------------------------------------------------------------------------
*/

$movie_name =
    !empty($booking['movie_name'])
    ? $booking['movie_name']
    : 'Movie';


/*
|--------------------------------------------------------------------------
| THEATER NAME
|--------------------------------------------------------------------------
*/

$theater_name =
    !empty($booking['theater_name'])
    ? $booking['theater_name']
    : 'Theater';


/*
|--------------------------------------------------------------------------
| SCREEN NAME
|--------------------------------------------------------------------------
*/

$screen_name =
    !empty($booking['screen_name'])
    ? $booking['screen_name']
    : 'Screen';


/*
|--------------------------------------------------------------------------
| DATE + TIME
|--------------------------------------------------------------------------
*/

$show_date = '';

if (!empty($booking['show_date'])) {

    $show_date =
        date(
            "d M Y",
            strtotime(
                $booking['show_date']
            )
        );

}


$show_time = '';

if (!empty($booking['show_time'])) {

    $show_time =
        date(
            "h:i A",
            strtotime(
                $booking['show_time']
            )
        );

}


/*
|--------------------------------------------------------------------------
| SEAT COUNT
|--------------------------------------------------------------------------
*/

$seat_count =
    count($booking_seats);


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$total_amount =
    (float)
    $booking['total_amount'];

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
        Ticket <?php echo htmlspecialchars(
            $booking['booking_reference']
        ); ?>
        | TicketFlix
    </title>


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

            background:

                radial-gradient(
                    circle at top right,
                    rgba(126,87,194,.28),
                    transparent 35%
                ),

                radial-gradient(
                    circle at bottom left,
                    rgba(255,49,95,.12),
                    transparent 35%
                ),

                #100b18;

            color: white;

            padding: 30px 15px 60px;

        }


        /* =====================================================
           PAGE
        ===================================================== */

        .page {

            width: 100%;

            max-width: 850px;

            margin: auto;

        }


        .page-header {

            text-align: center;

            margin-bottom: 25px;

        }


        .page-header h1 {

            font-size: 32px;

            margin-bottom: 8px;

        }


        .page-header h1 span {

            color: #ff315f;

        }


        .page-header p {

            color: #aaa4b0;

            font-size: 14px;

        }


        /* =====================================================
           TICKET
        ===================================================== */

        .ticket {

            background: #ffffff;

            color: #171020;

            border-radius: 25px;

            overflow: hidden;

            box-shadow:
                0 30px 80px
                rgba(0,0,0,.45);

        }


        /* =====================================================
           TICKET HEADER
        ===================================================== */

        .ticket-header {

            padding: 30px;

            background:

                linear-gradient(
                    135deg,
                    #171020,
                    #291638
                );

            color: white;

            position: relative;

        }


        .ticket-logo {

            font-size: 25px;

            font-weight: 800;

            margin-bottom: 25px;

        }


        .ticket-logo i {

            color: #ff315f;

            margin-right: 6px;

        }


        .ticket-logo span {

            color: #ff315f;

        }


        .movie-title {

            font-size: 30px;

            font-weight: 800;

            margin-bottom: 10px;

        }


        .ticket-subtitle {

            color: #c7c1ce;

            font-size: 14px;

        }


        /* =====================================================
           BOOKING STATUS
        ===================================================== */

        .status-badge {

            position: absolute;

            right: 30px;

            top: 30px;

            padding: 8px 15px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 800;

            text-transform: uppercase;

        }


        .status-badge.confirmed {

            color: #58e18a;

            background:
                rgba(46,204,113,.15);

            border:
                1px solid
                rgba(46,204,113,.35);

        }


        .status-badge.pending {

            color: #f3d35d;

            background:
                rgba(241,196,15,.15);

            border:
                1px solid
                rgba(241,196,15,.35);

        }


        .status-badge.cancelled {

            color: #ff6b89;

            background:
                rgba(255,49,95,.15);

            border:
                1px solid
                rgba(255,49,95,.35);

        }


        /* =====================================================
           TICKET BODY
        ===================================================== */

        .ticket-body {

            padding: 30px;

        }


        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

            margin-bottom: 30px;

        }


        .info-box {

            padding: 16px;

            border-radius: 14px;

            background: #f7f5f8;

            border:
                1px solid #ece8ef;

        }


        .info-label {

            display: block;

            font-size: 10px;

            color: #8b8491;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 7px;

        }


        .info-value {

            font-size: 15px;

            font-weight: 700;

            color: #211827;

        }


        .info-value i {

            color: #ff315f;

            margin-right: 6px;

        }


        /* =====================================================
           SEATS
        ===================================================== */

        .section-title {

            font-size: 18px;

            font-weight: 800;

            margin-bottom: 15px;

        }


        .section-title i {

            color: #ff315f;

            margin-right: 7px;

        }


        .seats {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-bottom: 30px;

        }


        .seat {

            padding: 10px 15px;

            border-radius: 10px;

            background: #fff1f4;

            border:
                1px solid #ffc5d2;

            color: #d91647;

            font-size: 13px;

            font-weight: 800;

        }


        .seat.vip {

            background: #fff8df;

            border-color: #ead27b;

            color: #9c7b12;

        }


        .seat.recliner {

            background: #f1eaff;

            border-color: #d4c0ff;

            color: #7047b8;

        }


        /* =====================================================
           PRICE
        ===================================================== */

        .price-box {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 20px 0;

            border-top:
                1px dashed #d8d2dc;

            border-bottom:
                1px dashed #d8d2dc;

            margin-bottom: 25px;

        }


        .price-label {

            color: #77717c;

            font-size: 14px;

        }


        .price-label strong {

            display: block;

            color: #211827;

            font-size: 18px;

            margin-top: 4px;

        }


        .price {

            color: #d4af37;

            font-size: 27px;

            font-weight: 900;

        }


        /* =====================================================
           REFERENCE
        ===================================================== */

        .reference {

            text-align: center;

            padding: 20px;

            border-radius: 15px;

            background: #f7f5f8;

            margin-bottom: 10px;

        }


        .reference-label {

            display: block;

            color: #88818d;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1.5px;

            margin-bottom: 7px;

        }


        .reference-code {

            color: #211827;

            font-size: 23px;

            font-weight: 900;

            letter-spacing: 2px;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .ticket-footer {

            padding: 22px 30px;

            background: #f7f5f8;

            border-top:
                1px dashed #d8d2dc;

            text-align: center;

            color: #77717c;

            font-size: 12px;

        }


        /* =====================================================
           BUTTONS
        ===================================================== */

        .actions {

            display: flex;

            justify-content: center;

            gap: 12px;

            flex-wrap: wrap;

            margin-top: 25px;

        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding: 13px 22px;

            border-radius: 25px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 800;

            cursor: pointer;

            border: none;

        }


        .btn-primary {

            color: white;

            background:

                linear-gradient(
                    135deg,
                    #ff315f,
                    #d91647
                );

        }


        .btn-secondary {

            color: white;

            background: #29202f;

        }


        .btn:hover {

            transform:
                translateY(-2px);

        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {

            body {

                background: white;

                padding: 0;

            }


            .page-header,

            .actions {

                display: none;

            }


            .page {

                max-width: 100%;

            }


            .ticket {

                box-shadow: none;

                border: 1px solid #ddd;

            }


            .ticket-header {

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }


            .ticket-footer {

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 600px) {

            body {

                padding: 20px 10px 40px;

            }


            .page-header h1 {

                font-size: 27px;

            }


            .ticket-header {

                padding: 25px 20px;

            }


            .movie-title {

                font-size: 24px;

                padding-right: 70px;

            }


            .status-badge {

                right: 20px;

                top: 20px;

                font-size: 10px;

                padding: 6px 10px;

            }


            .ticket-body {

                padding: 22px 18px;

            }


            .info-grid {

                grid-template-columns: 1fr;

            }


            .ticket-footer {

                padding: 20px;

            }

        }

    </style>

</head>


<body>


<div class="page">


    <!-- =====================================================
         PAGE HEADER
    ===================================================== -->

    <div class="page-header">

        <h1>

            Your
            <span>Movie Ticket</span>

        </h1>

        <p>

            Your TicketFlix booking is confirmed.

        </p>

    </div>



    <!-- =====================================================
         TICKET
    ===================================================== -->

    <div class="ticket">


        <!-- =================================================
             HEADER
        ================================================= -->

        <div class="ticket-header">


            <div class="ticket-logo">

                <i class="fa-solid fa-ticket"></i>

                Ticket<span>Flix</span>

            </div>


            <div
                class="status-badge <?php
                    echo htmlspecialchars(
                        $booking_status
                    );
                ?>"
            >

                <?php

                echo htmlspecialchars(
                    ucfirst(
                        $booking_status
                    )
                );

                ?>

            </div>


            <div class="movie-title">

                <?php

                echo htmlspecialchars(
                    $movie_name
                );

                ?>

            </div>


            <div class="ticket-subtitle">

                Movie Ticket •
                <?php
                echo htmlspecialchars(
                    $theater_name
                );
                ?>

            </div>


        </div>



        <!-- =================================================
             BODY
        ================================================= -->

        <div class="ticket-body">


            <!-- INFO -->

            <div class="info-grid">


                <!-- DATE -->

                <div class="info-box">

                    <span class="info-label">

                        Date

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-regular fa-calendar"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            $show_date ?: 'N/A'
                        );

                        ?>

                    </div>

                </div>



                <!-- TIME -->

                <div class="info-box">

                    <span class="info-label">

                        Showtime

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-regular fa-clock"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            $show_time ?: 'N/A'
                        );

                        ?>

                    </div>

                </div>



                <!-- THEATER -->

                <div class="info-box">

                    <span class="info-label">

                        Theater

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-solid fa-building"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            $theater_name
                        );

                        ?>

                    </div>

                </div>



                <!-- SCREEN -->

                <div class="info-box">

                    <span class="info-label">

                        Screen

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-solid fa-tv"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            $screen_name
                        );

                        ?>

                    </div>

                </div>



                <!-- CITY -->

                <div class="info-box">

                    <span class="info-label">

                        Location

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-solid fa-location-dot"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            $booking['theater_city']
                            ?: 'N/A'
                        );

                        ?>

                    </div>

                </div>



                <!-- PAYMENT -->

                <div class="info-box">

                    <span class="info-label">

                        Payment

                    </span>


                    <div class="info-value">

                        <i
                            class="fa-solid fa-credit-card"
                        ></i>

                        <?php

                        echo htmlspecialchars(
                            ucfirst(
                                $payment_status
                            )
                        );

                        ?>

                    </div>

                </div>


            </div>



            <!-- =================================================
                 SEATS
            ================================================= -->

            <div class="section-title">

                <i class="fa-solid fa-couch"></i>

                Selected Seats

            </div>


            <div class="seats">


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


                        if (
                            $seat_type === 'vip'
                        ) {

                            $seat_class =
                                'seat vip';

                        } elseif (
                            $seat_type === 'recliner'
                        ) {

                            $seat_class =
                                'seat recliner';

                        } else {

                            $seat_class =
                                'seat';

                        }


                        $seat_price =
                            calculateSeatPrice(
                                $seat_type,
                                $booking['base_price']
                            );

                        ?>


                        <div
                            class="<?php
                                echo $seat_class;
                            ?>"
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



            <!-- =================================================
                 PRICE
            ================================================= -->

            <div class="price-box">


                <div class="price-label">

                    Total Tickets

                    <strong>

                        <?php

                        echo (int)
                            $seat_count;

                        ?>

                        Seat(s)

                    </strong>

                </div>


                <div class="price">

                    ₹<?php

                    echo number_format(
                        $total_amount,
                        2
                    );

                    ?>

                </div>


            </div>



            <!-- =================================================
                 REFERENCE
            ================================================= -->

            <div class="reference">


                <span class="reference-label">

                    Booking Reference

                </span>


                <div class="reference-code">

                    <?php

                    echo htmlspecialchars(
                        $booking['booking_reference']
                    );

                    ?>

                </div>


            </div>


        </div>



        <!-- =================================================
             FOOTER
        ================================================= -->

        <div class="ticket-footer">

            Please show this ticket/booking reference
            at the theater.

            <br>

            Thank you for booking with TicketFlix 🎬

        </div>


    </div>



    <!-- =====================================================
         ACTIONS
    ===================================================== -->

    <div class="actions">


        <button
            type="button"
            class="btn btn-primary"
            onclick="window.print()"
        >

            <i class="fa-solid fa-print"></i>

            Print Ticket

        </button>


        <a
            href="my_bookings.php"
            class="btn btn-secondary"
        >

            <i class="fa-solid fa-ticket"></i>

            My Bookings

        </a>


        <a
            href="showtimes.php"
            class="btn btn-secondary"
        >

            <i class="fa-solid fa-film"></i>

            Book Another Movie

        </a>


    </div>


</div>


</body>

</html>