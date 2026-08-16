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
   GET ADMIN DETAILS
========================================================= */

$admin_id = (int) $_SESSION['admin_id'];

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email
    FROM admins
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: ../admin_login.php");
    exit();
}

/* =========================================================
   DELETE BOOKING
========================================================= */

if (isset($_GET['delete'])) {

    $delete_id = (int) $_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM bookings
        WHERE id = ?
    ");

    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: bookings.php?deleted=1");
    exit();
}

/* =========================================================
   UPDATE BOOKING STATUS
========================================================= */

if (isset($_POST['update_status'])) {

    $booking_id = (int) $_POST['booking_id'];
    $booking_status = $_POST['booking_status'];

    $allowed_status = [
        'pending',
        'confirmed',
        'cancelled'
    ];

    if (in_array($booking_status, $allowed_status)) {

        $stmt = $conn->prepare("
            UPDATE bookings
            SET booking_status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $booking_status,
            $booking_id
        );

        $stmt->execute();
        $stmt->close();
    }

    header("Location: bookings.php?updated=1");
    exit();
}

/* =========================================================
   SEARCH
========================================================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

/* =========================================================
   SORTING
========================================================= */

$allowed_sort = [
    'id',
    'booking_reference',
    'total_amount',
    'booking_status',
    'payment_status'
];

$sort = isset($_GET['sort'])
    ? $_GET['sort']
    : 'id';

if (!in_array($sort, $allowed_sort)) {
    $sort = 'id';
}

$order = isset($_GET['order'])
    ? strtoupper($_GET['order'])
    : 'DESC';

if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

/* =========================================================
   PAGINATION
========================================================= */

$limit = 8;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;

/* =========================================================
   SEARCH CONDITION
========================================================= */

$where = "";

if ($search !== "") {

    $safe_search = $conn->real_escape_string($search);

    $where = "
        WHERE
            b.booking_reference LIKE '%$safe_search%'
            OR u.first_name LIKE '%$safe_search%'
            OR u.last_name LIKE '%$safe_search%'
            OR u.email LIKE '%$safe_search%'
            OR m.name LIKE '%$safe_search%'
    ";
}

/* =========================================================
   TOTAL BOOKINGS
========================================================= */

$count_sql = "
    SELECT COUNT(*) AS total

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.id

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    $where
";

$count_result = $conn->query($count_sql);

$total_rows = $count_result->fetch_assoc()['total'];

$total_pages = max(
    1,
    ceil($total_rows / $limit)
);

/* =========================================================
   GET BOOKINGS
========================================================= */

$sql = "
    SELECT

        b.id,
        b.booking_reference,
        b.total_amount,
        b.booking_status,
        b.payment_status,

        u.first_name,
        u.last_name,
        u.email,
        u.phone,

        m.name AS movie_name,

        st.show_date,
        st.show_time

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.id

    INNER JOIN showtimes st
        ON b.showtime_id = st.id

    INNER JOIN movies m
        ON st.movie_id = m.id

    $where

    ORDER BY b.$sort $order

    LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);

/* =========================================================
   SUMMARY COUNTS
========================================================= */

$summary = $conn->query("
    SELECT

        COUNT(*) AS total,

        SUM(
            booking_status = 'confirmed'
        ) AS confirmed,

        SUM(
            booking_status = 'pending'
        ) AS pending,

        SUM(
            booking_status = 'cancelled'
        ) AS cancelled

    FROM bookings
");

$summary_data = $summary->fetch_assoc();

$total_bookings = $summary_data['total'];
$confirmed = $summary_data['confirmed'];
$pending = $summary_data['pending'];
$cancelled = $summary_data['cancelled'];

/* =========================================================
   REVENUE
========================================================= */

$revenue_result = $conn->query("
    SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS revenue

    FROM bookings

    WHERE booking_status = 'confirmed'
");

$revenue = $revenue_result->fetch_assoc()['revenue'];

/* =========================================================
   SORT URL FUNCTION
========================================================= */

function sortLink($column, $currentSort, $currentOrder, $search)
{
    $newOrder = 'ASC';

    if (
        $currentSort === $column &&
        $currentOrder === 'ASC'
    ) {
        $newOrder = 'DESC';
    }

    return "bookings.php?"
        . "sort=" . urlencode($column)
        . "&order=" . urlencode($newOrder)
        . "&search=" . urlencode($search);
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

<title>Bookings | TicketFlix Admin</title>

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

        radial-gradient(
            circle at bottom left,
            rgba(212,175,55,.08),
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

    background:
        rgba(212,175,55,.12);

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
   TOPBAR
========================================================= */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}

.welcome h1 {

    font-size: 28px;
}

.welcome h1 span {

    color: #d4af37;
}

.welcome p {

    color: #888;

    font-size: 13px;

    margin-top: 5px;
}

.admin-profile {

    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {

    width: 45px;

    height: 45px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #8f6d18
        );

    color: #171020;

    font-weight: 800;
}

/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.summary-card {

    padding: 18px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(255,255,255,.08);
}

.summary-card i {

    color: #d4af37;

    font-size: 20px;

    margin-bottom: 10px;
}

.summary-card small {

    display: block;

    color: #888;

    font-size: 11px;
}

.summary-card h2 {

    margin-top: 4px;

    font-size: 24px;
}

.summary-card.confirmed h2 {
    color: #61e69b;
}

.summary-card.pending h2 {
    color: #f1d46a;
}

.summary-card.cancelled h2 {
    color: #ff8175;
}

.summary-card.revenue h2 {
    color: #d4af37;
}

/* =========================================================
   PANEL
========================================================= */

.panel {

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.08);

    border-radius: 22px;

    padding: 25px;
}

/* =========================================================
   HEADER
========================================================= */

.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 22px;
}

.panel-header h2 {

    font-size: 20px;
}

.panel-header h2 span {

    color: #d4af37;
}

/* =========================================================
   SEARCH
========================================================= */

.search-box {

    display: flex;

    gap: 10px;
}

.search-box input {

    width: 280px;

    padding: 11px 15px;

    border-radius: 10px;

    border:
        1px solid rgba(255,255,255,.12);

    background:
        rgba(255,255,255,.06);

    color: white;

    outline: none;

    font-family: inherit;
}

.search-box input:focus {

    border-color: #d4af37;
}

.search-btn {

    border: none;

    background: #d4af37;

    color: #171020;

    padding: 0 16px;

    border-radius: 10px;

    cursor: pointer;

    font-weight: 700;
}

.reset-btn {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 0 14px;

    border-radius: 10px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(255,255,255,.1);

    color: #bbb;

    text-decoration: none;

    font-size: 12px;
}

/* =========================================================
   TABLE
========================================================= */


.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1050px;
}

th {

    text-align: left;

    color: #777;

    font-size: 11px;

    padding: 13px 10px;

    border-bottom:
        1px solid rgba(255,255,255,.08);

    white-space: nowrap;
}

th a {

    color: #888;

    text-decoration: none;
}

th a:hover {

    color: #d4af37;
}

td {

    padding: 15px 10px;

    font-size: 12px;

    color: #ccc;

    border-bottom:
        1px solid rgba(255,255,255,.05);

    vertical-align: middle;
}

.reference {

    color: #d4af37;

    font-weight: 700;
}

.customer-name {

    color: white;

    font-weight: 600;
}

.email {

    color: #777;

    font-size: 10px;

    margin-top: 2px;
}

.movie {

    color: white;

    font-weight: 500;
}

.date {

    color: #bbb;
}

.time {

    color: #777;

    font-size: 10px;
}

.amount {

    color: #d4af37;

    font-weight: 700;
}

<a href="booking_view.php?id=<?= $row['id']; ?>" class="view-btn">
    <i class="fa-solid fa-eye"></i> View
</a>

/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 15px;

    font-size: 10px;

    text-transform: capitalize;
}

.status-confirmed {

    color: #61e69b;

    background:
        rgba(46,204,113,.10);
}

.status-pending {

    color: #f1d46a;

    background:
        rgba(241,196,15,.10);
}

.status-cancelled {

    color: #ff8175;

    background:
        rgba(231,76,60,.10);
}

.payment-completed {

    color: #61e69b;

    background:
        rgba(46,204,113,.10);
}

.payment-pending {

    color: #f1d46a;

    background:
        rgba(241,196,15,.10);
}

.payment-failed {

    color: #ff8175;

    background:
        rgba(231,76,60,.10);
}

/* =========================================================
   ACTION BUTTONS
========================================================= */

.actions {

    display: flex;

    gap: 7px;
}

.action-btn {

    width: 32px;

    height: 32px;

    border-radius: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-decoration: none;

    border: none;

    cursor: pointer;

    transition: .2s;
}

.view {

    background:
        rgba(126,87,194,.15);

    color: #b99be8;
}

.cancel {

    background:
        rgba(231,76,60,.12);

    color: #ff8175;
}

.delete {

    background:
        rgba(231,76,60,.10);

    color: #ff8175;
}

.action-btn:hover {

    transform: translateY(-2px);
}

/* =========================================================
   STATUS FORM
========================================================= */

.status-form select {

    background: #191221;

    color: white;

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 7px;

    padding: 6px;

    font-size: 10px;

    outline: none;
}

.status-form button {

    background: #d4af37;

    color: #171020;

    border: none;

    border-radius: 7px;

    padding: 6px 8px;

    font-size: 10px;

    cursor: pointer;

    margin-left: 4px;
}

/* =========================================================
   PAGINATION
========================================================= */

.pagination {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 22px;

    flex-wrap: wrap;

    gap: 15px;
}

.pagination-info {

    color: #777;

    font-size: 11px;
}

.pages {

    display: flex;

    gap: 6px;
}

.pages a {

    width: 34px;

    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    text-decoration: none;

    color: #aaa;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid rgba(255,255,255,.07);

    font-size: 11px;
}

.pages a:hover,
.pages a.active {

    background: #d4af37;

    color: #171020;

    border-color: #d4af37;

    font-weight: 700;
}

/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 12px 15px;

    border-radius: 10px;

    margin-bottom: 18px;

    background:
        rgba(46,204,113,.10);

    color: #61e69b;

    font-size: 12px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .summary {

        grid-template-columns:
            repeat(3,1fr);
    }
}

@media(max-width:900px) {

    .summary {

        grid-template-columns:
            repeat(2,1fr);
    }

    .panel-header {

        flex-direction: column;

        align-items: flex-start;
    }

    .search-box {

        width: 100%;
    }

    .search-box input {

        flex: 1;

        width: auto;
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

    .summary {

        grid-template-columns: 1fr;
    }

    .topbar {

        align-items: flex-start;
    }

    .admin-profile strong {

        display: none;
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

    <a href="bookings.php" class="active">

        <i class="fa-solid fa-ticket"></i>

        <span>Bookings</span>

    </a>

    <a href="../movies.php">

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

    <a href="../logout.php" class="logout">

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>Logout</span>

    </a>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <!-- TOP BAR -->

    <div class="topbar">

        <div class="welcome">

            <h1>

                Booking
                <span>Management</span> 🎟️

            </h1>

            <p>
                Manage all TicketFlix bookings.
            </p>

        </div>

        <div class="admin-profile">

            <div>

                <strong>

                    <?= htmlspecialchars(
                        $admin['first_name']
                        . " "
                        . $admin['last_name']
                    ); ?>

                </strong>

            </div>

            <div class="avatar">

                <?= strtoupper(
                    substr(
                        $admin['first_name'],
                        0,
                        1
                    )
                ); ?>

            </div>

        </div>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if (isset($_GET['deleted'])): ?>

        <div class="message">

            <i class="fa-solid fa-check-circle"></i>

            Booking deleted successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['updated'])): ?>

        <div class="message">

            <i class="fa-solid fa-check-circle"></i>

            Booking status updated successfully.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="summary">

        <div class="summary-card">

            <i class="fa-solid fa-ticket"></i>

            <small>Total Bookings</small>

            <h2>
                <?= number_format($total_bookings); ?>
            </h2>

        </div>


        <div class="summary-card confirmed">

            <i class="fa-solid fa-circle-check"></i>

            <small>Confirmed</small>

            <h2>
                <?= number_format($confirmed); ?>
            </h2>

        </div>


        <div class="summary-card pending">

            <i class="fa-solid fa-clock"></i>

            <small>Pending</small>

            <h2>
                <?= number_format($pending); ?>
            </h2>

        </div>


        <div class="summary-card cancelled">

            <i class="fa-solid fa-circle-xmark"></i>

            <small>Cancelled</small>

            <h2>
                <?= number_format($cancelled); ?>
            </h2>

        </div>


        <div class="summary-card revenue">

            <i class="fa-solid fa-indian-rupee-sign"></i>

            <small>Confirmed Revenue</small>

            <h2>

                ₹<?= number_format(
                    $revenue,
                    2
                ); ?>

            </h2>

        </div>

    </div>


    <!-- =====================================================
         BOOKINGS PANEL
    ====================================================== -->

    <div class="panel">

        <div class="panel-header">

            <h2>

                All
                <span>Bookings</span>

            </h2>


            <!-- SEARCH -->

            <form
                method="GET"
                class="search-box"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search booking, customer, movie..."
                    value="<?= htmlspecialchars($search); ?>"
                >

                <button
                    type="submit"
                    class="search-btn"
                >

                    <i class="fa-solid fa-search"></i>

                </button>

                <?php if ($search !== ""): ?>

                    <a
                        href="bookings.php"
                        class="reset-btn"
                    >

                        Reset

                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- TABLE -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>

                            <a href="<?= sortLink(
                                'id',
                                $sort,
                                $order,
                                $search
                            ); ?>">

                                ID ↕️

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortLink(
                                'booking_reference',
                                $sort,
                                $order,
                                $search
                            ); ?>">

                                Reference ↕️

                            </a>

                        </th>


                        <th>
                            Customer
                        </th>


                        <th>
                            Movie
                        </th>


                        <th>
                            Show
                        </th>


                        <th>

                            <a href="<?= sortLink(
                                'total_amount',
                                $sort,
                                $order,
                                $search
                            ); ?>">

                                Amount ↕️

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortLink(
                                'booking_status',
                                $sort,
                                $order,
                                $search
                            ); ?>">

                                Booking Status ↕️

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortLink(
                                'payment_status',
                                $sort,
                                $order,
                                $search
                            ); ?>">

                                Payment ↕️

                            </a>

                        </th>


                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <!-- ID -->

                            <td>

                                #<?= (int) $row['id']; ?>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <div class="reference">

                                    <?= htmlspecialchars(
                                        $row['booking_reference']
                                    ); ?>

                                </div>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">

                                    <?= htmlspecialchars(

                                        $row['first_name']
                                        . " "
                                        . $row['last_name']

                                    ); ?>

                                </div>

                                <div class="email">

                                    <?= htmlspecialchars(
                                        $row['email']
                                    ); ?>

                                </div>

                            </td>


                            <!-- MOVIE -->

                            <td>

                                <div class="movie">

                                    <?= htmlspecialchars(
                                        $row['movie_name']
                                    ); ?>

                                </div>

                            </td>


                            <!-- SHOW -->

                            <td>

                                <div class="date">

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $row['show_date']
                                        )
                                    ); ?>

                                </div>

                                <div class="time">

                                    <?= date(
                                        "h:i A",
                                        strtotime(
                                            $row['show_time']
                                        )
                                    ); ?>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <div class="amount">

                                    ₹<?= number_format(
                                        $row['total_amount'],
                                        2
                                    ); ?>

                                </div>

                            </td>


                            <!-- BOOKING STATUS -->

                            <td>

                                <span class="status status-<?= htmlspecialchars(
                                    $row['booking_status']
                                ); ?>">

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $row['booking_status']
                                        )
                                    ); ?>

                                </span>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <span class="status payment-<?= htmlspecialchars(
                                    $row['payment_status']
                                ); ?>">

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $row['payment_status']
                                        )
                                    ); ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">

                                    <!-- VIEW -->

                                    <a
                                        href="booking_view.php?id=<?= (int) $row['id']; ?>"
                                        class="action-btn view"
                                        title="View Booking"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                    </a>


                                    <!-- DELETE -->

                                    <a
                                        href="bookings.php?delete=<?= (int) $row['id']; ?>"
                                        class="action-btn delete"
                                        title="Delete Booking"
                                        onclick="return confirm('Are you sure you want to delete this booking?');"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            style="
                                text-align:center;
                                padding:50px;
                                color:#777;
                            "
                        >

                            <i
                                class="fa-solid fa-ticket"
                                style="
                                    font-size:30px;
                                    margin-bottom:10px;
                                "
                            ></i>

                            <br>

                            No bookings found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- =================================================
             PAGINATION
        ================================================== -->

        <div class="pagination">

            <div class="pagination-info">

                Showing

                <?php

                $start = $total_rows > 0
                    ? $offset + 1
                    : 0;

                $end = min(
                    $offset + $limit,
                    $total_rows
                );

                ?>

                <?= $start; ?>

                -

                <?= $end; ?>

                of

                <?= $total_rows; ?>

                bookings

            </div>


            <div class="pages">

                <?php if ($page > 1): ?>

                    <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>&sort=<?= urlencode($sort); ?>&order=<?= urlencode($order); ?>">

                        <i class="fa-solid fa-chevron-left"></i>

                    </a>

                <?php endif; ?>


                <?php

                $start_page = max(
                    1,
                    $page - 2
                );

                $end_page = min(
                    $total_pages,
                    $page + 2
                );

                for (
                    $i = $start_page;
                    $i <= $end_page;
                    $i++
                ):

                ?>

                    <a
                        href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>&sort=<?= urlencode($sort); ?>&order=<?= urlencode($order); ?>"
                        class="<?= $i == $page ? 'active' : ''; ?>"
                    >

                        <?= $i; ?>

                    </a>

                <?php endfor; ?>


                <?php if ($page < $total_pages): ?>

                    <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>&sort=<?= urlencode($sort); ?>&order=<?= urlencode($order); ?>">

                        <i class="fa-solid fa-chevron-right"></i>

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</main>

</body>

</html>