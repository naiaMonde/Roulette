<?php
require_once 'functions.php';

// --- AJOUT AJAX ---
if (isset($_POST['ajax_action'])) {
    $present = $_POST["present"] ?? [];
    $gens = $_POST["gens"] ?? [];
    $absent = array_values(array_diff($gens, $present));
    $profilPresent = importationPresent($present);
    $profilAbsent  = importationPasPresent($absent);

    $moinsDeDeuxHeures = isset($_POST['court']) && $_POST['court'] === 'true';

    // On récupère la liste brute selon l'action
    if ($_POST["ajax_action"] === "random") $filmsPossibles = filmRandom($profilPresent);
    elseif ($_POST["ajax_action"] === "commune") $filmsPossibles = watchlistCommune($profilPresent);
    else $filmsPossibles = dejaVu($profilPresent, $profilAbsent);

    // FILTRE PAR DURÉE
    if ($moinsDeDeuxHeures) {
        shuffle($filmsPossibles);

        $filmTrouve = null;
        foreach ($filmsPossibles as $f) {
            $data = json_decode(getMovieDataFromOMDb($f["title"]), true);

            if ($data && $data['Response'] === 'True') {
                $runtime = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);
                if ($runtime > 0 && $runtime <= 120) {
                    $filmTrouve = $f;
                    break;
                }
            }
        }
        if ($filmTrouve) {
            $filmsFiltres = [$filmTrouve];
        } else {
            $filmsFiltres = [];
        }
    } else {
        $filmsFiltres = $filmsPossibles;
    }
    if (empty($filmsFiltres)) {
        echo "<div class='alert alert-warning border shadow-sm text-center py-4'>Aucun film trouvé (surtout avec le filtre -120min).</div>";
    } else {
        // ON CHOISIT LE FILM DANS LA LISTE FILTRÉE
        $film = $filmsFiltres[array_rand($filmsFiltres)];

        $response = getMovieDataFromOMDb($film["title"]);
        $data = json_decode($response, true);

        if ($data && $data['Response'] === 'True') {
            $runtimeMinutes = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);
            $hours = intdiv($runtimeMinutes, 60);
            $minutes = $runtimeMinutes % 60;
?>
            <div class='card mb-5 overflow-hidden border-0 shadow-lg'>
                <div class='row g-0'>
                    <div class='col-md-4'><img src='<?php echo $data['Poster']; ?>' class='img-fluid h-100 movie-poster'></div>
                    <div class='col-md-8 d-flex align-items-center'>
                        <div class='card-body p-4'>
                            <h3 class='card-title h2'><?php echo $data['Title']; ?> <span class='text-muted small'>(<?php echo $data['Year']; ?>)</span></h3>
                            <div class='mb-3'>
                                <span class='badge bg-light text-dark border me-2'><i class='bi bi-clock me-1'></i> <?php echo "{$hours}h {$minutes}min"; ?></span>
                                <span class='badge bg-light text-dark border'><i class='bi bi-tags me-1'></i> <?php echo $data['Genre']; ?></span>
                            </div>
                            <p class='card-text lead fs-6'><?php echo $data['Plot']; ?></p>
                            <hr>
                            <a href='<?php echo $film['url']; ?>' target='_blank' class='btn btn-outline-dark mt-2'>Letterboxd</a>
                            <a href='https://www.justwatch.com/fr/recherche?q=<?php echo urlencode($data["Title"]); ?>' target='_blank' class='btn btn-outline-dark mt-2'>
                                Où regarder ?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
<?php
        } else {
            echo "<h3>" . $film['title'] . "</h3><p>Infos non trouvées</p>";
        }
    }
    exit;
}

// =========================
// TRAITEMENT UPLOAD CSV
// =========================
if (isset($_POST["update_action"])) {

    $user = $_POST["update_user"];
    $basePath = "Data/$user/";

    function handleUpload($key, $dest)
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]["error"] !== UPLOAD_ERR_OK)
            return "Aucun fichier pour $key";

        $filename = $_FILES[$key]["name"];
        $tmp = $_FILES[$key]["tmp_name"];

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "csv")
            return "$key doit être un fichier CSV";

        if (!move_uploaded_file($tmp, $dest))
            return "Échec de l'upload de $key";

        return "$key mis à jour avec succès";
    }

    $messages = [];

    if (!empty($_FILES["watched"]["name"])) {
        $messages[] = handleUpload("watched", $basePath . "watched.csv");
    }
    if (!empty($_FILES["watchlist"]["name"])) {
        $messages[] = handleUpload("watchlist", $basePath . "watchlist.csv");
    }

    echo "<div class='alert alert-info border-0 shadow-sm mx-auto mt-3' style='max-width: 800px;'>";
    echo "<i class='bi bi-info-circle-fill me-2'></i><b>Mise à jour de $user :</b><br>";
    foreach ($messages as $m) echo "<span class='ms-4'>$m</span><br>";
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roulette Cinéma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" type="image/x-icon" href="wailordpixel.png" />
</head>

<body class="py-5">

    <div class="container" style="max-width: 900px;">

        <header class="text-center mb-5">
            <h1 class="display-4"><i class="bi bi-film me-2"></i> Roulette Film</h1>
        </header>

        <div class="card p-4 mb-4">
            <h2 class="h5 mb-4 border-bottom pb-2 text-uppercase" style="letter-spacing: 1px;">
                <i class="bi bi-people me-2"></i> Participants
            </h2>

            <form method="POST">
                <div class="row g-3">
                    <?php
                    $gens = [];
                    foreach (scandir("Data") as $entry) {
                        if ($entry === "." || $entry === "..") continue;
                        $checked = (isset($_POST["present"]) && in_array($entry, $_POST["present"])) ? "checked" : "";
                        echo "
                        <div class='col-6 col-md-3'>
                            <div class='form-check'>
                                <input class='form-check-input' type='checkbox' name='present[]' value='$entry' id='$entry' $checked>
                                <label class='form-check-label' for='$entry'>$entry</label>
                            </div>
                        </div>";
                        $gens[] = $entry;
                    }
                    ?>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-circle me-2"></i>Confirmer la présence
                    </button>
                </div>
            </form>
        </div>

        <?php
        if (isset($_POST["present"])) :
            $present = $_POST["present"];
            $absent  = array_values(array_diff($gens, $present));
            $profilPresent = importationPresent($present);
            $profilAbsent  = importationPasPresent($absent);
        ?>

            <div class="card p-4 mb-4 border-0" style="background: transparent; box-shadow: none;">
                <form method="POST" class="d-flex gap-3 justify-content-center flex-wrap">
                    <?php foreach ($present as $p) echo "<input type='hidden' name='present[]' value='$p'>"; ?>

                    <button name="action" value="random" class="btn btn-primary">
                        <i class="bi bi-dice-5 me-2"></i>Au hasard
                    </button>
                    <button name="action" value="commune" class="btn btn-warning">
                        <i class="bi bi-stars me-2"></i>Watchlist commune
                    </button>
                    <button name="action" value="absent" class="btn btn-danger">
                        <i class="bi bi-eye-slash me-2"></i>Vu par les absents
                    </button>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input" type="checkbox" id="court">
                        <label class="form-check-label small fw-bold" for="court">moins de 2h</label>
                    </div>
                </form>
            </div>

            <div id="resultat-film">
            </div>

            <div class="mt-5">
                <h2 class="h5 mb-4 text-center text-muted text-uppercase" style="letter-spacing: 2px;">
                    <i class="bi bi-cloud-arrow-up me-2"></i> Gérer vos données
                </h2>

                <div class="row g-4">
                    <?php foreach ($present as $user) : ?>
                        <div class="col-md-6">
                            <div class="card p-4 upload-section">
                                <h4 class="h6 mb-3"><i class="bi bi-person-circle me-2"></i><?php echo $user; ?></h4>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_user" value="<?php echo $user; ?>">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Watched (CSV)</label>
                                        <input type="file" name="watched" class="form-control form-control-sm">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Watchlist (CSV)</label>
                                        <input type="file" name="watchlist" class="form-control form-control-sm">
                                    </div>
                                    <button type="submit" name="update_action" value="upload" class="btn btn-sm btn-outline-secondary w-100">
                                        Mettre à jour
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>