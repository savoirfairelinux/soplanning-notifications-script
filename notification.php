<?php
/**
 *  @file
 *  Brief description of what the file does, along with the project's name
 * 
 *  @copyright 2013 Savoir-faire Linux, inc.
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


// FORMAT: ID du projet => courriel du responsable
// TODO: this could be stored in soplanning config
$watched_projets = array(
	'abs' => 'sylvain.bouchard@savoirfairelinux.com'
);

function get_periodes_courriel(){
	/*
	Liste des périodes sujettes à un avertissement par courriel.
	Retourne: une liste de rangées-objets, exemple $row->periode_id
	*/

	global $watched_projets;

	$sql = sprintf(
		"SELECT pp.*, pu.nom, pu.login, pc.ts_courriel "
			. "FROM planning_periode as pp "
			. "JOIN planning_user as pu "
				. "ON pp.user_id = pu.user_id "
			. "LEFT JOIN planning_courriel as pc "
				. "ON pp.periode_id = pc.periode_id "
			. "WHERE pp.projet_id IN(%s) AND pc.ts_courriel IS NULL;",
		implode(',', array_map(
			function($x){return "'$x'";},
			array_keys($watched_projets)))
	);
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

	$rows_periodes = get_periodes_courriel();

	foreach($rows_periodes as $row_periode){

		// Envoyer courriel + mettre à jour table planning_courriel.
		$mail_ok = send_courriel($row_periode);
		if($mail_ok){
			$sql = sprintf(
				"INSERT INTO planning_courriel "
						. "(periode_id, ts_courriel)"
					. "VALUES "
					."	(%s,CURRENT_TIMESTAMP())"
			  	. "ON DUPLICATE KEY UPDATE ts_courriel=CURRENT_TIMESTAMP();",
			  	$row_periode->periode_id);
			db_query($sql);
		}

		// Debugging info
		printf("[%s] %s %s - %s. Mail: %s\n", 
			$row_periode->login,
			$row_periode->projet_id,
			$row_periode->date_debut,
			$row_periode->date_fin,
			$mail_ok?'ok':'echec');

	}
}

send_periodes_courriels();

?>