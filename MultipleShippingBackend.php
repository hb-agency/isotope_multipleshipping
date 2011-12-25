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


class MultipleShippingBackend extends Backend
{

	/**
	 * Form ID - Needs to be set to this to trigger payment modules
	 * @var string
	 */
	protected $strFormId = 'iso_mod_checkout_payment';
	
	/**
	 * Import Isotope object
	 *
	 * @access public
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Isotope');
		// Load CSS
		$GLOBALS['TL_CSS']['multipleshipping'] = 'system/modules/isotope_multipleshipping/html/multipleshipping.css';
	}
	

	/**
	 * Build the multiple shipping interface
	 *
	 * @access public
	 * @param DataContainer
	 * @return string
	 */
	public function shippingInterface($dc)
	{
		$objOrder = $this->Database->execute("SELECT * FROM tl_iso_orders WHERE id=".$dc->id);
		
		$GLOBALS['TL_CSS'][] = 'system/modules/isotope_multipleshipping/html/multipleshipping.css';
		
		if(!$objOrder->shipping_multiple)
		{
			$this->import('tl_iso_orders');
			return $this->tl_iso_orders->shippingInterface($dc, false); //Return single shipping interface
		}
		
		$objTemplate = new IsotopeTemplate('be_iso_multipleshipping');
		$arrPackages = array();
		$objPackages = $this->Database->execute("SELECT p.*, o.*, s.*, s.type as type, p.id as packageid FROM tl_iso_packages p INNER JOIN tl_iso_orders o ON p.pid=o.id LEFT JOIN tl_iso_shipping_modules s ON s.id=p.shipping_id WHERE p.pid=".$dc->id);

		if (!$objPackages->numRows)
		{
			return '<p class="tl_gerror">'.$GLOBALS['TL_LANG']['ISO']['backendShippingNotFound'].'</p>';
		}
		
		while($objPackages->next() )
		{
			$strClass = $GLOBALS['ISO_SHIP'][$objPackages->type];
	
			if (!strlen($strClass) || !$this->classFileExists($strClass))
			{
				return '<p class="tl_gerror">'.$GLOBALS['TL_LANG']['ISO']['backendShippingNotFound'].'</p>';
			}
	
			$objModule = new $strClass($objPackages->row());
	
			$arrPackages[] = $objModule->backendInterface($dc->id, true, $objPackages->packageid);
		}
		
		$objTemplate->backLink = ampersand(str_replace('&key=shipping', '', $this->Environment->request));
		$objTemplate->backLabel = $GLOBALS['TL_LANG']['MSC']['backBT'];
		$objTemplate->headline = sprintf($GLOBALS['TL_LANG']['ISO']['multipleshipping_backend'], $dc->id);
		$objTemplate->packages = $arrPackages;
		
		return $objTemplate->parse();
		
	}
	
	
	/**
	 * Generate the address interface
	 */
	public function getAddressInterface(&$objModule)
	{
		$blnRequiresPayment = $this->Isotope->Order->requiresPayment;

		$objTemplate = new IsotopeTemplate('be_iso_order_address');
		
		$strBuffer = '';
		
		//Add in the Member table Lookup
		$arrLookup = array
		(
				'id'			=>	'member_lookup',
				'name'			=>	'member_lookup',
				'label'			=>  $GLOBALS['TL_LANG']['tl_iso_orders']['member_lookup'],
				'sqlWhere'		=> 'disable!=1',
				'searchLabel'	=> 'Search Members',
				'fieldType'		=> 'radio',
				'foreignTable'	=> 'tl_member',
				'searchFields'	=> array('firstname', 'lastname'),
				'listFields'	=> array('firstname', 'lastname'),
		);
			
		$objLookupWidget = new TableLookupWizard($arrLookup);
		$objLookupWidget->value = $this->Isotope->Order->pid ? $this->Isotope->Order->pid : '';
		
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
		{
			$objModule->arrOrderData['user'] = $this->Input->post('member_lookup');
		}
		
		$strBuffer .= '<div id="member_lookup">' . $objLookupWidget->parse() . '</div>';	
		
		$objTemplate->headline = $blnRequiresPayment ? $GLOBALS['TL_LANG']['ISO']['billing_address'] : $GLOBALS['TL_LANG']['ISO']['customer_address'];
		$objTemplate->message = (FE_USER_LOGGED_IN ? $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_message'] : $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_guest_message']);
		$objTemplate->addressfields = $this->generateAddressWidget('billing_address', $objModule) . $this->generateAddressWidget('shipping_address', $objModule);
		$objTemplate->lookup = $strBuffer;
		
		$strBillingAddress = $this->Isotope->generateAddressString($this->Isotope->Order->billingAddress, $this->Isotope->Config->billing_fields);
		
		$strShippingAddress = $this->Isotope->Order->shippingAddress['id'] == -1 ? ($this->Isotope->Order->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']) : $this->Isotope->generateAddressString($this->Isotope->Order->shippingAddress, $this->Isotope->Config->shipping_fields);

		$objModule->arrOrderData['billing_address_html'] 	= $strBillingAddress;
		$objModule->arrOrderData['billing_address_text']	= str_replace('<br />', "\n", $strBillingAddress);
		$objModule->arrOrderData['shipping_address_html']	= $strShippingAddress;
		$objModule->arrOrderData['shipping_address_text']	= str_replace('<br />', "\n", $strShippingAddress);
					
		//************************************************************************
						
		//Unset the multiple shipping step if we do not need it
		if( $this->Isotope->Order->shippingAddress['id'] != -2 )
		{
			unset($GLOBALS['ISO_ORDER_STEPS']['multipleshipping']);
		}
		else
		{
			//Replace existing shipping modules step with multiple one
			$GLOBALS['ISO_ORDER_STEPS']['shipping'] = array(
				array('MultipleShippingBackend', 'getMultipleShippingModulesInterface')
			);
		}
		//************************************************************************
		
		return $this->Input->post('isAjax') ? $objTemplate->addressfields : $objTemplate->parse();
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
	public function getMultipleShippingAddressInterface(&$objModule)
	{	
		
		if (!$this->Isotope->Order->requiresShipping)
			return '';

				
		$objTemplate = new BackendTemplate('be_iso_order_multipleshipping_address');
                
		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address_message'];
		$objTemplate->addressfields =  $this->generateAddressWidgets('multipleshipping_address', $intOptions, $objModule);
		$objTemplate->productfields =  $this->generateProductWidgets('multipleshipping_products', $objModule);
					
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
	public function getMultipleShippingModulesInterface(&$objCheckoutModule)
	{
		$arrMultipleShippingData = array();
		
		$arrProducts = array();
		$arrInitialProducts = $this->Isotope->Order->getProducts();
		//Quick foreach to set product IDs as keys - probably a quicker way to do this
		foreach($arrInitialProducts as $objProduct)
		{
			$arrProducts[$objProduct->cart_id] = $objProduct;
		}
						
		$arrShippingData = $this->Isotope->Order->shipping_data;
						
		$arrProductaddresses = $arrShippingData['products'];
		$arrAddresses = $arrShippingData['addresses'];

		foreach($arrProductaddresses as $productCartID=>$arrAdd)
		{
			foreach($arrAdd as $addressKey)
			{
				//Get billing Address if necessary
				$arrAddress = $addressKey==-1 ? $this->Isotope->Order->billingAddress : $arrAddresses[$addressKey];
				$arrPackages[$addressKey]['address'] = $arrAddress;
				$arrPackages[$addressKey]['products'][] = $arrProducts[$productCartID];
				$arrPackages[$addressKey]['productids'][] = $productCartID;
			}
		}
				
		$arrModuleIds = $this->Database->execute("SELECT * FROM tl_iso_shipping_modules WHERE enabled='1'")->fetchEach('id');
		
		if (is_array($arrModuleIds) && count($arrModuleIds))
		{
			$fltShippingTotalPrice = 0.00;
			$objCartShipping = new stdClass();
			$arrData = $this->Input->post('multipleshipping');
						
			foreach($arrPackages as $key=>$arrPackage)
			{
				$arrModules = array();
				$objModules = $this->Database->execute("SELECT * FROM tl_iso_shipping_modules WHERE id IN (" . implode(',', $arrModuleIds) . ")" . (BE_USER_LOGGED_IN ? '' : " AND enabled='1'"));
				
				while( $objModules->next() )
				{
					$objKey = new stdClass();
		 			$objKey->id = 0;
		 			$objCartShipping->$key = $objKey;
					
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
		 				$arrShippingData['shipping'][$key]['module'] = $arrData[$key]['module'];
		 			}
		 				
		 			if (is_array($arrShippingData['shipping'][$key]) && $arrShippingData['shipping'][$key]['module'] == $objModule->id)
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
					$objTemplate = new FrontendTemplate('mod_message');
					$objTemplate->class = 'shipping_method';
					$objTemplate->type = 'error';
					$objTemplate->message = $GLOBALS['TL_LANG']['MSC']['noShippingModules'];
					$strBuffer .= $objTemplate->parse();
					continue;
				}
				elseif (!$objCartShipping->$key && !strlen($arrShippingData['shipping'][$key]['module']) && count($arrModules) == 1)
				{
					$objCartShipping->$key = $objLastModule;
					$arrShippingData['shipping'][$key]['module'] = $objCartShipping->$key->id;
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
					
					$objCheckoutModule->arrOrderData['shipping'][$key]['module'] = $objCartShipping->$key->id;		
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
		$objCartShipping->id	= -2; 
		$this->Isotope->Order->Shipping = $objCartShipping;
		$this->Isotope->Order->shipping_data = $arrShippingData;
		$this->Isotope->Order->multipleshipping = $arrMultipleShippingData;
		$this->Isotope->Order->shippingTotal = $fltShippingTotalPrice;
		$this->Isotope->Order->save();
										
		$objTemplate = new IsotopeTemplate('be_iso_order_multipleshipping_method');
		
		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_method'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['shipping_method_message'];
		$objTemplate->fields = $strBuffer;

		// Remove payment step if items are free of charge
		if (!$this->Isotope->Order->requiresPayment)
		{
			unset($GLOBALS['ISO_ORDER_STEPS']['payment']);
		}

		return $objTemplate->parse();
	}

	
	/**
	 * Generate the address widget
	 */
	protected function generateAddressWidget($field, &$objModule)
	{
		$strBuffer = '<div id="'. $field . '">';
		$strBuffer .= '<h2>'. $GLOBALS['TL_LANG']['ISO'][$field] .'</h2>';
		$arrOptions['normal'] = array();
		$arrOptions['ajax'] = array();
		$intMember = 0;
				
		if ($this->Input->post('isAjax') && $this->Input->post('data') && $this->Input->post('action')=='resetAddresses')
		{
			$intMember = $this->Input->post('data');
		}
		else
		{
			$intMember =  $this->Isotope->Order->pid;
		}
		
		if($intMember > 0)
		{
			$objAddress = $this->Database->execute("SELECT * FROM tl_iso_addresses WHERE pid={$intMember} AND store_id={$this->Isotope->Config->store_id} ORDER BY isDefaultBilling DESC, isDefaultShipping DESC");
						
			while( $objAddress->next() )
			{
				$arrData = $objAddress->row();
				$arrData['country'] = strtolower($arrData['country']);
				$arrOptions['ajax'][] = array
				(
					'value'		=> $objAddress->id,
					'label'		=> $this->Isotope->generateAddressString($arrData, ($field == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields)),
				);
			}
						
		}
		
		switch($field)
		{
			case 'shipping_address':
				$arrAddress = $objModule->arrOrderData[$field] ? $objModule->arrOrderData[$field] : $this->Isotope->Order->shippingAddress;
								
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : -1;
								
				array_insert($arrOptions['normal'], 0, array(array
				(
					'value'	=> -1,
					'label' => $GLOBALS['TL_LANG']['MSC']['useBillingAddress'],
				)));

				$arrOptions['normal'][] = array
				(
					'value'	=> 0,
					'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
				);
				//************************************************************************
				//Add in multiple shipping option
				$arrOptions['normal'][] = array
				(
					'value'	=> -2,
					'label' => $GLOBALS['TL_LANG']['MSC']['multipleShippingAddress'],
				);
				//************************************************************************
				break;

			case 'billing_address':
			default:
				$arrAddress = $objModule->arrOrderData[$field] ? $objModule->arrOrderData[$field] : $this->Isotope->Order->billingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : 0;
				if(count($arrOptions['ajax']))
				{
					$arrOptions['normal'][] = array
					(
						'value'	=> 0,
						'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
					);
				}
				
				
				break;
		}
		
		if (count($arrOptions['ajax']) || count($arrOptions['normal']) || $this->Input->post($field) > 0) //Special check for addresses loaded via AJAX
		{		
			$strClass = $GLOBALS['TL_FFL']['radio'];

			$arrData = array('id'=>$field, 'name'=>$field, 'mandatory'=>true);

			$objWidget = new $strClass($arrData);
			$objWidget->options = array_merge($arrOptions['normal'], $arrOptions['ajax']);
			$objWidget->value = $intDefaultValue;
			$objWidget->storeValues = true;
			$objWidget->tableless = true;
			
			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
			{				
				$objWidget->validate();

				if ($objWidget->hasErrors())
				{
					$objModule->doNotSubmit = true;
				}
				else
				{
					$objModule->arrOrderData[$field]['id'] = $objWidget->value;
					
					//Replacement for lack of address set/get in IsotopeOrder
					if($objWidget->value>0)
					{
						$arrAddress = array();
						$objAddress = $this->Database->prepare("SELECT * FROM tl_iso_addresses WHERE id=?")->limit(1)->execute($objWidget->value);
						if ($objAddress->numRows)
						{
							$arrAddress =  $objAddress->fetchAssoc();
						}
						elseif($this->Input->post('member_lookup'))
						{
							//get default user data
							$arrMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id=?")->limit(1)->execute($this->Input->post('member_lookup'))->fetchAssoc();
							$arrAddress = array_intersect_key(array_merge($arrMember, array('id'=>0, 'street_1'=>$arrMember['street'], 'subdivision'=>strtoupper($arrMember['country'] . '-' . $arrMember['state']))), array_flip($this->Isotope->Config->billing_fields_raw));
							
						}
						
						if(count($arrAddress))
						{
							$this->Isotope->Order->$field = $arrAddress;
						}
					}
					elseif($objWidget->value==-1)
					{
						//Shipping Address
						$this->Isotope->Order->$field = array_merge($this->Isotope->Order->billingAddress, array('id' => -1));
					
					}
					elseif($objWidget->value==-2)
					{
						$arrAddress = count($this->Isotope->Order->shippingAddress) ? $this->Isotope->Order->shippingAddress : $this->Isotope->Order->billingAddress;
						$this->Isotope->Order->$field = array_merge($arrAddress, array('id' => -2));
					}
				}
			}
			elseif ($objWidget->value != '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$objModule->doNotSubmit = true;
				}
			}
			
			$strBuffer .= $objWidget->parse();
		}
										
		$strBuffer .= '<div id="' . $field . '_new" class="address_new"' . (($field == 'billing_address' || $objWidget->value == 0) ? '>' : ' style="display:none">');
		$strBuffer .= '<span>' . $this->generateAddressWidgets($field, count($arrOptions), $objModule) . '</span>';
		$strBuffer .= '</div>';
		
		$strBuffer .= '</div>';

		return $strBuffer;
	}
	
	
	
	/**
	 * Generate the current step widgets.
	 * strResourceTable is used either to load a DCA or else to gather settings related to a given DCA.
	 *
	 * @todo <table...> was in a template, but I don't get why we need to define the table here?
	 */
	protected function generateAddressWidgets($strAddressType, $intOptions, &$objModule)
	{
		$arrBuffer = array();

		$this->loadLanguageFile('tl_iso_addresses');
		$this->loadDataContainer('tl_iso_addresses');

		$arrFields = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields);
		$arrDefault = $this->Isotope->Order->$strAddressType;

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
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($objModule->arrOrderData[$strAddressType][$field['value']]) ? $objModule->arrOrderData[$strAddressType][$field['value']] : $arrDefault[$field['value']])));
			}
			else
			{
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($objModule->arrOrderData[$strAddressType][$field['value']]) ? $objModule->arrOrderData[$strAddressType][$field['value']] : $arrDefault[$field['value']])));
			}
			//************************************************************************
			
			$objWidget->mandatory = false;
			$objWidget->required = $objWidget->mandatory;
			$objWidget->tableless = $this->tableless;
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
					$objModule->doNotSubmit = true;
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
					$objModule->doNotSubmit = true;
				}
			}

			$arrBuffer[] = $objWidget->parse();
		}

		// Add row_last class to the last widget
		array_pop($arrBuffer);
		$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ' row_last' . ((($i % 2) == 0) ? ' even' : ' odd');
		$arrBuffer[] = $objWidget->parse();
		
		//************************************************************************
		//Custom for multiple shipping - Add addAddress submit
		if($strAddressType == 'multipleshipping_address')
		{
			$strAddSubmit = $objModule->tableless ? '' : '<tr><td>&nbsp;</td><td>';
			$strAddSubmit .= '<input type="submit" name="addAddress" id="addAddress" class="tl_submit" value="'.$GLOBALS['TL_LANG']['MSC']['addShippingAddress'].'">';
			$strAddSubmit .= $objModule->tableless ? '' : '</td></tr>';
			$arrBuffer[] = $strAddSubmit;
		}
		//************************************************************************

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$objModule->doNotSubmit && is_array($arrAddress) && count($arrAddress))
		{									
			$arrAddress['id'] = 0;
			//************************************************************************
			//Custom for multiple shipping - Set shipping_data array instead of storing value if multipleshipping
			if($strAddressType != 'multipleshipping_address')
			{
				$objModule->arrOrderData[$strAddressType] = $arrAddress;
			}
			elseif(isset($_POST['addAddress']))
			{
				$arrCurrentData = $this->Isotope->Order->shipping_data;
				$count = count($arrCurrentData['addresses']) + 1;
				$arrCurrentData['addresses'][$count] = $arrAddress;
				$this->Isotope->Order->shipping_data = $arrCurrentData;
			}
			//************************************************************************
		}
		
		//************************************************************************
		//Custom for multiple shipping - Don't validate if addAddress is present, but add data to Order
		if($strAddressType == 'multipleshipping_address' && $this->Input->post('addAddress'))
		{
			$objModule->doNotSubmit = true;
		}
		//************************************************************************
		
		//************************************************************************
		//Custom for multiple shipping - Don't need to check for ID
		if (is_array($objModule->arrOrderData[$strAddressType]) && $objModule->arrOrderData[$strAddressType]['id'] === 0 || $strAddressType=='multipleshipping_address')
		{
			$this->Isotope->Order->$strAddressType = $objModule->arrOrderData[$strAddressType];
		}
		
		if ($this->tableless)
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
	protected function generateProductWidgets( $strField, &$objModule )
	{	
		$arrBuffer = array();
								
		//Get existing Cart products
		$arrProducts = $this->Isotope->Order->getProducts();
		
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
			'label' =>  ($this->Isotope->Order->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']),
		);
		
		$arrCurrentField = $this->Isotope->Order->shipping_data;

		if(is_array($arrCurrentField['addresses']))
		{
			foreach($arrCurrentField['addresses'] as $key=>$address)
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

				$arrData = array('id'=>$strField . '['.$objProduct->cart_id.']['.$j.']' , 'name'=>$strField . '['.$objProduct->cart_id.']['.$j.']');
			
				$objWidget = new $strClass($arrData);
				$objWidget->mandatory = $this->Input->post('save') ? true : false;
				$objWidget->required = $objWidget->mandatory;
				$objWidget->options = $arrOptions;
				$objWidget->value = $arrCurrentField['products'][$objProduct->cart_id][$j] ? $arrCurrentField['products'][$objProduct->cart_id][$j] : -1;
				$objWidget->storeValues = true;
				$objWidget->tableless = $objModule->tableless;
				$objWidget->label = $objProduct->images->generateMainImage('gallery') . '<span>'. $objProduct->name . '</span>';
				
								
				// Validate input
				if ($this->Input->post('FORM_SUBMIT') == $this->strFormId )
				{				
					$objWidget->validate();
	
					if ($objWidget->hasErrors())
					{
						$objModule->doNotSubmit = true;
					}
					else
					{	
						$arrCurrentField['products'][$objProduct->cart_id][$j] = $objWidget->value;
					}
				}
				elseif ($objWidget->value != '')
				{
					$this->Input->setPost($objWidget->name, $objWidget->value);
	
					$objValidator = clone $objWidget;
					$objValidator->validate();
	
					if ($objValidator->hasErrors())
					{
						$objModule->doNotSubmit = true;
					}
				}
				
				$arrBuffer[] = $objWidget->parse();
				
			}
		}
		
		$this->Isotope->Order->shipping_data = $arrCurrentField;
								
		if ($objModule->tableless)
		{
			return implode('', $arrBuffer);
		}
		

		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	
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
		if($this->Isotope->Order->Shipping->id==-2 && TL_MODE=='BE')
		{
			//find the existing Shipping surcharge and unset it
			foreach($arrSurcharges as $k=>$arrSurcharge)
			{
				//@todo This is pretty much our only way to identify this at this point. Perhaps another sort of ID?
				if( preg_match('/'.$GLOBALS['TL_LANG']['MSC']['shippingLabel'].'/', $arrSurcharge['label']) )
					unset($arrSurcharges[$k]);
			}
		
			$arrPackages = $this->Isotope->Order->Shipping->packages;
			
			if ($this->Isotope->Order->hasShipping && $this->Isotope->Order->Shipping->price != 0)
			{
				foreach($arrPackages as $key=>$arrPackage)
				{
					$strSurcharge = $this->Isotope->Order->Shipping->$key->surcharge;
	
					$arrSurcharges[] = array
					(
						'label'			=> ($GLOBALS['TL_LANG']['MSC']['shippingLabel'] . ' (' . $this->Isotope->Order->Shipping->$key->label . ')'),
						'price'			=> ($strSurcharge == '' ? '&nbsp;' : $strSurcharge),
						'total_price'	=> $this->Isotope->Order->Shipping->$key->price,
						'tax_class'		=> $this->Isotope->Order->Shipping->$key->tax_class,
						'before_tax'	=> ($this->Isotope->Order->Shipping->$key->tax_class ? true : false),
					);
				}
			}
		}

		return $arrSurcharges;
	}



}
