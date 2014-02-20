<?php
/**
 * Plugin Paniers
 * Gestion des paniers
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */


define('_PANIER_EPHEMERE_TTL',24*3600); // duree des paniers pour les visiteurs non enregistres (sur foi du cookie seul)
define('_PANIER_ENREGISTRES_TTL',2*24*3600); // duree des paniers pour les visiteurs authentifies (sur foi de son authent)

function paniers_setcookie($nom,$valeur){
	static $expire = NULL;
	if ($expire==NULL) $expire = time()+_PANIER_EPHEMERE_TTL;
	include_spip('inc/cookie');
	spip_setcookie($nom,$_COOKIE[$nom] = $valeur, $expire);
}
function paniers_delcookie($nom){
	include_spip('inc/cookie');
	spip_setcookie($nom,"", 0);
	unset($_COOKIE[$nom]);
}

function paniers_calcule_cle($id_panier,$id_auteur){
	// soit le panier est encore anonyme sinon l'auteur doit correspondre
	// on n'accepte pas le cas auteur deconnecte, panier identifie
	$row = sql_fetsel("id_panier,id_auteur,date_panier","spip_paniers","id_panier=".intval($id_panier)." AND (id_auteur=0 OR id_auteur=".intval($id_auteur).")");
	if (!$row) return false;
	return md5(implode(';',array_values($row)));
}

function paniers_mise_a_jour_cle($id_panier,$id_auteur){
	$cle = paniers_calcule_cle($id_panier,$id_auteur);
	if ($cle){
		paniers_setcookie('id_panier',$id_panier);
		paniers_setcookie('id_panier_key',$cle);
		$GLOBALS['visiteur_session']['id_panier'] = $id_panier;
	}
	else {
		paniers_delcookie('id_panier');
		paniers_delcookie('id_panier_key');
		if (isset($GLOBALS['visiteur_session']['id_panier']))
			unset($GLOBALS['visiteur_session']['id_panier']);
	}
}

function paniers_id_panier_encours() {
	$id_panier = 0;
	$id_auteur = isset($GLOBALS['visiteur_session']['id_auteur'])?$GLOBALS['visiteur_session']['id_auteur']:0;
	// on prend en priorite le panier existant en memoire
	// l'id et sa cle de verif doivent etre present dans les cookies et coherents sinon on oublie le panier
	if (isset($_COOKIE['id_panier']) && isset($_COOKIE['id_panier_key']) && isset($_COOKIE['panier'])){
		$id_panier = $_COOKIE['id_panier'];
		$key = $_COOKIE['id_panier_key'];
		if (!($cle = paniers_calcule_cle($id_panier,$id_auteur))
		  && $key !== $cle) {
		 	$id_panier = 0;
		 	paniers_delcookie('id_panier');
		 	paniers_delcookie('id_panier_key');
		 	#paniers_delcookie('panier');
		 	spip_log('panier errone, id_panier_key invalide','paniers');
		}
	}
	if (!$id_panier AND $id_auteur){
		// regarder si pas deja un panier memorise et pas trop vieux
		$row = sql_fetsel('id_panier,id_auteur,cookie_panier,date_panier','spip_paniers','id_auteur='.intval($id_auteur),'','date_panier DESC','0,1');
		if ($row  AND time()<strtotime($row['date_panier'])+_PANIER_ENREGISTRES_TTL) {
			$id_panier = $row['id_panier'];
			#paniers_setcookie('panier',$row['cookie_panier']);
			paniers_mise_a_jour_cle($id_panier,$id_auteur);
		}
	}
	/*
	if (!$id_panier && isset($_COOKIE['panier']) && strlen($_COOKIE['panier'])) {
		// il y a un panier en memoire qu'on ne retrouve pas, il faut le creer !
		include_spip('base/abstract_sql');
		$id_panier = spip_abstract_insert('spip_paniers',"(id_auteur,cookie_panier,maj)","("._q($id_auteur).",'',NOW())");
		spip_log("creation panier:$id_panier",'panier');
		$_COOKIE['id_panier_key'] = $_COOKIE['id_panier'] = 0; // forcer la remise a jour de la cle
	}
	*/
	#spip_log("id_encours:$id_panier/id_auteur:$id_auteur"/*.var_export($_COOKIE,true)*/,'panier');
	return $id_panier;
}

/**
 * Calcul le prix total d'un panier a partir de ses items
 * @param array $items
 * @return array
 *   array($net,$gross)
 */
function paniers_calcul_total($items,$negative_allowed=false){
	$net = 0;
	$gross = 0;
	$reduc = 1.0;
	foreach($items as $k=>$item){
		if (preg_match(',[-][0-9.]+[%],',trim($item['gross_price']))) {
			$reduc = $reduc * (1.0+floatval($item['gross_price'])/100.0);
		}
		else {
			$net += $item['net_price'];
			$gross += $item['gross_price'];
		}
	}
	if (!$negative_allowed){
		$net = max($net,0) * $reduc;
		$gross = max($gross,0) * $reduc;
	}
	return array($net,$gross);
}

/**
 * Deserialize le cookie du panier en tableau d'items explicites
 * @param string $panier
 * @return array
 */
function paniers_explique_cookie($panier){
	$items = explode('!',$panier);
	foreach($items as $k=>$item){
		if (strlen(trim($item))) {
			$items[$k] = array_map('urldecode',explode('|',$item));
			/* id, quantite, net, gross, category */
			$items[$k]['id'] = $items[$k][0];
			$items[$k]['quantity'] = $items[$k][1];
			$items[$k]['net_price'] = $items[$k][2];
			$items[$k]['gross_price'] = $items[$k][3];
			$items[$k]['category'] = $items[$k][4];
			$items[$k]['id_syndic'] = $items[$k][5];
		}
		else unset($items[$k]);
	}
	return $items;
}

/**
 * Serialize le tableau d'items du panier en chaine serializee utilisable en cookie
 * @param array $items
 * @return string
 */
function paniers_make_cookie($items){
	$produits = array();
	$reduc_fixes = array();
	$reduc_pourcent = array();
	foreach($items as $k=>$item){
		$sitem = str_replace('%25','%',implode('|',array_map('urlencode',array($item['id'],$item['quantity'],$item['net_price'],$item['gross_price'],$item['category'],$item['id_syndic']))));
		if (floatval($item['gross_price'])>0)
			$produits[] = $sitem;
		elseif(substr(trim($item['gross_price']),-1)=='%')
			$reduc_pourcent[] = $sitem;
		else
			$produits[] = $sitem;
			#$reduc_fixes[] = $sitem;
	}
	$cookie = implode('!',array_merge($produits,$reduc_fixes,$reduc_pourcent));
	return $cookie;
}

if (!function_exists('affiche_monnaie')) {
function affiche_monnaie($valeur,$decimales=2,$unite=true){
	if ($unite===true){
		$unite = "&nbsp;EUR";
		if (substr(trim($valeur),-1)=="%")
			$unite = "&nbsp;%";
	}
	if (!$unite)
		$unite=="";
	return sprintf("%.{$decimales}f",$valeur).$unite;
}
}


if (!test_espace_prive()) {
	if ($id_panier = paniers_id_panier_encours()) {
		#paniers_update_from_cookies($id_panier);
		$GLOBALS['visiteur_session']['id_panier']=$id_panier; // mettre l'id_panier dans la session !
	}
	#spip_log("panier:$id_panier:".$GLOBALS['visiteur_session']['id_auteur'].":".var_export($_COOKIE,true),'panier');
}
?>
