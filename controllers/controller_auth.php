<?php

class ControllerAuth extends Controller
{
    public function index(): void
    {
        if (isset($_POST['login_action'])) {
            $this->handleLogin();
            return;
        }
        if (isset($_POST['register_action'])) {
            $this->handleRegister();
            return;
        }
        if (isset($_GET['logout'])) {
            $this->handleLogout();
            return;
        }
        echo $this->getTwig()->render('login.html.twig');
    }

    private function handleLogin(): void
    {
        $pseudo = trim($_POST['pseudo'] ?? '');
        $mdp    = $_POST['mdp'] ?? '';
        $users  = $this->loadUsers();

        if (isset($users[$pseudo]) && password_verify($mdp, $users[$pseudo]['password'])) {
            $_SESSION['user'] = $pseudo;
            header('Location: ?');
            exit;
        }

        echo $this->getTwig()->render('login.html.twig', [
            'error' => 'Pseudo ou mot de passe incorrect.',
            'pseudo' => $pseudo,
        ]);
    }

    private function handleLogout(): void
    {
        session_destroy();
        header('Location: ?');
        exit;
    }

    public static function loadUsers(): array
    {
        $path = 'config/users.json';
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    public static function saveUsers(array $users): void
    {
        file_put_contents('config/users.json', json_encode($users, JSON_PRETTY_PRINT));
    }

    public static function isLogged(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function currentUser(): ?string
    {
        return $_SESSION['user'] ?? null;
    }

    private function handleRegister(): void
    {
        $pseudo  = trim($_POST['pseudo'] ?? '');
        $mdp     = $_POST['mdp']     ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $errors  = [];

        if (strlen($pseudo) < 3) {
            $errors[] = "Le pseudo doit faire au moins 3 caractères.";
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pseudo)) {
            $errors[] = "Le pseudo ne peut contenir que des lettres, chiffres, - et _.";
        }
        if (strlen($mdp) < 6) {
            $errors[] = "Le mot de passe doit faire au moins 6 caractères.";
        }
        if ($mdp !== $confirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        $users = self::loadUsers();
        if (isset($users[$pseudo])) {
            $errors[] = "Ce pseudo est déjà pris.";
        }

        if (!empty($errors)) {
            echo $this->getTwig()->render('login.html.twig', [
                'register_errors' => $errors,
                'register_pseudo' => $pseudo,
                'show_register'   => true,
            ]);
            return;
        }

        // Création du compte
        $users[$pseudo] = [
            'password'    => password_hash($mdp, PASSWORD_DEFAULT),
            'data_folder' => $pseudo,
        ];
        self::saveUsers($users);
        if (!is_dir("Data/{$pseudo}")) {
            mkdir("Data/{$pseudo}", 0755, true);
        }

        // Connexion automatique
        $_SESSION['user'] = $pseudo;
        header('Location: ?');
        exit;
    }
}