<?php
// Save.php

require_once __DIR__ . "/functions.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: /pen/");
    exit;

}



if (empty($_POST["code"])) {

    header("Location: /pen/?no_code_received");
    exit;

}



if (strlen($_POST["code"]) > MAX_PEN_SIZE) {

    header("Location: /pen/?too_large");
    exit;

}



$code = $_POST["code"];



$id = savePen(
    $code
);



header(
    "Location: /pen/".$id
);


exit;