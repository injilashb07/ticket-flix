```php
<?php

session_start();

require_once "../config.php";

/* =========================================================
   THEATER LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION['theater_user_id']) ||
    !isset($_SESSION['theater_id'])
) {
    header("Location: login.php");
    exit();
}

$theater_id = (int) $_SESSION['theater_id'];


/* =========================================================
   CHECK SHOWTIME ID
========================================================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: showtimes.php?error=invalid_showtime");
    exit();
}

$showtime_id = (int) $_GET['id'];


/* =========================================================
   VERIFY SHOWTIME BELONGS TO LOGGED-IN THEATER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        st.id,
        st.screen_id,
        s.theater_id
    FROM showtimes st
    INNER JOIN screens s
        ON st.screen_id = s.id
    WHERE st.id = ?
      AND s.theater_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $showtime_id,
    $theater_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: showtimes.php?error=not_allowed");
    exit();
}

$showtime = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CHECK EXISTING BOOKINGS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE showtime_id = ?
");

$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$booking_count = (int) $row['total'];

$stmt->close();


/* =========================================================
   DO NOT DELETE SHOWTIME WITH BOOKINGS
========================================================= */

if ($booking_count > 0) {

    header(
        "Location: showtimes.php?error=has_bookings"
    );

    exit();
}


/* =========================================================
   DELETE SHOWTIME
========================================================= */

$stmt = $conn->prepare("
    DELETE FROM showtimes
    WHERE id = ?
");

$stmt->bind_param(
    "i",
    $showtime_id
);

if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: showtimes.php?success=deleted"
    );

    exit();

} else {

    $stmt->close();

    header(
        "Location: showtimes.php?error=delete_failed"
    );

    exit();
}

?>
```
