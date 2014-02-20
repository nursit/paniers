<?php
/**
 * Plugin Paniers
 * Gestion des paniers
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/*
function paniers_update_from_cookie($id_panier) {
	$id_auteur = isset($GLOBALS['visiteur_session']['id_auteur'])?$GLOBALS['visiteur_session']['id_auteur']:0;
	$panier = isset($_COOKIE['panier'])?$_COOKIE['panier']:'';
	$res = spip_query("SELECT * FROM spip_paniers WHERE id_panier="._q($id_panier));
	$mise_a_jour_cle = (!$_COOKIE['id_panier_key'] || ($_COOKIE['id_panier']!=$id_panier));
	if($row = spip_fetch_array($res)) {
		if (($id_auteur!==$row['id_auteur'])
		  OR ($panier!==$row['cookie_panier'])
		  OR _request('var_panier')
		  OR _request('var_promo')) {
				$items = paniers_explique_cookie($panier);
				$items = pipeline('paniers_actualise_contenu',array('args'=>array(),'data'=>$items));
				$panier = paniers_make_cookie($items);
				paniers_setcookie('panier',$panier);
				spip_log("maj panier en base:$id_panier",'panier');
	  		spip_query("UPDATE spip_paniers SET "
		  	  . (($id_auteur && ($row['id_auteur']==0)) ? "id_auteur="._q($id_auteur).", " :"") // on ne peut mettre a jour l'id_auteur d'un panier qu'a la premiere connexion (securite) !
		  	  . "cookie_panier="._q($panier).", "
		  	  . "date_panier=NOW()"
		  	  . " WHERE id_panier="._q($id_panier));
				spip_query("DELETE FROM spip_forms_donnees_paniers WHERE id_panier="._q($id_panier));
				$rang = 0;
				foreach($items as $item) {
					if (intval($item[1]))
						spip_query("INSERT INTO spip_forms_donnees_paniers (id_panier,id_donnee,quantite,rang) VALUES ("._q($id_panier).","._q($item[0]).","._q($item[1]).","._q($rang++).")");
				}
				$mise_a_jour_cle = true;
		}
		if ($mise_a_jour_cle)
			paniers_mise_a_jour_cle($id_panier,$id_auteur);
	}
}*/

function panier_creer($id_auteur){
	$id_panier = sql_insertq(
		"spip_paniers",
		array(
			"id_auteur"	=> $id_auteur,
			"cookie_panier" => "",
			"date_panier" => date('Y-m-d H:i:s'),
		)
	);

	if ($id_auteur==$GLOBALS['visiteur_session']['id_auteur'])
		$GLOBALS['visiteur_session']['id_panier'] = $id_panier;

	return $id_panier;
}

/**
 * @param int $id_panier
 * @param array $item
 *  id  : reference produit
 *  quantity : quantite
 *  net_price  : prix HT
 *  gross_price : string prix TTC
 *  category : categorie
 *  id_syndic : site associe
 *
 * @return array|bool
 */
function panier_ajouter_item($id_panier,$item){
	if (!$row = sql_fetsel("*","spip_paniers","id_panier=".intval($id_panier)))
		return false;

	$cookie = $row['cookie_panier'];
	$items = paniers_explique_cookie($cookie);

	$items[] = $item;
	$cookie = paniers_make_cookie($items);
	sql_updateq("spip_paniers",
		array(
		'cookie_panier' => $cookie,
		'date_panier' => date('Y-m-d H:i:s'),
		),
		"id_panier=".intval($id_panier)
	);

	return $items;
}

function panier_modifier_item($id_panier,$item){
	if (!$row = sql_fetsel("*","spip_paniers","id_panier=".intval($id_panier)))
		return false;

	$cookie = $row['cookie_panier'];
	$items = paniers_explique_cookie($cookie);

	// trouver et modifier l'item
	foreach ($items as $k=>$it){
		if ($it['id']==$item['id']
		  AND (!isset($item['id_syndic']) OR $it['id_syndic']==$item['id_syndic'])
			AND (!isset($item['category']) OR $it['category']==$item['category'])
			){
			if (!isset($item['quantity']) OR !$item['quantity']){
				unset($items[$k]);
			}
			elseif (isset($item['quantity']) AND isset($item['net_price']) AND isset($item['gross_price'])) {
				$items[$k]['quantity'] = $item['quantity'];
				$items[$k]['net_price'] = $item['net_price'];
				$items[$k]['gross_price'] = $item['gross_price'];
			}
			continue; // pas la peine de continuer
		}
	}

	if (count($items)){
		$cookie = paniers_make_cookie($items);
		sql_updateq("spip_paniers",
			array(
			'cookie_panier' => $cookie,
			'date_panier' => date('Y-m-d H:i:s'),
			),
			"id_panier=".intval($id_panier)
		);
	}
	else {
		sql_delete("spip_paniers","id_panier=".intval($id_panier));
	}

	return $items;
}