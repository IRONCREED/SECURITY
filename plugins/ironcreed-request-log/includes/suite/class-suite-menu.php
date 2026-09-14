<?php
/** IRONCREED Suite Protocol v1 menu adapter. @package Ironcreed_Request_Log */

namespace Ironcreed\Request_Log\Suite;

/** Registers inert metadata and coordinates same-theme navigation. */
final class Suite_Menu {
	/** @var callable */
	private $render;

	/** @param callable $render Plugin-owned page callback. */
	public function __construct( callable $render ) {
		$this->render = $render;
	}

	/** Register protocol hooks. */
	public function register(): void {
		add_filter( 'ironcreed_suite_registry_v1', array( $this, 'descriptor' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
	}

	/** Append this plugin's inert descriptor. */
	public function descriptor( mixed $registry ): array {
		$registry   = is_array( $registry ) ? $registry : array();
		$registry[] = array(
			'vendor'     => 'ironcreed',
			'product'    => 'request-log',
			'theme'      => 'security',
			'protocol'   => 1,
			'page_slug'  => 'ironcreed-request-log',
			'menu_label' => __( 'Request Log', 'ironcreed-request-log' ),
			'capability' => 'view_ironcreed_request_log',
			'position'   => 20,
			'render'     => $this->render,
		);

		return $registry;
	}

	/** Register standalone or grouped navigation. */
	public function menu(): void {
		$valid = array_filter( apply_filters( 'ironcreed_suite_registry_v1', array() ), array( self::class, 'valid_descriptor' ) );
		usort(
			$valid,
			static function ( array $left, array $right ): int {
				return strcmp( $left['vendor'] . '/' . $left['product'], $right['vendor'] . '/' . $right['product'] );
			}
		);

		if ( count( $valid ) < 2 ) {
			add_management_page( __( 'Request Log', 'ironcreed-request-log' ), __( 'Request Log', 'ironcreed-request-log' ), 'view_ironcreed_request_log', 'ironcreed-request-log', $this->render );
			return;
		}

		$coordinator = $valid[0]['vendor'] . '/' . $valid[0]['product'];
		if ( 'ironcreed/request-log' === $coordinator ) {
			add_menu_page( __( 'IRONCREED — Security', 'ironcreed-request-log' ), __( 'IRONCREED — Security', 'ironcreed-request-log' ), 'view_ironcreed_request_log', 'ironcreed-security', $this->render, 'dashicons-shield' );
		}
		add_submenu_page( 'ironcreed-security', __( 'Request Log', 'ironcreed-request-log' ), __( 'Request Log', 'ironcreed-request-log' ), 'view_ironcreed_request_log', 'ironcreed-request-log', $this->render );
	}

	/** Validate descriptor shape without invoking its render callback. */
	private static function valid_descriptor( mixed $descriptor ): bool {
		return is_array( $descriptor )
			&& 'ironcreed' === ( $descriptor['vendor'] ?? null )
			&& 'security' === ( $descriptor['theme'] ?? null )
			&& 1 === ( $descriptor['protocol'] ?? null )
			&& is_string( $descriptor['product'] ?? null );
	}
}
