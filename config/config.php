<?php
/**
 * @file config.php
 * @brief Contrôle les constantes accessibles dans toute l'application.
 */

use Symfony\Component\Yaml\Yaml;

class Config
{
    // Attributs
    private static $instance = null; /** Instance Singleton de Config */

    /**
     * Constructeur privé pour la construction d'un Singleton de Config
     */
    private function __construct()
    {
        self::$instance = Yaml::parseFile(__DIR__ . '/constantes.yaml');
    }

    //Fonctions
    /**
     * @brief Récupère l'instance de Config
     * 
     * @return array Tableau des constantes de configuration
     */
    public static function get()
    {
        if (self::$instance == null) {
            new Config();
        }
        return self::$instance;
    }
} 
