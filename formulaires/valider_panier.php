<?php
/**
 * Plugin Site.maker
 * Administration des sites&matrices
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


include_spip('inc/sitmaker_sites');

function formulaires_valider_panier_charger_dist($id_auteur, $id_panier, $redirect_success="", $redirect_abandon=""){
	if (!$id_panier
	  OR !sql_countsel('spip_paniers','id_panier='.intval($id_panier)))
		return "<p>"._T('paniers:erreur_aucune_commande')."</p>";

	if ($verifier_panier_avant_validation=charger_fonction('verifier_panier_avant_validation','inc',true))
		$verifier_panier_avant_validation($id_panier);

	$valeurs = array(
		'id_auteur' => $id_auteur,
		'id_panier' => $id_panier,
		'_url_cancel' => $redirect_abandon,
		'contrats' => array(),
	);

	return $valeurs;
}

function formulaires_valider_panier_verifier_dist($id_auteur, $id_panier, $redirect_success="", $redirect_abandon=""){
	$erreurs = array();

	$contrats = _request('contrats');
	if (!$contrats)
		$contrats = array();

	if (
		!in_array('cgv',$contrats)
	)
		$erreurs['message_erreur'] = _T('paniers:erreur_accepter_tous_les_contrats');

	return $erreurs;
}

function formulaires_valider_panier_traiter_dist($id_auteur, $id_panier, $redirect_success="", $redirect_abandon=""){

	$panier = sql_fetsel('*','spip_paniers','id_panier='.intval($id_panier));
	$items = paniers_explique_cookie($panier['cookie_panier']);
	list($montant_ht,$montant) = paniers_calcul_total($items);


	if ($inserer_transaction = charger_fonction('inserer_transaction','bank',true)
		AND $id_transaction = $inserer_transaction(
		$montant,
		$montant_ht,
		$id_auteur,
		"",
		"",
		"",
		"",
		array(
			'id_panier'=>$id_panier, // stocker la reference au panier en cours
			'contenu'=>$panier['cookie_panier'], // stocker le contenu de la commande dans un cookie serialize idem au panier
			'force'=>false, // recycler la transaction deja cree pour ce panier si possible
		)
	)){
		$res = array(
			'message_ok' => _T('paniers:message_panier_valide'),
		);
		if ($redirect_success){
			$hash = sql_getfetsel('transaction_hash','spip_transactions','id_transaction='.intval($id_transaction));
			$res['redirect'] = parametre_url(parametre_url($redirect_success,'id_transaction',$id_transaction),'transaction_hash',$hash);
		}
	}
	else {
		spip_log('echec creation transaction','paniers.'. _LOG_CRITIQUE);
		$res = array('message_erreur' => _T('paniers:erreur_technique_creation_commande'));
	}

	return $res;
}