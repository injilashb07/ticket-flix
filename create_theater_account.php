<?php

include("config.php");

$message = "";
$error = "";

$theaters = $conn->query("
    SELECT id, name, city
    FROM theaters
    ORDER BY name ASC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $theater_id = intval($_POST["theater_id"]);
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if ($theater_id <= 0 || empty($name) || empty($email) || empty($password)) {

        $error = "Please fill all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email.";

    } else {

        $check = $conn->prepare("
            SELECT id
            FROM theater_users
            WHERE email = ?
        ");

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "This email is already registered.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                INSERT INTO theater_users
                (theater_id, name, email, password)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isss",
                $theater_id,
                $name,
                $email,
                $hashed_password
            );

            if ($stmt->execute()) {

                $message = "Theater account created successfully!";

            } else {

                $error = "Unable to create account.";

            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Create Theater Account | TicketFlix</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    font-family: 'Poppins', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(126,87,194,.3),
            transparent 35%
        ),
        #100b18;

    color: white;
}

.box {

    width: 450px;

    max-width: 92%;

    background: rgba(255,255,255,.05);

    border: 1px solid rgba(212,175,55,.2);

    border-radius: 22px;

    padding: 35px;

    box-shadow: 0 20px 60px rgba(0,0,0,.4);
}

.logo {

    text-align: center;

    font-size: 28px;

    font-weight: 800;

    margin-bottom: 10px;
}

.logo i,
.logo span {

    color: #d4af37;
}

.subtitle {

    text-align: center;

    color: #999;

    font-size: 13px;

    margin-bottom: 25px;
}

label {

    display: block;

    color: #aaa;

    font-size: 12px;

    margin-bottom: 7px;
}

input,
select {

    width: 100%;

    padding: 13px;

    border-radius: 10px;

    border: 1px solid rgba(255,255,255,.1);

    background: rgba(0,0,0,.25);

    color: white;

    outline: none;

    font-family: inherit;

    margin-bottom: 16px;
}

select option {

    background: #21152e;
}

input:focus,
select:focus {

    border-color: #d4af37;
}

button {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 10px;

    background: #d4af37;

    color: #171020;

    font-family: inherit;

    font-weight: 700;

    cursor: pointer;
}

.success {

    padding: 12px;

    border-radius: 10px;

    margin-bottom: 18px;

    background: rgba(46,204,113,.1);

    color: #61e69b;

    font-size: 12px;
}

.error {

    padding: 12px;

    border-radius: 10px;

    margin-bottom: 18px;

    background: rgba(231,76,60,.1);

    color: #ff8175;

    font-size: 12px;
}

</style>

</head>

<body>

<div class="box">

    <div class="logo">
        🎟️ Ticket<span>Flix</span>
    </div>

    <p class="subtitle">
        Create Theater Login Account
    </p>

    <?php if ($message) { ?>

        <div class="success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php } ?>

    <?php if ($error) { ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php } ?>

    <form method="POST">

        <label>Select Theater</label>

        <select name="theater_id" required>

            <option value="">
                Select Theater
            </option>

            <?php while ($theater = $theaters->fetch_assoc()) { ?>

                <option value="<?php echo $theater["id"]; ?>">

                    <?php
                    echo htmlspecialchars(
                        $theater["name"]
                    );
                    ?>

                    -
                    <?php
                    echo htmlspecialchars(
                        $theater["city"]
                    );
                    ?>

                </option>

            <?php } ?>

        </select>

        <label>Manager Name</label>

        <input
            type="text"
            name="name"
            placeholder="Theater manager name"
            required
        >

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="theater@example.com"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Create password"
            required
        >

        <button type="submit">
            Create Theater Account
        </button>

    </form>

</div>

</body>

</html>