(function () {
    /**
     * @param {string} listId       
     * @param {string} searchId     
     * @param {string} sortId      
     * @param {string} emptyMsgId  
     * @param {string} countId     
     */
    function setupFilmList(listId, searchId, sortId, emptyMsgId, countId) {
        const list      = document.getElementById(listId);
        const searchEl  = document.getElementById(searchId);
        const sortEl    = document.getElementById(sortId);
        const emptyMsg  = document.getElementById(emptyMsgId);
        const countEl   = document.getElementById(countId);

        if (!list || !searchEl || !sortEl) return;

        function getItems() {
            return Array.from(list.querySelectorAll('.film-item'));
        }

        function applyFilterSort() {
            const query = searchEl.value.trim().toLowerCase();
            const sort  = sortEl.value;

            let items = getItems();

            // Filtrer
            items.forEach(item => {
                const title = (item.dataset.title || '').toLowerCase();
                if (title.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });

            // Triage de la recherche
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

            // Ajouter dans l'odre
            visible.forEach(item => list.appendChild(item));

            // MaJ du compteur
            if (countEl) {
                countEl.textContent = visible.length + ' film' + (visible.length !== 1 ? 's' : '');
            }
            if (emptyMsg) {
                emptyMsg.style.display = visible.length === 0 ? '' : 'none';
            }
        }

        searchEl.addEventListener('input', applyFilterSort);
        sortEl.addEventListener('change', applyFilterSort);

        // Init count display
        applyFilterSort();
    }

    setupFilmList('watchlist-list', 'search-watchlist', 'sort-watchlist', 'watchlist-empty-msg', 'watchlist-visible-count');
    setupFilmList('watched-list',   'search-watched',   'sort-watched',   'watched-empty-msg',   'watched-visible-count');
})();