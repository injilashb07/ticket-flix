
<?php

require_once "config.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $password   = $_POST['password'];

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password)
    ) {

        $message = "Please fill all fields.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters.";
        $message_type = "error";

    } else {

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ?"
        );

        mysqli_stmt_bind_param(
            $check,
            "s",
            $email
        );

        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($result) > 0) {

            $message = "Email already registered.";
            $message_type = "error";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users
                (email, password, phone, first_name, last_name)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $email,
                $hashed_password,
                $phone,
                $first_name,
                $last_name
            );

            if (mysqli_stmt_execute($stmt)) {

                header("Location: login.php?registered=1");
                exit;

            } else {

                $message = "Registration failed. Please try again.";
                $message_type = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}

include "header.php";

?>

<style>

/* =========================================================
   TICKETFLIX REGISTER PAGE
   PURPLE + GOLDEN + WHITE THEME
========================================================= */

.register-page {

    min-height: calc(100vh - 80px);

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 45px 20px;

    background:

        radial-gradient(
            circle at 15% 20%,
            rgba(126,63,242,.25),
            transparent 35%
        ),

        radial-gradient(
            circle at 85% 80%,
            rgba(212,175,55,.16),
            transparent 35%
        ),

        #100b18;

    position: relative;

    overflow: hidden;
}


/* DECORATIVE LIGHTS */

.register-page::before {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    border-radius: 50%;

    background: rgba(126,63,242,.12);

    filter: blur(70px);

    top: -100px;
    left: -80px;
}


.register-page::after {

    content: "";

    position: absolute;

    width: 250px;
    height: 250px;

    border-radius: 50%;

    background: rgba(212,175,55,.10);

    filter: blur(70px);

    bottom: -100px;
    right: -70px;
}


/* MAIN CARD */

.register-card {

    width: 100%;

    max-width: 900px;

    display: grid;

    grid-template-columns: 38% 62%;

    background: rgba(22,15,33,.96);

    border: 1px solid rgba(212,175,55,.25);

    border-radius: 26px;

    overflow: hidden;

    box-shadow:

        0 25px 80px rgba(0,0,0,.55),

        0 0 40px rgba(126,63,242,.08);

    position: relative;

    z-index: 2;

}


/* =========================================================
   LEFT SIDE
========================================================= */

.register-info {

    padding: 45px 32px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background:

        linear-gradient(
            145deg,
            rgba(126,63,242,.30),
            rgba(55,25,90,.30)
        );

    border-right: 1px solid rgba(212,175,55,.15);

    position: relative;

    overflow: hidden;
}


.register-info::before {

    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    border: 1px solid rgba(212,175,55,.15);

    right: -80px;
    top: -50px;
}


.register-info::after {

    content: "";

    position: absolute;

    width: 130px;
    height: 130px;

    border-radius: 50%;

    border: 1px solid rgba(126,63,242,.25);

    left: -65px;
    bottom: -40px;
}


.info-icon {

    width: 65px;
    height: 65px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 18px;

    background:

        linear-gradient(
            135deg,
            #d4af37,
            #f5d76e
        );

    color: #160d20;

    font-size: 27px;

    margin-bottom: 22px;

    box-shadow:

        0 10px 30px rgba(212,175,55,.18);
}


.register-info h1 {

    font-size: 30px;

    line-height: 1.2;

    margin-bottom: 12px;

    color: white;
}


.register-info h1 span {

    color: #d4af37;
}


.register-info > p {

    color: #aaa;

    font-size: 12px;

    line-height: 1.8;

    margin-bottom: 28px;
}


/* BENEFITS */

.benefit {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 13px 0;

    color: #ddd;

    font-size: 11px;
}


.benefit i {

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background: rgba(212,175,55,.10);

    color: #d4af37;

    font-size: 12px;
}


/* =========================================================
   RIGHT SIDE
========================================================= */

.register-form-area {

    padding: 42px 45px;

    background: rgba(16,11,24,.70);
}


/* LOGO */

.register-logo {

    text-align: center;

    font-size: 25px;

    font-weight: 800;

    margin-bottom: 7px;

    letter-spacing: .3px;
}


.register-logo i {

    color: #d4af37;

    margin-right: 5px;
}


.register-logo span {

    color: #d4af37;
}


.register-heading {

    text-align: center;

    margin-bottom: 25px;
}


.register-heading h2 {

    font-size: 22px;

    color: white;

    margin-bottom: 5px;
}


.register-heading p {

    color: #777;

    font-size: 11px;
}


/* MESSAGE */

.register-message {

    padding: 11px 14px;

    border-radius: 10px;

    margin-bottom: 18px;

    font-size: 11px;

    text-align: center;
}


.register-message.error {

    background: rgba(244,67,54,.10);

    border: 1px solid rgba(244,67,54,.22);

    color: #ff8585;
}


/* FORM */

.register-form {

    display: flex;

    flex-direction: column;

    gap: 14px;
}


.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 14px;
}


.form-group {

    position: relative;
}


.form-group label {

    display: block;

    color: #bbb;

    font-size: 10px;

    font-weight: 500;

    margin-bottom: 7px;

    letter-spacing: .3px;
}


.input-wrapper {

    position: relative;
}


.input-wrapper i {

    position: absolute;

    left: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #777;

    font-size: 12px;

    transition: .3s;

    pointer-events: none;
}


.input-wrapper input {

    width: 100%;

    height: 45px;

    padding: 0 13px 0 38px;

    background: rgba(255,255,255,.045);

    border: 1px solid rgba(255,255,255,.10);

    border-radius: 10px;

    outline: none;

    color: white;

    font-family: 'Poppins', sans-serif;

    font-size: 11px;

    transition: .3s;
}


.input-wrapper input::placeholder {

    color: #555;
}


.input-wrapper input:focus {

    border-color: #d4af37;

    background: rgba(212,175,55,.045);

    box-shadow:

        0 0 0 3px rgba(212,175,55,.07);
}


.input-wrapper input:focus + i {

    color: #d4af37;
}


/* PASSWORD */

.password-note {

    color: #666;

    font-size: 9px;

    margin-top: 5px;
}


/* BUTTON */

.register-btn {

    width: 100%;

    height: 47px;

    border: none;

    border-radius: 11px;

    margin-top: 5px;

    cursor: pointer;

    font-family: 'Poppins', sans-serif;

    font-size: 12px;

    font-weight: 700;

    color: #160d20;

    background:

        linear-gradient(
            135deg,
            #d4af37,
            #f5d76e
        );

    box-shadow:

        0 8px 25px rgba(212,175,55,.16);

    transition: .3s;
}


.register-btn:hover {

    transform: translateY(-2px);

    box-shadow:

        0 12px 30px rgba(212,175,55,.25);

}


.register-btn i {

    margin-left: 6px;
}


/* LOGIN */

.login-text {

    text-align: center;

    color: #777;

    font-size: 10px;

    margin-top: 19px;
}


.login-text a {

    color: #d4af37;

    text-decoration: none;

    font-weight: 600;

}


.login-text a:hover {

    text-decoration: underline;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .register-card {

        max-width: 600px;

        grid-template-columns: 1fr;
    }

    .register-info {

        display: none;
    }

    .register-form-area {

        padding: 35px 30px;
    }
}


@media(max-width:500px) {

    .register-page {

        padding: 25px 12px;
    }

    .register-form-area {

        padding: 30px 20px;
    }

    .form-row {

        grid-template-columns: 1fr;

        gap: 14px;
    }

    .register-card {

        border-radius: 20px;
    }
}

</style>


<div class="register-page">

    <div class="register-card">


        <!-- =================================================
             LEFT INFORMATION SECTION
        ================================================== -->

        <div class="register-info">

            <div class="info-icon">

                <i class="fa-solid fa-ticket"></i>

            </div>


            <h1>

                Your Movie Journey
                <span>Starts Here.</span>

            </h1>


            <p>

                Create your TicketFlix account and enjoy
                a simple and exciting way to discover
                movies and book your favourite seats.

            </p>


            <div class="benefit">

                <i class="fa-solid fa-film"></i>

                <span>
                    Discover latest movies
                </span>

            </div>


            <div class="benefit">

                <i class="fa-solid fa-couch"></i>

                <span>
                    Choose your favourite seats
                </span>

            </div>


            <div class="benefit">

                <i class="fa-solid fa-ticket"></i>

                <span>
                    Easy & fast ticket booking
                </span>

            </div>


            <div class="benefit">

                <i class="fa-solid fa-clock-rotate-left"></i>

                <span>
                    Manage your bookings easily
                </span>

            </div>

        </div>


        <!-- =================================================
             REGISTER FORM
        ================================================== -->

        <div class="register-form-area">


            <div class="register-logo">

                <i class="fa-solid fa-ticket"></i>

                Ticket<span>Flix</span>

            </div>


            <div class="register-heading">

                <h2>
                    Create Your Account
                </h2>

                <p>
                    Join us and start booking your movies 🎬
                </p>

            </div>


            <?php if ($message) { ?>

                <div class="register-message <?php
                    echo $message_type;
                ?>">

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php } ?>


            <form
                method="POST"
                class="register-form"
            >


                <!-- FIRST + LAST NAME -->

                <div class="form-row">


                    <div class="form-group">

                        <label>
                            First Name
                        </label>

                        <div class="input-wrapper">

                            <input
                                type="text"
                                name="first_name"
                                placeholder="Enter first name"
                                value="<?php
                                    echo htmlspecialchars(
                                        $_POST['first_name'] ?? ''
                                    );
                                ?>"
                                required
                            >

                            <i class="fa-solid fa-user"></i>

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Last Name
                        </label>

                        <div class="input-wrapper">

                            <input
                                type="text"
                                name="last_name"
                                placeholder="Enter last name"
                                value="<?php
                                    echo htmlspecialchars(
                                        $_POST['last_name'] ?? ''
                                    );
                                ?>"
                                required
                            >

                            <i class="fa-solid fa-user"></i>

                        </div>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Email Address
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST['email'] ?? ''
                                );
                            ?>"
                            required
                        >

                        <i class="fa-solid fa-envelope"></i>

                    </div>

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label>
                        Phone Number
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="text"
                            name="phone"
                            placeholder="Enter phone number"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST['phone'] ?? ''
                                );
                            ?>"
                            required
                        >

                        <i class="fa-solid fa-phone"></i>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Create a password"
                            required
                        >

                        <i class="fa-solid fa-lock"></i>

                    </div>

                    <div class="password-note">

                        <i class="fa-solid fa-circle-info"></i>

                        Password must contain at least 6 characters.

                    </div>

                </div>


                <!-- BUTTON -->

                <button
                    type="submit"
                    class="register-btn"
                >

                    Create Account

                    <i class="fa-solid fa-arrow-right"></i>

                </button>


            </form>


            <div class="login-text">

                Already have a TicketFlix account?

                <a href="login.php">
                    Login here
                </a>

            </div>


        </div>

    </div>

</div>


<?php include "footer.php"; 
