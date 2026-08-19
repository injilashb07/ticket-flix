<?php

session_start();

require_once 'config.php';



/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| GET BOOKING DATA
|--------------------------------------------------------------------------
*/

$showtime_id = isset($_POST['showtime_id'])
    ? (int) $_POST['showtime_id']
    : (
        isset($_SESSION['payment_showtime_id'])
            ? (int) $_SESSION['payment_showtime_id']
            : 0
    );


$seat_ids = [];


if (
    isset($_POST['seat_ids']) &&
    is_array($_POST['seat_ids'])
) {

    $seat_ids = array_map(
        'intval',
        $_POST['seat_ids']
    );

} elseif (
    isset($_SESSION['payment_seat_ids']) &&
    is_array($_SESSION['payment_seat_ids'])
) {

    $seat_ids = array_map(
        'intval',
        $_SESSION['payment_seat_ids']
    );

}


$seat_ids = array_values(
    array_unique(
        array_filter(
            $seat_ids,
            function ($id) {
                return $id > 0;
            }
        )
    )
);


if (
    $showtime_id <= 0 ||
    empty($seat_ids)
) {

    die("Invalid booking information.");

}


/*
|--------------------------------------------------------------------------
| SAVE PAYMENT DATA IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION['payment_showtime_id'] = $showtime_id;

$_SESSION['payment_seat_ids'] = $seat_ids;


/*
|------------------------------------------------------------------
| GET REGISTERED USER DETAILS
|------------------------------------------------------------------
*/

$booking_email = '';
$booking_name = 'TicketFlix User';

$user_id = (int) $_SESSION['user_id'];

$user_stmt = $conn->prepare("
    SELECT name, email
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($user_stmt) {

    $user_stmt->bind_param(
        "i",
        $user_id
    );

    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    $user_data = $user_result->fetch_assoc();

    if ($user_data) {

        $booking_name =
            !empty($user_data['name'])
                ? $user_data['name']
                : 'TicketFlix User';

        $booking_email =
            !empty($user_data['email'])
                ? trim($user_data['email'])
                : '';

    }

    $user_stmt->close();

}


/*
|------------------------------------------------------------------
| FALLBACK SESSION EMAIL
|------------------------------------------------------------------
*/

if (
    $booking_email === '' &&
    isset($_SESSION['email']) &&
    filter_var(
        $_SESSION['email'],
        FILTER_VALIDATE_EMAIL
    )
) {

    $booking_email =
        trim($_SESSION['email']);

}


/*
|--------------------------------------------------------------------------
| SHOWTIME DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        st.id,
        st.show_date,
        st.show_time,
        st.price,

        m.name AS movie_name,

        s.screen_name,

        t.name AS theater_name,
        t.city,
        t.state

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
        "Database error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "i",
    $showtime_id
);

$stmt->execute();

$result = $stmt->get_result();

$showtime = $result->fetch_assoc();

$stmt->close();


if (!$showtime) {

    die("Showtime not found.");

}


/*
|--------------------------------------------------------------------------
| SELECTED SEATS
|--------------------------------------------------------------------------
*/

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
        seat_row,
        seat_number,
        seat_type

    FROM seats

    WHERE id IN ($placeholders)

    ORDER BY
        seat_row ASC,
        seat_number ASC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Seat query error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    $types,
    ...$seat_ids
);

$stmt->execute();

$seat_result = $stmt->get_result();


$selected_seats = [];


while (
    $seat = $seat_result->fetch_assoc()
) {

    $selected_seats[] = $seat;

}


$stmt->close();


if (empty($selected_seats)) {

    die("Selected seats not found.");

}


/*
|--------------------------------------------------------------------------
| TOTAL AMOUNT
|--------------------------------------------------------------------------
*/

$ticket_price =
    (float) $showtime['price'];


$seat_count =
    count($selected_seats);


$booking_fee = 0;


$total_amount =
    ($ticket_price * $seat_count)
    + $booking_fee;


/*
|--------------------------------------------------------------------------
| PAYMENT STATE
|--------------------------------------------------------------------------
*/

$payment_success = false;

$payment_method = '';


/*
|--------------------------------------------------------------------------
| DUMMY PAYMENT PROCESS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['dummy_pay']) &&
    $_POST['dummy_pay'] === 'yes'
) {

    $payment_method =
        isset($_POST['payment_method'])
            ? trim(
                $_POST['payment_method']
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD VALIDATION
    |--------------------------------------------------------------------------
    */
if (
    in_array(
        $payment_method,
        [
            'card',
            'upi',
            'netbanking'
        ],
        true
    )
) {

    /*
    |------------------------------------------------------------------
    | PAYMENT SUCCESS
    |------------------------------------------------------------------
    */

    $payment_success = true;


    /*
    |------------------------------------------------------------------
    | GENERATE PAYMENT ID
    |------------------------------------------------------------------
    */

    $_SESSION['dummy_payment_id'] =
        'TFX-' .
        date('YmdHis') .
        '-' .
        strtoupper(
            substr(
                md5(
                    uniqid(
                        (string)$showtime_id,
                        true
                    )
                ),
                0,
                6
            )
        );


    /*
    |------------------------------------------------------------------
    | PAYMENT ID
    |------------------------------------------------------------------
    */

    $payment_id =
        $_SESSION['dummy_payment_id'];


    /*
    |------------------------------------------------------------------
    | SEND BOOKING CONFIRMATION EMAIL
    |------------------------------------------------------------------
    */

    if (
        $booking_email !== '' &&
        filter_var(
            $booking_email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $mail = new PHPMailer(true);

        try {

            /*
            |----------------------------------------------------------
            | SMTP SETTINGS
            |----------------------------------------------------------
            */

            $mail->isSMTP();

            $mail->Host =
                'smtp.gmail.com';

            $mail->SMTPAuth =
                true;

            $mail->Username =
                'ticketflix40@gmail.com';

            /*
            | IMPORTANT:
            | Put your Gmail APP PASSWORD here.
            | NOT your normal Gmail password.
            */

            $mail->Password =
                'zkso vpca dspg krtl';

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port =
                587;


            /*
            |----------------------------------------------------------
            | SENDER
            |----------------------------------------------------------
            */

            $mail->setFrom(
                'ticketflix40@gmail.com',
                'TicketFlix'
            );


            /*
            |----------------------------------------------------------
            | REGISTERED USER
            |----------------------------------------------------------
            */

            $mail->addAddress(
                $booking_email,
                $booking_name
            );


            /*
            |----------------------------------------------------------
            | EMAIL FORMAT
            |----------------------------------------------------------
            */

            $mail->isHTML(true);


            /*
            |----------------------------------------------------------
            | SUBJECT
            |----------------------------------------------------------
            */

            $mail->Subject =
                'Booking Confirmation - ' .
                $showtime['movie_name'];


            /*
            |----------------------------------------------------------
            | EMAIL BODY
            |----------------------------------------------------------
            */

            $mail->Body = '

            <!DOCTYPE html>

            <html>

            <head>

                <meta charset="UTF-8">

                <style>

                    body {
                        margin: 0;
                        padding: 0;
                        background: #f4f1f7;
                        font-family: Arial, Helvetica, sans-serif;
                    }

                    .container {
                        max-width: 600px;
                        margin: 30px auto;
                        background: #ffffff;
                        border-radius: 15px;
                        overflow: hidden;
                        box-shadow: 0 5px 20px rgba(0,0,0,0.10);
                    }

                    .header {
                        background: #321452;
                        padding: 25px;
                        text-align: center;
                        color: white;
                    }

                    .logo {
                        font-size: 30px;
                        font-weight: bold;
                    }

                    .logo span {
                        color: #f4c430;
                    }

                    .header p {
                        margin: 8px 0 0;
                        color: #ddd;
                    }

                    .content {
                        padding: 30px;
                        color: #333333;
                    }

                    .success {
                        color: #5b2c83;
                        font-size: 21px;
                        font-weight: bold;
                    }

                    .details {
                        background: #faf7fc;
                        border-left: 5px solid #f4c430;
                        padding: 20px;
                        margin-top: 20px;
                        border-radius: 8px;
                    }

                    .details p {
                        margin: 10px 0;
                    }

                    .label {
                        font-weight: bold;
                        color: #5b2c83;
                    }

                    .payment-id {
                        color: #d39e00;
                        font-weight: bold;
                    }

                    .footer {
                        background: #f1edf5;
                        text-align: center;
                        padding: 18px;
                        color: #777777;
                        font-size: 12px;
                    }

                </style>

            </head>


            <body>

                <div class="container">


                    <div class="header">

                        <div class="logo">

                            Ticket<span>Flix</span>

                        </div>

                        <p>
                            Movie Ticket Booking
                        </p>

                    </div>


                    <div class="content">

                        <p>
                            Hi <strong>' .
                            htmlspecialchars(
                                $booking_name
                            ) .
                            '</strong>,
                        </p>


                        <p class="success">

                            🎉 Your booking is confirmed!

                        </p>


                        <p>

                            Your payment was successful
                            and your movie ticket has been
                            booked successfully.

                        </p>


                        <div class="details">


                            <p>

                                <span class="label">
                                    Payment ID:
                                </span>

                                <span class="payment-id">
                                    ' .
                                    htmlspecialchars(
                                        $payment_id
                                    ) .
                                    '
                                </span>

                            </p>


                            <p>

                                <span class="label">
                                    Movie:
                                </span>

                                ' .
                                htmlspecialchars(
                                    $showtime['movie_name']
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Theater:
                                </span>

                                ' .
                                htmlspecialchars(
                                    $showtime['theater_name']
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Location:
                                </span>

                                ' .
                                htmlspecialchars(
                                    $showtime['city']
                                ) .
                                ',
                                ' .
                                htmlspecialchars(
                                    $showtime['state']
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Screen:
                                </span>

                                ' .
                                htmlspecialchars(
                                    $showtime['screen_name']
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Date:
                                </span>

                                ' .
                                date(
                                    "D, d M Y",
                                    strtotime(
                                        $showtime['show_date']
                                    )
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Time:
                                </span>

                                ' .
                                date(
                                    "h:i A",
                                    strtotime(
                                        $showtime['show_time']
                                    )
                                ) .
                                '

                            </p>


                            <p>

                                <span class="label">
                                    Seats:
                                </span>

                                ';


            /*
            |----------------------------------------------------------
            | SEAT LIST
            |----------------------------------------------------------
            */

            $seat_names = [];

            foreach (
                $selected_seats
                as $seat
            ) {

                $seat_names[] =
                    htmlspecialchars(
                        $seat['seat_row']
                    ) .
                    (int)
                    $seat['seat_number'];

            }


            $mail->Body .=
                implode(
                    ', ',
                    $seat_names
                );


            $mail->Body .= '

                            </p>


                            <p>

                                <span class="label">
                                    Total Amount:
                                </span>

                                ₹' .
                                number_format(
                                    $total_amount,
                                    2
                                ) .
                                '

                            </p>


                        </div>


                        <p style="margin-top:25px;">

                            🍿 Enjoy the show!

                        </p>


                        <p>

                            Thank you for booking
                            with TicketFlix.

                        </p>


                        <p>

                            — TicketFlix Team

                        </p>


                    </div>


                    <div class="footer">

                        This is an automated confirmation
                        email from TicketFlix.

                    </div>


                </div>

            </body>

            </html>

            ';


            /*
            |----------------------------------------------------------
            | PLAIN TEXT VERSION
            |----------------------------------------------------------
            */

            $mail->AltBody =
                "Hi $booking_name,\n\n" .
                "Your TicketFlix booking is confirmed!\n\n" .
                "Payment ID: $payment_id\n" .
                "Movie: " .
                $showtime['movie_name'] . "\n" .
                "Theater: " .
                $showtime['theater_name'] . "\n" .
                "Date: " .
                date(
                    "D, d M Y",
                    strtotime(
                        $showtime['show_date']
                    )
                ) . "\n" .
                "Time: " .
                date(
                    "h:i A",
                    strtotime(
                        $showtime['show_time']
                    )
                ) . "\n" .
                "Total: ₹" .
                number_format(
                    $total_amount,
                    2
                ) . "\n\n" .
                "Enjoy the show!\n\n" .
                "TicketFlix Team";


            /*
            |----------------------------------------------------------
            | SEND
            |----------------------------------------------------------
            */

            $mail->send();


        } catch (Exception $e) {

            /*
            |----------------------------------------------------------
            | DON'T STOP THE SUCCESS PAGE IF EMAIL FAILS
            |----------------------------------------------------------
            */

            error_log(
                "TicketFlix Email Error: " .
                $mail->ErrorInfo
            );

        }

    }

}

/*
|--------------------------------------------------------------------------
| PAYMENT ID
|--------------------------------------------------------------------------
*/

$payment_id =
    isset($_SESSION['dummy_payment_id'])
        ? $_SESSION['dummy_payment_id']
        : '';


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

<?= $payment_success
    ? 'Payment Successful | TicketFlix'
    : 'Payment | TicketFlix'
?>

</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    background:

        radial-gradient(
            circle at top left,
            #38145c 0%,
            #180b27 42%,
            #09060f 100%
        );

    color: white;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 78px;

    padding:
        0 6%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(10,7,16,.94);

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.logo {

    font-size: 29px;

    font-weight: 800;

    letter-spacing:
        -.5px;

}


.logo span {

    color:
        #f4c430;

}


.back {

    color:
        #e9e4ee;

    text-decoration:
        none;

    padding:
        10px 19px;

    border:
        1px solid
        rgba(255,255,255,.14);

    border-radius:
        25px;

    background:
        rgba(255,255,255,.04);

    transition:
        .25s;

}


.back:hover {

    background:
        #f4c430;

    color:
        #21102d;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width:
        min(1120px,92%);

    margin:
        42px auto 80px;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    text-align:
        center;

    margin-bottom:
        30px;

}


.page-heading h1 {

    font-size:
        36px;

    margin-bottom:
        8px;

}


.page-heading h1 span {

    color:
        #f4c430;

}


.page-heading p {

    color:
        #aaa2b1;

    font-size:
        14px;

}


/* =========================================================
   PAYMENT GRID
========================================================= */

.payment-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1.15fr;

    gap:
        26px;

    align-items:
        start;

}


/* =========================================================
   LEFT SUMMARY
========================================================= */

.summary-card {

    background:

        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid
        #583378;

    border-radius:
        22px;

    padding:
        30px;

    box-shadow:
        0 25px 60px
        rgba(0,0,0,.28);

}


.summary-heading {

    font-size:
        23px;

    margin-bottom:
        24px;

}


.movie-name {

    color:
        #f4c430;

    font-size:
        25px;

    font-weight:
        800;

    line-height:
        1.2;

    margin-bottom:
        22px;

}


.info-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        15px;

    padding:
        13px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

    color:
        #c7bfce;

    font-size:
        14px;

}


.info-row strong {

    color:
        white;

    text-align:
        right;

    font-size:
        14px;

}


/* =========================================================
   SEATS
========================================================= */

.seat-heading {

    margin-top:
        24px;

    margin-bottom:
        12px;

    color:
        #a9a0b2;

    font-size:
        12px;

    font-weight:
        700;

    letter-spacing:
        .5px;

}


.seats {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        8px;

}


.seat {

    padding:
        8px 13px;

    border-radius:
        8px;

    background:
        #f4c430;

    color:
        #21102f;

    font-size:
        13px;

    font-weight:
        800;

}


/* =========================================================
   SUMMARY TOTAL
========================================================= */

.summary-divider {

    height:
        1px;

    background:
        rgba(255,255,255,.10);

    margin:
        23px 0;

}


.price-row {

    display:
        flex;

    justify-content:
        space-between;

    color:
        #c9c1d0;

    font-size:
        14px;

    margin-bottom:
        12px;

}


.price-row strong {

    color:
        white;

}


.total-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-top:
        18px;

}


.total-label {

    font-size:
        18px;

    font-weight:
        700;

}


.total-price {

    color:
        #f4c430;

    font-size:
        28px;

    font-weight:
        800;

}


/* =========================================================
   RIGHT PAYMENT CARD
========================================================= */

.payment-card {

    background:
        #ffffff;

    color:
        #29232f;

    border-radius:
        22px;

    padding:
        32px;

    box-shadow:
        0 25px 65px
        rgba(0,0,0,.38);

}


.payment-card h2 {

    font-size:
        27px;

    margin-bottom:
        7px;

}


.payment-subtitle {

    color:
        #817987;

    font-size:
        14px;

    margin-bottom:
        24px;

}


/* =========================================================
   BOOKING EMAIL
========================================================= */

.email-box {

    background:
        #f7f4fb;

    border:
        1px solid
        #e2ddea;

    border-radius:
        10px;

    padding:
        13px 15px;

    margin-bottom:
        24px;

}


.email-label {

    display:
        block;

    color:
        #82798d;

    font-size:
        11px;

    font-weight:
        800;

    letter-spacing:
        .5px;

    margin-bottom:
        5px;

}


.email-value {

    color:
        #28212f;

    font-size:
        14px;

    font-weight:
        700;

}


/* =========================================================
   PAYMENT METHOD TITLE
========================================================= */

.method-title {

    font-size:
        16px;

    font-weight:
        800;

    margin-bottom:
        12px;

}


/* =========================================================
   PAYMENT METHODS
========================================================= */

.payment-methods {

    border:
        1px solid
        #ded9e2;

    border-radius:
        12px;

    overflow:
        hidden;

}


.payment-option {

    position:
        relative;

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    min-height:
        70px;

    padding:
        0 16px;

    cursor:
        pointer;

    border-bottom:
        1px solid
        #ebe7ee;

    transition:
        .2s;

}


.payment-option:last-child {

    border-bottom:
        none;

}


.payment-option:hover {

    background:
        #faf8fd;

}


.payment-option:has(input:checked) {

    background:
        #faf7ff;

}


.payment-option input {

    width:
        18px;

    height:
        18px;

    accent-color:
        #7c3aed;

}


.payment-icon {

    width:
        36px;

    height:
        36px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        9px;

    background:
        #f0e9ff;

    font-size:
        18px;

}


.method-content {

    display:
        flex;

    flex-direction:
        column;

    gap:
        3px;

}


.method-name {

    font-size:
        14px;

    font-weight:
        800;

    color:
        #29232f;

}


.method-small {

    color:
        #96909d;

    font-size:
        11px;

}


/* =========================================================
   PAYMENT DETAILS
========================================================= */

.details-area {

    margin-top:
        20px;

}


.form-label {

    display:
        block;

    font-size:
        12px;

    font-weight:
        800;

    color:
        #443b4c;

    margin-bottom:
        7px;

}


.form-input {

    width:
        100%;

    height:
        47px;

    padding:
        0 13px;

    border:
        1px solid
        #ddd8e1;

    border-radius:
        9px;

    outline:
        none;

    background:
        white;

    color:
        #29232f;

    font-size:
        14px;

    transition:
        .2s;

}


.form-input:focus {

    border-color:
        #7c3aed;

    box-shadow:
        0 0 0 3px
        rgba(124,58,237,.10);

}


.form-row {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        12px;

    margin-top:
        14px;

}


.form-group {

    margin-top:
        14px;

}


/* =========================================================
   CARD DETAILS AREA
========================================================= */

.card-fields {

    display:
        none;

}


body.card-selected .card-fields {

    display:
        block;

}


/* =========================================================
   UPI DETAILS
========================================================= */

.upi-fields {

    display:
        none;

}


body.upi-selected .upi-fields {

    display:
        block;

}


/* =========================================================
   NET BANKING
========================================================= */

.bank-fields {

    display:
        none;

}


body.bank-selected .bank-fields {

    display:
        block;

}


.select-input {

    width:
        100%;

    height:
        47px;

    padding:
        0 13px;

    border:
        1px solid
        #ddd8e1;

    border-radius:
        9px;

    background:
        white;

    color:
        #29232f;

    outline:
        none;

    font-size:
        14px;

}


/* =========================================================
   PAYABLE AMOUNT
========================================================= */

.payment-total {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-top:
        23px;

    padding-top:
        20px;

    border-top:
        1px solid
        #e8e3ea;

}


.payment-total span:first-child {

    font-size:
        15px;

    font-weight:
        700;

    color:
        #57505e;

}


.payment-total strong {

    color:
        #6b21a8;

    font-size:
        25px;

}


/* =========================================================
   PAY BUTTON
========================================================= */

.pay-btn {

    width:
        100%;

    height:
        54px;

    margin-top:
        22px;

    border:
        none;

    border-radius:
        10px;

    background:

        linear-gradient(
            135deg,
            #f4c430,
            #e7ad10
        );

    color:
        #21102f;

    font-size:
        16px;

    font-weight:
        800;

    cursor:
        pointer;

    transition:
        .25s;

}


.pay-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(244,196,48,.30);

}


.pay-note {

    text-align:
        center;

    color:
        #96909a;

    font-size:
        11px;

    margin-top:
        12px;

}


/* =========================================================
   SUCCESS
========================================================= */

.success-wrapper {

    max-width:
        700px;

    margin:
        55px auto;

}


.success-card {

    background:

        linear-gradient(
            145deg,
            #321452,
            #1d0d31
        );

    border:
        1px solid
        #654085;

    border-radius:
        24px;

    padding:
        50px 35px;

    text-align:
        center;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.35);

}


.success-icon {

    width:
        82px;

    height:
        82px;

    margin:
        0 auto 22px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        50%;

    background:
        #f4c430;

    color:
        #24102e;

    font-size:
        42px;

    font-weight:
        800;

}


.success-card h1 {

    font-size:
        31px;

    margin-bottom:
        10px;

}


.success-card h1 span {

    color:
        #f4c430;

}


.success-card p {

    color:
        #c8bfd2;

    line-height:
        1.6;

    margin-bottom:
        25px;

}


.payment-id {

    padding:
        16px;

    border-radius:
        12px;

    background:
        rgba(255,255,255,.06);

    border:
        1px solid
        rgba(255,255,255,.10);

    margin-bottom:
        24px;

}


.payment-id small {

    display:
        block;

    color:
        #9e94aa;

    margin-bottom:
        6px;

}


.payment-id strong {

    color:
        #f4c430;

    font-size:
        18px;

    letter-spacing:
        1px;

}


.done-btn {

    display:
        inline-block;

    padding:
        13px 28px;

    border-radius:
        30px;

    background:
        #f4c430;

    color:
        #24102e;

    text-decoration:
        none;

    font-weight:
        800;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .payment-grid {

        grid-template-columns:
            1fr;

    }

}


@media(max-width:550px) {

    .header {

        padding:
            0 4%;

    }

    .logo {

        font-size:
            23px;

    }

    .container {

        width:
            94%;

        margin-top:
            25px;

    }

    .page-heading h1 {

        font-size:
            29px;

    }

    .summary-card,
    .payment-card {

        padding:
            22px;

    }

    .form-row {

        grid-template-columns:
            1fr;

    }

    .payment-card h2 {

        font-size:
            24px;

    }

}


/* =========================================================
   WHEN NO EMAIL IN SESSION
========================================================= */

.email-missing {

    color:
        #b42323;

    font-size:
        13px;

    font-weight:
        600;

}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">

    <div class="logo">

        Ticket<span>Flix</span>

    </div>


    <?php if (!$payment_success): ?>

        <a
            href="javascript:history.back()"
            class="back"
        >

            ← Back

        </a>

    <?php endif; ?>

</header>



<main class="container">


<?php if ($payment_success): ?>


<!-- =========================================================
     SUCCESS PAGE
========================================================= -->

<div class="success-wrapper">

    <div class="success-card">


        <div class="success-icon">

            ✓

        </div>


        <h1>

            Payment <span>Successful!</span>

        </h1>


        <p>

            Your TicketFlix booking has been confirmed.
            Your seats have been successfully reserved.

        </p>


        <div class="payment-id">

            <small>

                Payment ID

            </small>

            <strong>

                <?= htmlspecialchars(
                    $payment_id
                ); ?>

            </strong>

        </div>


        <a
            href="my_bookings.php"
            class="done-btn"
        >

            🎟️ View My Bookings

        </a>


    </div>

</div>


<?php else: ?>


<!-- =========================================================
     PAGE HEADING
========================================================= -->

<div class="page-heading">

    <h1>

        Complete Your
        <span>Payment</span>

    </h1>


    <p>

        Secure checkout for your TicketFlix booking

    </p>

</div>



<!-- =========================================================
     PAYMENT GRID
========================================================= -->

<div class="payment-grid">


<!-- =========================================================
     LEFT BOOKING SUMMARY
========================================================= -->

<section class="summary-card">


    <h2 class="summary-heading">

        Booking Summary

    </h2>


    <div class="movie-name">

        <?= htmlspecialchars(
            $showtime['movie_name']
        ); ?>

    </div>


    <div class="info-row">

        <span>

            Theater

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['theater_name']
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Location

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['city']
            ); ?>,

            <?= htmlspecialchars(
                $showtime['state']
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Screen

        </span>

        <strong>

            <?= htmlspecialchars(
                $showtime['screen_name']
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Date

        </span>

        <strong>

            <?= date(
                "D, d M Y",
                strtotime(
                    $showtime['show_date']
                )
            ); ?>

        </strong>

    </div>


    <div class="info-row">

        <span>

            Time

        </span>

        <strong>

            <?= date(
                "h:i A",
                strtotime(
                    $showtime['show_time']
                )
            ); ?>

        </strong>

    </div>



    <div class="seat-heading">

        SELECTED SEATS

    </div>


    <div class="seats">

        <?php foreach (
            $selected_seats
            as $seat
        ): ?>

            <div class="seat">

                <?= htmlspecialchars(
                    $seat['seat_row']
                ); ?><?= (int)
                    $seat['seat_number']; ?>

            </div>

        <?php endforeach; ?>

    </div>



    <div class="summary-divider"></div>


    <div class="price-row">

        <span>

            Tickets

        </span>

        <strong>

            <?= $seat_count; ?>

            ×

            ₹<?= number_format(
                $ticket_price,
                2
            ); ?>

        </strong>

    </div>


    <div class="price-row">

        <span>

            Convenience Fee

        </span>

        <strong>

            ₹<?= number_format(
                $booking_fee,
                2
            ); ?>

        </strong>

    </div>


    <div class="total-row">

        <span class="total-label">

            Total

        </span>

        <span class="total-price">

            ₹<?= number_format(
                $total_amount,
                2
            ); ?>

        </span>

    </div>


</section>



<!-- =========================================================
     RIGHT PAYMENT
========================================================= -->

<section class="payment-card">


    <h2>

        Complete Payment

    </h2>


    <p class="payment-subtitle">

        Choose your preferred payment method

    </p>



    <!-- =====================================================
         SESSION EMAIL
    ====================================================== -->

    <div class="email-box">

        <span class="email-label">

            BOOKING EMAIL

        </span>


        <?php if ($booking_email !== ''): ?>

            <div class="email-value">

                <?= htmlspecialchars(
                    $booking_email
                ); ?>

            </div>

        <?php else: ?>

            <div class="email-missing">

                Email address is not available
                in your session.

            </div>

        <?php endif; ?>

    </div>



    <!-- =====================================================
         PAYMENT METHOD
    ====================================================== -->

    <div class="method-title">

        Payment method

    </div>


    <form
        method="POST"
        id="paymentForm"
    >


        <!-- SHOWTIME -->

        <input
            type="hidden"
            name="showtime_id"
            value="<?= $showtime_id; ?>"
        >


        <!-- SEATS -->

        <?php foreach (
            $seat_ids as
            $seat_id
        ): ?>

            <input
                type="hidden"
                name="seat_ids[]"
                value="<?= (int)
                    $seat_id; ?>"
            >

        <?php endforeach; ?>


        <!-- =================================================
             PAYMENT OPTIONS
        ================================================== -->

        <div class="payment-methods">


            <!-- CARD -->

            <label
                class="payment-option"
            >

                <input
                    type="radio"
                    name="payment_method"
                    value="card"
                    required
                >


                <div class="payment-icon">

                    💳

                </div>


                <div class="method-content">

                    <span class="method-name">

                        Card

                    </span>

                    <span class="method-small">

                        Visa • Mastercard • RuPay

                    </span>

                </div>

            </label>



            <!-- UPI -->

            <label
                class="payment-option"
            >

                <input
                    type="radio"
                    name="payment_method"
                    value="upi"
                >


                <div class="payment-icon">

                    📱

                </div>


                <div class="method-content">

                    <span class="method-name">

                        UPI

                    </span>

                    <span class="method-small">

                        Google Pay • PhonePe • Paytm

                    </span>

                </div>

            </label>



            <!-- NET BANKING -->

            <label
                class="payment-option"
            >

                <input
                    type="radio"
                    name="payment_method"
                    value="netbanking"
                >


                <div class="payment-icon">

                    🏦

                </div>


                <div class="method-content">

                    <span class="method-name">

                        Net Banking

                    </span>

                    <span class="method-small">

                        All major banks supported

                    </span>

                </div>

            </label>


        </div>



        <!-- =================================================
             CARD FIELDS
        ================================================== -->

        <div class="card-fields">


            <div class="form-group">

                <label class="form-label">

                    Cardholder Name

                </label>


                <input
                    type="text"
                    class="form-input"
                    placeholder="Enter cardholder name"
                    autocomplete="off"
                >

            </div>


            <div class="form-group">

                <label class="form-label">

                    Card Number

                </label>


                <input
                    type="text"
                    class="form-input"
                    placeholder="XXXX XXXX XXXX XXXX"
                    maxlength="19"
                    inputmode="numeric"
                    autocomplete="off"
                >

            </div>


            <div class="form-row">


                <div>

                    <label class="form-label">

                        Expiry Date

                    </label>


                    <input
                        type="text"
                        class="form-input"
                        placeholder="MM / YY"
                        maxlength="7"
                        autocomplete="off"
                    >

                </div>


                <div>

                    <label class="form-label">

                        CVV

                    </label>


                    <input
                        type="password"
                        class="form-input"
                        placeholder="•••"
                        maxlength="3"
                        autocomplete="off"
                    >

                </div>


            </div>

        </div>



        <!-- =================================================
             UPI
        ================================================== -->

        <div class="upi-fields">


            <div class="form-group">

                <label class="form-label">

                    UPI ID

                </label>


                <input
                    type="text"
                    class="form-input"
                    placeholder="example@upi"
                    autocomplete="off"
                >

            </div>

        </div>



        <!-- =================================================
             NET BANKING
        ================================================== -->

        <div class="bank-fields">


            <div class="form-group">

                <label class="form-label">

                    Select Bank

                </label>


                <select
                    class="select-input"
                >

                    <option value="">

                        Select your bank

                    </option>

                    <option>

                        State Bank of India

                    </option>

                    <option>

                        HDFC Bank

                    </option>

                    <option>

                        ICICI Bank

                    </option>

                    <option>

                        Axis Bank

                    </option>

                    <option>

                        Punjab National Bank

                    </option>

                </select>

            </div>

        </div>



        <!-- =================================================
             TOTAL
        ================================================== -->

        <div class="payment-total">

            <span>

                Payable Amount

            </span>


            <strong>

                ₹<?= number_format(
                    $total_amount,
                    2
                ); ?>

            </strong>

        </div>



        <!-- =================================================
             DUMMY PAYMENT FLAG
        ================================================== -->

        <input
            type="hidden"
            name="dummy_pay"
            value="yes"
        >



        <!-- =================================================
             PAY BUTTON
        ================================================== -->

        <button
            type="submit"
            class="pay-btn"
        >

            🔒 Pay ₹<?= number_format(
                $total_amount,
                2
            ); ?>

        </button>


        <div class="pay-note">

            Secure payment

        </div>


    </form>


</section>


</div>


<?php endif; ?>


</main>



<script>

/*
|--------------------------------------------------------------------------
| PAYMENT METHOD UI
|--------------------------------------------------------------------------
*/

const paymentOptions =
    document.querySelectorAll(
        'input[name="payment_method"]'
    );


function updatePaymentFields() {

    document.body.classList.remove(
        'card-selected',
        'upi-selected',
        'bank-selected'
    );


    const selected =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (!selected) {

        return;

    }


    if (selected.value === 'card') {

        document.body.classList.add(
            'card-selected'
        );

    }


    if (selected.value === 'upi') {

        document.body.classList.add(
            'upi-selected'
        );

    }


    if (
        selected.value === 'netbanking'
    ) {

        document.body.classList.add(
            'bank-selected'
        );

    }

}


paymentOptions.forEach(
    function (option) {

        option.addEventListener(
            'change',
            updatePaymentFields
        );

    }
);


/*
|--------------------------------------------------------------------------
| CARD NUMBER FORMAT
|--------------------------------------------------------------------------
*/

const cardNumber =
    document.querySelector(
        '.card-fields input[placeholder="XXXX XXXX XXXX XXXX"]'
    );


if (cardNumber) {

    cardNumber.addEventListener(
        'input',
        function () {

            let value =
                this.value
                    .replace(/\D/g, '')
                    .substring(0, 16);

            value =
                value.match(/.{1,4}/g)
                    ?.join(' ')
                || '';

            this.value = value;

        }
    );

}


/*
|--------------------------------------------------------------------------
| EXPIRY FORMAT
|--------------------------------------------------------------------------
*/

const expiry =
    document.querySelector(
        '.card-fields input[placeholder="MM / YY"]'
    );


if (expiry) {

    expiry.addEventListener(
        'input',
        function () {

            let value =
                this.value
                    .replace(/\D/g, '')
                    .substring(0, 4);


            if (value.length > 2) {

                value =
                    value.substring(0, 2)
                    + ' / '
                    + value.substring(2);

            }


            this.value = value;

        }
    );

}


/*
|--------------------------------------------------------------------------
| CVV ONLY NUMBERS
|--------------------------------------------------------------------------
*/

const cvv =
    document.querySelector(
        '.card-fields input[placeholder="•••"]'
    );


if (cvv) {

    cvv.addEventListener(
        'input',
        function () {

            this.value =
                this.value
                    .replace(/\D/g, '')
                    .substring(0, 3);

        }
    );

}

</script>


</body>

</html>