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
 * @copyright  Winans Creative 2009, Intelligent Spark 2010, iserv.ch GmbH 2010
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

/**
 * Override default iso_orders shipping callback
 */
$GLOBALS['BE_MOD']['isotope']['iso_orders']['shipping']	 = array('MultipleShippingBackend', 'shippingInterface');


/**
 * Override default checkout module until core changes are made
 */
$GLOBALS['FE_MOD']['isotope']['iso_checkout'] = 'ModuleIsotopeCheckoutExtended';


/**
 * Replacements for existing checkout steps
 */
//Replace existing callback for shipping address until we can add to the core
$GLOBALS['ISO_CHECKOUT_STEPS']['address'][1] = array('MultipleShippingFrontend', 'getShippingAddressInterface');

//Need to find the existing address step and insert multiple shipping directly after it
//NOTE: will need to update this when we get checkout step sorting in module config
$count = 1;
foreach($GLOBALS['ISO_CHECKOUT_STEPS'] as $step=>$callback)
{
	if($step=='address')
		break;
	$count++;
}

//Add in the multiple shipping step
array_insert($GLOBALS['ISO_CHECKOUT_STEPS'], $count, array
(
	'multipleshipping' => array(
		array('MultipleShippingFrontend', 'getMultipleShippingAddressInterface')
	)
));


/**
 * Replacements for existing backend fulfillment steps
 */
//Replace existing callback for shipping address until we can add to the core
$GLOBALS['ISO_ORDER_STEPS']['address'][0] = array('MultipleShippingBackend', 'getAddressInterface');

//Need to find the existing address step and insert multiple shipping directly after it
//NOTE: will need to update this when we get checkout step sorting in module config
$count = 1;

foreach($GLOBALS['ISO_ORDER_STEPS'] as $step=>$callback)
{
	if($step=='address')
		break;
	$count++;
}

//Add in the multiple shipping step
array_insert($GLOBALS['ISO_ORDER_STEPS'], $count, array
(
	'multipleshipping' => array(
		array('MultipleShippingBackend', 'getMultipleShippingAddressInterface')
	)
));


/**
 * Hook to replace existing shipping surcharge Hook
 */
$GLOBALS['ISO_HOOKS']['checkoutSurcharge'][] = array('MultipleShippingFrontend', 'getShippingSurcharge');
$GLOBALS['ISO_HOOKS']['checkoutSurcharge'][] = array('MultipleShippingBackend', 'getShippingSurcharge');
$GLOBALS['ISO_HOOKS']['preCheckout'][] = array('MultipleShippingFrontend', 'preCheckoutMultipleShipping');

?>