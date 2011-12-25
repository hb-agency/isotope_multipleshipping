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
 * Table tl_iso_payment_modules
 */
$GLOBALS['TL_DCA']['tl_iso_payment_modules']['config']['onload_callback'][] = array('tl_iso_payment_modules_multipleshipping', 'loadShippingModules');

/**
 * tl_iso_payment_modules class.
 *
 * @extends Backend
 */
class tl_iso_payment_modules_multipleshipping extends Backend
{		
		
	/**
	 * Load shipping modules into the DCA. options_callback would not work due to numeric array keys.
	 *
	 * @access public
	 * @param object $dc
	 * @return void
	 */
	public function loadShippingModules($dc)
	{
		$arrModules = array(
			-1	=>	$GLOBALS['TL_LANG']['tl_iso_payment_modules']['no_shipping'],
			-2	=>	$GLOBALS['TL_LANG']['tl_iso_payment_modules']['multiple_shipping']
		);

		$objShippings = $this->Database->execute("SELECT * FROM tl_iso_shipping_modules ORDER BY name");

		while( $objShippings->next() )
		{
			$arrModules[$objShippings->id] = $objShippings->name;
		}

		$GLOBALS['TL_DCA']['tl_iso_payment_modules']['fields']['shipping_modules']['options'] = array_keys($arrModules);
		$GLOBALS['TL_DCA']['tl_iso_payment_modules']['fields']['shipping_modules']['reference'] = $arrModules;
	}
	
}