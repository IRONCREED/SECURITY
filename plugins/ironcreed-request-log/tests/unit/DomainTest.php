<?php
/** Domain regression tests. */

use Ironcreed\Request_Log\Domain\Event;
use Ironcreed\Request_Log\Domain\URI_Normalizer;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase {
	public function test_nested_repeated_and_encoded_sensitive_keys_are_redacted(): void {
		$value = URI_Normalizer::normalize( '/login?token=one&TOKEN%5B%5D=two&%74oken%5Bname%5D=three&safe=yes' );
		self::assertSame( '/login', $value['path'] );
		self::assertSame( 'token=%5Bredacted%5D&TOKEN%5B%5D=%5Bredacted%5D&token%5Bname%5D=%5Bredacted%5D&safe=yes', $value['query'] );
	}

	public function test_encoded_separator_and_complete_percent_escape_are_preserved(): void {
		self::assertSame( '/a%2Fb', URI_Normalizer::normalize( '/a%2Fb' )['path'] );
		$query = URI_Normalizer::redact_query( 'safe=' . str_repeat( 'a', 600 ) . '%2F' );
		self::assertDoesNotMatchRegularExpression( '/%(?:[0-9A-Fa-f]?)$/', $query );
	}

	public function test_event_uses_closed_categories(): void {
		$event = Event::validate( array( 'method' => 'HACK', 'status' => 999, 'route_kind' => 'other', 'source' => 'invalid' ) );
		self::assertSame( 'UNKNOWN', $event['method'] );
		self::assertSame( 0, $event['status'] );
		self::assertSame( 'unknown', $event['route_kind'] );
		self::assertSame( 'wordpress-runtime', $event['source'] );
	}
}
