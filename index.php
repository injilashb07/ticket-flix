<?php
include("config.php");
include("header.php");
?>


<!-- ================= HERO SECTION ================= -->

<section class="hero">

    <div class="hero-content">

        <div class="small-title">

            <i class="fa-solid fa-star"></i>

            Welcome to TicketFlix

        </div>


        <h1>

            Your Movies.
            <span>Your Seats.</span>
            Your Experience.

        </h1>


        <p>

            Discover the latest movies, choose your favourite seats
            and book your tickets instantly with TicketFlix.

        </p>


        <div class="hero-buttons">


            <!-- EXPLORE MOVIES -->

            <a
                href="movies.php"
                class="btn btn-primary"
            >

                <i class="fa-solid fa-film"></i>

                Explore Movies

            </a>


            <!-- SHOWTIMES -->

            <a
                href="showtimes.php"
                class="btn btn-gold"
            >

                <i class="fa-solid fa-clock"></i>

                Showtimes

            </a>


            <!-- THEATERS -->

            <a
                href="theaters.php"
                class="btn btn-gold"
            >

                <i class="fa-solid fa-location-dot"></i>

                Find Theaters

            </a>


        </div>

    </div>

</section>



<!-- ================= WHY TICKETFLIX ================= -->

<section class="section">


    <div class="section-title">

        <h2>

            Why Choose
            <span>TicketFlix?</span>

        </h2>


        <p>

            Everything you need for the perfect movie experience.

        </p>

    </div>



    <div class="movie-grid">


        <!-- CARD 1 -->

        <div class="theater-card">


            <div class="theater-icon">

                <i class="fa-solid fa-bolt"></i>

            </div>


            <h3>

                Fast Booking

            </h3>


            <p>

                Book your movie tickets in just a few clicks.

            </p>


        </div>



        <!-- CARD 2 -->

        <div class="theater-card">


            <div class="theater-icon">

                <i class="fa-solid fa-couch"></i>

            </div>


            <h3>

                Choose Your Seat

            </h3>


            <p>

                Select your favourite available seat before booking.

            </p>


        </div>



        <!-- CARD 3 -->

        <div class="theater-card">


            <div class="theater-icon">

                <i class="fa-solid fa-shield-halved"></i>

            </div>


            <h3>

                Secure Booking

            </h3>


            <p>

                Your booking information is safely stored.

            </p>


        </div>



        <!-- CARD 4 -->

        <div class="theater-card">


            <div class="theater-icon">

                <i class="fa-solid fa-ticket"></i>

            </div>


            <h3>

                Digital Ticket

            </h3>


            <p>

                Get your booking reference instantly.

            </p>


        </div>


    </div>

</section>



<!-- ================= POPULAR MOVIES ================= -->

<section class="section">


    <div class="section-title">


        <h2>

            <span>Popular</span>
            Movies

        </h2>


        <p>

            Watch something amazing today.

        </p>


    </div>



    <div class="movie-grid">


        <?php

        $sql =
            "SELECT * FROM movies LIMIT 6";


        $result =
            mysqli_query(
                $conn,
                $sql
            );


        while (
            $movie =
            mysqli_fetch_assoc($result)
        ) {


            $image =
                !empty(
                    $movie['poster_image']
                )

                ? $movie['poster_image']

                : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=600&q=80";

        ?>


        <!-- MOVIE CARD -->

        <div class="movie-card">


            <img
                src="<?php echo htmlspecialchars($image); ?>"
                alt="<?php echo htmlspecialchars($movie['name']); ?>"
            >


            <div class="movie-info">


                <h3>

                    <?php

                    echo htmlspecialchars(
                        $movie['name']
                    );

                    ?>

                </h3>


                <p>

                    <?php

                    echo htmlspecialchars(
                        $movie['genre']
                    );

                    ?>

                </p>



                <div class="movie-meta">


                    <span>

                        <?php

                        echo htmlspecialchars(
                            $movie['language']
                        );

                        ?>

                    </span>


                    <span class="rating">

                        ★

                        <?php

                        echo htmlspecialchars(
                            $movie['rating']
                        );

                        ?>

                    </span>


                </div>


                <br>


                <a
                    href="movie_details.php?id=<?php echo $movie['id']; ?>"
                    class="btn btn-primary"
                >

                    View Details

                </a>


            </div>


        </div>


        <?php

        }

        ?>


    </div>

</section>



<?php

include("footer.php");

?>