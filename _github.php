<?php

header("Content-Type: application/json");

$username = "iXeriox";

$cacheFile = __DIR__ . "/data/githubCache.json";
$cacheTime = 300; // 5 minutes

/*
|--------------------------------------------------------------------------
| Serve cache
|--------------------------------------------------------------------------
*/

if (
    file_exists($cacheFile) &&
    (time() - filemtime($cacheFile)) < $cacheTime
) {
    echo file_get_contents($cacheFile);
    exit;
}

/*
|--------------------------------------------------------------------------
| GitHub Request Helper
|--------------------------------------------------------------------------
*/

function githubRequest($url)
{
    $context = stream_context_create([
        "http" => [
            "header" => implode("\r\n", [
                "User-Agent: iXeriox.dev",
                "Accept: application/vnd.github+json"
            ])
        ]
    ]);

    $json = @file_get_contents($url, false, $context);

    if ($json === false) {
        return null;
    }

    return json_decode($json, true);
}

/*
|--------------------------------------------------------------------------
| Fetch repositories
|--------------------------------------------------------------------------
*/

$repos = githubRequest(
    "https://api.github.com/users/$username/repos?sort=pushed&per_page=100"
);

if (!is_array($repos)) {

    http_response_code(500);

    echo json_encode([
        "error" => "Unable to contact GitHub."
    ]);

    exit;

}

$totalStars = 0;
$totalForks = 0;

$latestPush = null;
$latestRepo = null;

$repositories = [];

/*
|--------------------------------------------------------------------------
| Process repositories
|--------------------------------------------------------------------------
*/

foreach ($repos as $repo) {

    // Ignore forks & archived repositories
    if ($repo["fork"] || $repo["archived"]) {
        continue;
    }

    $totalStars += $repo["stargazers_count"];
    $totalForks += $repo["forks_count"];

    if (
        !$latestPush ||
        strtotime($repo["pushed_at"]) > strtotime($latestPush)
    ) {
        $latestPush = $repo["pushed_at"];
        $latestRepo = $repo["name"];
    }

    /*
    |--------------------------------------------------------------------------
    | Latest commit
    |--------------------------------------------------------------------------
    */

    $commitMessage = null;
    $commitAuthor = null;

    $commit = githubRequest(
        "https://api.github.com/repos/$username/{$repo["name"]}/commits?per_page=1"
    );

    if (is_array($commit) && isset($commit[0]["commit"])) {

        $commitMessage =
            $commit[0]["commit"]["message"];

        $commitAuthor =
            $commit[0]["commit"]["author"]["name"];

    }

    $repositories[] = [

        "name" => $repo["name"],

        "description" => $repo["description"],

        "url" => $repo["html_url"],

        "homepage" => $repo["homepage"],

        "language" => $repo["language"],

        "stars" => $repo["stargazers_count"],

        "forks" => $repo["forks_count"],

        "watchers" => $repo["watchers_count"],

        "issues" => $repo["open_issues_count"],

        "visibility" => $repo["visibility"],

        "license" => $repo["license"]["name"] ?? null,

        "defaultBranch" => $repo["default_branch"],

        "size" => $repo["size"],

        "topics" => $repo["topics"] ?? [],

        "created" => $repo["created_at"],

        "updated" => $repo["updated_at"],

        "pushed" => $repo["pushed_at"],

        "lastCommit" => $commitMessage,

        "lastCommitAuthor" => $commitAuthor

    ];

}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

$response = [

    "repoCount" => count($repositories),

    "stars" => $totalStars,

    "forks" => $totalForks,

    "latestRepo" => $latestRepo,

    "lastPush" => $latestPush,

    "currentlyWorkingOn" => $repositories[0] ?? null,

    "repositories" => $repositories

];

/*
|--------------------------------------------------------------------------
| Cache
|--------------------------------------------------------------------------
*/

file_put_contents(
    $cacheFile,
    json_encode($response, JSON_PRETTY_PRINT),
    LOCK_EX
);

echo json_encode($response);