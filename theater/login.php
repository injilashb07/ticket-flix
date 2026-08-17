
<?php

session_start();

require_once "../config.php";

/* If already logged in */
if (isset($_SESSION['theater_user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === "" || $password === "") {

        $error = "Please enter email and password.";

    } else {

        /*
         * Find theater user
         */
        $stmt = $conn->prepare("
            SELECT
                id,
                theater_id,
                name,
                email,
                password,
                is_active
            FROM theater_users
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            die("Database query error: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $stmt->store_result();

        if ($stmt->num_rows === 1) {

            $stmt->bind_result(
                $id,
                $theater_id,
                $name,
                $db_email,
                $db_password,
                $is_active
            );

            $stmt->fetch();

            /* Check account active */
            if ((int)$is_active !== 1) {

                $error = "Your theater account is inactive.";

            } else {

                /*
                 * Supports:
                 * 1. Normal text password
                 * 2. password_hash() password
                 */

                $valid_password = false;

                /* Normal text password */
                if ($password === $db_password) {

                    $valid_password = true;

                }

                /* Hashed password */
                elseif (
                    password_get_info($db_password)['algo'] !== 0 &&
                    password_verify($password, $db_password)
                ) {

                    $valid_password = true;

                }

                if ($valid_password) {

                    /*
                     * Get theater name
                     */
                    $theater_stmt = $conn->prepare("
                        SELECT name
                        FROM theaters
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $theater_stmt->bind_param("i", $theater_id);
                    $theater_stmt->execute();

                    $theater_stmt->store_result();

                    if ($theater_stmt->num_rows === 1) {

                        $theater_stmt->bind_result($theater_name);
                        $theater_stmt->fetch();

                    } else {

                        $theater_name = "Theater";

                    }

                    $theater_stmt->close();


                    /*
                     * Create secure session
                     */
                    session_regenerate_id(true);

                    $_SESSION['theater_user_id'] = $id;
                    $_SESSION['theater_id'] = $theater_id;
                    $_SESSION['theater_name'] = $theater_name;
                    $_SESSION['theater_user_name'] = $name;
                    $_SESSION['theater_email'] = $db_email;


                    /*
                     * Go to dashboard
                     */
                    header("Location: dashboard.php");
                    exit();

                } else {

                    $error = "Invalid email or password.";

                }
            }

        } else {

            $error = "Invalid email or password.";

        }

        $stmt->close();
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Theater Login | TicketFlix</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
rel="stylesheet"
>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    font-family: 'Poppins', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(126,87,194,.35),
            transparent 35%
        ),
        #100b18;

    color: white;
}

.login-container {

    width: 420px;

    max-width: 92%;

    background: rgba(255,255,255,.06);

    border: 1px solid rgba(212,175,55,.25);

    border-radius: 25px;

    padding: 35px;

    box-shadow: 0 20px 60px rgba(0,0,0,.4);
}

.logo {

    text-align: center;

    font-size: 30px;

    font-weight: 800;

    margin-bottom: 8px;
}

.logo i,
.logo span {

    color: #d4af37;
}

.subtitle {

    text-align: center;

    color: #999;

    font-size: 13px;

    margin-bottom: 30px;
}

.login-icon {

    width: 70px;

    height: 70px;

    margin: 0 auto 20px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: rgba(212,175,55,.12);

    color: #d4af37;

    font-size: 28px;
}

.form-group {

    margin-bottom: 18px;
}

.form-group label {

    display: block;

    color: #aaa;

    font-size: 12px;

    margin-bottom: 7px;
}

.input-box {

    position: relative;
}

.input-box i {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    color: #d4af37;
}

.input-box input {

    width: 100%;

    padding: 13px 15px 13px 43px;

    border-radius: 10px;

    border: 1px solid rgba(255,255,255,.1);

    background: rgba(0,0,0,.25);

    color: white;

    outline: none;

    font-family: inherit;
}

.input-box input:focus {

    border-color: #d4af37;
}

.btn {

    width: 100%;

    border: none;

    padding: 13px;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #f1cf5b
        );

    color: #171020;

    font-weight: 700;

    font-family: inherit;

    cursor: pointer;

    margin-top: 8px;
}

.error {

    padding: 12px;

    border-radius: 10px;

    margin-bottom: 18px;

    background: rgba(231,76,60,.12);

    border: 1px solid rgba(231,76,60,.25);

    color: #ff8175;

    font-size: 12px;
}

.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #aaa;

    text-decoration: none;

    font-size: 12px;
}

.back:hover {

    color: #d4af37;
}

</style>

</head>


<body>

<div class="login-container">

    <div class="login-icon">

        <i class="fa-solid fa-building"></i>

    </div>


    <div class="logo">

        <i class="fa-solid fa-ticket"></i>

        Ticket<span>Flix</span>

    </div>


    <p class="subtitle">
        Theater Management Portal
    </p>


    <?php if ($error !== "") { ?>

        <div class="error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php } ?>


    <form method="POST">

        <div class="form-group">

            <label>Email Address</label>

            <div class="input-box">

                <i class="fa-solid fa-envelope"></i>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter theater email"
                    autocomplete="email"
                    required
                >

            </div>

        </div>


        <div class="form-group">

            <label>Password</label>

            <div class="input-box">

                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required
                >

            </div>

        </div>


        <button type="submit" class="btn">

            <i class="fa-solid fa-right-to-bracket"></i>

            Login to Theater Panel

        </button>

    </form>


    <a href="../index.php" class="back">

        <i class="fa-solid fa-arrow-left"></i>

        Back to TicketFlix

    </a>

</div>

</body>

</html>
