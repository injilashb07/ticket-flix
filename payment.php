<?php

session_start();

require_once __DIR__ . "/config.php";

/*
|--------------------------------------------------------------------------
| COMPOSER AUTOLOAD
|--------------------------------------------------------------------------
| Make sure this file exists:
|
| Ticket Flix/
| ├── vendor/
| │   └── autoload.php
| ├── payment.php
| ├── config.php
| └── ...
|
*/
$autoload = __DIR__ . "/vendor/autoload.php";

if (!file_exists($autoload)) {
    die("
        <h2 style='color:red;text-align:center;margin-top:100px;'>
            Composer vendor/autoload.php not found
        </h2>
        <p style='text-align:center;'>
            Run <b>composer install</b> or <b>composer require chillerlan/php-qrcode phpmailer/phpmailer</b>
        </p>
    ");
}

require_once $autoload;


/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/*
|--------------------------------------------------------------------------
| chillerlan QR Code
|--------------------------------------------------------------------------
*/

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

$user_id = (int)$_SESSION['user_id'];


/* =========================================================
   ONLY POST REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit();

}


/* =========================================================
   GET FORM DATA
========================================================= */

$showtime_id = isset($_POST['showtime_id'])
    ? (int)$_POST['showtime_id']
    : 0;

$seat_ids = $_POST['seat_ids'] ?? [];

$payment_method = isset($_POST['payment_method'])
    ? trim($_POST['payment_method'])
    : '';


/* =========================================================
   CLEAN SEAT IDS
========================================================= */

if (!is_array($seat_ids)) {

    $seat_ids = explode(',', $seat_ids);

}

$seat_ids = array_map('intval', $seat_ids);

$seat_ids = array_filter(
    $seat_ids,
    function ($id) {
        return $id > 0;
    }
);

$seat_ids = array_values(
    array_unique($seat_ids)
);


/* =========================================================
   BASIC VALIDATION
========================================================= */

if (
    $showtime_id <= 0 ||
    empty($seat_ids) ||
    $payment_method === ''
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Payment Error
            </h2>

            <p>
                Showtime, seats or payment method is missing.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   GET USER DETAILS
========================================================= */

$user_stmt = $conn->prepare("
    SELECT
        id,
        name,
        email
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$user_stmt) {

    die(
        "User query error: " .
        htmlspecialchars($conn->error)
    );

}

$user_stmt->bind_param(
    "i",
    $user_id
);

if (!$user_stmt->execute()) {

    die(
        "User query execution error: " .
        htmlspecialchars($user_stmt->error)
    );

}

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


if (!$user || empty($user['email'])) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                User Email Not Found
            </h2>

            <p>
                Registered user's email address could not be found.
            </p>

        </div>
    ");

}

$user_email = trim($user['email']);

$user_name = !empty($user['name'])
    ? trim($user['name'])
    : 'TicketFlix User';


/* =========================================================
   GET SHOWTIME DETAILS
========================================================= */

$stmt = $conn->prepare("

    SELECT

        st.id AS showtime_id,
        st.movie_id,
        st.screen_id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,

        s.screen_name,

        t.name AS theater_name,
        t.city,
        t.state,
        t.address

    FROM showtimes st

    INNER JOIN movies m
        ON st.movie_id = m.id

    INNER JOIN screens s
        ON st.screen_id = s.id

    INNER JOIN theaters t
        ON s.theater_id = t.id

    WHERE st.id = ?

    LIMIT 1

");


if (!$stmt) {

    die(
        "Showtime query error: " .
        htmlspecialchars($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $showtime_id
);


if (!$stmt->execute()) {

    die(
        "Showtime query execution error: " .
        htmlspecialchars($stmt->error)
    );

}


$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Showtime Not Found
            </h2>

            <p>
                Showtime ID:
                " . htmlspecialchars($showtime_id) . "
            </p>

        </div>
    ");

}


/* =========================================================
   GET SELECTED SEATS
========================================================= */

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($seat_ids),
        '?'
    )
);

$types = str_repeat(
    'i',
    count($seat_ids)
);


$sql = "

    SELECT

        id,
        screen_id,
        seat_row,
        seat_number,
        seat_type,
        is_active

    FROM seats

    WHERE id IN ($placeholders)

    AND screen_id = ?

    AND is_active = 1

    ORDER BY
        seat_row ASC,
        seat_number ASC

";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );

}


$params = $seat_ids;

$params[] = (int)$showtime['screen_id'];

$final_types = $types . "i";


$stmt->bind_param(
    $final_types,
    ...$params
);


if (!$stmt->execute()) {

    die(
        "Seat query execution error: " .
        htmlspecialchars($stmt->error)
    );

}


$seat_result = $stmt->get_result();

$selected_seats = [];


while ($seat = $seat_result->fetch_assoc()) {

    $selected_seats[] = $seat;

}


$stmt->close();


/* =========================================================
   VALIDATE SEATS
========================================================= */

if (
    count($selected_seats) !== count($seat_ids)
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Invalid Seats
            </h2>

            <p>
                One or more selected seats are invalid.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Select Seats Again
            </a>

        </div>
    ");

}


/* =========================================================
   CREATE SEAT LABELS
========================================================= */

$seat_labels = [];

foreach ($selected_seats as $seat) {

    $seat_labels[] =
        $seat['seat_row'] .
        $seat['seat_number'];

}


/* =========================================================
   CHECK ALREADY BOOKED SEATS
========================================================= */

$check_placeholders = implode(
    ',',
    array_fill(
        0,
        count($seat_ids),
        '?'
    )
);

$check_types = str_repeat(
    'i',
    count($seat_ids)
);


$check_sql = "

    SELECT bs.seat_id

    FROM booking_seats bs

    INNER JOIN bookings b
        ON b.id = bs.booking_id

    WHERE b.showtime_id = ?

    AND bs.seat_id IN ($check_placeholders)

    AND (
        b.booking_status = 'pending'
        OR
        b.booking_status = 'confirmed'
    )

    LIMIT 1

";


$check_stmt = $conn->prepare($check_sql);


if (!$check_stmt) {

    die(
        "Booking check error: " .
        htmlspecialchars($conn->error)
    );

}


$check_params = array_merge(
    [$showtime_id],
    $seat_ids
);

$check_final_types =
    'i' . $check_types;


$check_stmt->bind_param(
    $check_final_types,
    ...$check_params
);


if (!$check_stmt->execute()) {

    die(
        "Booking check execution error: " .
        htmlspecialchars($check_stmt->error)
    );

}


$check_result =
    $check_stmt->get_result();


if ($check_result->num_rows > 0) {

    $check_stmt->close();

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Seat Already Booked
            </h2>

            <p>
                One or more selected seats have already been booked.
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Select Different Seats
            </a>

        </div>
    ");

}


$check_stmt->close();


/* =========================================================
   CALCULATE TOTAL
========================================================= */

$ticket_price =
    (float)$showtime['price'];

$seat_count =
    count($selected_seats);

$subtotal =
    $ticket_price * $seat_count;

$booking_fee = 0;

$total_amount =
    $subtotal + $booking_fee;


/* =========================================================
   GENERATE BOOKING NUMBER
========================================================= */

/*
|--------------------------------------------------------------------------
| Example:
|
| 20260822153745123
|
| Starts with current year 2026.
|
*/

$booking_number =
    date('YmdHis')
    . str_pad(
        (string)random_int(0, 999),
        3,
        '0',
        STR_PAD_LEFT
    );


/* =========================================================
   GENERATE BOOKING REFERENCE
========================================================= */

$booking_reference =
    'TF'
    . date('Ymd')
    . strtoupper(
        substr(
            bin2hex(
                random_bytes(4)
            ),
            0,
            8
        )
    );


/* =========================================================
   GENERATE TRANSACTION ID
========================================================= */

$transaction_id =
    'TXN'
    . date('YmdHis')
    . strtoupper(
        substr(
            bin2hex(
                random_bytes(5)
            ),
            0,
            10
        )
    );


/* =========================================================
   PAYMENT STATUS
========================================================= */

$payment_status = 'completed';

$booking_status = 'confirmed';


/* =========================================================
   DATABASE TRANSACTION
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       INSERT INTO BOOKINGS
    ===================================================== */

    /*
    IMPORTANT:

    Your bookings table contains:

    booking_number
    booking_reference
    user_id
    showtime_id
    total_amount
    booking_status

    */

    $booking_sql = "

        INSERT INTO bookings

        (
            booking_number,
            booking_reference,
            user_id,
            showtime_id,
            total_amount,
            booking_status,
            payment_status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )

    ";


    $booking_stmt =
        $conn->prepare($booking_sql);


    if (!$booking_stmt) {

        throw new Exception(
            "Booking prepare error: " .
            $conn->error
        );

    }


    $booking_stmt->bind_param(
        "ssi dss",
        $booking_number,
        $booking_reference,
        $user_id,
        $showtime_id,
        $total_amount,
        $booking_status,
        $payment_status
    );


    /*
    Remove accidental spaces from bind type.
    */

    $booking_stmt->bind_param(
        "ssiidss",
        $booking_number,
        $booking_reference,
        $user_id,
        $showtime_id,
        $total_amount,
        $booking_status,
        $payment_status
    );


    if (!$booking_stmt->execute()) {

        throw new Exception(
            "Booking could not be saved: " .
            $booking_stmt->error
        );

    }


    $booking_id =
        $conn->insert_id;


    $booking_stmt->close();


    /* =====================================================
       INSERT BOOKING SEATS
    ===================================================== */

    $seat_stmt = $conn->prepare("

        INSERT INTO booking_seats
        (
            booking_id,
            seat_id
        )

        VALUES
        (
            ?,
            ?
        )

    ");


    if (!$seat_stmt) {

        throw new Exception(
            "Booking seats prepare error: " .
            $conn->error
        );

    }


    foreach ($selected_seats as $seat) {

        $seat_id =
            (int)$seat['id'];


        $seat_stmt->bind_param(
            "ii",
            $booking_id,
            $seat_id
        );


        if (!$seat_stmt->execute()) {

            throw new Exception(
                "Seat booking failed: " .
                $seat_stmt->error
            );

        }

    }


    $seat_stmt->close();


    /* =====================================================
       INSERT PAYMENT
    ===================================================== */

    /*
    IMPORTANT:

    Your payments table uses:

    transaction_id

    NOT payment_id.
    */

    $payment_sql = "

        INSERT INTO payments

        (
            booking_id,
            transaction_id,
            payment_method,
            amount,
            payment_status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )

    ";


    $payment_stmt =
        $conn->prepare($payment_sql);


    if (!$payment_stmt) {

        throw new Exception(
            "Payment prepare error: " .
            $conn->error
        );

    }


    $payment_stmt->bind_param(
        "issds",
        $booking_id,
        $transaction_id,
        $payment_method,
        $total_amount,
        $payment_status
    );


    if (!$payment_stmt->execute()) {

        throw new Exception(
            "Payment could not be saved: " .
            $payment_stmt->error
        );

    }


    $payment_stmt->close();


    /* =====================================================
       COMMIT
    ===================================================== */

    $conn->commit();

}
catch (Exception $e) {

    $conn->rollback();


    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
            background:#100b18;
            color:white;
            min-height:100vh;
        '>

            <h2 style='color:#ff7777;'>
                Booking Failed
            </h2>

            <p style='
                max-width:700px;
                margin:20px auto;
                line-height:1.7;
            '>
                " .
                htmlspecialchars(
                    $e->getMessage()
                )
                . "
            </p>

            <br>

            <a
                href='javascript:history.back()'
                style='
                    background:#f4c430;
                    color:#21102f;
                    padding:12px 25px;
                    border-radius:8px;
                    text-decoration:none;
                    font-weight:bold;
                '
            >
                ← Go Back
            </a>

        </div>
    ");

}


/* =========================================================
   GENERATE QR CODE
========================================================= */

/*
|--------------------------------------------------------------------------
| QR CONTENT
|--------------------------------------------------------------------------
|
| QR will contain ONLY the booking number.
|
| Example:
|
| 20260822153745123
|
*/

$qr_data =
    $booking_number;


/*
|--------------------------------------------------------------------------
| Temporary QR filename
|--------------------------------------------------------------------------
*/

$qr_filename =
    'ticketflix_qr_' .
    $booking_number .
    '.png';


$qr_path =
    sys_get_temp_dir() .
    DIRECTORY_SEPARATOR .
    $qr_filename;


/*
|--------------------------------------------------------------------------
| QR OPTIONS
|--------------------------------------------------------------------------
*/

try {

    $options = new QROptions();

    /*
    PNG output
    */
    $options->outputType = 'png';

    /*
    QR size
    */
    $options->scale = 10;

    /*
    Error correction
    */
    $options->eccLevel = 'L';


    /*
    Generate QR
    */
    $qrCode =
        new QRCode($options);


    $qr_output =
        $qrCode->render($qr_data);


    /*
    chillerlan can return a data URI.

    Example:

    data:image/png;base64,AAAA...
    */

    if (
        is_string($qr_output) &&
        strpos(
            $qr_output,
            'data:image'
        ) === 0
    ) {

        $parts =
            explode(
                ',',
                $qr_output,
                2
            );


        if (
            isset($parts[1])
        ) {

            $qr_binary =
                base64_decode(
                    $parts[1]
                );


            if (
                $qr_binary === false
            ) {

                throw new Exception(
                    "Could not decode QR image."
                );

            }


            file_put_contents(
                $qr_path,
                $qr_binary
            );

        }

    }
    else {

        /*
        Some versions may return
        raw image data.
        */

        file_put_contents(
            $qr_path,
            $qr_output
        );

    }


    /*
    Check QR file
    */

    if (
        !file_exists($qr_path) ||
        filesize($qr_path) <= 0
    ) {

        throw new Exception(
            "QR code file was not created."
        );

    }

    $qr_generated = true;

}
catch (Throwable $qr_error) {

    $qr_generated = false;

    $qr_error_message =
        $qr_error->getMessage();

}


/* =========================================================
   SEND EMAIL WITH QR CODE
========================================================= */

$mail_sent = false;

$mail_error = '';


try {

    $mail = new PHPMailer(true);


    /*
    ---------------------------------------------------------
    SMTP
    ---------------------------------------------------------
    */

    $mail->isSMTP();

    $mail->Host =
        'smtp.gmail.com';

    $mail->SMTPAuth =
        true;


    /*
    IMPORTANT:
    Replace these with your Gmail
    and Gmail App Password.
    */

    $mail->Username =
        'YOUR_GMAIL@gmail.com';

    $mail->Password =
        'YOUR_GMAIL_APP_PASSWORD';


    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port =
        587;


    /*
    ---------------------------------------------------------
    EMAIL
    ---------------------------------------------------------
    */

    $mail->setFrom(
        'YOUR_GMAIL@gmail.com',
        'TicketFlix'
    );


    $mail->addAddress(
        $user_email,
        $user_name
    );


    $mail->isHTML(true);


    $mail->Subject =
        'TicketFlix Booking Confirmation - '
        . $booking_number;


    /*
    ---------------------------------------------------------
    ATTACH QR
    ---------------------------------------------------------
    */

    if (
        $qr_generated &&
        file_exists($qr_path)
    ) {

        $mail->addAttachment(
            $qr_path,
            'TicketFlix_QR_' .
            $booking_number .
            '.png'
        );

    }


    /*
    ---------------------------------------------------------
    EMAIL HTML
    ---------------------------------------------------------
    */

    $safe_movie =
        htmlspecialchars(
            $showtime['movie_name']
        );

    $safe_theater =
        htmlspecialchars(
            $showtime['theater_name']
        );

    $safe_screen =
        htmlspecialchars(
            $showtime['screen_name']
        );

    $safe_booking_number =
        htmlspecialchars(
            $booking_number
        );

    $safe_reference =
        htmlspecialchars(
            $booking_reference
        );

    $safe_transaction =
        htmlspecialchars(
            $transaction_id
        );

    $safe_method =
        htmlspecialchars(
            strtoupper(
                $payment_method
            )
        );


    $seat_text =
        htmlspecialchars(
            implode(
                ', ',
                $seat_labels
            )
        );


    $mail->Body = "

    <!DOCTYPE html>

    <html>

    <body style='
        margin:0;
        padding:0;
        background:#f4f1f7;
        font-family:Arial,sans-serif;
    '>

        <div style='
            max-width:650px;
            margin:30px auto;
            background:white;
            border-radius:15px;
            overflow:hidden;
            box-shadow:0 5px 25px rgba(0,0,0,.12);
        '>

            <div style='
                background:#21102f;
                padding:25px;
                text-align:center;
            '>

                <h1 style='
                    color:white;
                    margin:0;
                    font-size:30px;
                '>
                    Ticket<span style='color:#f4c430;'>
                        Flix
                    </span>
                </h1>

            </div>


            <div style='padding:30px;'>

                <h2 style='
                    color:#21102f;
                    margin-top:0;
                '>
                    🎉 Booking Confirmed!
                </h2>


                <p style='color:#555;'>
                    Hello
                    <strong>
                        " . htmlspecialchars($user_name) . "
                    </strong>,
                </p>


                <p style='color:#555;line-height:1.6;'>

                    Your TicketFlix movie ticket has been
                    successfully booked.

                </p>


                <div style='
                    background:#f7f1fb;
                    padding:20px;
                    border-radius:12px;
                    margin:20px 0;
                '>

                    <p>
                        <strong>Movie:</strong>
                        $safe_movie
                    </p>

                    <p>
                        <strong>Theater:</strong>
                        $safe_theater
                    </p>

                    <p>
                        <strong>Screen:</strong>
                        $safe_screen
                    </p>

                    <p>
                        <strong>Date:</strong>
                        " .
                        date(
                            "d M Y",
                            strtotime(
                                $showtime['show_date']
                            )
                        )
                        . "
                    </p>

                    <p>
                        <strong>Time:</strong>
                        " .
                        date(
                            "h:i A",
                            strtotime(
                                $showtime['show_time']
                            )
                        )
                        . "
                    </p>

                    <p>
                        <strong>Seats:</strong>
                        $seat_text
                    </p>

                    <p>
                        <strong>Total Paid:</strong>
                        ₹" .
                        number_format(
                            $total_amount,
                            2
                        )
                        . "
                    </p>

                </div>


                <div style='
                    background:#21102f;
                    color:white;
                    padding:20px;
                    border-radius:12px;
                    margin:20px 0;
                '>

                    <p style='margin:8px 0;'>
                        <strong>
                            Booking Number:
                        </strong>
                        <br>

                        <span style='
                            color:#f4c430;
                            font-size:20px;
                        '>
                            $safe_booking_number
                        </span>
                    </p>


                    <p style='margin:8px 0;'>
                        <strong>
                            Booking Reference:
                        </strong>
                        <br>
                        $safe_reference
                    </p>


                    <p style='margin:8px 0;'>
                        <strong>
                            Transaction ID:
                        </strong>
                        <br>
                        $safe_transaction
                    </p>


                    <p style='margin:8px 0;'>
                        <strong>
                            Payment Method:
                        </strong>
                        $safe_method
                    </p>

                </div>


                <div style='
                    text-align:center;
                    padding:20px;
                    background:#fffaf0;
                    border:1px solid #f4c430;
                    border-radius:12px;
                '>

                    <h3 style='
                        color:#21102f;
                        margin-top:0;
                    '>
                        🎟️ Your QR Ticket
                    </h3>

                    <p style='color:#666;'>
                        Your QR code is attached to this email.
                    </p>

                    <p style='color:#666;'>
                        Show the QR code at the theater.
                    </p>

                </div>


                <p style='
                    margin-top:25px;
                    color:#777;
                    text-align:center;
                    font-size:13px;
                '>

                    Thank you for booking with TicketFlix! 🎬🍿

                </p>

            </div>

        </div>

    </body>

    </html>
    ";


    /*
    Plain text alternative
    */

    $mail->AltBody =
        "TicketFlix Booking Confirmation\n\n"
        . "Booking Number: "
        . $booking_number
        . "\n"
        . "Booking Reference: "
        . $booking_reference
        . "\n"
        . "Transaction ID: "
        . $transaction_id
        . "\n"
        . "Movie: "
        . $showtime['movie_name']
        . "\n"
        . "Seats: "
        . implode(
            ', ',
            $seat_labels
        )
        . "\n"
        . "Total: ₹"
        . number_format(
            $total_amount,
            2
        )
        . "\n\n"
        . "Your QR ticket is attached.";


    /*
    ---------------------------------------------------------
    SEND
    ---------------------------------------------------------
    */

    $mail->send();

    $mail_sent = true;

}
catch (Throwable $mail_exception) {

    $mail_sent = false;

    $mail_error =
        $mail_exception->getMessage();

}


/* =========================================================
   DELETE TEMPORARY QR AFTER EMAIL
========================================================= */

if (
    !empty($qr_path) &&
    file_exists($qr_path)
) {

    unlink($qr_path);

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

<title>
    Payment Successful | TicketFlix
</title>


<style>

* {
    box-sizing:border-box;
    margin:0;
    padding:0;
}


body {

    min-height:100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            #35145c,
            #170b26 45%,
            #09060f
        );

    color:white;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:25px;
}


.success-card {

    width:min(750px,100%);

    background:
        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid #634085;

    border-radius:25px;

    padding:45px 35px;

    text-align:center;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.4);
}


.success-icon {

    width:90px;

    height:90px;

    margin:0 auto 22px;

    border-radius:50%;

    background:#f4c430;

    color:#241033;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:48px;

    font-weight:bold;
}


h1 {

    font-size:32px;

    margin-bottom:12px;
}


h1 span {

    color:#f4c430;
}


.success-text {

    color:#c9bfd3;

    margin-bottom:28px;

    line-height:1.6;
}


/* BOOKING NUMBER */

.booking-number {

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.1);

    border-radius:13px;

    padding:18px;

    margin-bottom:22px;
}


.booking-number small {

    display:block;

    color:#9d94a7;

    margin-bottom:7px;
}


.booking-number strong {

    color:#f4c430;

    font-size:23px;

    letter-spacing:2px;
}


/* DETAILS */

.details {

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:12px;

    margin-bottom:22px;
}


.detail-box {

    background:
        rgba(255,255,255,.05);

    border-radius:10px;

    padding:14px;

    text-align:left;
}


.detail-box small {

    display:block;

    color:#91879b;

    margin-bottom:6px;

    font-size:11px;
}


.detail-box strong {

    font-size:14px;

    word-break:break-word;
}


/* EMAIL */

.email-box {

    padding:15px;

    border-radius:10px;

    margin-bottom:12px;

    font-size:13px;

    line-height:1.6;
}


.mail-success {

    background:
        rgba(46,204,113,.12);

    border:
        1px solid
        rgba(46,204,113,.3);

    color:#72e6a0;
}


.mail-failed {

    background:
        rgba(255,100,100,.10);

    border:
        1px solid
        rgba(255,100,100,.25);

    color:#ff9999;
}


.qr-success {

    background:
        rgba(46,204,113,.10);

    border:
        1px solid
        rgba(46,204,113,.25);

    color:#72e6a0;

    padding:12px;

    border-radius:9px;

    margin-bottom:12px;

    font-size:13px;
}


.qr-failed {

    background:
        rgba(255,100,100,.10);

    border:
        1px solid
        rgba(255,100,100,.25);

    color:#ff9999;

    padding:12px;

    border-radius:9px;

    margin-bottom:12px;

    font-size:13px;
}


.debug-box {

    margin-top:15px;

    padding:12px;

    background:
        rgba(255,255,255,.04);

    border-radius:8px;

    color:#aaa;

    font-size:11px;

    text-align:left;

    word-break:break-word;
}


.home-btn {

    display:inline-block;

    padding:13px 28px;

    border-radius:30px;

    background:#f4c430;

    color:#241033;

    text-decoration:none;

    font-weight:800;

    margin-top:10px;
}


.home-btn:hover {

    background:#ffd84f;
}


@media(max-width:550px) {

    .success-card {

        padding:35px 20px;

    }

    h1 {

        font-size:27px;

    }

    .details {

        grid-template-columns:1fr;

    }

}

</style>

</head>


<body>


<div class="success-card">


    <div class="success-icon">
        ✓
    </div>


    <h1>

        Payment
        <span>Successful!</span>

    </h1>


    <p class="success-text">

        Your TicketFlix booking has been
        successfully confirmed. 🎬🍿

    </p>


    <!-- BOOKING NUMBER -->

    <div class="booking-number">

        <small>
            Booking Number
        </small>

        <strong>

            <?= htmlspecialchars(
                $booking_number
            ); ?>

        </strong>

    </div>


    <!-- DETAILS -->

    <div class="details">


        <div class="detail-box">

            <small>
                Booking Reference
            </small>

            <strong>

                <?= htmlspecialchars(
                    $booking_reference
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Transaction ID
            </small>

            <strong>

                <?= htmlspecialchars(
                    $transaction_id
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Movie
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['movie_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Seats
            </small>

            <strong>

                <?= htmlspecialchars(
                    implode(
                        ', ',
                        $seat_labels
                    )
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Theater
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['theater_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Screen
            </small>

            <strong>

                <?= htmlspecialchars(
                    $showtime['screen_name']
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Date
            </small>

            <strong>

                <?= date(
                    "d M Y",
                    strtotime(
                        $showtime['show_date']
                    )
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Time
            </small>

            <strong>

                <?= date(
                    "h:i A",
                    strtotime(
                        $showtime['show_time']
                    )
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Payment Method
            </small>

            <strong>

                <?= htmlspecialchars(
                    strtoupper(
                        $payment_method
                    )
                ); ?>

            </strong>

        </div>


        <div class="detail-box">

            <small>
                Total Paid
            </small>

            <strong>

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </strong>

        </div>


    </div>


    <!-- EMAIL STATUS -->

    <div class="email-box">

        📧 Confirmation email:

        <strong>

            <?= htmlspecialchars(
                $user_email
            ); ?>

        </strong>

    </div>


    <?php if ($qr_generated): ?>

        <div class="qr-success">

            ✅ QR code generated successfully using
            your Booking Number.

        </div>

    <?php else: ?>

        <div class="qr-failed">

            ⚠️ QR code could not be generated.

        </div>

    <?php endif; ?>


    <?php if ($mail_sent): ?>

        <div class="email-box mail-success">

            ✅ Confirmation email with your QR code
            was sent successfully!

        </div>

    <?php else: ?>

        <div class="email-box mail-failed">

            ⚠️ Booking was successful, but the
            confirmation email could not be sent.

        </div>

        <?php if (!empty($mail_error)): ?>

            <div class="debug-box">

                <strong>
                    Mail Error:
                </strong>

                <?= htmlspecialchars(
                    $mail_error
                ); ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>


    <a
        href="index.php"
        class="home-btn"
    >

        🏠 Back to Home

    </a>


</div>


</body>

</html>