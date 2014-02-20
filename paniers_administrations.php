<?php
/**
 * Plugin Paniers
 * Gestion des paniers
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Declaration des objets sites et matrices
 * 
 * @param  $tables
 * @return 
 */
function paniers_declarer_tables_principales($tables_principales){
	
	$spip_paniers = array(
		"id_panier" => "bigint(21) NOT NULL",
		"id_auteur"	=> "bigint(21) NOT NULL",
		"cookie_panier" => "TEXT NOT NULL DEFAULT ''",
		"date_panier" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
		"maj" => "TIMESTAMP"
	);
	
	$spip_paniers_key = array(
		"PRIMARY KEY" 	=> "id_panier"
	);
	
	$tables_principales['spip_paniers'] = array(
		'field' => &$spip_paniers,
		'key' => &$spip_paniers_key);

	return $tables_principales;
}


/**
 * Installation/maj des tables clusters
 *
 * @param string $nom_meta_base_version
 * @param string $version_cible
 */
function paniers_upgrade($nom_meta_base_version,$version_cible){
	$maj = array();
	// creation initiale
	$maj['create'] = array(
		array('maj_tables',array('spip_paniers')),
	);

	// lancer la maj
	include_spip('base/upgrade');
	include_spip('action/paniers_matrices');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Desinstallation/suppression des tables clusters
 *
 * @param string $nom_meta_base_version
 */
function paniers_vider_tables($nom_meta_base_version) {
	sql_drop_table("spip_paniers");
	effacer_meta($nom_meta_base_version);
}