<?php
use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Discovery;
use Ironcreed\Request_Log\Application\HTTP_Client;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase {
	public function test_lookup_uses_services_endpoint_and_matching_service_id(): void {
		$client=new Discovery_Test_HTTP('{"result":true,"response":[{"id":"111","host":"other.example.test","account_id":"9"},{"id":"12345","host":"example.test","virtual_domain_id":"777","account_id":"999"}]}');
		self::assertSame(12345,(new Hosting_Ukraine_Discovery($client))->find('https://Example.test/path','test-token-not-a-secret'));
		self::assertSame('https://adm.tools/action/get_services/',$client->url);
		self::assertSame(['type'=>'host'],$client->arguments['body']);
		self::assertArrayNotHasKey('name',$client->arguments['body']);
		self::assertSame(0,$client->arguments['redirection']);
		self::assertSame(65537,$client->arguments['limit_response_size']);
		self::assertFileDoesNotExist($client->file);
	}

	public function test_unique_www_alias_is_accepted_but_exact_match_wins(): void {
		$client=new Discovery_Test_HTTP('{"result":true,"response":[{"id":"12345","host":"www.example.test","account_id":"999"}]}');
		self::assertSame(12345,(new Hosting_Ukraine_Discovery($client))->find('example.test','test-token-not-a-secret'));
		$client=new Discovery_Test_HTTP('{"result":true,"response":[{"id":"12345","host":"www.example.test"},{"id":"23456","host":"example.test"}]}');
		self::assertSame(23456,(new Hosting_Ukraine_Discovery($client))->find('example.test','test-token-not-a-secret'));
	}

	public function test_ambiguous_alias_and_unusable_responses_fail_without_leaking_or_storing_metadata(): void {
		foreach([
			['{"result":true,"response":[{"id":"1","host":"www.example.test"},{"id":"2","host":"www.example.test"}]}',200],
			['{"result":true,"response":[{"id":"1","host":"other.example.test","account_id":12345}]}',200],
			['{"result":false,"response":[{"id":"1","host":"example.test"}],"message":"secret-token"}',200],
			['{"result":true,"response":{"id":"1","host":"example.test"}}',200],
			['{"result":true,"response":[{"id":[1,2],"host":"example.test"}]}',200],
			['{"result":true,"response":[{"id":"-1","host":"example.test"}]}',200],
			['{"result":true,"response":[{"id":"999999999999999999999999","host":"example.test"}]}',200],
			['secret-token',200],['{}',302],['{}',403],[str_repeat('x',65537),200],
		] as [$body,$code]){
			$client=new Discovery_Test_HTTP($body,$code);
			try{(new Hosting_Ukraine_Discovery($client))->find('example.test','test-token-not-a-secret');self::fail('Unusable response accepted');}
			catch(RuntimeException $e){self::assertStringNotContainsString('secret-token',$e->getMessage());}
			self::assertFileDoesNotExist($client->file);
		}
	}

	public function test_invalid_domains_and_token_make_no_request(): void {
		foreach(['https://user:pass@example.test','file:///etc/passwd','https://127.0.0.1','localhost','https://example.test?token=x','https://example.test:443','-bad.example.test'] as $domain){
			$client=new Discovery_Test_HTTP('{}');
			try{(new Hosting_Ukraine_Discovery($client))->find($domain,'test-token-not-a-secret');self::fail('Invalid input accepted');}catch(RuntimeException $e){}
			self::assertSame('',$client->url);
		}
		$client=new Discovery_Test_HTTP('{}');
		try{(new Hosting_Ukraine_Discovery($client))->find('example.test','bad token');self::fail('Invalid token accepted');}catch(RuntimeException $e){}
		self::assertSame('',$client->url);
	}
}

final class Discovery_Test_HTTP implements HTTP_Client {
	public string $url='';
	public string $file='';
	public array $arguments=[];
	private string $body;
	private int $code;
	public function __construct(string $body,int $code=200){$this->body=$body;$this->code=$code;}
	public function download(string $url,array $arguments):array{
		$this->url=$url;$this->arguments=$arguments;$this->file=tempnam(sys_get_temp_dir(),'icrl-lookup-');
		file_put_contents($this->file,$this->body);
		return ['code'=>$this->code,'headers'=>['content-type'=>'application/json'],'size'=>strlen($this->body),'file'=>$this->file];
	}
}
