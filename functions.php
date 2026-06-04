<?php
function pdo_connect_mysql() {
    $DATABASE_HOST = 'localhost'; 
    $DATABASE_USER = 'root'; 
    $DATABASE_PASS = 'admin'; 
    $DATABASE_NAME = 'Projet_Mana'; // vous avez bien créé déjà une base de données :) ?
    try { // on essaie la connexion
    	return new PDO('mysql:host=' . $DATABASE_HOST . ';dbname=' . $DATABASE_NAME . ';charset=utf8', $DATABASE_USER, $DATABASE_PASS);
    } catch (PDOException $exception) {
		// Si erreur dans la connection alors stopper et afficher le message ci dessous
    	exit('Echec à la connexion vers la base !');
    }
}




?>
