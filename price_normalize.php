<?php

namespace seraph_wd;

if( !defined( 'ABSPATH' ) )
	exit;

const PRICENORMALIZEPERIOD_FLT_PRIOR	= 0;

function _PriceNormalizePeriod_InitForMethod( $sett, $product, $isLoop )
{
	if( !Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/enable', false, '/' ) )
		return;

	if( $isLoop )
	{
		if( Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/prodLoop', true, '/' ) )
			_PriceNormalizePeriod_Enable( $product, true );
	}
	else
	{
		if( Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/prodAddToCartForm', true, '/' ) )
			_PriceNormalizePeriod_Enable( $product, true );
	}
}

function _PriceNormalizePeriod_Init( $sett )
{
	if( !Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/enable', false, '/' ) )
		return;

	if( Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/prodLoop', true, '/' ) )
	{
		add_filter( 'woocommerce_before_shop_loop_item', function() { global $product; _PriceNormalizePeriod_Enable( $product, true ); } );
		add_filter( 'woocommerce_after_shop_loop_item', function() { global $product; _PriceNormalizePeriod_Enable( $product, false ); } );
	}

	if( Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/prodAddToCartForm', true, '/' ) )
	{
		add_filter( 'woocommerce_before_single_product', function() { global $product; _PriceNormalizePeriod_Enable( $product, true ); }, -10 );
		add_filter( 'woocommerce_after_single_product', function() { global $product; _PriceNormalizePeriod_Enable( $product, false ); }, 99999 );

	}

	if( Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/cartProduct', true, '/' ) )
	{
		add_filter( 'woocommerce_cart_item_product', function( $product, $cart_item, $cart_item_key ) { _PriceNormalizePeriod_Enable( null, false ); _PriceNormalizePeriod_Enable( $product, true ); return( $product ); }, 0, 3 );
	}
}

function _PriceNormalizePeriod_GetTimeLen( $period, $periodInterval )
{
	$res = 0.0;
	switch( $period )
	{
	case 'day':			$res = 1.0; break;
	case 'week':		$res = 7.0; break;
	case 'month':		$res = 365.25 / 12; break;
	case 'year':		$res = 365.25; break;
	}

	return( $res * ( float )$periodInterval );
}

function _PriceNormalizePeriod_GetCoeff( $product )
{
	global $_PriceNormalizePeriod_Ctx;

	$ctxSave = $_PriceNormalizePeriod_Ctx;
	$_PriceNormalizePeriod_Ctx = null;

	$period = \WC_Subscriptions_Product::get_period( $product );
	$periodInterval = \WC_Subscriptions_Product::get_interval( $product );

	$_PriceNormalizePeriod_Ctx = $ctxSave;

	$denom = _PriceNormalizePeriod_GetTimeLen( $period, $periodInterval );
	if( $denom == 0 )
		return( 1.0 );

	return( _PriceNormalizePeriod_GetTimeLen( $_PriceNormalizePeriod_Ctx[ 'period' ], $_PriceNormalizePeriod_Ctx[ 'periodInterval' ] ) / $denom );
}

function _PriceNormalizePeriod_Enable( $product, $enable )
{
	global $_PriceNormalizePeriod_Ctx;

	if( !$enable )
	{
		if( !$_PriceNormalizePeriod_Ctx )
			return;

		$ctxPrev = $_PriceNormalizePeriod_Ctx[ 'ctxPrev' ];
		if( $ctxPrev )
		{
			$_PriceNormalizePeriod_Ctx = $ctxPrev;
			return;
		}

		remove_filter( 'woocommerce_product_variation_get__subscription_price',	'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_variation_get__subscription_price', 99999 );
		remove_filter( 'woocommerce_variable_subscription_price_html',			'seraph_wd\\_PriceNormalizePeriod_woocommerce_variable_subscription_price_html', 99999 );

		remove_filter( 'woocommerce_product_get_price',							'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_variation_get_price',				'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_get_sale_price',					'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_sale_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_variation_get_sale_price',			'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_sale_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_get_regular_price',					'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_regular_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_variation_get_regular_price',		'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_regular_price', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_product_get__min_price_variation_id',		'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get__min_price_variation_id', PRICENORMALIZEPERIOD_FLT_PRIOR );

		remove_filter( 'woocommerce_subscriptions_product_period',				'seraph_wd\\_PriceNormalizePeriod_woocommerce_subscriptions_product_period', PRICENORMALIZEPERIOD_FLT_PRIOR );
		remove_filter( 'woocommerce_subscriptions_product_period_interval',		'seraph_wd\\_PriceNormalizePeriod_woocommerce_subscriptions_product_period_interval', PRICENORMALIZEPERIOD_FLT_PRIOR );

		$_PriceNormalizePeriod_Ctx = null;
		return;
	}

	if( !Gen::DoesFuncExist( 'WC_Subscriptions_Product::is_subscription' ) || !\WC_Subscriptions_Product::is_subscription( $product ) )
		return;

	$sett = Plugin::SettGet();

	$ctxPrev = $_PriceNormalizePeriod_Ctx;

	$_PriceNormalizePeriod_Ctx = array(
		'period' => Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/period', 'day', '/' ),
		'periodInterval' => Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/periodInterval', '1', '/' ),
		'diffPrices' => Gen::GetArrField( $sett, 'showNormalizedPeriodPrices/prodVarsRelDiscount', false, '/' ),
		'products' => array(),
		'ctxPrev' => $ctxPrev );

	if( !$ctxPrev )
	{
		add_filter( 'woocommerce_product_variation_get__subscription_price',	'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_variation_get__subscription_price', 99999, 2 );
		add_filter( 'woocommerce_variable_subscription_price_html',				'seraph_wd\\_PriceNormalizePeriod_woocommerce_variable_subscription_price_html', 99999, 2 );

		add_filter( 'woocommerce_product_get_price',							'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_variation_get_price',					'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_get_sale_price',						'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_sale_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price',				'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_sale_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_get_regular_price',					'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_regular_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price',			'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get_regular_price', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_product_get__min_price_variation_id',			'seraph_wd\\_PriceNormalizePeriod_woocommerce_product_get__min_price_variation_id', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );

		add_filter( 'woocommerce_subscriptions_product_period',					'seraph_wd\\_PriceNormalizePeriod_woocommerce_subscriptions_product_period', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
		add_filter( 'woocommerce_subscriptions_product_period_interval',		'seraph_wd\\_PriceNormalizePeriod_woocommerce_subscriptions_product_period_interval', PRICENORMALIZEPERIOD_FLT_PRIOR, 2 );
	}

	if( @is_a( $product, 'WC_Product_Variation' ) )
	{
		$parentId = $product -> get_parent_id();
		if( $parentId )
			$product = wc_get_product( $parentId );
	}

	if( @is_a( $product, 'WC_Product_Variable' ) )
	{
		$vars = $product -> get_available_variations();

		$productPriceMin = null;
		$productPriceMax = null;
		foreach( $vars as $var )
		{
			$productSub = wc_get_product( (isset($var[ 'variation_id' ])?$var[ 'variation_id' ]:null) );
			if( !$productSub )
				continue;

			$_PriceNormalizePeriod_Ctx[ 'products' ][ $productSub -> get_id() ] = array( 'coeffCorr' => _PriceNormalizePeriod_GetCoeff( $productSub ) );

			$price = $productSub -> get_price();

			if( !$productPriceMin || $productPriceMin -> get_price() > $price )
				$productPriceMin = $productSub;

			if( !$productPriceMax || $productPriceMax -> get_price() < $price )
				$productPriceMax = $productSub;
		}

		if( $productPriceMin )
			$_PriceNormalizePeriod_Ctx[ 'productPriceMin' ] = $productPriceMin;

		if( $productPriceMax )
			$_PriceNormalizePeriod_Ctx[ 'productPriceMax' ] = $productPriceMax;
	}
	else
	{
		$_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ] = array( 'coeffCorr' => _PriceNormalizePeriod_GetCoeff( $product ) );
	}
}

function _PriceNormalizePeriod_woocommerce_product_variation_get__subscription_price( $price, $product )
{
	global $wp_filter;

	$fltOld = $wp_filter[ 'woocommerce_product_variation_get__subscription_price' ]; unset( $wp_filter[ 'woocommerce_product_variation_get__subscription_price' ] );
	$price = $product -> get_regular_price();
	$wp_filter[ 'woocommerce_product_variation_get__subscription_price' ] = $fltOld;
	return( $price );
}

function _PriceNormalizePeriod_woocommerce_variable_subscription_price_html( $priceContent, $product )
{
	global $wp_filter;
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $priceContent );

	$productPriceMin = (isset($_PriceNormalizePeriod_Ctx[ 'diffPrices' ])?$_PriceNormalizePeriod_Ctx[ 'diffPrices' ]:null) ? (isset($_PriceNormalizePeriod_Ctx[ 'productPriceMin' ])?$_PriceNormalizePeriod_Ctx[ 'productPriceMin' ]:null) : null;
	if( !$productPriceMin )
		return( $priceContent );

	$fltOld = $wp_filter[ 'woocommerce_variable_subscription_price_html' ]; unset( $wp_filter[ 'woocommerce_variable_subscription_price_html' ] );
	$priceContent = $productPriceMin -> get_price_html();
	$wp_filter[ 'woocommerce_variable_subscription_price_html' ] = $fltOld;

	return( $priceContent );
}

function _PriceNormalizePeriod_woocommerce_product_get_price( $price, $product )
{
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $price );

	$productCorr = (isset($_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ])?$_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ]:null);
	if( $productCorr )
		$price = $price * $productCorr[ 'coeffCorr' ];

	return( $price );
}

function _PriceNormalizePeriod_woocommerce_product_get_sale_price( $price, $product )
{
	global $wp_filter;
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $price );

	$productPriceMax = (isset($_PriceNormalizePeriod_Ctx[ 'diffPrices' ])?$_PriceNormalizePeriod_Ctx[ 'diffPrices' ]:null) ? (isset($_PriceNormalizePeriod_Ctx[ 'productPriceMax' ])?$_PriceNormalizePeriod_Ctx[ 'productPriceMax' ]:null) : null;
	if( $productPriceMax && $productPriceMax -> get_id() != $product -> get_id() )
	{
		$fltOld1 = $wp_filter[ 'woocommerce_product_get_price' ]; unset( $wp_filter[ 'woocommerce_product_get_price' ] );
		$fltOld2 = $wp_filter[ 'woocommerce_product_variation_get_price' ]; unset( $wp_filter[ 'woocommerce_product_variation_get_price' ] );

		$price = $product -> get_price();

		$wp_filter[ 'woocommerce_product_get_price' ] = $fltOld1;
		$wp_filter[ 'woocommerce_product_variation_get_price' ] = $fltOld2;
	}

	if( $price !== '' )
	{
		$productCorr = (isset($_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ])?$_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ]:null);
		if( $productCorr )
			$price = $price * $productCorr[ 'coeffCorr' ];
	}

	return( $price );
}

function _PriceNormalizePeriod_woocommerce_product_get_regular_price( $price, $product )
{
	global $wp_filter;
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $price );

	$productCorr = null;

	$productPriceMax = (isset($_PriceNormalizePeriod_Ctx[ 'diffPrices' ])?$_PriceNormalizePeriod_Ctx[ 'diffPrices' ]:null) ? (isset($_PriceNormalizePeriod_Ctx[ 'productPriceMax' ])?$_PriceNormalizePeriod_Ctx[ 'productPriceMax' ]:null) : null;
	if( $productPriceMax && $productPriceMax -> get_id() != $product -> get_id() )
	{
		$fltOld1 = $wp_filter[ 'woocommerce_product_get_regular_price' ]; unset( $wp_filter[ 'woocommerce_product_get_regular_price' ] );
		$fltOld2 = $wp_filter[ 'woocommerce_product_variation_get_regular_price' ]; unset( $wp_filter[ 'woocommerce_product_variation_get_regular_price' ] );

		$price = $productPriceMax -> get_regular_price();

		$wp_filter[ 'woocommerce_product_get_regular_price' ] = $fltOld1;
		$wp_filter[ 'woocommerce_product_variation_get_regular_price' ] = $fltOld2;

		$productCorr = (isset($_PriceNormalizePeriod_Ctx[ 'products' ][ $productPriceMax -> get_id() ])?$_PriceNormalizePeriod_Ctx[ 'products' ][ $productPriceMax -> get_id() ]:null);
	}
	else
		$productCorr = (isset($_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ])?$_PriceNormalizePeriod_Ctx[ 'products' ][ $product -> get_id() ]:null);

	if( $productCorr )
		$price = $price * $productCorr[ 'coeffCorr' ];

	return( $price );
}

function _PriceNormalizePeriod_woocommerce_product_get__min_price_variation_id( $id, $product )
{
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $id );

	$productPriceMin = (isset($_PriceNormalizePeriod_Ctx[ 'productPriceMin' ])?$_PriceNormalizePeriod_Ctx[ 'productPriceMin' ]:null);
	if( !$productPriceMin )
		return( $id );

	return( $productPriceMin -> get_id() );
}

function _PriceNormalizePeriod_woocommerce_subscriptions_product_period( $v, $product )
{
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $v );

	return( $_PriceNormalizePeriod_Ctx[ 'period' ] );
}

function _PriceNormalizePeriod_woocommerce_subscriptions_product_period_interval( $v, $product )
{
	global $_PriceNormalizePeriod_Ctx;

	if( !$_PriceNormalizePeriod_Ctx )
		return( $v );

	return( $_PriceNormalizePeriod_Ctx[ 'periodInterval' ] );
}

