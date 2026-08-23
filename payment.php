<?php

session_start();

require_once __DIR__ . "/config.php";

/* =========================================================
   PHPMailer
========================================================= */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$phpmailerPath = __DIR__ . "/PHPMailer/src";

require_once $phpmailerPath . "/Exception.php";
require_once $phpmailerPath . "/PHPMailer.php";
require_once $phpmailerPath . "/SMTP.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}


/* =========================================================
   GET PAYMENT DATA
========================================================= */

$showtime_id = isset($_POST['showtime_id'])
    ? (int) $_POST['showtime_id']
    : 0;

$seat_ids = $_POST['seat_ids'] ?? [];

$payment_method = $_POST['payment_method'] ?? 'card';


/* =========================================================
   VALIDATE SHOWTIME
========================================================= */

if ($showtime_id <= 0) {
    die("Invalid showtime.");
}


/* =========================================================
   HANDLE SEAT IDS
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

if (empty($seat_ids)) {
    die("No seats selected.");
}


/* =========================================================
   PAYMENT METHOD LABEL
========================================================= */

$payment_methods = [
    'card'       => 'Card',
    'upi'        => 'UPI',
    'netbanking' => 'Net Banking'
];

if (!isset($payment_methods[$payment_method])) {
    $payment_method = 'card';
}

$payment_method_name = $payment_methods[$payment_method];


/* =========================================================
   GET USER DETAILS
========================================================= */

$user_stmt = $conn->prepare("
    SELECT
        id,
        email,
        first_name,
        last_name
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$user_stmt) {
    die("User query error: " . htmlspecialchars($conn->error));
}

$user_stmt->bind_param(
    "i",
    $user_id
);

if (!$user_stmt->execute()) {
    die("User query failed: " . htmlspecialchars($user_stmt->error));
}

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();

if (!$user) {
    die("User not found.");
}


$user_name = trim(
    ($user['first_name'] ?? '') . " " .
    ($user['last_name'] ?? '')
);

if ($user_name === '') {
    $user_name = "TicketFlix User";
}

$user_email = trim($user['email']);


/* =========================================================
   GET SHOWTIME + MOVIE + SCREEN + THEATER
========================================================= */

$showtime_stmt = $conn->prepare("
    SELECT
        st.id AS showtime_id,
        st.movie_id,
        st.screen_id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,
        m.poster_image,

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

if (!$showtime_stmt) {
    die(
        "Showtime query error: " .
        htmlspecialchars($conn->error)
    );
}

$showtime_stmt->bind_param(
    "i",
    $showtime_id
);

if (!$showtime_stmt->execute()) {
    die(
        "Showtime query failed: " .
        htmlspecialchars($showtime_stmt->error)
    );
}

$showtime_result = $showtime_stmt->get_result();

$showtime = $showtime_result->fetch_assoc();

$showtime_stmt->close();


if (!$showtime) {
    die("Showtime not found.");
}


/* =========================================================
   SHOWTIME DATA
========================================================= */

$screen_id = (int) $showtime['screen_id'];

$movie_name = $showtime['movie_name'];

$screen_name = $showtime['screen_name'];

$theater_name = $showtime['theater_name'];

$theater_city = $showtime['city'];

$theater_state = $showtime['state'];

$theater_address = $showtime['address'];

$show_date = $showtime['show_date'];

$show_time = $showtime['show_time'];

$ticket_price = (float) $showtime['price'];


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


$seat_sql = "
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


$seat_stmt = $conn->prepare($seat_sql);

if (!$seat_stmt) {
    die(
        "Seat query error: " .
        htmlspecialchars($conn->error)
    );
}


$seat_params = $seat_ids;

$seat_params[] = $screen_id;

$seat_types =
    str_repeat(
        'i',
        count($seat_ids)
    ) . 'i';


$seat_stmt->bind_param(
    $seat_types,
    ...$seat_params
);


if (!$seat_stmt->execute()) {
    die(
        "Seat query failed: " .
        htmlspecialchars($seat_stmt->error)
    );
}


$seat_result = $seat_stmt->get_result();

$selected_seats = [];


while ($seat = $seat_result->fetch_assoc()) {

    $selected_seats[] = $seat;

}

$seat_stmt->close();


/* =========================================================
   VALIDATE ALL SEATS
========================================================= */

if (
    count($selected_seats)
    !==
    count($seat_ids)
) {

    die(
        "One or more selected seats are invalid."
    );

}


/* =========================================================
   SEAT LIST
========================================================= */

$seat_labels = [];

$seat_numbers = [];


foreach ($selected_seats as $seat) {

    $seat_labels[] =
        $seat['seat_row'] .
        $seat['seat_number'];

    $seat_numbers[] =
        $seat['seat_row'] .
        $seat['seat_number'];
}


$seat_list = implode(
    ", ",
    $seat_numbers
);


/* =========================================================
   CALCULATE PAYMENT AMOUNT
========================================================= */

/*
   IMPORTANT:

   We DO NOT trust total_amount coming from POST.

   Amount is calculated directly from:
   showtime price × number of seats
*/

$seat_count = count($selected_seats);

$subtotal =
    $ticket_price *
    $seat_count;

$booking_fee = 0.00;

$total_amount =
    $subtotal +
    $booking_fee;


/* =========================================================
   VALIDATE PAYMENT AMOUNT
========================================================= */

if ($total_amount <= 0) {
    die("Invalid payment amount.");
}


/* =========================================================
   START TRANSACTION
========================================================= */

$conn->begin_transaction();


try {

    /* =====================================================
       CHECK ALREADY BOOKED SEATS
    ===================================================== */

    $conflict_sql = "
        SELECT
            bs.seat_id

        FROM booking_seats bs

        INNER JOIN bookings b
            ON bs.booking_id = b.id

        WHERE b.showtime_id = ?

        AND bs.seat_id IN ($placeholders)

        AND (
            b.booking_status = 'pending'
            OR
            b.booking_status = 'confirmed'
        )

        LIMIT 1
    ";


    $conflict_stmt =
        $conn->prepare($conflict_sql);


    if (!$conflict_stmt) {

        throw new Exception(
            "Seat conflict query failed: " .
            $conn->error
        );

    }


    $conflict_values =
        array_merge(
            [$showtime_id],
            $seat_ids
        );


    $conflict_types =
        'i' .
        str_repeat(
            'i',
            count($seat_ids)
        );


    $conflict_stmt->bind_param(
        $conflict_types,
        ...$conflict_values
    );


    if (!$conflict_stmt->execute()) {

        throw new Exception(
            "Seat availability check failed: " .
            $conflict_stmt->error
        );

    }


    $conflict_result =
        $conflict_stmt->get_result();


    if ($conflict_result->num_rows > 0) {

        $conflict_stmt->close();

        throw new Exception(
            "One or more selected seats are already booked."
        );

    }


    $conflict_stmt->close();


    /* =====================================================
       GENERATE BOOKING NUMBER
    ===================================================== */

    $booking_number =
        date('Ymd') .
        rand(100000, 999999);


    /* =====================================================
       GENERATE BOOKING REFERENCE
    ===================================================== */

    $booking_reference =
        "TF" .
        date("Ymd") .
        strtoupper(
            substr(
                bin2hex(
                    random_bytes(5)
                ),
                0,
                8
            )
        );


    /* =====================================================
       BOOKING STATUS
    ===================================================== */

    $booking_status = "confirmed";


    /* =====================================================
       INSERT BOOKING
    ===================================================== */

    $booking_stmt = $conn->prepare("
        INSERT INTO bookings
        (
            booking_number,
            booking_reference,
            user_id,
            showtime_id,
            total_amount,
            booking_status
        )
        VALUES
        (?, ?, ?, ?, ?, ?)
    ");


    if (!$booking_stmt) {

        throw new Exception(
            "Booking insert error: " .
            $conn->error
        );

    }


    $booking_stmt->bind_param(
        "ssiids",
        $booking_number,
        $booking_reference,
        $user_id,
        $showtime_id,
        $total_amount,
        $booking_status
    );


    if (!$booking_stmt->execute()) {

        throw new Exception(
            "Booking could not be created: " .
            $booking_stmt->error
        );

    }


    $booking_id =
        $conn->insert_id;


    $booking_stmt->close();


    /* =====================================================
       INSERT BOOKING SEATS
    ===================================================== */

    $booking_seat_stmt = $conn->prepare("
        INSERT INTO booking_seats
        (
            booking_id,
            seat_id
        )
        VALUES
        (?, ?)
    ");


    if (!$booking_seat_stmt) {

        throw new Exception(
            "Booking seats query error: " .
            $conn->error
        );

    }


    foreach ($seat_ids as $seat_id) {

        $booking_seat_stmt->bind_param(
            "ii",
            $booking_id,
            $seat_id
        );


        if (!$booking_seat_stmt->execute()) {

            throw new Exception(
                "Seat could not be booked: " .
                $booking_seat_stmt->error
            );

        }

    }


    $booking_seat_stmt->close();


    /* =====================================================
       GENERATE TRANSACTION ID
    ===================================================== */

    $transaction_id =
        "TXN" .
        date("YmdHis") .
        strtoupper(
            substr(
                bin2hex(
                    random_bytes(4)
                ),
                0,
                8
            )
        );


    /* =====================================================
       PAYMENT STATUS
    ===================================================== */

    $payment_status = "paid";


    /* =====================================================
       INSERT PAYMENT
    ===================================================== */

    $payment_stmt = $conn->prepare("
        INSERT INTO payments
        (
            transaction_id,
            booking_id,
            amount,
            payment_method,
            payment_status
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");


    if (!$payment_stmt) {

        throw new Exception(
            "Payment query error: " .
            $conn->error
        );

    }


    $payment_stmt->bind_param(
        "sidss",
        $transaction_id,
        $booking_id,
        $total_amount,
        $payment_method_name,
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


} catch (Throwable $e) {

    $conn->rollback();

    die(
        "<div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
        '>
            <h2 style='color:#c0392b;'>
                Booking Failed
            </h2>

            <p>" .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ) .
            "</p>

            <br>

            <a href='javascript:history.back()'
               style='
                   display:inline-block;
                   padding:12px 25px;
                   background:#f4c430;
                   color:#21102f;
                   text-decoration:none;
                   border-radius:8px;
                   font-weight:bold;
               '>
                ← Go Back
            </a>

        </div>"
    );

}


/* =========================================================
   CREATE TICKETS FOLDER
========================================================= */

$tickets_folder =
    __DIR__ . "/tickets";


if (!is_dir($tickets_folder)) {

    if (!mkdir(
        $tickets_folder,
        0777,
        true
    )) {

        die(
            "Unable to create tickets folder."
        );

    }

}


/* =========================================================
   QR CODE LIBRARY
========================================================= */

$qr_library =
    __DIR__ . "/phpqrcode/qrlib.php";


if (!file_exists($qr_library)) {

    die(
        "QR library not found.<br><br>" .
        "Please make sure this file exists:<br>" .
        htmlspecialchars($qr_library)
    );

}


require_once $qr_library;


if (!class_exists("QRcode")) {

    die(
        "QRcode class was not loaded."
    );

}


/* =========================================================
   QR FILE NAME
========================================================= */

$clean_booking_number =
    preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        $booking_number
    );


$qr_filename =
    "ticketflix_qr_" .
    $clean_booking_number .
    ".png";


$qr_path =
    $tickets_folder .
    DIRECTORY_SEPARATOR .
    $qr_filename;


/* =========================================================
   QR CONTENT
========================================================= */

$qr_data =
    "TicketFlix\n" .
    "Booking Number: " .
    $booking_number . "\n" .
    "Booking Reference: " .
    $booking_reference . "\n" .
    "Transaction ID: " .
    $transaction_id . "\n" .
    "Movie: " .
    $movie_name . "\n" .
    "Theater: " .
    $theater_name . "\n" .
    "Screen: " .
    $screen_name . "\n" .
    "Date: " .
    $show_date . "\n" .
    "Time: " .
    $show_time . "\n" .
    "Seats: " .
    $seat_list;


/* =========================================================
   GENERATE QR
========================================================= */

$qr_generated = false;


try {

    QRcode::png(
        $qr_data,
        $qr_path,
        QR_ECLEVEL_M,
        8,
        2
    );


    if (
        file_exists($qr_path) &&
        filesize($qr_path) > 0
    ) {

        $image_info =
            @getimagesize($qr_path);


        if ($image_info !== false) {

            $qr_generated = true;

        } else {

            @unlink($qr_path);

        }

    }

} catch (Throwable $e) {

    $qr_generated = false;

    error_log(
        "TicketFlix QR Error: " .
        $e->getMessage()
    );

}


/* =========================================================
   QR CID
========================================================= */

$qr_cid =
    "ticketflix_qr_" .
    $clean_booking_number;


/* =========================================================
   HTML ESCAPING
========================================================= */

$safe_user_name =
    htmlspecialchars(
        $user_name,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_booking_number =
    htmlspecialchars(
        $booking_number,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_booking_reference =
    htmlspecialchars(
        $booking_reference,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_transaction_id =
    htmlspecialchars(
        $transaction_id,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_movie_name =
    htmlspecialchars(
        $movie_name,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_theater_name =
    htmlspecialchars(
        $theater_name,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_theater_address =
    htmlspecialchars(
        $theater_address,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_theater_city =
    htmlspecialchars(
        $theater_city,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_theater_state =
    htmlspecialchars(
        $theater_state,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_screen_name =
    htmlspecialchars(
        $screen_name,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_show_date =
    htmlspecialchars(
        date(
            "D, d M Y",
            strtotime($show_date)
        ),
        ENT_QUOTES,
        'UTF-8'
    );


$safe_show_time =
    htmlspecialchars(
        date(
            "h:i A",
            strtotime($show_time)
        ),
        ENT_QUOTES,
        'UTF-8'
    );


$safe_seat_list =
    htmlspecialchars(
        $seat_list,
        ENT_QUOTES,
        'UTF-8'
    );


$safe_payment_method =
    htmlspecialchars(
        $payment_method_name,
        ENT_QUOTES,
        'UTF-8'
    );


/* =========================================================
   QR HTML
========================================================= */

if (
    $qr_generated &&
    file_exists($qr_path)
) {

    $qr_html = "

        <div style='
            text-align:center;
            margin:25px 0;
            padding:20px;
            background:#fffaf0;
            border:1px solid #f4c430;
            border-radius:12px;
        '>

            <h3 style='
                color:#21102f;
                margin:0 0 15px 0;
            '>
                &#127903; Your QR Ticket
            </h3>

            <p style='
                color:#666;
                margin-bottom:15px;
            '>
                Show this QR code at the theater.
            </p>

            <img
                src='cid:$qr_cid'
                alt='TicketFlix QR Code'
                width='220'
                height='220'
                style='
                    display:block;
                    margin:0 auto;
                    width:220px;
                    height:220px;
                    border:8px solid white;
                    border-radius:8px;
                '
            >

            <p style='
                margin:15px 0 0;
                color:#21102f;
                font-weight:bold;
            '>
                Booking No: $safe_booking_number
            </p>

        </div>

    ";

} else {

    $qr_html = "

        <div style='
            text-align:center;
            margin:25px 0;
            padding:20px;
            background:#fffaf0;
            border:1px solid #f4c430;
            border-radius:12px;
        '>

            <h3 style='
                color:#21102f;
                margin:0 0 15px 0;
            '>
                &#127903; Your QR Ticket
            </h3>

            <p style='color:red;'>
                QR code could not be generated.
            </p>

        </div>

    ";

}


/* =========================================================
   SEND EMAIL
========================================================= */

$mail = new PHPMailer(true);

$email_error = null;


try {

    /* =====================================================
       SMTP
    ===================================================== */

    $mail->isSMTP();

    $mail->Host = "smtp.gmail.com";

    $mail->SMTPAuth = true;

    $mail->Username =
        "ticketflix40@gmail.com";

    /*
       PUT YOUR EXISTING GMAIL APP PASSWORD HERE
    */

    $mail->Password =
        "whus euir nvbo tbbu";


    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = 587;


    /* =====================================================
       UTF-8 FIX
    ===================================================== */

    $mail->CharSet = "UTF-8";

    $mail->Encoding = "base64";


    /* =====================================================
       FROM
    ===================================================== */

    $mail->setFrom(
        "ticketflix40@gmail.com",
        "TicketFlix"
    );


    /* =====================================================
       TO
    ===================================================== */

    $mail->addAddress(
        $user_email,
        $user_name
    );


    /* =====================================================
       INLINE QR
    ===================================================== */

    if (
        $qr_generated &&
        file_exists($qr_path)
    ) {

        $mail->addEmbeddedImage(
            $qr_path,
            $qr_cid,
            "TicketFlix_QR.png",
            "base64",
            "image/png"
        );

    }


    /* =====================================================
       SUBJECT
    ===================================================== */

    $mail->Subject =
        "TicketFlix Booking Confirmed - " .
        $booking_number;


    $mail->isHTML(true);


    /* =====================================================
       EMAIL BODY
    ===================================================== */

    $mail->Body = "

<!DOCTYPE html>

<html>

<head>

<meta charset='UTF-8'>

<title>TicketFlix Booking Confirmation</title>

</head>

<body style='
    margin:0;
    padding:0;
    background:#f5f2f7;
    font-family:Arial,Helvetica,sans-serif;
'>


<div style='
    max-width:650px;
    margin:30px auto;
    background:#ffffff;
    border-radius:15px;
    overflow:hidden;
'>


<!-- HEADER -->

<div style='
    background:#21102f;
    padding:25px;
    text-align:center;
'>

    <h1 style='
        color:#f4c430;
        margin:0;
        font-size:30px;
    '>
        &#127909; TicketFlix
    </h1>

    <p style='
        color:#ffffff;
        margin:8px 0 0;
    '>
        Movie Ticket Booking
    </p>

</div>


<!-- CONTENT -->

<div style='padding:30px;'>


<h2 style='
    color:#21102f;
    margin-top:0;
'>
    &#127881; Booking Confirmed!
</h2>


<p style='
    color:#444;
    font-size:16px;
'>
    Hello <strong>$safe_user_name</strong>,
</p>


<p style='
    color:#555;
    line-height:1.6;
'>
    Your TicketFlix movie ticket has been successfully booked.
</p>


<!-- BOOKING NUMBER -->

<div style='
    margin:20px 0;
    padding:18px;
    background:#21102f;
    border-radius:10px;
    text-align:center;
'>

    <p style='
        color:#ffffff;
        margin:0 0 8px;
        font-weight:bold;
    '>
        Booking Number
    </p>

    <span style='
        color:#f4c430;
        font-size:22px;
        font-weight:bold;
    '>
        $safe_booking_number
    </span>

</div>


<!-- QR -->

$qr_html


<!-- DETAILS -->

<h3 style='
    color:#21102f;
    border-bottom:2px solid #f4c430;
    padding-bottom:8px;
'>
    &#127903; Booking Details
</h3>


<table style='
    width:100%;
    border-collapse:collapse;
'>


<tr>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Booking Reference
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_booking_reference
</td>

</tr>


<tr style='background:#fafafa;'>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Transaction ID
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_transaction_id
</td>

</tr>


<tr>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Payment Method
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_payment_method
</td>

</tr>


<tr style='background:#fafafa;'>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Movie
</td>

<td style='
    padding:10px;
    color:#21102f;
    font-weight:bold;
'>
    $safe_movie_name
</td>

</tr>


<tr>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Theater
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_theater_name
    <br>

    <span style='
        color:#777;
        font-size:13px;
    '>
        $safe_theater_address
        <br>
        $safe_theater_city, $safe_theater_state
    </span>

</td>

</tr>


<tr style='background:#fafafa;'>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Screen
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_screen_name
</td>

</tr>


<tr>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Date
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_show_date
</td>

</tr>


<tr style='background:#fafafa;'>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Time
</td>

<td style='
    padding:10px;
    color:#21102f;
'>
    $safe_show_time
</td>

</tr>


<tr>

<td style='
    padding:10px;
    font-weight:bold;
    color:#555;
'>
    Seats
</td>

<td style='
    padding:10px;
    color:#21102f;
    font-weight:bold;
'>
    $safe_seat_list
</td>

</tr>


<tr style='background:#fafafa;'>

<td style='
    padding:15px 10px;
    font-weight:bold;
    color:#555;
'>
    Total Paid
</td>

<td style='
    padding:15px 10px;
    color:#21102f;
    font-weight:bold;
    font-size:20px;
'>
    &#8377;" .
    number_format(
        $total_amount,
        2
    ) .
"
</td>

</tr>


</table>


<div style='
    margin-top:25px;
    padding:15px;
    background:#f8f4fb;
    border-radius:8px;
    text-align:center;
'>

<p style='
    margin:0;
    color:#555;
'>
    Please show the QR code above at the theater.
</p>

</div>


</div>


<!-- FOOTER -->

<div style='
    background:#21102f;
    padding:18px;
    text-align:center;
'>

<p style='
    margin:0;
    color:#f4c430;
    font-weight:bold;
'>
    Thank you for choosing TicketFlix! &#127909;
</p>

</div>


</div>

</body>

</html>
";


    /* =====================================================
       PLAIN TEXT
    ===================================================== */

    $mail->AltBody =
        "TicketFlix Booking Confirmed\n\n" .

        "Hello " .
        $user_name .
        ",\n\n" .

        "Booking Number: " .
        $booking_number .
        "\n" .

        "Booking Reference: " .
        $booking_reference .
        "\n" .

        "Transaction ID: " .
        $transaction_id .
        "\n" .

        "Payment Method: " .
        $payment_method_name .
        "\n" .

        "Movie: " .
        $movie_name .
        "\n" .

        "Theater: " .
        $theater_name .
        "\n" .

        "Screen: " .
        $screen_name .
        "\n" .

        "Date: " .
        $show_date .
        "\n" .

        "Time: " .
        $show_time .
        "\n" .

        "Seats: " .
        $seat_list .
        "\n\n" .

        "Total Paid: INR " .
        number_format(
            $total_amount,
            2
        ) .
        "\n\n" .

        "Please show your QR code at the theater.";


    /* =====================================================
       SEND
    ===================================================== */

    $mail->send();


} catch (Exception $e) {

    $email_error =
        $mail->ErrorInfo;

}


/* =========================================================
   SAVE SESSION
========================================================= */

$_SESSION['booking_id'] =
    $booking_id;

$_SESSION['booking_number'] =
    $booking_number;

$_SESSION['booking_reference'] =
    $booking_reference;

$_SESSION['transaction_id'] =
    $transaction_id;


/* =========================================================
   SUCCESS PAGE
========================================================= */

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<meta http-equiv="refresh"
      content="3;url=success.php?booking_id=<?php
          echo urlencode($booking_id);
      ?>">

<title>TicketFlix - Booking Confirmed</title>

<style>

* {
    box-sizing:border-box;
}

body {

    margin:0;

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

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;

}

.container {

    width:90%;

    max-width:600px;

    background:white;

    padding:40px;

    text-align:center;

    border-radius:18px;

    box-shadow:
        0 20px 50px
        rgba(0,0,0,.35);

}

h1 {

    color:#21102f;

}

.booking {

    color:#f4c430;

    background:#21102f;

    padding:15px;

    border-radius:10px;

    font-size:24px;

    font-weight:bold;

    word-break:break-all;

}

.success {

    color:#188038;

}

.error {

    color:#c0392b;

}

</style>

</head>


<body>


<div class="container">


<h1>
    &#127881; Booking Confirmed!
</h1>


<p class="success">
    Your payment was successful.
</p>


<p>
    Booking Number:
</p>


<div class="booking">

<?php
echo htmlspecialchars(
    $booking_number,
    ENT_QUOTES,
    'UTF-8'
);
?>

</div>


<?php if ($qr_generated): ?>

<p class="success">
    QR ticket generated successfully.
</p>

<?php else: ?>

<p class="error">
    QR code could not be generated.
</p>

<?php endif; ?>


<?php if ($email_error): ?>

<p class="error">
    Booking completed, but email could not be sent.
</p>

<?php else: ?>

<p class="success">
    Confirmation email sent successfully.
</p>

<?php endif; ?>


<p>
    Redirecting to your booking...
</p>


</div>


</body>

</html>