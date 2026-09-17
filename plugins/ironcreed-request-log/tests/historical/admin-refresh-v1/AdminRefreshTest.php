<?php
use Ironcreed\Request_Log\Admin\Admin_Controller;
use Ironcreed\Request_Log\Admin\Settings_View;
use Ironcreed\Request_Log\Admin\Help_View;
use Ironcreed\Request_Log\Infrastructure\Event_Repository;
use PHPUnit\Framework\TestCase;

final class AdminRefreshTest extends TestCase {
	protected function setUp():void{
		$GLOBALS['test_options']=[];$GLOBALS['test_capabilities']=[];$GLOBALS['test_nonce_checks']=[];
		$GLOBALS['test_multisite']=false;$GLOBALS['test_schedule']=[];$GLOBALS['test_nonce_valid']=false;
		$_GET=[];$_POST=[];
	}
	protected function tearDown():void{$GLOBALS['test_capabilities']=[];$GLOBALS['test_multisite']=false;$_GET=[];$_POST=[];}
	private function controller():Admin_Controller{return new Admin_Controller(new Event_Repository(new wpdb()));}
	public function test_settings_never_echo_token_and_suggest_network_root_domain():void{
		$GLOBALS['test_multisite']=true;
		ob_start();Settings_View::render(['host_id'=>12345,'token'=>'NEVER-ECHO-THIS']);$html=ob_get_clean();
		self::assertStringNotContainsString('NEVER-ECHO-THIS',$html);
		self::assertStringContainsString('main.example.test',$html);
		self::assertStringNotContainsString('child.example.test',$html);
		self::assertStringContainsString('name="token" value=""',$html);
		self::assertStringContainsString('ironcreed_request_log_test',$html);
		self::assertStringContainsString('ironcreed_request_log_connect',$html);
	}
	public function test_help_opens_requested_section_without_javascript():void{
		$_GET['section']='host-id';ob_start();Help_View::render();$html=ob_get_clean();
		self::assertStringContainsString('id="icrl-help-host-id" open',$html);
		self::assertStringContainsString('account_id',$html);
		self::assertStringContainsString('https://adm.tools/user/api/',$html);
	}
	public function test_view_permission_does_not_grant_settings_access():void{
		$GLOBALS['test_capabilities']['manage_ironcreed_request_log']=false;$_GET['tab']='settings';
		$this->expectException(RuntimeException::class);$this->controller()->render_log();
	}
	public function test_each_privileged_action_rejects_invalid_nonce_before_effects():void{
		$controller=$this->controller();
		foreach(['settings','connect','test','fetch','schedule','clear','disconnect'] as $action){
			try{$controller->{'handle_'.$action}();self::fail('Nonce was not enforced');}catch(RuntimeException $e){self::assertSame('Invalid nonce',$e->getMessage());}
		}
		self::assertCount(7,$GLOBALS['test_nonce_checks']);self::assertSame([],$GLOBALS['test_options']);
	}
	public function test_capability_is_checked_before_nonce():void{
		$GLOBALS['test_capabilities']['manage_ironcreed_request_log']=false;
		try{$this->controller()->handle_connect();self::fail('Capability was ignored');}catch(RuntimeException $e){}
		self::assertSame([],$GLOBALS['test_nonce_checks']);self::assertSame([],$GLOBALS['test_options']);
	}
	public function test_manual_save_preserves_hidden_token_and_disables_old_schedule():void{
		$GLOBALS['test_options']['ironcreed_request_log_credentials']=['host_id'=>12345,'token'=>'test-token-not-a-secret'];
		$GLOBALS['test_options']['ironcreed_request_log_import_interval']=300;
		$GLOBALS['test_schedule']['ironcreed_request_log_import']=time()+300;
		$GLOBALS['test_nonce_valid']=true;
		$_POST=['connection_mode'=>'manual','host_id'=>'23456','token'=>''];
		try{$this->controller()->handle_connect();self::fail('Expected redirect');}catch(Test_Redirect $e){}
		self::assertSame(['host_id'=>23456,'token'=>'test-token-not-a-secret'],get_option('ironcreed_request_log_credentials'));
		self::assertSame(0,get_option('ironcreed_request_log_import_interval'));self::assertSame([],$GLOBALS['test_schedule']);
	}
	public function test_invalid_manual_id_does_not_overwrite_saved_connection():void{
		$old=['host_id'=>12345,'token'=>'test-token-not-a-secret'];
		$GLOBALS['test_options']['ironcreed_request_log_credentials']=$old;$GLOBALS['test_nonce_valid']=true;
		$_POST=['connection_mode'=>'manual','host_id'=>'-23','token'=>''];
		try{$this->controller()->handle_connect();self::fail('Expected redirect');}catch(Test_Redirect $e){}
		self::assertSame($old,get_option('ironcreed_request_log_credentials'));
	}
}
