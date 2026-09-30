(() => {
    const $ = selector => document.querySelector(selector);
    const history = { cpu: [], memory: [], disk: [] };
    let admin = { csrf:"", pens:[], siteInfo:{}, github:null };
    let selectedPen = null;
    const formatSize = item => item ? `${item.value} ${item.unit}` : "â€”";
    const formatUptime = seconds => {
        if (seconds === null || seconds === undefined) return "Unavailable";
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${days}d ${hours}h ${minutes}m`;
    };
    const set = (selector, value) => { const node = $(selector); if (node) node.textContent = value; };
    const setBar = (selector, value) => { $(selector)?.style.setProperty("--value", `${Math.min(100, value)}%`); };

    function drawChart() {
        const canvas = $("[data-chart]");
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.max(1, rect.width * ratio);
        canvas.height = Math.max(1, rect.height * ratio);
        const ctx = canvas.getContext("2d");
        ctx.scale(ratio, ratio);
        const width = rect.width, height = rect.height, pad = 16;
        ctx.clearRect(0, 0, width, height);
        ctx.strokeStyle = "rgba(255,255,255,.055)";
        ctx.lineWidth = 1;
        [0,25,50,75,100].forEach(value => {
            const y = pad + (height - pad * 2) * (1 - value / 100);
            ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(width - pad, y); ctx.stroke();
        });
        [["cpu","#36e0d0"],["memory","#ffad55"],["disk","#9e7bff"]].forEach(([key,color]) => {
            const values = history[key];
            if (values.length < 2) return;
            ctx.beginPath();
            values.forEach((value,index) => {
                const x = pad + (width - pad * 2) * index / 19;
                const y = pad + (height - pad * 2) * (1 - value / 100);
                index ? ctx.lineTo(x,y) : ctx.moveTo(x,y);
            });
            ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = "round"; ctx.stroke();
        });
    }

    function update(data) {
        set("[data-health]", data.health.status); set("[data-score-text]", data.health.score);
        $("[data-score]")?.style.setProperty("--score", data.health.score);
        set("[data-cpu]", data.cpu.percent); set("[data-cores]", data.cpu.cores); setBar("[data-cpu-bar]", data.cpu.percent);
        set("[data-memory]", data.memory.percent); set("[data-memory-used]", formatSize(data.memory.used)); setBar("[data-memory-bar]", data.memory.percent);
        set("[data-disk]", data.disk.percent); set("[data-disk-free]", formatSize(data.disk.free)); setBar("[data-disk-bar]", data.disk.percent);
        set("[data-host]", data.system.hostname); set("[data-os]", `${data.system.os} ${data.system.kernel}`); set("[data-arch]", data.system.architecture); set("[data-uptime]", formatUptime(data.system.uptime)); set("[data-https]", data.request.https ? "Secured" : "Not secured");
        set("[data-cpu-model]", data.cpu.model); set("[data-core-detail]", data.cpu.cores); set("[data-load1]", data.cpu.load[0]); set("[data-load5]", data.cpu.load[1]); set("[data-load15]", data.cpu.load[2]);
        set("[data-mem-used-detail]", formatSize(data.memory.used)); set("[data-mem-total]", formatSize(data.memory.total)); set("[data-process]", formatSize(data.memory.process)); set("[data-peak]", formatSize(data.memory.peak));
        set("[data-disk-used]", formatSize(data.disk.used)); set("[data-disk-free-detail]", formatSize(data.disk.free)); set("[data-disk-total]", formatSize(data.disk.total));
        set("[data-php]", data.php.version); set("[data-sapi]", data.php.sapi); set("[data-extensions]", data.php.extensions); set("[data-opcache]", data.php.opcache ? "Enabled" : "Disabled");
        set("[data-limit]", data.php.memoryLimit); set("[data-execution]", data.php.maxExecutionTime ? `${data.php.maxExecutionTime}s` : "Unlimited"); set("[data-upload]", data.php.uploadLimit);
        set("[data-software]", data.system.serverSoftware); set("[data-protocol]", data.request.protocol); set("[data-views]", data.request.sessionViews); set("[data-timezone]", data.system.timezone);
        set("[data-side-status]", data.health.status); set("[data-updated]", new Date(data.generatedAt).toLocaleTimeString());
        ["cpu","memory","disk"].forEach(key => { history[key].push(data[key].percent); if (history[key].length > 20) history[key].shift(); });
        drawChart();
    }

    function renderAdmin() {
        set("[data-pen-count]", `${admin.pens.length} PENS`);
        const list = $("[data-pen-list]");
        list.replaceChildren(...admin.pens.map(pen => {
            const button = document.createElement("button");
            button.className = `admin-row${selectedPen?.id === pen.id ? " active" : ""}`;
            button.innerHTML = `<strong>${escapeHtml(pen.id)}</strong><small>${escapeHtml(pen.language)} Â· ${pen.views.toLocaleString()} views Â· ${(pen.bytes / 1024).toFixed(1)} KB</small>`;
            button.addEventListener("click", () => selectPen(pen.id));
            return button;
        }));
        if (!admin.pens.length) list.innerHTML = '<div class="metric-sub" style="padding:18px">No pen JSON files found.</div>';

        const reviews = admin.reviews || [];
        const pendingReviewCount = reviews.filter(review => review.status === "pending").length;
        set("[data-review-total]", reviews.length);
        set("[data-review-pending]", pendingReviewCount);
        set("[data-review-approved]", reviews.filter(review => review.status === "approved").length);
        set("[data-review-badge]", pendingReviewCount > 99 ? "99+" : pendingReviewCount);
        $("[data-review-badge]").hidden = pendingReviewCount === 0;
        const reviewList = $("[data-review-list]");
        reviewList.replaceChildren(...reviews.map(review => {
            const item = document.createElement("article");
            item.className = "review-admin-item";

            const top = document.createElement("div");
            top.className = "review-admin-head";
            const identity = document.createElement("div");
            const name = document.createElement("strong");
            name.textContent = review.name || "Anonymous";
            const contact = document.createElement("small");
            contact.textContent = review.contact || "No contact supplied";
            identity.append(name, contact);
            const status = document.createElement("span");
            status.className = `chip review-status-${review.status}`;
            status.textContent = String(review.status || "pending").toUpperCase();
            top.append(identity, status);

            const stars = document.createElement("div");
            stars.className = "review-admin-stars";
            stars.textContent = `${"★".repeat(Number(review.rating) || 0)}${"☆".repeat(5 - (Number(review.rating) || 0))}`;
            const copy = document.createElement("p");
            copy.textContent = review.review || "";
            const date = document.createElement("small");
            date.textContent = review.createdAt ? new Date(review.createdAt).toLocaleString() : "Unknown date";

            const actions = document.createElement("div");
            actions.className = "admin-actions";
            const toggle = document.createElement("button");
            toggle.className = "admin-btn primary";
            toggle.textContent = review.status === "approved" ? "Unpublish" : "Approve & publish";
            toggle.addEventListener("click", () => moderateReview(review.id, review.status === "approved" ? "pending" : "approved"));
            const remove = document.createElement("button");
            remove.className = "admin-btn danger";
            remove.textContent = "Delete";
            remove.addEventListener("click", () => removeReview(review.id));
            actions.append(remove, toggle);
            item.append(top, stars, copy, date, actions);
            return item;
        }));
        if (!reviews.length) reviewList.innerHTML = '<div class="metric-sub">No reviews have been submitted yet.</div>';

        const visitors = admin.siteInfo?.visitors || {};
        set("[data-total-unique]", Number(visitors.totalUnique || 0).toLocaleString());
        set("[data-today-unique]", Number(visitors.today?.unique || 0).toLocaleString());
        set("[data-country-count]", Object.keys(visitors.today?.countries || {}).length);
        set("[data-last-visit]", visitors.lastVisit ? new Date(visitors.lastVisit).toLocaleString() : "â€”");
        $("[data-site-info]").value = JSON.stringify(admin.siteInfo || {}, null, 2);

        const github = admin.github || {};
        set("[data-repos]", github.repoCount || 0); set("[data-stars]", github.stars || 0); set("[data-forks]", github.forks || 0); set("[data-latest]", github.latestRepo || "â€”");
        set("[data-github-file]", admin.githubPathFound || "NOT FOUND");
        const repos = $("[data-repo-list]");
        repos.replaceChildren(...(github.repositories || []).map(repo => {
            const row = document.createElement("div"); row.className = "repo";
            const link = document.createElement("a"); link.href = repo.url || "#"; link.target = "_blank"; link.rel = "noopener"; link.textContent = repo.name || "Unnamed repository";
            const description = document.createElement("p"); description.textContent = repo.description || "No description.";
            const meta = document.createElement("small"); meta.textContent = `${repo.language || "Unknown"} Â· ${repo.stars || 0} stars Â· ${repo.forks || 0} forks`;
            row.append(link, description, meta); return row;
        }));
        if (!github.repositories?.length) repos.innerHTML = '<div class="metric-sub">No GitHub cache was found.</div>';
        $("[data-password-warning]").hidden = !admin.mustChangePassword;
    }

    function escapeHtml(value) {
        const node = document.createElement("span"); node.textContent = String(value ?? ""); return node.innerHTML;
    }

    function selectPen(id) {
        selectedPen = admin.pens.find(pen => pen.id === id) || null;
        $("[data-pen-empty]").hidden = !!selectedPen;
        $("[data-pen-editor]").hidden = !selectedPen;
        if (!selectedPen) return;
        set("[data-pen-heading]", `./${selectedPen.id}`);
        $("[data-pen-language]").value = selectedPen.language;
        $("[data-pen-content]").value = selectedPen.content;
        $("[data-pen-open]").href = `/pen/${encodeURIComponent(selectedPen.id)}`;
        renderAdmin();
    }

    async function adminAction(action, extra = {}) {
        const response = await fetch("?api=admin", {
            method:"POST", headers:{"Content-Type":"application/json"}, cache:"no-store",
            body:JSON.stringify({ action, csrf:admin.csrf, ...extra })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Administration action failed.");
        return result;
    }

    async function loadAdmin() {
        const response = await fetch("?api=admin", { cache:"no-store" });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Could not load administration data.");
        admin = result; renderAdmin();
    }

    async function moderateReview(id, status) {
        try {
            await adminAction("setReviewStatus", { id, status });
            await loadAdmin();
        } catch (error) { alert(error.message); }
    }

    async function removeReview(id) {
        if (!confirm("Delete this review permanently?")) return;
        try {
            await adminAction("deleteReview", { id });
            await loadAdmin();
        } catch (error) { alert(error.message); }
    }

    async function refresh() {
        try {
            const response = await fetch("?api=stats", { cache:"no-store" });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            update(await response.json());
            $("[data-error]").hidden = true;
            $("[data-loader]").classList.add("hidden");
        } catch (error) {
            const banner = $("[data-error]");
            banner.textContent = `Live statistics unavailable: ${error.message}`;
            banner.hidden = false;
            set("[data-side-status]", "Unavailable");
            $("[data-loader]").classList.add("hidden");
        }
    }

    document.querySelectorAll("[data-view]").forEach(button => button.addEventListener("click", () => {
        document.querySelectorAll("[data-view]").forEach(item => item.classList.toggle("active", item === button));
        document.querySelectorAll("[data-panel]").forEach(panel => panel.hidden = panel.dataset.panel !== button.dataset.view);
        set("[data-title]", {overview:"Command overview",system:"System information",runtime:"PHP runtime",pens:"Pen control",reviews:"Review moderation",visitors:"Site information",github:"GitHub cache",chat:"Embedded chat",pen:"Embedded pen",account:"Administrator account"}[button.dataset.view]);
        const activePanel = document.querySelector(`[data-panel="${button.dataset.view}"]`);
        const frame = activePanel?.querySelector("iframe[data-embed-src]");
        if (frame && !frame.src) frame.src = frame.dataset.embedSrc;
        requestAnimationFrame(drawChart);
    }));
    $("[data-save-pen]").addEventListener("click", async () => {
        if (!selectedPen) return;
        try {
            await adminAction("savePen", { id:selectedPen.id, language:$("[data-pen-language]").value, content:$("[data-pen-content]").value });
            await loadAdmin(); selectPen(selectedPen.id);
        } catch (error) { alert(error.message); }
    });
    $("[data-delete-pen]").addEventListener("click", async () => {
        if (!selectedPen || !confirm(`Delete pen ${selectedPen.id}? This cannot be undone.`)) return;
        try { await adminAction("deletePen", { id:selectedPen.id }); selectedPen = null; await loadAdmin(); } catch (error) { alert(error.message); }
    });
    $("[data-save-site]").addEventListener("click", async () => {
        try {
            const siteInfo = JSON.parse($("[data-site-info]").value);
            await adminAction("saveSiteInfo", { siteInfo }); await loadAdmin();
        } catch (error) { alert(error instanceof SyntaxError ? "siteInfo.json contains invalid JSON." : error.message); }
    });
    $("[data-change-password]").addEventListener("click", async () => {
        try {
            await adminAction("changePassword", { password:$("[data-new-password]").value });
            $("[data-new-password]").value = ""; await loadAdmin(); alert("Password updated.");
        } catch (error) { alert(error.message); }
    });
    window.addEventListener("resize", drawChart);
    Promise.all([refresh(), loadAdmin()]).catch(error => {
        const banner = $("[data-error]"); banner.textContent = error.message; banner.hidden = false;
    });
    window.setInterval(refresh, 5000);
})();
