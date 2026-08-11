<?php

// Max size (bytes) a single pen's content is allowed to be.
// Prevents someone from writing arbitrarily large files to disk.
const MAX_PEN_SIZE = 500 * 1024; // 500 KB




function getPensDirectory()
{

    $dir = dirname(__DIR__) . "/data/pens";


    if(!is_dir($dir))
    {

        mkdir(
            $dir,
            0755,
            true
        );

    }


    return $dir;

}




function generatePenID($length = 8)
{

    $chars =
    "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";


    $id = "";


    for($i = 0; $i < $length; $i++)
    {

        $id .=
        $chars[
            random_int(
                0,
                strlen($chars)-1
            )
        ];

    }


    return $id;

}




function pathForPenID($id)
{

    return
    getPensDirectory()
    .
    "/"
    .
    basename($id)
    .
    ".json";

}




function savePen($content, $language = "auto")
{

    if(strlen($content) > MAX_PEN_SIZE)
    {

        die(
            "Pen content too large (max "
            .
            number_format(MAX_PEN_SIZE / 1024)
            .
            " KB)"
        );

    }


    $dir = getPensDirectory();


    $id = generatePenID();


    $file = $dir . "/" . $id . ".json";



    $data = [

        "id" => $id,

        "content" => $content,

        "language" => $language,

        "created" => date("c"),

        "updated" => date("c"),

        "views" => 0

    ];



    $result = file_put_contents(

        $file,

        json_encode(
            $data,
            JSON_PRETTY_PRINT
        ),

        LOCK_EX

    );



    if($result === false)
    {

        die(
            "Failed writing: " . $file
        );

    }



    return $id;

}




function loadPen($id)
{

    $file = pathForPenID($id);


    if(!file_exists($file))
    {

        return null;

    }




    $data = json_decode(

        file_get_contents($file),

        true

    );



    if(!is_array($data))
    {

        return null;

    }



    return $data;

}




// Load every pen, keyed by ID, e.g. $pens["abc123"] = [...]
function loadPens()
{

    $dir = getPensDirectory();


    $pens = [];



    foreach(
        glob($dir."/*.json")
        as $file
    )
    {

        $data = json_decode(

            file_get_contents($file),

            true

        );


        if(is_array($data) && isset($data["id"]))
        {

            $pens[$data["id"]] = $data;

        }

    }



    return $pens;

}




// Flat list of every pen (no keys), same data as loadPens() but as a plain array.
function listPens()
{

    return array_values(loadPens());

}




// Overwrite an existing pen's content/language. Returns false if the pen
// doesn't exist, true on success. Uses LOCK_EX around the full
// read-modify-write so a concurrent save can't interleave and corrupt data.
function updatePen($id, $content, $language = "plaintext")
{

    if(strlen($content) > MAX_PEN_SIZE)
    {

        return false;

    }


    $file = pathForPenID($id);


    if(!file_exists($file))
    {

        return false;

    }



    $handle = fopen($file, "c+");


    if($handle === false)
    {

        return false;

    }



    if(!flock($handle, LOCK_EX))
    {

        fclose($handle);

        return false;

    }



    $raw = stream_get_contents($handle);


    $pen = json_decode($raw, true);


    if(!is_array($pen))
    {

        flock($handle, LOCK_UN);

        fclose($handle);

        return false;

    }



    $pen["content"] = $content;

    $pen["language"] = $language;

    $pen["updated"] = date("c");



    ftruncate($handle, 0);

    rewind($handle);

    fwrite(
        $handle,
        json_encode($pen, JSON_PRETTY_PRINT)
    );

    fflush($handle);


    flock($handle, LOCK_UN);

    fclose($handle);



    return true;

}




// Bumps the view counter for a pen by 1. Silently no-ops if the pen
// disappears between loadPen() and this call.
function incrementViews($id)
{

    $file = pathForPenID($id);


    if(!file_exists($file))
    {

        return;

    }



    $handle = fopen($file, "c+");


    if($handle === false)
    {

        return;

    }



    if(!flock($handle, LOCK_EX))
    {

        fclose($handle);

        return;

    }



    $data = json_decode(
        stream_get_contents($handle),
        true
    );


    if(is_array($data))
    {

        $data["views"] = ($data["views"] ?? 0) + 1;


        ftruncate($handle, 0);

        rewind($handle);

        fwrite(
            $handle,
            json_encode($data, JSON_PRETTY_PRINT)
        );

        fflush($handle);

    }



    flock($handle, LOCK_UN);

    fclose($handle);

}




function deletePen($id)
{

    $file = pathForPenID($id);


    if(file_exists($file))
    {

        unlink($file);

        return true;

    }


    return false;

}