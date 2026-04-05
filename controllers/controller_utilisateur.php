<?php

class ControllerUtilisateur extends Controller
{

    public function index(): void
    {
        // Réponse AJAX pour la roulette — on répond et on coupe
        if (isset($_POST['ajax_action'])) {
            $this->ajaxFilm();
            exit;
        }

        // Upload de CSV
        $uploadMessages = [];
        if (isset($_POST['update_action'])) {
            $uploadMessages = $this->handleUpload();
        }

        // Liste de tous les utilisateurs disponibles
        $users = $this->getUsers();

        // Participants cochés (depuis le POST précédent)
        $present = $_POST['present'] ?? [];

        echo $this->getTwig()->render('roulette.html.twig', [
            'users'           => $users,
            'present'         => $present,
            'upload_messages' => $uploadMessages,
        ]);
    }

    private function ajaxFilm(): void
    {
        $present = $_POST['present'] ?? [];
        $gens    = $_POST['gens']    ?? [];
        $absent  = array_values(array_diff($gens, $present));
        $court   = isset($_POST['court']) && $_POST['court'] === 'true';
        $long   = isset($_POST['long']) && $_POST['long'] === 'true';

        $profilPresent = $this->importerProfils($present);
        $profilAbsent  = $this->importerProfils($absent);

        // Choisir la liste selon l'action demandée
        switch ($_POST['ajax_action']) {
            case 'commune':
                $filmsPossibles = $this->watchlistCommune($profilPresent);
                break;
            case 'absent':
                $filmsPossibles = $this->dejaVu($profilPresent, $profilAbsent);
                break;
            default: // 'random'
                $filmsPossibles = $this->filmsPossibles($profilPresent);
        }

        // Filtre durée < 2h
        $film = null;
        if ($court) {
            shuffle($filmsPossibles);
            foreach ($filmsPossibles as $f) {
                $data = $this->fetchOMDb($f['title']);
                if ($data && $data['Response'] === 'True') {
                    $runtime = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);
                    if ($runtime > 0 && $runtime <= 120) {
                        $film = $this->buildFilmData($f, $data);
                        break;
                    }
                }
            }
        }
        if ($long) {
            shuffle($filmsPossibles);
            foreach ($filmsPossibles as $f) {
                $data = $this->fetchOMDb($f['title']);
                if ($data && $data['Response'] === 'True') {
                    $runtime = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);
                    if ($runtime >= 120) {
                        $film = $this->buildFilmData($f, $data);
                        break;
                    }
                }
            }
        } 
        else {
            if (!empty($filmsPossibles)) {
                $f    = $filmsPossibles[array_rand($filmsPossibles)];
                $data = $this->fetchOMDb($f['title']);
                if ($data && $data['Response'] === 'True') {
                    $film = $this->buildFilmData($f, $data);
                }
            }
        }

        echo $this->getTwig()->render('film_result.html.twig', [
            'film'  => $film,
            'court' => $court,
            'long' => $long,
        ]);
    }

    private function buildFilmData(array $f, array $data): array
    {
        $runtimeMinutes = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);
        $hours   = intdiv($runtimeMinutes, 60);
        $minutes = $runtimeMinutes % 60;

        return [
            'title'         => $data['Title'],
            'year'          => $data['Year'],
            'poster'        => $data['Poster'],
            'duration'      => "{$hours}h {$minutes}min",
            'genre'         => $data['Genre'],
            'plot'          => $data['Plot'],
            'letterboxd_url'=> $f['url'],
        ];
    }

    private function handleUpload(): array
    {
        $user     = $_POST['update_user'] ?? '';
        $basePath = "Data/{$user}/";
        $messages = [];

        if (!empty($_FILES['watched']['name'])) {
            $messages[] = $this->moveUploadedCsv('watched', $basePath . 'watched.csv');
        }
        if (!empty($_FILES['watchlist']['name'])) {
            $messages[] = $this->moveUploadedCsv('watchlist', $basePath . 'watchlist.csv');
        }

        return $messages;
    }

    private function moveUploadedCsv(string $key, string $dest): string
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            return "Aucun fichier pour {$key}.";
        }

        $filename = $_FILES[$key]['name'];
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            return "{$key} doit être un fichier CSV.";
        }

        if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) {
            return "Échec de l'upload de {$key}.";
        }

        return "{$key} mis à jour avec succès.";
    }

    private function getUsers(): array
    {
        $users = [];
        foreach (scandir('Data') as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir("Data/{$entry}")) {
                $users[] = $entry;
            }
        }
        return $users;
    }

    private function chargerProfil(string $user): array
    {
        return [
            'watched'   => $this->lireCsv("Data/{$user}/watched.csv"),
            'watchlist' => $this->lireCsv("Data/{$user}/watchlist.csv"),
        ];
    }

    private function lireCsv(string $path): array
    {
        $films = [];
        if (!file_exists($path)) return $films;

        $f     = fopen($path, 'r');
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; } // skip header
            if (isset($row[1], $row[3])) {
                $films[] = ['title' => $row[1], 'url' => $row[3]];
            }
        }
        fclose($f);

        return $films;
    }

    private function importerProfils(array $users): array
    {
        return array_map(fn($u) => $this->chargerProfil($u), $users);
    }


    private function filmsPossibles(array $profils): array
    {
        $vus = $this->titresVus($profils);

        $films = [];
        foreach ($profils as $profil) {
            foreach ($profil['watchlist'] as $film) {
                if (!in_array($film['title'], $vus)) {
                    $films[] = $film;
                }
            }
        }

        return $films;
    }

    private function watchlistCommune(array $profils): array
    {
        $candidats   = $this->filmsPossibles($profils);
        $nbPersonnes = count($profils);
 
        // Compte combien de personnes ont chaque film dans leur watchlist
        $counts = [];
        foreach ($candidats as $film) {
            $counts[$film['title']] = ($counts[$film['title']] ?? 0) + 1;
        }
 
        // Essaie depuis nbPersonnes jusqu'à 2, retourne dès qu'on trouve quelque chose
        for ($seuil = $nbPersonnes; $seuil >= 2; $seuil--) {
            $res  = [];
            $seen = [];
            foreach ($candidats as $film) {
                if ($counts[$film['title']] >= $seuil && !in_array($film['title'], $seen)) {
                    $res[]  = $film;
                    $seen[] = $film['title'];
                }
            }
            if (!empty($res)) {
                return $res;
            }
        }
 
        // Rien trouvé même à 2 personnes
        return [];
    }

    private function dejaVu(array $profils, array $profilsAbsents): array
    {
        $candidats   = $this->filmsPossibles($profils);
        $titresVusAbsents = $this->titresVus($profilsAbsents);

        $res  = [];
        $seen = [];
        foreach ($candidats as $film) {
            if (
                in_array($film['title'], $titresVusAbsents)
                && !in_array($film['title'], $seen)
            ) {
                $res[]  = $film;
                $seen[] = $film['title'];
            }
        }

        return $res;
    }

    private function titresVus(array $profils): array
    {
        $titres = [];
        foreach ($profils as $profil) {
            foreach ($profil['watched'] as $film) {
                $titres[] = $film['title'];
            }
        }
        return $titres;
    }

    private function fetchOMDb(string $title): ?array
    {
        $apiKey  = Config::get()['api']['omdb_key'];
        $url     = "https://www.omdbapi.com/?apikey={$apiKey}&t=" . urlencode($title);
        $response = @file_get_contents($url);
 
        if (!$response) return null;
 
        $data = json_decode($response, true);
        return $data ?: null;
    }
}