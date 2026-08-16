```php
<?php

session_start();

require_once "../config.php";


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION['admin_id'])) {

    header("Location: ../admin_login.php");
    exit();

}


/* =========================================================
   MARK MOVIE AS EXPIRED
========================================================= */

if (isset($_GET['expire'])) {

    $movie_id = intval($_GET['expire']);

    if ($movie_id > 0) {

        $stmt = $conn->prepare(
            "UPDATE movies SET status = 'expired' WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $stmt->close();

        }

    }

    header("Location: manage_movies.php?expired=1");
    exit();

}


/* =========================================================
   UPDATE MOVIE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_movie'])
) {

    $movie_id = intval($_POST['movie_id']);

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $genre = trim($_POST['genre']);
    $language = trim($_POST['language']);
    $duration = intval($_POST['duration']);
    $rating = trim($_POST['rating']);

    $release_date = !empty($_POST['release_date'])
        ? $_POST['release_date']
        : null;

    $poster_image = trim($_POST['poster_image']);
    $trailer = trim($_POST['trailer']);
    $status = $_POST['status'];


    $stmt = $conn->prepare("
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


    if ($stmt) {

        $stmt->bind_param(
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


        if ($stmt->execute()) {

            $stmt->close();

            header(
                "Location: manage_movies.php?updated=1"
            );

            exit();

        }

        $stmt->close();

    }

}


/* =========================================================
   EDIT MOVIE DATA
========================================================= */

$edit_movie = null;


if (isset($_GET['edit'])) {

    $edit_id = intval($_GET['edit']);

    if ($edit_id > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM movies
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param("i", $edit_id);

            $stmt->execute();

            $result = $stmt->get_result();

            $edit_movie = $result->fetch_assoc();

            $stmt->close();

        }

    }

}


/* =========================================================
   SEARCH MOVIES
========================================================= */

$search = "";

if (isset($_GET['search'])) {

    $search = trim($_GET['search']);

}


if ($search !== "") {

    $search_safe = "%" . $search . "%";


    $stmt = $conn->prepare("
        SELECT *
        FROM movies
        WHERE
            name LIKE ?
            OR genre LIKE ?
            OR language LIKE ?
            OR status LIKE ?
        ORDER BY id DESC
    ");


    $stmt->bind_param(
        "ssss",
        $search_safe,
        $search_safe,
        $search_safe,
        $search_safe
    );


    $stmt->execute();

    $movies_result = $stmt->get_result();

    $stmt->close();


} else {

    $movies_result = $conn->query("
        SELECT *
        FROM movies
        ORDER BY id DESC
    ");

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

<title>Manage Movies | TicketFlix</title>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

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

        #100b18;

    color: white;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    height: 100vh;

    position: fixed;

    left: 0;

    top: 0;

    background: rgba(18,12,28,.98);

    border-right:
        1px solid rgba(212,175,55,.18);

    padding: 30px 18px;

    z-index: 100;

}


.logo {

    text-align: center;

    font-size: 26px;

    font-weight: 800;

    margin-bottom: 40px;

}


.logo i {

    color: #d4af37;

}


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


.sidebar a i {

    width: 20px;

    text-align: center;

}


.sidebar a:hover,
.sidebar a.active {

    background: rgba(212,175,55,.12);

    color: #d4af37;

}


.logout {

    position: absolute;

    bottom: 25px;

    left: 18px;

    right: 18px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    padding: 35px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

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


/* =========================================================
   BUTTONS
========================================================= */

.btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 11px 18px;

    border-radius: 10px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-family: inherit;

    font-size: 12px;

    font-weight: 600;

    transition: .3s;

}


.btn-gold {

    background: #d4af37;

    color: #171020;

}


.btn-gold:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px rgba(212,175,55,.25);

}


.btn-purple {

    background:
        linear-gradient(
            135deg,
            #7e3ff2,
            #a35cff
        );

    color: white;

}


.btn-purple:hover {

    transform: translateY(-2px);

}


.btn-edit {

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

}


.btn-expire {

    background:
        rgba(231,76,60,.12);

    color: #ff8175;

}


.btn-view {

    background:
        rgba(126,87,194,.15);

    color: #b58cff;

}


/* =========================================================
   ALERT
========================================================= */

.alert {

    padding: 14px 18px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 13px;

}


.alert-success {

    background:
        rgba(46,204,113,.10);

    border:
        1px solid rgba(46,204,113,.25);

    color: #61e69b;

}


/* =========================================================
   SEARCH
========================================================= */

.search-panel {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    padding: 20px;

    border-radius: 18px;

    margin-bottom: 22px;

}


.search-form {

    display: flex;

    gap: 10px;

}


.search-form input {

    flex: 1;

    padding: 13px 16px;

    border-radius: 10px;

    border:
        1px solid rgba(255,255,255,.1);

    background:
        rgba(0,0,0,.2);

    color: white;

    outline: none;

    font-family: inherit;

}


.search-form input:focus {

    border-color: #d4af37;

}


.search-form button {

    padding: 0 22px;

}


/* =========================================================
   TABLE PANEL
========================================================= */

.panel {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 20px;

    padding: 22px;

}


.table-wrapper {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    text-align: left;

    color: #777;

    font-size: 11px;

    padding: 14px 10px;

    border-bottom:
        1px solid rgba(255,255,255,.08);

    white-space: nowrap;

}


td {

    padding: 15px 10px;

    font-size: 12px;

    color: #ccc;

    border-bottom:
        1px solid rgba(255,255,255,.05);

    vertical-align: middle;

}


.movie-info {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 200px;

}


.poster {

    width: 48px;

    height: 65px;

    object-fit: cover;

    border-radius: 7px;

    border:
        1px solid rgba(212,175,55,.2);

}


.movie-name {

    color: white;

    font-weight: 600;

}


.movie-id {

    color: #777;

    font-size: 10px;

    margin-top: 3px;

}


.status {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;

}


.status-now {

    color: #61e69b;

    background:
        rgba(46,204,113,.10);

}


.status-coming {

    color: #f1d46a;

    background:
        rgba(241,196,15,.10);

}


.status-expired {

    color: #ff8175;

    background:
        rgba(231,76,60,.10);

}


.actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

}


.action-btn {

    width: 35px;

    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    text-decoration: none;

    transition: .3s;

}


.action-btn:hover {

    transform: translateY(-2px);

}


/* =========================================================
   EDIT FORM
========================================================= */

.edit-panel {

    margin-bottom: 25px;

    border:
        1px solid rgba(212,175,55,.25);

}


.edit-title {

    font-size: 18px;

    margin-bottom: 20px;

}


.edit-title span {

    color: #d4af37;

}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 16px;

}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;

}


.form-group.full {

    grid-column: 1 / -1;

}


.form-group label {

    color: #aaa;

    font-size: 11px;

}


.form-group input,
.form-group textarea,
.form-group select {

    width: 100%;

    padding: 12px 14px;

    border-radius: 9px;

    border:
        1px solid rgba(255,255,255,.1);

    background:
        rgba(0,0,0,.2);

    color: white;

    outline: none;

    font-family: inherit;

}


.form-group textarea {

    min-height: 100px;

    resize: vertical;

}


.form-group select option {

    background: #21152e;

    color: white;

}


.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {

    border-color: #d4af37;

}


.form-buttons {

    margin-top: 20px;

    display: flex;

    gap: 10px;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 50px 20px;

    color: #777;

}


.empty i {

    font-size: 40px;

    color: #d4af37;

    margin-bottom: 12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .form-grid {

        grid-template-columns: 1fr;

    }

}


@media(max-width:700px) {

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


    .logout {

        left: 10px;

        right: 10px;

    }


    .main {

        margin-left: 70px;

        padding: 20px;

    }


    .page-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;

    }


    .search-form {

        flex-direction: column;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

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


    <a href="manage_movies.php" class="active">

        <i class="fa-solid fa-film"></i>

        <span>Manage Movies</span>

    </a>


    <a href="add_movie.php">

        <i class="fa-solid fa-circle-plus"></i>

        <span>Add Movie</span>

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


    <a
        href="../logout.php"
        class="logout"
    >

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>Logout</span>

    </a>


</aside>



<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- PAGE HEADER -->

    <div class="page-header">


        <div>

            <h1>

                Manage <span>Movies</span> 🎬

            </h1>


            <p>

                Add, edit, expire and search TicketFlix movies.

            </p>

        </div>


        <a
            href="add_movie.php"
            class="btn btn-gold"
        >

            <i class="fa-solid fa-plus"></i>

            Add Movie

        </a>


    </div>



    <!-- =====================================================
         SUCCESS MESSAGES
    ====================================================== -->

    <?php if (isset($_GET['updated'])) { ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            Movie updated successfully!

        </div>

    <?php } ?>


    <?php if (isset($_GET['expired'])) { ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check"></i>

            Movie marked as expired successfully!

        </div>

    <?php } ?>



    <!-- =====================================================
         EDIT MOVIE
    ====================================================== -->

    <?php if ($edit_movie) { ?>


        <div class="panel edit-panel">


            <h2 class="edit-title">

                Edit <span>Movie</span>

            </h2>


            <form method="POST">


                <input
                    type="hidden"
                    name="movie_id"
                    value="<?php echo $edit_movie['id']; ?>"
                >


                <div class="form-grid">


                    <!-- NAME -->

                    <div class="form-group">

                        <label>Movie Name</label>

                        <input
                            type="text"
                            name="name"
                            required
                            value="<?php echo htmlspecialchars($edit_movie['name']); ?>"
                        >

                    </div>


                    <!-- GENRE -->

                    <div class="form-group">

                        <label>Genre</label>

                        <input
                            type="text"
                            name="genre"
                            required
                            value="<?php echo htmlspecialchars($edit_movie['genre']); ?>"
                        >

                    </div>


                    <!-- LANGUAGE -->

                    <div class="form-group">

                        <label>Language</label>

                        <input
                            type="text"
                            name="language"
                            required
                            value="<?php echo htmlspecialchars($edit_movie['language']); ?>"
                        >

                    </div>


                    <!-- DURATION -->

                    <div class="form-group">

                        <label>Duration (minutes)</label>

                        <input
                            type="number"
                            name="duration"
                            min="1"
                            required
                            value="<?php echo htmlspecialchars($edit_movie['duration']); ?>"
                        >

                    </div>


                    <!-- RATING -->

                    <div class="form-group">

                        <label>Rating</label>

                        <input
                            type="text"
                            name="rating"
                            required
                            value="<?php echo htmlspecialchars($edit_movie['rating']); ?>"
                        >

                    </div>


                    <!-- RELEASE DATE -->

                    <div class="form-group">

                        <label>Release Date</label>

                        <input
                            type="date"
                            name="release_date"
                            value="<?php echo htmlspecialchars($edit_movie['release_date'] ?? ''); ?>"
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label>Status</label>

                        <select name="status" required>

                            <option
                                value="coming_soon"
                                <?php
                                echo ($edit_movie['status'] == 'coming_soon')
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Coming Soon
                            </option>


                            <option
                                value="now_showing"
                                <?php
                                echo ($edit_movie['status'] == 'now_showing')
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Now Showing
                            </option>


                            <option
                                value="expired"
                                <?php
                                echo ($edit_movie['status'] == 'expired')
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Expired
                            </option>

                        </select>

                    </div>


                    <!-- POSTER -->

                    <div class="form-group">

                        <label>Poster Image URL</label>

                        <input
                            type="text"
                            name="poster_image"
                            value="<?php echo htmlspecialchars($edit_movie['poster_image'] ?? ''); ?>"
                        >

                    </div>


                    <!-- TRAILER -->

                    <div class="form-group full">

                        <label>Trailer URL</label>

                        <input
                            type="text"
                            name="trailer"
                            value="<?php echo htmlspecialchars($edit_movie['trailer'] ?? ''); ?>"
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="form-group full">

                        <label>Description</label>

                        <textarea
                            name="description"
                        ><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>

                    </div>


                </div>


                <div class="form-buttons">


                    <button
                        type="submit"
                        name="update_movie"
                        class="btn btn-gold"
                    >

                        <i class="fa-solid fa-save"></i>

                        Update Movie

                    </button>


                    <a
                        href="manage_movies.php"
                        class="btn btn-purple"
                    >

                        Cancel

                    </a>


                </div>


            </form>


        </div>


    <?php } ?>



    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <div class="search-panel">


        <form
            method="GET"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                placeholder="Search movie, genre, language or status..."
                value="<?php echo htmlspecialchars($search); ?>"
            >


            <button
                type="submit"
                class="btn btn-gold"
            >

                <i class="fa-solid fa-magnifying-glass"></i>

                Search

            </button>


            <?php if ($search !== "") { ?>

                <a
                    href="manage_movies.php"
                    class="btn btn-purple"
                >

                    Clear

                </a>

            <?php } ?>


        </form>


    </div>



    <!-- =====================================================
         MOVIES TABLE
    ====================================================== -->

    <div class="panel">


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Movie</th>

                        <th>Genre</th>

                        <th>Language</th>

                        <th>Duration</th>

                        <th>Rating</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php

                if (
                    $movies_result &&
                    $movies_result->num_rows > 0
                ) {


                    while (
                        $movie =
                        $movies_result->fetch_assoc()
                    ) {

                ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            #<?php echo $movie['id']; ?>

                        </td>



                        <!-- MOVIE -->

                        <td>


                            <div class="movie-info">


                                <?php

                                $poster =
                                    !empty($movie['poster_image'])
                                    ? $movie['poster_image']
                                    : "https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=200&q=80";

                                ?>


                                <img
                                    src="<?php echo htmlspecialchars($poster); ?>"
                                    class="poster"
                                    alt="Movie Poster"
                                >


                                <div>


                                    <div class="movie-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $movie['name']
                                        );
                                        ?>

                                    </div>


                                    <div class="movie-id">

                                        Movie ID:
                                        <?php echo $movie['id']; ?>

                                    </div>


                                </div>


                            </div>


                        </td>



                        <!-- GENRE -->

                        <td>

                            <?php
                            echo htmlspecialchars(
                                $movie['genre']
                            );
                            ?>

                        </td>



                        <!-- LANGUAGE -->

                        <td>

                            <?php
                            echo htmlspecialchars(
                                $movie['language']
                            );
                            ?>

                        </td>



                        <!-- DURATION -->

                        <td>

                            <?php
                            echo htmlspecialchars(
                                $movie['duration']
                            );
                            ?>

                            min

                        </td>



                        <!-- RATING -->

                        <td>

                            <span style="color:#d4af37;">

                                ★

                            </span>

                            <?php
                            echo htmlspecialchars(
                                $movie['rating']
                            );
                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>


                            <?php

                            if (
                                $movie['status']
                                == 'now_showing'
                            ) {

                                echo '
                                <span class="status status-now">
                                    Now Showing
                                </span>
                                ';

                            }

                            elseif (
                                $movie['status']
                                == 'coming_soon'
                            ) {

                                echo '
                                <span class="status status-coming">
                                    Coming Soon
                                </span>
                                ';

                            }

                            else {

                                echo '
                                <span class="status status-expired">
                                    Expired
                                </span>
                                ';

                            }

                            ?>


                        </td>



                        <!-- ACTIONS -->

                        <td>


                            <div class="actions">


                                <!-- VIEW -->

                                <a
                                    href="../movie_details.php?id=<?php echo $movie['id']; ?>"
                                    class="action-btn btn-view"
                                    title="View Movie"
                                >

                                    <i class="fa-solid fa-eye"></i>

                                </a>


                                <!-- EDIT -->

                                <a
                                    href="manage_movies.php?edit=<?php echo $movie['id']; ?>"
                                    class="action-btn btn-edit"
                                    title="Edit Movie"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                </a>


                                <!-- MARK EXPIRED -->

                                <?php if ($movie['status'] != 'expired') { ?>

                                    <a
                                        href="manage_movies.php?expire=<?php echo $movie['id']; ?>"
                                        class="action-btn btn-expire"
                                        title="Mark as Expired"
                                        onclick="
                                            return confirm(
                                                'Are you sure you want to mark this movie as expired?'
                                            );
                                        "
                                    >

                                        <i class="fa-solid fa-clock-rotate-left"></i>

                                    </a>

                                <?php } else { ?>

                                    <span
                                        class="action-btn btn-expire"
                                        title="Already Expired"
                                        style="opacity:.45; cursor:not-allowed;"
                                    >

                                        <i class="fa-solid fa-circle-check"></i>

                                    </span>

                                <?php } ?>


                            </div>


                        </td>


                    </tr>


                <?php

                    }

                } else {

                ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            <i class="fa-solid fa-film"></i>

                            <br>

                            No movies found.

                        </td>

                    </tr>


                <?php

                }

                ?>


                </tbody>


            </table>


        </div>


    </div>


</main>


</body>

</html>
```
