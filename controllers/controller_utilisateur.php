<?php

class ControllerUtilisateur extends Controller
{
    // =========================================================
    // POINT D'ENTRÉE PRINCIPAL
    // =========================================================

    public function index(): void
    {
        if (isset($_POST['ajax_action'])) {
            $this->ajaxFilm();
            exit;
        }

        $uploadMessages = [];
        if (isset($_POST['update_action'])) {
            $uploadMessages = $this->handleUpload();
        }

        $users   = $this->getUsers();
        $present = $_POST['present'] ?? [];

        echo $this->getTwig()->render('roulette.html.twig', [
            'users'           => $users,
            'present'         => $present,
            'upload_messages' => $uploadMessages,
        ]);
    }

    // =========================================================
    // AJAX : TIRER UN FILM AU SORT
    // =========================================================

    private function ajaxFilm(): void
    {
        $present = $_POST['present'] ?? [];
        $gens    = $_POST['gens']    ?? [];
        $absent  = array_values(array_diff($gens, $present));
        $court   = isset($_POST['court']) && $_POST['court'] === 'true';
        $long    = isset($_POST['long'])  && $_POST['long']  === 'true';

        $profilPresent = $this->importerProfils($present);
        $profilAbsent  = $this->importerProfils($absent);

        switch ($_POST['ajax_action']) {
            case 'commune':
                $filmsPossibles = $this->watchlistCommune($profilPresent);
                break;
            case 'absent':
                $filmsPossibles = $this->dejaVu($profilPresent, $profilAbsent);
                break;
            default:
                $filmsPossibles = $this->filmsPossibles($profilPresent);
        }

        $film = null;

        if ($court) {
            // Filtre : moins de 2h
            shuffle($filmsPossibles);
            foreach ($filmsPossibles as $f) {
                $data = $this->fetchOMDb($f['title']);
                //var_dump($data);
                $runtime = $this->parseRuntime($data);
                //var_dump($runtime);
                if ($runtime > 0 && $runtime <= 120) {
                    $film = $this->buildFilmData($f, $data);
                    break;
                }
            }
        } elseif ($long) {
            // Filtre : plus de 2h
            shuffle($filmsPossibles);
            foreach ($filmsPossibles as $f) {
                $data    = $this->fetchOMDb($f['title']);
                $runtime = $this->parseRuntime($data);
                if ($runtime >= 120) {
                    $film = $this->buildFilmData($f, $data);
                    break;
                }
            }
        } else {
            // Pas de filtre : on tire au hasard directement
            if (!empty($filmsPossibles)) {
                $f    = $filmsPossibles[array_rand($filmsPossibles)];
                $data = $this->fetchOMDb($f['title']);
                if ($data && ($data['Response'] ?? '') === 'True') {
                    $film = $this->buildFilmData($f, $data);
                }
            }
        }

        echo $this->getTwig()->render('film_result.html.twig', [
            'film'  => $film,
            'court' => $court,
            'long'  => $long,
        ]);
    }

    // =========================================================
    // PARSING DURÉE OMDB
    // =========================================================

    private function parseRuntime(?array $data): int
    {
        if (!$data || ($data['Response'] ?? '') !== 'True') return 0;
        preg_match('/(\d+)/', $data['Runtime'] ?? '', $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    // =========================================================
    // CONSTRUCTION DES DONNÉES D'UN FILM POUR LE TEMPLATE
    // =========================================================

    private function buildFilmData(array $f, array $data): array
    {
        $runtimeMinutes = $this->parseRuntime($data);
        $hours   = intdiv($runtimeMinutes, 60);
        $minutes = $runtimeMinutes % 60;

        return [
            'title'          => $data['Title']  ?? $f['title'],
            'year'           => $data['Year']   ?? '?',
            'poster'         => $data['Poster'] ?? '',
            'duration'       => $runtimeMinutes > 0 ? "{$hours}h {$minutes}min" : 'Durée inconnue',
            'genre'          => $data['Genre']  ?? 'Inconnu',
            'plot'           => $data['Plot']   ?? '',
            'letterboxd_url' => $f['url'],
        ];
    }

    // =========================================================
    // UPLOAD CSV
    // =========================================================

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
        if (strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return "{$key} doit être un fichier CSV.";
        }
        if (!move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) {
            return "Échec de l'upload de {$key}.";
        }
        return "{$key} mis à jour avec succès.";
    }

    // =========================================================
    // LISTE DES UTILISATEURS DISPONIBLES
    // =========================================================

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

    // =========================================================
    // CHARGEMENT DES PROFILS CSV
    // =========================================================

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

        $f = fopen($path, 'r');
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; }
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

    // =========================================================
    // LOGIQUE DE SÉLECTION DES FILMS
    // =========================================================

    private function filmsPossibles(array $profils): array
    {
        $vus   = $this->titresVus($profils);
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

        $counts = [];
        foreach ($candidats as $film) {
            $counts[$film['title']] = ($counts[$film['title']] ?? 0) + 1;
        }

        for ($seuil = $nbPersonnes; $seuil >= 2; $seuil--) {
            $res  = [];
            $seen = [];
            foreach ($candidats as $film) {
                if ($counts[$film['title']] >= $seuil && !in_array($film['title'], $seen)) {
                    $res[]  = $film;
                    $seen[] = $film['title'];
                }
            }
            if (!empty($res)) return $res;
        }

        return [];
    }

    private function dejaVu(array $profils, array $profilsAbsents): array
    {
        $candidats        = $this->filmsPossibles($profils);
        $titresVusAbsents = $this->titresVus($profilsAbsents);

        $res  = [];
        $seen = [];
        foreach ($candidats as $film) {
            if (in_array($film['title'], $titresVusAbsents) && !in_array($film['title'], $seen)) {
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

    // =========================================================
    // API OMDB
    // =========================================================

    private function fetchOMDb(string $title): ?array
    {
        $apiKey   = Config::get()['api']['omdb_key'];
        $url      = "https://www.omdbapi.com/?apikey={$apiKey}&t=" . urlencode($title);
        $response = @file_get_contents($url);

        if (!$response) return null;

        $data = json_decode($response, true);
        return $data ?: null;
    }
}