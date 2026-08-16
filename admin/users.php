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

$admin_id = (int) $_SESSION['admin_id'];


/* =========================================================
   GET ADMIN DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email
    FROM admins
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Admin query error: " . $conn->error);
}

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

$stmt->close();

if (!$admin) {
    session_unset();
    session_destroy();

    header("Location: ../admin_login.php");
    exit();
}


/* =========================================================
   DELETE USER
========================================================= */

if (isset($_GET['delete'])) {

    $delete_id = (int) $_GET['delete'];

    if ($delete_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM users
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("i", $delete_id);

            if ($stmt->execute()) {
                header("Location: users.php?deleted=1");
                exit();
            }

            $stmt->close();
        }
    }
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
    "id",
    "first_name",
    "last_name",
    "email",
    "phone"
];

$sort = isset($_GET['sort'])
    ? $_GET['sort']
    : "id";

if (!in_array($sort, $allowed_sort)) {
    $sort = "id";
}


$order = isset($_GET['order'])
    ? strtoupper($_GET['order'])
    : "DESC";

if ($order !== "ASC" && $order !== "DESC") {
    $order = "DESC";
}


/* =========================================================
   PAGINATION
========================================================= */

$per_page = isset($_GET['per_page'])
    ? (int) $_GET['per_page']
    : 10;

$allowed_per_page = [5, 10, 20, 50];

if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 10;
}


$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}


/* =========================================================
   TOTAL USERS
========================================================= */

$total_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

$total_users = 0;

if ($total_result) {
    $total_users = (int) $total_result
        ->fetch_assoc()['total'];
}


/* =========================================================
   SEARCHED USERS COUNT
========================================================= */

$count_sql = "
    SELECT COUNT(*) AS total
    FROM users
";

$count_params = [];
$count_types = "";

if ($search !== "") {

    $count_sql .= "
        WHERE
            first_name LIKE ?
            OR last_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
    ";

    $search_value = "%" . $search . "%";

    $count_params = [
        $search_value,
        $search_value,
        $search_value,
        $search_value
    ];

    $count_types = "ssss";
}

$stmt = $conn->prepare($count_sql);

if (!$stmt) {
    die("Count query error: " . $conn->error);
}

if ($search !== "") {
    $stmt->bind_param(
        $count_types,
        ...$count_params
    );
}

$stmt->execute();

$count_result = $stmt->get_result();

$filtered_users = (int) $count_result
    ->fetch_assoc()['total'];

$stmt->close();


/* =========================================================
   TOTAL PAGES
========================================================= */

$total_pages = max(
    1,
    (int) ceil($filtered_users / $per_page)
);

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;


/* =========================================================
   GET USERS
========================================================= */

$sql = "
    SELECT
        id,
        email,
        phone,
        first_name,
        last_name
    FROM users
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        WHERE
            first_name LIKE ?
            OR last_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
    ";

    $search_value = "%" . $search . "%";

    $params = [
        $search_value,
        $search_value,
        $search_value,
        $search_value
    ];

    $types = "ssss";
}


/*
   SORTING
   Column names come only from the whitelist above.
*/

$sql .= "
    ORDER BY `$sort` $order
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$types .= "ii";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Users query error: " . $conn->error);
}

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$users_result = $stmt->get_result();


/* =========================================================
   SORT URL HELPER
========================================================= */

function sortUrl($column)
{
    global $search, $per_page, $sort, $order;

    $new_order = "ASC";

    if ($sort === $column && $order === "ASC") {
        $new_order = "DESC";
    }

    return "users.php?" . http_build_query([
        "search" => $search,
        "sort" => $column,
        "order" => $new_order,
        "per_page" => $per_page,
        "page" => 1
    ]);
}


/* =========================================================
   PAGINATION URL
========================================================= */

function pageUrl($page_number)
{
    global $search, $per_page, $sort, $order;

    return "users.php?" . http_build_query([
        "search" => $search,
        "sort" => $sort,
        "order" => $order,
        "per_page" => $per_page,
        "page" => $page_number
    ]);
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

<title>Users | TicketFlix Admin</title>

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
   TOP BAR
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
   HEADER CARD
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}

.page-header h2 {

    font-size: 21px;
}

.page-header h2 span {

    color: #d4af37;
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}

.stat-card {

    padding: 22px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(255,255,255,.08);
}

.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.stat-top small {

    color: #888;
}

.stat-icon {

    width: 45px;

    height: 45px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 18px;
}

.stat-card h2 {

    font-size: 27px;

    margin-top: 10px;
}


/* =========================================================
   MAIN PANEL
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
   SEARCH AREA
========================================================= */

.controls {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 22px;

    flex-wrap: wrap;
}

.search-box {

    display: flex;

    align-items: center;

    gap: 10px;

    flex: 1;

    min-width: 250px;

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 12px;

    padding: 0 14px;
}

.search-box i {

    color: #d4af37;
}

.search-box input {

    width: 100%;

    padding: 13px 5px;

    background: transparent;

    border: none;

    outline: none;

    color: white;

    font-family: inherit;
}

.search-box input::placeholder {

    color: #777;
}

.control-right {

    display: flex;

    gap: 10px;
}

.select-box {

    background:
        rgba(255,255,255,.055);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 12px;

    padding: 0 12px;

    color: #ccc;

    outline: none;

    font-family: inherit;

    cursor: pointer;
}

.select-box option {

    background: #181020;

    color: white;
}

.search-btn {

    border: none;

    border-radius: 12px;

    padding: 0 20px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #a98420
        );

    color: #171020;

    font-weight: 700;

    cursor: pointer;

    font-family: inherit;

    transition: .3s;
}

.search-btn:hover {

    transform: translateY(-2px);
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

.success-message {

    background:
        rgba(46,204,113,.10);

    border:
        1px solid rgba(46,204,113,.25);

    color: #61e69b;

    padding: 12px 15px;

    border-radius: 12px;

    margin-bottom: 18px;

    font-size: 13px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 750px;
}

th {

    text-align: left;

    padding: 14px 10px;

    color: #777;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;

    border-bottom:
        1px solid rgba(255,255,255,.08);
}

th a {

    color: #777;

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 5px;

    transition: .2s;
}

th a:hover {

    color: #d4af37;
}

td {

    padding: 16px 10px;

    color: #ccc;

    font-size: 12px;

    border-bottom:
        1px solid rgba(255,255,255,.05);
}

tr:hover td {

    background:
        rgba(212,175,55,.025);
}

.user-id {

    color: #d4af37;

    font-weight: 600;
}

.user-name {

    color: white;

    font-weight: 600;
}

.email {

    color: #aaa;
}

.phone {

    color: #aaa;
}


/* =========================================================
   USER AVATAR
========================================================= */

.user-info {

    display: flex;

    align-items: center;

    gap: 10px;
}

.user-avatar {

    width: 36px;

    height: 36px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(212,175,55,.15);

    color: #d4af37;

    font-weight: 700;

    font-size: 13px;
}


/* =========================================================
   DELETE BUTTON
========================================================= */

.delete-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 35px;

    height: 35px;

    border-radius: 10px;

    color: #ff8175;

    background:
        rgba(231,76,60,.08);

    border:
        1px solid rgba(231,76,60,.15);

    text-decoration: none;

    transition: .3s;
}

.delete-btn:hover {

    background:
        rgba(231,76,60,.18);

    transform: translateY(-2px);
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

    margin-bottom: 15px;

    color: #d4af37;
}


/* =========================================================
   PAGINATION
========================================================= */

.pagination-area {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 22px;

    flex-wrap: wrap;

    gap: 15px;
}

.pagination-info {

    color: #777;

    font-size: 12px;
}

.pagination {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.pagination a {

    min-width: 35px;

    height: 35px;

    padding: 0 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    color: #aaa;

    text-decoration: none;

    background:
        rgba(255,255,255,.04);

    border:
        1px solid rgba(255,255,255,.07);

    font-size: 12px;

    transition: .2s;
}

.pagination a:hover,
.pagination a.active {

    background:
        rgba(212,175,55,.15);

    border-color: #d4af37;

    color: #d4af37;
}

.pagination a.disabled {

    opacity: .35;

    pointer-events: none;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

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
}

@media(max-width:650px) {

    .topbar {

        align-items: flex-start;

        gap: 15px;
    }

    .admin-profile strong {

        display: none;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .controls {

        flex-direction: column;
    }

    .control-right {

        width: 100%;
    }

    .select-box,
    .search-btn {

        height: 45px;
    }

    .search-btn {

        padding: 0 15px;
    }

    .page-header {

        align-items: flex-start;
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


    <a
        href="users.php"
        class="active"
    >

        <i class="fa-solid fa-users"></i>

        <span>Users</span>

    </a>


    <a href="bookings.php">

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


    <a
        href="../logout.php"
        class="logout"
    >

        <i class="fa-solid fa-right-from-bracket"></i>

        <span>Logout</span>

    </a>

</aside>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <div class="welcome">

            <h1>

                Manage
                <span>Users</span>
                👥

            </h1>

            <p>
                View and manage TicketFlix customers.
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



    <!-- PAGE HEADER -->

    <div class="page-header">

        <h2>

            User
            <span>Management</span>

        </h2>

    </div>



    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-top">

                <small>Total Registered Users</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

            </div>


            <h2>

                <?= number_format($total_users); ?>

            </h2>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <small>Search Results</small>

                <div class="stat-icon">

                    <i class="fa-solid fa-filter"></i>

                </div>

            </div>


            <h2>

                <?= number_format($filtered_users); ?>

            </h2>

        </div>


    </div>



    <!-- =================================================
         USERS PANEL
    ================================================= -->

    <div class="panel">


        <?php if (isset($_GET['deleted'])): ?>

            <div class="success-message">

                <i class="fa-solid fa-circle-check"></i>

                User deleted successfully.

            </div>

        <?php endif; ?>



        <!-- SEARCH + CONTROLS -->

        <form
            method="GET"
            action="users.php"
            class="controls"
        >


            <div class="search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    placeholder="Search by name, email or phone..."
                    value="<?= htmlspecialchars($search); ?>"
                >

            </div>


            <div class="control-right">


                <select
                    name="per_page"
                    class="select-box"
                    onchange="this.form.submit()"
                >

                    <option
                        value="5"
                        <?= $per_page == 5 ? 'selected' : ''; ?>
                    >
                        5 per page
                    </option>

                    <option
                        value="10"
                        <?= $per_page == 10 ? 'selected' : ''; ?>
                    >
                        10 per page
                    </option>

                    <option
                        value="20"
                        <?= $per_page == 20 ? 'selected' : ''; ?>
                    >
                        20 per page
                    </option>

                    <option
                        value="50"
                        <?= $per_page == 50 ? 'selected' : ''; ?>
                    >
                        50 per page
                    </option>

                </select>


                <button
                    type="submit"
                    class="search-btn"
                >

                    <i class="fa-solid fa-search"></i>

                    Search

                </button>


            </div>

        </form>



        <!-- =================================================
             TABLE
        ================================================= -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>


                        <th>

                            <a href="<?= sortUrl('id'); ?>">

                                ID

                                <?php if ($sort === 'id'): ?>

                                    <i class="
                                        fa-solid
                                        fa-sort-
                                        <?= $order === 'ASC'
                                            ? 'up'
                                            : 'down';
                                        ?>
                                    "></i>

                                <?php else: ?>

                                    <i class="fa-solid fa-sort"></i>

                                <?php endif; ?>

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortUrl('first_name'); ?>">

                                User

                                <i class="fa-solid fa-sort"></i>

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortUrl('email'); ?>">

                                Email

                                <i class="fa-solid fa-sort"></i>

                            </a>

                        </th>


                        <th>

                            <a href="<?= sortUrl('phone'); ?>">

                                Phone

                                <i class="fa-solid fa-sort"></i>

                            </a>

                        </th>


                        <th>
                            Action
                        </th>


                    </tr>

                </thead>


                <tbody>


                <?php if ($users_result->num_rows > 0): ?>


                    <?php while (
                        $user =
                        $users_result->fetch_assoc()
                    ): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <span class="user-id">

                                    #<?= (int) $user['id']; ?>

                                </span>

                            </td>



                            <!-- USER -->

                            <td>

                                <div class="user-info">


                                    <div class="user-avatar">

                                        <?= strtoupper(
                                            substr(
                                                $user['first_name'],
                                                0,
                                                1
                                            )
                                        ); ?>

                                    </div>


                                    <div>

                                        <div class="user-name">

                                            <?= htmlspecialchars(

                                                $user['first_name']
                                                . " "
                                                . $user['last_name']

                                            ); ?>

                                        </div>

                                    </div>


                                </div>

                            </td>



                            <!-- EMAIL -->

                            <td>

                                <span class="email">

                                    <?= htmlspecialchars(
                                        $user['email']
                                    ); ?>

                                </span>

                            </td>



                            <!-- PHONE -->

                            <td>

                                <span class="phone">

                                    <?= htmlspecialchars(
                                        $user['phone']
                                    ); ?>

                                </span>

                            </td>



                            <!-- ACTION -->

                            <td>

                                <a
                                    href="users.php?delete=<?= (int) $user['id']; ?>"
                                    class="delete-btn"
                                    title="Delete User"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this user?'
                                        );
                                    "
                                >

                                    <i class="fa-solid fa-trash"></i>

                                </a>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="5"
                            class="empty"
                        >

                            <i class="fa-solid fa-users-slash"></i>

                            <br>

                            No users found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>



        <!-- =================================================
             PAGINATION
        ================================================= -->

        <div class="pagination-area">


            <div class="pagination-info">

                Showing

                <strong>
                    <?= $filtered_users > 0
                        ? $offset + 1
                        : 0;
                    ?>
                </strong>

                –

                <strong>
                    <?= min(
                        $offset + $per_page,
                        $filtered_users
                    ); ?>
                </strong>

                of

                <strong>
                    <?= number_format(
                        $filtered_users
                    ); ?>
                </strong>

                users

            </div>



            <div class="pagination">


                <!-- PREVIOUS -->

                <?php if ($page > 1): ?>

                    <a
                        href="<?= pageUrl($page - 1); ?>"
                        title="Previous"
                    >

                        <i class="fa-solid fa-chevron-left"></i>

                    </a>

                <?php else: ?>

                    <a class="disabled">

                        <i class="fa-solid fa-chevron-left"></i>

                    </a>

                <?php endif; ?>



                <!-- PAGE NUMBERS -->

                <?php

                $start_page = max(
                    1,
                    $page - 2
                );

                $end_page = min(
                    $total_pages,
                    $page + 2
                );

                ?>


                <?php if ($start_page > 1): ?>

                    <a href="<?= pageUrl(1); ?>">
                        1
                    </a>

                    <?php if ($start_page > 2): ?>

                        <a class="disabled">
                            ...
                        </a>

                    <?php endif; ?>

                <?php endif; ?>



                <?php for (
                    $i = $start_page;
                    $i <= $end_page;
                    $i++
                ): ?>


                    <a
                        href="<?= pageUrl($i); ?>"
                        class="<?= $i === $page
                            ? 'active'
                            : ''; ?>"
                    >

                        <?= $i; ?>

                    </a>


                <?php endfor; ?>



                <?php if ($end_page < $total_pages): ?>

                    <?php if (
                        $end_page < $total_pages - 1
                    ): ?>

                        <a class="disabled">
                            ...
                        </a>

                    <?php endif; ?>


                    <a
                        href="<?= pageUrl($total_pages); ?>"
                    >

                        <?= $total_pages; ?>

                    </a>

                <?php endif; ?>



                <!-- NEXT -->

                <?php if ($page < $total_pages): ?>

                    <a
                        href="<?= pageUrl($page + 1); ?>"
                        title="Next"
                    >

                        <i class="fa-solid fa-chevron-right"></i>

                    </a>

                <?php else: ?>

                    <a class="disabled">

                        <i class="fa-solid fa-chevron-right"></i>

                    </a>

                <?php endif; ?>


            </div>

        </div>


    </div>


</main>


</body>

</html>