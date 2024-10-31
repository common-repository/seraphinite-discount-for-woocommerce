<?php

namespace seraph_wd;

if( !defined( 'ABSPATH' ) )
	exit;

require_once( __DIR__ . '/Cmn/Gen.php' );
require_once( __DIR__ . '/Cmn/Ui.php' );
require_once( __DIR__ . '/Cmn/Plugin.php' );

const PLUGIN_SETT_VER								= 1;
const PLUGIN_DATA_VER								= 1;
const PLUGIN_EULA_VER								= 1;

const PLUGIN_PRODUCT_SETT_PAGE_ID					= 'discount_product_data';
const PLUGIN_PRODUCT_SETT_VER						= 2;

function OnProductOptRead_Sett( $sett, $vFrom )
{
	if( $vFrom == 1 )
	{
		$fldId = 'items';
		$items = Gen::GetArrField( $sett, $fldId, array(), '/' );
		foreach( $items as $itemKey => $item )
		{
			foreach( array( 'quantityMin', 'quantityMax', 'priceTotalMin', 'priceTotalMax' ) as $fldIdSub )
			{
				$v = (isset($item[ $fldIdSub ])?$item[ $fldIdSub ]:null);
				if( is_string( $v ) )
					Gen::SetArrField( $sett, array( $fldId, $itemKey, $fldIdSub ), $v ? ( float )$v : null );
			}
		}
	}

	return( $sett );
}

function GetWooCurrencySymbol( $currency = null )
{
	if( Gen::DoesFuncExist( 'get_woocommerce_currency_symbol' ) )
		return( get_woocommerce_currency_symbol( $currency ) );
	return( '$' );
}

function GetWooAvailableAttrs()
{
	global $wpdb;

	$a = array();

	Gen::SetArrField( $a, array( 'val' ),		array( 't' => 'v',	'classes' => array( 'vsrc' ),				'n' => esc_html_x( 'AttrTypeValLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ) );

	Gen::SetArrField( $a, array( 'sku' ),		array( 't' => 'v',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'SKU', null, 'woocommerce' ) ) ) );
	Gen::SetArrField( $a, array( 'onsale' ),	array( 't' => 'b',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'On Sale', null, 'woocommerce' ) ) ) );

	Gen::SetArrField( $a, array( 'weight' ),	array( 't' => 'r',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'Weight', null, 'woocommerce' ) ) ) );
	Gen::SetArrField( $a, array( 'length' ),	array( 't' => 'r',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'Length', null, 'woocommerce' ) ) ) );
	Gen::SetArrField( $a, array( 'width' ),		array( 't' => 'r',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'Width', null, 'woocommerce' ) ) ) );
	Gen::SetArrField( $a, array( 'height' ),	array( 't' => 'r',	'classes' => array( 'attr' ),				'n' => esc_html( Wp::GetLocString( 'Height', null, 'woocommerce' ) ) ) );

	Gen::SetArrField( $a, array( 'meta' ),		array( 't' => 'nv',	'classes' => array( 'attr', 'vsrc' ),		'n' => esc_html_x( 'AttrTypeMetaLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ) );

	if( !Gen::DoesFuncExist( 'wc_get_attribute_taxonomies' ) )
		return( $a );

	$filters = Wp::RemoveLangFilters();

	$attrTaxons = wc_get_attribute_taxonomies();
	foreach( $attrTaxons as $attrTaxon )
	{
		$id = $attrTaxon -> attribute_id;

		$terms = get_terms( wc_attribute_taxonomy_name( $attrTaxon -> attribute_name ), array( 'hide_empty' => false ) );
		if( empty( $terms ) )
			continue;

		$attrIdVals = array();
		foreach( $terms as $term )
			$attrIdVals[ $term -> term_id ] = $term -> name;

		asort( $attrIdVals, SORT_STRING | SORT_FLAG_CASE );

		Gen::SetArrField( $a, array( 'a_' . $id ), array( 't' => 'e', 'classes' => array( 'attr', 'vsrc' ), 'n' => $attrTaxon -> attribute_label, 'vs' => $attrIdVals, 'vso' => array_keys( $attrIdVals ) ) );
	}

	$postsMetas = $wpdb -> get_col( 'SELECT meta_value FROM ' . $wpdb -> postmeta . ' WHERE meta_key=\'_product_attributes\'' );
	if( !is_array( $postsMetas ) )
		$postsMetas = array();

	foreach( $postsMetas as $postMeta )
	{
		$postMeta = @maybe_unserialize( $postMeta );
		if( !is_array( $postMeta ) )
			continue;

		foreach( $postMeta as $attrId => $attr )
		{
			if( !is_array( $attr ) )
				continue;

			if( (isset($attr[ 'is_taxonomy' ])?$attr[ 'is_taxonomy' ]:null) )
				continue;

			$attrName = (isset($attr[ 'name' ])?$attr[ 'name' ]:null);

			$attrIdVals = array();
			foreach( @explode( '|', ( string )(isset($attr[ 'value' ])?$attr[ 'value' ]:'') ) as $value )
			{
				$value = trim( $value );
				$attrIdVals[ mb_strtolower( $value ) ] = $value;
			}

			asort( $attrIdVals, SORT_STRING | SORT_FLAG_CASE );

			Gen::SetArrField( $a, array( 'ap_' . mb_strtolower( $attrId ) ), array( 't' => 'e', 'classes' => array( 'attr', 'vsrc' ), 'n' => $attrName, 'vs' => $attrIdVals, 'vso' => array_keys( $attrIdVals ) ) );
		}
	}

	Wp::AddFilters( $filters );

	return( $a );
}

function _GetWooProductQuantityInputAttrs_OnAct( $template_name, $template_path, $located, $args )
{
	global $_wooProductQuantityInputAttrsArgs;
	$_wooProductQuantityInputAttrsArgs = $args;
}

function GetWooCurProductQuantityInputAttrs( $product )
{
	global $_wooProductQuantityInputAttrsArgs;

	{
		add_action( 'woocommerce_after_template_part', 'seraph_wd\\_GetWooProductQuantityInputAttrs_OnAct', 99999, 4 );

		woocommerce_quantity_input( array(
			'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product -> get_min_purchase_quantity(), $product ),
			'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product -> get_max_purchase_quantity(), $product ),
			'input_value' => isset( $_POST[ 'quantity' ] ) ? wc_stock_amount( wp_unslash( Wp::SanitizeText( $_POST[ 'quantity' ] ) ) ) : $product -> get_min_purchase_quantity(),
		), $product, false );

		remove_action( 'woocommerce_after_template_part', 'seraph_wd\\_GetWooProductQuantityInputAttrs_OnAct', 99999 );
	}

	$res = array();
	if( $_wooProductQuantityInputAttrsArgs )
	{
		$res[ 'input_value' ] = (isset($_wooProductQuantityInputAttrsArgs[ 'input_value' ])?$_wooProductQuantityInputAttrsArgs[ 'input_value' ]:null);
		$res[ 'unit' ] = (isset($_wooProductQuantityInputAttrsArgs[ 'unit' ])?$_wooProductQuantityInputAttrsArgs[ 'unit' ]:null);
		unset( $_wooProductQuantityInputAttrsArgs );
	}

	return( $res );
}

function GetWooProdAttrsVals( $product )
{
	$a = array();

	$attrs = $product -> get_attributes();
	foreach( $attrs as $attrId => $attr )
	{
		$values = $attr -> get_options();
		if( empty( $values ) )
			continue;

		$vals = array();

		if( !$attr -> is_taxonomy() )
		{
			foreach( $values as $value )
				$vals[ mb_strtolower( $value ) ] = $value;

			Gen::SetArrField( $a, array( 'ap_' . $attrId ), $vals );
			continue;
		}

		foreach( $values as $value )
		{
			if( is_int( $value ) )
			{
				$term = get_term( $value, $attrId );
				if( $term && !is_wp_error( $term ) )
					$name = $term -> name;
				else
					$name = $value;
				$vals[ $value ] = $name;
				continue;
			}

			$term = get_term_by( 'name', $value, $attrId );
			if( $term && !is_wp_error( $term ) )
				$val = $term -> term_id;
			else
			{
				$termNew = wp_insert_term( $value, $attrId );
				if( $termNew && !is_wp_error( $termNew ) )
					$val = $termNew[ 'term_id' ];
				else
					$val = 0;
			}

			$vals[ $val ] = $value;
		}

		Gen::SetArrField( $a, array( 'a_' . $attr -> get_id() ), $vals );
	}

	return( $a );
}

function OnAdminApi_GetWooMetaData( $args )
{
	$taxs = array();
	{
		$taxonomies = get_taxonomies( null, 'objects' );
		foreach( $taxonomies as $taxonomyId => $taxonomy )
		{
			$ok = true;
			if( !in_array( 'product', Gen::GetArrField( ( array )$taxonomy, array( 'object_type' ), array() ) ) )
				$ok = false;

			if( $ok && !$taxonomy -> show_ui )
				$ok = false;

			if( $ok && strpos( $taxonomyId, 'pa_' ) === 0 )
				$ok = false;
			if( $ok && strpos( $taxonomyId, 'product_cat' ) === 0 )
				$ok = false;
			if( $ok && strpos( $taxonomyId, 'product_tag' ) === 0 )
				$ok = false;

			if( !$ok )
				continue;

			$vs = array();
			{
				$terms = get_terms( $taxonomyId );
				if( empty( $terms ) || is_wp_error( $terms ) )
					continue;

				foreach( $terms as $term )
					$vs[ $term -> term_id ] = $term -> name;
			}

			asort( $vs, SORT_STRING | SORT_FLAG_CASE );

			$taxs[ $taxonomyId ] = array( 'n' => $taxonomy -> label, 'vs' => $vs, 'vso' => array_keys( $vs ) );
		}
	}

	$cats = Wp::GetAvailableTaxonomyTerms( 'product_cat' );
	if( $cats === null )
		$cats = array();

	return( array( 'categs' => $cats, 'attrs' => GetWooAvailableAttrs(), 'taxs' => $taxs ) );
}

function OnAdminApi_GetProductNames( $args )
{
	global $wpdb;

	$items = @json_decode( @base64_decode( @rawurldecode( (isset($args[ 'items' ])?$args[ 'items' ]:null) ) ), true );
	if( !$items )
		return( array() );

	$idsQuery = array();

	$res = array();
	foreach( $items as $item )
	{
		if( empty( $item ) )
			continue;

		$id = intval( $item );
		if( $id )
		{
			$idsQuery[] = $id;
			continue;
		}

		$itemLike = esc_sql( like_escape( strtolower( $item ) ) );
		$posts = $wpdb -> get_results( 'SELECT * FROM ' . $wpdb -> posts . ' WHERE post_type=\'product\' AND (LOWER(post_title) LIKE \'' . $itemLike . '%\' OR post_name LIKE \'' . $itemLike . '%\') LIMIT 5' );
		if( is_array( $posts ) )
			foreach( $posts as $post )
				$res[ intval( $post -> ID ) ] = array( 'n' => get_the_title( $post ), 's' => $item );
	}

	if( count( $idsQuery ) )
	{
		Wp::RemoveLangFilters();

		$posts = get_posts( array( 'post_type' => 'product', 'include' => $idsQuery, 'numberposts' => -1, 'suppress_filters' => false ) );

		foreach( $posts as $post )
			$res[ intval( $post -> ID ) ] = array( 'n' => get_the_title( $post ), 's' => '' . $post -> ID );
	}

	return( $res );
}

function OnApi_GetProductsPriceContent( $args )
{
	if( !Gen::DoesFuncExist( 'wc_get_product' ) )
	{
		http_response_code( 599 );
		return;
	}

	global $post;
	global $product, $seraph_wd_g_productDispData;

	$aRequest = Gen::GetArrField( Net::GetQueryObjArg( (isset($args[ 'a' ])?$args[ 'a' ]:null) ), array( '' ), array() );
	$aRes = array();
	foreach( $aRequest as $dataId => $args )
	{
		$res = array();

		$idVar = @intval( (isset($args[ 'iv' ])?$args[ 'iv' ]:null) );

		$product = wc_get_product( intval( $args[ 'i' ] ) );
		if( !$product )
		{
			$aRes[ $dataId ] = $res;
			continue;
		}

		$post = get_post( $product -> get_id() );

		$quantity = ( float )$args[ 'q' ];
		$isLoop = !!$args[ 'l' ];
		$prodIdAddQuantity = $idVar ? $idVar : $product -> get_id();
		_AdjustPriceFromQty_SetAdditionalQty( $prodIdAddQuantity, $quantity );

		$productVar = $idVar ? wc_get_product( $idVar ) : null;
		$seraph_wd_g_productDispData = GetProductDispData( $product );
		$productVarMaxSaleCoeff = Gen::GetArrField( $seraph_wd_g_productDispData, array( 'varMaxSaleCoeff' ) );

		$sett = Plugin::SettGet();
		_PriceNormalizePeriod_InitForMethod( $sett, $product, $isLoop );

		{
			_PriceVarRangeAdjustEnable( $product, Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedVarsOriginalPrice' ), false ), true );
			$res[ 'p' ] = $product -> get_price_html();
			_PriceVarRangeAdjustEnable( $product, Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedVarsOriginalPrice' ), false ), false );
		}

		if( $productVar )
			$res[ 'pv' ] = $productVar -> get_price_html();

		if( !!(isset($args[ 's' ])?$args[ 's' ]:null) )
			$res[ 's' ] = _GetCurProductSaleFlashDynContent( $isLoop );

		if( !!(isset($args[ 'pt' ])?$args[ 'pt' ]:null) )
			$res[ 'pt' ] = _GetProductTotalPricePreviewDynContent( $product, $productVarMaxSaleCoeff, $quantity );

		if( !!(isset($args[ 'nd' ])?$args[ 'nd' ]:null) )
			$res[ 'nd' ] = _GetProductNearestDiscountDynContent( $isLoop, $product, $productVarMaxSaleCoeff, $quantity );

		if( !!(isset($args[ 't' ])?$args[ 't' ]:null) )
			$res[ 't' ] = _GetProductDiscountTableContent( false, $product, $productVarMaxSaleCoeff );

		_AdjustPriceFromQty_SetAdditionalQty( $prodIdAddQuantity, 0 );

		$aRes[ $dataId ] = $res;
	}

	return( $aRes );
}

function LogWrite( $text, $severity = Ui::MsgInfo, $category = 'DEBUG' )
{
	Gen::LogWrite( WP_CONTENT_DIR . '/seraph_wd/logs/log.txt', $text, $severity, $category );
}

function LogClear()
{
	Gen::LogClear( WP_CONTENT_DIR . '/seraph_wd/logs/log.txt' );
}

