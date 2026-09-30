<?php
require_once __DIR__ . "/visitor.php";

$visitorStats = trackVisitor();

$discordUser = file_get_contents(
    "https://api.infini9.net/getUserInfo/375368296347729921"
);

$discord = json_decode(
    $discordUser,
    true
);


$identity = [

    "name" => $discord["displayName"] ?? "iXeriox",

    "avatar" => $discord["avatar"] ?? "",

    "about" => $discord["meta"]["about"] ?? "",

    "color" => $discord["color"] ?? "#11806a",

    "roles" => $discord["roles"] ?? []

];

$reviewFile = __DIR__ . "/data/reviews.json";
$approvedReviews = [];

if (is_file($reviewFile) && is_readable($reviewFile)) {
    $storedReviews = json_decode((string) file_get_contents($reviewFile), true);

    if (is_array($storedReviews)) {
        $approvedReviews = array_values(array_filter(
            $storedReviews,
            static fn($review): bool =>
                is_array($review) && ($review["status"] ?? "") === "approved"
        ));

        usort(
            $approvedReviews,
            static fn(array $a, array $b): int =>
                strcmp((string) ($b["approvedAt"] ?? ""), (string) ($a["approvedAt"] ?? ""))
        );

        $approvedReviews = array_map(
            static fn(array $review): array => [
                "id" => (string) ($review["id"] ?? ""),
                "name" => (string) ($review["name"] ?? "Anonymous"),
                "rating" => max(1, min(5, (int) ($review["rating"] ?? 5))),
                "review" => (string) ($review["review"] ?? "")
            ],
            $approvedReviews
        );
    }
}
/**
 * iXeriox.dev
 * Personal developer homeground
 *
 * PHP = data
 * Vue 3 = rendering + interaction
 */

$site = [

    "identity" => [
        "name" => "iXeriox",
        "domain" => "iXeriox.dev",
        "tagline" => "Developer. Problem Solver. Gamer. Karter. Father. Building things, breaking things, and occasionally wondering why I built them in the first place.",
        "intro" => "Geek in a box~",
        "raw" => $identity,
        "visitors" => $visitorStats
    ],

    "reviews" => $approvedReviews,


"projects" => [

    [
        "name" => "infini9.net",
        "url" => "https://infini9.net",
        "isLink" => true,
        "status" => "CURRENT",
        "description" =>
        "My current main project. A platform built around web development, backend systems, experimentation and creating things that people can actually use.",
        "tags" => [
            "PHP",
            "Vue 3",
            "JavaScript",
            "APIs",
            "WebSockets"
        ]
    ],

    [
        "name" => "FWA Karting",
        "url" => "https://fwa.infini9.net",
        "isLink" => true,
        "status" => "CURRENT",
        "description" =>
        "My racing team website, built using NodeJS and designed to support multiple platforms including mobile and browser clients.",
        "tags" => [
            "Vue 2/3",
            "JavaScript",
            "WebSockets",
            "NodeJS"
        ]
    ],

    [
        "name" => "Orbit Roleplay",
        "isLink" => false,
        "status" => "CURRENT",
        "description" =>
        "FiveM roleplay development including server maintenance, administration, scripting, integrations and keeping a live multiplayer environment running.",
        "tags" => [
            "FiveM",
            "Lua",
            "Servers",
            "Administration"
        ]
    ]

],


    "archive" => [

        [
            "name" => "RS4SERVER.co.uk",
            "description" =>
            "A programming community built by myself hosting a Runescape Private Server and basic challenges for community and engagement."
        ],

        [
            "name" => "threeC.tv",
            "description" =>
            "A depreciated platform much like Twitch.tv with our own twist!"
        ],

        [
            "name" => "JoinWorldWide",
            "description" =>
            "Another depreciated platform for music and community!"
        ]

    ],


"stack" => [

    // Languages
    "PHP",
    "Java",
    "JavaScript",
    "TypeScript",
    "Python",
    "Lua",
    "HTML5",
    "CSS3",
    "SQL",

    // Frontend
    "Vue 3",
    "Vue 2",
    "React",
    "Nuxt 3",
    "Bootstrap",
    "Vuetify",
    "Vite",

    // Backend & APIs
    "Node.js",
    "Express",
    "REST APIs",
    "WebSockets",
    "JSON APIs",
    "Authentication Systems",
    "Real-time Applications",

    // Databases
    "MySQL",
    "MariaDB",
    "SQLite",
    "Database Design",
    "Data Migration",

    // Servers & Infrastructure
    "Linux",
    "Ubuntu Server",
    "Apache",
    "Nginx",
    "Cloudflare",
    "DNS Management",
    "SSL/TLS",
    "Reverse Proxying",
    "Server Hardening",

    // Development Tools
    "Git",
    "GitHub",
    "VS Code",
    "Linux CLI",
    "Bash",
    "SSH",

    // Game & Community Development
    "FiveM",
    "FiveM Server Management",
    "Lua Scripting",
    "Game Server Administration",
    "Multiplayer Systems",
    "Community Platforms",

    // Motorsport / Data Systems
    "Node.js Automation",
    "Real-time Data Processing",
    "API Integrations",
    "Event Systems",

    // Concepts
    "Object-Oriented Programming",
    "MVC Architecture",
    "Software Architecture",
    "Performance Optimisation",
    "Debugging",
    "System Design"

],


"code" => [

    "// iXeriox.dev",
    "// where ideas become systems",

    "const passion = 'building things that actually work';",

    "class SMSConnection {",

    "    constructor(config) {",

    "        this.connected = false;",
    "        this.retries = 0;",

    "    }",

    "}",


    "// Infini9 Platform",

    "const projects = [",

    "    'Infini9',",

    "    'FWA Karting',",

    "    'Orbit Roleplay'",

    "];",


    "<?php",

    "$server->boot();",

    "$community->connect();",

    "$project->deploy();",

    "?>",


    "<script setup>",

    "const stack = ref([",

    "    'Vue 3',",

    "    'JavaScript',",

    "    'PHP',",

    "    'NodeJS'",

    "]);",

    "</script>",


    "function createSomething() {",

    "    return 'another late night idea';",

    "}",


    "git add .",

    "git commit -m \"built another thing because I could\"",

    "git push origin main",
"// Most people play games.",
"// I end up debugging the servers they run on.",

"while(alive) {",
"    learn();",
"    build();",
"    improve();",
"}",

"sudo systemctl restart imagination.service",

"ERROR: sleep() not found",

"WARNING: another project created at 3AM",

"deploy --production --hope-it-works",

"// coffee.exe is running..."
]

];


function json($data)
{
    return json_encode(
        $data,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="data:image/png;base64,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">
<title>
<?= $site["identity"]["domain"] ?>
</title>


<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

<style>

:root{

    --bg:#07090d;
    --panel:#0d1118;
    --panel2:#111823;
    --border:#202936;

    --text:#e8edf3;
    --muted:#8893a3;

    --amber:#ffad55;
    --cyan:#36e0d0;
    --green:#65ff9a;

    --mono:
    "JetBrains Mono",
    "Fira Code",
    Consolas,
    monospace;

}


*{

    margin:0;
    padding:0;
    box-sizing:border-box;

}


html{

    scroll-behavior:smooth;

}


body{

    background:var(--bg);
    color:var(--text);

    font-family:
    Inter,
    system-ui,
    sans-serif;

    min-height:100vh;

    overflow-x:hidden;

}



body::before{

    content:"";

    position:fixed;

    inset:0;

    background:

    linear-gradient(
        rgba(255,255,255,.025) 1px,
        transparent 1px
    ),

    linear-gradient(
        90deg,
        rgba(255,255,255,.025) 1px,
        transparent 1px
    );

    background-size:
    45px 45px;


    pointer-events:none;

    opacity:.25;

    z-index:0;

}



body::after{

    content:"";

    position:fixed;

    inset:0;

    background:

    radial-gradient(
        circle at top,
        rgba(54,224,208,.08),
        transparent 40%
    ),

    radial-gradient(
        circle at bottom,
        rgba(255,173,85,.08),
        transparent 40%
    );


    z-index:-1;

}




/*
    Animated code background
*/


.code-background{

    position:fixed;

    inset:0;

    overflow:hidden;

    pointer-events:none;

    z-index:-1;

}



.code-line{

    position:absolute;

    font-family:var(--mono);

    font-size:.8rem;

    color:

    rgba(
        54,
        224,
        208,
        .12
    );


    white-space:nowrap;


    animation:

    drift 22s linear infinite;


}



@keyframes drift{


from{

    transform:

    translateY(120vh)
    rotate(-2deg);

}


to{

    transform:

    translateY(-30vh)
    rotate(2deg);

}


}





/*
 Header
*/


header{


    position:sticky;

    top:0;

    z-index:10;


    background:

    rgba(
        7,
        9,
        13,
        .75
    );


    backdrop-filter:blur(15px);


    border-bottom:

    1px solid var(--border);


}



.nav{


    max-width:1100px;

    margin:auto;

    padding:

    1.2rem
    2rem;


    display:flex;

    justify-content:space-between;

    align-items:center;


}



.logo{


    font-family:var(--mono);

    font-size:1.2rem;

    font-weight:700;


}



.logo span{

    color:var(--amber);

}




.status{


    font-family:var(--mono);

    font-size:.75rem;

    letter-spacing:.15em;

    color:var(--muted);


    display:flex;

    align-items:center;

    gap:.6rem;


}



.status span{


    width:8px;

    height:8px;

    border-radius:50%;

    background:var(--green);


    box-shadow:

    0 0 12px var(--green);


}





/*
 Main
*/


main{


    max-width:1100px;

    margin:auto;


    padding:

    0 2rem;


    position:relative;

    z-index:2;


}




section{


    padding:

    5rem 0;


}



.hero{


    min-height:

    calc(100vh - 80px);


    display:flex;

    justify-content:center;

    flex-direction:column;


}



.terminal{


    font-family:var(--mono);

    color:var(--cyan);

    font-size:.9rem;


    margin-bottom:2rem;


    line-height:2;


}



.hero h1{


    font-family:var(--mono);

    font-size:

    clamp(
        3rem,
        8vw,
        6rem
    );


    color:white;


    letter-spacing:-.06em;


}



.hero h2{


    margin-top:1rem;


    color:var(--amber);


    font-family:var(--mono);


    font-size:

    clamp(
        1rem,
        3vw,
        1.6rem
    );


}



.hero p{


    margin-top:2rem;


    max-width:650px;


    color:var(--muted);


    font-size:1.1rem;

    line-height:1.8;


}




h3{


    font-family:var(--mono);


    color:var(--cyan);


    margin-bottom:2rem;


    font-size:1rem;


}



.cards{


    display:grid;


    grid-template-columns:

    repeat(
        auto-fit,
        minmax(
            300px,
            1fr
        )
    );


    gap:1.5rem;


}



.card{


    background:

    rgba(
        13,
        17,
        24,
        .8
    );


    border:

    1px solid var(--border);


    padding:2rem;


    border-radius:14px;


    backdrop-filter:blur(10px);


    transition:.3s;


}



.card:hover{


    transform:

    translateY(-8px);


    border-color:

    var(--cyan);


}



.badge{


    display:inline-block;


    font-family:var(--mono);


    font-size:.7rem;


    color:var(--amber);


    border:

    1px solid var(--amber);


    padding:

    .25rem
    .6rem;


    margin-bottom:1rem;


}



.card h4{


    font-family:var(--mono);


    font-size:1.3rem;


    margin-bottom:1rem;


}



.card p{


    color:var(--muted);


    line-height:1.7;


}




.tags{


    margin-top:1.5rem;


    display:flex;


    flex-wrap:wrap;


    gap:.5rem;


}



.tags span,


.stack span{


    font-family:var(--mono);


    font-size:.75rem;


    padding:

    .5rem
    .8rem;


    border:

    1px solid var(--border);


    background:

    var(--panel);


    color:var(--cyan);


}




.stack{


    display:flex;


    flex-wrap:wrap;


    gap:.8rem;


}





.archive{


    border-left:

    2px solid var(--border);


    padding-left:2rem;


}



.archive-item{


    margin-bottom:2rem;


}



.archive-item strong{


    font-family:var(--mono);

}



.archive-item p{


    margin-top:.5rem;

    color:var(--muted);

}




.quote{


    text-align:center;


}



.quote code{


    font-family:var(--mono);


    color:var(--amber);


    font-size:

    clamp(
        1rem,
        3vw,
        1.5rem
    );


}



footer{


    text-align:center;


    padding:

    4rem 1rem;


    font-family:var(--mono);


    color:var(--muted);


    border-top:

    1px solid var(--border);


}





@media(max-width:700px){


    main{

        padding:
        0 1rem;

    }


    .nav{

        padding:
        1rem;

    }


    .hero h1{

        font-size:3rem;

    }

    .modal{

        width:95%;

        padding:1.5rem;

        max-height:85vh;

    }


    .modal h3{

        font-size:1.2rem;

    }



}

.hero-actions{

margin-top:2rem;

}



.hero-actions{

display:flex;

gap:1rem;

flex-wrap:wrap;

}



.hero-actions button,
.hero-actions .discord-button,
.modal button{


font-family:var(--mono);

background:

transparent;


border:

1px solid var(--amber);


color:var(--amber);


padding:

0.8rem 1.5rem;


cursor:pointer;

text-decoration:none;


transition:.25s;


}



.hero-actions button:hover,
.hero-actions .discord-button:hover,
.modal button:hover{


background:var(--amber);

color:var(--bg);


}

.hero-actions .discord-button{
    border-color:#5865f2;
    color:#aeb4ff;
}

.hero-actions .discord-button:hover{
    background:#5865f2;
    color:#fff;
}




.modal-overlay{


position:fixed;

inset:0;


background:

rgba(0,0,0,.75);


display:flex;

align-items:center;

justify-content:center;


padding:1rem;


z-index:50;


backdrop-filter:blur(10px);


overflow-y:auto;


}




.modal{


position:relative;


width:min(
500px,
90%
);


max-width:500px;


max-height:90vh;


overflow-y:auto;


background:

var(--panel);


border:

1px solid var(--border);


padding:2rem;


border-radius:14px;


margin:auto;


}



.modal h3{


margin-bottom:1.5rem;


}



.modal input,
.modal textarea{


width:100%;


background:

var(--bg);


border:

1px solid var(--border);


color:white;


padding:1rem;


margin-bottom:1rem;


font-family:var(--mono);


}



.modal textarea{


resize:none;


}



.modal .close{


position:absolute;


right:1rem;

top:1rem;


border:none;


font-size:1.5rem;


padding:0;


}



.contact-status{


margin-top:1rem;

font-family:var(--mono);

color:var(--cyan);

}

.modal select{


width:100%;


background:

var(--bg);


border:

1px solid var(--border);


color:white;


padding:1rem;


margin-bottom:1rem;


font-family:var(--mono);


cursor:pointer;


appearance:none;


background-image:

url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2336e0d0' d='M6 9L1 4h10z'/%3E%3C/svg%3E");


background-repeat:no-repeat;


background-position:right 1rem center;


}



.modal select:focus{


outline:none;


border-color:var(--cyan);


}



.features-section{


margin:1.5rem 0;


}



.features-section h4{


font-family:var(--mono);


font-size:.9rem;


color:var(--cyan);


margin-bottom:1rem;


letter-spacing:.1em;


}



.feature-grid{


display:grid;


grid-template-columns:repeat(2,1fr);


gap:.8rem;


margin-bottom:1rem;


}



.feature-option{


display:flex;


align-items:center;


gap:.6rem;


padding:.8rem;


background:var(--panel);


border:1px solid var(--border);


border-radius:8px;


cursor:pointer;


transition:.25s;


}



.feature-option:hover{


border-color:var(--cyan);


}



.feature-option input[type="checkbox"]{


width:18px;


height:18px;


cursor:pointer;


accent-color:var(--cyan);


}



.feature-option label{


font-family:var(--mono);


font-size:.8rem;


color:var(--text);


cursor:pointer;


flex:1;


}



.price-calculator{


margin-top:1.5rem;


padding:1.5rem;


background:

linear-gradient(

135deg,

rgba(54,224,208,.08),

rgba(255,173,85,.08)

);


border:1px solid var(--border);


border-radius:12px;


}



.price-calculator h4{


font-family:var(--mono);


font-size:.9rem;


color:var(--amber);


margin-bottom:1rem;


letter-spacing:.1em;


}



.price-display{


font-family:var(--mono);


font-size:2rem;


color:var(--green);


margin-bottom:.5rem;


}



.price-note{


font-family:var(--mono);


font-size:.75rem;


color:var(--muted);


line-height:1.6;


}



.field-label{


display:block;


font-family:var(--mono);


font-size:.75rem;


color:var(--cyan);


margin-bottom:.5rem;


letter-spacing:.1em;


}
.hero-logo{

    margin-bottom:2rem;

}


.hero-logo img{

    width:120px;

    height:120px;

    object-fit:contain;


    filter:
    drop-shadow(
        0 0 20px rgba(54,224,208,.35)
    );


    animation:
    logoFloat 4s ease-in-out infinite;

}



@keyframes logoFloat{

    0%,
    100%{

        transform:translateY(0);

    }


    50%{

        transform:translateY(-8px);

    }

}
.project-link {
    color: inherit;
    text-decoration: none;
}

.project-link:hover h4 {
    color: var(--amber);
}
.privacy-warning {
    display:flex;
    align-items:flex-start;
    gap:12px;

    margin-top:20px;
    padding:14px 16px;

    background:rgba(255,159,74,0.08);
    border:1px solid rgba(255,159,74,0.35);
    border-left:3px solid #ff9f4a;

    border-radius:8px;

    font-family:'JetBrains Mono', monospace;
    color:#b8c0cc;

    font-size:.85rem;
    line-height:1.5;
}

.warning-icon {
    color:#ff9f4a;
    font-size:1.2rem;
    animation:pulse-warning 2s infinite;
}

.privacy-warning strong {
    display:block;
    color:#ff9f4a;
    font-size:.8rem;
    text-transform:uppercase;
    letter-spacing:.15em;
    margin-bottom:5px;
}

.privacy-warning p {
    margin:0;
    color:#8b95a5;
}


@keyframes pulse-warning {

    50% {
        opacity:.45;
    }

}

.visitor-count {

    position: relative;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    gap: .35rem;

}



/* Analytics popup */

.visitor-tooltip {

    position: absolute;

    top: calc(100% + 16px);

    right: 0;


    width: 280px;


    background:

    rgba(
        13,
        17,
        24,
        .97
    );


    border:

    1px solid var(--border);


    border-radius:14px;


    padding:1.2rem;


    opacity:0;


    pointer-events:none;


    transform:

    translateY(-8px);


    transition:

    .25s ease;


    backdrop-filter:blur(15px);


    box-shadow:

    0 20px 50px rgba(0,0,0,.45),

    0 0 25px rgba(54,224,208,.12);


    z-index:100;

}



.visitor-count:hover .visitor-tooltip {

    opacity:1;

    pointer-events:auto;


    transform:

    translateY(0);

}



/* Top glow line */

.visitor-tooltip::after {

    content:"";


    position:absolute;


    top:0;

    left:15%;

    right:15%;


    height:2px;


    background:

    linear-gradient(
        90deg,
        transparent,
        var(--cyan),
        transparent
    );

}



/* Arrow */

.visitor-tooltip::before {

    content:"";


    position:absolute;


    top:-7px;

    right:30px;


    width:12px;

    height:12px;


    background:#0d1118;


    border-left:

    1px solid var(--border);


    border-top:

    1px solid var(--border);


    transform:rotate(45deg);

}



/* Header */

.visitor-header {

    display:flex;

    align-items:center;

    gap:.6rem;


    font-family:var(--mono);

    font-size:.7rem;


    letter-spacing:.15em;


    color:var(--cyan);


    margin-bottom:1rem;

}



.visitor-header span {

    width:8px;

    height:8px;


    border-radius:50%;


    background:var(--green);


    box-shadow:

    0 0 12px var(--green);

}



/* Main total */

.visitor-total strong {

    display:block;


    font-family:var(--mono);


    font-size:2.2rem;


    line-height:1;


    letter-spacing:-.06em;


    color:white;

}



.visitor-total small {

    display:block;


    margin-top:.4rem;


    color:var(--muted);


    font-size:.7rem;


    letter-spacing:.1em;


    text-transform:uppercase;

}



/* Divider */

.visitor-divider {

    height:1px;


    background:

    rgba(255,255,255,.08);


    margin:

    1rem 0;

}



/* Sections */

.visitor-label {

    font-family:var(--mono);


    color:var(--muted);


    font-size:.65rem;


    letter-spacing:.15em;


    margin-bottom:.5rem;

}



.visitor-value {

    font-family:var(--mono);


    color:var(--amber);


    font-size:1.2rem;

}



/* Countries */

.country {

    display:flex;

    justify-content:space-between;

    align-items:center;


    padding:.45rem 0;


    font-family:var(--mono);


    font-size:.8rem;


    border-bottom:

    1px solid rgba(255,255,255,.05);

}



.country:last-child {

    border-bottom:none;

}



.country span {

    color:var(--muted);

}



.country strong {

    color:var(--cyan);

}
.github-panel{

    background:rgba(13,17,24,.8);

    border:1px solid var(--border);

    border-radius:14px;

    padding:2rem;

}

.github-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:2rem;

}

.github-header h4{

    font-family:var(--mono);

    font-size:1.4rem;

    margin-bottom:.4rem;

}

.github-header p{

    color:var(--muted);

}

.github-profile{

    color:var(--amber);

    text-decoration:none;

    font-family:var(--mono);

}

.github-grid{

    display:grid;

    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));

    gap:1rem;

    margin-bottom:2rem;

}

.github-card{

    background:var(--panel);

    border:1px solid var(--border);

    border-radius:10px;

    padding:1.2rem;

    transition:.25s;

}

.github-card:hover{

    border-color:var(--cyan);

    transform:translateY(-3px);

}

.github-label{

    display:block;

    color:var(--muted);

    font-family:var(--mono);

    font-size:.7rem;

    letter-spacing:.15em;

    margin-bottom:.7rem;

}

.github-card strong{

    font-family:var(--mono);

    font-size:1.3rem;

    color:white;

}

.github-feed{

    display:flex;

    flex-direction:column;

    gap:1rem;

}

.github-event{

    display:flex;

    gap:1rem;

    align-items:flex-start;

    padding:1rem;

    background:var(--panel);

    border-left:3px solid var(--cyan);

    border-radius:8px;

}

.event-icon{

    color:var(--green);

    font-size:1.1rem;

}

.github-event strong{

    font-family:var(--mono);

    color:white;

}

.github-event p{

    color:var(--muted);

    margin:.25rem 0;

}

.github-event small{

    color:var(--amber);

    font-family:var(--mono);

}
/*
|--------------------------------------------------------------------------
| GitHub
|--------------------------------------------------------------------------
*/

.github-panel{

    background:rgba(13,17,24,.8);

    border:1px solid var(--border);

    border-radius:14px;

    padding:2rem;

    backdrop-filter:blur(10px);

}

.github-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:2rem;

    margin-bottom:2rem;

}

.github-header h4{

    font-family:var(--mono);

    font-size:1.5rem;

    margin-bottom:.4rem;

}

.github-header p{

    color:var(--muted);

}

.github-profile{

    color:var(--amber);

    text-decoration:none;

    font-family:var(--mono);

    transition:.25s;

}

.github-profile:hover{

    color:var(--cyan);

}

.github-grid{

    display:grid;

    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));

    gap:1rem;

    margin-bottom:2rem;

}

.github-card{

    background:var(--panel);

    border:1px solid var(--border);

    border-radius:12px;

    padding:1.2rem;

    transition:.25s;

}

.github-card:hover{

    border-color:var(--cyan);

    transform:translateY(-4px);

    box-shadow:0 0 18px rgba(54,224,208,.08);

}

.github-label{

    display:block;

    color:var(--muted);

    font-family:var(--mono);

    font-size:.72rem;

    letter-spacing:.12em;

    margin-bottom:.8rem;

}

.github-card strong{

    color:white;

    font-size:1.35rem;

    font-family:var(--mono);

}

.github-divider{

    margin:2rem 0 1rem;

    padding-bottom:.75rem;

    border-bottom:1px solid var(--border);

    font-family:var(--mono);

    color:var(--cyan);

    letter-spacing:.1em;

}

.github-repository{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:2rem;

    padding:1.4rem 0;

    border-bottom:1px solid rgba(255,255,255,.05);

    transition:.25s;

}

.github-repository:last-child{

    border-bottom:none;

}

.github-repository:hover{

    transform:translateX(6px);

}

.repository-info{

    flex:1;

}

.repository-name{

    display:inline-block;

    color:white;

    font-size:1.15rem;

    font-family:var(--mono);

    text-decoration:none;

    margin-bottom:.5rem;

    transition:.25s;

}

.repository-name:hover{

    color:var(--amber);

}

.repository-info p{

    color:var(--muted);

    line-height:1.7;

    margin-top:.25rem;

}

.repository-meta{

    display:flex;

    flex-wrap:wrap;

    justify-content:flex-end;

    gap:.6rem;

    min-width:280px;

}

.repository-meta span{

    padding:.45rem .75rem;

    border-radius:8px;

    border:1px solid var(--border);

    background:var(--panel2);

    font-family:var(--mono);

    font-size:.75rem;

    color:var(--cyan);

}

.current-project{

    margin-bottom:2rem;

    padding:1.5rem;

    border-radius:14px;

    border:1px solid rgba(101,255,154,.25);

    background:linear-gradient(
        135deg,
        rgba(101,255,154,.06),
        rgba(54,224,208,.04)
    );

}

.current-project h5{

    color:var(--green);

    font-family:var(--mono);

    font-size:.8rem;

    letter-spacing:.15em;

    margin-bottom:.8rem;

}

.current-project h3{

    margin:0;

    color:white;

    font-size:1.6rem;

}

.current-project p{

    margin-top:.75rem;

    color:var(--muted);

}

.current-project .tags{

    margin-top:1rem;

}

.repo-language{

    color:var(--green)!important;

}

.repo-stars{

    color:#ffd166!important;

}

.repo-forks{

    color:#ffad55!important;

}

.repo-updated{

    color:var(--cyan)!important;

}

@media(max-width:900px){

    .github-header{

        flex-direction:column;

        align-items:flex-start;

    }

    .github-repository{

        flex-direction:column;

        gap:1rem;

    }

    .repository-meta{

        justify-content:flex-start;

        min-width:unset;

    }

}
.github-warning{

    display:flex;

    align-items:flex-start;

    gap:14px;

    margin-bottom:2rem;

    padding:16px 18px;

    background:
        linear-gradient(
            135deg,
            rgba(54,224,208,.08),
            rgba(17,24,35,.85)
        );

    border:1px solid rgba(54,224,208,.25);

    border-left:4px solid var(--cyan);

    border-radius:12px;

    backdrop-filter:blur(8px);

}

.github-warning-icon{

    display:flex;

    align-items:center;

    justify-content:center;

    width:32px;

    height:32px;

    border-radius:50%;

    background:rgba(54,224,208,.12);

    color:var(--cyan);

    font-family:var(--mono);

    font-weight:700;

    font-size:1rem;

    flex-shrink:0;

}

.github-warning strong{

    display:block;

    margin-bottom:6px;

    color:var(--cyan);

    font-family:var(--mono);

    font-size:.8rem;

    text-transform:uppercase;

    letter-spacing:.15em;

}

.github-warning p{

    margin:0;

    color:var(--muted);

    line-height:1.7;

    font-size:.9rem;

}
.user-menu {
    position:relative;
}


.user-tooltip {

    position:absolute;

    top:calc(100% + 1rem);

    left:0;


    width:260px;


    background:
    rgba(13,17,24,.95);


    border:
    1px solid var(--border);


    border-radius:14px;


    padding:1rem;


    backdrop-filter:blur(14px);


    box-shadow:
    0 0 30px rgba(0,0,0,.4);


    opacity:0;

    visibility:hidden;


    transform:
    translateY(-10px);


    transition:.25s ease;


    z-index:100;

}


.user-menu:hover .user-tooltip {

    opacity:1;

    visibility:visible;


    transform:
    translateY(0);

}



.user-avatar img {

    width:48px;

    height:48px;

    border-radius:50%;


    border:
    1px solid var(--cyan);


    box-shadow:
    0 0 15px rgba(54,224,208,.4);

}



.user-info {

    margin-top:.8rem;

    font-family:var(--mono);

}


.user-info strong {

    display:block;

    color:var(--cyan);

}


.user-info span {

    color:var(--muted);

    font-size:.8rem;

}



.user-links {

    margin-top:1rem;

    display:flex;

    gap:.5rem;

}


.user-links a {

    flex:1;

    text-align:center;

    padding:.5rem;


    border:
    1px solid var(--border);


    color:var(--text);

    text-decoration:none;


    font-family:var(--mono);

    font-size:.75rem;

}


.user-links a:hover {

    border-color:var(--amber);

    color:var(--amber);

}
.quick-nav {

    position:fixed;

    left:1.5rem;

    top:50%;

    transform:translateY(-50%);


    display:flex;

    flex-direction:column;

    gap:.5rem;


    z-index:100;

}



.quick-nav a {

    width:120px;


    padding:.7rem 1rem;


    background:

    rgba(
        13,
        17,
        24,
        .75
    );


    border:

    1px solid var(--border);


    border-radius:10px;


    color:var(--muted);


    font-family:var(--mono);

    font-size:.75rem;


    text-decoration:none;


    letter-spacing:.1em;


    backdrop-filter:blur(10px);


    transition:.25s;

}



.quick-nav a:hover {

    color:var(--cyan);


    border-color:var(--cyan);


    transform:
    translateX(8px);


    box-shadow:

    0 0 20px

    rgba(
        54,
        224,
        208,
        .25
    );

}
@media (max-width: 1350px) {

    .quick-nav {

        display:none;

    }

}
/* Contact modal */
.contact-overlay{
    padding:clamp(1rem,4vw,3rem);
    background:
        radial-gradient(circle at 20% 15%,rgba(54,224,208,.13),transparent 34%),
        radial-gradient(circle at 85% 85%,rgba(255,173,85,.08),transparent 28%),
        rgba(3,6,10,.88);
}

.contact-modal{
    width:min(620px,100%);
    max-width:620px;
    padding:clamp(1.5rem,4vw,2.75rem);
    border:1px solid rgba(255,255,255,.1);
    border-radius:24px;
    background:
        linear-gradient(145deg,rgba(255,255,255,.04),transparent 36%),
        var(--panel);
    box-shadow:0 30px 90px rgba(0,0,0,.62),0 0 0 1px rgba(54,224,208,.04);
    scrollbar-width:thin;
    scrollbar-color:var(--cyan) rgba(3,7,12,.72);
}

.contact-modal::before{
    content:"";
    position:absolute;
    inset:0 0 auto;
    height:3px;
    border-radius:24px 24px 0 0;
    background:linear-gradient(90deg,var(--cyan),var(--amber));
}

.contact-modal > h3{
    margin:0 0 .35rem;
    padding-right:3rem;
    font-size:clamp(1.6rem,4vw,2.15rem);
    letter-spacing:-.035em;
}

.contact-modal > h3::after{
    content:"Have a question, an idea, or just want to say hello? Drop me a message.";
    display:block;
    max-width:500px;
    margin-top:.75rem;
    color:var(--muted);
    font-family:Inter,"Segoe UI",sans-serif;
    font-size:.94rem;
    font-weight:400;
    line-height:1.65;
    letter-spacing:0;
}

.contact-modal .close{
    top:1.25rem;
    right:1.25rem;
    display:grid;
    place-items:center;
    width:42px;
    height:42px;
    border:1px solid var(--border);
    border-radius:12px;
    background:rgba(255,255,255,.035);
    color:var(--muted);
    transition:.2s ease;
}

.contact-modal .close:hover{
    color:var(--text);
    border-color:var(--cyan);
    background:rgba(54,224,208,.08);
    transform:rotate(4deg);
}

.contact-modal .privacy-warning{
    display:flex;
    gap:.9rem;
    margin:1.5rem 0 1.75rem;
    padding:1rem 1.1rem;
    border:1px solid rgba(255,173,85,.2);
    border-radius:14px;
    background:rgba(255,173,85,.055);
    color:var(--muted);
}

.contact-modal .privacy-warning .warning-icon{
    display:grid;
    place-items:center;
    flex:0 0 30px;
    width:30px;
    height:30px;
    border-radius:9px;
    background:rgba(255,173,85,.14);
    color:var(--amber);
    font-weight:800;
}

.contact-modal .privacy-warning strong{color:var(--text);}
.contact-modal .privacy-warning p{margin:.25rem 0 0;font-size:.8rem;line-height:1.55;}
.contact-modal > br{display:none;}

.contact-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}

.contact-field{
    display:flex;
    flex-direction:column;
    gap:.55rem;
    min-width:0;
}

.contact-field > span{
    color:var(--text);
    font-family:var(--mono);
    font-size:.74rem;
    font-weight:700;
    letter-spacing:.07em;
    text-transform:uppercase;
}

.contact-field--message,
.contact-form > button,
.contact-form .contact-status{
    grid-column:1/-1;
}

.contact-form input,
.contact-form textarea{
    width:100%;
    min-height:54px;
    margin:0;
    border:1px solid rgba(255,255,255,.1);
    border-radius:12px;
    background:rgba(3,7,12,.72);
    color:var(--text);
    font-family:Inter,"Segoe UI",sans-serif;
    transition:border-color .2s ease,box-shadow .2s ease,background .2s ease;
}

.contact-form textarea{
    min-height:160px;
    resize:vertical;
    line-height:1.6;
}

.contact-form input::placeholder,
.contact-form textarea::placeholder{color:#667386;}

.contact-form input:focus,
.contact-form textarea:focus{
    outline:none;
    border-color:var(--cyan);
    background:rgba(4,10,15,.92);
    box-shadow:0 0 0 4px rgba(54,224,208,.1);
}

.contact-form > button{
    min-height:56px;
    margin-top:.25rem;
    border:0;
    border-radius:13px;
    background:linear-gradient(100deg,var(--cyan),#73f0c5);
    color:#061312;
    font-size:.9rem;
    font-weight:800;
    letter-spacing:.02em;
    box-shadow:0 12px 28px rgba(54,224,208,.14);
    transition:transform .2s ease,box-shadow .2s ease,filter .2s ease;
}

.contact-form > button:hover:not(:disabled){
    transform:translateY(-2px);
    filter:brightness(1.06);
    box-shadow:0 16px 34px rgba(54,224,208,.24);
}

.contact-form > button:disabled{opacity:.55;cursor:wait;}

.contact-form .contact-status{
    margin:0;
    padding:.85rem 1rem;
    border:1px solid rgba(54,224,208,.2);
    border-radius:11px;
    background:rgba(54,224,208,.06);
}

@media(max-width:600px){
    .contact-modal{padding:1.35rem;border-radius:18px;}
    .contact-form{grid-template-columns:1fr;}
    .contact-field--message,
    .contact-form > button,
    .contact-form .contact-status{grid-column:1;}
}

/* Project request — dedicated, spacious treatment */
.project-request-overlay{
    padding:clamp(1rem,4vw,3rem);
    background:
        radial-gradient(circle at 15% 15%,rgba(54,224,208,.12),transparent 34%),
        radial-gradient(circle at 85% 80%,rgba(255,173,85,.1),transparent 30%),
        rgba(3,6,10,.88);
}

.project-request-modal{
    width:min(860px,100%);
    max-width:860px;
    max-height:min(92vh,960px);
    padding:clamp(1.5rem,4vw,3rem);
    border-radius:24px;
    border:1px solid rgba(255,255,255,.1);
    background:
        linear-gradient(145deg,rgba(255,255,255,.035),transparent 35%),
        var(--panel);
    box-shadow:0 30px 90px rgba(0,0,0,.6),0 0 0 1px rgba(54,224,208,.04);
    scrollbar-width:thin;
    scrollbar-color:var(--cyan) rgba(3,7,12,.72);
}

.project-request-modal::-webkit-scrollbar{
    width:10px;
}

.project-request-modal::-webkit-scrollbar-track{
    margin:18px 0;
    border-radius:999px;
    background:rgba(3,7,12,.72);
}

.project-request-modal::-webkit-scrollbar-thumb{
    min-height:50px;
    border:2px solid var(--panel);
    border-radius:999px;
    background:linear-gradient(180deg,var(--cyan),#178f89);
}

.project-request-modal::-webkit-scrollbar-thumb:hover{
    background:linear-gradient(180deg,#72f5e9,var(--cyan));
}

.project-request-modal::before{
    content:"";
    position:absolute;
    inset:0 0 auto;
    height:3px;
    border-radius:24px 24px 0 0;
    background:linear-gradient(90deg,var(--cyan),var(--amber));
}

.project-request-modal > h3{
    margin:0 0 .35rem;
    padding-right:3rem;
    font-size:clamp(1.65rem,4vw,2.35rem);
    letter-spacing:-.04em;
}

.project-request-modal > h3::after{
    content:"Tell me about the idea, the scope, and what success looks like.";
    display:block;
    max-width:620px;
    margin-top:.75rem;
    color:var(--muted);
    font-family:Inter,"Segoe UI",sans-serif;
    font-size:.95rem;
    font-weight:400;
    line-height:1.65;
    letter-spacing:0;
}

.project-request-modal .close{
    top:1.25rem;
    right:1.25rem;
    display:grid;
    place-items:center;
    width:42px;
    height:42px;
    border:1px solid var(--border);
    border-radius:12px;
    background:rgba(255,255,255,.035);
    color:var(--muted);
    transition:.2s ease;
}

.project-request-modal .close:hover{
    color:var(--text);
    border-color:var(--cyan);
    background:rgba(54,224,208,.08);
    transform:rotate(4deg);
}

.project-request-modal .privacy-warning{
    display:flex;
    gap:.9rem;
    margin:1.5rem 0 2rem;
    padding:1rem 1.1rem;
    border:1px solid rgba(255,173,85,.2);
    border-radius:14px;
    background:rgba(255,173,85,.055);
    color:var(--muted);
}

.project-request-modal .privacy-warning .warning-icon{
    display:grid;
    place-items:center;
    flex:0 0 30px;
    width:30px;
    height:30px;
    border-radius:9px;
    background:rgba(255,173,85,.14);
    color:var(--amber);
    font-weight:800;
}

.project-request-modal .privacy-warning strong{color:var(--text);}
.project-request-modal .privacy-warning p{margin:.25rem 0 0;line-height:1.55;font-size:.8rem;}

.project-request-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}

.project-request-form .field-label{
    grid-column:1/-1;
    display:flex;
    align-items:center;
    gap:.75rem;
    margin:1rem 0 0;
    color:var(--text);
    font-size:.78rem;
}

.project-request-form .field-label::after{
    content:"";
    height:1px;
    flex:1;
    background:linear-gradient(90deg,var(--border),transparent);
}

.project-request-form input,
.project-request-form select,
.project-request-form textarea{
    min-height:54px;
    margin:0;
    border-radius:12px;
    border-color:rgba(255,255,255,.1);
    background:rgba(3,7,12,.72);
    font-family:Inter,"Segoe UI",sans-serif;
    transition:border-color .2s ease,box-shadow .2s ease,background .2s ease;
}

.project-request-form textarea,
.project-request-form .features-section,
.project-request-form .price-calculator,
.project-request-form > button,
.project-request-form .contact-status{
    grid-column:1/-1;
}

.project-request-form textarea{min-height:150px;resize:vertical;}
.project-request-form input::placeholder,
.project-request-form textarea::placeholder{color:#667386;}

.project-request-form input:focus,
.project-request-form select:focus,
.project-request-form textarea:focus{
    outline:none;
    border-color:var(--cyan);
    background:rgba(4,10,15,.92);
    box-shadow:0 0 0 4px rgba(54,224,208,.1);
}

.project-request-form .features-section{
    margin:1rem 0 0;
    padding:1.35rem;
    border:1px solid rgba(255,255,255,.075);
    border-radius:16px;
    background:rgba(255,255,255,.018);
}

.project-request-form .feature-grid{
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.7rem;
    margin:0;
}

.project-request-form .feature-option{
    position:relative;
    min-height:58px;
    margin:0;
    padding:.85rem;
    border-radius:12px;
    background:rgba(3,7,12,.58);
    user-select:none;
}

.project-request-form .feature-option:has(input:checked){
    border-color:var(--cyan);
    background:rgba(54,224,208,.08);
    box-shadow:inset 0 0 0 1px rgba(54,224,208,.08);
}

.project-request-form .feature-option input[type="checkbox"]{
    appearance:none;
    -webkit-appearance:none;
    display:grid;
    place-content:center;
    flex:0 0 20px;
    width:20px;
    height:20px;
    min-height:20px;
    margin:0;
    border:1px solid rgba(54,224,208,.48);
    border-radius:6px;
    background:rgba(3,7,12,.9);
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.025);
    cursor:pointer;
    transition:background .18s ease,border-color .18s ease,box-shadow .18s ease,transform .18s ease;
}

.project-request-form .feature-option input[type="checkbox"]::before{
    content:"";
    width:9px;
    height:5px;
    border:solid #05201e;
    border-width:0 0 2px 2px;
    opacity:0;
    transform:translateY(-1px) rotate(-45deg) scale(.5);
    transition:opacity .15s ease,transform .15s ease;
}

.project-request-form .feature-option input[type="checkbox"]:checked{
    border-color:var(--cyan);
    background:var(--cyan);
    box-shadow:0 0 0 3px rgba(54,224,208,.1),0 0 16px rgba(54,224,208,.22);
}

.project-request-form .feature-option input[type="checkbox"]:checked::before{
    opacity:1;
    transform:translateY(-1px) rotate(-45deg) scale(1);
}

.project-request-form .feature-option input[type="checkbox"]:focus-visible{
    outline:2px solid var(--amber);
    outline-offset:3px;
}

.project-request-form .feature-option label{font-size:.76rem;line-height:1.35;}

.project-request-form .price-calculator{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1.5rem;
    margin-top:.5rem;
    padding:1.35rem 1.5rem;
    border-color:rgba(54,224,208,.18);
    background:linear-gradient(120deg,rgba(54,224,208,.09),rgba(255,173,85,.055));
}

.project-request-form .price-calculator h4{margin:0 0 .45rem;}
.project-request-form .price-note{max-width:520px;margin:0;}
.project-request-form .price-display{flex:0 0 auto;margin:0;font-size:2.35rem;font-weight:800;}

.project-request-form > button{
    min-height:56px;
    margin-top:.25rem;
    border:0;
    border-radius:13px;
    background:linear-gradient(100deg,var(--cyan),#73f0c5);
    color:#061312;
    font-size:.9rem;
    font-weight:800;
    letter-spacing:.02em;
    box-shadow:0 12px 28px rgba(54,224,208,.14);
    transition:transform .2s ease,box-shadow .2s ease,filter .2s ease;
}

.project-request-form > button:hover:not(:disabled){
    transform:translateY(-2px);
    filter:brightness(1.06);
    box-shadow:0 16px 34px rgba(54,224,208,.24);
}

.project-request-form > button:disabled{opacity:.55;cursor:wait;}

@media(max-width:720px){
    .project-request-modal{padding:1.35rem;border-radius:18px;}
    .project-request-form{grid-template-columns:1fr;}
    .project-request-form .field-label,
    .project-request-form textarea,
    .project-request-form .features-section,
    .project-request-form .price-calculator,
    .project-request-form > button,
    .project-request-form .contact-status{grid-column:1;}
    .project-request-form .feature-grid{grid-template-columns:1fr 1fr;}
    .project-request-form .price-calculator{align-items:flex-start;flex-direction:column;}
}

@media(max-width:460px){
    .project-request-form .feature-grid{grid-template-columns:1fr;}
}
[v-cloak] {
    display: none !important;
}

.vue-loading-screen {
    position: fixed;
    inset: 0;
    z-index: 99999;

    display: flex;
    align-items: center;
    justify-content: center;

    background:
        radial-gradient(
            circle at center,
            rgba(17, 128, 106, 0.16),
            transparent 45%
        ),
        #070a0d;

    color: #ffffff;

    transition:
        opacity 0.4s ease,
        visibility 0.4s ease;
}

.vue-loading-screen.is-loaded {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.vue-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
}

.vue-loader-logo {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 72px;
    height: 72px;

    border: 1px solid rgba(17, 128, 106, 0.65);
    border-radius: 20px;

    background: rgba(17, 128, 106, 0.12);
    box-shadow:
        0 0 30px rgba(17, 128, 106, 0.2),
        inset 0 0 20px rgba(17, 128, 106, 0.08);

    color: #45e0bd;

    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.08em;
}

.vue-loader-spinner {
    width: 32px;
    height: 32px;

    border: 3px solid rgba(255, 255, 255, 0.12);
    border-top-color: #45e0bd;
    border-radius: 50%;

    animation: vue-loader-spin 0.8s linear infinite;
}

.vue-loader p {
    margin: 0;

    color: rgba(255, 255, 255, 0.62);

    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

@keyframes vue-loader-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .vue-loader-spinner {
        animation-duration: 1.8s;
    }
}
.about-section {
    position: relative;
    overflow: hidden;
}

.about-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2rem;

    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;

    border-bottom: 1px solid var(--border);
}

.section-label {
    display: block;

    margin-bottom: 0.65rem;

    color: var(--cyan);

    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.about-heading h3 {
    margin: 0;

    font-size: clamp(2rem, 5vw, 3.6rem);
    line-height: 1;
    letter-spacing: -0.055em;
}

.about-heading > p {
    max-width: 340px;
    margin: 0;

    color: var(--muted);

    line-height: 1.7;
    text-align: right;
}

.about-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
    gap: clamp(2rem, 6vw, 5rem);
    align-items: start;
}

.about-intro {
    max-width: 680px;
}

.about-intro > p {
    margin: 0 0 1.4rem;

    color: var(--muted);

    font-size: 1rem;
    line-height: 1.85;
}

.about-intro .about-lead {
    color: var(--text);

    font-size: clamp(1.25rem, 2.4vw, 1.7rem);
    line-height: 1.55;
    letter-spacing: -0.025em;
}

.about-intro strong,
.about-card strong,
.about-project strong {
    color: var(--cyan);
    font-weight: 700;
}

.about-cards {
    display: grid;
    gap: 0.85rem;
}

.about-card {
    position: relative;

    display: grid;
    grid-template-columns: 42px 1fr;
    gap: 1rem;

    padding: 1.35rem;

    border: 1px solid var(--border);
    border-radius: 16px;

    background: rgba(255, 255, 255, 0.025);

    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        background 0.25s ease;
}

.about-card:hover {
    transform: translateX(5px);

    border-color: rgba(114, 245, 233, 0.35);
    background: rgba(114, 245, 233, 0.045);
}

.about-card-number {
    padding-top: 0.15rem;

    color: var(--cyan);

    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
}

.about-card h4 {
    margin: 0 0 0.45rem;

    color: var(--text);

    font-size: 1.05rem;
}

.about-card p {
    margin: 0;

    color: var(--muted);

    font-size: 0.9rem;
    line-height: 1.7;
}

.terminal-quote {
    position: relative;

    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.85rem;

    margin: 2rem 0 0;
    padding: 1.25rem 1.4rem;

    border: 1px solid rgba(114, 245, 233, 0.2);
    border-left: 3px solid var(--cyan);
    border-radius: 0 14px 14px 0;

    background: rgba(114, 245, 233, 0.035);
}

.terminal-quote > span {
    color: var(--cyan);

    font-family: monospace;
    font-weight: 700;
}

.terminal-quote p {
    margin: 0;

    color: var(--text);

    font-family: monospace;
    font-size: 0.88rem;
    line-height: 1.75;
}

.about-project {
    display: grid;
    grid-template-columns: minmax(190px, 0.55fr) 1fr 1fr;
    gap: 2rem;

    margin-top: clamp(2.5rem, 7vw, 5rem);
    padding: clamp(1.5rem, 4vw, 2.4rem);

    border: 1px solid var(--border);
    border-radius: 20px;

    background:
        linear-gradient(
            135deg,
            rgba(114, 245, 233, 0.07),
            transparent 55%
        ),
        rgba(255, 255, 255, 0.02);
}

.about-project-title span {
    display: block;

    margin-bottom: 0.5rem;

    color: var(--amber);

    font-family: monospace;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.about-project-title h4 {
    margin: 0;

    font-size: clamp(1.4rem, 3vw, 2rem);
    letter-spacing: -0.035em;
}

.about-project > p {
    margin: 0;

    color: var(--muted);

    font-size: 0.92rem;
    line-height: 1.75;
}

@media (max-width: 850px) {
    .about-heading {
        display: block;
    }

    .about-heading > p {
        margin-top: 1rem;
        text-align: left;
    }

    .about-layout {
        grid-template-columns: 1fr;
    }

    .about-project {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 520px) {
    .about-card {
        grid-template-columns: 32px 1fr;
        padding: 1.1rem;
    }

    .about-project {
        padding: 1.25rem;
    }
}

/* Reviews and community */
.reviews-section{
    position:relative;
}

.reviews-heading{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:2rem;
    margin-bottom:2rem;
}

.reviews-heading h3{
    margin-top:.65rem;
    font-size:clamp(1.65rem,4vw,2.5rem);
}

.reviews-heading > p{
    max-width:390px;
    color:var(--muted);
    line-height:1.7;
    text-align:right;
}

.reviews-panel{
    display:grid;
    grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);
    gap:1px;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:18px;
    background:var(--border);
    box-shadow:0 24px 80px rgba(0,0,0,.22);
}

.reviews-copy{
    padding:clamp(1.6rem,4vw,3rem);
    background:linear-gradient(135deg,rgba(17,24,35,.98),rgba(10,14,20,.98));
}

.reviews-prompt{
    color:var(--cyan);
    font:700 .7rem var(--mono);
    letter-spacing:.16em;
}

.review-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
    padding:clamp(1.5rem,4vw,3rem);
    border:1px solid var(--border);
    border-radius:18px;
    background:linear-gradient(135deg,rgba(17,24,35,.98),rgba(10,14,20,.98));
    box-shadow:0 24px 80px rgba(0,0,0,.22);
}

.published-reviews{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
    margin-bottom:1.25rem;
}
.published-review{
    padding:1.5rem;
    border:1px solid var(--border);
    border-radius:16px;
    background:rgba(17,24,35,.84);
}
.published-review-stars{color:var(--amber);letter-spacing:.2em;}
.published-review-stars .muted{color:#3b414b;}
.published-review blockquote{margin:1rem 0;color:var(--text);line-height:1.75;white-space:pre-line;}
.published-review strong{color:var(--cyan);font:.75rem var(--mono);}

.review-form-intro,
.review-rating,
.review-field--message,
.review-form > button,
.review-status{grid-column:1/-1;}

.review-form-intro h4{
    margin:.8rem 0 .65rem;
    font-size:clamp(1.35rem,3vw,2rem);
}

.review-form-intro p,
.discord-community p{color:var(--muted);line-height:1.7;}

.review-rating{display:flex;gap:.65rem;margin:.5rem 0;border:0;}
.review-rating legend,
.review-field > span{
    margin-bottom:.65rem;
    color:var(--text);
    font:700 .74rem var(--mono);
    letter-spacing:.07em;
    text-transform:uppercase;
}
.review-rating label{cursor:pointer;}
.review-rating input{position:absolute;opacity:0;pointer-events:none;}
.review-rating label > span{
    display:block;
    padding:.65rem .85rem;
    border:1px solid var(--border);
    border-radius:10px;
    color:var(--muted);
    font:700 .8rem var(--mono);
    transition:.2s;
}
.review-rating label small{margin-left:.25rem;color:var(--amber);}
.review-rating input:checked + span,
.review-rating label:hover > span{border-color:var(--amber);background:rgba(255,173,85,.08);color:var(--text);}

.review-field{display:flex;flex-direction:column;min-width:0;}
.review-field > span small{color:var(--muted);font-weight:400;text-transform:none;}
.review-field input,
.review-field textarea{
    width:100%;
    min-height:52px;
    padding:.9rem 1rem;
    border:1px solid rgba(255,255,255,.1);
    border-radius:11px;
    outline:0;
    background:rgba(3,7,12,.72);
    color:var(--text);
    font:inherit;
}
.review-field textarea{min-height:150px;resize:vertical;line-height:1.6;}
.review-field input:focus,
.review-field textarea:focus{border-color:var(--cyan);box-shadow:0 0 0 4px rgba(54,224,208,.1);}
.review-form > button{
    min-height:54px;
    border:0;
    border-radius:11px;
    background:linear-gradient(100deg,var(--cyan),#73f0c5);
    color:#061312;
    font:800 .85rem var(--mono);
    cursor:pointer;
}
.review-form > button:disabled{opacity:.55;cursor:wait;}
.review-status{padding:.85rem 1rem;border:1px solid rgba(101,255,154,.25);border-radius:10px;color:var(--green);}
.review-status--error{border-color:rgba(255,100,100,.3);color:#ff8c8c;}

.discord-community{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:2rem;
    margin-top:1.25rem;
    padding:1.5rem 2rem;
    border:1px solid rgba(88,101,242,.35);
    border-radius:16px;
    background:linear-gradient(145deg,rgba(88,101,242,.16),rgba(13,17,24,.9));
}
.discord-community > div{display:grid;grid-template-columns:auto 1fr;column-gap:1rem;align-items:center;}
.discord-community .discord-card-icon{grid-row:1/4;}
.discord-community small{color:#aeb4ff;font:700 .65rem var(--mono);letter-spacing:.12em;}
.discord-community h4{margin:.25rem 0;font-size:1.1rem;}
.discord-community a{
    flex:none;padding:.8rem 1rem;border-radius:10px;background:#5865f2;color:#fff;
    font:700 .75rem var(--mono);text-decoration:none;
}

.reviews-copy h4{
    max-width:570px;
    margin:.8rem 0 1rem;
    font-size:clamp(1.35rem,3vw,2rem);
}

.reviews-copy p{
    max-width:650px;
    color:var(--muted);
    line-height:1.75;
}

.review-topics{
    display:flex;
    flex-wrap:wrap;
    gap:.65rem;
    margin-top:1.75rem;
}

.review-topics span{
    padding:.45rem .7rem;
    border:1px solid rgba(54,224,208,.2);
    border-radius:999px;
    color:#aebbc9;
    font:.72rem var(--mono);
}

.discord-card{
    display:flex;
    align-items:center;
    gap:1.1rem;
    min-height:240px;
    padding:2rem;
    background:linear-gradient(145deg,rgba(88,101,242,.26),rgba(13,17,24,.98));
    color:var(--text);
    text-decoration:none;
    transition:background .25s,box-shadow .25s;
}

.discord-card:hover{
    background:linear-gradient(145deg,rgba(88,101,242,.42),rgba(13,17,24,.98));
    box-shadow:inset 0 0 50px rgba(88,101,242,.08);
}

.discord-card-icon{
    display:grid;
    flex:0 0 52px;
    width:52px;
    height:52px;
    place-items:center;
    border-radius:16px;
    background:#5865f2;
    color:#fff;
    font:700 1.5rem var(--mono);
    box-shadow:0 10px 30px rgba(88,101,242,.35);
}

.discord-card > span:last-child{
    display:flex;
    min-width:0;
    flex-direction:column;
    gap:.4rem;
}

.discord-card small{
    color:#aeb4ff;
    font:700 .65rem var(--mono);
    letter-spacing:.12em;
}

.discord-card strong{font-size:1.05rem;}
.discord-card em{
    overflow-wrap:anywhere;
    color:var(--muted);
    font:normal .72rem var(--mono);
}

@media (max-width: 760px){
    .reviews-heading{display:block;}
    .reviews-heading > p{margin-top:1rem;text-align:left;}
    .reviews-panel{grid-template-columns:1fr;}
    .discord-card{min-height:unset;}
    .review-form{grid-template-columns:1fr;}
    .published-reviews{grid-template-columns:1fr;}
    .review-field{grid-column:1;}
    .discord-community{align-items:flex-start;flex-direction:column;}
}
</style>
</head>


<body>
<nav class="quick-nav">
    <a href="#top">
        Top
   </a>

    <a href="#about">
        ABOUT
    </a>

    <a href="#github">
        GITHUB
    </a>

    <a href="#projects">
        PROJECTS
    </a>

    <a href="#skills">
        SKILLS
    </a>

    <a href="#archive">
        ARCHIVE
    </a>

    <a href="#reviews">
        REVIEWS
    </a>



</nav>
<div id="vue-loading-screen" class="vue-loading-screen">
    <div class="vue-loader">
        <div class="vue-loader-logo">iX</div>

        <div class="vue-loader-spinner"></div>

        <p>Loading iXeriox.dev</p>
    </div>
</div>
<div id="app" v-cloak>


<div class="code-background">

    <div
    v-for="(line,index) in floatingCode"
    :key="index"
    class="code-line"
    :style="line.style"
    >
        {{ line.text }}
    </div>

</div>



<header>


<div class="nav">


<div class="user-menu">

    <div class="logo">
        {{ identity.name }}
        <span>.dev</span>
    </div>


    <div class="user-tooltip">

<span>Services</span>



        <div class="user-links">

            <a href="/pen">
                Add Pen
            </a>
            <a href="/chat">
                i9 Chat
                </a>

        </div>

    </div>

</div>


<div class="status">

    <span></span>

    ONLINE

    <div class="visitor-count">

        <strong style="color:white;">
            {{ visitors.today?.unique?.toLocaleString() || '0' }}
        </strong>

        {{ visitors.today?.unique === 1 ? 'Visitor' : 'Visitors' }}
        today!


        <div class="visitor-tooltip">

            <div class="visitor-header">
                <span></span>
                VISITOR ANALYTICS
            </div>


            <div class="visitor-total">

                <strong>
                    {{ visitors.totalUnique?.toLocaleString() || '0' }}
                </strong>

                <small>
                    Total Unique Visitors
                </small>

            </div>


            <div class="visitor-divider"></div>


            <div class="visitor-section">

                <div class="visitor-label">
                    TODAY
                </div>


                <div class="visitor-value">

                    {{ visitors.today?.unique?.toLocaleString() || '0' }}

                </div>

            </div>


            <div class="visitor-divider"></div>


            <div class="visitor-section">

                <div class="visitor-label">
                    COUNTRIES
                </div>


                <div
                class="country"
                v-for="country in visitors.today?.countries || []"
                :key="country.name"
                >

                    <p>
                        {{ country.name }}
                    </p>


                    <strong>
                        {{ country.count }}
                    </strong>

                </div>


                <div
                v-if="!visitors.today?.countries?.length"
                class="country"
                >

                    <span>
                        No data yet
                    </span>

                </div>


            </div>


        </div>

    </div>

</div>


</div>


</header>




<main>



<section class="hero">






<div class="hero-logo">

<img
:src="identity.raw.avatar"
:alt="identity.raw.name"
>
</div>


<h1>

{{ identity.name }}

</h1>


<h2>

{{ identity.tagline }}

</h2>


<p>

{{ identity.intro }}


</p>

<div class="hero-actions">

<button @click="contactOpen = true">
    <span>./contact</span>
    Send me a message
</button>


<button @click="projectRequestOpen = true">
    <span>./request_project</span>
    Request a Project
</button>

<a
    class="discord-button"
    href="https://discord.gg/Tk7hCUrshR"
    target="_blank"
    rel="noopener noreferrer"
>
    <span>./discord</span>
    Join the server
</a>


</div>
</section>



<section id="about" class="about-section">

    <div class="about-heading">
        <div>
            <span class="section-label">./about_me</span>
            <h3>More than just the code.</h3>
        </div>

        <p>
            Developer, father, gamer and competitive karter.
        </p>
    </div>

    <div class="about-layout">

        <div class="about-intro">

            <p class="about-lead">
                I'm <strong>Leon</strong>, though most people know me as
                <strong>Leo</strong>. I enjoy taking ideas, figuring out how
                they work and turning them into something real.
            </p>

            <p>
                Most of my work sits somewhere between web development,
                backend systems and real-time applications. I like building
                practical projects that solve an actual problem, especially
                when they connect with the things I already care about.
            </p>

            <blockquote class="terminal-quote">
                <span>&gt;</span>

                <p>
                    I don't just enjoy the things I do. I build around them,
                    improve them and share them with the people around me.
                </p>
            </blockquote>

        </div>

        <div class="about-cards">

            <article class="about-card">
                <span class="about-card-number">01</span>

                <div>
                    <h4>Developer</h4>

                    <p>
                        I build websites, APIs, backend services and real-time
                        systems using technologies including PHP, JavaScript,
                        Vue and Node.js.
                    </p>
                </div>
            </article>

            <article class="about-card">
                <span class="about-card-number">02</span>

                <div>
                    <h4>Father</h4>

                    <p>
                        Being a dad to my son, <strong>Theo</strong>, is the
                        most important part of my life. He keeps me motivated
                        to learn, improve and lead by example.
                    </p>
                </div>
            </article>

            <article class="about-card">
                <span class="about-card-number">03</span>

                <div>
                    <h4>Motorsport</h4>

                    <p>
                        Away from the keyboard, I compete in karting and
                        co-own <strong>FWA (First Wave Agents)</strong>, a
                        team focused on progression, teamwork and performance.
                    </p>
                </div>
            </article>

        </div>

    </div>

    <div class="about-project">

        <div class="about-project-title">
            <span>Featured personal build</span>
            <h4>FWA Karting</h4>
        </div>

        <p>
            I brought my interests in development and motorsport together by
            creating <strong>FWA Karting</strong>, a Node.js-powered platform
            that works with TeamSport timing data.
        </p>

        <p>
            It captures and stores lap times in real time while supporting
            progression tracking, improvement insights, team communication
            and event planning.
        </p>

    </div>

</section>

<section id="github">

<h3>
./github
</h3>

<div class="github-panel">
<div class="github-warning">

    <span class="github-warning-icon">ⓘ</span>

    <div>

        <strong>GitHub Activity Notice</strong>

        <p>
            Repository activity shown below only includes my <b>Public GitHub Repositories</b>.
            Pushes to private projects, client work and unpublished repositories are intentionally excluded.
        </p>

    </div>

</div>
    <div class="github-header">

        <div>

            <h4>Live GitHub Overview</h4>

            <p>
                Automatically populated from my public repositories.
            </p>

        </div>

        <a
            class="github-profile"
            href="https://github.com/iXeriox"
            target="_blank"
            rel="noopener noreferrer"
        >
            View Profile →
        </a>

    </div>


    <div class="github-grid">

        <div class="github-card">

            <span class="github-label">
                Last Push
            </span>

            <strong>
                {{ github.lastPush ? timeAgo(github.lastPush) : "Loading..." }}
            </strong>

        </div>

        <div class="github-card">

            <span class="github-label">
                Public Repositories
            </span>

            <strong>
                {{ github.repoCount ?? "--" }}
            </strong>

        </div>

        <div class="github-card">

            <span class="github-label">
                Total Stars
            </span>

            <strong>
                ⭐ {{ github.stars ?? "--" }}
            </strong>

        </div>

        <div class="github-card">

            <span class="github-label">
                Latest Repository
            </span>

            <strong>
                {{ github.latestRepo || "Loading..." }}
            </strong>

        </div>

    </div>


    <div class="github-divider">

        Latest Repositories

    </div>


    <div
        class="github-repository"
        v-for="repo in github.repositories"
        :key="repo.name"
    >

        <div class="repository-info">

            <a
                :href="repo.url"
                target="_blank"
                class="repository-name"
            >

                {{ repo.name }}

            </a>

            <p>

                {{ repo.description || "No description provided." }}

            </p>

        </div>


        <div class="repository-meta">

            <span>

                {{ repo.language || "Unknown" }}

            </span>

            <span>

                ⭐ {{ repo.stars }}

            </span>

            <span>

                🍴 {{ repo.forks }}

            </span>

            <span>

                {{ timeAgo(repo.updated) }}

            </span>

        </div>

    </div>

</div>

</section>
<section id="projects">


<h3>

./current_projects

</h3>



<div class="cards">


<div
class="card"
v-for="project in projects"
:key="project.name"
>


<div class="badge">

{{ project.status }}

</div>

<a
    v-if="project.isLink"
    :href="project.url"
    target="_blank"
    rel="noopener noreferrer"
    class="project-link"
>
    <h4>
        {{ project.name }}
    </h4>
</a>

<h4 v-else>
    {{ project.name }}
</h4>


<p>

{{ project.description }}

</p>


<div class="tags">


<span
v-for="tag in project.tags"
>

{{ tag }}

</span>


</div>


</div>


</div>


</section>





<section id="skills">


<h3>

./technology

</h3>



<div class="stack">


<span
v-for="item in stack"
>

{{ item }}

</span>


</div>



</section>





<section id="archive">


<h3>

./archive

</h3>


<div class="archive">


<div
v-for="item in archive"
class="archive-item"
>


<strong>

{{ item.name }}

</strong>


<p>

{{ item.description }}

</p>


</div>


</div>


</section>





<section id="reviews" class="reviews-section">

    <div class="reviews-heading">
        <div>
            <span class="section-label">./reviews</span>
            <h3>Built with people, not just pixels.</h3>
        </div>

        <p>
            Feedback from the people and communities I build alongside.
        </p>
    </div>

    <div v-if="reviews.length" class="published-reviews">
        <article v-for="item in reviews" :key="item.id" class="published-review">
            <div class="published-review-stars" :aria-label="`${item.rating} out of 5 stars`">
                <span v-for="score in 5" :key="score" :class="{ muted: score > item.rating }">★</span>
            </div>
            <blockquote>{{ item.review }}</blockquote>
            <strong>— {{ item.name }}</strong>
        </article>
    </div>

    <form class="review-form" @submit.prevent="sendReview">
        <div class="review-form-intro">
            <span class="reviews-prompt">LEAVE A REVIEW</span>
            <h4>Worked with me or used one of my projects?</h4>
            <p>
                Share your honest experience below. Your review is sent
                privately to me through Discord and will not be published
                automatically.
            </p>
        </div>

        <fieldset class="review-rating">
            <legend>Your rating</legend>
            <label v-for="score in 5" :key="score">
                <input
                    v-model.number="review.rating"
                    type="radio"
                    name="review-rating"
                    :value="score"
                    required
                >
                <span>{{ score }}<small>★</small></span>
            </label>
        </fieldset>

        <label class="review-field">
            <span>Your name</span>
            <input v-model.trim="review.name" type="text" maxlength="80" required>
        </label>

        <label class="review-field">
            <span>Email or Discord <small>(kept private)</small></span>
            <input v-model.trim="review.email" type="text" maxlength="120" required>
        </label>

        <label class="review-field review-field--message">
            <span>Your review</span>
            <textarea
                v-model.trim="review.message"
                maxlength="1500"
                placeholder="What did you enjoy, and what could be better?"
                required
            ></textarea>
        </label>

        <button type="submit" :disabled="sendingReview">
            {{ sendingReview ? "Sending review..." : "Send review" }}
        </button>

        <p
            v-if="reviewStatus"
            class="review-status"
            :class="{ 'review-status--error': !reviewStatus.success }"
            role="status"
        >
            {{ reviewStatus.message }}
        </p>
    </form>

    <aside class="discord-community">
        <div>
            <span class="discord-card-icon" aria-hidden="true">#</span>
            <small>WANT TO CHAT INSTEAD?</small>
            <h4>Join my Discord community.</h4>
            <p>Talk projects, development, gaming and everything in between.</p>
        </div>

        <a href="https://discord.gg/Tk7hCUrshR" target="_blank" rel="noopener noreferrer">
            Join Discord <span aria-hidden="true">→</span>
        </a>
    </aside>

</section>


<section class="quote">


<code>

"I started as a gamer. Somewhere along the way I accidentally became the guy everyone asks to fix things."

</code>


</section>




</main>




<footer>


iXeriox.dev

<br>

Built with curiosity | All rights reserved | Protected by Cloudflare

</footer>


<div
class="modal-overlay contact-overlay"
v-if="contactOpen"
@click.self="contactOpen=false"
>


<div class="modal contact-modal" role="dialog" aria-modal="true" aria-labelledby="contact-modal-title">


<button
class="close"
@click="contactOpen=false"
>
×
</button>


<h3 id="contact-modal-title">
./send_message
</h3>
<div class="privacy-warning">
    <span class="warning-icon">⚠</span>

    <div>
        <strong>Security Notice</strong>
        <p>
            • Messages are logged for security and moderation purposes.
            IP address and approximate geolocation data may be recorded.
        </p>
    <p> • Due to spamming reasons, We've limited messages to <b>1</b> message every <b>5</b> minutes!
    </div>
</div>
<br />

<form class="contact-form" @submit.prevent="sendMessage">


<label class="contact-field">
<span>Your name</span>
<input
v-model="contact.name"
placeholder="e.g. Alex Smith"
autocomplete="name"
required
>
</label>


<label class="contact-field">
<span>Email or Discord</span>
<input
v-model="contact.email"
placeholder="alex@example.com or @alex"
autocomplete="email"
required
>
</label>


<label class="contact-field contact-field--message">
<span>How can I help?</span>
<textarea
v-model="contact.message"
placeholder="Tell me what you have in mind..."
rows="6"
required
></textarea>
</label>



<button
type="submit"
:disabled="sending"
>

{{ sending ? "Sending message..." : "Send message →" }}

</button>



<p
v-if="contactStatus"
class="contact-status"
>

{{ contactStatus?.message || ''  }}

</p>


</form>


</div>


</div>

<div
class="modal-overlay project-request-overlay"
v-if="projectRequestOpen"
@click.self="projectRequestOpen=false"
>


<div class="modal project-request-modal" role="dialog" aria-modal="true" aria-label="Project request">


<button
class="close"
@click="projectRequestOpen=false"
>
×
</button>


<h3>
./project_request
</h3>
<div class="privacy-warning">
    <span class="warning-icon">⚠</span>

    <div>
        <strong>Security Notice</strong>
        <p>
            • Project requests are logged for security and moderation purposes.
            IP address and approximate geolocation data may be recorded.
        </p>
    <p> • Due to spamming reasons, We've limited submissions to <b>1</b> request every <b>10</b> minutes!
    </div>
</div>
<br />

<form class="project-request-form" @submit.prevent="sendProjectRequest()">


<label class="field-label">YOUR INFORMATION</label>


<input
v-model="projectRequest.name"
placeholder="Your name"
required
>


<input
v-model="projectRequest.email"
placeholder="Email or Discord"
required
>


<label class="field-label">PROJECT DETAILS</label>


<input
v-model="projectRequest.projectName"
placeholder="Project name"
required
>


<select
v-model="projectRequest.projectType"
@change="calculatePrice"
required
>

<option value="" disabled>Select project type</option>

<option
v-for="type in projectTypes"
:key="type.value"
:value="type.value"
>

{{ type.label }}

</option>

</select>


<select
v-model="projectRequest.budget"
>

<option value="">Budget (optional)</option>

<option
v-for="range in budgetRanges"
:key="range.value"
:value="range.value"
>

{{ range.label }}

</option>

</select>


<select
v-model="projectRequest.timeline"
@change="calculatePrice"
required
>

<option value="" disabled>Select desired timeline</option>

<option
v-for="option in timelineOptions"
:key="option.value"
:value="option.value"
>

{{ option.label }}

</option>

</select>


<textarea
v-model="projectRequest.description"
placeholder="Project description..."
rows="6"
required
></textarea>


<div class="features-section">


<h4>ADDITIONAL FEATURES</h4>


<div class="feature-grid">


<div class="feature-option" @click.self="toggleProjectFeature('api')">

<input
type="checkbox"
id="feature-api"
value="api"
v-model="projectRequest.selectedFeatures"
@change="calculatePrice"
>

<label for="feature-api">API Integration</label>

</div>


<div class="feature-option" @click.self="toggleProjectFeature('auth')">

<input
type="checkbox"
id="feature-auth"
value="auth"
v-model="projectRequest.selectedFeatures"
@change="calculatePrice"
>

<label for="feature-auth">User Authentication</label>

</div>


<div class="feature-option" @click.self="toggleProjectFeature('database')">

<input
type="checkbox"
id="feature-db"
value="database"
v-model="projectRequest.selectedFeatures"
@change="calculatePrice"
>

<label for="feature-db">Database Design</label>

</div>


<div class="feature-option" @click.self="toggleProjectFeature('admin')">

<input
type="checkbox"
id="feature-admin"
value="admin"
v-model="projectRequest.selectedFeatures"
@change="calculatePrice"
>

<label for="feature-admin">Admin Panel</label>

</div>


<div class="feature-option" @click.self="toggleProjectFeature('realtime')">

<input
type="checkbox"
id="feature-realtime"
value="realtime"
v-model="projectRequest.selectedFeatures"
@change="calculatePrice"
>

<label for="feature-realtime">Real-time Updates</label>

</div>


</div>


</div>


<div class="price-calculator">


<h4>GUIDE PRICE</h4>


<div class="price-display">

{{ projectRequest.calculatedPrice }}

</div>


<p class="price-note">

This is a simple guide based on the project type and features selected.
Mobile-friendly design is included where applicable. If the price or options
do not quite fit, send the request anyway and we can discuss it.

</p>


</div>



<button
type="submit"
:disabled="sendingProject"
>

{{ sendingProject ? "Submitting..." : "Submit Request" }}

</button>



<p
v-if="projectRequestStatus"
class="contact-status"
>

{{ projectRequestStatus?.message || ''  }}

</p>


</form>


</div>


</div>
</div>



<script>

const DATA = <?= json($site) ?>;

const SITE_DATA = <?= json_encode([
    "visitors" => $visitorStats
]) ?>;
const {createApp}=Vue;


createApp({

data(){

return{

...DATA,
contactOpen:false,
projectRequestOpen:false,
github: {

    repoCount: 0,

    stars: 0,

    forks: 0,

    latestRepo: null,

    lastPush: null,

    currentlyWorkingOn: null,

    repositories: []

},
sending:false,
sendingProject:false,
sendingReview:false,
visitors: SITE_DATA.visitors,

contactStatus:"",
projectRequestStatus:"",
reviewStatus:null,

contact:{

    name:"",
    email:"",
    message:""

},
review:{
    name:"",
    email:"",
    rating:null,
    message:""
},
projectRequest:{

    name:"",
    email:"",
    projectName:"",
    projectType:"",
    budget:"",
    timeline:"",
    description:"",
    selectedFeatures:[],
    calculatedPrice:"Select a project type"

},
projectTypes:[

    {value:"website",label:"Website / Landing Page"},
    {value:"webapp",label:"Web Application"},
    {value:"api",label:"API / Backend Service"},
    {value:"automation",label:"Automation / Scripts"},
    {value:"integration",label:"System Integration"},
    {value:"other",label:"Other / Custom"}

],
budgetRanges:[

    {value:"under-100",label:"Under £100"},
    {value:"100-250",label:"£100 - £250"},
    {value:"250-500",label:"£250 - £500"},
    {value:"500-1000",label:"£500 - £1,000"},
    {value:"1000+",label:"£1,000+"},
    {value:"discuss",label:"Not sure — happy to discuss"}

],
timelineOptions:[

    {value:"1-2weeks",label:"1-2 weeks"},
    {value:"2-4weeks",label:"2-4 weeks"},
    {value:"1-2months",label:"1-2 months"},
    {value:"2-3months",label:"2-3 months"},
    {value:"3months+",label:"3+ months"}

],
floatingCode:[]

}

},


mounted(){
    const loadingScreen = document.getElementById(
        "vue-loading-screen"
    );

    if (loadingScreen) {
        requestAnimationFrame(() => {
            loadingScreen.classList.add("is-loaded");

            setTimeout(() => {
                loadingScreen.remove();
            }, 450);
        });
    }
this.createCode();
    this.loadGithub();
setInterval(() => {

    this.loadGithub();

}, 300000); // Every 5 minutes
},


methods:{
timeAgo(date) {

    if (!date)
        return "Unknown";

    const seconds = Math.floor(
        (Date.now() - new Date(date)) / 1000
    );

    const intervals = [

        [31536000, "year"],
        [2592000, "month"],
        [86400, "day"],
        [3600, "hour"],
        [60, "minute"]

    ];

    for (const [value, label] of intervals) {

        const amount = Math.floor(seconds / value);

        if (amount >= 1) {

            return `${amount} ${label}${amount > 1 ? "s" : ""} ago`;

        }

    }

    return "Just now";

},
openRepository(repo) {

    window.open(
        repo.url,
        "_blank",
        "noopener"
    );

},
truncate(text, length = 120) {

    if (!text)
        return "";

    if (text.length <= length)
        return text;

    return text.substring(0, length) + "...";

},
commitTitle(message) {

    if (!message)
        return "No recent commits";

    return message.split("\n")[0];

},
async loadGithub() {

    try {

        const response = await fetch("/_github.php");

        this.github = await response.json();

    }

    catch(err){

        console.error(err);

    }

},
async sendMessage()
{
    this.sending = true;
    this.contactStatus = null;

    try {
        const response = await fetch("/contact.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                formType: "message",
                name: this.contact.name,
                email: this.contact.email,
                message: this.contact.message
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.error || `Message failed (${response.status}).`
            );
        }

        this.contactStatus = {
            success: true,
            message: "Message sent successfully ✓"
        };

        this.contact = {
            name: "",
            email: "",
            message: ""
        };
    }
    catch (error) {
        console.error("Contact message failed:", error);

        this.contactStatus = {
            success: false,
            message: error?.message || "Unable to send message."
        };
    }
    finally {
        this.sending = false;
    }
},
async sendReview()
{
    this.sendingReview = true;
    this.reviewStatus = null;

    try {
        const response = await fetch("/contact.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                formType: "review",
                name: this.review.name,
                email: this.review.email,
                rating: this.review.rating,
                review: this.review.message
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || `Review failed (${response.status}).`);
        }

        this.reviewStatus = {
            success: true,
            message: "Thanks — your review was sent successfully ✓"
        };
        this.review = {name: "", email: "", rating: null, message: ""};
    }
    catch (error) {
        console.error("Review submission failed:", error);
        this.reviewStatus = {
            success: false,
            message: error?.message || "Unable to send your review."
        };
    }
    finally {
        this.sendingReview = false;
    }
},
toggleProjectFeature(feature)
{
    const selected = this.projectRequest.selectedFeatures;
    const index = selected.indexOf(feature);

    if(index === -1)
    {
        selected.push(feature);
    }
    else
    {
        selected.splice(index, 1);
    }

    this.calculatePrice();
},
calculatePrice()
{
    const projectPrices = {
        "website": 150,
        "webapp": 300,
        "api": 200,
        "automation": 100,
        "integration": 175,
        "other": 100
    };


    const featurePrices = {
        "api": 75,
        "auth": 75,
        "database": 100,
        "admin": 150,
        "realtime": 125
    };

    if(!this.projectRequest.projectType)
    {
        this.projectRequest.calculatedPrice = "Select a project type";
        return;
    }

    let total = projectPrices[this.projectRequest.projectType] || 100;

    this.projectRequest.selectedFeatures.forEach(feature => {
        total += featurePrices[feature] || 0;
    });

    this.projectRequest.calculatedPrice = `From £${total.toLocaleString()}`;

},
async sendProjectRequest()
{

    this.sendingProject = true;

    this.projectRequestStatus = null;


    try {

        const response = await fetch(
            "/contact.php",
            {

                method:"POST",

                headers:
                {
                    "Content-Type":"application/json"
                },

body: JSON.stringify({
    formType: "project",
    name: this.projectRequest.name,
    email: this.projectRequest.email,
    projectName: this.projectRequest.projectName,
    projectType: this.projectRequest.projectType,
    budget: this.projectRequest.budget,
    timeline: this.projectRequest.timeline,
    description: this.projectRequest.description,
    selectedFeatures: this.projectRequest.selectedFeatures,
    estimatedPrice: this.projectRequest.calculatedPrice
})

            }
        );


        const data = await response.json();


        if(data.success)
        {

            this.projectRequestStatus =
            {
                success:true,
                message:"Project request submitted successfully ✓"
            };


            this.projectRequest =
            {
                name:"",
                email:"",
                projectName:"",
                projectType:"",
                budget:"",
                timeline:"",
                description:"",
                selectedFeatures:[],
                calculatedPrice:"Select a project type"
            };

        }
        else
        {

            this.projectRequestStatus =
            {
                success:false,
                message:data.error || "Failed submitting project request."
            };

        }


    }
    catch(error)
    {

        console.error(error);


        this.projectRequestStatus =
        {
            success:false,
            message:"Unable to contact server."
        };

    }
    finally
    {

        this.sendingProject = false;

    }

},
createCode(){

this.floatingCode=this.code.map((line)=>{

return {

text:line,

style:{

top:
Math.random()*100+"vh",

left:
Math.random()*100+"vw",

animationDelay:
Math.random()*10+"s"

}

}

})


}


}



}).mount("#app");


</script>


</body>

</html>
