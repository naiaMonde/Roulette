function setupFilmList(listId, searchId, sortId, emptyMsgId, countId) {
    const list      = document.getElementById(listId);
    const searchEl  = document.getElementById(searchId);
    const sortEl    = document.getElementById(sortId);
    const emptyMsg  = document.getElementById(emptyMsgId);
    const countEl   = document.getElementById(countId);

    if (!list || !searchEl || !sortEl) return null;

    function getItems() {
        return Array.from(list.querySelectorAll('.film-item'));
    }

    function applyFilterSort() {
        const query = searchEl.value.trim().toLowerCase();
        const sort  = sortEl.value;

        let items = getItems();

        items.forEach(item => {
            const title = (item.dataset.title || '').toLowerCase();
            if (title.includes(query)) {
                item.style.display = '';
            } else {
                item.style.setProperty('display', 'none', 'important');
            }
        });

        const visible = items.filter(i => i.style.display !== 'none');

        visible.sort((a, b) => {
            switch (sort) {
                case 'alpha-asc':
                    return a.dataset.title.localeCompare(b.dataset.title, 'fr');
                case 'alpha-desc':
                    return b.dataset.title.localeCompare(a.dataset.title, 'fr');
                case 'year-asc':
                    return (parseInt(a.dataset.year) || 0) - (parseInt(b.dataset.year) || 0);
                case 'year-desc':
                    return (parseInt(b.dataset.year) || 0) - (parseInt(a.dataset.year) || 0);
                case 'date-asc':
                    return (a.dataset.date || '').localeCompare(b.dataset.date || '');
                case 'date-desc':
                default:
                    return (b.dataset.date || '').localeCompare(a.dataset.date || '');
            }
        });

        visible.forEach(item => list.appendChild(item));

        if (countEl) {
            countEl.textContent = visible.length + ' film' + (visible.length !== 1 ? 's' : '');
        }
        if (emptyMsg) {
            emptyMsg.style.display = visible.length === 0 ? '' : 'none';
        }
    }

    searchEl.addEventListener('input', applyFilterSort);
    sortEl.addEventListener('change', applyFilterSort);

    applyFilterSort();
    return applyFilterSort;
}

const refreshWatchlist = setupFilmList('watchlist-list', 'search-watchlist', 'sort-watchlist', 'watchlist-empty-msg', 'watchlist-visible-count');
const refreshWatched   = setupFilmList('watched-list',   'search-watched',   'sort-watched',   'watched-empty-msg',   'watched-visible-count');

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-vu');
    if (!btn) return;

    const title = btn.dataset.title;
    const url   = btn.dataset.url || '';

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    const formData = new FormData();
    formData.append('ajax_action', 'marquer_vu');
    formData.append('title', title);
    formData.append('url', url);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const li        = btn.closest('li');
                const filmYear  = li ? (li.dataset.year || '') : '';
                const today     = new Date().toISOString().slice(0, 10);

                if (li) li.remove();

                const watchedList = document.getElementById('watched-list');
                if (watchedList) {
                    const newLi = document.createElement('li');
                    newLi.className = 'list-group-item d-flex justify-content-between align-items-center px-0 film-item';
                    newLi.dataset.title = title.toLowerCase();
                    newLi.dataset.year  = filmYear;
                    newLi.dataset.date  = today;

                    const titleSpan = document.createElement('span');
                    titleSpan.textContent = title;
                    const yearSpan = document.createElement('span');
                    yearSpan.className = 'text-muted small ms-2';
                    yearSpan.textContent = filmYear;
                    const textDiv = document.createElement('div');
                    textDiv.appendChild(titleSpan);
                    if (filmYear) textDiv.appendChild(yearSpan);

                    const link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.className = 'btn btn-sm btn-outline-secondary';
                    link.innerHTML = '<i class="bi bi-box-arrow-up-right"></i>';

                    newLi.appendChild(textDiv);
                    newLi.appendChild(link);
                    watchedList.prepend(newLi);
                }

                const noFilmsMsg = document.getElementById('watched-no-films-msg');
                if (noFilmsMsg) noFilmsMsg.style.display = 'none';

                const wlBadge = document.getElementById('watchlist-badge-count');
                if (wlBadge) wlBadge.textContent = Math.max(0, parseInt(wlBadge.textContent) - 1);
                const wdBadge = document.getElementById('watched-badge-count');
                if (wdBadge) wdBadge.textContent = parseInt(wdBadge.textContent) + 1;

                if (refreshWatchlist) refreshWatchlist();
                if (refreshWatched)   refreshWatched();
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Vu';
                alert('Erreur : ' + data.message);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Vu';
        });
});