<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ControllerReset extends Controller
{
    private const TOKENS_FILE  = 'config/reset_tokens.json';
    private const TOKEN_TTL    = 3600; // 1 heure

    public function index(): void
    {
        $token = $_GET['token'] ?? null;

        if ($token) {
            $this->handleResetPage($token);
            return;
        }

        if (isset($_POST['forgot_action'])) {
            $this->handleForgotSubmit();
            return;
        }

        if (isset($_POST['reset_action'])) {
            $this->handleResetSubmit();
            return;
        }

        echo $this->getTwig()->render('forgot_password.html.twig');
    }

    // --- Étape 1 : formulaire "j'ai oublié mon mot de passe" ---

    private function handleForgotSubmit(): void
    {
        $pseudo = trim($_POST['pseudo'] ?? '');
        $email  = trim($_POST['email']  ?? '');

        $users = ControllerAuth::loadUsers();

        // Réponse générique pour ne pas révéler si un pseudo existe
        $successMsg = "Si ce compte existe et qu'une adresse email y est associée, un lien de réinitialisation a été envoyé.";

        if (!isset($users[$pseudo])) {
            echo $this->getTwig()->render('forgot_password.html.twig', ['success' => $successMsg]);
            return;
        }

        $storedEmail = $users[$pseudo]['email'] ?? null;

        if (!$storedEmail) {
            echo $this->getTwig()->render('forgot_password.html.twig', [
                'error' => "Aucune adresse email n'est associée à ce compte. Demandez à l'administrateur de réinitialiser votre mot de passe.",
            ]);
            return;
        }

        if (strtolower($storedEmail) !== strtolower($email)) {
            echo $this->getTwig()->render('forgot_password.html.twig', ['success' => $successMsg]);
            return;
        }

        $token = $this->createToken($pseudo);
        $this->sendResetEmail($storedEmail, $pseudo, $token);

        echo $this->getTwig()->render('forgot_password.html.twig', ['success' => $successMsg]);
    }

    // --- Étape 2 : page de réinitialisation avec le token ---

    private function handleResetPage(string $token): void
    {
        $tokens = $this->loadTokens();

        if (!isset($tokens[$token]) || $tokens[$token]['expires'] < time()) {
            $this->purgeExpiredTokens();
            echo $this->getTwig()->render('forgot_password.html.twig', [
                'error' => "Ce lien est invalide ou a expiré. Veuillez refaire une demande.",
            ]);
            return;
        }

        echo $this->getTwig()->render('reset_password.html.twig', ['token' => $token]);
    }

    // --- Étape 3 : soumission du nouveau mot de passe ---

    private function handleResetSubmit(): void
    {
        $token   = $_POST['token']   ?? '';
        $mdp     = $_POST['mdp']     ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $tokens = $this->loadTokens();

        if (!isset($tokens[$token]) || $tokens[$token]['expires'] < time()) {
            echo $this->getTwig()->render('forgot_password.html.twig', [
                'error' => "Ce lien est invalide ou a expiré. Veuillez refaire une demande.",
            ]);
            return;
        }

        if (strlen($mdp) < 6) {
            echo $this->getTwig()->render('reset_password.html.twig', [
                'token' => $token,
                'error' => "Le mot de passe doit faire au moins 6 caractères.",
            ]);
            return;
        }

        if ($mdp !== $confirm) {
            echo $this->getTwig()->render('reset_password.html.twig', [
                'token' => $token,
                'error' => "Les mots de passe ne correspondent pas.",
            ]);
            return;
        }

        $pseudo = $tokens[$token]['pseudo'];
        $users  = ControllerAuth::loadUsers();

        if (!isset($users[$pseudo])) {
            echo $this->getTwig()->render('forgot_password.html.twig', [
                'error' => "Compte introuvable.",
            ]);
            return;
        }

        $users[$pseudo]['password'] = password_hash($mdp, PASSWORD_DEFAULT);
        ControllerAuth::saveUsers($users);

        unset($tokens[$token]);
        $this->saveTokens($tokens);

        header('Location: ?controleur=auth&reset=ok');
        exit;
    }

    // --- Utilitaires tokens ---

    private function createToken(string $pseudo): string
    {
        $token  = bin2hex(random_bytes(32));
        $tokens = $this->loadTokens();
        $this->purgeExpiredTokens($tokens);

        $tokens[$token] = [
            'pseudo'  => $pseudo,
            'expires' => time() + self::TOKEN_TTL,
        ];
        $this->saveTokens($tokens);
        return $token;
    }

    private function loadTokens(): array
    {
        if (!file_exists(self::TOKENS_FILE)) return [];
        return json_decode(file_get_contents(self::TOKENS_FILE), true) ?? [];
    }

    private function saveTokens(array $tokens): void
    {
        file_put_contents(self::TOKENS_FILE, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    private function purgeExpiredTokens(array &$tokens = null): void
    {
        if ($tokens === null) $tokens = $this->loadTokens();
        $now = time();
        foreach ($tokens as $t => $data) {
            if ($data['expires'] < $now) unset($tokens[$t]);
        }
        $this->saveTokens($tokens);
    }

    // --- Envoi de l'email ---

    private function sendResetEmail(string $to, string $pseudo, string $token): void
    {
        require_once 'config/mail.php';

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $link     = "{$protocol}://{$host}{$dir}/?controleur=reset&token={$token}";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to, $pseudo);

            $mail->Subject = 'Réinitialisation de votre mot de passe — Roulette Films';
            $mail->isHTML(true);
            $mail->Body = "
                <p>Bonjour <strong>{$pseudo}</strong>,</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                <p><a href='{$link}' style='background:#0d6efd;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>Réinitialiser mon mot de passe</a></p>
                <p>Ce lien expire dans 1 heure.</p>
                <p>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
            ";
            $mail->AltBody = "Bonjour {$pseudo},\n\nLien de réinitialisation (valable 1h) :\n{$link}\n\nSi vous n'avez pas fait cette demande, ignorez cet email.";

            $mail->send();
        } catch (Exception $e) {
            // On ne révèle pas l'erreur à l'utilisateur
            error_log("Erreur envoi email reset: " . $mail->ErrorInfo);
        }
    }
}
