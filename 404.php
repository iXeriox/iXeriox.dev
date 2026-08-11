<?php

http_response_code(404);

$request = htmlspecialchars($_SERVER["REQUEST_URI"] ?? "/");

$country = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? "UNKNOWN";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
404 | iXeriox.dev
</title>


<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&display=swap" rel="stylesheet">


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
--red:#ff5c5c;


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


body{

min-height:100vh;

background:var(--bg);

color:var(--text);

font-family:
Inter,
system-ui,
sans-serif;

display:flex;

align-items:center;

justify-content:center;

overflow:hidden;

}



/*
 background grid
*/


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


opacity:.25;

}



body::after{

content:"";

position:fixed;

inset:0;


background:

radial-gradient(
circle at top,
rgba(54,224,208,.12),
transparent 40%
),

radial-gradient(
circle at bottom,
rgba(255,173,85,.12),
transparent 40%
);


z-index:-1;

}




.container{

width:min(900px,92%);


background:

rgba(
13,
17,
24,
.85
);


border:

1px solid var(--border);


padding:

3rem;


border-radius:16px;


backdrop-filter:blur(15px);


box-shadow:

0 0 50px rgba(54,224,208,.08);


}



.header{

font-family:var(--mono);

font-size:.8rem;

letter-spacing:.15em;

color:var(--cyan);

margin-bottom:2rem;

}



.status{

display:flex;

align-items:center;

gap:.6rem;

font-family:var(--mono);

font-size:.75rem;

color:var(--muted);

}



.status span{

width:8px;

height:8px;

border-radius:50%;

background:var(--amber);

box-shadow:

0 0 15px var(--amber);

}




h1{


font-family:var(--mono);


font-size:

clamp(
4rem,
10vw,
7rem
);


letter-spacing:-.08em;


color:white;


margin-top:1rem;


}



h1 span{

color:var(--amber);

}




h2{

font-family:var(--mono);

color:var(--cyan);

margin-top:1rem;

font-size:1.2rem;

}



p{

margin-top:1.5rem;

max-width:700px;

line-height:1.8;

color:var(--muted);

}




.terminal{


margin-top:2rem;


background:#080b10;


border:

1px solid var(--border);


padding:1.5rem;


font-family:var(--mono);


font-size:.85rem;


}



.line{

margin:.5rem 0;

}



.green{

color:var(--green);

}


.amber{

color:var(--amber);

}


.red{

color:var(--red);

}



.cyan{

color:var(--cyan);

}




.cursor{

display:inline-block;

width:8px;

height:15px;

background:var(--cyan);

animation:

blink 1s infinite;

}




@keyframes blink{


50%{

opacity:0;

}


}





.actions{

margin-top:2rem;

}



a{


display:inline-block;


padding:

.8rem

1.5rem;


border:

1px solid var(--amber);


color:var(--amber);


font-family:var(--mono);


text-decoration:none;


transition:.25s;


}



a:hover{

background:var(--amber);

color:var(--bg);

}





.footer{


margin-top:2rem;


font-family:var(--mono);


font-size:.7rem;


color:var(--muted);


}




</style>


</head>


<body>


<div class="container">


<div class="header">

// iXeriox.dev // ROUTE_HANDLER

</div>



<div class="status">

<span></span>

SYSTEM ONLINE

</div>



<h1>

4<span>0</span>4

</h1>


<h2>

Well... this is awkward.

</h2>



<p>

The page you requested does not exist.

The server searched through the entire project tree,
checked the routes, checked the deployments...

and politely informed me that you probably clicked something
that shouldn't exist.

</p>



<div class="terminal">


<div class="line green">

[ OK ] Server connection established

</div>


<div class="line cyan">

[ INFO ] Searching route:

<?= $request ?>

</div>


<div class="line amber">

[ INFO ] Visitor region:

<?= $country ?>

</div>



<div class="line red">

[ FAIL ] Resource not found

</div>


<div class="line red">

[ FAIL ] Nothing here except disappointment

</div>


<div class="line cyan">

root@ixeriox.dev:~$ _

<span class="cursor"></span>

</div>


</div>




<p>

If you were looking for something legitimate,
the page may have moved.

If you were testing routes, probing endpoints,
or looking for somewhere you shouldn't be...

don't worry.

The server noticed.

</p>



<div class="actions">

<a href="/">

./return_home

</a>

</div>




<div class="footer">

Built by iXeriox.dev | PHP + Vue 3 + questionable amounts of coffee, cookies and Cola.

</div>



</div>



</body>


</html>