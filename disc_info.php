<?php

namespace seraph_wd;

if( !defined( 'ABSPATH' ) )
	exit;

function _GetOverridenLocString( $sett, $fldId, $defLoc )
{
	$loc = Gen::GetArrField( $sett, $fldId, '', '/' );
	return( Wp::SanitizeHtml( empty( $loc ) ? $defLoc : $loc ) );
}

function _GetProductNearestDiscountContent( $isLoop, $product, $productVar )
{
	$content = null;

	$sett = Plugin::SettGet();

	if( Gen::GetArrField( $sett, 'showProductNearestDiscount/onLoad', true, '/' ) )
	{
		$inputAttrs = GetWooCurProductQuantityInputAttrs( $product );
		$content = _GetProductNearestDiscountDynContent( $isLoop, $product, $productVar, (isset($inputAttrs[ 'input_value' ])?$inputAttrs[ 'input_value' ]:null) );
		if( $content )
			$content = $content[ 'content' ];
	}

	return( Ui::Tag( 'div', $content, array( 'class' => array( 'seraph-wd', 'nearest-discount' ), 'style' => array( 'display' => empty( $content ) ? 'none' : null ) ) ) );
}

function _GetProductNearestDiscountItemInfo( $product, $productVar, $items, $num = 1, $quantityAdd = 0 )
{
	$quantityCur = _GetCartProductQty( $product, $productVar, _GetCartItems(), $quantityAdd );

	$itemsNear = array(); $itemCur = null;
	foreach( $items as $item )
	{
		if( $quantityCur < $item[ 'qtyMin' ] )
		{
			$itemsNear[] = $item;
			if( $num > 1 )
			{
				$num--;
				continue;
			}

			break;
		}

			$itemCur = $item;
	}

	return( array( 'itemsNear' => $itemsNear, 'itemCur' => $itemCur, 'quantityCur' => $quantityCur ) );
}

function _GetProductNearestDiscountDynContent( $isLoop, $product, $productVar, $quantityAdd )
{

	return( '' );

}

function _GetProductDiscountTableContent( $full, $product, $productVar = null )
{
	$content = '';

	if( !$full )
		return( $content );
	return( Ui::Tag( 'div', $content, array( 'class' => array( 'seraph-wd', 'discount-table' ) ) ) );
}

function _GetProductTotalPricePreviewContent( $product, $productVar = null )
{
	$sett = Plugin::SettGet();
	$curLang = Wp::GetCurLang( 'sysdef' );

	$contentSub = '';
	if( Gen::GetArrField( $sett, 'showProductTotalPricePreview/onLoad', true, '/' ) )
	{
		$inputAttrs = GetWooCurProductQuantityInputAttrs( $product );
		$contentSub = _GetProductTotalPricePreviewDynContent( $product, $productVar, (isset($inputAttrs[ 'input_value' ])?$inputAttrs[ 'input_value' ]:null) );
	}

	$content = '';
	$content .= Ui::TagOpen( 'p', array( 'class' => array( 'seraph-wd', 'product-subtotal', 'price' ), 'style' => array( 'display' => empty( $contentSub ) ? 'none' : null ) ) );
	$content .= Ui::Tag( 'span', _GetOverridenLocString( $sett, array( 'showProductTotalPricePreview', 'loc', $curLang, 'label' ), _x( 'Label', 'ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ) ), array( 'class' => array( 'label' ) ) );
	$content .= Ui::Tag( 'span', $contentSub, array( 'class' => array( 'data' ) ) );
	$content .= Ui::TagClose( 'p' );
	return( $content );
}

function _GetProductTotalPricePreviewDynContent( $product, $productVar, $quantity )
{
	if( is_a( $product, 'WC_Product_Variable' ) && !$productVar )
		return( '' );

	$sett = Plugin::SettGet();

	if( Gen::GetArrField( $sett, 'showProductTotalPricePreview/useCart', false, '/' ) )
		$quantity = _GetCartProductQty( $product, $productVar, _GetCartItems(), $quantity );

	return( WC() -> cart -> get_product_subtotal( $productVar ? $productVar : $product, $quantity ) );
}

function _GetCurProductSaleFlashDynContent( $isLoop )
{
	ob_start();
	$isLoop ? woocommerce_show_product_loop_sale_flash() : woocommerce_show_product_sale_flash();
	return( ob_get_clean() );
}

function _GetSaleFlashTplContent( $content = null, $classesEx = '' )
{
	return( Ui::Tag( 'span', $content, array( 'class' => array( 'onsale', $classesEx ), 'style' => array( 'display' => empty( $content ) ? 'none' : null ) ) ) );
}

function _AdjustSaleFlashPartContent( $content, $type )
{
	$nd = HtmlNd::Parse( $content, LIBXML_NONET );
	$ndSpan = HtmlNd::FindByTag( $nd, 'span' );
	HtmlNd::SetAttrVal( $ndSpan, 'class', $type );
	return( HtmlNd::DeParse( $nd ) );
}

