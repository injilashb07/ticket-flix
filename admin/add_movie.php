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
   GET MOVIE ID
===================================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_movies.php");
    exit();
}

$movie_id = (int) $_GET['id'];

$message = "";
$error = "";


/* =====================================================
   FETCH MOVIE
===================================================== */

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


/* =====================================================
   MOVIE NOT FOUND
===================================================== */

if (!$movie) {
    die("Movie not found.");
}


/* =====================================================
   UPDATE MOVIE
===================================================== */

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

    $trailer = trim($_POST['trailer'] ?? "");

    $status = $_POST['status'] ?? "coming_soon";


    /* Keep old poster if no new file selected */

    $poster_image = $movie['poster_image'] ?? "";


    /* =================================================
       VALIDATION
    ================================================= */

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
            [
                'coming_soon',
                'now_showing',
                'expired'
            ]
        )
    ) {

        $error = "Invalid movie status.";

    } else {


        /* =================================================
           POSTER FILE UPLOAD
        ================================================= */

        if (
            isset($_FILES['poster_image']) &&
            $_FILES['poster_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            /* ---------------------------------------------
               CHECK UPLOAD ERROR
            --------------------------------------------- */

            if (
                $_FILES['poster_image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    "There was an error uploading the poster.";

            }


            /* ---------------------------------------------
               CHECK FILE SIZE
            --------------------------------------------- */

            elseif (
                $_FILES['poster_image']['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    "Poster image must be less than 5MB.";

            }


            else {

                /* -----------------------------------------
                   ALLOWED IMAGE TYPES
                ----------------------------------------- */

                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];


                /* Detect actual MIME type */

                $file_type = mime_content_type(
                    $_FILES['poster_image']['tmp_name']
                );


                if (!in_array($file_type, $allowed_types)) {

                    $error =
                        "Only JPG, JPEG, PNG and WEBP images are allowed.";

                }

                else {

                    /* -------------------------------------
                       GET FILE EXTENSION
                    ------------------------------------- */

                    $extension = strtolower(
                        pathinfo(
                            $_FILES['poster_image']['name'],
                            PATHINFO_EXTENSION
                        )
                    );


                    /* -------------------------------------
                       CREATE UNIQUE FILE NAME
                    ------------------------------------- */

                    $new_file_name =
                        "movie_" .
                        $movie_id .
                        "_" .
                        time() .
                        "." .
                        $extension;


                    /* -------------------------------------
                       UPLOAD DIRECTORY
                    ------------------------------------- */

                    $upload_directory =
                        __DIR__ .
                        "/../uploads/posters/";


                    /* Create folder if not exists */

                    if (!is_dir($upload_directory)) {

                        mkdir(
                            $upload_directory,
                            0777,
                            true
                        );
                    }


                    /* -------------------------------------
                       COMPLETE FILE PATH
                    ------------------------------------- */

                    $upload_path =
                        $upload_directory .
                        $new_file_name;


                    /* -------------------------------------
                       MOVE FILE
                    ------------------------------------- */

                    if (
                        move_uploaded_file(
                            $_FILES['poster_image']['tmp_name'],
                            $upload_path
                        )
                    ) {

                        /*
                         * Delete old poster
                         * only if it is our local upload
                         */

                        if (
                            !empty($movie['poster_image']) &&
                            strpos(
                                $movie['poster_image'],
                                'uploads/posters/'
                            ) === 0
                        ) {

                            $old_file =
                                __DIR__ .
                                "/../" .
                                $movie['poster_image'];


                            if (file_exists($old_file)) {

                                unlink($old_file);
                            }
                        }


                        /* Save new database path */

                        $poster_image =
                            "uploads/posters/" .
                            $new_file_name;

                    }

                    else {

                        $error =
                            "Failed to save poster image.";
                    }
                }
            }
        }


        /* =================================================
           UPDATE DATABASE
        ================================================= */

        if (empty($error)) {

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

                $error =
                    "Database error: " .
                    $conn->error;

            }

            else {

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

                    $message =
                        "Movie updated successfully!";


                    /* Update displayed values */

                    $movie['name'] = $name;

                    $movie['description'] =
                        $description;

                    $movie['genre'] =
                        $genre;

                    $movie['language'] =
                        $language;

                    $movie['duration'] =
                        $duration;

                    $movie['rating'] =
                        $rating;

                    $movie['release_date'] =
                        $release_date;

                    $movie['poster_image'] =
                        $poster_image;

                    $movie['trailer'] =
                        $trailer;

                    $movie['status'] =
                        $status;

                }

                else {

                    $error =
                        "Failed to update movie: " .
                        $update->error;
                }


                $update->close();
            }
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


<!-- =================================================
     GOOGLE FONT
================================================= -->

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<!-- =================================================
     FONT AWESOME
================================================= -->

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
   HEADER
===================================================== */

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

    border:
        1px solid
        rgba(212,175,55,.3);

    padding: 10px 18px;

    border-radius: 10px;

    font-size: 13px;

    transition: .3s;
}


.back-btn:hover {

    background:
        rgba(212,175,55,.1);
}


/* =====================================================
   FORM CARD
===================================================== */

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


/* =====================================================
   MESSAGE
===================================================== */

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


/* =====================================================
   FORM GRID
===================================================== */

.form-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}


.full {

    grid-column: 1 / -1;
}


/* =====================================================
   FORM GROUP
===================================================== */

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


/* =====================================================
   INPUTS
===================================================== */

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


/* =====================================================
   CHOOSE FILE
===================================================== */

#poster_image {

    width: 100%;

    padding: 12px;

    border:
        1px solid
        rgba(212,175,55,.25);

    border-radius: 10px;

    background:
        rgba(0,0,0,.25);

    color: #ccc;

    cursor: pointer;

    font-family: inherit;

    font-size: 13px;
}


#poster_image:hover {

    border-color: #d4af37;
}


/* Browser choose file button */

#poster_image::file-selector-button {

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #a8841d
        );

    color: #171020;

    border: none;

    padding: 9px 16px;

    border-radius: 7px;

    font-family: inherit;

    font-weight: 700;

    cursor: pointer;

    margin-right: 12px;
}


#poster_image::file-selector-button:hover {

    background: #e2bd45;
}


/* =====================================================
   FILE INFORMATION
===================================================== */

.file-info {

    color: #888;

    font-size: 11px;

    margin-top: 7px;

    line-height: 1.6;
}


.selected-file {

    color: #d4af37;

    font-size: 12px;

    margin-top: 7px;

    display: none;
}


/* =====================================================
   POSTER SECTION
===================================================== */

.poster-section {

    margin-top: 25px;
}


.poster-label {

    color: #ccc;

    font-size: 12px;

    margin-bottom: 10px;

    display: block;
}


.poster-preview {

    width: 140px;

    height: 190px;

    border-radius: 12px;

    overflow: hidden;

    border:
        1px solid
        rgba(212,175,55,.35);

    background: #0c0812;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.3);
}


.poster-preview img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}


/* =====================================================
   BUTTONS
===================================================== */

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

    transition: .3s;
}


.update-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(212,175,55,.2);
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

    transition: .3s;
}


.cancel-btn:hover {

    background:
        rgba(255,255,255,.1);

    color: white;
}


/* =====================================================
   RESPONSIVE
===================================================== */

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


    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }


    .form-grid {

        grid-template-columns: 1fr;

    }


    .full {

        grid-column: auto;

    }

}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:500px) {

    .main {

        padding: 15px;

    }


    .form-card {

        padding: 20px;

        border-radius: 16px;

    }


    .page-header h1 {

        font-size: 23px;

    }


    .form-buttons {

        flex-direction: column;

    }


    .update-btn,
    .cancel-btn {

        width: 100%;

        text-align: center;

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


    <!-- HEADER -->

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



    <!-- FORM CARD -->

    <div class="form-card">


        <!-- SUCCESS MESSAGE -->

        <?php if (!empty($message)): ?>

            <div class="success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>



        <!-- ERROR MESSAGE -->

        <?php if (!empty($error)): ?>

            <div class="error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             FORM
        ================================================= -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >


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



                <!-- =================================================
                     POSTER IMAGE - CHOOSE FILE
                ================================================= -->

                <div class="form-group">

                    <label>
                        Poster Image
                    </label>


                    <input
                        type="file"
                        id="poster_image"
                        name="poster_image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >


                    <div class="file-info">

                        <i class="fa-solid fa-circle-info"></i>

                        Choose poster from your computer.

                        <br>

                        JPG, JPEG, PNG or WEBP | Maximum 5MB

                    </div>


                    <div
                        id="selectedFile"
                        class="selected-file"
                    ></div>

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



            <!-- =================================================
                 POSTER PREVIEW
            ================================================= -->

            <?php if (!empty($movie['poster_image'])): ?>

                <div
                    class="poster-section"
                    id="previewSection"
                >

                    <span class="poster-label">

                        Current / Selected Poster

                    </span>


                    <div class="poster-preview">

                        <img
                            id="posterPreview"
                            src="../<?= htmlspecialchars($movie['poster_image']); ?>"
                            alt="Movie Poster"
                            onerror="this.style.display='none';"
                        >

                    </div>

                </div>

            <?php else: ?>

                <div
                    class="poster-section"
                    id="previewSection"
                    style="display:none;"
                >

                    <span class="poster-label">

                        Selected Poster

                    </span>


                    <div class="poster-preview">

                        <img
                            id="posterPreview"
                            src=""
                            alt="Movie Poster"
                        >

                    </div>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 BUTTONS
            ================================================= -->

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



<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

const posterInput =
    document.getElementById("poster_image");

const posterPreview =
    document.getElementById("posterPreview");

const selectedFile =
    document.getElementById("selectedFile");

const previewSection =
    document.getElementById("previewSection");


posterInput.addEventListener(
    "change",
    function () {

        const file = this.files[0];


        /* ---------------------------------------------
           No file selected
        --------------------------------------------- */

        if (!file) {

            selectedFile.style.display = "none";

            return;
        }


        /* ---------------------------------------------
           File size check
        --------------------------------------------- */

        if (file.size > 5 * 1024 * 1024) {

            alert(
                "Poster image must be less than 5MB."
            );

            this.value = "";

            selectedFile.style.display = "none";

            return;
        }


        /* ---------------------------------------------
           File type check
        --------------------------------------------- */

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];


        if (!allowedTypes.includes(file.type)) {

            alert(
                "Only JPG, JPEG, PNG and WEBP images are allowed."
            );

            this.value = "";

            selectedFile.style.display = "none";

            return;
        }


        /* ---------------------------------------------
           Show selected file name
        --------------------------------------------- */

        selectedFile.innerHTML =
            '<i class="fa-solid fa-image"></i> ' +
            file.name;

        selectedFile.style.display =
            "block";


        /* ---------------------------------------------
           Show instant preview
        --------------------------------------------- */

        const reader =
            new FileReader();


        reader.onload =
            function (event) {

                posterPreview.src =
                    event.target.result;

                posterPreview.style.display =
                    "block";

                previewSection.style.display =
                    "block";
            };


        reader.readAsDataURL(file);

    }
);

</script>


</body>

</html>