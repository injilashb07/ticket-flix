```php
<?php

/* =========================================================
   START SESSION
========================================================= */

session_start();


/* =========================================================
   REMOVE THEATER SESSION DATA
========================================================= */

unset($_SESSION['theater_user_id']);
unset($_SESSION['theater_id']);
unset($_SESSION['theater_user_name']);


/* =========================================================
   DESTROY SESSION
========================================================= */

$_SESSION = array();

session_destroy();


/* =========================================================
   PREVENT BROWSER CACHE
========================================================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


/* =========================================================
   REDIRECT TO THEATER LOGIN
========================================================= */

header("Location: login.php");
exit();

?>
```
