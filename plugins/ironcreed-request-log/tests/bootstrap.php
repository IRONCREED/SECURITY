<?php
/** Minimal WordPress stubs for isolated domain and adapter tests. */

defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'ABSPATH' ) || define( 'ABSPATH', '/synthetic-wordpress/' );

function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_check_invalid_utf8( $text ) { return mb_check_encoding( $text, 'UTF-8' ) ? $text : ''; }
function sanitize_text_field( $text ) { return trim( strip_tags( preg_replace( '/[\x00-\x1F\x7F]/', '', $text ) ) ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) ); }
function wp_delete_file( $file ) { return unlink( $file ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_option( $name, $default = false ) { return $GLOBALS['test_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { if ( ( $GLOBALS['test_update_fail'] ?? '' ) === $name ) return false; $GLOBALS['test_options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['test_options'][ $name ] ); return true; }
function dbDelta( $sql ) { $GLOBALS['test_dbdelta'] = $sql; return array( 'schema' => 'updated' ); }
function apply_filters( $hook, $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_doing_ajax() { return $GLOBALS['test_ajax'] ?? false; }
function wp_doing_cron() { return $GLOBALS['test_cron'] ?? false; }
function is_admin() { return $GLOBALS['test_admin'] ?? false; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook( $file, $callback ) { $GLOBALS['test_activation_callback'] = $callback; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['test_actions'][ $hook ][] = $callback; }
function current_user_can( $capability ) { return true; }
function __( $message, $domain = null ) { return $message; }
function esc_html__( $message, $domain = null ) { return $message; }
function esc_html( $message ) { return $message; }
function wp_die( $message, $title = '', $arguments = array() ) { throw new RuntimeException( $message ); }
if ( ! class_exists( 'wpdb' ) ) { class wpdb { public string $prefix = 'wp_'; public string $last_error = ''; public function get_charset_collate(){return '';} } }

require_once dirname( __DIR__ ) . '/includes/domain/class-uri-normalizer.php';
require_once dirname( __DIR__ ) . '/includes/domain/class-event.php';
require_once dirname( __DIR__ ) . '/includes/application/interface-http-client.php';
require_once dirname( __DIR__ ) . '/includes/application/interface-provider.php';
require_once dirname( __DIR__ ) . '/includes/infrastructure/class-hosting-ukraine-provider.php';
require_once dirname( __DIR__ ) . '/includes/infrastructure/class-event-repository.php';
require_once dirname( __DIR__ ) . '/includes/admin/class-admin-controller.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
require_once dirname( __DIR__ ) . '/includes/infrastructure/class-lifecycle.php';
