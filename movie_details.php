<?php

include("config.php");
include("header.php");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '
    <div class="section">
        <div class="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            Movie not found.
        </div>
    </div>
    ';
    include("footer.php");
    exit;
}

$movie_id = intval($_GET['id']);

$sql = "SELECT * FROM movies WHERE id = $movie_id LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {

    echo '
    <div class="section">
        <div class="alert">
            <i class="fa-solid fa-film"></i>
            Sorry! This movie does not exist.
        </div>
    </div>
    ';

    include("footer.php");
    exit;
}

$movie = mysqli_fetch_assoc($result);


/* Movie values */

$name = $movie['name'];
$genre = $movie['genre'];
$language = $movie['language'];
$duration = $movie['duration'];
$rating = $movie['rating'];
$status = $movie['status'];
$description = $movie['description'];
$release_date = $movie['release_date'];
$poster = $movie['poster_image'];
$trailer = $movie['trailer'];


/* Default image if poster is empty */

if (empty($poster)) {

    $poster = "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=80";

}

?>

<section class="movie-details-section">

    <div class="movie-details-container">

        <!-- LEFT : POSTER -->

        <div class="movie-details-poster">

            <img
                src="<?php echo htmlspecialchars($poster); ?>"
                alt="<?php echo htmlspecialchars($name); ?>"
            >

            <div class="poster-badge">

                <i class="fa-solid fa-star"></i>

                <?php echo htmlspecialchars($rating); ?>

            </div>

        </div>


        <!-- RIGHT : DETAILS -->

        <div class="movie-details-content">

            <div class="movie-status">

                <?php

                if ($status == "coming_soon") {

                    echo '<span class="status-coming">
                            <i class="fa-solid fa-clock"></i>
                            Coming Soon
                          </span>';

                } else {

                    echo '<span class="status-now">
                            <i class="fa-solid fa-circle"></i>
                            Now Showing
                          </span>';

                }

                ?>

            </div>


            <h1>
                <?php echo htmlspecialchars($name); ?>
            </h1>


            <div class="movie-tags">

                <span>
                    <i class="fa-solid fa-film"></i>
                    <?php echo htmlspecialchars($genre); ?>
                </span>

                <span>
                    <i class="fa-solid fa-language"></i>
                    <?php echo htmlspecialchars($language); ?>
                </span>

                <span>
                    <i class="fa-regular fa-clock"></i>
                    <?php echo htmlspecialchars($duration); ?> min
                </span>

            </div>


            <div class="movie-rating-box">

                <div class="big-rating">

                    <i class="fa-solid fa-star"></i>

                    <strong>
                        <?php echo htmlspecialchars($rating); ?>
                    </strong>

                    <small>/ 10</small>

                </div>

                <div>
                    <p>IMDb Rating</p>
                </div>

            </div>


            <?php if (!empty($description)) { ?>

                <div class="movie-description">

                    <h3>
                        <i class="fa-solid fa-align-left"></i>
                        About the Movie
                    </h3>

                    <p>
                        <?php echo nl2br(htmlspecialchars($description)); ?>
                    </p>

                </div>

            <?php } ?>


            <?php if (!empty($release_date)) { ?>

                <div class="release-info">

                    <i class="fa-regular fa-calendar"></i>

                    <div>

                        <small>Release Date</small>

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($release_date)
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            <?php } ?>


            <div class="movie-actions">

                <?php if ($status != "coming_soon") { ?>

                    <a
                        href="showtimes.php?movie_id=<?php echo $movie_id; ?>"
                        class="btn btn-gold large-btn"
                    >

                        <i class="fa-solid fa-ticket"></i>

                        Book Tickets

                    </a>

                <?php } else { ?>

                    <button
                        class="btn btn-disabled large-btn"
                        disabled
                    >

                        <i class="fa-solid fa-clock"></i>

                        Coming Soon

                    </button>

                <?php } ?>


                <?php if (!empty($trailer)) { ?>

                    <a
                        href="<?php echo htmlspecialchars($trailer); ?>"
                        target="_blank"
                        class="btn btn-primary large-btn"
                    >

                        <i class="fa-solid fa-play"></i>

                        Watch Trailer

                    </a>

                <?php } ?>

            </div>

        </div>

    </div>

</section>


<!-- MOVIE EXPERIENCE -->

<section class="section">

    <div class="section-title">

        <h2>
            Why Watch <span><?php echo htmlspecialchars($name); ?>?</span>
        </h2>

        <p>
            Get ready for an amazing movie experience.
        </p>

    </div>


    <div class="movie-features">

        <div class="feature-box">

            <div class="feature-icon">
                <i class="fa-solid fa-couch"></i>
            </div>

            <h3>Premium Seats</h3>

            <p>
                Choose from Standard, Recliner and VIP seats.
            </p>

        </div>


        <div class="feature-box">

            <div class="feature-icon">
                <i class="fa-solid fa-building"></i>
            </div>

            <h3>Multiple Theaters</h3>

            <p>
                Select your preferred theater and showtime.
            </p>

        </div>


        <div class="feature-box">

            <div class="feature-icon">
                <i class="fa-solid fa-ticket"></i>
            </div>

            <h3>Easy Booking</h3>

            <p>
                Book your tickets quickly and securely.
            </p>

        </div>


        <div class="feature-box">

            <div class="feature-icon">
                <i class="fa-solid fa-mobile-screen"></i>
            </div>

            <h3>Digital Ticket</h3>

            <p>
                Get your booking reference instantly.
            </p>

        </div>

    </div>

</section>


<?php include("footer.php"); ?>