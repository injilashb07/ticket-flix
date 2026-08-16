<?php
include 'config.php';
include 'header.php';

$sql = "SELECT * FROM theaters ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TicketFlix - Theaters</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #120b1f;
            color: white;
        }

        .theater-page {
            padding: 40px 6%;
            min-height: 80vh;
        }

        .page-title {
            text-align: center;
            margin-bottom: 10px;
            font-size: 38px;
            color: #f5c542;
        }

        .page-subtitle {
            text-align: center;
            color: #d7cce5;
            margin-bottom: 40px;
        }

        .search-box {
            max-width: 600px;
            margin: 0 auto 40px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 16px 22px;
            border-radius: 30px;
            border: 2px solid #8b5cf6;
            background: #211334;
            color: white;
            font-size: 16px;
            outline: none;
        }

        .search-box input::placeholder {
            color: #b8a9c9;
        }

        .theater-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .theater-card {
            background: linear-gradient(145deg, #241337, #170d27);
            border: 1px solid rgba(245, 197, 66, 0.35);
            border-radius: 20px;
            overflow: hidden;
            transition: 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .theater-card:hover {
            transform: translateY(-8px);
            border-color: #f5c542;
            box-shadow: 0 15px 35px rgba(139,92,246,0.3);
        }

        .theater-image {
            height: 170px;
            background:
                linear-gradient(rgba(35,13,55,0.25), rgba(35,13,55,0.7)),
                url('images/theater.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            padding: 20px;
        }

        .theater-icon {
            font-size: 42px;
        }

        .theater-content {
            padding: 22px;
        }

        .theater-name {
            color: #f5c542;
            font-size: 22px;
            margin: 0 0 15px;
        }

        .location {
            color: #eee;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 20px;
        }

        .location span {
            color: #c9b7dc;
        }

        .view-btn {
            display: inline-block;
            text-decoration: none;
            background: linear-gradient(90deg, #7c3aed, #9f67ff);
            color: white;
            padding: 11px 22px;
            border-radius: 25px;
            font-weight: bold;
            transition: 0.3s;
        }

        .view-btn:hover {
            background: #f5c542;
            color: #160d25;
        }

        .no-result {
            text-align: center;
            color: #c9b7dc;
            padding: 40px;
            display: none;
        }

        @media(max-width:600px) {
            .page-title {
                font-size: 30px;
            }

            .theater-page {
                padding: 30px 5%;
            }
        }
    </style>
</head>

<body>

<div class="theater-page">

    <h1 class="page-title">🎬 Our Theaters</h1>

    <p class="page-subtitle">
        Experience movies like never before at TicketFlix cinemas
    </p>

    <div class="search-box">
        <input
            type="text"
            id="theaterSearch"
            placeholder="🔍 Search theater or city..."
            onkeyup="searchTheaters()"
        >
    </div>

    <div class="theater-grid" id="theaterGrid">

        <?php if(mysqli_num_rows($result) > 0): ?>

            <?php while($theater = mysqli_fetch_assoc($result)): ?>

                <div class="theater-card"
                     data-search="<?php
                        echo strtolower(
                            $theater['name'] . ' ' .
                            $theater['city'] . ' ' .
                            $theater['state']
                        );
                     ?>">

                    <div class="theater-image">
                        <div class="theater-icon">🎥</div>
                    </div>

                    <div class="theater-content">

                        <h2 class="theater-name">
                            <?php echo htmlspecialchars($theater['name']); ?>
                        </h2>

                        <div class="location">
                            📍 <?php echo htmlspecialchars($theater['address']); ?><br>

                            <span>
                                <?php echo htmlspecialchars($theater['city']); ?>,
                                <?php echo htmlspecialchars($theater['state']); ?>
                                - <?php echo htmlspecialchars($theater['zip_code']); ?>
                            </span>
                        </div>

                        <a href="showtimes.php?theater_id=<?php echo $theater['id']; ?>"
                           class="view-btn">
                            View Showtimes →
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="no-result" style="display:block;">
                No theaters available.
            </div>

        <?php endif; ?>

    </div>

    <div id="noResult" class="no-result">
        😔 No theater found.
    </div>

</div>

<script>

function searchTheaters() {

    let input = document
        .getElementById("theaterSearch")
        .value
        .toLowerCase();

    let cards = document.querySelectorAll(".theater-card");

    let found = false;

    cards.forEach(function(card) {

        let text = card
            .getAttribute("data-search")
            .toLowerCase();

        if(text.includes(input)) {
            card.style.display = "block";
            found = true;
        } else {
            card.style.display = "none";
        }

    });

    document.getElementById("noResult").style.display =
        found ? "none" : "block";
}

</script>

<?php include 'footer.php'; ?>

</body>
</html>