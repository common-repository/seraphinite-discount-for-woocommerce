<?php

namespace seraph_wd;

if( !defined( 'ABSPATH' ) )
	exit;

include( __DIR__ . '/common.php' );
include( __DIR__ . '/price_normalize.php' );
include( __DIR__ . '/disc_info.php' );

Plugin::Init();

function OnInit( $isAdminMode )
{
	if( $isAdminMode && !wp_doing_ajax() )
		return;

	$sett = Plugin::SettGet();

	_PriceNormalizePeriod_Init( $sett );

	if( Gen::GetArrField( $sett, 'productUpdateDataDyn', true, '/' ) )
	{

		{
			add_action( 'woocommerce_after_single_product',
				function()
				{
					_FinalizeProductsView();
				}
			);
		}

		{
			add_action( 'woocommerce_product_loop_end',
				function( $content )
				{
					_FinalizeProductsView();
					return( $content );
				}
			);
		}
	}

	if( Gen::GetArrField( $sett, array( 'adjustOnSale', 'enable' ), true ) )
	{
		_PriceAdjustEnable();

		add_action( 'the_post',
			function()
			{
				global $product, $seraph_wd_g_productDispData;

				$seraph_wd_g_productDispData = GetProductDispData( $product );

			}
		, 99, 0 );
		do_action( 'woocommerce_before_single_product' );

		add_action( 'woocommerce_before_template_part',
			function( $template_name, $template_path, $located, $args )
			{
				global $product;

				if( in_array( $template_name, array( 'single-product/price.php', 'loop/price.php' ) ) )
				{
					$sett = Plugin::SettGet();

					_PriceVarRangeAdjustEnable( $product, Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedVarsOriginalPrice' ), false ), true );
					return;
				}

				if( !in_array( $template_name, array( 'single-product/sale-flash.php', 'loop/sale-flash.php' ) ) )
					return;

				$sett = Plugin::SettGet();

				if( Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedOriginalSale' ), true ) )
					_PriceAdjustEnable( false );

				ob_start();
			}
			, 0, 4
		);

		add_action( 'woocommerce_after_template_part',
			function( $template_name, $template_path, $located, $args )
			{
				global $product, $seraph_wd_g_productDispData;

				if( in_array( $template_name, array( 'single-product/price.php', 'loop/price.php' ) ) )
				{
					$sett = Plugin::SettGet();

					_PriceVarRangeAdjustEnable( $product, Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedVarsOriginalPrice' ), false ), false );
					return;
				}

				if( !in_array( $template_name, array( 'single-product/sale-flash.php', 'loop/sale-flash.php' ) ) )
					return;

				$sett = Plugin::SettGet();

				$content = ob_get_clean();

				if( Gen::GetArrField( $sett, array( 'adjustOnSale', 'showSeparatedOriginalSale' ), true ) )
				{
					_PriceAdjustEnable( true );

					$saleExtraCoeffMin = null;
					$saleExtraCoeffMax = null;
					foreach( Gen::GetArrField( $seraph_wd_g_productDispData, array( 'vars' ), array( $product ) ) as $productWithPrice )
					{
						$salePrice = $productWithPrice -> get_sale_price();
						if( !is_numeric( $salePrice ) )
							continue;

						$salePriceOrig = $productWithPrice -> get_sale_price( 'original' );

						$saleExtraCoeff = null;
						if( is_numeric( $salePriceOrig ) )
						{
							if( $salePrice < $salePriceOrig )
								$saleExtraCoeff = 1.0 - ( float )$salePrice / ( float )$salePriceOrig;
						}
						else
							$saleExtraCoeff = 1.0 - ( float )$salePrice / ( float )$productWithPrice -> get_regular_price();

						if( !$saleExtraCoeff )
							continue;

						$saleExtraCoeff = round( $saleExtraCoeff, 2 );

						if( !$saleExtraCoeffMin || $saleExtraCoeffMin > $saleExtraCoeff )
							$saleExtraCoeffMin = $saleExtraCoeff;
						if( !$saleExtraCoeffMax || $saleExtraCoeffMax < $saleExtraCoeff )
							$saleExtraCoeffMax = $saleExtraCoeff;
					}

					if( $saleExtraCoeffMin || $saleExtraCoeffMax )
					{
						$curLang = Wp::GetCurLang( 'sysdef' );

						if( $saleExtraCoeffMin && $saleExtraCoeffMax && $saleExtraCoeffMin != $saleExtraCoeffMax )
							$saleFlashExtraCont = Gen::StrPrintf( _GetOverridenLocString( $sett, array( 'adjustOnSale', 'loc', $curLang, 'saleRangeFlashExtra' ), _x( 'SaleRangeFlashExtra_%1$s%2$s', 'AdjustOnSale', 'seraphinite-discount-for-woocommerce' ) ), array( Gen::FloatToStr( 100 * $saleExtraCoeffMin ) . '%', Gen::FloatToStr( 100 * $saleExtraCoeffMax ) . '%' ) );
						else
							$saleFlashExtraCont = Gen::StrPrintf( _GetOverridenLocString( $sett, array( 'adjustOnSale', 'loc', $curLang, 'saleFlashExtra' ), _x( 'SaleFlashExtra_%1$s', 'AdjustOnSale', 'seraphinite-discount-for-woocommerce' ) ), array( Gen::FloatToStr( 100 * ( $saleExtraCoeffMax ? $saleExtraCoeffMax : $saleExtraCoeffMin ) ) . '%' ) );

						$content = _GetSaleFlashTplContent( _AdjustSaleFlashPartContent( $content, 'base' ) . $saleFlashExtraCont, 'group' );
					}
				}

				if( empty( $content ) )
					$content = _GetSaleFlashTplContent();

				echo( $content );
			}
			, 99999, 4
		);
	}

	if( Gen::GetArrField( $sett, 'showProductTotalPricePreview/enable', false, '/' ) )
		add_action( 'woocommerce_after_add_to_cart_form',
			function()
			{
				global $product, $seraph_wd_g_productDispData;
				echo( _GetProductTotalPricePreviewContent( $product, Gen::GetArrField( $seraph_wd_g_productDispData, array( 'varMaxSaleCoeff' ) ) ) );
			}
		);

}

function OnActivate()
{
}

function OnDeactivate()
{
}

function _ParseActionHookMode( $mode )
{
	if( empty( $mode ) )
		return( null );

	$res = explode( ':', $mode );
	if( count( $res ) < 2 )
		$res[ 1 ] = 10;
	return( $res );
}

function _AddMenus( $accepted = false )
{
	add_submenu_page( 'woocommerce', Plugin::GetSettingsTitle(), Plugin::GetNavMenuTitle(), 'manage_woocommerce', 'seraph_wd_settings', $accepted ? 'seraph_wd\\_SettingsPage' : 'seraph_wd\\Plugin::OutputNotAcceptedPageContent' );
}

function OnInitAdminModeNotAccepted()
{
	add_action( 'admin_menu',
		function()
		{
			_AddMenus();
		}
	);
}

function OnInitAdminMode()
{
	add_action( 'admin_init',
		function()
		{
			if( isset( $_POST[ 'seraph_wd_saveSettings' ] ) )
			{
				unset( $_POST[ 'seraph_wd_saveSettings' ] );
				Plugin::ReloadWithPostOpRes( array( 'saveSettings' => _OnSaveSettings( $_POST ) ) );
				exit;
			}
		}
	);

	add_action( 'seraph_wd_postOpsRes',
		function( $res )
		{
			if( ( $hr = (isset($res[ 'saveSettings' ])?$res[ 'saveSettings' ]:null) ) !== null )
				echo( Plugin::Sett_SaveResultBannerMsg( $hr, Ui::MsgOptDismissible ) );
		}
	);

	add_action( 'add_meta_boxes',
		function()
		{
			$rmtCfg = PluginRmtCfg::Get();
			$sett = Plugin::SettGet();

			Ui::MetaboxAdd(
				'seraph_wd_settings',
				Plugin::GetNavMenuTitle() . Ui::Tag( 'span', Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.ProdSett' ), Ui::AdminHelpBtnModeBlockHeader ) ),
				'seraph_wd\\_ProductSettingsMetabox', null,
				'product', 'normal', 'core'
			);
		}
	);

	add_action( 'admin_menu',
		function()
		{
			_AddMenus( true );
		}
		, 99999
	);

	add_action( 'save_post', 'seraph_wd\\_OnProductSettingsSave' );
}

function _FinalizeProductsView()
{
	if( !function_exists( 'wp_add_inline_script' ) )
		return;

	global $seraph_wd_g_bViewInited;

	if( $seraph_wd_g_bViewInited )
		return;

	$handleScript = Plugin::ScriptId( 'View' );
	{
		$cmnScr = array( 'Cmn', 'Gen', 'Net' );
		Plugin::CmnScripts( $cmnScr );
		wp_enqueue_script( $handleScript, add_query_arg( Plugin::GetFileUrlPackageParams(), Plugin::FileUrl( 'View.js', __FILE__ ) ), Plugin::CmnScriptId( $cmnScr ), '2.4.5' );
	}

	wp_add_inline_script( $handleScript, 'document.addEventListener("DOMContentLoaded",function(){seraph_wd.View.Init("' . Plugin::GetApiUri() . '");});' );
	$seraph_wd_g_bViewInited = true;
}

function _ShowItemsSettings( $rmtCfg, $sett, $currencySymb, $ns, $editorAreaCssPath, $isPaidLockedContent, $isProfile = false )
{
	$fldId = 'items';
	$items = Gen::GetArrField( $sett, $fldId, array(), '/' );

	$itemsListPrms = array( 'editorAreaCssPath' => $editorAreaCssPath, 'sortable' => true );

	echo( Ui::ItemsList( $itemsListPrms, $items, $ns . $fldId,
		function( $cbArgs, $idItems, $items, $itemKey, $item )
		{
			extract( $cbArgs );

			ob_start();

			echo( Ui::SepLine() );

			echo( Ui::SettBlock_Begin( array( 'class' => 'compact', 'style' => array( 'margin-top' => 0 ) ) ) );
			{
				echo( Ui::SettBlock_Item_Begin( esc_html_x( 'EnabledLbl', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.ProdSettItem_Enable' ) ) ) );
				{
					$fldId = 'enable';
					echo( Ui::CheckBox( null, $idItems . '/' . $itemKey . '/' . $fldId, Gen::GetArrField( $item, $fldId, true, '/' ), true ) );
				}
				echo( Ui::SettBlock_Item_End() );

				echo( Ui::SettBlock_Item_Begin( esc_html_x( 'ConditionLbl', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminBtnsBlock( array( array( 'type' => Ui::AdminBtn_Help, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.ProdSettItem_Condition' ) ), Plugin::AdminBtnsBlock_GetPaidContent( $isPaidLockedContent ) ), Ui::AdminHelpBtnModeText ) ) );
				{
					$fldIdConditionType = 'condType';
					$condType = Gen::GetArrField( $item, $fldIdConditionType, 'quantity', '/' );

					echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'class' => 'std', 'style' => array( 'width' => '100%' ) ) ) . Ui::TagOpen( 'tr' ) );
					{
						{
							$comboItems = array(
								'quantity' => array( esc_html_x( 'QuantityLbl', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ) ),

							);

							echo( Ui::Tag( 'td', Ui::ComboBox( $idItems . '/' . $itemKey . '/' . $fldIdConditionType, $comboItems, $condType, true, array( 'style' => array( 'width' => 'auto' ), 'onchange' => 'seraph_wd.Ui.ComboShowDependedItems( this, this.parentNode.parentNode )' ) ), array( 'style' => array( 'width' => '1px' ) ) ) );
						}

						echo( Ui::TagOpen( 'td', array( 'class' => 'ns-quantity', 'style' => array( 'display' => ( $condType != 'quantity' ) ? 'none' : null ) ) ) );
						{
							$fldId1 = 'quantityMin';
							$fldId2 = 'quantityMax';

							echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId1, Gen::GetArrField( $item, $fldId1, 2.0, '/' ), array( 'step' => 'any', 'min' => '0', 'placeholder' => _x( 'MinPhlr', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '45%', 'max-width' => '10em' ) ), true ) );
							echo( Ui::Tag( 'span', '-', array( 'style' => array( 'display' => 'inline-block', 'width' => '10%', 'max-width' => '1.5em', 'text-align' => 'center' ) ) ) );
							echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId2, Gen::GetArrField( $item, $fldId2, null, '/' ), array( 'step' => 'any', 'min' => '0', 'placeholder' => _x( 'UnlimPhlr', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '45%', 'max-width' => '10em' ) ), true ) );
						}
						echo( Ui::TagClose( 'td' ) );

						echo( Ui::TagOpen( 'td', array( 'class' => 'ns-priceTotal', 'style' => array( 'display' => ( $condType != 'priceTotal' ) ? 'none' : null ) ) ) );
						{
							$fldId1 = 'priceTotalMin';
							$fldId2 = 'priceTotalMax';

							echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId1, Gen::GetArrField( $item, $fldId1, 100.0, '/' ), array( 'step' => 'any', 'min' => '0', 'placeholder' => _x( 'MinPhlr', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '45%', 'max-width' => '10em' ) ), true ) );
							echo( Ui::Tag( 'span', '-', array( 'style' => array( 'display' => 'inline-block', 'width' => '10%', 'max-width' => '1.5em', 'text-align' => 'center' ) ) ) );
							echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId2, Gen::GetArrField( $item, $fldId2, null, '/' ), array( 'step' => 'any', 'min' => '0', 'placeholder' => _x( 'UnlimPhlr', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '45%', 'max-width' => '10em' ) ), true ) );
						}
						echo( Ui::TagClose( 'td' ) );
					}
					echo( Ui::TagClose( 'tr' ) . Ui::SettBlock_ItemSubTbl_End() );
				}
				echo( Ui::SettBlock_Item_End() );

				echo( Ui::SettBlock_Item_Begin( esc_html_x( 'DiscountLbl', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminBtnsBlock( array( array( 'type' => Ui::AdminBtn_Help, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.ProdSettItem_Discount' ) ), Plugin::AdminBtnsBlock_GetPaidContent( $isPaidLockedContent ) ), Ui::AdminHelpBtnModeText ) ) );
				{
					$fldIdType = 'type';
					$type = Gen::GetArrField( $item, $fldIdType, 'percent', '/' );

					{
						$comboItems = array(
							'percent' => array( '%', array( 'data-ns' => 'percent' ) ),

						);

						echo( Ui::ComboBox( $idItems . '/' . $itemKey . '/' . $fldIdType, $comboItems, $type, true, array( 'class' => 'ctlSpaceAfter', 'style' => array( 'width' => 'auto', 'display' => 'inline' ), 'onchange' => 'seraph_wd.Ui.ComboShowDependedItems( this )' ) ) );
					}

					{
						$fldId = 'percent';
						echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId, Gen::GetArrField( $item, $fldId, '5', '/' ), array( 'class' => 'ns-' . $fldId, 'step' => 'any', 'min' => '0', 'max' => '100', 'style' => array( 'width' => '45%', 'max-width' => '10em', 'display' => ( $type == 'percent' ) ? null : 'none' ) ), true ) );
					}

					{
						$fldId = 'currency';
						echo( Ui::NumberBox( $idItems . '/' . $itemKey . '/' . $fldId, Gen::GetArrField( $item, $fldId, '0.1', '/' ), array( 'class' => 'ns-' . $fldId, 'step' => 'any', 'min' => '0', 'style' => array( 'width' => '45%', 'max-width' => '10em', 'display' => ( $type == 'currency' || $type == 'currencyPerItem' ) ? null : 'none' ) ), true ) );
					}

				}
				echo( Ui::SettBlock_Item_End() );
			}
			echo( Ui::SettBlock_End() );

			echo( Ui::Tag( 'div', Ui::ItemsList_ItemOperateBtns( $itemsListPrms, array( 'class' => 'ctlSpaceAfterSm' ) ), array( 'class' => 'ctlSpaceVBefore ctlSpaceVAfter' ) ) );

			return( ob_get_clean() );
		},

		function( $cbArgs, $attrs )
		{
			Gen::SetArrField( $attrs, 'class.+', 'ctlSpaceVAfter' );
			return( Ui::Tag( 'div', Ui::Tag( 'label', Ui::ItemsList_NoItemsContent() ), $attrs ) );
		},

		get_defined_vars(), array( 'class' => 'ctlMaxSizeX' ), $isProfile ? 1 : 0
	) );

	echo( Ui::SepLine() );
	echo( Ui::Tag( 'div', Ui::ItemsList_OperateBtns( $itemsListPrms, array( 'class' => 'ctlSpaceAfter', 'style' => array( 'margin-left' => 0 ) ) ), array( 'class' => 'ctlSpaceVBefore' ) ) );
}

function _SaveItemsSettings( &$sett, $ns, $args )
{
	$fldId = 'items';
	Gen::SetArrField( $sett, $fldId, Ui::ItemsList_GetSaveItems( $ns . $fldId, '/', $args,
		function( $cbArgs, $idItems, $itemKey, $item, $args )
		{
			$item = array();

			{
				$fldId = 'enable';
				Gen::SetArrField( $item, $fldId, isset( $args[ $idItems . '/' . $itemKey . '/' . $fldId ] ), '/' );
			}

			{
				$fldId = 'condType';
				Gen::SetArrField( $item, $fldId, Wp::SanitizeId( (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null) ), '/' );
			}

			{
				$fldId = 'quantityMin'; $v = (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null); if( $v !== null ) Gen::SetArrField( $item, $fldId, ( float )$v, '/' );
				$fldId = 'quantityMax'; $v = (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null); if( $v ) Gen::SetArrField( $item, $fldId, ( float )$v, '/' );
			}

			{
				$fldId = 'type';
				Gen::SetArrField( $item, $fldId, Wp::SanitizeId( (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null) ), '/' );
			}

			{
				$fldId = 'percent';
				Gen::SetArrField( $item, $fldId, Wp::SanitizeText( (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null) ), '/' );
			}

			return( $item );
		}
	), '/' );
}

function _ExclInclSetting( $sett, $fldId, $ns, $n, $trivial = false, array $attrs = array(), $addNames = 'n' )
{
	$opts = $trivial ?
		array(
			'e'		=> _nx( 'ExclTrivCmbItem', 'ExclTrivCmbItem', $n, 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ),
			'in'	=> _nx( 'InclTrivCmbItem', 'InclTrivCmbItem', $n, 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ),
		) :
		array(
			'e'		=> _nx( 'ExclCmbItem', 'ExclCmbItem', $n, 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ),
			'in'	=> _nx( 'InCmbItem', 'InCmbItem', $n, 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ),
		);

	return( Ui::ComboBox( $ns . $fldId, $opts, Gen::GetArrField( $sett, $fldId, 'e', '/' ), $addNames, $attrs ) );
}

function _ShowFiltersSettings( $rmtCfg, $sett, $ns, $blockId, $isPaidLockedContent )
{
	echo( Ui::TagOpen( 'div', array( 'class' => 'blck', 'style' => array( 'margin-bottom' => '2em' ) ) ) );
	{
		echo( Ui::Tag( 'label', Ui::Tag( 'strong', esc_html_x( 'CtgsLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ) .  Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_FltCtgs' ) ) ) );

		$fldId = 'filters/categs';

		echo( _ExclInclSetting( $sett, 'filters/categsOp', $ns, 10, false, array( 'class' => 'ctlSpaceAfter ctlSpaceVAfter', 'style' => array( 'display' => 'block' ) ) ) );

		echo( Ui::TokensList( Gen::GetArrField( $sett, $fldId, array(), '/' ), $ns . $fldId, array( 'class' => 'ctlSpaceVAfter', 'style' => array( 'min-height' => '3em', 'height' => '15em', 'max-height' => '100em' ), 'data-onexpand' => 'seraph_wd.Ui.TokensMetaTree.Expand(this,seraph_wd.Settings._int.wooMetaData.categs,isExpanding)', 'data-onapply' => 'seraph_wd.Ui.TokensMetaTree.Apply(this)' ), true ) );
	}
	echo( Ui::TagClose( 'div' ) );

	echo( Ui::TagOpen( 'div', array( 'class' => 'blck', 'style' => array( 'margin-bottom' => '2em' ) ) ) );
	{
		echo( Ui::Tag( 'label', Ui::Tag( 'strong', esc_html_x( 'TagsLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ) .  Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_FltTags' ) ) ) );

		echo( _ExclInclSetting( $sett, 'filters/tagsOp', $ns, 10, false, array( 'class' => 'ctlSpaceAfter ctlSpaceVAfter', 'style' => array( 'display' => 'block' ) ) ) );

		$fldId = 'filters/tags';
		echo( Ui::TokensList( Gen::GetArrField( $sett, $fldId, array(), '/' ), $ns . $fldId, array( 'class' => 'vals ctlSpaceVAfter', 'style' => array( 'min-height' => '3em', 'height' => '3em', 'max-height' => '20em' ), 'data-onexpand' => 'seraph_wd.Ui.TokensList.InitItems(this,isExpanding)' ), true ) );

		echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'class' => 'std', 'style' => array( 'width' => '100%' ) ) ) . Ui::TagOpen( 'tr' ) );
		{
			echo( Ui::Tag( 'td', Ui::TextBox( null, '', array( 'class' => 'val', 'placeholder' => _x( 'TagsPhlr', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '100%' ) ) ) ) );
			echo( Ui::Tag( 'td', Ui::Button( esc_html( Wp::GetLocString( array( 'AddItemBtn', 'admin.Common_ItemsList' ), null, 'seraphinite-discount-for-woocommerce' ) ), false, null, null, 'button', array( 'onclick' => 'seraph_wd.Settings._int.TagVal_OnAdd(this);return false;' ) ), array( 'style' => array( 'width' => '1px' ) ) ) );
		}
		echo( Ui::TagClose( 'tr' ) . Ui::SettBlock_ItemSubTbl_End() );
	}
	echo( Ui::TagClose( 'div' ) );

	echo( Ui::TagOpen( 'div', array( 'class' => 'blck', 'style' => array( 'margin-bottom' => '2em' ) ) ) );
	{
		echo( Ui::Tag( 'label', Ui::Tag( 'strong', esc_html_x( 'ProdsLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ) .  Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_FltProds' ) ) ) );

		echo( _ExclInclSetting( $sett, 'filters/prodsOp', $ns, 10, false, array( 'class' => 'ctlSpaceAfter ctlSpaceVAfter', 'style' => array( 'display' => 'block' ) ) ) );

		$fldId = 'filters/prods';
		echo( Ui::TokensList( Gen::GetArrField( $sett, $fldId, array(), '/' ), $ns . $fldId, array( 'class' => 'vals ctlSpaceVAfter', 'style' => array( 'min-height' => '3em', 'height' => '3em', 'max-height' => '20em' ), 'data-onexpand' => 'seraph_wd.Settings._int.ProductVal_OnExpand(this,isExpanding)' ), true ) );

		echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'class' => 'std', 'style' => array( 'width' => '100%' ) ) ) . Ui::TagOpen( 'tr' ) );
		{
			echo( Ui::Tag( 'td', Ui::TextBox( null, '', array( 'class' => 'val', 'placeholder' => _x( 'ProdsPhlr', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ), 'style' => array( 'width' => '100%' ) ) ) ) );
			echo( Ui::Tag( 'td',
				Ui::Button( esc_html( Wp::GetLocString( array( 'AddItemBtn', 'admin.Common_ItemsList' ), null, 'seraphinite-discount-for-woocommerce' ) ), false, null, null, 'button', array( 'style' => array( 'vertical-align' => 'middle' ), 'onclick' => 'seraph_wd.Settings._int.ProductVal_OnAdd(this);return false;' ) ) .
				Ui::Spinner( false, array( 'class' => array( 'ctlSpaceBefore' ), 'style' => array( 'display' => 'none', 'vertical-align' => 'middle' ) ) )
				, array( 'style' => array( 'width' => '1px', 'white-space' => 'nowrap' ) )
			) );
		}
		echo( Ui::TagClose( 'tr' ) . Ui::SettBlock_ItemSubTbl_End() );
	}
	echo( Ui::TagClose( 'div' ) );

}

function _SaveFiltersSettings( &$sett, $ns, $args )
{
	{
		$fldId = 'filters/categs';
		$a = Ui::TokensList_GetVal( (isset($args[ $ns . $fldId ])?$args[ $ns . $fldId ]:null) );
		if( count( $a ) )
			Gen::SetArrField( $sett, $fldId, $a, '/' );

		$fldId = 'filters/categsOp';
		Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ $ns . $fldId ] ), '/' );
	}

	{
		$fldId = 'filters/tags';
		$a = Ui::TokensList_GetVal( (isset($args[ $ns . $fldId ])?$args[ $ns . $fldId ]:null), 'seraph_wd\\Wp::SanitizeText' );
		if( count( $a ) )
			Gen::SetArrField( $sett, $fldId, $a, '/' );

		$fldId = 'filters/tagsOp';
		Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ $ns . $fldId ] ), '/' );
	}

	{
		$fldId = 'filters/prods';
		$a = Ui::TokensList_GetVal( (isset($args[ $ns . $fldId ])?$args[ $ns . $fldId ]:null) );
		if( count( $a ) )
			Gen::SetArrField( $sett, $fldId, $a, '/' );

		$fldId = 'filters/prodsOp';
		Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ $ns . $fldId ] ), '/' );
	}

}

function _ProductSettingsMetabox( $post )
{
	Plugin::CmnScripts( array( 'Cmn', 'Gen', 'Ui', 'AdminUi' ) );

	$rmtCfg = PluginRmtCfg::Get();
	$sett = Plugin::PostSettGet( $post -> ID, 'Product' );
	$currencySymb = GetWooCurrencySymbol();

	$isPaidLockedContent = false;

	echo( Ui::Tag( 'p', Plugin::SwitchToExt(), null, false, array( 'noTagsIfNoContent' => true, 'afterContent' => Ui::SepLine( 'p' ) ) ) );

	{
		$htmlContent = Plugin::GetLockedFeatureLicenseContent();
		if( !empty( $htmlContent ) )
			echo( Ui::Tag( 'p', $htmlContent ) . Ui::SepLine( 'p' ) );
		unset( $htmlContent );
	}

	wp_nonce_field( 'savePostSettings', 'seraph_wd/_nonce' );

	echo( Ui::SettBlock_Begin( array( 'class' => 'compact', 'name' => '' ) ) );
	{
		echo( Ui::SettBlock_Item_Begin( esc_html_x( 'EnabledAllLbl', 'admin.ProductSettings', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.ProdSett_Enable' ) ) ) );
		{
			$fldId = 'enable';
			echo( Ui::CheckBox( null, 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
		}
		echo( Ui::SettBlock_Item_End() );
	}
	echo( Ui::SettBlock_End() );

	_ShowItemsSettings( $rmtCfg, $sett, $currencySymb, 'seraph_wd/', '#seraph_wd_settings', $isPaidLockedContent );

	echo( Ui::ViewInitContent( '#seraph_wd_settings' ) );
}

function _OnProductSettingsSave( $postId )
{
	if( !current_user_can( 'edit_post', $postId ) )
		return;

	$post = get_post( $postId );
	if( $post -> post_type != 'product' )
		return;

	if( !wp_verify_nonce( Wp::SanitizeId( (isset($_REQUEST[ 'seraph_wd/_nonce' ])?$_REQUEST[ 'seraph_wd/_nonce' ]:null) ), 'savePostSettings' ) )
		return;

	$sett = array();

	{
		$fldId = 'enable';
		Gen::SetArrField( $sett, $fldId, isset( $_REQUEST[ 'seraph_wd/' . $fldId ] ), '/' );
	}

	_SaveItemsSettings( $sett, 'seraph_wd/', $_REQUEST );

	Plugin::PostSettSet( $post -> ID, $sett, 'Product' );
}

function _GetProductLoopInsertPositions()
{
	return( array(
		array( '',												esc_html_x( 'Loop_None', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_shop_loop_item_title',		esc_html_x( 'Loop_Title_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_shop_loop_item_title:20',		esc_html_x( 'Loop_Price_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_shop_loop_item',				esc_html_x( 'Loop_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
	) );
}

function _GetProductSingleInsertPositions()
{
	return( array(
		array( '',												esc_html_x( 'Single_None', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_before_single_product',				esc_html_x( 'Single_Before', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_product_meta_start',				esc_html_x( 'Single_Meta_Before', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_before_add_to_cart_form',			esc_html_x( 'Single_AddToCartForm_Before', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_add_to_cart_form',			esc_html_x( 'Single_AddToCartForm_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_product_meta_end',					esc_html_x( 'Single_Meta_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_before_single_product_summary',		esc_html_x( 'Single_Summary_Before', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_single_product_summary',		esc_html_x( 'Single_Summary_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
		array( 'woocommerce_after_single_product',				esc_html_x( 'Single_After', 'admin.Settings_Display_ProductInsertPos', 'seraphinite-discount-for-woocommerce' ) ),
	) );
}

function _SettingsPage()
{
	Plugin::CmnScripts( array( 'Cmn', 'Gen', 'Ui', 'Net', 'AdminUi' ) );
	wp_enqueue_script( Plugin::ScriptId( 'Settings' ), add_query_arg( Plugin::GetFileUrlPackageParams(), Plugin::FileUrl( 'Settings.js', __FILE__ ) ), array_merge( array( 'jquery' ), Plugin::CmnScriptId( array( 'Cmn', 'Gen', 'Ui', 'Net' ) ) ), '2.4.5' );

	$curLang = Wp::GetCurLang( 'sysdef' );

	$isPaidLockedContent = false;

	Plugin::DisplayAdminFooterRateItContent();

	$rmtCfg = PluginRmtCfg::Get();
	$sett = Plugin::SettGet();
	$currencySymb = GetWooCurrencySymbol();

	echo( Ui::TagOpen( 'input', array( 'type' => 'hidden', 'id' => 'seraph_wd_helperRequestUrl', 'value' => Plugin::GetAdminApiUri() ), true ) );

	{
		Ui::PostBoxes_MetaboxAdd( 'general', esc_html_x( 'Title', 'admin.Settings_General', 'seraphinite-discount-for-woocommerce' ) . Ui::Tag( 'span', Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings' ), Ui::AdminHelpBtnModeBlockHeader ) ), true,
			function( $callbacks_args, $box )
			{
				extract( $box[ 'args' ] );

				{
					$htmlContent = Plugin::GetLockedFeatureLicenseContent();
					if( !empty( $htmlContent ) )
						echo( Ui::Tag( 'p', $htmlContent ) . Ui::SepLine( 'p' ) );
					unset( $htmlContent );
				}

				echo( Ui::SettBlock_Begin() );
				{
					echo( Ui::SettBlock_Item_Begin( esc_html_x( 'Lbl', 'admin.Settings_Cmn', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings_Cmn' ) ) ) );
					{
						echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'style' => array( 'width' => '100%' ) ) ) );
						{
							echo( Ui::TagOpen( 'tr' ) );
							{
								echo( Ui::TagOpen( 'td' ) );
								{
									$fldId = 'showItemSubtotalPrevPrice';
									echo( Ui::CheckBox( esc_html_x( 'ShowItemSubtotalPrevPriceChk', 'admin.Settings_Cmn', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
								}
								echo( Ui::TagClose( 'td' ) );
							}
							echo( Ui::TagClose( 'tr' ) );

							echo( Ui::TagOpen( 'tr' ) );
							{
								echo( Ui::TagOpen( 'td' ) );
								{
									$fldId = 'productUpdateDataDyn';
									echo( Ui::CheckBox( esc_html_x( 'ProductUpdateDataDynChk', 'admin.Settings_Cmn', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
								}
								echo( Ui::TagClose( 'td' ) );
							}
							echo( Ui::TagClose( 'tr' ) );
						}
						echo( Ui::SettBlock_ItemSubTbl_End() );
					}
					echo( Ui::SettBlock_Item_End() );

					echo( Ui::SettBlock_Item_Begin( esc_html_x( 'Lbl', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings_AdjustOnSale' ) ) ) );
					{
						echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'style' => array( 'width' => '100%' ) ) ) );
						{

							{
								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'adjustOnSale/enable';
										echo( Ui::CheckBox( esc_html_x( 'EnableChk', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'adjustOnSale/showSeparatedOriginalSale';
										echo( Ui::CheckBox( esc_html_x( 'ShowSeparatedOriginalSaleChk', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'adjustOnSale/showSeparatedVarsOriginalPrice';
										echo( Ui::CheckBox( esc_html_x( 'ShowSeparatedVarsOriginalPriceChk', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, false, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'adjustOnSale/loc/' . $curLang . '/saleFlashExtra';
										echo( Ui::Label( Ui::EscHtml( _x( 'Loc_SaleFlashExtraLbl', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ), true ) ) . Ui::TextBox( 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, '', '/' ), array( 'placeholder' => Wp::SanitizeHtml( _x( 'SaleFlashExtra_%1$s', 'AdjustOnSale', 'seraphinite-discount-for-woocommerce' ) ), 'style' => array( 'width' => '100%' ) ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'adjustOnSale/loc/' . $curLang . '/saleRangeFlashExtra';
										echo( Ui::Label( Ui::EscHtml( _x( 'Loc_SaleRangeFlashExtraLbl', 'admin.Settings_AdjustOnSale', 'seraphinite-discount-for-woocommerce' ), true ) ) . Ui::TextBox( 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, '', '/' ), array( 'placeholder' => Wp::SanitizeHtml( _x( 'SaleRangeFlashExtra_%1$s%2$s', 'AdjustOnSale', 'seraphinite-discount-for-woocommerce' ) ), 'style' => array( 'width' => '100%' ) ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );
							}
						}
						echo( Ui::SettBlock_ItemSubTbl_End() );
					}
					echo( Ui::SettBlock_Item_End() );

					echo( Ui::SettBlock_Item_Begin( esc_html_x( 'Lbl', 'admin.Settings_ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings_ProductTotalPrice' ) ) ) );
					{
						echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'style' => array( 'width' => '100%' ) ) ) );
						{

							{
								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showProductTotalPricePreview/enable';
										echo( Ui::CheckBox( esc_html_x( 'EnableChk', 'admin.Settings_ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, false, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showProductTotalPricePreview/useCart';
										echo( Ui::CheckBox( esc_html_x( 'UseCartChk', 'admin.Settings_ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, false, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showProductTotalPricePreview/onLoad';
										echo( Ui::CheckBox( esc_html_x( 'OnLoadChk', 'admin.Settings_ProductDiscount', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showProductTotalPricePreview/loc/' . $curLang . '/label';
										echo( Ui::Label( Ui::EscHtml( _x( 'Loc_LabelLbl', 'admin.Settings_ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ), true ) ) . Ui::TextBox( 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, '', '/' ), array( 'placeholder' => _x( 'Label', 'ProductTotalPricePreview', 'seraphinite-discount-for-woocommerce' ) ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );
							}
						}
						echo( Ui::SettBlock_ItemSubTbl_End() );
					}
					echo( Ui::SettBlock_Item_End() );

					if( Gen::DoesFuncExist( 'wcs_get_subscription_period_strings' ) )
					{
						echo( Ui::SettBlock_Item_Begin( esc_html_x( 'Lbl', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings_PeriodPrices' ) ) ) );
						{
							echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'style' => array( 'width' => '100%' ) ) ) );
							{
								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showNormalizedPeriodPrices/enable';
										$fldIdPeriod = 'showNormalizedPeriodPrices/period';
										$fldIdPeriodInterval = 'showNormalizedPeriodPrices/periodInterval';

										$fldIdPeriodValues = array();
										if( Gen::DoesFuncExist( 'wcs_get_subscription_period_strings' ) )
											foreach( wcs_get_subscription_period_strings() as $v => $vLabel )
												$fldIdPeriodValues[] = array( $v, $vLabel );

										$fldIdPeriodIntervalValues = array();
										if( Gen::DoesFuncExist( 'wcs_get_subscription_period_interval_strings' ) )
											foreach( wcs_get_subscription_period_interval_strings() as $v => $vLabel )
												$fldIdPeriodIntervalValues[] = array( ( string )$v, $vLabel );

										echo( Ui::CheckBox(
											array(
												esc_html_x( 'ShowNormalizedChk_%1$s%2$s', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ),
												array(
													array( 'seraph_wd/' . $fldIdPeriod, $fldIdPeriodValues, Gen::GetArrField( $sett, $fldIdPeriod, count( $fldIdPeriodValues ) ? $fldIdPeriodValues[ 0 ][ 0 ] : '', '/' ) ),
													array( 'seraph_wd/' . $fldIdPeriodInterval, $fldIdPeriodIntervalValues, Gen::GetArrField( $sett, $fldIdPeriodInterval, count( $fldIdPeriodIntervalValues ) ? $fldIdPeriodIntervalValues[ 0 ][ 0 ] : '', '/' ) ),

												)
											),
											'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, false, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										{
											$fldId = 'showNormalizedPeriodPrices/prodLoop';
											echo( Ui::CheckBox( esc_html_x( 'ShowNormalizedProdLoopChk', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
										}

										{
											$fldId = 'showNormalizedPeriodPrices/prodAddToCartForm';
											echo( Ui::CheckBox( esc_html_x( 'ShowNormalizedProdAddToCartFormChk', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
										}

										{
											$fldId = 'showNormalizedPeriodPrices/cartProduct';
											echo( Ui::CheckBox( esc_html_x( 'ShowNormalizedCartProductChk', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, true, '/' ), true ) );
										}
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );

								echo( Ui::TagOpen( 'tr' ) );
								{
									echo( Ui::TagOpen( 'td' ) );
									{
										$fldId = 'showNormalizedPeriodPrices/prodVarsRelDiscount';
										echo( Ui::CheckBox( esc_html_x( 'ShowNormalizedProdVarsRelDiscountChk', 'admin.Settings_PeriodPrices', 'seraphinite-discount-for-woocommerce' ), 'seraph_wd/' . $fldId, Gen::GetArrField( $sett, $fldId, false, '/' ), true ) );
									}
									echo( Ui::TagClose( 'td' ) );
								}
								echo( Ui::TagClose( 'tr' ) );
							}
							echo( Ui::SettBlock_ItemSubTbl_End() );
						}
						echo( Ui::SettBlock_Item_End() );
					}

					echo( Ui::SettBlock_Item_Begin( esc_html_x( 'Lbl', 'admin.Settings_CalcMode', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminBtnsBlock( array( array( 'type' => Ui::AdminBtn_Help, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.Settings_CalcMode' ) ), Plugin::AdminBtnsBlock_GetPaidContent( $isPaidLockedContent ) ), Ui::AdminHelpBtnModeText ) ) );
					{
						$fldId = 'calcMode';
						echo( Ui::ComboBox(
							'seraph_wd/' . $fldId,
							array(

								'firstMatch'		=> esc_html_x( 'Item_FirstMatch', 'admin.Settings_CalcMode', 'seraphinite-discount-for-woocommerce' ),
							),
							Gen::GetArrField( $sett, $fldId, 'firstMatch', '/' ), true ) );
					}
					echo( Ui::SettBlock_Item_End() );
				}
				echo( Ui::SettBlock_End() );
			},
			get_defined_vars()
		);

		Ui::PostBoxes_MetaboxAdd( 'profiles', esc_html_x( 'Title', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) . Ui::Tag( 'span', Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles' ), Ui::AdminHelpBtnModeBlockHeader ) ), true,
			function( $callbacks_args, $box )
			{
				extract( $box[ 'args' ] );

				{
					$htmlContent = Plugin::GetLockedFeatureLicenseContent();
					if( !empty( $htmlContent ) )
						echo( Ui::Tag( 'p', $htmlContent ) . Ui::SepLine( 'p' ) );
					unset( $htmlContent );
				}

				echo( Ui::Tag( 'p', esc_html_x( 'Descr', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ), array( 'class' => array( 'description', 'ctlSpaceVBefore' ) ) ) );

				$fldId = 'profiles';
				$items = Gen::GetArrField( $sett, $fldId, array(), '/' );

				$itemsListPrms = array( 'editorAreaCssPath' => '#profiles', 'sortable' => true );

				echo( Ui::ItemsList( $itemsListPrms, $items, 'seraph_wd/' . $fldId,
					function( $cbArgs, $idItems, $items, $itemKey, $item )
					{
						extract( $cbArgs );

						ob_start();

						echo( Ui::SepLine( 'p' ) );

						echo( Ui::SettBlock_Begin() );
						{
							echo( Ui::SettBlock_Item_Begin( esc_html_x( 'EnabledLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_Enable' ) ) ) );
							{
								$fldId = 'enable';
								echo( Ui::CheckBox( null, $idItems . '/' . $itemKey . '/' . $fldId, Gen::GetArrField( $item, $fldId, true, '/' ), true ) );
							}
							echo( Ui::SettBlock_Item_End() );

							echo( Ui::SettBlock_Item_Begin( esc_html_x( 'NameLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminHelpBtn( Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_Name' ) ) ) );
							{
								$fldId = 'name';
								echo( Ui::TextBox( $idItems . '/' . $itemKey . '/' . $fldId, Gen::GetArrField( $item, $fldId, '', '/' ), array( 'style' => array( 'width' => '100%' ), 'placeholder' => _x( 'NamePhlr', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) ), true ) );
							}
							echo( Ui::SettBlock_Item_End() );

							echo( Ui::SettBlock_Item_Begin( esc_html_x( 'FiltersLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminBtnsBlock( array( array( 'type' => Ui::AdminBtn_Help, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_Filters' ) ), Plugin::AdminBtnsBlock_GetPaidContent( $isPaidLockedContent ) ), Ui::AdminHelpBtnModeText ) ) );
							{
								$idBlock = 'profile-' . $itemKey . '-filters';

								echo( Ui::ToggleButton( '#' . $idBlock, array( 'style' => array( 'min-width' => '7em' ) ), array( 'class' => 'ctlSpaceVAfter' ) ) );

								echo( Ui::TagOpen( 'div', array( 'id' => $idBlock, 'data-onexpand' => 'seraph_wd.Settings._int.ProfileBlock_OnExpand(this,isExpanding)', 'style' => array( 'display' => 'none' ) ) ) );
								_ShowFiltersSettings( $rmtCfg, $item, $idItems . '/' . $itemKey . '/', $idBlock, $isPaidLockedContent );
								echo( Ui::TagClose( 'div' ) );
							}
							echo( Ui::SettBlock_Item_End() );

							echo( Ui::SettBlock_Item_Begin( esc_html_x( 'ItemsLbl', 'admin.Settings_Profiles', 'seraphinite-discount-for-woocommerce' ) . Ui::AdminBtnsBlock( array( array( 'type' => Ui::AdminBtn_Help, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SettingsProfiles_Items' ) ), Plugin::AdminBtnsBlock_GetPaidContent( $isPaidLockedContent ) ), Ui::AdminHelpBtnModeText ) ) );
							{
								$idBlock = 'profile-' . $itemKey . '-items';

								echo( Ui::ToggleButton( '#' . $idBlock, array( 'style' => array( 'min-width' => '7em' ) ), array( 'class' => 'ctlSpaceVAfter' ) ) );

								echo( Ui::TagOpen( 'div', array( 'id' => $idBlock, 'data-onexpand' => 'seraph_wd.Settings._int.ProfileBlock_OnExpand(this,isExpanding)', 'style' => array( 'display' => 'none' ) ) ) );
								_ShowItemsSettings( $rmtCfg, $item, $currencySymb, $idItems . '/' . $itemKey . '/', '#' . $idBlock, $isPaidLockedContent, true );
								echo( Ui::TagClose( 'div' ) );
							}
							echo( Ui::SettBlock_Item_End() );
						}
						echo( Ui::SettBlock_End() );

						echo( Ui::Tag( 'div', Ui::ItemsList_ItemOperateBtns( $itemsListPrms, array( 'class' => 'ctlSpaceAfterSm' ) ), array( 'class' => 'ctlSpaceVBefore ctlSpaceVAfter' ) ) );

						return( ob_get_clean() );
					},

					function( $cbArgs, $attrs )
					{
						Gen::SetArrField( $attrs, 'class.+', 'ctlSpaceVAfter' );
						return( Ui::Tag( 'div', Ui::Tag( 'label', Ui::ItemsList_NoItemsContent() ), $attrs ) );
					},

					get_defined_vars(), array( 'class' => 'ctlMaxSizeX' )
				) );

				echo( Ui::SepLine() );
				echo( Ui::Tag( 'div', Ui::ItemsList_OperateBtns( $itemsListPrms, array( 'class' => 'ctlSpaceAfter', 'style' => array( 'margin-left' => 0 ) ) ), array( 'class' => 'ctlSpaceVBefore' ) ) );
			},
			get_defined_vars()
		);
	}

	{
		$htmlContent = Plugin::GetSettingsLicenseContent();
		if( !empty( $htmlContent ) )
			Ui::PostBoxes_MetaboxAdd( 'license', Plugin::GetSettingsLicenseTitle(), true, function( $callbacks_args, $box ) { echo( $box[ 'args' ][ 'c' ] ); }, array( 'c' => $htmlContent ), 'normal' );

		$htmlContent = Plugin::GetAdvertProductsContent( 'advertProducts' );
		if( !empty( $htmlContent ) )
			Ui::PostBoxes_MetaboxAdd( 'advertProducts', Plugin::GetAdvertProductsTitle(), false, function( $callbacks_args, $box ) { echo( $box[ 'args' ][ 'c' ] ); }, array( 'c' => $htmlContent ), 'normal' );
	}

	{
		$htmlContent = Plugin::GetRateItContent( 'rateIt', Plugin::DisplayContent_SmallBlock );
		if( !empty( $htmlContent ) )
			Ui::PostBoxes_MetaboxAdd( 'rateIt', Plugin::GetRateItTitle(), false, function( $callbacks_args, $box ) { echo( $box[ 'args' ][ 'c' ] ); }, array( 'c' => $htmlContent ), 'side' );

		$htmlContent = Plugin::GetLockedFeatureLicenseContent( Plugin::DisplayContent_SmallBlock );
		if( !empty( $htmlContent ) )
			Ui::PostBoxes_MetaboxAdd( 'switchToFull', Plugin::GetSwitchToFullTitle(), false, function( $callbacks_args, $box ) { echo( $box[ 'args' ][ 'c' ] ); }, array( 'c' => $htmlContent ), 'side' );

		Ui::PostBoxes_MetaboxAdd( 'about', Plugin::GetAboutPluginTitle(), false, function( $callbacks_args, $box ) { echo( Plugin::GetAboutPluginContent() ); }, null, 'side' );
		Ui::PostBoxes_MetaboxAdd( 'aboutVendor', Plugin::GetAboutVendorTitle(), false, function( $callbacks_args, $box ) { echo( Plugin::GetAboutVendorContent() ); }, null, 'side' );
	}

	Ui::PostBoxes( Plugin::GetSettingsTitle(), array( 'body' => array( 'nosort' => true ), 'normal' => array(), 'side' => array( 'nosort' => true ) ),
		array(
			'bodyContentBegin' => function( $callbacks_args )
			{
				extract( $callbacks_args );

				echo( Ui::TagOpen( 'form', array( 'id' => 'seraph-wd-form', 'method' => 'post', 'onsubmit' => 'return seraph_wd.Ui.Apply(this);' ) ) );
			},

			'bodyContentEnd' => function( $callbacks_args )
			{
				extract( $callbacks_args );

				Ui::PostBoxes_BottomGroupPanel( function( $callbacks_args ) { echo( Plugin::Sett_SaveBtn( 'seraph_wd_saveSettings' ) ); } );
				echo( Ui::TagClose( 'form' ) );
			}
		),
		get_defined_vars()
	);
}

function _OnSaveSettings( $args )
{
	$curLang = Wp::GetCurLang( 'sysdef' );
	$settPrev = Plugin::SettGet();

	$sett = array();

	{ $fldId = 'showItemSubtotalPrevPrice';						Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
	{ $fldId = 'productUpdateDataDyn';							Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }

	{
		{ $fldId = 'adjustOnSale/enable';							Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'adjustOnSale/showSeparatedOriginalSale';		Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'adjustOnSale/showSeparatedVarsOriginalPrice';	Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }

		{ $fldId = 'adjustOnSale/loc';		Gen::SetArrField( $sett, $fldId, Gen::GetArrField( $settPrev, $fldId, null, '/' ), '/' ); }
		{ $fldId = 'adjustOnSale/loc/' . $curLang . '/saleFlashExtra';			Gen::SetArrField( $sett, $fldId, @stripslashes( Wp::SanitizeHtml( $args[ 'seraph_wd/' . $fldId ] ) ), '/' ); }
		{ $fldId = 'adjustOnSale/loc/' . $curLang . '/saleRangeFlashExtra';		Gen::SetArrField( $sett, $fldId, @stripslashes( Wp::SanitizeHtml( $args[ 'seraph_wd/' . $fldId ] ) ), '/' ); }
	}

	{
		{ $fldId = 'showProductTotalPricePreview/enable';		Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showProductTotalPricePreview/useCart';	Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showProductTotalPricePreview/onLoad';		Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }

		{ $fldId = 'showProductTotalPricePreview/loc';		Gen::SetArrField( $sett, $fldId, Gen::GetArrField( $settPrev, $fldId, null, '/' ), '/' ); }
		{ $fldId = 'showProductTotalPricePreview/loc/' . $curLang . '/label';			Gen::SetArrField( $sett, $fldId, @stripslashes( Wp::SanitizeHtml( $args[ 'seraph_wd/' . $fldId ] ) ), '/' ); }
	}

	if( Gen::DoesFuncExist( 'wcs_get_subscription_period_strings' ) )
	{
		{ $fldId = 'showNormalizedPeriodPrices/enable';						Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showNormalizedPeriodPrices/period';						Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showNormalizedPeriodPrices/periodInterval';				Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showNormalizedPeriodPrices/prodVarsRelDiscount';		Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }

		{ $fldId = 'showNormalizedPeriodPrices/prodLoop';					Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showNormalizedPeriodPrices/prodAddToCartForm';			Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
		{ $fldId = 'showNormalizedPeriodPrices/cartProduct';				Gen::SetArrField( $sett, $fldId, isset( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }
	}

	{ $fldId = 'calcMode';										Gen::SetArrField( $sett, $fldId, Wp::SanitizeId( $args[ 'seraph_wd/' . $fldId ] ), '/' ); }

	{
		$fldId = 'profiles';
		Gen::SetArrField( $sett, $fldId, Ui::ItemsList_GetSaveItems( 'seraph_wd/' . $fldId, '/', $args,
			function( $cbArgs, $idItems, $itemKey, $item, $args )
			{
				$item = array();

				{
					$fldId = 'enable';
					Gen::SetArrField( $item, $fldId, isset( $args[ $idItems . '/' . $itemKey . '/' . $fldId ] ), '/' );
				}

				{
					$fldId = 'name';
					Gen::SetArrField( $item, $fldId, Wp::SanitizeText( (isset($args[ $idItems . '/' . $itemKey . '/' . $fldId ])?$args[ $idItems . '/' . $itemKey . '/' . $fldId ]:null) ), '/' );
				}

				_SaveFiltersSettings( $item, $idItems . '/' . $itemKey . '/', $args );

				_SaveItemsSettings( $item, $idItems . '/' . $itemKey . '/', $args );

				return( $item );
			}
		), '/' );
	}

	return( Plugin::SettSet( $sett ) );
}

add_action( 'woocommerce_before_calculate_totals',				'seraph_wd\\_act_woocommerce_before_calculate_totals'			);
add_action( 'woocommerce_after_calculate_totals',				'seraph_wd\\_act_woocommerce_after_calculate_totals'				);

add_filter( 'woocommerce_cart_product_subtotal',				'seraph_wd\\_flt_woocommerce_cart_product_subtotal',				0, 4 );

function _flt_woocommerce_variation_prices_price_as_regular( $price, $productVar, $product )
{
	if( !$productVar )
		return( $price );

	remove_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price_as_regular', 99 );
	$price = $productVar -> get_regular_price();
	add_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price_as_regular', 99, 3 );
	return( $price );
}

function _flt_woocommerce_variation_prices_sale_price_as_regular( $price, $productVar, $product )
{
	return( '' );
}

function _flt_varproduct_get_price_html_with_regular( $html )
{
	global $product, $seraph_wd_g_productDispData;

	if( !$seraph_wd_g_productDispData )
		return( $html );

	if( !Gen::GetArrField( $seraph_wd_g_productDispData, array( 'maxSaleCoeff' ) ) )
		return( $html );

	$aFlts = array();
	Wp::RemoveFilters( 'woocommerce_get_price_html', 'seraph_wd\\_flt_varproduct_get_price_html_with_regular', Wp::REMOVEFILTER_PRIORITY_ALL, $aFlts );
	Wp::RemoveFilters( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation', Wp::REMOVEFILTER_PRIORITY_ALL, $aFlts );
	Wp::RemoveFilters( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation', Wp::REMOVEFILTER_PRIORITY_ALL, $aFlts );
	Wp::RemoveFilters( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price', Wp::REMOVEFILTER_PRIORITY_ALL, $aFlts );
	Wp::RemoveFilters( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price', Wp::REMOVEFILTER_PRIORITY_ALL, $aFlts );

	add_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation_as_regular', 99, 2 );
	add_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation_as_regular', 99, 2 );
	add_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price_as_regular', 99, 3 );
	add_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price_as_regular', 99, 3 );

	_PriceVarRangeSyncProduct( $product );
	$htmlRegular = $product -> get_price_html();

	remove_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price_as_regular', 99 );
	remove_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price_as_regular', 99 );
	remove_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation_as_regular', 99 );
	remove_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation_as_regular', 99 );

	$html = wc_format_sale_price( $htmlRegular, $html );

	Wp::AddFilters( $aFlts );
	_PriceVarRangeSyncProduct( $product );
	return( $html );
}

function _PriceVarRangeSyncProduct( $product )
{
	if( is_callable( array( $product, 'sync' ) ) )
		$product -> sync( $product );
}

function _PriceVarRangeAdjustEnable( $product, $orig, $enable = true )
{
	if( !@is_a( $product, 'WC_Product_Variable' ) )
		return;

	if( $enable )
	{
		if( $orig )
			add_filter( 'woocommerce_get_price_html', 'seraph_wd\\_flt_varproduct_get_price_html_with_regular', 99, 1 );
		add_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price', 99, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price', 99, 3 );
	}
	else
	{
		if( $orig )
			remove_filter( 'woocommerce_get_price_html', 'seraph_wd\\_flt_varproduct_get_price_html_with_regular', 99 );
		remove_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price', 99 );
		remove_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price', 99 );
	}

	_PriceVarRangeSyncProduct( $product );
}

function GetProductSaleOrRegularPrice( $product, &$saleCoeff = null, &$priceReg = null )
{
	$price = $product -> get_sale_price();
	$priceReg = $product -> get_regular_price();

	if( !is_numeric( $price ) )
	{
		$price = $priceReg;
		$saleCoeff = 0.0;
	}
	else if( ( float )$price < ( float )$priceReg )
		$saleCoeff = 1.0 - ( float )$price / ( float )$priceReg;

	return( $price );
}

function GetProductDispData( $product )
{
	if( !$product )
		return( null );

	if( !@is_a( $product, 'WC_Product_Variable' ) )
		return( null );

	$aVars = $product -> get_available_variations( 'object' );
	if( !$aVars )
		return( null );

	$data = array();

	foreach( $aVars as $productVar )
	{
		$price = GetProductSaleOrRegularPrice( $productVar, $saleCoeff );

		if( !$data )
		{
			$data[ 'min' ] = $data[ 'max' ] = $price;
			$data[ 'varMin' ] = $data[ 'varMax' ] = $productVar;

			$data[ 'minSaleCoeff' ] = $data[ 'maxSaleCoeff' ] = $saleCoeff;
			$data[ 'varMinSaleCoeff' ] = $data[ 'varMaxSaleCoeff' ] = $productVar;

			continue;
		}

		{
			if( $price < $data[ 'min' ] )
			{
				$data[ 'min' ] = $price;
				$data[ 'varMin' ] = $productVar;
			}

			if( $price > $data[ 'max' ] )
			{
				$data[ 'max' ] = $price;
				$data[ 'varMax' ] = $productVar;
			}
		}

		{
			if( $saleCoeff < $data[ 'minSaleCoeff' ] )
			{
				$data[ 'minSaleCoeff' ] = $saleCoeff;
				$data[ 'varMinSaleCoeff' ] = $productVar;
			}

			if( $saleCoeff > $data[ 'maxSaleCoeff' ] )
			{
				$data[ 'maxSaleCoeff' ] = $saleCoeff;
				$data[ 'varMaxSaleCoeff' ] = $productVar;
			}
		}
	}

	$data[ 'vars' ] = $aVars;
	return( $data );
}

function _PriceAdjustEnable( $enable = true, $sale = true )
{
	if( $enable )
	{
		add_filter( 'woocommerce_product_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price', 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation', 99, 2 );

		if( $sale )
		{
			add_filter( 'woocommerce_product_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price', 99, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation', 99, 2 );
		}
	}
	else
	{
		remove_filter( 'woocommerce_product_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price', 99 );
		remove_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation', 99 );

		if( $sale )
		{
			remove_filter( 'woocommerce_product_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price', 99 );
			remove_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation', 99 );
		}
	}
}

function _GetCartItems()
{
	$cartItems = null;
	if( Gen::DoesFuncExist( 'WC' ) && WC() -> cart )
		$cartItems = WC() -> cart -> get_cart();
	return( is_array( $cartItems ) ? $cartItems : array() );
}

function _GetVarProduct( $productVar )
{
	$parentId = $productVar -> get_parent_id();
	if( !$parentId )
		return( null );

	return( wc_get_product( $parentId ) );
}

function _flt_woocommerce_product_get_price( $price, $product )
{
	remove_filter( 'woocommerce_product_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_product_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price', 99, 2 );

	return( _AdjustPriceFromQty( $price, $product, null, $cartItems ) );
}

function _flt_woocommerce_product_get_sale_price( $price, $product )
{
	remove_filter( 'woocommerce_product_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_product_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price', 99, 2 );

	$priceAdjust = $price ? $price : $product -> get_regular_price();
	$priceSale = _AdjustPriceFromQty( $priceAdjust, $product, null, $cartItems );
	return( $priceSale < $priceAdjust ? $priceSale : $price );
}

function _flt_woocommerce_product_get_price_variation( $price, $productVar )
{
	remove_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation', 99, 2 );

	$product = _GetVarProduct( $productVar );
	if( !$product )
		return( $price );

	return( _AdjustPriceFromQty( $price, $product, $productVar, $cartItems ) );
}

function _flt_woocommerce_product_get_price_variation_as_regular( $price, $productVar )
{
	if( !$productVar )
		return( $price );

	remove_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation_as_regular', 99 );
	$price = $productVar -> get_regular_price();
	add_filter( 'woocommerce_product_variation_get_price', 'seraph_wd\\_flt_woocommerce_product_get_price_variation_as_regular', 99, 2 );
	return( $price );
}

function _flt_woocommerce_variation_prices_price( $price, $productVar, $product )
{
	if( !$product )
		return( $price );

	remove_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_variation_prices_price', 'seraph_wd\\_flt_woocommerce_variation_prices_price', 99, 3 );

	return( _AdjustPriceFromQty( $price, $product, $productVar, $cartItems ) );
}

function _flt_woocommerce_product_get_sale_price_variation( $price, $productVar )
{
	remove_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_product_variation_get_sale_price', 'seraph_wd\\_flt_woocommerce_product_get_sale_price_variation', 99, 2 );

	$product = _GetVarProduct( $productVar );
	if( !$product )
		return( $price );

	$priceAdjust = $price ? $price : $productVar -> get_regular_price();
	$priceSale = _AdjustPriceFromQty( $priceAdjust, $product, $productVar, $cartItems );
	return( $priceSale < $priceAdjust ? $priceSale : $price );
}

function _flt_woocommerce_product_get_sale_price_variation_as_regular( $price, $productVar )
{
	return( '' );
}

function _flt_woocommerce_variation_prices_sale_price( $price, $productVar, $product )
{
	if( !$product )
		return( $price );

	remove_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price', 99 );
	$cartItems = _GetCartItems();
	add_filter( 'woocommerce_variation_prices_sale_price', 'seraph_wd\\_flt_woocommerce_variation_prices_sale_price', 99, 3 );

	$priceAdjust = $price ? $price : $productVar -> get_regular_price();
	$priceSale = _AdjustPriceFromQty( $priceAdjust, $product, $productVar, $cartItems );
	return( $priceSale < $priceAdjust ? $priceSale : $price );
}

function _flt_woocommerce_cart_product_subtotal( $product_subtotal_html, $product, $quantity, $cart )
{
	global $wp_filter;

	$sett = Plugin::SettGet();

	$fltOld = $wp_filter[ 'woocommerce_cart_product_subtotal' ]; unset( $wp_filter[ 'woocommerce_cart_product_subtotal' ] );

	_act_woocommerce_before_calculate_totals( $cart );
	$priceReg = $product -> get_regular_price();
	$priceDiscount = $product -> get_price();
	$product_subtotal_discount_html = $cart -> get_product_subtotal( $product, $quantity );
	_act_woocommerce_after_calculate_totals( $cart );

	$wp_filter[ 'woocommerce_cart_product_subtotal' ] = $fltOld;

	if( $priceReg > $priceDiscount )
	{
		if( Gen::GetArrField( $sett, 'showItemSubtotalPrevPrice', true ) )
			$product_subtotal_html = wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $priceReg, 'qty' => $quantity ) ), $product_subtotal_discount_html );
		else
			$product_subtotal_html = $product_subtotal_discount_html;
	}

	return( $product_subtotal_html );
}

function _act_woocommerce_before_calculate_totals( $cart )
{
	$sett = Plugin::SettGet();
	if( !Gen::GetArrField( $sett, array( 'adjustOnSale', 'enable' ), true ) )
		_PriceAdjustEnable( true, false );
}

function _act_woocommerce_after_calculate_totals( $cart )
{
	$sett = Plugin::SettGet();
	if( !Gen::GetArrField( $sett, array( 'adjustOnSale', 'enable' ), true ) )
		_PriceAdjustEnable( false, false );
}

function _AdjustPriceFromQty_SetAdditionalQty( $prodId, $qty )
{
	global $seraph_wd_g_aAdjustPriceFromCart_AdditionalQty;

	$seraph_wd_g_aAdjustPriceFromCart_AdditionalQty[ $prodId ] = $qty;
}

function _GetCartProductQty( $product, $productVar, $cartItems, $additionalQty )
{
	$quantity = $additionalQty ? $additionalQty : 0.0;

	$productId = $product -> get_id();
	$productVarId = $productVar ? $productVar -> get_id() : 0;

	foreach( $cartItems as $cartItem )
	{
		if( $cartItem[ 'product_id' ] != $productId )
			continue;
		if( isset( $cartItem[ 'subscription_renewal' ] ) )
			continue;

		$varId = (isset($cartItem[ 'variation_id' ])?$cartItem[ 'variation_id' ]:null);
		if( empty( $varId ) )
			$varId = 0;

		if( $varId != $productVarId )
			continue;

		$quantity += ( float )$cartItem[ 'quantity' ];
		break;
	}

	return( $quantity );
}

function _AdjustPriceFromQty( $price, $product, $productVar, $cartItems )
{
	global $seraph_wd_g_aAdjustPriceFromCart_AdditionalQty;

	$additionalQty = 0;
	if( $seraph_wd_g_aAdjustPriceFromCart_AdditionalQty )
		$additionalQty = ( float )(isset($seraph_wd_g_aAdjustPriceFromCart_AdditionalQty[ $productVar ? $productVar -> get_id() : $product -> get_id() ])?$seraph_wd_g_aAdjustPriceFromCart_AdditionalQty[ $productVar ? $productVar -> get_id() : $product -> get_id() ]:null);

	$attrs = array( 'quantity' => _GetCartProductQty( $product, $productVar, $cartItems, $additionalQty ) );
	if( !$attrs[ 'quantity' ] )
		$attrs[ 'quantity' ] = 1.0;

	return( API::AdjustPrice( $product, $price, $attrs ) );
}

class API
{
	static private $_cacheProductDiscountItems = null;

	static function GetProductDiscountItems( $product )
	{
		$ctxProduct = array( 'p' => $product, 'id' => $product -> get_id() );

		$data = Gen::GetArrField( self::$_cacheProductDiscountItems, $ctxProduct[ 'id' ] );
		if( $data )
			return( $data );

		$sett = Plugin::SettGet();
		$postSett = Plugin::PostSettGet( $ctxProduct[ 'id' ], 'Product' );

		$calcMode = Gen::GetArrField( $sett, 'calcMode', 'firstMatch' );
		$items = array();

		if( Gen::GetArrField( $postSett, 'enable', false ) )
			self::_AddItems( $ctxProduct, $items, Gen::GetArrField( $postSett, 'items', array() ) );

		foreach( Gen::GetArrField( $sett, 'profiles', array() ) as $profile )
			if( Gen::GetArrField( $profile, 'enable', false ) && self::_FilterProfile( $profile, $ctxProduct ) )
				self::_AddItems( $ctxProduct, $items, Gen::GetArrField( $profile, 'items', array() ) );

		$data = array( 'calcMode' => $calcMode, 'items' => $items );
		self::$_cacheProductDiscountItems[ $ctxProduct[ 'id' ] ] = $data;
		return( $data );
	}

	static function AdjustPrice( $product, $price, $attrs )
	{
		if( !is_numeric( $price ) )
			$price = 0.0;
		$attrs[ 'quantity' ] = ( float )$attrs[ 'quantity' ];
		return( self::_AdjustPrice( API::GetProductDiscountItems( $product ), $price, $attrs ) );
	}

	static function GetProductPriceContent( $product, $price, $priceSale = null )
	{

		$cont = '';
		if( $priceSale !== null && $price > $priceSale )
			$cont = wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $price ) ), wc_get_price_to_display( $product, array( 'price' => $priceSale ) ) );
		else
			$cont = wc_price( wc_get_price_to_display( $product, array( 'price' => $price ) ) );

		$cont .= $product -> get_price_suffix();
		return( apply_filters( 'woocommerce_get_price_html', $cont, $product ) );
	}

	static function GetDiscountRangeItems( $product, $productDiscountItems )
	{
		$productDiscountItems = $productDiscountItems[ 'items' ];
		if( empty( $productDiscountItems ) )
			return( null );

		$sett = Plugin::SettGet();

		$items = array();

		return( $items );
	}

	static private function _CheckInRange( $v, $vMin, $vMax )
	{
		if( $vMin === null )
			$vMin = 0;
		return( $v >= $vMin && ( !$vMax || $v <= $vMax ) );
	}

	static private function _CheckInArr( $needle, $heystack )
	{
		if( !$needle )
			return( false );

		if( !count( $heystack ) )
			return( false );

		if( gettype( $heystack[ 0 ] ) != "array" )
		{
			foreach( $needle as $needleItem )
				if( in_array( $needleItem, $heystack ) )
					return( true );

			return( false );
		}

		foreach( $needle as $needleItem )
			foreach( $heystack as $heystackItem )
				if( self::_CheckInRange( $needleItem, $heystackItem[ 'f' ], $heystackItem[ 't' ] ) )
					return( true );

		return( false );
	}

	static private function &_ValsCheck_Add( &$items, $key, $in, $vals )
	{
		$item = &$items[ $key ];
		if( !$item )
			$item = array( 'i' => array(), 'e' => array(), 'v' => null );

		$itemIOrE = &$item[ $in ? 'i' : 'e' ];
		foreach( $vals as $val )
			$itemIOrE[] = $val;

		return( $item );
	}

	static private function _FilterValues( array $items )
	{
		foreach( $items as $item )
		{
			$itemIncl = $item[ 'i' ];
			$itemExcl = $item[ 'e' ];
			$itemVals = $item[ 'v' ];

			if( $itemIncl && !self::_CheckInArr( $itemVals, $itemIncl ) )
				return( false );
			if( $itemExcl && self::_CheckInArr( $itemVals, $itemExcl ) )
				return( false );
		}

		return( true );
	}

	static private function _FilterProfile( $profile, &$ctxProduct )
	{
		$filters = Gen::GetArrField( $profile, 'filters', array(), '/' );

		{
			$vals = Gen::GetArrField( $filters, 'prods', array(), '/' );
			if( $vals )
			{
				$items = array();

				$item = &self::_ValsCheck_Add( $items, '', Gen::GetArrField( $filters, 'prodsOp', 'e', '/' ) == 'in', $vals );
				$item[ 'v' ] = array( $ctxProduct[ 'id' ] );

				if( !self::_FilterValues( $items ) )
					return( false );
				unset( $items );
			}
		}

		{
			$vals = Gen::GetArrField( $filters, 'categs', array(), '/' );
			if( $vals )
			{
				$items = array();

				$item = &self::_ValsCheck_Add( $items, '', Gen::GetArrField( $filters, 'categsOp', 'e', '/' ) == 'in', $vals );
				$item[ 'v' ] = wp_get_object_terms( $ctxProduct[ 'id' ], 'product_cat', array( 'fields' => 'ids' ) );

				if( !self::_FilterValues( $items ) )
					return( false );
				unset( $items );
			}
		}

		{
			$vals = Gen::GetArrField( $filters, 'tags', array(), '/' );
			if( $vals )
			{
				$items = array();

				$item = &self::_ValsCheck_Add( $items, '', Gen::GetArrField( $filters, 'tagsOp', 'e', '/' ) == 'in', array_map( 'mb_strtolower', $vals ) );
				$item[ 'v' ] = array_map( 'mb_strtolower', wp_get_object_terms( $ctxProduct[ 'id' ], 'product_tag', array( 'fields' => 'names' ) ) );

				if( !self::_FilterValues( $items ) )
					return( false );
				unset( $items );
			}
		}

		return( true );
	}

	static private function _AddItems( &$ctxProduct, &$items, $itemsSett )
	{
		foreach( $itemsSett as $itemSett )
		{
			if( !Gen::GetArrField( $itemSett, 'enable', true ) )
				continue;

			$item = array();

			switch( $item[ 'cType' ] = Gen::GetArrField( $itemSett, 'condType', 'quantity', '/' ) )
			{
			case 'quantity':
				$item[ 'cMin' ] = (isset($itemSett[ 'quantityMin' ])?$itemSett[ 'quantityMin' ]:null);
				$item[ 'cMax' ] = (isset($itemSett[ 'quantityMax' ])?$itemSett[ 'quantityMax' ]:null);
				break;

			}

			switch( $item[ 'type' ] = Gen::GetArrField( $itemSett, 'type', 'percent' ) )
			{
			case 'percent':			$item[ 'val' ] = (isset($itemSett[ 'percent' ])?$itemSett[ 'percent' ]:null); break;
			case 'currency':		$item[ 'val' ] = (isset($itemSett[ 'currency' ])?$itemSett[ 'currency' ]:null); break;
			case 'currencyPerItem':	$item[ 'val' ] = (isset($itemSett[ 'currency' ])?$itemSett[ 'currency' ]:null); break;
			}

			if( !( float )$item[ 'val' ] )
				continue;

			$items[] = $item;
		}
	}

	static private function _AdjustPrice( $productDiscountItems, $price, $attrs )
	{
		$priceRes = $price;
		foreach( $productDiscountItems[ 'items' ] as $item )
		{
			$priceResItem = self::_AdjustPriceItem( $item, $price, $attrs );
			if( $priceResItem >= $priceRes )
				continue;

			$priceRes = $priceResItem;

			break;
		}

		return( $priceRes );
	}

	static private function _AdjustPriceItem( $item, $price, $attrs )
	{
		$priceRes = $price;

		$quantity = $attrs[ 'quantity' ];
		$priceTotal = $quantity * $price;

		$apply = false;

		$cMin = ( float )$item[ 'cMin' ];
		$cMax = ( float )$item[ 'cMax' ];
		switch( Gen::GetArrField( $item, 'cType', 'quantity', '/' ) )
		{
		case 'quantity':
			if( self::_CheckInRange( $quantity, $cMin, $cMax ) )
				$apply = true;
			break;

		}

		if( $apply )
		{
			$val = ( float )$item[ 'val' ];
			switch( Gen::GetArrField( $item, 'type', 'percent' ) )
			{
			case 'percent':
				$priceCoeff = 1.0 - ( $val / 100 );
				if( $priceCoeff <= 0.0 )
					$priceCoeff = 0.0;
				if( $priceCoeff >= 1.0 )
					$priceCoeff = 1.0;

				$priceRes = $price * $priceCoeff;
				break;

			}
		}

		return( $priceRes );
	}
}

