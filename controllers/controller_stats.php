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

        // ── Watchlist stats ──────────────────────────────────────
        $filmCounts = [];
        $filmUrls   = [];

        foreach ($profils as $profil) {
            $seen = [];
            foreach ($profil['watchlist'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seen)) {
                    $filmCounts[$title] = ($filmCounts[$title] ?? 0) + 1;
                    $filmUrls[$title]   = $film['url'];
                    $seen[]             = $title;
                }
            }
        }

        $totalUnique   = count($filmCounts);
        $filmsEnCommun = count(array_filter($filmCounts, fn($c) => $c >= 2));
        $pourcentage   = $totalUnique > 0 ? round($filmsEnCommun / $totalUnique * 100, 1) : 0;

        arsort($filmCounts);
        $films = [];
        foreach ($filmCounts as $title => $count) {
            $films[] = ['title' => $title, 'url' => $filmUrls[$title], 'count' => $count, 'total' => $nbPersonnes];
        }

        // ── Watched stats ────────────────────────────────────────
        $watchedCounts = [];
        $watchedUrls   = [];

        foreach ($profils as $profil) {
            $seen = [];
            foreach ($profil['watched'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seen)) {
                    $watchedCounts[$title] = ($watchedCounts[$title] ?? 0) + 1;
                    $watchedUrls[$title]   = $film['url'];
                    $seen[]                = $title;
                }
            }
        }

        $totalUniqueWatched   = count($watchedCounts);
        $watchedEnCommun      = count(array_filter($watchedCounts, fn($c) => $c >= 2));
        $pourcentageWatched   = $totalUniqueWatched > 0 ? round($watchedEnCommun / $totalUniqueWatched * 100, 1) : 0;

        arsort($watchedCounts);
        $watchedFilms = [];
        foreach ($watchedCounts as $title => $count) {
            $watchedFilms[] = ['title' => $title, 'url' => $watchedUrls[$title], 'count' => $count, 'total' => $nbPersonnes];
        }

        // ── Decade distribution (watchlist + watched) ────────────
        $decades        = [];
        $decadesWatched = [];
        $seenWl         = [];
        $seenWd         = [];

        foreach ($profils as $profil) {
            foreach ($profil['watchlist'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seenWl) && !empty($film['year'])) {
                    $d = (int)(intdiv((int)$film['year'], 10) * 10);
                    $decades[$d] = ($decades[$d] ?? 0) + 1;
                    $seenWl[] = $title;
                }
            }
            foreach ($profil['watched'] as $film) {
                $title = $film['title'];
                if (!in_array($title, $seenWd) && !empty($film['year'])) {
                    $d = (int)(intdiv((int)$film['year'], 10) * 10);
                    $decadesWatched[$d] = ($decadesWatched[$d] ?? 0) + 1;
                    $seenWd[] = $title;
                }
            }
        }
        ksort($decades);
        ksort($decadesWatched);

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
            'totalUniqueFilms'    => $totalUnique,
            'filmsEnCommun'       => $filmsEnCommun,
            'pourcentage'         => $pourcentage,
            'nbPersonnes'         => $nbPersonnes,
            'films'               => $films,
            'totalUniqueWatched'  => $totalUniqueWatched,
            'watchedEnCommun'     => $watchedEnCommun,
            'pourcentageWatched'  => $pourcentageWatched,
            'watchedFilms'        => $watchedFilms,
            'decades'             => $decades,
            'decadesWatched'      => $decadesWatched,
            'userSummary'         => $userSummary,
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
