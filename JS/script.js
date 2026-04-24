document.addEventListener('DOMContentLoaded', function () {

    const resultDiv = document.getElementById('resultat-film');
    if (!resultDiv) return;

    const buttons = [
        { id: 'btn-random',  action: 'random'  },
        { id: 'btn-commune', action: 'commune' },
        { id: 'btn-absent',  action: 'absent'  },
    ];

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