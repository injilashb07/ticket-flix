<?php

session_start();

require_once "../config.php";

if (isset($_SESSION['theater_user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {

        $error = "Please enter email and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT 
                tu.id,
                tu.theater_id,
                tu.name,
                tu.email,
                tu.password,
                tu.is_active,
                t.name AS theater_name
            FROM theater_users tu
            INNER JOIN theaters t
                ON tu.theater_id = t.id
            WHERE tu.email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if ((int)$user['is_active'] !== 1) {

                $error = "Your theater account is inactive.";

            } else {

                /*
                 * Supports both password_hash() passwords
                 * and normal text passwords.
                 */

                $valid_password = false;

                if (
                    password_get_info($user['password'])['algo'] !== 0
                ) {

                    $valid_password = password_verify(
                        $password,
                        $user['password']
                    );

                } else {

                    $valid_password =
                        ($password === $user['password']);

                }

                if ($valid_password) {

                    session_regenerate_id(true);

                    $_SESSION['theater_user_id'] =
                        $user['id'];

                    $_SESSION['theater_id'] =
                        $user['theater_id'];

                    $_SESSION['theater_name'] =
                        $user['theater_name'];

                    $_SESSION['theater_user_name'] =
                        $user['name'];

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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

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

    background:
        rgba(255,255,255,.06);

    border:
        1px solid rgba(212,175,55,.25);

    border-radius: 25px;

    padding: 35px;

    box-shadow:
        0 20px 60px rgba(0,0,0,.4);
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

    background:
        rgba(212,175,55,.12);

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

    border:
        1px solid rgba(255,255,255,.1);

    background:
        rgba(0,0,0,.25);

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

    background:
        rgba(231,76,60,.12);

    border:
        1px solid rgba(231,76,60,.25);

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