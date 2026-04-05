        document.addEventListener('DOMContentLoaded', function() {
            // On cible les 3 boutons de la roulette
            const buttons = document.querySelectorAll('button[name="action"]');
            const resultDiv = document.getElementById('resultat-film');

            buttons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const court = document.getElementById('court').checked;

                    // Récupérer les participants cochés
                    const present = Array.from(document.querySelectorAll('input[name="present[]"]:checked')).map(cb => cb.value);
                    // Récupérer tous les participants possible
                    const gens = Array.from(document.querySelectorAll('input[name="present[]"]')).map(cb => cb.value);

                    if (present.length === 0) return alert("Choisis au moins une personne !");

                    // Animation d'attente omg 
                    resultDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-warning"></div></div>';

                    // Envoi des données vers le même fichier PHP
                    const formData = new FormData();
                    formData.append('ajax_action', this.value); // On utilise ajax_action pour que le PHP nous reconnaisse trop smart :D
                    present.forEach(p => formData.append('present[]', p));
                    gens.forEach(g => formData.append('gens[]', g));

                    formData.append('court', court);
                    fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.text())
                        .then(html => {
                            resultDiv.innerHTML = html;
                        });
                });
            });
        });