<?php

class ControllerFactory
{
    public static function getController($controleur, \Twig\Loader\FilesystemLoader $loader, \Twig\Environment $twig)
    {
        // On enlève "Controller" si présent
        $controleur = preg_replace('/^controller/i', '', $controleur);

        // On normalise la casse
        $controllerName = 'Controller' . ucfirst(strtolower($controleur));

        if (!class_exists($controllerName)) {
            throw new Exception("Le controleur $controllerName n'existe pas");
        }

        return new $controllerName($twig, $loader);
    }
}