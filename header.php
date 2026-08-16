<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

    <title>TicketFlix</title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- TicketFlix CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>


<body>


<!-- ================= NAVBAR ================= -->

<header class="navbar">


    <!-- LOGO -->

    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        <span>
            Ticket<span>Flix</span>
        </span>

    </div>



    <!-- NAVIGATION -->

    <nav>


        <!-- HOME -->

        <a href="index.php">

            <i class="fa-solid fa-house"></i>

            Home

        </a>



        <!-- MOVIES -->

        <a href="movies.php">

            <i class="fa-solid fa-film"></i>

            Movies

        </a>



        <!-- THEATERS -->

        <a href="theaters.php">

            <i class="fa-solid fa-building"></i>

            Theaters

        </a>



        <!-- SHOWTIMES -->

        <a href="showtimes.php">

            <i class="fa-solid fa-clock"></i>

            Showtimes

        </a>



        <?php if (isset($_SESSION['user_id'])) { ?>


            <!-- MY BOOKINGS -->

            <a href="my_bookings.php">

                <i class="fa-solid fa-ticket"></i>

                My Bookings

            </a>



            <!-- USER NAME -->

            <?php if (!empty($_SESSION['user_name'])) { ?>

                <span class="user-welcome">

                    <i class="fa-solid fa-user"></i>

                    Hi,
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['user_name']
                    );
                    ?>

                </span>

            <?php } ?>



            <!-- LOGOUT -->

            <a
                href="logout.php"
                class="login-btn"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>


        <?php } else { ?>


            <!-- LOGIN -->

            <a
                href="login.php"
                class="login-btn"
            >

                <i class="fa-solid fa-user"></i>

                Login

            </a>


        <?php } ?>


    </nav>


</header>