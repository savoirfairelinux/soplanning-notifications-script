<?php

// Doit pointer vers le répertoire racine SOplanning.
define('SOPLANNING_PATH', '../soplanning');

// Délai de sûreté avant d'envoyer un courriel
define('SOPLANNING_NOTIFICATION_INTERVAL', '10 MINUTE');

// Doit pointer vers le répertoire racine SOplanning.
define('BASE', SOPLANNING_PATH . '/www');

// Emprunté à config.inc
define('DEBUG', false);


// Liste des projets à surveiller
//	FORMAT: ID du projet => courriel du responsable
//	TODO: dans l'éventualité d'une contribution, pourrait être stoqué dans la config SOPlanning
$watched_projets = array(
	'abs' => 'sylvain.bouchard@savoirfairelinux.com'
);

?>