<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2011
 * @author     Blair Winans <blair@winanscreative.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 *  Provides methods for working with multiple shipping on the Frontend
 */
class MultipleShippingFrontend extends Frontend
{
	
	/**
	 * Form ID
	 * @var string
	 */
	protected $strFormId = 'iso_mod_checkout';
	
	
	/**
	 * Isotope object
	 * @var object
	 */
	protected $Isotope;


	public function __construct()
	{
		parent::__construct();

		$this->import('Isotope');
	}
	

	/**
	 * Override ModuleIsotopeCheckout Shipping Address Interface
	 *
	 * Use this to add an option for multiple shipping destinations and add a step if checked
	 *
	 * @access public
	 * @param object
	 * @param bool
	 * @return string
	 */
	public function getShippingAddressInterface(&$objCheckoutModule, $blnReview=false)
	{	
		$this->strFormId = 'iso_mod_checkout_address';
		
		if (!$this->Isotope->Cart->requiresShipping)
			return '';
				
		if ($blnReview)
		{
			if ($this->Isotope->Cart->shippingAddress['id'] == -1)
				return false;

			return array
			(
				'shipping_address' => array
				(
					'headline'	=> $GLOBALS['TL_LANG']['ISO']['shipping_address'],
					'info'		=> $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields),
					'edit'		=> $this->addToUrl('step=address', true),
				),
			);
		}
				
		$objTemplate = new IsotopeTemplate('iso_checkout_shipping_address');

		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_address'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['shipping_address_message'];
		$objTemplate->fields =  $this->generateAddressWidget('shipping_address', $objCheckoutModule);

		if (!$objCheckoutModule->doNotSubmit)
		{
			$strShippingAddress = $this->Isotope->Cart->shippingAddress['id'] == -1 ? ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']) : $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields);

			$this->arrOrderData['shipping_address']			= $strShippingAddress;
			$this->arrOrderData['shipping_address_text']	= str_replace('<br />', "\n", $strShippingAddress);
			
			//************************************************************************
			//Unset the multiple shipping step if we do not need it
			if( $_SESSION['CHECKOUT_DATA']['shipping_address']['id'] != -2 )
			{
				unset($GLOBALS['ISO_CHECKOUT_STEPS']['multipleshipping']);
			}
			else
			{
				//Replace existing shipping modules step with multiple one
				$GLOBALS['ISO_CHECKOUT_STEPS']['shipping'] = array(
					array('MultipleShippingFrontend', 'getMultipleShippingModulesInterface')
				);
			}
			//************************************************************************
		}
			
		return $objTemplate->parse();
	}
	
	
	/**
	 * ModuleIsotopeCheckout Multiple Shipping Address Interface
	 *
	 * Creates an interface that lets the user add addresses to their order and pair those addresses with products
	 *
	 * @access public
	 * @param object
	 * @param bool
	 * @return string
	 */
	public function getMultipleShippingAddressInterface(&$objCheckoutModule, $blnReview=false)
	{	
		$this->strFormId = 'iso_mod_checkout_multipleshipping';
		
		if (!$this->Isotope->Cart->requiresShipping)
			return '';
				
		if ($blnReview)
		{
			if ($this->Isotope->Cart->shippingAddress['id'] != 'multiple')
				return false;

			return array
			(
				'multipleshipping_address' => array
				(
					'headline'	=> $GLOBALS['TL_LANG']['ISO']['multipleshipping_address'],
					'info'		=> $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields),
					'edit'		=> $this->addToUrl('step=multipleshipping', true),
				),
			);
		}
				
		$objTemplate = new IsotopeTemplate('iso_checkout_multipleshipping_address');
                
		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address_message'];
		$objTemplate->addressfields =  $this->generateAddressWidgets('multipleshipping_address', $intOptions, $objCheckoutModule);
		$objTemplate->productfields =  $this->generateProductWidgets('multipleshipping_address', $objCheckoutModule);
		
		if (!$objCheckoutModule->doNotSubmit)
		{
			$objCheckoutModule->arrOrderData['multipleshipping_address']	= $this->Isotope->Cart->multipleshippingAddress;
			
			//Replace existing shipping modules step with multiple one
			$GLOBALS['ISO_CHECKOUT_STEPS']['shipping'] = array(
				array('MultipleShippingFrontend', 'getMultipleShippingModulesInterface')
			);
		}
				
		return $objTemplate->parse();
	}
	
	/**
	 * ModuleIsotopeCheckout Multiple Shipping Modules Interface
	 *
	 * Creates an interface that lets the user add a shipping method for each shipping destination
	 *
	 * @access public
	 * @param object
	 * @param bool
	 * @return string
	 */
	public function getMultipleShippingModulesInterface(&$objCheckoutModule, $blnReview=false)
	{

		$this->strFormId = 'iso_mod_checkout_shipping';
		
		if ($blnReview)
		{
			if (!$this->Isotope->Cart->hasShipping)
				return false;

			return array
			(
				'shipping_method' => array
				(
					'headline'	=> $GLOBALS['TL_LANG']['ISO']['checkout_multipleshipping'],
					'info'		=> $this->multipleShippingcheckoutReview(),
					'note'		=> $this->multipleShippingcheckoutNotes(),
					'edit'		=> $this->addToUrl('step=multipleshipping', true),
				),
			);
		}
		
		$arrMultipleShippingData = array();
		
		$arrProducts = array();
		$arrInitialProducts = $this->Isotope->Cart->getProducts();
		//Quick foreach to set product IDs as keys - probably a quicker way to do this
		foreach($arrInitialProducts as $objProduct)
		{
			$arrProducts[$objProduct->cart_id] = $objProduct;
		}

		$arrProductaddresses = $this->Isotope->Cart->multipleshipping_address['products'];
		$arrAddresses = $this->Isotope->Cart->multipleshipping_address['addresses'];

		foreach($arrProductaddresses as $productCartID=>$arrAdd)
		{
			foreach($arrAdd as $addressKey)
			{
				//Get billing Address if necessary
				$arrAddress = $addressKey==-1 ? $this->Isotope->Cart->billingAddress : $arrAddresses[$addressKey];
				$arrPackages[$addressKey]['address'] = $arrAddress;
				$arrPackages[$addressKey]['products'][] = $arrProducts[$productCartID];
				$arrPackages[$addressKey]['productids'][] = $productCartID;
			}
		}
		
		$arrModuleIds = deserialize($objCheckoutModule->iso_shipping_modules);
		
		if (is_array($arrModuleIds) && count($arrModuleIds))
		{
			$fltShippingTotalPrice = 0.00;
			$objCartShipping = new StdClass();
			$arrData = $this->Input->post('multipleshipping');
			
			foreach($arrPackages as $key=>$arrPackage)
			{
				$arrModules = array();
				$objModules = $this->Database->execute("SELECT * FROM tl_iso_shipping_modules WHERE id IN (" . implode(',', $arrModuleIds) . ")" . (BE_USER_LOGGED_IN ? '' : " AND enabled='1'"));
				
				while( $objModules->next() )
				{				
					$strClass = $GLOBALS['ISO_SHIP'][$objModules->type];
	
					if (!strlen($strClass) || !$this->classFileExists($strClass))
						continue;
	
					$objModule = new $strClass($objModules->row(), $arrPackage); //Adding package onto Shipping module construct
										
					if (!$objModule->available)
						continue;
						
					$fltPrice = $objModule->price;
		 			$strSurcharge = $objModule->surcharge;
		 			$strPrice = $fltPrice != 0 ? (($strSurcharge == '' ? '' : ' ('.$strSurcharge.')') . ': '.$this->Isotope->formatPriceWithCurrency($fltPrice)) : '';
					
					if (is_array($arrData[$key]) && $arrData[$key]['module'] == $objModule->id)
		 			{
		 				$_SESSION['CHECKOUT_DATA']['shipping'][$key] = $arrData;
		 			}
	
		 			if (is_array($_SESSION['CHECKOUT_DATA']['shipping'][$key]) && $_SESSION['CHECKOUT_DATA']['shipping'][$key]['module'] == $objModule->id)
		 			{
		 				$objCartShipping->$key = $objModule;
						$fltShippingTotalPrice += $fltPrice;
		 			}
					
		 			$arrModules[] = array
		 			(
		 				'id'			=> $objModule->id,
		 				'key'			=> $key,
		 				'label'		=> $objModule->label,
		 				'price'		=> $strPrice,
		 				'checked'	=> (($objCartShipping->$key->id == $objModule->id || $objModules->numRows==1) ? ' checked="checked"' : ''),
		 				'note'		=> $objModule->note,
		 				'form'		=> $objModule->getShippingOptions($objCheckoutModule),
		 			);
	
		 			$objLastModule = $objModule;
				}
				
				$objTemplate = new IsotopeTemplate('multipleshipping_method');
		
				if(!count($arrModules))
				{				
					$objCheckoutModule->doNotSubmit = true;
					$objCheckoutModule->Template->showNext = false;
		
					$objTemplate = new FrontendTemplate('mod_message');
					$objTemplate->class = 'shipping_method';
					$objTemplate->type = 'error';
					$objTemplate->message = $GLOBALS['TL_LANG']['MSC']['noShippingModules'];
					$strBuffer .= $objTemplate->parse();
					continue;
				}
				elseif (!$objCartShipping->$key && !strlen($_SESSION['CHECKOUT_DATA']['shipping'][$key]['module']) && count($arrModules) == 1)
				{
					$objCartShipping->$key = $objLastModule;
					$_SESSION['CHECKOUT_DATA']['shipping'][$key]['module'] = $objCartShipping->$key->id;
				}
				elseif (!$objCartShipping->$key)
				{
					if ($this->Input->post('FORM_SUBMIT') != '')
					{
						$objTemplate->error = $GLOBALS['TL_LANG']['ISO']['shipping_method_missing'];
					}
									
					$objCheckoutModule->doNotSubmit = true;
				}
				
				if (!$objCheckoutModule->doNotSubmit)
				{	
					$arrMultipleShippingData[$key]['products'] = $arrPackage['productids'];
					$arrMultipleShippingData[$key]['id'] =  $objCartShipping->$key->id;
								
					$objCheckoutModule->arrOrderData['shipping_method_id'][$key]	= $objCartShipping->$key->id;
					$objCheckoutModule->arrOrderData['shipping_method'][$key]		= $objCartShipping->$key->label;
					$objCheckoutModule->arrOrderData['shipping_note'][$key]	= $objCartShipping->$key->note;
					$objCheckoutModule->arrOrderData['shipping_note_text'][$key] 	= strip_tags($objCartShipping->$key->note);
				}
				
				$objTemplate->address = $this->Isotope->generateAddressString($arrPackage['address'], $this->Isotope->Config->shipping_fields);
		 		$objTemplate->products = $arrPackage['products'];
				$objTemplate->shippingMethods = $arrModules;
				
				$strBuffer .= $objTemplate->parse();
			}
		}
		
		$objCartShipping->packages = $arrPackages;
		$objCartShipping->price = $fltShippingTotalPrice; //Setting the shippingTotal
		$objCartShipping->id	= 'multiple'; 
		$this->Isotope->Cart->Shipping = $objCartShipping;
		$this->Isotope->Cart->multipleshipping = $arrMultipleShippingData;
					
		$objTemplate = new IsotopeTemplate('iso_checkout_multipleshipping_method');
		
		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_method'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['shipping_method_message'];
		$objTemplate->fields = $strBuffer;

		// Remove payment step if items are free of charge
		if (!$this->Isotope->Cart->requiresPayment)
		{
			unset($GLOBALS['ISO_CHECKOUT_STEPS']['payment']);
		}

		return $objTemplate->parse();
	}

	
	
	/**
	 * Override ModuleIsotopeCheckout generateAddressWidget
	 *
	 * Used to add an additional shipping option for multiple shipping destinations
	 *
	 * @access protected
	 * @param string
	 * @return string
	 */
	protected function generateAddressWidget($field, &$objCheckoutModule)
	{
		$strBuffer = '';
		$arrOptions = array();
		$arrCountries = ($field == 'billing_address' ? $this->Isotope->Config->billing_countries : $this->Isotope->Config->shipping_countries);

		if (FE_USER_LOGGED_IN)
		{
			$objAddress = $this->Database->execute("SELECT * FROM tl_iso_addresses WHERE pid={$this->User->id} AND store_id={$this->Isotope->Config->store_id} ORDER BY isDefaultBilling DESC, isDefaultShipping DESC");

			while( $objAddress->next() )
			{
				if (is_array($arrCountries) && !in_array($objAddress->country, $arrCountries))
					continue;

				$arrOptions[] = array
				(
					'value'		=> $objAddress->id,
					'label'		=> $this->Isotope->generateAddressString($objAddress->row(), ($field == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields)),
				);
			}
		}

		switch($field)
		{
			case 'shipping_address':
				$arrAddress = $_SESSION['CHECKOUT_DATA'][$field] ? $_SESSION['CHECKOUT_DATA'][$field] : $this->Isotope->Cart->shippingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : -1;

				array_insert($arrOptions, 0, array(array
				(
					'value'	=> -1,
					'label' => ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']),
				)));

				$arrOptions[] = array
				(
					'value'	=> 0,
					'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
				);
				//************************************************************************
				//Add in multiple shipping option
				$arrOptions[] = array
				(
					'value'	=> -2,
					'label' => $GLOBALS['TL_LANG']['MSC']['multipleShippingAddress'],
				);
				//************************************************************************
				break;

			case 'billing_address':
			default:
				$arrAddress = $_SESSION['CHECKOUT_DATA'][$field] ? $_SESSION['CHECKOUT_DATA'][$field] : $this->Isotope->Cart->billingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : 0;

				if (FE_USER_LOGGED_IN)
				{
					$arrOptions[] = array
					(
						'value'	=> 0,
						'label' => &$GLOBALS['TL_LANG']['MSC']['createNewAddressLabel'],
					);
				}
				break;
		}

		// HOOK: add custom addresses, such as from a stored gift registry ******** ADDED BY BLAIR
		if (isset($GLOBALS['ISO_HOOKS']['addCustomAddress']) && is_array($GLOBALS['ISO_HOOKS']['addCustomAddress']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['addCustomAddress'] as $callback)
			{
				$this->import($callback[0]);
				$arrOptions = $this->$callback[0]->$callback[1]($arrOptions, $field, $objCheckoutModule);
			}
		}

		if (count($arrOptions))
		{
			$strClass = $GLOBALS['TL_FFL']['radio'];

			$arrData = array('id'=>$field, 'name'=>$field, 'mandatory'=>true);

			$objWidget = new $strClass($arrData);
			$objWidget->options = $arrOptions;
			$objWidget->value = $intDefaultValue;
			$objWidget->onclick = "Isotope.toggleAddressFields(this, '" . $field . "_new');";
			$objWidget->storeValues = true;
			$objWidget->tableless = true;

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
			{
				$objWidget->validate();

				if ($objWidget->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}
				else
				{
					$_SESSION['CHECKOUT_DATA'][$field]['id'] = $objWidget->value;
				}
			}
			elseif ($objWidget->value != '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}
			}

			$strBuffer .= $objWidget->parse();
		}

		if (strlen($_SESSION['CHECKOUT_DATA'][$field]['id']))
		{
			$this->Isotope->Cart->$field = $_SESSION['CHECKOUT_DATA'][$field]['id'];
		}
		elseif (!FE_USER_LOGGED_IN)
		{

		//	$objCheckoutModule->doNotSubmit = true;
		}


		$strBuffer .= '<div id="' . $field . '_new" class="address_new"' . (((!FE_USER_LOGGED_IN && $field == 'billing_address') || $objWidget->value == 0) ? '>' : ' style="display:none">');
		$strBuffer .= '<span>' . $this->generateAddressWidgets($field, count($arrOptions), $objCheckoutModule) . '</span>';
		$strBuffer .= '</div>';

		return $strBuffer;
	}
	


	/**
	 * Generate the current step widgets.
	 * Only need this here because we can't access the protected method in the checkout module
	 * and we need to pass the checkout module, plus making the fields not mandatory in some cases
	 */
	protected function generateAddressWidgets($strAddressType, $intOptions, &$objCheckoutModule)
	{
		$arrBuffer = array();

		$this->loadLanguageFile('tl_iso_addresses');
		$this->loadDataContainer('tl_iso_addresses');

		$arrFields = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields);
		$arrDefault = $this->Isotope->Cart->$strAddressType;

		if ($arrDefault['id'] == -1)
			$arrDefault = array();

		foreach( $arrFields as $field )
		{
			$arrData = $GLOBALS['TL_DCA']['tl_iso_addresses']['fields'][$field['value']];

			if (!is_array($arrData) || !$arrData['eval']['feEditable'] || !$field['enabled'] || ($arrData['eval']['membersOnly'] && !FE_USER_LOGGED_IN))
				continue;

			$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

			// Continue if the class is not defined
			if (!$this->classFileExists($strClass))
				continue;

			// Special field "country"
			if ($field['value'] == 'country')
			{
				$arrCountries = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_countries : $this->Isotope->Config->shipping_countries);

				$arrData['options'] = array_values(array_intersect($arrData['options'], $arrCountries));
				$arrData['default'] = $this->Isotope->Config->country;
			}

			// Special field type "conditionalselect"
			elseif (strlen($arrData['eval']['conditionField']))
			{
				$arrData['eval']['conditionField'] = $strAddressType . '_' . $arrData['eval']['conditionField'];
			}

			// Special fields "isDefaultBilling" & "isDefaultShipping"
			elseif (($field['value'] == 'isDefaultBilling' && $strAddressType == 'billing_address' && $intOptions < 2) || ($field['value'] == 'isDefaultShipping' && $strAddressType == 'shippping_address' && $intOptions < 3))
			{
				$arrDefault[$field['value']] = '1';
			}

			$i = count($arrBuffer);
			
			//************************************************************************
			//Custom for multiple shipping
			if($strAddressType == 'multipleshipping_address')
			{
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']]) ? $_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']] : $arrDefault[$field['value']])));
				$objWidget->mandatory = $field['mandatory'] && $strAddressType =='multipleshipping_address' && $this->Input->post('addAddress') ? true : false;
			}
			else
			{
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']]) ? $_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']] : $arrDefault[$field['value']])));
				$objWidget->mandatory = $field['mandatory'] ? true : false;
			}
			//************************************************************************

			$objWidget->required = $objWidget->mandatory;
			$objWidget->tableless = $objCheckoutModule->tableless;
			$objWidget->label = $field['label'] ? $this->Isotope->translate($field['label']) : $objWidget->label;
			$objWidget->storeValues = true;
			$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '' || $strAddressType=='multipleshipping_address'))
			{
				$objWidget->validate();

				$varValue = $objWidget->value;

				// Convert date formats into timestamps
				if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
				{
					$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
					$varValue = $objDate->tstamp;
				}

				// Do not submit if there are errors
				if ($objWidget->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}

				// Store current value
				elseif ($objWidget->submitInput())
				{
					$arrAddress[$field['value']] = $varValue;
				}
			}
			elseif ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}
			}

			$arrBuffer[] = $objWidget->parse();
		}
		
		//************************************************************************
		//Custom for multiple shipping - Add addAddress submit
		if($strAddressType == 'multipleshipping_address')
		{
			$strClass = $GLOBALS['TL_FFL']['submit'];
			$arrData = array('id'=>'addAdress' , 'name'=>'addAddress');
			$objWidget = new $strClass($arrData );
			$objWidget->slabel = $GLOBALS['TL_LANG']['MSC']['addShippingAddress'];
			$objWidget->tableless = $objCheckoutModule->tableless;
			$arrBuffer[] = $objWidget->parse();
			$i++;
		}
		//************************************************************************
		
		// Add row_last class to the last widget
		array_pop($arrBuffer);
		$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ' row_last' . ((($i % 2) == 0) ? ' even' : ' odd');
		$arrBuffer[] = $objWidget->parse();

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$objCheckoutModule->doNotSubmit && is_array($arrAddress) && count($arrAddress))
		{					
			$arrAddress['id'] = 0;
			//************************************************************************
			//Custom for multiple shipping - Array instead of storing value if multipleshipping
			if($strAddressType != 'multipleshipping_address')
			{
				$_SESSION['CHECKOUT_DATA'][$strAddressType] = $arrAddress;
			}
			elseif($this->Input->post('addAddress'))
			{
				$count = count($_SESSION['CHECKOUT_DATA'][$strAddressType]['addresses']) + 1;
				$_SESSION['CHECKOUT_DATA'][$strAddressType]['addresses'][$count] = $arrAddress;
			}
			//************************************************************************
		}
		
		//************************************************************************
		//Custom for multiple shipping - Don't validate if addAddress is present
		if($strAddressType == 'multipleshipping_address' && $this->Input->post('addAddress'))
		{
			$objCheckoutModule->doNotSubmit = true;
		}
		//************************************************************************
		
		//************************************************************************
		//Custom for multiple shipping - Don't need to check for ID
		if (is_array($_SESSION['CHECKOUT_DATA'][$strAddressType]) && ($_SESSION['CHECKOUT_DATA'][$strAddressType]['id'] === 0 || $strAddressType=='multipleshipping_address'))
		{
			$this->Isotope->Cart->$strAddressType = $_SESSION['CHECKOUT_DATA'][$strAddressType];
		}

		if ($objCheckoutModule->tableless)
		{
			return implode('', $arrBuffer);
		}

		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	}
	
	
	/**
	 * Generate a table of products and select widgets of addresses to pair them with
	 *
	 * Used to match batches of products to multiple shipping destinations
	 *
	 * @access protected
	 * @param string
	 * @param ModuleIsotopeCheckout
	 * @return string
	 */
	protected function generateProductWidgets( $strField, &$objCheckoutModule )
	{	
		$arrBuffer = array();
						
		//Get existing Cart products
		$arrProducts = $this->Isotope->Cart->getProducts();
		
		//Add empty option
		$arrOptions[] = array
		(
			'value'	=> '',
			'label' =>  $GLOBALS['TL_LANG']['MSC']['selectAddress'],
		);
		
		//Get existing addresses, starting with billing address as an option
		$arrOptions[] = array
		(
			'value'	=> -1,
			'label' =>  ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']),
		);

		if(is_array($_SESSION['CHECKOUT_DATA'][$strField]['addresses']))
		{
			foreach($_SESSION['CHECKOUT_DATA'][$strField]['addresses'] as $key=>$address)
			{
				$arrOptions[] = array
				(
					'value'	=> $key,
					'label' =>  $address['lastname'] . ', ' . $address['firstname'] . ': ' . $address['city'] . ', ' . $address['subdivision'],
				);
			}
		}
		
		foreach($arrProducts as $i => $objProduct)
		{
			for($j = 0; $j < $objProduct->quantity_requested; $j++)
			{
				$strClass = $GLOBALS['TL_FFL']['select'];

				$arrData = array('id'=>$strField . '['.$objProduct->id.']['.$j.']' , 'name'=>$strField . '['.$objProduct->cart_id.']['.$j.']');
			
				$objWidget = new $strClass($arrData);
				$objWidget->mandatory = ($this->Input->post('nextStep') && !strlen($_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j])) ? true : false;
				$objWidget->required = $objWidget->mandatory;
				$objWidget->options = $arrOptions;
				$objWidget->value = $_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j];
				$objWidget->storeValues = true;
				$objWidget->tableless = $objCheckoutModule->tableless;
				$objWidget->label = $objProduct->images->generateMainImage('gallery') . '<span>'. $objProduct->name . '</span>';
				
				// Validate input
				if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
				{
					$objWidget->validate();
	
					if ($objWidget->hasErrors())
					{
						$objCheckoutModule->doNotSubmit = true;
					}
					else
					{
						$_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j] = $objWidget->value;
					}
				}
				elseif ($objWidget->value != '')
				{
					$this->Input->setPost($objWidget->name, $objWidget->value);
	
					$objValidator = clone $objWidget;
					$objValidator->validate();
	
					if ($objValidator->hasErrors())
					{
						$objCheckoutModule->doNotSubmit = true;
					}
				}
				
				$arrBuffer[] = $objWidget->parse();
				
			}
		}
				
		if ($objCheckoutModule->tableless)
		{
			return implode('', $arrBuffer);
		}

		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	
	}
	
	/**
	 * Return multiple shipping data for checkout review step
	 *
	 * Need to aggregate the review from each shipping module
	 *
	 * @access public
	 * @return string
	 */
	public function multipleShippingcheckoutReview()
	{
		$strBuffer = '';
		$arrPackages = $this->Isotope->Cart->Shipping->packages;
		
		foreach($arrPackages as $key=>$arrPackage)
		{
			$objTemplate = new IsotopeTemplate('multipleshipping_review');
			$objTemplate->address = $this->Isotope->generateAddressString($arrPackage['address'], $this->Isotope->Config->shipping_fields);
			$objTemplate->label = $this->Isotope->Cart->Shipping->$key->checkoutReview();
			
			$fltPrice = $this->Isotope->Cart->Shipping->$key->price;
			$strSurcharge = $this->Isotope->Cart->Shipping->$key->surcharge;
		 	$objTemplate->price = $fltPrice != 0 ? (($strSurcharge == '' ? '' : ' ('.$strSurcharge.')') . ' '.$this->Isotope->formatPriceWithCurrency($fltPrice)) : '';
			
			$strBuffer .= $objTemplate->parse();
		}
		
		return $strBuffer;
		
	}
	
	/**
	 * Return multiple shipping notes for checkout review step
	 *
	 * Need to aggregate the notes from each shipping module
	 *
	 * @access public
	 * @return string
	 */
	public function multipleShippingcheckoutNotes()
	{
		$strBuffer = '';
		$arrPackages = $this->Isotope->Cart->Shipping->packages;
		
		foreach($arrPackages as $key=>$arrPackage)
		{
			$strBuffer .= '<span>'  . $this->Isotope->Cart->Shipping->$key->note . '</span>';
		}
				
		return $strBuffer;
	
	}
	
	
	/**
	 * Hook-callback for isoCheckoutSurcharge. Accesses the shipping module to get a shipping surcharge.
	 *
	 * @access	public
	 * @param	array
	 * @return	array
	 */
	public function getShippingSurcharge($arrSurcharges)
	{
		if($this->Isotope->Cart->Shipping->id==-2 && TL_MODE=='FE')
		{
			//find the existing Shipping surcharge and unset it
			foreach($arrSurcharges as $k=>$arrSurcharge)
			{
				//@todo This is pretty much our only way to identify this at this point. Perhaps another sort of ID?
				if( preg_match('/'.$GLOBALS['TL_LANG']['MSC']['shippingLabel'].'/', $arrSurcharge['label']) )
					unset($arrSurcharges[$k]);
			}
		
			$arrPackages = $this->Isotope->Cart->Shipping->packages;
			
			if ($this->Isotope->Cart->hasShipping && $this->Isotope->Cart->Shipping->price != 0)
			{
				foreach($arrPackages as $key=>$arrPackage)
				{
					$strSurcharge = $this->Isotope->Cart->Shipping->$key->surcharge;
	
					$arrSurcharges[] = array
					(
						'label'			=> ($GLOBALS['TL_LANG']['MSC']['shippingLabel'] . ' (' . $this->Isotope->Cart->Shipping->$key->label . ')'),
						'price'			=> ($strSurcharge == '' ? '&nbsp;' : $strSurcharge),
						'total_price'	=> $this->Isotope->Cart->Shipping->$key->price,
						'tax_class'		=> $this->Isotope->Cart->Shipping->$key->tax_class,
						'before_tax'	=> ($this->Isotope->Cart->Shipping->$key->tax_class ? true : false),
					);
				}
			}
		}

		return $arrSurcharges;
	}
	
	
	/**
	 * Add multiple shipping product options
	 *
	 * @access public
	 * @param	array
	 * @return	void
	 */
	protected function setProductOptions($arrPackage)
	{
		foreach($arrPackage['products'] as $arrProductData)
		{
			$objProduct = $arrProductData['product'];
			$quantity = $arrProductData['count'];
			$arrOptions = deserialize($objProduct->getOptions(true), true);
			$arrOptions['Shipping'] = $this->Isotope->generateAddressString($arrPackage['address'], $this->Isotope->Config->shipping_fields);
			
			$objNewProduct = clone $objProduct;
			
			$objNewProduct->setOptions($arrOptions);
			$intInsertId = $this->Isotope->Cart->addProduct($objNewProduct , $quantity);
			
			$this->Database->execute("UPDATE tl_iso_cart_items SET package_id={$arrProductData['packageid']} WHERE id={$intInsertId}");
			
			$this->Isotope->Cart->deleteProduct($objProduct);
		}
	}
	
	
	/**
	 * Hook-callback for preCheckout. Adds the addresses to each product as product options for easy identification.
	 * Also moves each package to the tl_iso_packages table
	 *
	 * @access public
	 * @param	IsotopeOrder
	 * @param	IsotopeCart
	 * @return	bool
	 */
	public function preCheckoutMultipleShipping($objOrder, $objCart)
	{
		$arrShipping = $objCart->multipleshipping;
		$arrAddresses = $objCart->multipleshipping_address;
		$objOrder->shipping_data = $arrAddresses; //Set the address data so we can retrieve later for cleanup
		$arrInitialProducts = $objCart->getProducts();
		
		//Order has multiple shipping
		if(is_array($arrShipping) && is_array($arrAddresses))
		{
			$objOrder->shipping_multiple = 1; //Flag so we know which backend interface to load
			$objOrder->save();
			
			//Quick foreach to set product IDs as keys - probably a quicker way to do this
			foreach($arrInitialProducts as $objProduct)
			{
				$arrProducts[$objProduct->cart_id] = $objProduct;
			}
			
			foreach($arrShipping as $intAddress => $arrSettings)
			{
				$arrPackage = array();
				$arrPackage['address'] = $intAddress==-1 ? $objCart->billingAddress : $arrAddresses['addresses'][$intAddress];
				
				$arrSet = array(
					'pid'							=> $objOrder->id,
					'tstamp'					=> time(),
					'shipping_id'				=> $arrSettings['id'],
					'order_address_id'		=> $intAddress,
					'order_address'			=> $arrPackage['address'],
					'config_id'					=> $this->Isotope->Config->id,
					'status'						=> 'not_shipped'
				);
				
				$intPackageID = $this->Database->prepare("INSERT INTO tl_iso_packages %s")->set($arrSet)->execute()->insertId;
				
				foreach($arrSettings['products'] as $productCartId)
				{
					$arrPackage['products'][$productCartId]['product'] = $arrProducts[$productCartId];
					$arrPackage['products'][$productCartId]['count'] = $arrPackage['products'][$productCartId]['count'] +1;
					$arrPackage['products'][$productCartId]['packageid'] = $intPackageID;
				}
				$this->setProductOptions($arrPackage); //Attach addresses to Products using setOptions
				
			}
		}
		else //Order has single shipping address and therefore single package
		{
			$objOrder->shipping_status = 'not_shipped';
			$objOrder->save();
		
			$arrSet = array(
				'pid'					=> $objOrder->id,
				'tstamp'				=> time(),
				'order_address_id'		=> 0,
				'order_address'			=> $objCart->shippingAddress,
				'config_id'				=> $this->Isotope->Config->id,
				'status'				=> 'not_shipped'
			);
			
			$intPackageID = $this->Database->prepare("INSERT INTO tl_iso_packages %s")->set($arrSet)->execute()->insertId;
			
			$arrUpdate = array('package_id' => $intPackageID);
			
			foreach($arrInitialProducts as $objProduct)
			{
				$objCart->updateProduct($objProduct, $arrUpdate);
			}
			
		}
		
		return true;
	}
	
}