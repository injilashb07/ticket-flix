<?php
session_start();
require_once "../config.php";

/* ==============================
   ADD MOVIE
================================ */

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $genre = trim($_POST["genre"]);
    $language = trim($_POST["language"]);
    $duration = intval($_POST["duration"]);
    $rating = trim($_POST["rating"]);
    $release_date = !empty($_POST["release_date"])
        ? $_POST["release_date"]
        : null;
    $trailer = trim($_POST["trailer"]);
    $status = $_POST["status"];

    /* Poster upload */
    $poster_image = "";

    if (isset($_FILES["poster_image"]) && $_FILES["poster_image"]["error"] === 0) {

        $upload_dir = "../uploads/posters/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = $_FILES["poster_image"]["name"];
        $file_tmp = $_FILES["poster_image"]["tmp_name"];
        $file_size = $_FILES["poster_image"]["size"];

        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($extension, $allowed)) {

            $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
            $message_type = "error";

        } elseif ($file_size > 5 * 1024 * 1024) {

            $message = "Poster image must be less than 5 MB.";
            $message_type = "error";

        } else {

            $new_name = time() . "_" . uniqid() . "." . $extension;

            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $poster_image = "uploads/posters/" . $new_name;
            } else {
                $message = "Failed to upload poster image.";
                $message_type = "error";
            }
        }
    }

    /* Insert movie */
    if ($message_type !== "error") {

        $sql = "INSERT INTO movies
                (name, description, genre, language, duration, rating,
                 release_date, poster_image, trailer, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "ssssisssss",
                $name,
                $description,
                $genre,
                $language,
                $duration,
                $rating,
                $release_date,
                $poster_image,
                $trailer,
                $status
            );

            if ($stmt->execute()) {

                $message = "Movie added successfully! 🎬";
                $message_type = "success";

                $_POST = [];

            } else {

                $message = "Error: " . $stmt->error;
                $message_type = "error";
            }

            $stmt->close();

        } else {

            $message = "Database error: " . $conn->error;
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Movie - TicketFlix</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #10091d;
    color: white;
}

/* Header */

.header {
    background: #140b24;
    padding: 20px 50px;
    border-bottom: 1px solid #4a2d63;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 30px;
    font-weight: bold;
}

.logo span {
    color: #ffc83d;
}

.back {
    color: white;
    text-decoration: none;
    background: linear-gradient(135deg, #7b2ff7, #a855f7);
    padding: 12px 22px;
    border-radius: 25px;
    font-weight: bold;
}

/* Container */

.container {
    width: 90%;
    max-width: 1000px;
    margin: 40px auto;
}

.title {
    text-align: center;
    margin-bottom: 30px;
}

.title h1 {
    font-size: 38px;
    margin-bottom: 8px;
}

.title span {
    color: #ffc83d;
}

.title p {
    color: #b9aec7;
}

/* Form */

.form-box {
    background: #1d1230;
    border: 1px solid #4e3264;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 10px 40px rgba(0,0,0,.3);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.full {
    grid-column: 1 / -1;
}

label {
    margin-bottom: 8px;
    color: #ffc83d;
    font-weight: bold;
}

input,
textarea,
select {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #59416d;
    border-radius: 10px;
    background: #120a1e;
    color: white;
    font-size: 15px;
    outline: none;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #914cff;
    box-shadow: 0 0 8px rgba(145,76,255,.3);
}

textarea {
    min-height: 120px;
    resize: vertical;
}

select option {
    background: #1d1230;
}

small {
    margin-top: 6px;
    color: #998ca8;
}

/* Message */

.message {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: bold;
}

.success {
    background: #173d2a;
    border: 1px solid #32c878;
    color: #7dffb1;
}

.error {
    background: #421d25;
    border: 1px solid #ff526d;
    color: #ff9aaa;
}

/* Button */

.button-area {
    margin-top: 30px;
    text-align: center;
}

.add-btn {
    border: none;
    cursor: pointer;
    padding: 15px 45px;
    border-radius: 30px;

    background: linear-gradient(135deg, #ffc83d, #ffad00);
    color: #160d20;

    font-size: 17px;
    font-weight: bold;

    transition: .3s;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255,200,61,.3);
}

@media(max-width: 700px) {

    .header {
        padding: 18px 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .form-box {
        padding: 22px;
    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        🎟️ Ticket<span>Flix</span>
    </div>

    <a href="dashboard.php" class="back">
        ← Dashboard
    </a>

</div>


<div class="container">

    <div class="title">

        <h1>Add <span>New Movie</span> 🎬</h1>

        <p>Add a movie to your TicketFlix collection</p>

    </div>


    <?php if (!empty($message)): ?>

        <div class="message <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <div class="form-box">

        <form method="POST"
              enctype="multipart/form-data">

            <div class="form-grid">

                <!-- Movie Name -->

                <div class="form-group full">

                    <label>Movie Name *</label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Enter movie name"
                        value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- Genre -->

                <div class="form-group">

                    <label>Genre *</label>

                    <input
                        type="text"
                        name="genre"
                        placeholder="Example: Action, Drama"
                        value="<?= htmlspecialchars($_POST["genre"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- Language -->

                <div class="form-group">

                    <label>Language *</label>

                    <input
                        type="text"
                        name="language"
                        placeholder="Example: English, Hindi"
                        value="<?= htmlspecialchars($_POST["language"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- Duration -->

                <div class="form-group">

                    <label>Duration (minutes) *</label>

                    <input
                        type="number"
                        name="duration"
                        min="1"
                        placeholder="Example: 150"
                        value="<?= htmlspecialchars($_POST["duration"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- Rating -->

                <div class="form-group">

                    <label>Rating *</label>

                    <input
                        type="text"
                        name="rating"
                        placeholder="Example: PG-13 / R / U"
                        value="<?= htmlspecialchars($_POST["rating"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- Release Date -->

                <div class="form-group">

                    <label>Release Date</label>

                    <input
                        type="date"
                        name="release_date"
                        value="<?= htmlspecialchars($_POST["release_date"] ?? "") ?>"
                    >

                </div>


                <!-- Status -->

                <div class="form-group">

                    <label>Status *</label>

                    <select name="status" required>

                        <option value="coming_soon">
                            Coming Soon
                        </option>

                        <option value="now_showing">
                            Now Showing
                        </option>

                        <option value="expired">
                            Expired
                        </option>

                    </select>

                </div>


                <!-- Description -->

                <div class="form-group full">

                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Enter movie description..."
                    ><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>

                </div>


                <!-- Poster -->

                <div class="form-group">

                    <label>Poster Image</label>

                    <input
                        type="file"
                        name="poster_image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        JPG, PNG, WEBP — Maximum 5 MB
                    </small>

                </div>


                <!-- Trailer -->

                <div class="form-group">

                    <label>Trailer URL *</label>

                    <input
                        type="text"
                        name="trailer"
                        placeholder="https://www.youtube.com/watch?v=..."
                        value="<?= htmlspecialchars($_POST["trailer"] ?? "") ?>"
                        required
                    >

                </div>

            </div>


            <div class="button-area">

                <button type="submit" class="add-btn">
                    🎬 Add Movie
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>