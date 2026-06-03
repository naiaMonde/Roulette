<?php

class ControllerProfil extends Controller
{
    public function index(): void
    {
        if (!ControllerAuth::isLogged()) {
            header('Location: ?controleur=auth');
            exit;
        }

        $user = ControllerAuth::currentUser();

        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'marquer_vu') {
            $this->marquerVu($user);
            exit;
        }

        $messages = [];

        if (isset($_POST['upload_action']))   $messages = $this->handleUpload($user);
        if (isset($_POST['change_password'])) $messages[] = $this->handleChangePassword($user);
        if (isset($_POST['save_friends']))    $messages[] = $this->handleSaveFriends($user);
        if (isset($_POST['save_email']))      $messages[] = $this->handleSaveEmail($user);

        $watched   = $this->lireCsv("Data/{$user}/watched.csv");
        $watchlist = $this->lireCsv("Data/{$user}/watchlist.csv");

        $allUsers = array_keys(ControllerAuth::loadUsers());
        $friends  = $this->getFriends($user);
        $users    = ControllerAuth::loadUsers();

        echo $this->getTwig()->render('profil.html.twig', [
            'user'          => $user,
            'watched'       => $watched,
            'watchlist'     => $watchlist,
            'messages'      => $messages,
            'all_users'     => $allUsers,
            'friends'       => $friends,
            'current_email' => $users[$user]['email'] ?? '',
        ]);
    }

    private function handleUpload(string $user): array
    {
        $basePath = "Data/{$user}/";
        if (!is_dir($basePath)) mkdir($basePath, 0755, true);
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
        return ucfirst($key) . " mis à jour avec succès.";
    }

    private function handleSaveEmail(string $user): string
    {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Adresse email invalide.";
        }

        $users = ControllerAuth::loadUsers();
        $users[$user]['email'] = $email;
        ControllerAuth::saveUsers($users);
        return "Adresse email mise à jour !";
    }

    private function handleChangePassword(string $user): string
    {
        $ancien  = $_POST['ancien_mdp']   ?? '';
        $nouveau = $_POST['nouveau_mdp']  ?? '';
        $confirm = $_POST['confirm_mdp']  ?? '';

        $users = ControllerAuth::loadUsers();

        if (!password_verify($ancien, $users[$user]['password'])) {
            return "Ancien mot de passe incorrect.";
        }
        if (strlen($nouveau) < 6) {
            return "Le nouveau mot de passe doit faire au moins 6 caractères.";
        }
        if ($nouveau !== $confirm) {
            return "Les mots de passe ne correspondent pas.";
        }

        $users[$user]['password'] = password_hash($nouveau, PASSWORD_DEFAULT);
        ControllerAuth::saveUsers($users);
        return "Mot de passe mis à jour !";
    }

    private function lireCsv(string $path): array
    {
        $films = [];
        if (!file_exists($path)) return $films;
        $f     = fopen($path, 'r');
        $first = true;
        while (($row = fgetcsv($f)) !== false) {
            if ($first) { $first = false; continue; }
            $films[] = [
                    'title' => $row[1],
                    'year'  => isset($row[2]) ? (int)$row[2] : 0,
                    'date'  => $row[0] ?? '',
                    'url'   => $row[3],
                ];
        }
        fclose($f);
        return $films;
    }

    private function handleSaveFriends(string $user): string
    {
        $selected = $_POST['friends'] ?? [];
        // On retire l'utilisateur lui-même s'il s'est coché
        $selected = array_values(array_filter($selected, fn($u) => $u !== $user));

        $users = ControllerAuth::loadUsers();
        $users[$user]['friends'] = $selected;
        ControllerAuth::saveUsers($users);

        return "Groupe mis à jour !";
    }

    public static function getFriends(string $user): array
    {
        $users = ControllerAuth::loadUsers();
        // Par défaut tous le monde sauf soi-même
        $all = array_keys($users);
        return $users[$user]['friends'] ?? array_values(array_filter($all, fn($u) => $u !== $user));
    }

    private function marquerVu(string $user): void
    {
        $title = $_POST['title'] ?? '';
        $url   = $_POST['url']   ?? '';

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            return;
        }

        $watchlistPath = "Data/{$user}/watchlist.csv";
        $watchedPath   = "Data/{$user}/watched.csv";

        if (file_exists($watchlistPath)) {
            $rows   = [];
            $f      = fopen($watchlistPath, 'r');
            $header = fgetcsv($f);
            while (($row = fgetcsv($f)) !== false) {
                if (($row[1] ?? '') !== $title) {
                    $rows[] = $row;
                }
            }
            fclose($f);

            $f = fopen($watchlistPath, 'w');
            fputcsv($f, $header);
            foreach ($rows as $row) fputcsv($f, $row);
            fclose($f);
        }

        if (!file_exists($watchedPath)) {
            echo json_encode(['success' => false, 'message' => "Fichier watched introuvable pour {$user}"]);
            return;
        }

        $already = false;
        $f       = fopen($watchedPath, 'r');
        $header  = fgetcsv($f);
        $rows    = [];
        while (($row = fgetcsv($f)) !== false) {
            if (($row[1] ?? '') === $title) $already = true;
            $rows[] = $row;
        }
        fclose($f);

        if (!$already) {
            $rows[] = [date('Y-m-d'), $title, '', $url];
            $f = fopen($watchedPath, 'w');
            fputcsv($f, $header);
            foreach ($rows as $row) fputcsv($f, $row);
            fclose($f);
        }

        echo json_encode(['success' => true, 'message' => 'Film marqué comme vu']);
    }
}