
<?php

session_start();

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

    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";

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

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

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

                session_write_close();

                header("Location: index.php");
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Login | TicketFlix</title>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>

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
            circle at 15% 20%,
            rgba(126, 63, 242, .28),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 80%,
            rgba(212, 175, 55, .15),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #090611,
            #160d24,
            #0d0815
        );

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow-x: hidden;

    position: relative;
}


/* =====================================================
   BACKGROUND LIGHTS
===================================================== */

body::before {

    content: "";

    position: fixed;

    width: 320px;
    height: 320px;

    border-radius: 50%;

    background:
        rgba(126, 63, 242, .13);

    filter: blur(70px);

    top: -100px;
    left: -80px;

    pointer-events: none;
}

body::after {

    content: "";

    position: fixed;

    width: 280px;
    height: 280px;

    border-radius: 50%;

    background:
        rgba(212, 175, 55, .10);

    filter: blur(70px);

    bottom: -100px;
    right: -80px;

    pointer-events: none;
}


/* =====================================================
   LOGIN WRAPPER
===================================================== */

.login-wrapper {

    width: 92%;

    max-width: 1050px;

    min-height: 620px;

    display: grid;

    grid-template-columns: 1fr 1fr;

    border-radius: 28px;

    overflow: hidden;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid rgba(255,255,255,.10);

    box-shadow:
        0 30px 80px rgba(0,0,0,.55);

    backdrop-filter: blur(20px);

    position: relative;

    z-index: 2;
}


/* =====================================================
   LEFT SIDE
===================================================== */

.login-left {

    padding: 55px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background:
        linear-gradient(
            145deg,
            rgba(126,63,242,.20),
            rgba(212,175,55,.05)
        );

    position: relative;

    overflow: hidden;
}


.login-left::before {

    content: "";

    position: absolute;

    width: 250px;
    height: 250px;

    border-radius: 50%;

    border:
        1px solid rgba(212,175,55,.18);

    right: -80px;

    top: -80px;
}


.login-left::after {

    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    border:
        1px solid rgba(126,63,242,.18);

    left: -90px;

    bottom: -80px;
}


/* =====================================================
   LOGO
===================================================== */

.logo {

    font-size: 28px;

    font-weight: 800;

    margin-bottom: 50px;

    position: relative;

    z-index: 2;
}

.logo i {

    color: #d4af37;

    margin-right: 7px;
}

.logo span {

    color: #d4af37;
}


/* =====================================================
   LEFT CONTENT
===================================================== */

.left-content {

    position: relative;

    z-index: 2;
}

.left-content .tag {

    display: inline-block;

    padding: 7px 13px;

    border-radius: 30px;

    background:
        rgba(212,175,55,.10);

    border:
        1px solid rgba(212,175,55,.20);

    color: #d4af37;

    font-size: 10px;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    margin-bottom: 20px;
}


.left-content h1 {

    font-size: 42px;

    line-height: 1.2;

    margin-bottom: 18px;

    font-weight: 800;
}

.left-content h1 span {

    color: #d4af37;
}


.left-content p {

    color: #aaa;

    font-size: 13px;

    line-height: 1.9;

    max-width: 400px;

    margin-bottom: 30px;
}


/* =====================================================
   FEATURES
===================================================== */

.features {

    display: flex;

    flex-direction: column;

    gap: 14px;
}

.feature {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #ccc;

    font-size: 12px;
}

.feature-icon {

    width: 34px;
    height: 34px;

    border-radius: 9px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(126,63,242,.15);

    color: #d4af37;

    border:
        1px solid rgba(212,175,55,.10);
}


/* =====================================================
   RIGHT LOGIN AREA
===================================================== */

.login-right {

    padding: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(10,7,17,.48);
}


.login-card {

    width: 100%;

    max-width: 390px;
}


/* =====================================================
   LOGIN HEADING
===================================================== */

.login-card h2 {

    font-size: 29px;

    margin-bottom: 7px;

    font-weight: 700;
}


.login-card h2 span {

    color: #d4af37;
}


.subtitle {

    color: #888;

    font-size: 12px;

    margin-bottom: 30px;
}


/* =====================================================
   MESSAGE
===================================================== */

.info-message {

    padding: 12px 14px;

    border-radius: 10px;

    margin-bottom: 20px;

    background:
        rgba(212,175,55,.08);

    border:
        1px solid rgba(212,175,55,.20);

    color: #e6c75a;

    font-size: 11px;

    display: flex;

    align-items: center;

    gap: 9px;
}

.info-message::before {

    content: "\f071";

    font-family:
        "Font Awesome 6 Free";

    font-weight: 900;
}


/* =====================================================
   FORM
===================================================== */

.form-group {

    margin-bottom: 20px;

    position: relative;
}


.form-group label {

    display: block;

    font-size: 11px;

    color: #bbb;

    margin-bottom: 8px;

    font-weight: 500;
}


.input-wrapper {

    position: relative;
}


.input-wrapper i {

    position: absolute;

    left: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #777;

    font-size: 13px;
}


.form-group input {

    width: 100%;

    height: 48px;

    padding:
        0 45px 0 42px;

    border-radius: 11px;

    border:
        1px solid rgba(255,255,255,.10);

    background:
        rgba(255,255,255,.045);

    color: white;

    outline: none;

    font-family:
        'Poppins',
        sans-serif;

    font-size: 12px;

    transition: .3s;
}


.form-group input::placeholder {

    color: #666;
}


.form-group input:focus {

    border-color:
        rgba(212,175,55,.55);

    background:
        rgba(212,175,55,.035);

    box-shadow:
        0 0 0 3px rgba(212,175,55,.07);
}


/* =====================================================
   PASSWORD TOGGLE
===================================================== */

.password-toggle {

    position: absolute;

    right: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #777;

    cursor: pointer;

    font-size: 13px;

    transition: .2s;
}

.password-toggle:hover {

    color: #d4af37;
}


/* =====================================================
   LOGIN BUTTON
===================================================== */

.login-btn {

    width: 100%;

    height: 50px;

    border: none;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f0cf63
        );

    color: #140d1d;

    font-family:
        'Poppins',
        sans-serif;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    margin-top: 5px;

    box-shadow:
        0 10px 25px rgba(212,175,55,.18);

    transition: .3s;
}


.login-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 15px 30px rgba(212,175,55,.28);
}


.login-btn:active {

    transform:
        translateY(0);
}


/* =====================================================
   DIVIDER
===================================================== */

.divider {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 25px 0;

    color: #555;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.divider::before,
.divider::after {

    content: "";

    height: 1px;

    flex: 1;

    background:
        rgba(255,255,255,.08);
}


/* =====================================================
   REGISTER
===================================================== */

.register-text {

    text-align: center;

    color: #777;

    font-size: 11px;
}


.register-text a {

    color: #d4af37;

    text-decoration: none;

    font-weight: 600;

    margin-left: 4px;

    transition: .2s;
}


.register-text a:hover {

    color: #f1d46c;

    text-decoration: underline;
}


/* =====================================================
   FOOTER
===================================================== */

.security-note {

    margin-top: 24px;

    text-align: center;

    color: #555;

    font-size: 9px;
}

.security-note i {

    color: #d4af37;

    margin-right: 5px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:850px) {

    .login-wrapper {

        grid-template-columns: 1fr;

        max-width: 500px;

        min-height: auto;
    }

    .login-left {

        padding: 40px;

        min-height: 300px;
    }

    .logo {

        margin-bottom: 30px;
    }

    .left-content h1 {

        font-size: 32px;
    }

    .features {

        display: none;
    }

    .login-right {

        padding: 40px;
    }
}


@media(max-width:500px) {

    body {

        padding: 20px 0;
    }

    .login-wrapper {

        width: 94%;

        border-radius: 20px;
    }

    .login-left {

        padding: 30px 25px;
    }

    .login-right {

        padding: 30px 25px;
    }

    .left-content h1 {

        font-size: 27px;
    }

    .login-card h2 {

        font-size: 24px;
    }
}

</style>

</head>


<body>


<div class="login-wrapper">


    <!-- =================================================
         LEFT SIDE
    ================================================== -->

    <section class="login-left">


        <div class="logo">

            <i class="fa-solid fa-ticket"></i>

            Ticket<span>Flix</span>

        </div>


        <div class="left-content">

            <span class="tag">

                Movie Ticket Booking

            </span>


            <h1>

                Your next movie
                <span>adventure</span>
                starts here.

            </h1>


            <p>

                Book your favourite movies,
                choose your perfect seats and
                enjoy a seamless cinema experience
                with TicketFlix.

            </p>


            <div class="features">


                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-solid fa-film"></i>

                    </div>

                    <span>

                        Explore latest movies

                    </span>

                </div>


                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-solid fa-chair"></i>

                    </div>

                    <span>

                        Choose your favourite seats

                    </span>

                </div>


                <div class="feature">

                    <div class="feature-icon">

                        <i class="fa-solid fa-ticket"></i>

                    </div>

                    <span>

                        Easy & secure ticket booking

                    </span>

                </div>


            </div>

        </div>


    </section>



    <!-- =================================================
         RIGHT SIDE
    ================================================== -->

    <section class="login-right">


        <div class="login-card">


            <h2>

                Welcome <span>Back</span> 👋

            </h2>


            <p class="subtitle">

                Login to continue your movie journey

            </p>


            <?php if ($message) { ?>

                <div class="info-message">

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php } ?>



            <form method="POST">


                <!-- EMAIL -->

                <div class="form-group">

                    <label>

                        Email Address

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-envelope"></i>

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label>

                        Password

                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <i
                            class="fa-solid fa-eye password-toggle"
                            id="togglePassword"
                        ></i>

                    </div>

                </div>



                <!-- BUTTON -->

                <button
                    class="login-btn"
                    type="submit"
                >

                    <i class="fa-solid fa-right-to-bracket"></i>

                    &nbsp; Login to TicketFlix

                </button>


            </form>



            <div class="divider">

                OR

            </div>



            <p class="register-text">

                Don't have an account?

                <a href="register.php">

                    Create Account

                </a>

            </p>


            <div class="security-note">

                <i class="fa-solid fa-shield-halved"></i>

                Your login information is securely protected

            </div>


        </div>


    </section>


</div>



<script>

/* =====================================================
   PASSWORD SHOW / HIDE
===================================================== */

const password =
    document.getElementById("password");

const togglePassword =
    document.getElementById("togglePassword");


togglePassword.addEventListener(
    "click",
    function () {

        if (
            password.type === "password"
        ) {

            password.type = "text";

            this.classList.remove(
                "fa-eye"
            );

            this.classList.add(
                "fa-eye-slash"
            );

        } else {

            password.type = "password";

            this.classList.remove(
                "fa-eye-slash"
            );

            this.classList.add(
                "fa-eye"
            );
        }

    }
);

</script>


</body>

</html>
