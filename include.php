<?php

// Autoload Composer (Twig, Symfony YAML, etc.)
require_once 'vendor/autoload.php';

// Configuration Twig
require_once 'config/twig.php';
require_once 'config/config.php';

// Contrôleurs
require_once 'controllers/controller.class.php';
require_once 'controllers\controller_utilisateur.php';
require_once 'controllers/controller_factory.class.php';