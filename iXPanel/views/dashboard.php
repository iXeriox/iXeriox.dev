<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06080d">
    <meta name="robots" content="noindex,nofollow">
    <title>iXPanel | System Intelligence</title>
    <link rel="stylesheet" href="assets/ixpanel.css">
</head>
<body>
<div class="skeleton" data-loader><div style="text-align:center"><div class="loader"></div><div class="load-text">INITIALISING IXPANEL</div></div></div>
<div class="app">
    <aside>
        <div class="logo"><span class="logo-mark">iX</span><span>iX<em>Panel</em></span></div>
        <nav>
            <button class="nav active" data-view="overview"><i>⌁</i><span>Overview</span></button>
            <button class="nav" data-view="system"><i>□</i><span>System</span></button>
            <button class="nav" data-view="runtime"><i>&lt;/&gt;</i><span>PHP runtime</span></button>
            <button class="nav" data-view="pens"><i>P</i><span>Pens</span></button>
            <button class="nav" data-view="visitors"><i>V</i><span>Site information</span></button>
            <button class="nav" data-view="github"><i>G</i><span>GitHub cache</span></button>
            <button class="nav" data-view="chat"><i>C</i><span>Chat</span></button>
            <button class="nav" data-view="pen"><i>P</i><span>Pen</span></button>
            <button class="nav" data-view="account"><i>A</i><span>Account</span></button>
        </nav>
        <div class="side-foot"><small>SYSTEM STATUS</small><strong><span class="dot"></span><span data-side-status>Connecting</span></strong><a class="logout" href="?logout=1">SIGN OUT</a></div>
    </aside>
    <main>
        <header>
            <div class="headline"><p class="eyebrow">SYSTEM INTELLIGENCE</p><h1 data-title>Command overview</h1><p>Live infrastructure telemetry from this PHP host.</p></div>
            <div class="live"><span class="pulse"></span><span>LIVE · <span data-updated>waiting</span></span></div>
        </header>
        <div class="error-banner" data-error hidden></div>

        <section class="view" data-panel="overview">
            <div class="hero-grid">
                <article class="card health"><div><span class="card-label">Overall health</span><h2 data-health>Checking…</h2><p>Calculated from current system pressure.</p></div><div class="score" data-score style="--score:0"><strong data-score-text>0</strong></div></article>
                <article class="card"><span class="card-label">CPU pressure</span><div class="metric-value"><span data-cpu>0</span>%</div><div class="metric-sub"><span data-cores>0</span> logical cores</div><div class="bar" style="--value:0%" data-cpu-bar><span></span></div></article>
                <article class="card amber"><span class="card-label">Memory use</span><div class="metric-value"><span data-memory>0</span>%</div><div class="metric-sub"><span data-memory-used>—</span> used</div><div class="bar" style="--value:0%" data-memory-bar><span></span></div></article>
                <article class="card purple"><span class="card-label">Storage use</span><div class="metric-value"><span data-disk>0</span>%</div><div class="metric-sub"><span data-disk-free>—</span> available</div><div class="bar" style="--value:0%" data-disk-bar><span></span></div></article>
            </div>
            <div class="content-grid">
                <article class="card chart-card"><div class="card-head"><div><span class="card-label">Live pressure</span><h3>Resource activity</h3></div><span class="chip">LAST 20 SAMPLES</span></div><canvas data-chart></canvas></article>
                <article class="card"><div class="card-head"><div><span class="card-label">Host snapshot</span><h3>Machine details</h3></div></div><div class="facts"><div class="fact"><span>Hostname</span><strong data-host>—</strong></div><div class="fact"><span>Operating system</span><strong data-os>—</strong></div><div class="fact"><span>Architecture</span><strong data-arch>—</strong></div><div class="fact"><span>Uptime</span><strong data-uptime>—</strong></div><div class="fact"><span>HTTPS</span><strong data-https>—</strong></div></div></article>
            </div>
        </section>

        <section class="view" data-panel="system" hidden>
            <div class="detail-grid">
                <article class="card"><span class="card-label">Processor</span><h3>CPU information</h3><div class="facts"><div class="fact"><span>Model</span><strong data-cpu-model>—</strong></div><div class="fact"><span>Logical cores</span><strong data-core-detail>—</strong></div><div class="fact"><span>1 min load</span><strong data-load1>—</strong></div><div class="fact"><span>5 min load</span><strong data-load5>—</strong></div><div class="fact"><span>15 min load</span><strong data-load15>—</strong></div></div></article>
                <article class="card"><span class="card-label">Memory</span><h3>Allocation</h3><div class="facts"><div class="fact"><span>System used</span><strong data-mem-used-detail>—</strong></div><div class="fact"><span>System total</span><strong data-mem-total>—</strong></div><div class="fact"><span>PHP process</span><strong data-process>—</strong></div><div class="fact"><span>Process peak</span><strong data-peak>—</strong></div></div></article>
                <article class="card"><span class="card-label">Storage</span><h3>Primary volume</h3><div class="facts"><div class="fact"><span>Used</span><strong data-disk-used>—</strong></div><div class="fact"><span>Free</span><strong data-disk-free-detail>—</strong></div><div class="fact"><span>Total</span><strong data-disk-total>—</strong></div></div></article>
            </div>
        </section>

        <section class="view" data-panel="runtime" hidden>
            <div class="detail-grid">
                <article class="card"><span class="card-label">Runtime</span><h3>PHP environment</h3><div class="facts"><div class="fact"><span>PHP version</span><strong data-php>—</strong></div><div class="fact"><span>Server API</span><strong data-sapi>—</strong></div><div class="fact"><span>Extensions</span><strong data-extensions>—</strong></div><div class="fact"><span>OPcache</span><strong data-opcache>—</strong></div></div></article>
                <article class="card"><span class="card-label">Limits</span><h3>Execution policy</h3><div class="facts"><div class="fact"><span>Memory limit</span><strong data-limit>—</strong></div><div class="fact"><span>Execution time</span><strong data-execution>—</strong></div><div class="fact"><span>Upload limit</span><strong data-upload>—</strong></div></div></article>
                <article class="card"><span class="card-label">Web server</span><h3>Request context</h3><div class="facts"><div class="fact"><span>Software</span><strong data-software>—</strong></div><div class="fact"><span>Protocol</span><strong data-protocol>—</strong></div><div class="fact"><span>Session polls</span><strong data-views>—</strong></div><div class="fact"><span>Timezone</span><strong data-timezone>—</strong></div></div></article>
            </div>
        </section>

        <section class="view" data-panel="pens" hidden>
            <div class="admin-grid">
                <article class="card"><div class="card-head"><div><span class="card-label">Stored pens</span><h3>Pen library</h3></div><span class="chip" data-pen-count>0 PENS</span></div><div class="admin-list" data-pen-list></div></article>
                <article class="card"><div class="card-head"><div><span class="card-label">Editor</span><h3 data-pen-heading>Select a pen</h3></div></div><div data-pen-empty class="metric-sub">Choose a pen from the library to inspect or modify it.</div><div data-pen-editor hidden><label class="editor-label">LANGUAGE</label><input class="admin-input" data-pen-language maxlength="40"><label class="editor-label">CONTENT · MAX 500 KB</label><textarea class="admin-editor" data-pen-content spellcheck="false"></textarea><div class="admin-actions"><a class="admin-btn" data-pen-open target="_blank">Open pen</a><button class="admin-btn danger" data-delete-pen>Delete</button><button class="admin-btn primary" data-save-pen>Save pen</button></div></div></article>
            </div>
        </section>

        <section class="view" data-panel="visitors" hidden>
            <div class="summary-grid"><div class="summary"><span>TOTAL UNIQUE</span><strong data-total-unique>0</strong></div><div class="summary"><span>TODAY UNIQUE</span><strong data-today-unique>0</strong></div><div class="summary"><span>COUNTRIES</span><strong data-country-count>0</strong></div><div class="summary"><span>LAST VISIT</span><strong data-last-visit style="font-size:12px">—</strong></div></div>
            <article class="card"><div class="card-head"><div><span class="card-label">Editable source</span><h3>/Data/siteInfo.json</h3></div></div><textarea class="admin-editor" data-site-info spellcheck="false"></textarea><div class="admin-actions"><button class="admin-btn primary" data-save-site>Validate & save JSON</button></div></article>
        </section>

        <section class="view" data-panel="github" hidden>
            <div class="summary-grid"><div class="summary"><span>REPOSITORIES</span><strong data-repos>0</strong></div><div class="summary"><span>STARS</span><strong data-stars>0</strong></div><div class="summary"><span>FORKS</span><strong data-forks>0</strong></div><div class="summary"><span>LATEST</span><strong data-latest style="font-size:14px">—</strong></div></div>
            <article class="card"><div class="card-head"><div><span class="card-label">Cached data</span><h3>/Data/githubCache.json</h3></div><span class="chip" data-github-file>NOT FOUND</span></div><div data-repo-list></div></article>
        </section>

        <section class="view" data-panel="chat" hidden>
            <div class="embed-shell">
                <div class="embed-top"><span>EMBEDDED APPLICATION · /chat</span><a href="/chat/" target="_blank" rel="noopener">Open in new tab ↗</a></div>
                <div class="embed-frame"><iframe title="iXeriox Chat" data-embed-src="/chat/" loading="lazy"></iframe></div>
            </div>
        </section>

        <section class="view" data-panel="pen" hidden>
            <div class="embed-shell">
                <div class="embed-top"><span>EMBEDDED APPLICATION · /pen</span><a href="/pen/" target="_blank" rel="noopener">Open in new tab ↗</a></div>
                <div class="embed-frame"><iframe title="iXeriox Pen" data-embed-src="/pen/" loading="lazy"></iframe></div>
            </div>
        </section>

        <section class="view" data-panel="account" hidden>
            <article class="card" style="max-width:620px"><div class="card-head"><div><span class="card-label">Security</span><h3>Administrator password</h3></div></div><div class="warning" data-password-warning hidden>You are still using the default administrator password. Change it now.</div><label class="editor-label">NEW PASSWORD · MINIMUM 12 CHARACTERS</label><input class="admin-input" type="password" data-new-password autocomplete="new-password"><div class="admin-actions"><button class="admin-btn primary" data-change-password>Update password</button></div></article>
        </section>
    </main>
</div>
<script src="assets/ixpanel.js" defer></script>
</body>
</html>
