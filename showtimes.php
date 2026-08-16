<?php
require_once 'config.php';
include 'header.php';

// Get showtimes with movie, theater and screen details
$sql = "SELECT 
            st.id AS showtime_id,
            st.show_date,
            st.show_time,
            st.end_time,
            st.price,
            st.available_seats,
            
            m.id AS movie_id,
            m.name AS movie_name,
            m.genre,
            m.language,
            m.duration,
            m.rating,
            
            s.screen_name,
            s.total_seats,
            
            t.name AS theater_name,
            t.city,
            t.state
            
        FROM showtimes st
        
        INNER JOIN movies m 
            ON st.movie_id = m.id
            
        INNER JOIN screens s 
            ON st.screen_id = s.id
            
        INNER JOIN theaters t 
            ON s.theater_id = t.id
            
        ORDER BY st.show_date ASC, st.show_time ASC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showtimes | TicketFlix</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #10051c;
            color: white;
        }

        .showtime-container {
            width: 90%;
            max-width: 1350px;
            margin: 50px auto;
        }

        .page-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title h1 {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .gold {
            color: #f4c430;
        }

        .page-title p {
            color: #d6cde0;
            font-size: 17px;
        }

        .showtime-card {
            display: flex;
            min-height: 250px;
            margin-bottom: 28px;

            background: linear-gradient(
                90deg,
                #3a1760,
                #241039
            );

            border: 1px solid #59367b;
            border-radius: 22px;
            overflow: hidden;
        }

        /* Movie section */
        .movie-box {
            width: 190px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;

            background: #351454;
            padding: 20px;
        }

        .movie-icon {
            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #f4c430;
            color: #351454;

            font-size: 36px;
            margin-bottom: 15px;
        }

        .movie-box h2 {
            font-size: 18px;
            text-align: center;
            margin: 0;
        }

        /* Details */
        .details-box {
            flex: 1;
            padding: 28px 32px;
        }

        .details-box h2 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 22px;
        }

        .tag {
            border: 1px solid #80622a;
            color: #f4c430;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 13px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 30px;
        }

        .info {
            font-size: 16px;
            color: #f4eef9;
        }

        .info strong {
            color: #f4c430;
        }

        /* Booking section */
        .booking-box {
            width: 230px;
            border-left: 1px solid #49305f;

            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;

            padding: 25px;
            text-align: center;
        }

        .date {
            color: #f4c430;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .time {
            font-size: 27px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .price {
            color: #f4c430;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .seats {
            color: #cfc4da;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .book-btn {
            display: inline-block;
            text-decoration: none;

            background: #f4c430;
            color: #12071d;

            padding: 14px 30px;
            border-radius: 30px;

            font-size: 16px;
            font-weight: bold;

            transition: 0.3s;
        }

        .book-btn:hover {
            background: #ffd84d;
            transform: translateY(-2px);
        }

        .no-showtimes {
            text-align: center;
            padding: 50px;
            background: #241039;
            border-radius: 20px;
            color: #ddd;
        }

        /* Responsive */
        @media (max-width: 900px) {

            .showtime-card {
                flex-direction: column;
            }

            .movie-box {
                width: auto;
            }

            .booking-box {
                width: auto;
                border-left: none;
                border-top: 1px solid #49305f;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="showtime-container">

    <div class="page-title">
        <h1>
            <span class="gold">Movie</span> Showtimes
        </h1>

        <p>
            Choose your movie, theater and preferred showtime
        </p>
    </div>


    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <div class="showtime-card">

                <!-- Movie -->
                <div class="movie-box">

                    <div class="movie-icon">
                        🎞️
                    </div>

                    <h2>
                        <?php echo htmlspecialchars($row['movie_name']); ?>
                    </h2>

                </div>


                <!-- Movie Details -->
                <div class="details-box">

                    <h2>
                        <?php echo htmlspecialchars($row['movie_name']); ?>
                    </h2>


                    <div class="tags">

                        <span class="tag">
                            🎬 <?php echo htmlspecialchars($row['genre']); ?>
                        </span>

                        <span class="tag">
                            🌐 <?php echo htmlspecialchars($row['language']); ?>
                        </span>

                        <span class="tag">
                            🕐 <?php echo htmlspecialchars($row['duration']); ?> min
                        </span>

                        <span class="tag">
                            ⭐ <?php echo htmlspecialchars($row['rating']); ?>
                        </span>

                    </div>


                    <div class="info-grid">

                        <div class="info">
                            🏢
                            <strong>Theater:</strong>
                            <?php echo htmlspecialchars($row['theater_name']); ?>
                        </div>


                        <div class="info">
                            📍
                            <strong>Location:</strong>
                            <?php echo htmlspecialchars($row['city']); ?>,
                            <?php echo htmlspecialchars($row['state']); ?>
                        </div>


                        <div class="info">
                            🚪
                            <strong>Screen:</strong>
                            <?php echo htmlspecialchars($row['screen_name']); ?>
                        </div>


                        <div class="info">
                            💺
                            <strong>Seats:</strong>
                            <?php echo htmlspecialchars($row['available_seats']); ?>
                            available
                        </div>

                    </div>

                </div>


                <!-- Booking -->
                <div class="booking-box">

                    <div class="date">
                        <?php
                        echo date(
                            "D, d M Y",
                            strtotime($row['show_date'])
                        );
                        ?>
                    </div>


                    <div class="time">
                        <?php
                        echo date(
                            "h:i A",
                            strtotime($row['show_time'])
                        );
                        ?>
                    </div>


                    <div class="price">
                        ₹ <?php
                        echo number_format(
                            $row['price'],
                            2
                        );
                        ?>
                    </div>


                    <div class="seats">
                        💺
                        <?php echo htmlspecialchars($row['available_seats']); ?>
                        seats available
                    </div>


                    <!-- BOOK TICKETS BUTTON -->
                    <a
                        href="seats.php?showtime_id=<?php echo $row['showtime_id']; ?>"
                        class="book-btn"
                    >
                        🎟️ Book Tickets
                    </a>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="no-showtimes">
            <h2>No Showtimes Available</h2>
            <p>Please check again later.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

<?php
include 'footer.php';
?>