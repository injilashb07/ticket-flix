<?php

session_start();

require_once "../config.php";

/* ================================
   ADMIN LOGIN CHECK
================================ */

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

/* ================================
   GET MOVIE ID
================================ */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_movies.php");
    exit();
}

$movie_id = (int) $_GET['id'];

$message = "";
$error = "";

/* ================================
   FETCH MOVIE
================================ */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        genre,
        language,
        duration,
        rating,
        release_date,
        poster_image,
        trailer,
        status
    FROM movies
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $movie_id);
$stmt->execute();

$result = $stmt->get_result();
$movie = $result->fetch_assoc();

$stmt->close();

/* ================================
   MOVIE NOT FOUND
================================ */

if (!$movie) {
    die("Movie not found.");
}

/* ================================
   UPDATE MOVIE
================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $genre = trim($_POST['genre'] ?? "");
    $language = trim($_POST['language'] ?? "");
    $duration = (int) ($_POST['duration'] ?? 0);
    $rating = trim($_POST['rating'] ?? "");
    $release_date = !empty($_POST['release_date'])
        ? $_POST['release_date']
        : null;

    $poster_image = trim($_POST['poster_image'] ?? "");
    $trailer = trim($_POST['trailer'] ?? "");
    $status = $_POST['status'] ?? "coming_soon";

    /* ================================
       VALIDATION
    ================================ */

    if (
        empty($name) ||
        empty($genre) ||
        empty($language) ||
        $duration <= 0 ||
        empty($rating) ||
        empty($trailer)
    ) {

        $error = "Please fill all required fields.";

    } elseif (
        !in_array(
            $status,
            ['coming_soon', 'now_showing', 'expired']
        )
    ) {

        $error = "Invalid movie status.";

    } else {

        /* ================================
           UPDATE QUERY
        ================================ */

        $update = $conn->prepare("
            UPDATE movies
            SET
                name = ?,
                description = ?,
                genre = ?,
                language = ?,
                duration = ?,
                rating = ?,
                release_date = ?,
                poster_image = ?,
                trailer = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$update) {

            $error = "Database error: " . $conn->error;

        } else {

            $update->bind_param(
                "ssssisssssi",
                $name,
                $description,
                $genre,
                $language,
                $duration,
                $rating,
                $release_date,
                $poster_image,
                $trailer,
                $status,
                $movie_id
            );

            if ($update->execute()) {

                $message = "Movie updated successfully!";

                /* Update displayed values */

                $movie['name'] = $name;
                $movie['description'] = $description;
                $movie['genre'] = $genre;
                $movie['language'] = $language;
                $movie['duration'] = $duration;
                $movie['rating'] = $rating;
                $movie['release_date'] = $release_date;
                $movie['poster_image'] = $poster_image;
                $movie['trailer'] = $trailer;
                $movie['status'] = $status;

            } else {

                $error = "Failed to update movie: "
                    . $update->error;

            }

            $update->close();
        }
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

<title>Edit Movie | TicketFlix</title>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

/* ================================
   GLOBAL
================================ */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family: 'Poppins', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(126,87,194,.22),
            transparent 35%
        ),
        radial-gradient(
            circle at bottom left,
            rgba(212,175,55,.08),
            transparent 35%
        ),
        #100b18;

    color: white;

}

/* ================================
   SIDEBAR
================================ */

.sidebar {

    width: 250px;

    height: 100vh;

    position: fixed;

    left: 0;
    top: 0;

    background: #120c1c;

    border-right:
        1px solid
        rgba(212,175,55,.18);

    padding: 30px 18px;

}

.logo {

    text-align: center;

    font-size: 26px;

    font-weight: 800;

    margin-bottom: 40px;

}

.logo i,
.logo span {

    color: #d4af37;

}

.admin-label {

    text-align: center;

    color: #888;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 2px;

    margin-bottom: 20px;

}

.sidebar a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 13px 15px;

    margin-bottom: 8px;

    color: #bbb;

    text-decoration: none;

    border-radius: 12px;

    font-size: 14px;

    transition: .3s;

}

.sidebar a:hover,
.sidebar a.active {

    background: rgba(212,175,55,.12);

    color: #d4af37;

}

.sidebar a i {

    width: 20px;

    text-align: center;

}

/* ================================
   MAIN
================================ */

.main {

    margin-left: 250px;

    padding: 35px;

}

/* ================================
   HEADER
================================ */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;

}

.page-header h1 {

    font-size: 28px;

}

.page-header h1 span {

    color: #d4af37;

}

.page-header p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;

}

.back-btn {

    text-decoration: none;

    color: #d4af37;

    border: 1px solid
        rgba(212,175,55,.3);

    padding: 10px 18px;

    border-radius: 10px;

    font-size: 13px;

}

.back-btn:hover {

    background:
        rgba(212,175,55,.1);

}

/* ================================
   FORM CARD
================================ */

.form-card {

    max-width: 950px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 30px;

}

/* ================================
   MESSAGE
================================ */

.success {

    background:
        rgba(46,204,113,.12);

    border:
        1px solid
        rgba(46,204,113,.3);

    color: #61e69b;

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

}

.error {

    background:
        rgba(231,76,60,.12);

    border:
        1px solid
        rgba(231,76,60,.3);

    color: #ff8175;

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

}

/* ================================
   FORM GRID
================================ */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

}

.full {

    grid-column: 1 / -1;

}

/* ================================
   FORM GROUP
================================ */

.form-group {

    display: flex;

    flex-direction: column;

}

.form-group label {

    color: #ccc;

    font-size: 12px;

    margin-bottom: 8px;

}

.form-group label span {

    color: #d4af37;

}

.form-group input,
.form-group textarea,
.form-group select {

    width: 100%;

    padding: 13px 15px;

    border-radius: 10px;

    border:
        1px solid
        rgba(255,255,255,.1);

    background:
        rgba(0,0,0,.25);

    color: white;

    outline: none;

    font-family: inherit;

    font-size: 13px;

}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {

    border-color: #d4af37;

}

.form-group textarea {

    min-height: 130px;

    resize: vertical;

}

.form-group select option {

    background: #181020;

    color: white;

}

/* ================================
   POSTER PREVIEW
================================ */

.poster-preview {

    margin-top: 10px;

    width: 120px;

    height: 160px;

    border-radius: 10px;

    overflow: hidden;

    border:
        1px solid
        rgba(212,175,55,.3);

}

.poster-preview img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}

/* ================================
   BUTTONS
================================ */

.form-buttons {

    display: flex;

    gap: 12px;

    margin-top: 25px;

}

.update-btn {

    border: none;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #a8841d
        );

    color: #171020;

    padding: 13px 25px;

    border-radius: 10px;

    font-family: inherit;

    font-weight: 700;

    cursor: pointer;

    font-size: 13px;

}

.update-btn:hover {

    transform: translateY(-2px);

}

.cancel-btn {

    text-decoration: none;

    padding: 13px 25px;

    border-radius: 10px;

    color: #ccc;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.1);

    font-size: 13px;

}

/* ================================
   RESPONSIVE
================================ */

@media(max-width:800px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;

    }

    .logo {

        font-size: 0;

    }

    .logo i {

        font-size: 23px;

    }

    .admin-label,
    .sidebar a span {

        display: none;

    }

    .sidebar a {

        justify-content: center;

    }

    .main {

        margin-left: 70px;

        padding: 20px;

    }

    .form-grid {

        grid-template-columns: 1fr;

    }

    .full {

        grid-column: auto;

    }

}

</style>

</head>

<body>


<!-- ================================
     SIDEBAR
================================ -->

<aside class="sidebar">

    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </div>

    <div class="admin-label">
        Admin Panel
    </div>

    <a href="dashboard.php">

        <i class="fa-solid fa-chart-line"></i>

        <span>Dashboard</span>

    </a>

    <a href="users.php">

        <i class="fa-solid fa-users"></i>

        <span>Users</span>

    </a>

    <a href="bookings.php">

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>

    <a
        href="manage_movies.php"
        class="active"
    >

        <i class="fa-solid fa-film"></i>

        <span>Movies</span>

    </a>

    <a href="../theaters.php">

        <i class="fa-solid fa-building"></i>

        <span>Theaters</span>

    </a>

    <a href="../showtimes.php">

        <i class="fa-solid fa-clock"></i>

        <span>Showtimes</span>

    </a>

    <a href="../index.php">

        <i class="fa-solid fa-globe"></i>

        <span>View Website</span>

    </a>

</aside>


<!-- ================================
     MAIN
================================ -->

<main class="main">


    <div class="page-header">

        <div>

            <h1>
                Edit <span>Movie</span> 🎬
            </h1>

            <p>
                Update movie information in TicketFlix.
            </p>

        </div>

        <a
            href="manage_movies.php"
            class="back-btn"
        >

            ← Back to Movies

        </a>

    </div>


    <div class="form-card">


        <?php if (!empty($message)): ?>

            <div class="success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="form-grid">


                <!-- MOVIE NAME -->

                <div class="form-group">

                    <label>
                        Movie Name <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($movie['name']); ?>"
                        required
                    >

                </div>


                <!-- GENRE -->

                <div class="form-group">

                    <label>
                        Genre <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="genre"
                        value="<?= htmlspecialchars($movie['genre']); ?>"
                        placeholder="Action, Comedy, Drama"
                        required
                    >

                </div>


                <!-- LANGUAGE -->

                <div class="form-group">

                    <label>
                        Language <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="language"
                        value="<?= htmlspecialchars($movie['language']); ?>"
                        required
                    >

                </div>


                <!-- DURATION -->

                <div class="form-group">

                    <label>
                        Duration (minutes) <span>*</span>
                    </label>

                    <input
                        type="number"
                        name="duration"
                        value="<?= htmlspecialchars($movie['duration']); ?>"
                        min="1"
                        required
                    >

                </div>


                <!-- RATING -->

                <div class="form-group">

                    <label>
                        Rating <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="rating"
                        value="<?= htmlspecialchars($movie['rating']); ?>"
                        placeholder="8.5/10"
                        required
                    >

                </div>


                <!-- RELEASE DATE -->

                <div class="form-group">

                    <label>
                        Release Date
                    </label>

                    <input
                        type="date"
                        name="release_date"
                        value="<?= htmlspecialchars($movie['release_date'] ?? ''); ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label>
                        Movie Status <span>*</span>
                    </label>

                    <select
                        name="status"
                        required
                    >

                        <option
                            value="coming_soon"
                            <?= $movie['status'] === 'coming_soon'
                                ? 'selected'
                                : ''; ?>
                        >
                            Coming Soon
                        </option>

                        <option
                            value="now_showing"
                            <?= $movie['status'] === 'now_showing'
                                ? 'selected'
                                : ''; ?>
                        >
                            Now Showing
                        </option>

                        <option
                            value="expired"
                            <?= $movie['status'] === 'expired'
                                ? 'selected'
                                : ''; ?>
                        >
                            Expired
                        </option>

                    </select>

                </div>


                <!-- POSTER IMAGE -->

                <div class="form-group">

                    <label>
                        Poster Image URL
                    </label>

                    <input
                        type="text"
                        name="poster_image"
                        value="<?= htmlspecialchars($movie['poster_image'] ?? ''); ?>"
                        placeholder="images/movie.jpg"
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter movie description..."
                    ><?= htmlspecialchars($movie['description'] ?? ''); ?></textarea>

                </div>


                <!-- TRAILER -->

                <div class="form-group full">

                    <label>
                        Trailer URL <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="trailer"
                        value="<?= htmlspecialchars($movie['trailer']); ?>"
                        placeholder="https://www.youtube.com/watch?v=..."
                        required
                    >

                </div>


            </div>


            <!-- POSTER PREVIEW -->

            <?php if (!empty($movie['poster_image'])): ?>

                <div class="poster-preview">

                    <img
                        src="<?= htmlspecialchars($movie['poster_image']); ?>"
                        alt="Movie Poster"
                        onerror="this.parentElement.style.display='none';"
                    >

                </div>

            <?php endif; ?>


            <!-- BUTTONS -->

            <div class="form-buttons">

                <button
                    type="submit"
                    class="update-btn"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Update Movie

                </button>


                <a
                    href="manage_movies.php"
                    class="cancel-btn"
                >

                    Cancel

                </a>

            </div>


        </form>


    </div>


</main>

</body>

</html>