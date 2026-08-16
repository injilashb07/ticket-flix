<?php
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

<?php include("footer.php"); ?>