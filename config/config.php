<?php
/**
 * @file config.php
 * @brief Contrôle les constantes accessibles dans toute l'application.
 */

use Symfony\Component\Yaml\Yaml;

class Config
{
    private static $instance = null;

    private function __construct()
    {
        self::$instance = Yaml::parseFile(__DIR__ . '/constantes.yaml');
    }

    public static function get()
    {
        if (self::$instance == null) {
            new Config();
        }
        return self::$instance;
    }
} 
