<?php
/**
 *  @file
 *  Automated notification emails for selected SOPlanning projects. Standalone PHP script.
 * 
 *  @copyright 2014 Savoir-faire Linux, inc.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @author Sylvain Bouchard <sylvain.bouchard@savoirfairelinux.com>
 *
 */

// import local config (the "SOPLANNING_PATH" constant, mostly)
require('config.inc');

// import soplanning email dependencies
require(SOPLANNING_PATH . '/phpmailer/class.phpmailer.php');
require(SOPLANNING_PATH . '/includes/class_mailer.inc');

// import soplanning database config
require(SOPLANNING_PATH . '/database.inc');
require(SOPLANNING_PATH . '/includes/db_wrapper.inc');
db_connect($cfgHostname, $cfgUsername, $cfgPassword, $cfgDatabase, $cfgSqlType);

// chargement des données de config (emprunté à config.inc)
$configs = db_query('SELECT * FROM planning_config');
while($configTemp = db_fetch_array($configs)) {
	define('CONFIG_' . $configTemp['cle'], $configTemp['valeur']);
}


// par souci d'uniformité, $_GET accueille les paramètres de la ligne de commande
parse_str(implode('&', array_slice($argv, 1)), $_GET);


if(isset($_GET['--help'])){
	echo "\n  Utilisation: php5 notification.php [options]";

	echo "\n\n  --help: affiche ce message d'aide";
	echo "\n  --no-email: met à jour la BD mais sans envoyer de courriel";
	echo "\n";
	exit;
}

// Désactiver les courriels
define('ENABLE_EMAIL', ! isset($_GET['--no-email']) );


function get_periodes_courriel(){
	/*
	Liste des périodes sujettes à un avertissement par courriel.
	Retourne: une liste de rangées-objets, exemple $row->periode_id
	*/

	// voir config.inc local
	global $watched_projets;

	$sql = sprintf(
		"SELECT pp.*, pu.nom, pu.login, pc.ts_courriel "
			. "FROM planning_periode as pp "
			. "JOIN planning_user as pu "
				. "ON pp.user_id = pu.user_id "
			. "LEFT JOIN planning_courriel as pc "
				. "ON pp.periode_id = pc.periode_id "
			. "WHERE pp.projet_id IN(%s) "
			. "AND (pc.ts_courriel IS NULL OR pc.ts_courriel < (NOW() - INTERVAL %s));",
		implode(',', array_map(
			function($x){return "'$x'";},
			array_keys($watched_projets))),

		// voir config.inc local
		SOPLANNING_NOTIFICATION_INTERVAL
	);
	if(DEBUG){
		echo $sql . "\n";
	}
	$res = db_query($sql);

	$rows = array();
	while($row = mysql_fetch_object($res)) {
		array_push($rows, $row);
	}
	return $rows;
}

function send_courriel($row_periode){
	/*
	Retourne: true/false -> "le courriel a bien été envoyé?"
	*/

	// voir config.inc local
	global $watched_projets;

	$body_data = array(
		"Début"=>$row_periode->date_debut,
		"Fin"=>$row_periode->date_fin,
		"Titre"=>$row_periode->titre,
		"Notes"=>$row_periode->notes,
		"Lien"=>$row_periode->lien,
	);
	$body = sprintf(
		"Vacances de %s (%s)\n\n%s\n\nCeci est un envoi automatique.",

		// Nom & login
		$row_periode->nom, $row_periode->login,

		// Quelques infos clé: valeur séparées par des sauts de lignes.
		implode("\n", array_map(
			function($v,$k){return sprintf("%s: %s",$k,$v);},
			$body_data, array_keys($body_data)))
	);

	$mail = new Mailer(
		$watched_projets[$row_periode->projet_id], // Destinataire
		sprintf("SOPLANNING - Vacances de %s", $row_periode->nom), // Sujet
		$body
	);

	try {
		$result = $mail->send();
	} catch (phpmailerException $e) {
		echo 'error while sending the email :';
		print_r($e);
		return false;
	}

	return true;
}

function send_periodes_courriels(){
	/*
	Cette fonction est la "boucle principale" qui envoie un courriel
	pour chaque ajout à planning_periode sur un projet surveillé.
	*/

	$rows_periodes = get_periodes_courriel();

	foreach($rows_periodes as $row_periode){

		// Envoyer courriel + mettre à jour table planning_courriel.
		if(ENABLE_EMAIL){
			$mail_ok = send_courriel($row_periode);
			$mail_msg = $mail_ok ? 'ok' : 'echec';
		} else {
			$mail_ok = true;
			$mail_msg = "n/a";
		}

		if($mail_ok){
			$sql = sprintf(
				"INSERT INTO planning_courriel "
						. "(periode_id, ts_courriel) "
					. "VALUES "
						. "(%s,CURRENT_TIMESTAMP()) "
			  	. "ON DUPLICATE KEY UPDATE ts_courriel=CURRENT_TIMESTAMP();",
			  	$row_periode->periode_id);
			if(DEBUG){
				echo $sql . "\n";
			}
			db_query($sql);
		}

		// Results output
		printf("[%s] %s %s - %s. Mail: %s\n", 
			$row_periode->login,
			$row_periode->projet_id,
			$row_periode->date_debut,
			$row_periode->date_fin,
			$mail_msg);

	}
}

send_periodes_courriels();


?>