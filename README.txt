
SOPlanning @ SFL


VUE D'ENSEMBLE

	Courriels automatiques à RH quand les usagers créent des entrées pour leurs vacances.

	Script PHP à rouler en ligne de commande : notification.php
	Il détecte les nouvelles saisies dans le projet "conge/ abs".

	Table MySQL pour savoir quelles notifications sont déjà faites : planning_courriel

	Le script respecte un certain délai de sûreté pour ne pas envoyer les courriels trop vite.
	On laisse aux utilisateurs le temps de modifier/corriger leurs saisies.

	Aucun changement à SOPlanning mais le script en dépend (connection à la BD, envoi de courriels, etc).
	Contribution potentielle.


UTILISATION

	Voir INSTALL.txt pour l'installation.

	$ php5 notification.php

	Fortement recommandé de définir une cron job.
