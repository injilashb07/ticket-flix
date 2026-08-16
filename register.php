<?php

require_once "config.php";

$message = "";

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

                $message = "Registration failed.";
            }
        }
    }
}

include "header.php";

?>

<div class="auth-container">

    <div class="auth-box">

        <div class="auth-logo">
            🎬 Ticket<span>Flix</span>
        </div>

        <h2>Create Account</h2>

        <p class="auth-subtitle">
            Join TicketFlix today
        </p>

        <?php if ($message) { ?>

            <div class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php } ?>


        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label>First Name</label>

                    <input
                        type="text"
                        name="first_name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Last Name</label>

                    <input
                        type="text"
                        name="last_name"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    required
                >

            </div>


            <div class="form-group">

                <label>Phone</label>

                <input
                    type="text"
                    name="phone"
                    required
                >

            </div>


            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    required
                >

            </div>


            <button type="submit" class="btn full-btn">
                Create Account
            </button>

        </form>


        <p class="auth-footer">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </p>

    </div>

</div>

<?php include "footer.php"; ?>