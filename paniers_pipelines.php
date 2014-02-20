<?php
/**
 * Plugin Paniers
 * Gestion des paniers
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

function paniers_bank_traiter_reglement($flux){
	spip_log('vidange cookies panier','paniers');
	paniers_mise_a_jour_cle(0,$GLOBALS['visiteur_session']['id_auteur']);

	// garder la reference au panier invalide qui permettra de le supprimer
	/*panier_delcookie('id_panier');
	panier_delcookie('id_panier_key');*/
	if (!$flux['args']['new']){
		return $flux;
	}

	$id_transaction = $flux['args']['id_transaction'];
	// retrouver l'auteur
	if (($row = sql_fetsel("id_auteur","spip_transactions","id_transaction=".intval($id_transaction)))
	 AND ($id_auteur = $row['id_auteur'])){
		spip_log('vidange paniers en base');

	 	// supprimer tous les paniers de cet auteur
		sql_delete("spip_paniers","id_auteur=".intval($id_auteur));
	}
	return $flux;
}

?>
