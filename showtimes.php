<?php

require_once 'config.php';
include 'header.php';


/*
|--------------------------------------------------------------------------
| GET SHOWTIMES
|--------------------------------------------------------------------------
|
| Poster is taken from:
| movies.poster_image
|
| Example database value:
| uploads/the_godfather_1787219774.jpg
|
*/


$sql = "
    SELECT 

        /* =========================================================
           SHOWTIME
        ========================================================= */

        st.id AS showtime_id,
        st.show_date,
        st.show_time,
        st.end_time,
        st.price,


        /* =========================================================
           MOVIE
        ========================================================= */

        m.id AS movie_id,
        m.name AS movie_name,
        m.description,
        m.genre,
        m.language,
        m.duration,
        m.rating,
        m.poster_image,
        m.release_date,
        m.trailer,


        /* =========================================================
           SCREEN
        ========================================================= */

        s.screen_name,
        s.total_seats,


        /* =========================================================
           THEATER
        ========================================================= */

        t.name AS theater_name,
        t.city,
        t.state,


        /* =========================================================
           BOOKED SEATS
        ========================================================= */

        (
            SELECT COUNT(DISTINCT bs.seat_id)

            FROM booking_seats bs

            INNER JOIN bookings b
                ON b.id = bs.booking_id

            WHERE b.showtime_id = st.id

            AND (
                b.booking_status = 'pending'
                OR
                b.booking_status = 'confirmed'
            )
        ) AS booked_seats,


        /* =========================================================
           AVAILABLE SEATS
        ========================================================= */

        GREATEST(
            0,

            s.total_seats -

            (
                SELECT COUNT(DISTINCT bs2.seat_id)

                FROM booking_seats bs2

                INNER JOIN bookings b2
                    ON b2.id = bs2.booking_id

                WHERE b2.showtime_id = st.id

                AND (
                    b2.booking_status = 'pending'
                    OR
                    b2.booking_status = 'confirmed'
                )
            )

        ) AS available_seats


    FROM showtimes st


    /* MOVIE */

    INNER JOIN movies m
        ON st.movie_id = m.id


    /* SCREEN */

    INNER JOIN screens s
        ON st.screen_id = s.id


    /* THEATER */

    INNER JOIN theaters t
        ON s.theater_id = t.id


    /* SORT */

    ORDER BY
        st.show_date ASC,
        st.show_time ASC
";


$result = $conn->query($sql);


/*
|--------------------------------------------------------------------------
| CHECK SQL ERROR
|--------------------------------------------------------------------------
*/

if (!$result) {

    die(
        "Showtimes Query Error: " .
        htmlspecialchars($conn->error)
    );

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
        Showtimes | TicketFlix
    </title>


    <style>


        /* =========================================================
           GLOBAL
        ========================================================= */

        body {

            margin: 0;

            font-family:
                Arial,
                sans-serif;

            background:
                #10051c;

            color:
                white;

        }


        /* =========================================================
           MAIN CONTAINER
        ========================================================= */

        .showtime-container {

            width:
                90%;

            max-width:
                1350px;

            margin:
                50px auto;

        }


        /* =========================================================
           PAGE TITLE
        ========================================================= */

        .page-title {

            text-align:
                center;

            margin-bottom:
                40px;

        }


        .page-title h1 {

            font-size:
                42px;

            margin-bottom:
                10px;

        }


        .gold {

            color:
                #f4c430;

        }


        .page-title p {

            color:
                #d6cde0;

            font-size:
                17px;

        }


        /* =========================================================
           SHOWTIME CARD
        ========================================================= */

        .showtime-card {

            display:
                flex;

            min-height:
                250px;

            margin-bottom:
                28px;

            background:
                linear-gradient(
                    90deg,
                    #3a1760,
                    #241039
                );

            border:
                1px solid #59367b;

            border-radius:
                22px;

            overflow:
                hidden;

            box-shadow:
                0 10px 30px rgba(
                    0,
                    0,
                    0,
                    0.25
                );

        }


        /* =========================================================
           MOVIE / POSTER BOX
        ========================================================= */

        .movie-box {

            width:
                190px;

            min-width:
                190px;

            display:
                flex;

            flex-direction:
                column;

            justify-content:
                center;

            align-items:
                center;

            background:
                #351454;

            padding:
                20px;

            box-sizing:
                border-box;

        }


        /* =========================================================
           POSTER IMAGE
        ========================================================= */

        .movie-poster {

            width:
                140px;

            height:
                190px;

            object-fit:
                cover;

            display:
                block;

            border-radius:
                10px;

            border:
                2px solid #f4c430;

            box-shadow:
                0 8px 20px rgba(
                    0,
                    0,
                    0,
                    0.45
                );

            margin-bottom:
                15px;

        }


        /* =========================================================
           FALLBACK ICON
        ========================================================= */

        .movie-icon {

            width:
                70px;

            height:
                70px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                12px;

            background:
                #f4c430;

            color:
                #351454;

            font-size:
                36px;

            margin-bottom:
                15px;

        }


        /* =========================================================
           MOVIE NAME UNDER POSTER
        ========================================================= */

        .movie-box h2 {

            font-size:
                18px;

            text-align:
                center;

            margin:
                0;

            line-height:
                1.3;

        }


        /* =========================================================
           DETAILS
        ========================================================= */

        .details-box {

            flex:
                1;

            padding:
                28px 32px;

            min-width:
                0;

        }


        .details-box h2 {

            margin:
                0 0 12px;

            font-size:
                28px;

        }


        /* =========================================================
           TAGS
        ========================================================= */

        .tags {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                8px;

            margin-bottom:
                22px;

        }


        .tag {

            border:
                1px solid #80622a;

            color:
                #f4c430;

            border-radius:
                20px;

            padding:
                6px 12px;

            font-size:
                13px;

            background:
                rgba(
                    0,
                    0,
                    0,
                    0.08
                );

        }


        /* =========================================================
           INFORMATION GRID
        ========================================================= */

        .info-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                18px 30px;

        }


        .info {

            font-size:
                16px;

            color:
                #f4eef9;

            line-height:
                1.5;

        }


        .info strong {

            color:
                #f4c430;

        }


        /* =========================================================
           BOOKING BOX
        ========================================================= */

        .booking-box {

            width:
                230px;

            min-width:
                230px;

            border-left:
                1px solid #49305f;

            display:
                flex;

            flex-direction:
                column;

            justify-content:
                center;

            align-items:
                center;

            padding:
                25px;

            text-align:
                center;

            box-sizing:
                border-box;

        }


        /* =========================================================
           DATE
        ========================================================= */

        .date {

            color:
                #f4c430;

            font-weight:
                bold;

            font-size:
                16px;

            margin-bottom:
                8px;

        }


        /* =========================================================
           TIME
        ========================================================= */

        .time {

            font-size:
                27px;

            font-weight:
                bold;

            margin-bottom:
                12px;

        }


        /* =========================================================
           PRICE
        ========================================================= */

        .price {

            color:
                #f4c430;

            font-size:
                22px;

            font-weight:
                bold;

            margin-bottom:
                12px;

        }


        /* =========================================================
           SEATS
        ========================================================= */

        .seats {

            color:
                #cfc4da;

            font-size:
                14px;

            margin-bottom:
                20px;

        }


        .seats strong {

            color:
                #f4c430;

        }


        /* =========================================================
           BOOK BUTTON
        ========================================================= */

        .book-btn {

            display:
                inline-block;

            text-decoration:
                none;

            background:
                #f4c430;

            color:
                #12071d;

            padding:
                14px 30px;

            border-radius:
                30px;

            font-size:
                16px;

            font-weight:
                bold;

            transition:
                0.3s;

        }


        .book-btn:hover {

            background:
                #ffd84d;

            transform:
                translateY(-2px);

        }


        /* =========================================================
           DISABLED BUTTON
        ========================================================= */

        .book-btn.disabled {

            background:
                #555;

            color:
                #aaa;

            cursor:
                not-allowed;

            pointer-events:
                none;

            opacity:
                0.7;

        }


        /* =========================================================
           NO SHOWTIMES
        ========================================================= */

        .no-showtimes {

            text-align:
                center;

            padding:
                50px;

            background:
                #241039;

            border-radius:
                20px;

            color:
                #ddd;

        }


        .no-showtimes h2 {

            color:
                #f4c430;

            margin-bottom:
                10px;

        }


        /* =========================================================
           RESPONSIVE - TABLET
        ========================================================= */

        @media (max-width: 1000px) {

            .showtime-card {

                flex-wrap:
                    wrap;

            }


            .movie-box {

                width:
                    190px;

            }


            .details-box {

                flex:
                    1;

                min-width:
                    450px;

            }


            .booking-box {

                width:
                    100%;

                border-left:
                    none;

                border-top:
                    1px solid #49305f;

                padding:
                    25px;

            }

        }


        /* =========================================================
           RESPONSIVE - MOBILE
        ========================================================= */

        @media (max-width: 700px) {

            .showtime-container {

                width:
                    94%;

                margin-top:
                    30px;

            }


            .page-title h1 {

                font-size:
                    32px;

            }


            .page-title p {

                font-size:
                    14px;

            }


            .showtime-card {

                flex-direction:
                    column;

            }


            .movie-box {

                width:
                    100%;

                min-width:
                    100%;

                padding:
                    25px;

            }


            .movie-poster {

                width:
                    150px;

                height:
                    205px;

            }


            .details-box {

                min-width:
                    0;

                padding:
                    22px;

            }


            .details-box h2 {

                font-size:
                    23px;

            }


            .info-grid {

                grid-template-columns:
                    1fr;

                gap:
                    15px;

            }


            .booking-box {

                width:
                    100%;

                min-width:
                    0;

                border-left:
                    none;

                border-top:
                    1px solid #49305f;

                padding:
                    25px 20px;

            }

        }


    </style>

</head>


<body>


<div class="showtime-container">


    <!-- =========================================================
         PAGE TITLE
    ========================================================= -->

    <div class="page-title">

        <h1>

            <span class="gold">
                Movie
            </span>

            Showtimes

        </h1>


        <p>

            Choose your movie, theater and preferred showtime

        </p>

    </div>


    <!-- =========================================================
         SHOWTIMES
    ========================================================= -->

    <?php if ($result->num_rows > 0): ?>


        <?php while ($row = $result->fetch_assoc()): ?>


            <?php

            /*
            |--------------------------------------------------------------------------
            | AVAILABLE SEATS
            |--------------------------------------------------------------------------
            */

            $available_seats =
                max(
                    0,
                    (int)$row['available_seats']
                );


            /*
            |--------------------------------------------------------------------------
            | TOTAL SEATS
            |--------------------------------------------------------------------------
            */

            $total_seats =
                max(
                    0,
                    (int)$row['total_seats']
                );


            /*
            |--------------------------------------------------------------------------
            | BOOKED SEATS
            |--------------------------------------------------------------------------
            */

            $booked_seats =
                max(
                    0,
                    (int)$row['booked_seats']
                );


            /*
            |--------------------------------------------------------------------------
            | PRICE
            |--------------------------------------------------------------------------
            */

            $price =
                (float)($row['price'] ?? 0);


            /*
            |--------------------------------------------------------------------------
            | POSTER
            |--------------------------------------------------------------------------
            |
            | Database column:
            |
            | poster_image
            |
            | Example:
            |
            | uploads/the_godfather_1787219774.jpg
            |
            */

            $poster_path =
                trim(
                    $row['poster_image'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | POSTER PATH FIX
            |--------------------------------------------------------------------------
            |
            | If database contains:
            |
            | uploads/movie.jpg
            |
            | use it directly.
            |
            | If database contains only:
            |
            | movie.jpg
            |
            | add uploads/.
            |
            */

            if (
                $poster_path !== '' &&
                strpos(
                    $poster_path,
                    'uploads/'
                ) !== 0
            ) {

                $poster_path =
                    'uploads/' .
                    $poster_path;

            }

            ?>


            <!-- =====================================================
                 SHOWTIME CARD
            ====================================================== -->

            <div class="showtime-card">


                <!-- =================================================
                     POSTER SECTION
                ================================================== -->

                <div class="movie-box">


                    <?php if ($poster_path !== ''): ?>


                        <!-- POSTER -->

                        <img
                            src="<?php echo htmlspecialchars($poster_path); ?>"
                            alt="<?php echo htmlspecialchars($row['movie_name']); ?>"
                            class="movie-poster"
                            onerror="
                                this.style.display='none';
                                this.nextElementSibling.style.display='flex';
                            "
                        >


                        <!-- FALLBACK ICON -->

                        <div
                            class="movie-icon"
                            style="display:none;"
                        >

                            🎞️

                        </div>


                    <?php else: ?>


                        <!-- NO POSTER -->

                        <div class="movie-icon">

                            🎞️

                        </div>


                    <?php endif; ?>


                    <!-- MOVIE NAME -->

                    <h2>

                        <?php

                        echo htmlspecialchars(
                            $row['movie_name']
                        );

                        ?>

                    </h2>


                </div>


                <!-- =================================================
                     DETAILS SECTION
                ================================================== -->

                <div class="details-box">


                    <!-- MOVIE NAME -->

                    <h2>

                        <?php

                        echo htmlspecialchars(
                            $row['movie_name']
                        );

                        ?>

                    </h2>


                    <!-- =================================================
                         TAGS
                    ================================================== -->

                    <div class="tags">


                        <!-- GENRE -->

                        <span class="tag">

                            🎬

                            <?php

                            echo htmlspecialchars(
                                $row['genre'] ?? 'N/A'
                            );

                            ?>

                        </span>


                        <!-- LANGUAGE -->

                        <span class="tag">

                            🌐

                            <?php

                            echo htmlspecialchars(
                                $row['language'] ?? 'N/A'
                            );

                            ?>

                        </span>


                        <!-- DURATION -->

                        <span class="tag">

                            🕐

                            <?php

                            echo htmlspecialchars(
                                $row['duration'] ?? 'N/A'
                            );

                            ?>

                            min

                        </span>


                        <!-- RATING -->

                        <span class="tag">

                            ⭐

                            <?php

                            echo htmlspecialchars(
                                $row['rating'] ?? 'N/A'
                            );

                            ?>

                        </span>


                    </div>


                    <!-- =================================================
                         INFORMATION
                    ================================================== -->

                    <div class="info-grid">


                        <!-- THEATER -->

                        <div class="info">

                            🏢

                            <strong>
                                Theater:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $row['theater_name']
                            );

                            ?>

                        </div>


                        <!-- LOCATION -->

                        <div class="info">

                            📍

                            <strong>
                                Location:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $row['city']
                            );

                            ?>,

                            <?php

                            echo htmlspecialchars(
                                $row['state']
                            );

                            ?>

                        </div>


                        <!-- SCREEN -->

                        <div class="info">

                            🚪

                            <strong>
                                Screen:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $row['screen_name']
                            );

                            ?>

                        </div>


                        <!-- AVAILABLE SEATS -->

                        <div class="info">

                            💺

                            <strong>
                                Seats:
                            </strong>

                            <?php

                            echo $available_seats;

                            ?>

                            available

                        </div>


                    </div>


                </div>


                <!-- =================================================
                     BOOKING SECTION
                ================================================== -->

                <div class="booking-box">


                    <!-- DATE -->

                    <div class="date">

                        <?php

                        if (
                            !empty(
                                $row['show_date']
                            )
                        ) {

                            echo date(
                                "D, d M Y",
                                strtotime(
                                    $row['show_date']
                                )
                            );

                        } else {

                            echo "Date not available";

                        }

                        ?>

                    </div>


                    <!-- TIME -->

                    <div class="time">

                        <?php

                        if (
                            !empty(
                                $row['show_time']
                            )
                        ) {

                            echo date(
                                "h:i A",
                                strtotime(
                                    $row['show_time']
                                )
                            );

                        } else {

                            echo "Time not available";

                        }

                        ?>

                    </div>


                    <!-- PRICE -->

                    <div class="price">

                        ₹<?php

                        echo number_format(
                            $price,
                            2
                        );

                        ?>

                    </div>


                    <!-- SEATS -->

                    <div class="seats">

                        💺

                        <strong>

                            <?php

                            echo $available_seats;

                            ?>

                        </strong>

                        seats available

                    </div>


                    <!-- =================================================
                         BOOK BUTTON
                    ================================================== -->

                    <?php if ($available_seats > 0): ?>


                        <a
                            href="seats.php?showtime_id=<?php echo (int)$row['showtime_id']; ?>"
                            class="book-btn"
                        >

                            🎟️

                            Book Tickets

                        </a>


                    <?php else: ?>


                        <a
                            href="javascript:void(0)"
                            class="book-btn disabled"
                        >

                            🚫

                            Sold Out

                        </a>


                    <?php endif; ?>


                </div>


            </div>


        <?php endwhile; ?>


    <?php else: ?>


        <!-- =========================================================
             NO SHOWTIMES
        ========================================================== -->

        <div class="no-showtimes">


            <h2>

                No Showtimes Available

            </h2>


            <p>

                Please check again later.

            </p>


        </div>


    <?php endif; ?>


</div>


</body>

</html>


<?php

include 'footer.php';

?>