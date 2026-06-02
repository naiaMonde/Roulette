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
                    <button class="stats-tab-btn active" data-tab="commun">Films en commun</button>
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
                            <span class="badge rounded-pill" style="background-color:#5d6d31">${f.count}/${f.total}</span>
                        </div>
                    </a>`;
            });
            html += `</div>`;
        }

        return html;
    }

    function renderDecadesTab(data) {
        const hasWl = data.decades        && Object.keys(data.decades).length > 0;
        const hasWd = data.decadesWatched && Object.keys(data.decadesWatched).length > 0;

        if (!hasWl && !hasWd) {
            return `<div class="text-center text-muted py-3">Aucune donnée disponible</div>`;
        }

        // Merge all decade keys
        const allDecades = [...new Set([
            ...Object.keys(data.decades        || {}),
            ...Object.keys(data.decadesWatched || {}),
        ])].map(Number).sort((a, b) => b - a);

        const maxCount = Math.max(
            ...Object.values(data.decades        || {0: 0}),
            ...Object.values(data.decadesWatched || {0: 0}),
        );

        let html = `
            <div class="d-flex gap-4 justify-content-center mb-4 small fw-semibold">
                <span><span class="decade-legend" style="background:var(--accent-color)"></span>À voir</span>
                <span><span class="decade-legend" style="background:#5d6d31"></span>Vus</span>
            </div>
            <div class="d-flex flex-column gap-3">`;

        allDecades.forEach(decade => {
            const wl  = (data.decades        || {})[decade] || 0;
            const wd  = (data.decadesWatched || {})[decade] || 0;
            const pWl = Math.round(wl / maxCount * 100);
            const pWd = Math.round(wd / maxCount * 100);

            html += `
                <div class="d-flex align-items-center gap-3">
                    <div class="decade-label">${decade}s</div>
                    <div class="flex-grow-1 d-flex flex-column gap-1">
                        ${wl > 0 ? `
                        <div class="progress" style="height:14px;border-radius:4px;">
                            <div class="progress-bar" style="width:${pWl}%;background-color:var(--accent-color);border-radius:4px;font-size:.72rem;line-height:14px;">
                                <span class="ps-1">${wl}</span>
                            </div>
                        </div>` : ''}
                        ${wd > 0 ? `
                        <div class="progress" style="height:14px;border-radius:4px;">
                            <div class="progress-bar" style="width:${pWd}%;background-color:#5d6d31;border-radius:4px;font-size:.72rem;line-height:14px;">
                                <span class="ps-1">${wd}</span>
                            </div>
                        </div>` : ''}
                    </div>
                </div>`;
        });

        html += `</div>`;
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
        const style = color ? `style="color:${color}"` : '';
        let s = '';
        for (let i = 0; i < total; i++) {
            s += i < count
                ? `<span class="dot" ${style}>●</span>`
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
