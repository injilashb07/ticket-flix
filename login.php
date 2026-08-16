<?php

// session_start();

require_once "config.php";

$message = "";


/* =====================================================
   ALREADY LOGGED IN
===================================================== */

if (isset($_SESSION['user_id'])) {

    header("Location: index.php");
    exit();

}


/* =====================================================
   REGISTRATION MESSAGE
===================================================== */

if (isset($_GET['registered'])) {

    $message = "Registration successful. Please login.";

}


/* =====================================================
   LOGIN
===================================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];


    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM users WHERE email = ? LIMIT 1"
    );


    if (!$stmt) {

        $message = "Database error. Please try again.";

    } else {


        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $email
        );


        mysqli_stmt_execute($stmt);


        $result =
            mysqli_stmt_get_result($stmt);


        if (mysqli_num_rows($result) == 1) {


            $user =
                mysqli_fetch_assoc($result);


            if (
                password_verify(
                    $password,
                    $user['password']
                )
            ) {


                /* =====================================
                   CREATE USER SESSION
                ===================================== */

                $_SESSION['user_id'] =
                    (int)$user['id'];


                $_SESSION['user_name'] =
                    $user['first_name']
                    . " "
                    . $user['last_name'];


                $_SESSION['user_email'] =
                    $user['email'];


                /*
                   Make sure session is written
                   before redirecting.
                */

                session_write_close();


                header(
                    "Location: index.php"
                );

                exit();


            } else {

                $message =
                    "Invalid email or password.";

            }


        } else {

            $message =
                "Invalid email or password.";

        }


        mysqli_stmt_close($stmt);

    }

}


include "header.php";

?>


<div class="auth-container">

    <div class="auth-box">


        <!-- LOGO -->

        <div class="auth-logo">

            🎬 Ticket<span>Flix</span>

        </div>


        <h2>

            Welcome Back 👋

        </h2>


        <p class="auth-subtitle">

            Login to continue your movie journey

        </p>


        <?php if ($message) { ?>

            <div class="info-message">

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>

        <?php } ?>


        <!-- LOGIN FORM -->

        <form method="POST">


            <div class="form-group">

                <label>

                    Email

                </label>


                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>


            <div class="form-group">

                <label>

                    Password

                </label>


                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>


            <button
                class="btn full-btn"
                type="submit"
            >

                Login 🎟️

            </button>


        </form>


        <p class="auth-footer">

            Don't have an account?

            <a href="register.php">

                Create Account

            </a>

        </p>


    </div>

</div>


<?php include "footer.php"; ?>