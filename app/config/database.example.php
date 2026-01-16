<?php
/**
 * PERSONNALY - Configuration Base de Données
 *
 * INSTRUCTIONS:
 * 1. Copier ce fichier vers database.php
 * 2. Remplir les valeurs avec vos credentials o2switch
 * 3. Ne JAMAIS commiter database.php
 */

return [
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => '',      // Nom de la base (ex: zajr1824_personnaly)
    'username' => '',      // Utilisateur MySQL
    'password' => '',      // Mot de passe MySQL
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
