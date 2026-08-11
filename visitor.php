<?php

function trackVisitor()
{
    $file = __DIR__ . "/data/siteInfo.json";

    /*
        Create database if required
    */

    if (!file_exists($file)) {

        file_put_contents(
            $file,
            json_encode([
                "visitors" => [
                    "totalUnique" => 0,
                    "today" => [
                        "date" => date("Y-m-d"),
                        "unique" => 0,
                        "ips" => [],
                        "countries" => []
                    ],
                    "lastVisit" => null
                ]
            ], JSON_PRETTY_PRINT)
        );

    }

    /*
        Open & lock file
    */

    $fp = fopen($file, "c+");

    if (!$fp) {
        return [
            "totalUnique" => 0,
            "today" => [
                "unique" => 0,
                "countries" => []
            ],
            "lastVisit" => null
        ];
    }

    flock($fp, LOCK_EX);

    rewind($fp);

    $contents = stream_get_contents($fp);

    $data = json_decode($contents, true);

    if (!is_array($data)) {
        $data = [];
    }

    /*
        Validate structure
    */

    $data["visitors"] ??= [];

    $data["visitors"]["totalUnique"] ??= 0;

    $data["visitors"]["today"] ??= [
        "date" => date("Y-m-d"),
        "unique" => 0,
        "ips" => [],
        "countries" => []
    ];

    $data["visitors"]["lastVisit"] ??= null;

    $today = date("Y-m-d");

    /*
        New day
    */

    if ($data["visitors"]["today"]["date"] !== $today) {

        $data["visitors"]["today"] = [

            "date" => $today,

            "unique" => 0,

            "ips" => [],

            "countries" => []

        ];

    }

    /*
        Real visitor IP
        Uses Cloudflare when available
    */

    $ip =
        $_SERVER["HTTP_CF_CONNECTING_IP"]
        ?? $_SERVER["REMOTE_ADDR"]
        ?? "unknown";

    $hashedIP = hash("sha256", $ip);

    /*
        Country
    */

    $country =
        $_SERVER["HTTP_CF_IPCOUNTRY"]
        ?? "Unknown";

    /*
        Unique today?
    */

    if (!in_array(
        $hashedIP,
        $data["visitors"]["today"]["ips"],
        true
    )) {

        $data["visitors"]["today"]["ips"][] = $hashedIP;

        $data["visitors"]["today"]["unique"]++;

        $data["visitors"]["totalUnique"]++;

        if (!isset(
            $data["visitors"]["today"]["countries"][$country]
        )) {

            $data["visitors"]["today"]["countries"][$country] = 0;

        }

        $data["visitors"]["today"]["countries"][$country]++;

    }

    $data["visitors"]["lastVisit"] = date("c");

    /*
        Save
    */

    rewind($fp);

    ftruncate($fp, 0);

    fwrite(
        $fp,
        json_encode(
            $data,
            JSON_PRETTY_PRINT
        )
    );

    fflush($fp);

    flock($fp, LOCK_UN);

    fclose($fp);

    /*
        Prepare countries for Vue
    */

    $countries = [];

    foreach (
        $data["visitors"]["today"]["countries"]
        as $name => $count
    ) {

        $countries[] = [

            "name" => $name,

            "count" => $count

        ];

    }

    usort(
        $countries,
        fn($a, $b) => $b["count"] <=> $a["count"]
    );

    return [

        "totalUnique" =>
            $data["visitors"]["totalUnique"],

        "today" => [

            "unique" =>
                $data["visitors"]["today"]["unique"],

            "countries" =>
                $countries

        ],

        "lastVisit" =>
            $data["visitors"]["lastVisit"]

    ];
}