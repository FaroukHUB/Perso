<?php
/**
 * PERSONNALY - Point d'entrée racine
 * Redirige vers public/index.php
 */

// Change le répertoire de travail pour que les chemins relatifs fonctionnent
chdir(__DIR__ . '/public');

// Inclure le vrai index
require __DIR__ . '/public/index.php';
