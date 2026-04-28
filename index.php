<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'include.php';

try {
    $controleur = $_GET['controleur'] ?? 'utilisateur';

    // Logout en priorité avant tout check
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: ?controleur=auth');
        exit;
    }

    if ($controleur === 'utilisateur' && !ControllerAuth::isLogged()) {
        $controleur = 'auth';
    }

    $controller = ControllerFactory::getController($controleur, $loader, $twig);
    $controller->call('index');
} catch (Exception $e) {
    http_response_code(500);
    echo "<div class='alert alert-danger m-4'><strong>Erreur :</strong> "
        . htmlspecialchars($e->getMessage()) . "</div>";
}