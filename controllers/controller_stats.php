<?php

class ControllerStats extends Controller
{
    public function index(): void
    {
        if (!ControllerAuth::isLogged()) {
            header('Location: ?controleur=auth');
            exit;
        }

        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'stats') {
            header('Content-Type: application/json');
            $this->ajaxStats();
            exit;
        }

        $currentUser = ControllerAuth::currentUser();
        $friends     = ControllerProfil::getFriends($currentUser);
        $users       = array_merge([$currentUser], $friends);

        echo $this->getTwig()->render('stats.html.twig', [
            'users' => $users,
        ]);
    }

    private function ajaxStats(): void
    {
        $present = $_POST['present'] ?? [];

        if (empty($present)) {
            echo json_encode(['error' => 'Aucun participant sélectionné']);
            return;
        }

        $nbPersonnes = count($present);
        $profils     = [];
        foreach ($present as $u) {
            $profils[] = [
                'user'      => $u,
                'watchlist' => $this->lireCsv("Data/{$u}/watchlist.csv"),
                'watched'   => $this->lireCsv("Data/{$u}/watched.csv"),
            ];
        }

        // Count occurrences of each film across watchlists
        $filmCounts  = [];
        $filmUrls    = [];
        $filmYears   = [];
        $watchedAll  = [];

        foreach ($profils as $profil) {
            $seen = [];
            foreach ($profil['watchlist'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seen)) {
                    $filmCounts[$title] = ($filmCounts[$title] ?? 0) + 1;
                    $filmUrls[$title]   = $film['url'];
                    $filmYears[$title]  = $film['year'] ?? null;
                    $seen[]             = $title;
                }
            }
            foreach ($profil['watched'] as $film) {
                $watchedAll[$film['title']] = true;
            }
        }

        $totalUnique   = count($filmCounts);
        $filmsEnCommun = 0;
        foreach ($filmCounts as $count) {
            if ($count >= 2) $filmsEnCommun++;
        }
        $pourcentage = $totalUnique > 0 ? round($filmsEnCommun / $totalUnique * 100, 1) : 0;

        // Sort by count desc, then alphabetically
        arsort($filmCounts);

        $films = [];
        foreach ($filmCounts as $title => $count) {
            $films[] = [
                'title' => $title,
                'url'   => $filmUrls[$title],
                'count' => $count,
                'total' => $nbPersonnes,
            ];
        }

        // Decade distribution (unique films only)
        $decades = [];
        $seenForDecade = [];
        foreach ($profils as $profil) {
            foreach ($profil['watchlist'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seenForDecade) && !empty($film['year'])) {
                    $decade = (int)(intdiv((int)$film['year'], 10) * 10);
                    $decades[$decade] = ($decades[$decade] ?? 0) + 1;
                    $seenForDecade[] = $title;
                }
            }
        }
        ksort($decades);

        // Per-user summary
        $userSummary = [];
        foreach ($profils as $profil) {
            $userSummary[] = [
                'user'      => $profil['user'],
                'watchlist' => count($profil['watchlist']),
                'watched'   => count($profil['watched']),
            ];
        }

        echo json_encode([
            'totalUniqueFilms' => $totalUnique,
            'filmsEnCommun'    => $filmsEnCommun,
            'pourcentage'      => $pourcentage,
            'nbPersonnes'      => $nbPersonnes,
            'films'            => $films,
            'decades'          => $decades,
            'userSummary'      => $userSummary,
        ]);
    }

    private function lireCsv(string $path): array
    {
        $films = [];
        if (!file_exists($path)) return $films;

        $f     = fopen($path, 'r');
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; }
            if (isset($row[1], $row[3])) {
                $films[] = [
                    'title' => $row[1],
                    'year'  => isset($row[2]) && is_numeric($row[2]) ? (int)$row[2] : null,
                    'url'   => $row[3],
                ];
            }
        }
        fclose($f);

        return $films;
    }
}
