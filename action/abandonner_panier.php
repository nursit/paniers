<?php
/**
 * Plugin Paniers
 * Gestion des paniers
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


function action_abandonner_panier_dist($id_panier=null){

	if (is_null($id_panier)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$id_panier = $securiser_action();
	}

	if ($id_panier=intval($id_panier)
		AND sql_countsel("spip_paniers","id_panier=".intval($id_panier))){

		sql_delete('spip_paniers',"id_panier=".intval($id_panier));

		if ($GLOBALS['visiteur_session']['id_panier']==$id_panier)
			paniers_mise_a_jour_cle($id_panier,$GLOBALS['visiteur_session']['id_auteur']);
	}
}