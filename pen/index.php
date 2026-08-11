<?php

$id = $_GET["id"] ?? "";

$content = "";


if ($id) {

    require_once __DIR__ . "/functions.php";

    $pen = loadPen($id);


    if ($pen) {

        $content = $pen["content"];

    }

}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>
iXeriox.dev | Pen
</title>


<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.0/min/vs/loader.min.js"></script>
<script src="/pen/js/detect-language.js"></script>
<link rel="icon" type="image/png" href="data:image/png;base64,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">


<style>

:root {

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



* {

    margin:0;
    padding:0;
    box-sizing:border-box;

}



html {

    background:var(--bg);

}



body {

    min-height:100vh;

    background:var(--bg);

    color:var(--text);

    font-family:
    Inter,
    system-ui,
    sans-serif;

    overflow-x:hidden;

    position:relative;

}



/*
    Background grid
*/


body::before {

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

    pointer-events:none;

    z-index:0;

}
body::after {

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


    pointer-events:none;


    z-index:0;

}

/*
    Floating code
*/


.code-background {

    position:fixed;

    inset:0;

    pointer-events:none;

    overflow:hidden;

    z-index:1;

}

.container {

    position:relative;

    z-index:2;

}

.code-line {

    position:absolute;

    font-family:var(--mono);

    color:

    rgba(
        54,
        224,
        208,
        .05
    );


    animation:
    drift 25s linear infinite;


    white-space:nowrap;

}



@keyframes drift {

from {

    transform:
    translateY(120vh);

}


to {

    transform:
    translateY(-30vh);

}

}



/*
    Layout
*/


.container {

    max-width:1100px;

    margin:auto;

    padding:

    3rem 2rem;

}




header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:3rem;


    padding-bottom:1.5rem;


    border-bottom:

    1px solid var(--border);

}



.logo {

    font-family:var(--mono);

    font-size:1.4rem;

    font-weight:bold;

}



.logo span {

    color:var(--amber);

}



/*
    Status
*/


.status {

    display:flex;

    align-items:center;

    gap:.6rem;

    font-family:var(--mono);

    color:var(--muted);

    font-size:.8rem;

}



.status span {

    width:8px;

    height:8px;

    border-radius:50%;

    background:var(--green);


    box-shadow:

    0 0 12px var(--green);

}



/*
    Main panel
*/


.panel {

    background:

    rgba(
        13,
        17,
        24,
        .85
    );


    border:

    1px solid var(--border);


    border-radius:14px;


    padding:2rem;


    backdrop-filter:

    blur(12px);

}



.title {

    font-family:var(--mono);

    color:var(--cyan);

    margin-bottom:.5rem;

}



.subtitle {

    color:var(--muted);

    line-height:1.6;

    margin-bottom:2rem;

}



/*
    Editor header
*/


.editor-header {

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:1rem;

    font-family:var(--mono);

}



.label {

    display:block;

    color:var(--muted);

    font-size:.7rem;

    letter-spacing:.15em;

}



#language {

    color:var(--cyan);

}



.auto {

    color:var(--green);

    font-size:.75rem;

}



/*
    Monaco
*/


#editor {

    height:600px;

    min-height:600px;

    width:100%;


    position:relative;


    border:

    1px solid var(--border);


    border-radius:12px;


    overflow:hidden;

}



.editor-loading {

    position:absolute;

    inset:0;


    display:flex;

    align-items:center;

    justify-content:center;


    background:#05070b;


    color:var(--cyan);


    font-family:var(--mono);


    z-index:10;


    transition:.5s;

}



.editor-ready .editor-loading {

    opacity:0;

    pointer-events:none;

}
/*
    Save Button
*/


.save-button {

    position:relative;

    margin-top:1.5rem;


    padding:

    1rem 2.5rem;


    background:

    rgba(
        255,
        173,
        85,
        .05
    );


    border:

    1px solid var(--amber);


    color:var(--amber);


    font-family:var(--mono);


    font-size:.9rem;


    letter-spacing:.08em;


    text-transform:uppercase;


    cursor:pointer;


    overflow:hidden;


    transition:.3s;

}




.save-button::before {


    content:"";


    position:absolute;


    inset:0;


    background:

    linear-gradient(

        120deg,

        transparent,

        rgba(
            255,
            173,
            85,
            .25
        ),

        transparent

    );


    transform:

    translateX(-100%);


    transition:.5s;

}



.save-button:hover::before {


    transform:

    translateX(100%);

}




.save-button:hover {


    background:var(--amber);


    color:var(--bg);


    box-shadow:

    0 0 25px

    rgba(
        255,
        173,
        85,
        .35
    );


    transform:

    translateY(-3px);

}




.save-button::after {


    content:">";


    margin-left:.8rem;


    color:var(--cyan);


}




.save-button:hover::after {


    color:var(--bg);

}



/*
    Info box
*/


.info {


    margin-top:2rem;


    padding:1rem;


    border-left:

    3px solid var(--cyan);



    background:

    rgba(
        54,
        224,
        208,
        .05
    );



    color:var(--muted);



    font-family:var(--mono);



    font-size:.85rem;



    line-height:1.6;

}




@media(max-width:700px){


    .container {

        padding:

        1.5rem 1rem;

    }



    header {

        flex-direction:column;

        align-items:flex-start;

        gap:1rem;

    }



    .panel {

        padding:1rem;

    }



    #editor {

        height:500px;

        min-height:500px;

    }


}

.logo {
    font-family:var(--mono);
    font-size:1.4rem;
    font-weight:bold;
    text-decoration:none;
    color:var(--text);
    cursor:pointer;
    opacity:1;
    transition:.25s ease;
}

.logo span {
    color:var(--amber);
    transition:.25s ease;
}

.logo:hover {
    opacity:1;
}

.logo:hover span {
    color:var(--cyan);
}

</style>


</head>



<body>



<div class="code-background">


<div class="code-line" style="top:10%;left:5%">

const idea = "build something";

</div>


<div class="code-line" style="top:40%;left:65%">

git commit -m "another late night idea"

</div>


<div class="code-line" style="top:70%;left:20%">

sudo systemctl restart imagination.service

</div>


</div>





<div class="container">



<header>


<a href="/" class="logo">
    iXeriox<span>.dev/pen</span>
</a>



<div class="status">

<span></span>

ONLINE |

Developer Scratchpad

</div>


</header>






<div class="panel">



<h1 class="title">

./new_pen

</h1>




<p class="subtitle">

Quick storage for code snippets, debugging sessions,
ideas and experiments.

</p>






<form method="POST" action="save.php">





<div class="editor-header">



<div>


<span class="label">

LANGUAGE

</span>



<strong id="language">

TEXT

</strong>



</div>




<div class="auto">

AUTO DETECT

</div>



</div>







<div id="editor">


<div class="editor-loading">

./initialising_editor...

</div>


</div>







<input

type="hidden"

name="code"

id="codeInput"


>







<button

type="submit"

class="save-button"

>

SAVE PEN

</button>




</form>







<div class="info">


⚠ Pens are temporary developer storage.


<br><br>


Do not store passwords, API keys,
private credentials or sensitive information.


</div>




</div>



</div>
<script>


let monacoEditor = null;


const languageLabel =
document.getElementById("language");



/*
    Language detection
    (detectLanguage() itself now lives in /js/detect-language.js
    so it's shared with view.php instead of duplicated)
*/




function updateLanguage()
{

    if(!monacoEditor)
        return;



    const detected =
    detectLanguage(
        monacoEditor.getValue()
    );



    languageLabel.innerText =
    detected.toUpperCase();



    monaco.editor.setModelLanguage(

        monacoEditor.getModel(),

        detected

    );

}




/*
    Monaco loader
*/


require.config({

    paths:{

        vs:

        "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.0/min/vs"

    }

});





require(
[
    "vs/editor/editor.main"
],


function(){



/*
    Custom iXeriox theme
*/


monaco.editor.defineTheme(

"ixeriox-dark",

{


base:"vs-dark",

inherit:true,


rules:[

{
    token:"comment",
    foreground:"8893a3"
},

{
    token:"keyword",
    foreground:"ffad55"
},

{
    token:"string",
    foreground:"65ff9a"
},

{
    token:"number",
    foreground:"36e0d0"
}

],


colors:{


"editor.background":

"#05070b",



"editor.foreground":

"#e8edf3",



"editorCursor.foreground":

"#36e0d0"



}


});





const startingCode =

<?= json_encode($content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;



const detectedLanguage =

detectLanguage(

startingCode

);




monacoEditor =

monaco.editor.create(


document.getElementById("editor"),


{


value:

startingCode,



language:

detectedLanguage,



theme:

"ixeriox-dark",



automaticLayout:true,



fontFamily:

"'JetBrains Mono', monospace",



fontSize:14,



minimap:{


    enabled:true

},



wordWrap:"on",



scrollBeyondLastLine:false,



padding:{


    top:15,

    bottom:15

}


}

);





/*
    Remove loading screen
*/


setTimeout(()=>{


    document

    .getElementById("editor")

    .classList

    .add("editor-ready");



    document

    .querySelector(".editor-loading")

    .innerText =

    "./ready";



    monacoEditor.layout();



    monacoEditor.focus();



},500);






/*
    Live detection
*/


monacoEditor.onDidChangeModelContent(

()=>{


    updateLanguage();


});




updateLanguage();



});







/*
    Save handler
*/


document

.querySelector("form")

.addEventListener(

"submit",

function(){



    if(monacoEditor)

    {


        document

        .getElementById("codeInput")

        .value =

        monacoEditor.getValue();


    }



});



</script>


</body>

</html>