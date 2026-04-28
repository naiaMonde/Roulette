document.addEventListener('DOMContentLoaded', function () {

    const resultDiv = document.getElementById('resultat-film');
    if (!resultDiv) return;

    const buttons = [
        { id: 'btn-random',  action: 'random'  },
        { id: 'btn-commune', action: 'commune' },
        { id: 'btn-absent',  action: 'absent'  },
    ];

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-vu');
        if (!btn) return;

        const title   = btn.dataset.title;
        const url     = btn.dataset.url;
        const present = Array.from(document.querySelectorAll('.present-hidden')).map(el => el.value);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

        const formData = new FormData();
        formData.append('ajax_action', 'marquer_vu');
        formData.append('title', title);
        formData.append('url', url);
        present.forEach(p => formData.append('present[]', p));

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.outerHTML = '<div class="alert alert-success mt-2 py-2 px-3 d-inline-block">'
                        + '<i class="bi bi-check-circle me-2"></i>' + data.message + '</div>';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>On l\'a vu ';
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>On l\'a vu ';
            });
    });

    buttons.forEach(function({ id, action }) {
        const btn = document.getElementById(id);
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            // Lecture du filtre durée (radio)
            const dureeSelected = document.querySelector('input[name="duree"]:checked');
            const duree = dureeSelected ? dureeSelected.value : 'aucun';
            const court = (duree === 'court');
            const long  = (duree === 'long');

            const present = Array.from(document.querySelectorAll('.present-hidden')).map(function(el) { return el.value; });
            const gens    = Array.from(document.querySelectorAll('.gens-hidden')).map(function(el) { return el.value; });

            if (present.length === 0) {
                alert('Choisis au moins une personne !');
                return;
            }

            resultDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-warning" role="status"></div></div>';

            const formData = new FormData();
            formData.append('ajax_action', action);
            present.forEach(function(p) { formData.append('present[]', p); });
            gens.forEach(function(g)    { formData.append('gens[]', g); });
            formData.append('court', court ? 'true' : 'false');
            formData.append('long',  long  ? 'true' : 'false');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
            })
            .then(function(res) {
                if (!res.ok) throw new Error('Erreur HTTP ' + res.status);
                return res.text();
            })
            .then(function(html) {
                resultDiv.innerHTML = html;
            })
            .catch(function(err) {
                resultDiv.innerHTML = '<div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle me-2"></i>Erreur : ' + err.message + '</div>';
            });
        });
    });
});