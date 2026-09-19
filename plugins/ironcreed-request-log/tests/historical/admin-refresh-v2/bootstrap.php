<?php
/** Minimal WordPress stubs for isolated domain and adapter tests. */

defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'ABSPATH' ) || define( 'ABSPATH', '/synthetic-wordpress/' );

function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_check_invalid_utf8( $text ) { return mb_check_encoding( $text, 'UTF-8' ) ? $text : ''; }
function sanitize_text_field( $text ) { return trim( strip_tags( preg_replace( '/[\x00-\x1F\x7F]|%[a-f0-9]{2}/i', '', $text ) ) ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) ); }
function wp_delete_file( $file ) { return unlink( $file ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_option( $name, $default = false ) { return $GLOBALS['test_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { if ( ( $GLOBALS['test_update_fail'] ?? '' ) === $name ) return false; $GLOBALS['test_options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['test_options'][ $name ] ); return true; }
function wp_cache_delete( $name, $group = '' ) { return true; }
function dbDelta( $sql ) { $GLOBALS['test_dbdelta'] = $sql; return array( 'schema' => 'updated' ); }
function apply_filters( $hook, $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_doing_ajax() { return $GLOBALS['test_ajax'] ?? false; }
function wp_doing_cron() { return $GLOBALS['test_cron'] ?? false; }
function is_admin() { return $GLOBALS['test_admin'] ?? false; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook( $file, $callback ) { $GLOBALS['test_activation_callback'] = $callback; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['test_actions'][ $hook ][] = $callback; }
function current_user_can( $capability ) { return $GLOBALS['test_capabilities'][$capability] ?? true; }
function __( $message, $domain = null ) { return $message; }
function esc_html__( $message, $domain = null ) { return $message; }
function esc_html( $message ) { return $message; }
function wp_die( $message, $title = '', $arguments = array() ) { throw new RuntimeException( $message ); }
if ( ! class_exists( 'wpdb' ) ) { class wpdb { public string $prefix = 'wp_'; public string $last_error = ''; public function get_charset_collate(){return '';} } }

require_once dirname( __DIR__, 3 ) . '/includes/domain/class-uri-normalizer.php';
require_once dirname( __DIR__, 3 ) . '/includes/domain/class-event.php';
require_once dirname( __DIR__, 3 ) . '/includes/application/interface-http-client.php';
require_once dirname( __DIR__, 3 ) . '/includes/application/interface-provider.php';
require_once dirname( __DIR__, 3 ) . '/includes/infrastructure/class-hosting-ukraine-provider.php';
require_once dirname( __DIR__, 3 ) . '/includes/infrastructure/class-event-repository.php';
require_once dirname( __DIR__, 3 ) . '/includes/admin/class-admin-controller.php';
require_once dirname( __DIR__, 3 ) . '/includes/class-plugin.php';
require_once dirname( __DIR__, 3 ) . '/includes/infrastructure/class-lifecycle.php';

function absint( $value ) { return abs( (int) $value ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['test_schedule'][$hook] ?? false; }
function wp_schedule_single_event( $when, $hook ) { if ( $GLOBALS['test_schedule_fail'] ?? false ) return false; $GLOBALS['test_schedule'][$hook] = $when; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['test_schedule'][$hook] ); return 1; }
function esc_attr__( $value, $domain = '' ) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr( $value ) { return htmlspecialchars((string)$value, ENT_QUOTES); }
function esc_url( $value ) { return htmlspecialchars((string)$value, ENT_QUOTES); }
function esc_html_e( $value, $domain = '' ) { echo htmlspecialchars($value, ENT_QUOTES); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function home_url() { return 'https://child.example.test'; }
function get_home_url( $id ) { return 'https://main.example.test'; }
function get_main_site_id() { return 1; }
function is_multisite() { return $GLOBALS['test_multisite'] ?? false; }
function add_query_arg( $key, $value = null, $url = null ) { if(is_array($key)){ $url=$value;$args=$key; }else{$args=[$key=>$value];} return $url . (str_contains($url,'?')?'&':'?') . http_build_query($args); }
function checked( $left, $right = true, $echo = true ) { $s=(string)$left === (string)$right ? 'checked' : '';if($echo)echo $s;return $s; }
function selected( $left, $right = true, $echo = true ) { $s=(string)$left === (string)$right ? 'selected' : '';if($echo)echo $s;return $s; }
function disabled( $left, $right = true, $echo = true ) { $s=(string)$left === (string)$right ? 'disabled' : '';if($echo)echo $s;return $s; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="synthetic-nonce">'; }
function submit_button( $label, $class = '', $name = 'submit', $wrap = false ) { echo '<button class="button '.esc_attr($class).'" type="submit">'.esc_attr($label).'</button>'; }
function wp_date( $format, $timestamp ) { return gmdate($format,$timestamp); }
function check_admin_referer( $action ) { $GLOBALS['test_nonce_checks'][]=$action;if(($GLOBALS['test_nonce_valid']??false)!==true)throw new RuntimeException('Invalid nonce'); }
function get_current_user_id() { return 42; }
function get_transient( $key ) { return $GLOBALS['test_transients'][$key]??false; }
function set_transient( $key, $value, $expiration ) { $GLOBALS['test_transients'][$key]=$value;return true; }
function delete_transient( $key ) { unset($GLOBALS['test_transients'][$key]);return true; }
function wp_safe_redirect( $url ) { throw new Test_Redirect($url); }
class Test_Redirect extends Exception {}
defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS',60);
require_once dirname(__DIR__, 3).'/includes/infrastructure/class-provider-rate-limit.php';
require_once dirname(__DIR__, 3).'/includes/infrastructure/class-provider-import.php';
require_once dirname(__DIR__, 3).'/includes/infrastructure/class-hosting-ukraine-discovery.php';
require_once dirname(__DIR__, 3).'/includes/admin/class-settings-view.php';
require_once dirname(__DIR__, 3).'/includes/admin/class-help-view.php';
