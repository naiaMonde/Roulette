<?php
 
session_start();
 
require_once 'vendor/autoload.php';
require_once 'include.php';
 
try {
    $controller = ControllerFactory::getController('utilisateur', $loader, $twig);
    $controller->call('index');
} catch (Exception $e) {
    http_response_code(500);
    echo "<div class='alert alert-danger m-4'><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}