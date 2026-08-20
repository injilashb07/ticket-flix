<?php

session_start();

require_once "../config.php";

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit();
}


/* =====================================================
   DELETE MOVIE
===================================================== */

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $movie_id = (int) $_GET['delete'];

    /* ---------------------------------------------
       GET POSTER BEFORE DELETE
    --------------------------------------------- */

    $stmt = $conn->prepare("
        SELECT poster_image
        FROM movies
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $movie_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $movie = $result->fetch_assoc();

    $stmt->close();


    /* ---------------------------------------------
       DELETE MOVIE
    --------------------------------------------- */

    $delete = $conn->prepare("
        DELETE FROM movies
        WHERE id = ?
    ");

    $delete->bind_param("i", $movie_id);

    if ($delete->execute()) {

        /* -----------------------------------------
           DELETE LOCAL POSTER FILE
        ----------------------------------------- */

        if (
            !empty($movie['poster_image']) &&
            strpos(
                $movie['poster_image'],
                "uploads/posters/"
            ) === 0
        ) {

            $poster_file =
                __DIR__ .
                "/../" .
                $movie['poster_image'];

            if (file_exists($poster_file)) {
                unlink($poster_file);
            }
        }
    }

    $delete->close();


    header("Location: manage_movies.php?deleted=1");
    exit();
}


/* =====================================================
   SUCCESS / ERROR MESSAGE
===================================================== */

$message = "";
$error = "";

if (isset($_GET['deleted'])) {
    $message = "Movie deleted successfully.";
}


/* =====================================================
   GET ALL MOVIES
===================================================== */

$sql = "
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
    ORDER BY id DESC
";

$result = $conn->query($sql);

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


<!-- GOOGLE FONT -->

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<!-- FONT AWESOME -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =====================================================
   GLOBAL
===================================================== */

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


/* =====================================================
   SIDEBAR
===================================================== */

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

    overflow-y: auto;
}


.logo {

    text-align: center;

    font-size: 26px;

    font-weight: 800;

    margin-bottom: 40px;
}


.logo i {

    color: #d4af37;

    margin-right: 5px;
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


.sidebar a:hover,
.sidebar a.active {

    background:
        rgba(212,175,55,.12);

    color: #d4af37;
}


.sidebar a i {

    width: 20px;

    text-align: center;
}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 250px;

    padding: 35px;

    min-height: 100vh;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}


.page-header h1 {

    font-size: 30px;
}


.page-header h1 span {

    color: #d4af37;
}


.page-header p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;
}


/* =====================================================
   ADD MOVIE BUTTON
===================================================== */

.add-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    text-decoration: none;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #a8841d
        );

    color: #171020;

    padding: 12px 20px;

    border-radius: 10px;

    font-size: 13px;

    font-weight: 700;

    transition: .3s;
}


.add-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(212,175,55,.25);
}


/* =====================================================
   MESSAGE
===================================================== */

.message {

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


/* =====================================================
   MOVIE TABLE CARD
===================================================== */

.table-card {

    width: 100%;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius: 20px;

    overflow: hidden;
}


/* =====================================================
   TABLE WRAPPER
===================================================== */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


/* =====================================================
   TABLE
===================================================== */

.movie-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}


.movie-table th {

    background:
        rgba(212,175,55,.10);

    color: #d4af37;

    padding: 17px 15px;

    text-align: left;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .5px;

    white-space: nowrap;
}


.movie-table td {

    padding: 15px;

    border-top:
        1px solid
        rgba(255,255,255,.06);

    color: #ddd;

    font-size: 13px;

    vertical-align: middle;
}


.movie-table tr:hover {

    background:
        rgba(255,255,255,.025);
}


/* =====================================================
   POSTER
===================================================== */

.poster {

    width: 60px;

    height: 82px;

    border-radius: 8px;

    overflow: hidden;

    background: #24172f;

    border:
        1px solid
        rgba(212,175,55,.25);

    display: flex;

    align-items: center;

    justify-content: center;
}


.poster img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}


.no-poster {

    color: #777;

    font-size: 22px;
}


/* =====================================================
   MOVIE NAME
===================================================== */

.movie-name {

    color: white;

    font-weight: 600;

    max-width: 200px;
}


.movie-description {

    color: #888;

    font-size: 11px;

    margin-top: 4px;

    max-width: 220px;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;
}


/* =====================================================
   TAG
===================================================== */

.genre-tag {

    display: inline-block;

    color: #d4af37;

    border:
        1px solid
        rgba(212,175,55,.25);

    background:
        rgba(212,175,55,.07);

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;
}


/* =====================================================
   RATING
===================================================== */

.rating {

    color: #f4c430;

    font-weight: 600;
}


/* =====================================================
   STATUS
===================================================== */

.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;
}


.status.now {

    color: #61e69b;

    background:
        rgba(46,204,113,.12);

    border:
        1px solid
        rgba(46,204,113,.25);
}


.status.coming {

    color: #d4af37;

    background:
        rgba(212,175,55,.12);

    border:
        1px solid
        rgba(212,175,55,.25);
}


.status.expired {

    color: #ff8175;

    background:
        rgba(231,76,60,.12);

    border:
        1px solid
        rgba(231,76,60,.25);
}


/* =====================================================
   ACTION BUTTONS
===================================================== */

.actions {

    display: flex;

    gap: 7px;
}


.action-btn {

    width: 34px;

    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    text-decoration: none;

    transition: .3s;
}


.edit-btn {

    color: #d4af37;

    background:
        rgba(212,175,55,.10);

    border:
        1px solid
        rgba(212,175,55,.20);
}


.edit-btn:hover {

    background:
        rgba(212,175,55,.20);
}


.delete-btn {

    color: #ff8175;

    background:
        rgba(231,76,60,.10);

    border:
        1px solid
        rgba(231,76,60,.20);
}


.delete-btn:hover {

    background:
        rgba(231,76,60,.20);
}


/* =====================================================
   EMPTY
===================================================== */

.empty {

    text-align: center;

    padding: 70px 20px;

    color: #888;
}


.empty i {

    font-size: 50px;

    color: #d4af37;

    margin-bottom: 15px;
}


.empty h2 {

    color: #ddd;

    font-size: 20px;

    margin-bottom: 8px;
}


.empty p {

    font-size: 13px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width: 800px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;
    }


    .logo {

        font-size: 0;
    }


    .logo i {

        font-size: 23px;

        margin: 0;
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


    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 18px;
    }

}


@media(max-width: 500px) {

    .main {

        padding: 15px;
    }


    .page-header h1 {

        font-size: 24px;
    }


    .add-btn {

        width: 100%;

        justify-content: center;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

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



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h1>

                Manage <span>Movies</span> 🎬

            </h1>

            <p>

                Add, edit and manage movies in TicketFlix.

            </p>

        </div>


        <!-- IMPORTANT ADD MOVIE BUTTON -->

        <a
            href="add_movie.php"
            class="add-btn"
        >

            <i class="fa-solid fa-plus"></i>

            Add Movie

        </a>

    </div>



    <!-- =================================================
         SUCCESS MESSAGE
    ================================================== -->

    <?php if (!empty($message)): ?>

        <div class="message">

            <i class="fa-solid fa-circle-check"></i>

            <?= htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         MOVIE TABLE
    ================================================== -->

    <div class="table-card">

        <?php if ($result && $result->num_rows > 0): ?>


            <div class="table-wrapper">

                <table class="movie-table">


                    <thead>

                        <tr>

                            <th>
                                Poster
                            </th>

                            <th>
                                Movie
                            </th>

                            <th>
                                Genre
                            </th>

                            <th>
                                Language
                            </th>

                            <th>
                                Duration
                            </th>

                            <th>
                                Rating
                            </th>

                            <th>
                                Release Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php while ($row = $result->fetch_assoc()): ?>


                        <tr>


                            <!-- =================================
                                 POSTER
                            ================================== -->

                            <td>

                                <div class="poster">


                                    <?php

                                    /*
                                     * IMPORTANT:
                                     *
                                     * Database value:
                                     * uploads/posters/movie_xxx.jpg
                                     *
                                     * manage_movies.php is inside
                                     * admin folder.
                                     *
                                     * Therefore ../ is required.
                                     */

                                    $poster =
                                        trim(
                                            $row['poster_image'] ?? ''
                                        );

                                    ?>


                                    <?php if (!empty($poster)): ?>

                                        <img
                                            src="../<?= htmlspecialchars($poster); ?>"
                                            alt="<?= htmlspecialchars($row['name']); ?>"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >

                                        <div
                                            class="no-poster"
                                            style="display:none;"
                                        >

                                            <i class="fa-solid fa-film"></i>

                                        </div>

                                    <?php else: ?>

                                        <div class="no-poster">

                                            <i class="fa-solid fa-film"></i>

                                        </div>

                                    <?php endif; ?>


                                </div>

                            </td>



                            <!-- =================================
                                 MOVIE
                            ================================== -->

                            <td>

                                <div class="movie-name">

                                    <?= htmlspecialchars(
                                        $row['name']
                                    ); ?>

                                </div>


                                <?php if (!empty($row['description'])): ?>

                                    <div class="movie-description">

                                        <?= htmlspecialchars(
                                            $row['description']
                                        ); ?>

                                    </div>

                                <?php endif; ?>

                            </td>



                            <!-- =================================
                                 GENRE
                            ================================== -->

                            <td>

                                <span class="genre-tag">

                                    <?= htmlspecialchars(
                                        $row['genre'] ?? 'N/A'
                                    ); ?>

                                </span>

                            </td>



                            <!-- =================================
                                 LANGUAGE
                            ================================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['language'] ?? 'N/A'
                                ); ?>

                            </td>



                            <!-- =================================
                                 DURATION
                            ================================== -->

                            <td>

                                <?= (int)(
                                    $row['duration'] ?? 0
                                ); ?>

                                min

                            </td>



                            <!-- =================================
                                 RATING
                            ================================== -->

                            <td>

                                <span class="rating">

                                    ⭐

                                    <?= htmlspecialchars(
                                        $row['rating'] ?? 'N/A'
                                    ); ?>

                                </span>

                            </td>



                            <!-- =================================
                                 RELEASE DATE
                            ================================== -->

                            <td>

                                <?php

                                if (!empty($row['release_date'])) {

                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $row['release_date']
                                        )
                                    );

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </td>



                            <!-- =================================
                                 STATUS
                            ================================== -->

                            <td>


                                <?php

                                $status =
                                    $row['status'] ?? 'coming_soon';


                                if ($status === 'now_showing'):

                                ?>

                                    <span class="status now">

                                        Now Showing

                                    </span>


                                <?php elseif ($status === 'expired'): ?>

                                    <span class="status expired">

                                        Expired

                                    </span>


                                <?php else: ?>

                                    <span class="status coming">

                                        Coming Soon

                                    </span>

                                <?php endif; ?>


                            </td>



                            <!-- =================================
                                 ACTIONS
                            ================================== -->

                            <td>

                                <div class="actions">


                                    <!-- EDIT -->

                                    <a
                                        href="edit_movie.php?id=<?= (int)$row['id']; ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Movie"
                                    >

                                        <i class="fa-solid fa-pen"></i>

                                    </a>



                                    <!-- DELETE -->

                                    <a
                                        href="manage_movies.php?delete=<?= (int)$row['id']; ?>"
                                        class="action-btn delete-btn"
                                        title="Delete Movie"
                                        onclick="return confirm('Are you sure you want to delete this movie?');"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>


                </table>

            </div>


        <?php else: ?>


            <!-- =============================================
                 NO MOVIES
            ============================================== -->

            <div class="empty">

                <i class="fa-solid fa-film"></i>

                <h2>
                    No Movies Found
                </h2>

                <p>
                    Add your first movie to TicketFlix.
                </p>

                <br>

                <a
                    href="add_movie.php"
                    class="add-btn"
                >

                    <i class="fa-solid fa-plus"></i>

                    Add Movie

                </a>

            </div>


        <?php endif; ?>


    </div>


</main>


</body>

</html>