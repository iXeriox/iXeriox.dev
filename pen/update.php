<?php

require_once __DIR__ . "/functions.php";


header(
"Content-Type: application/json"
);



if($_SERVER["REQUEST_METHOD"] !== "POST")
{

    http_response_code(405);

    echo json_encode(
        [
            "success"=>false,
            "error"=>"Method not allowed"
        ]
    );

    exit;

}




$data =
json_decode(
file_get_contents("php://input"),
true
);



if(
empty($data["id"])
||
!isset($data["content"])
)
{

echo json_encode(
[
"success"=>false,
"error"=>"Invalid data"
]
);

exit;

}




if(strlen($data["content"]) > MAX_PEN_SIZE)
{

echo json_encode(
[
"success"=>false,
"error"=>
"Content too large (max "
.
number_format(MAX_PEN_SIZE / 1024)
.
" KB)"
]
);

exit;

}




$updated = updatePen(

    $data["id"],

    $data["content"],

    $data["language"] ?? "plaintext"

);




if(!$updated)
{

echo json_encode(
[
"success"=>false,
"error"=>"Pen does not exist"
]
);

exit;

}




echo json_encode(
[
"success"=>true
]
);