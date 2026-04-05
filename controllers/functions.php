<?php

function chargerProfil($user)
{
    $watched = [];
    $watchlist = [];

    $f_watched = "Data/$user/watched.csv";
    if (file_exists($f_watched)) {
        $f = fopen($f_watched, "r");
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; }
            if (isset($row[1], $row[3])) {
                $watched[] = ["title" => $row[1], "url" => $row[3]];
            }
        }
        fclose($f);
    }

    $f_watchlist = "Data/$user/watchlist.csv";
    if (file_exists($f_watchlist)) {
        $f = fopen($f_watchlist, "r");
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; }
            if (isset($row[1], $row[3])) {
                $watchlist[] = ["title" => $row[1], "url" => $row[3]];
            }
        }
        fclose($f);
    }

    return [$watched, $watchlist];
}

function importationPresent($liste)
{
    $res = [];
    foreach ($liste as $u) {
        $res[] = chargerProfil($u);
    }
    return $res;
}

function importationPasPresent($liste)
{
    $res = [];
    foreach ($liste as $u) {
        $res[] = chargerProfil($u);
    }
    return $res;
}

function filmRandom($present)
{
    $wl_commune = [];
    foreach ($present as $source) {
        foreach ($source[1] as $film) {
            $est_vu = false;
            foreach ($present as $profil) {
                foreach ($profil[0] as $vu) {
                    if ($vu["title"] === $film["title"]) {
                        $est_vu = true;
                        break 2;
                    }
                }
            }
            if (!$est_vu) {
                $wl_commune[] = $film;
            }
        }
    }
    return $wl_commune;
}

function watchlistCommune($present)
{
    $wl = filmRandom($present);
    $res = [];
    $counts = [];

    foreach ($wl as $film) {
        $title = $film["title"];
        $counts[$title] = ($counts[$title] ?? 0) + 1;
    }

    foreach ($wl as $film) {
        if ($counts[$film["title"]] >= 2) {
            $exists = false;
            foreach ($res as $r) {
                if ($r["title"] === $film["title"]) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) $res[] = $film;
        }
    }
    return $res;
}

function dejaVu($present, $absent)
{
    $wl = filmRandom($present);
    $deja = [];

    foreach ($absent as $profil) {
        foreach ($profil[0] as $vu) {
            foreach ($wl as $film) {
                if ($vu["title"] === $film["title"]) {
                    $exists = false;
                    foreach ($deja as $d) {
                        if ($d["title"] === $film["title"]) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) $deja[] = $film;
                }
            }
        }
    }

    $final = [];
    foreach ($deja as $film) {
        $vuParPresent = false;
        foreach ($present as $profil) {
            foreach ($profil[0] as $vu) {
                if ($vu["title"] === $film["title"]) {
                    $vuParPresent = true;
                    break 2;
                }
            }
        }
        if (!$vuParPresent) $final[] = $film;
    }

    return $final;
}

function getMovieDataFromOMDb($title)
{
    $apiKey = "afe2ef4c";
    $url = "https://www.omdbapi.com/?apikey={$apiKey}&t=" . urlencode($title);
    $response = @file_get_contents($url);
    return $response ?: null;
}
