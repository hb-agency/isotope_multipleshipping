-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

--
-- Table `tl_iso_packages`
--

CREATE TABLE `tl_iso_packages` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `pid` int(10) unsigned NOT NULL default '0',
  `tstamp` int(10) unsigned NOT NULL default '0',
  `shipping_id` int(10) unsigned NOT NULL default '0',
  `order_address_id` int(10) unsigned NOT NULL default '0',
  `order_address` blob NULL,
  `config_id` int(10) unsigned NOT NULL default '0',
  `ups_tracking_number` varchar(255) NOT NULL default '',
  `ups_label` blob NULL,
  `status` varchar(255) NOT NULL default '',
  `archive` int(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `pid` (`pid`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `tl_iso_orders` (
  `shipping_multiple` int(1) unsigned NOT NULL default '0',
  `shipping_status` varchar(255) NOT NULL default '',
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `tl_iso_order_items` (
  `package_id` int(10) unsigned NOT NULL default '0',
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `tl_iso_cart_items` (
  `package_id` int(10) unsigned NOT NULL default '0',
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
