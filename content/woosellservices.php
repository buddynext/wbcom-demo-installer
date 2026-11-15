<?php
/**
 * Woo Sell Services: "Try the demo" page text (Core\Guide). The provider builds no sample content.
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'guide' => array(
		'name'         => 'Woo Sell Services',
		'tab'          => 'Services',
		'member_label' => 'As a customer',
		'owner_points' => array( 'Services sold as WooCommerce products', 'Every order with its conversation', 'Delivery, revisions and approval rules' ),
		'roles'        => array(
			'customer' => array( 'Customer', array( 'Service orders already placed', 'Messages with the seller on each order', 'Deliveries to review and approve' ) ),
		),
		'member'       => array(
			'browse' => array( 'store', 'Browse services', 'Packages with prices, delivery time and extras.', 'Open the services' ),
			'order'  => array( 'card', 'Order a service', 'Add a service to the cart and pay with the test gateway.', 'Open a service' ),
			'track'  => array( 'message', 'Follow your order', 'Talk to the seller, get the delivery, then approve it.', 'Open my service orders' ),
		),
		'owner'        => array(
			'orders'        => array( 'list', 'Service orders', 'Every order, its status and its buyer.', 'Open orders' ),
			'conversations' => array( 'message', 'Order conversations', 'Read and moderate messages on any order.', 'Open conversations' ),
			'services'      => array( 'package', 'Your services', 'Services are WooCommerce products with service options.', 'Open services' ),
			'settings'      => array( 'settings', 'Settings', 'Requirements, deliveries, revisions and emails.', 'Open settings' ),
		),
		'highlights'   => array(
			array( 'store', 'Sell services in WooCommerce', 'Your cart, checkout and payment gateways, unchanged.' ),
			array( 'message', 'Order conversations', 'Buyer and seller talk and share files on each order.' ),
			array( 'check', 'Deliver and approve', 'Deliveries, revisions and approval in one place.' ),
			array( 'shield', 'Moderation', 'The owner can read and moderate every conversation.' ),
		),
	),
);
