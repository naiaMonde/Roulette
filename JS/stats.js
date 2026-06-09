document.addEventListener('DOMContentLoaded', () => {
    const checkboxes  = document.querySelectorAll('.participant-check');
    const statsCard   = document.getElementById('stats-card');
    let debounceTimer = null;

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadStats, 150);
        });
    });

    function getPresent() {
        return [...checkboxes].filter(cb => cb.checked).map(cb => cb.value);
    }

    function loadStats() {
        const present = getPresent();
        if (present.length === 0) {
            statsCard.innerHTML = '';
            return;
        }

        statsCard.innerHTML = `
            <div class="card p-5 mb-4 text-center">
                <div class="spinner-border mx-auto" role="status" style="color:var(--accent-color)"></div>
                <div class="text-muted mt-2 small">Calcul des stats…</div>
            </div>`;

        const body = new URLSearchParams();
        body.append('ajax_action', 'stats');
        present.forEach(p => body.append('present[]', p));

        fetch('?controleur=stats', { method: 'POST', body })
            .then(r => r.json())
            .then(renderStats)
            .catch(() => {
                statsCard.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des stats.</div>`;
            });
    }

    function renderStats(data) {
        if (data.error) {
            statsCard.innerHTML = `<div class="alert alert-warning">${data.error}</div>`;
            return;
        }

        statsCard.innerHTML = `
            <div class="card mb-4 overflow-hidden">
                <div class="stats-tabs-header">
                    <button class="stats-tab-btn active" data-tab="commun">Watchlists</button>
                    <button class="stats-tab-btn" data-tab="vus">Films vus</button>
                    <button class="stats-tab-btn" data-tab="decades">Décennies</button>
                    <button class="stats-tab-btn" data-tab="recap">Récap</button>
                </div>
                <div class="p-4" id="stats-tab-content">
                    <div id="tab-commun">${renderCommunTab(data)}</div>
                    <div id="tab-vus"    class="d-none">${renderVusTab(data)}</div>
                    <div id="tab-decades" class="d-none">${renderDecadesTab(data)}</div>
                    <div id="tab-recap"   class="d-none">${renderRecapTab(data)}</div>
                </div>
            </div>`;

        statsCard.querySelectorAll('.stats-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                statsCard.querySelectorAll('.stats-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                statsCard.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('d-none'));
                document.getElementById('tab-' + btn.dataset.tab).classList.remove('d-none');
            });
        });
    }

    function renderCommunTab(data) {
        const pct         = data.pourcentage;
        const barWidth    = Math.min(pct, 100);
        const commonFilms = data.films.filter(f => f.count >= 2);

        let html = `
            <div class="text-center mb-4 pb-4 border-bottom">
                <div class="stats-big-number">${pct}%</div>
                <div class="text-muted mt-1">de films en commun</div>
                <div class="text-muted small">${data.filmsEnCommun} film${data.filmsEnCommun > 1 ? 's' : ''} partagés · ${data.totalUniqueFilms} films uniques au total</div>
                <div class="progress mt-3 mx-auto" style="height:8px;max-width:300px;">
                    <div class="progress-bar" role="progressbar"
                         style="width:${barWidth}%;background-color:var(--accent-color);">
                    </div>
                </div>
            </div>`;

        if (commonFilms.length === 0) {
            html += `<div class="text-center text-muted py-3">
                <i class="bi bi-emoji-frown fs-3 d-block mb-2"></i>
                Aucun film en commun pour l'instant
            </div>`;
        } else {
            html += `<div class="list-group list-group-flush stats-film-list">`;
            commonFilms.forEach(f => {
                const dots = buildDots(f.count, f.total);
                html += `
                    <a href="${escHtml(f.url)}" target="_blank" rel="noopener"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span class="film-title-stat">${escHtml(f.title)}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="dots-indicator">${dots}</span>
                            <span class="badge rounded-pill stat-badge">${f.count}/${f.total}</span>
                        </div>
                    </a>`;
            });
            html += `</div>`;
        }

        return html;
    }

    function renderVusTab(data) {
        const pct         = data.pourcentageWatched;
        const barWidth    = Math.min(pct, 100);
        const commonFilms = data.watchedFilms.filter(f => f.count >= 2);

        let html = `
            <div class="text-center mb-4 pb-4 border-bottom">
                <div class="stats-big-number">${pct}%</div>
                <div class="text-muted mt-1">de films vus en commun</div>
                <div class="text-muted small">${data.watchedEnCommun} film${data.watchedEnCommun > 1 ? 's' : ''} en commun · ${data.totalUniqueWatched} films vus au total</div>
                <div class="progress mt-3 mx-auto" style="height:8px;max-width:300px;">
                    <div class="progress-bar" role="progressbar"
                         style="width:${barWidth}%;background-color:#5d6d31;">
                    </div>
                </div>
            </div>`;

        if (commonFilms.length === 0) {
            html += `<div class="text-center text-muted py-3">
                <i class="bi bi-emoji-frown fs-3 d-block mb-2"></i>
                Aucun film vu en commun
            </div>`;
        } else {
            html += `<div class="list-group list-group-flush stats-film-list">`;
            commonFilms.forEach(f => {
                const dots = buildDots(f.count, f.total, '#5d6d31');
                html += `
                    <a href="${escHtml(f.url)}" target="_blank" rel="noopener"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span class="film-title-stat">${escHtml(f.title)}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="dots-indicator">${dots}</span>
                            <span class="badge rounded-pill stat-badge-vus">${f.count}/${f.total}</span>
                        </div>
                    </a>`;
            });
            html += `</div>`;
        }

        return html;
    }

    const USER_COLORS = ['#e67e22','#5d6d31','#2980b9','#8e44ad','#c0392b','#16a085'];

    function renderDecadesTab(data) {
        const dpu   = data.decadesPerUser || {};
        const users = Object.keys(dpu);
        if (users.length === 0) {
            return `<div class="text-center text-muted py-3">Aucune donnée disponible</div>`;
        }

        // All decades present across everyone
        const allDecades = [...new Set(
            users.flatMap(u => [
                ...Object.keys(dpu[u].watchlist || {}),
                ...Object.keys(dpu[u].watched   || {}),
            ])
        )].map(Number).sort((a, b) => b - a);

        // Global max for scale
        const allCounts = users.flatMap(u => [
            ...Object.values(dpu[u].watchlist || {}),
            ...Object.values(dpu[u].watched   || {}),
        ]);
        const maxCount = allCounts.length ? Math.max(...allCounts) : 1;

        // Legend
        let legend = `<div class="d-flex gap-3 flex-wrap justify-content-center mb-4 small fw-semibold">`;
        users.forEach((u, i) => {
            legend += `<span><span class="decade-legend" style="background:${USER_COLORS[i % USER_COLORS.length]}"></span>${escHtml(u)}</span>`;
        });
        legend += `</div>`;

        // Toggle
        const toggleHtml = `
            <div class="d-flex justify-content-center mb-4">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary decade-toggle active" data-mode="watchlist">À voir</button>
                    <button class="btn btn-outline-secondary decade-toggle" data-mode="watched">Vus</button>
                </div>
            </div>`;

        let html = legend + toggleHtml + `<div id="decades-bars" class="d-flex flex-column gap-4">`;
        html += buildDecadeBars(allDecades, users, dpu, 'watchlist', maxCount);
        html += `</div>`;

        // Bind toggle after insertion via event delegation (handled below)
        setTimeout(() => {
            document.querySelectorAll('.decade-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.decade-toggle').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    const mode = btn.dataset.mode;
                    document.getElementById('decades-bars').innerHTML =
                        buildDecadeBars(allDecades, users, dpu, mode, maxCount);
                });
            });
        }, 0);

        return html;
    }

    function buildDecadeBars(allDecades, users, dpu, mode, maxCount) {
        let html = '';
        allDecades.forEach(decade => {
            const hasAny = users.some(u => (dpu[u][mode] || {})[decade] > 0);
            if (!hasAny) return;

            html += `<div>
                <div class="decade-label mb-1">${decade}s</div>
                <div class="d-flex flex-column gap-1">`;

            users.forEach((u, i) => {
                const count      = (dpu[u][mode] || {})[decade] || 0;
                const pct        = Math.round(count / maxCount * 100);
                const color      = USER_COLORS[i % USER_COLORS.length];
                const barInner   = count > 0
                    ? `<div class="progress-bar" style="width:${Math.max(pct, 2)}%;background-color:${color};border-radius:4px;"></div>`
                    : `<div class="progress-bar" style="width:100%;background-color:#f0ece6;border-radius:4px;"></div>`;
                const countLabel = `<div style="width:28px;font-size:.72rem;text-align:left;color:${count > 0 ? color : '#bbb'};font-weight:600">${count}</div>`;
                html += `
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:52px;font-size:.75rem;color:#888;text-align:right">${escHtml(u)}</div>
                        <div class="flex-grow-1">
                            <div class="progress" style="height:16px;border-radius:4px;">${barInner}</div>
                        </div>
                        ${countLabel}
                    </div>`;
            });

            html += `</div></div>`;
        });
        return html;
    }

    function renderRecapTab(data) {
        let html = `<div class="row g-3">`;
        data.userSummary.forEach(u => {
            html += `
                <div class="col-sm-6">
                    <div class="recap-card">
                        <div class="recap-name"><i class="bi bi-person-circle me-2"></i>${escHtml(u.user)}</div>
                        <div class="recap-stats">
                            <div><i class="bi bi-bookmark me-1" style="color:var(--accent-color)"></i>
                                <strong>${u.watchlist}</strong> à voir
                            </div>
                            <div><i class="bi bi-check2-circle me-1" style="color:#5d6d31"></i>
                                <strong>${u.watched}</strong> vus
                            </div>
                        </div>
                    </div>
                </div>`;
        });
        html += `</div>`;
        return html;
    }

    function buildDots(count, total, color = null) {
        const filledClass = color ? 'dot' : 'dot dot-filled';
        const filledStyle = color ? `style="color:${color}"` : '';
        let s = '';
        for (let i = 0; i < total; i++) {
            s += i < count
                ? `<span class="${filledClass}" ${filledStyle}>●</span>`
                : `<span class="dot dot-empty">○</span>`;
        }
        return s;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
