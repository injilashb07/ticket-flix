<!-- <?php
include("config.php");
include("header.php");

$search = "";

if(isset($_GET['search'])) {
    $search = mysqli_real_escape_string(
        $conn,
        $_GET['search']
    );
}

if($search != "") {

    $sql = "SELECT * FROM movies
            WHERE name LIKE '%$search%'
            OR genre LIKE '%$search%'
            OR language LIKE '%$search%'";

} else {

    $sql = "SELECT * FROM movies";
}

$result = mysqli_query($conn, $sql);
?>

<section class="section">

    <div class="section-title">

        <h2>
            Explore <span>Movies</span>
        </h2>

        <p>
            Find your next favourite movie.
        </p>

    </div>


    <form method="GET" class="search-box">

        <input
            type="text"
            name="search"
            placeholder="Search movies, genre or language..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit">
            <i class="fa-solid fa-magnifying-glass"></i>
            Search
        </button>

    </form>


    <div class="movie-grid">

        <?php

        if(mysqli_num_rows($result) > 0) {

            while($movie = mysqli_fetch_assoc($result)) {

                $image = !empty($movie['poster_image'])
                    ? $movie['poster_image']
                    : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=600&q=80";

        ?>

        <div class="movie-card">

            <img
                src="<?php echo htmlspecialchars($image); ?>"
                alt="<?php echo htmlspecialchars($movie['name']); ?>"
            >

            <div class="movie-info">

                <h3>
                    <?php echo htmlspecialchars($movie['name']); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($movie['genre']); ?>
                </p>

                <div class="movie-meta">

                    <span>
                        <?php echo htmlspecialchars($movie['duration']); ?> min
                    </span>

                    <span class="rating">
                        ★ <?php echo htmlspecialchars($movie['rating']); ?>
                    </span>

                </div>

                <br>

                <a
                    href="movie_details.php?id=<?php echo $movie['id']; ?>"
                    class="btn btn-primary"
                >
                    View Movie
                </a>

            </div>

        </div>

        <?php

            }

        } else {

            echo "<p>No movies found.</p>";

        }

        ?>

    </div>

</section>

<?php include("footer.php"); ?> -->



<?php
require_once 'config.php';

$sql = "SELECT id, name, poster FROM movies ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Movies | TicketFlix</title>

    <style>

        /* ================================
           RESET
        ================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* ================================
           BODY
        ================================= */

        body {
            font-family: Arial, Helvetica, sans-serif;

            min-height: 100vh;

            background:
                radial-gradient(
                    circle at top left,
                    #341252 0%,
                    #180b25 45%,
                    #09060e 100%
                );

            color: white;
        }


        /* ================================
           HEADER
        ================================= */

        .header {
            width: 100%;
            height: 78px;

            padding: 0 6%;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: rgba(10, 7, 15, 0.95);

            border-bottom: 1px solid rgba(255,255,255,0.08);
        }


        .logo {
            font-size: 29px;
            font-weight: 800;
            color: white;
        }


        .logo span {
            color: #f4c430;
        }


        .home-btn {
            text-decoration: none;

            color: white;

            border: 1px solid rgba(255,255,255,0.2);

            padding: 10px 20px;

            border-radius: 25px;

            transition: 0.3s;
        }


        .home-btn:hover {
            background: #f4c430;
            color: #21102d;
        }


        /* ================================
           MAIN CONTAINER
        ================================= */

        .container {
            width: min(1200px, 92%);

            margin: 45px auto 70px;
        }


        .page-title {
            text-align: center;

            font-size: 34px;

            margin-bottom: 10px;
        }


        .page-title span {
            color: #f4c430;
        }


        .page-subtitle {
            text-align: center;

            color: #aaa2b0;

            font-size: 14px;

            margin-bottom: 35px;
        }


        /* ================================
           MOVIE GRID
        ================================= */

        .movies-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(200px, 1fr)
                );

            gap: 28px;
        }


        /* ================================
           MOVIE CARD
        ================================= */

        .movie-card {

            background:
                linear-gradient(
                    145deg,
                    #351650,
                    #1c0e2c
                );

            border: 1px solid
                rgba(244,196,48,0.20);

            border-radius: 16px;

            padding: 12px;

            overflow: hidden;

            transition: 0.3s;

        }


        .movie-card:hover {

            transform: translateY(-7px);

            border-color:
                rgba(244,196,48,0.55);

            box-shadow:
                0 15px 35px
                rgba(0,0,0,0.35);
        }


        /* ================================
           POSTER CONTAINER
        ================================= */

        .poster-container {

            width: 100%;

            /*
             * Movie poster ratio
             * Width : Height = 2 : 3
             */

            aspect-ratio: 2 / 3;

            overflow: hidden;

            border-radius: 12px;

            background: #24112f;
        }


        /* ================================
           POSTER IMAGE
        ================================= */

        .poster-container img {

            width: 100%;

            height: 100%;

            /*
             * IMPORTANT
             * Keeps image from stretching
             */

            object-fit: cover;

            object-position: center;

            display: block;

            transition: transform 0.4s ease;
        }


        .movie-card:hover
        .poster-container img {

            transform: scale(1.04);
        }


        /* ================================
           MOVIE INFORMATION
        ================================= */

        .movie-info {

            padding:
                15px 5px 5px;
        }


        .movie-name {

            color: white;

            font-size: 17px;

            font-weight: 800;

            margin-bottom: 12px;

            /*
             * If movie name is very long
             */

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        .view-btn {

            display: block;

            width: 100%;

            text-align: center;

            text-decoration: none;

            background: #f4c430;

            color: #24102e;

            padding: 10px;

            border-radius: 8px;

            font-size: 13px;

            font-weight: 800;

            transition: 0.3s;
        }


        .view-btn:hover {

            background: #e8b415;

            transform: translateY(-1px);
        }


        /* ================================
           NO MOVIES
        ================================= */

        .no-movies {

            text-align: center;

            padding: 50px;

            color: #aaa2b0;

            background:
                rgba(255,255,255,0.05);

            border-radius: 15px;
        }


        /* ================================
           TABLET
        ================================= */

        @media (max-width: 900px) {

            .movies-grid {

                grid-template-columns:
                    repeat(
                        3,
                        1fr
                    );

                gap: 20px;
            }

        }


        /* ================================
           MOBILE
        ================================= */

        @media (max-width: 650px) {

            .header {

                height: 70px;

                padding: 0 4%;
            }


            .logo {

                font-size: 24px;
            }


            .home-btn {

                padding: 8px 14px;

                font-size: 13px;
            }


            .container {

                width: 94%;

                margin-top: 30px;
            }


            .page-title {

                font-size: 27px;
            }


            .movies-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

                gap: 15px;
            }


            .movie-card {

                padding: 8px;

                border-radius: 12px;
            }


            .movie-name {

                font-size: 14px;

                margin-bottom: 9px;
            }


            .view-btn {

                font-size: 12px;

                padding: 9px 5px;
            }

        }


        /* ================================
           VERY SMALL MOBILE
        ================================= */

        @media (max-width: 380px) {

            .movies-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

                gap: 10px;
            }


            .movie-card {

                padding: 6px;
            }


            .movie-info {

                padding:
                    10px 3px 3px;
            }


            .movie-name {

                font-size: 12px;
            }


            .view-btn {

                font-size: 11px;

                padding: 8px 3px;
            }

        }


        .poster-container {
    width: 100%;
    aspect-ratio: 2 / 3;
    overflow: hidden;
}

.poster-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

    </style>

</head>


<body>


<!-- =====================================
     HEADER
====================================== -->

<header class="header">

    <div class="logo">

        Ticket<span>Flix</span>

    </div>


    <a
        href="index.php"
        class="home-btn"
    >

        ← Home

    </a>

</header>



<!-- =====================================
     MAIN
====================================== -->

<main class="container">


    <h1 class="page-title">

        Now Showing <span>🎬</span>

    </h1>


    <p class="page-subtitle">

        Choose your favourite movie and book your tickets.

    </p>



    <?php if ($result && $result->num_rows > 0): ?>


        <div class="movies-grid">


            <?php while ($movie = $result->fetch_assoc()): ?>


                <div class="movie-card">


                    <!-- POSTER -->

                    <div class="poster-container">

                        <img
                            src="assets/images/<?= htmlspecialchars($movie['poster']); ?>"
                            alt="<?= htmlspecialchars($movie['name']); ?>"
                        >

                    </div>



                    <!-- MOVIE DETAILS -->

                    <div class="movie-info">


                        <h2 class="movie-name">

                            <?= htmlspecialchars(
                                $movie['name']
                            ); ?>

                        </h2>


                        <a
                            href="movie_details.php?id=<?= (int)$movie['id']; ?>"
                            class="view-btn"
                        >

                            View Details

                        </a>


                    </div>


                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div class="no-movies">

            <h2>No movies available 🎬</h2>

            <p>
                Please check again later.
            </p>

        </div>


    <?php endif; ?>


</main>


</body>

</html>

