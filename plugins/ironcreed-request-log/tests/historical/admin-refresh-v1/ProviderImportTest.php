<?php
use Ironcreed\Request_Log\Infrastructure\Provider_Import;
use Ironcreed\Request_Log\Infrastructure\Provider_Rate_Limit;
use PHPUnit\Framework\TestCase;

final class ProviderImportTest extends TestCase {
	private Import_Test_Database $db;
	private int $calls;

	protected function setUp(): void {
		$GLOBALS['test_options'] = [
			'ironcreed_request_log_credentials' => ['host_id'=>12345,'token'=>'test-token-not-a-secret'],
			'ironcreed_request_log_storage_ready' => '1',
		];
		$GLOBALS['test_update_fail']='';
		$GLOBALS['test_schedule']=[];
		$GLOBALS['test_schedule_fail']=false;
		$this->db=new Import_Test_Database();
		$this->calls=0;
	}
	private function importer(?callable $operation=null): Provider_Import {
		return new Provider_Import($this->db,$operation??function(){++$this->calls;return 17;});
	}
	public function test_disabled_schedule_performs_no_http_or_lock(): void {
		$this->importer()->scheduled();
		self::assertSame(0,$this->calls);
		self::assertSame([],$this->db->queries);
		self::assertSame([],$GLOBALS['test_schedule']);
	}
	public function test_consent_schedules_without_fetch_and_repeated_save_has_one_event(): void {
		Provider_Import::configure(300);Provider_Import::configure(300);
		self::assertSame(0,$this->calls);
		self::assertCount(1,$GLOBALS['test_schedule']);
		self::assertGreaterThanOrEqual(time()+299,wp_next_scheduled(Provider_Import::HOOK));
		Provider_Import::configure(0);
		self::assertFalse(wp_next_scheduled(Provider_Import::HOOK));
	}
	public function test_scheduled_success_tracks_new_count_and_reschedules_without_browser(): void {
		Provider_Import::configure(60);
		unset($GLOBALS['test_schedule'][Provider_Import::HOOK]); // WP-Cron dequeues a single event before its callback.
		$this->importer()->scheduled();
		self::assertSame(1,$this->calls);
		self::assertSame(17,get_option('ironcreed_request_log_import_status')['count']);
		self::assertSame('success',get_option('ironcreed_request_log_import_status')['state']);
		self::assertSame(1,$this->db->releases);
		self::assertNotFalse(wp_next_scheduled(Provider_Import::HOOK));
	}
	public function test_errors_back_off_and_never_store_exception_secrets(): void {
		Provider_Import::configure(60);unset($GLOBALS['test_schedule'][Provider_Import::HOOK]);
		$this->importer(function(){throw new RuntimeException('private token and body');})->scheduled();
		$status=get_option('ironcreed_request_log_import_status');
		self::assertSame('failed',$status['state']);
		self::assertStringNotContainsString('private',json_encode($status));
		self::assertGreaterThanOrEqual(time()+119,wp_next_scheduled(Provider_Import::HOOK));
		self::assertSame(1,$this->db->releases);
	}
	public function test_rate_limit_respects_retry_after_for_automatic_and_manual_attempts(): void {
		Provider_Import::configure(60);unset($GLOBALS['test_schedule'][Provider_Import::HOOK]);
		$this->importer(function(){throw new Provider_Rate_Limit(900);})->scheduled();
		self::assertGreaterThanOrEqual(time()+899,wp_next_scheduled(Provider_Import::HOOK));
		try {$this->importer()->run();self::fail('Rate limit ignored');}catch(RuntimeException $e){}
		self::assertSame(0,$this->calls);
	}
	public function test_overlap_refuses_before_download_and_never_releases_another_owner_lock(): void {
		$this->db->held=false;
		try{$this->importer()->run();self::fail('Busy lock ignored');}catch(RuntimeException $e){}
		self::assertSame(0,$this->calls);self::assertSame(0,$this->db->releases);
	}
	public function test_disconnect_during_request_does_not_rearm_or_resurrect_status(): void {
		Provider_Import::configure(300);unset($GLOBALS['test_schedule'][Provider_Import::HOOK]);
		$this->importer(function(){Provider_Import::configure(0);delete_option('ironcreed_request_log_credentials');delete_option('ironcreed_request_log_import_status');return 1;})->scheduled();
		self::assertFalse(wp_next_scheduled(Provider_Import::HOOK));
		self::assertFalse(get_option('ironcreed_request_log_import_status'));
		self::assertSame(1,$this->db->releases);
	}
	public function test_unready_storage_does_not_download(): void {
		update_option('ironcreed_request_log_storage_ready','0');
		try{$this->importer()->run();self::fail('Unready storage used');}catch(RuntimeException $e){}
		self::assertSame(0,$this->calls);self::assertSame([],$this->db->queries);
		self::assertSame('failed',get_option('ironcreed_request_log_import_status')['state']);
	}
	public function test_schedule_failure_disables_and_invalid_intervals_are_rejected(): void {
		$GLOBALS['test_schedule_fail']=true;
		try{Provider_Import::configure(60);self::fail('Scheduling failure ignored');}catch(RuntimeException $e){}
		self::assertSame(0,get_option('ironcreed_request_log_import_interval'));
		foreach([-1,1,20,86400] as $seconds){try{Provider_Import::configure($seconds);self::fail('Invalid interval accepted');}catch(RuntimeException $e){}}
		self::assertSame([],$GLOBALS['test_schedule']);
	}
	public function test_consent_storage_failure_never_schedules(): void {
		$GLOBALS['test_update_fail']='ironcreed_request_log_import_interval';
		try{Provider_Import::configure(300);self::fail('Consent storage failure ignored');}catch(RuntimeException $e){}
		self::assertSame([],$GLOBALS['test_schedule']);self::assertSame(0,$this->calls);
	}
	public function test_release_failure_after_success_is_not_reported_as_success(): void {
		$this->db->release_result=0;
		try{$this->importer()->run();self::fail('Release failure ignored');}catch(RuntimeException $e){}
		self::assertSame(1,$this->db->releases);
		self::assertSame('failed',get_option('ironcreed_request_log_import_status')['state']);
	}
	public function test_deactivation_cancels_both_hooks_and_retains_credentials(): void {
		Provider_Import::configure(60);$GLOBALS['test_schedule']['ironcreed_request_log_cleanup']=time()+3600;
		$before=get_option('ironcreed_request_log_credentials');
		\Ironcreed\Request_Log\Lifecycle::deactivate_site();
		self::assertSame([],$GLOBALS['test_schedule']);
		self::assertSame($before,get_option('ironcreed_request_log_credentials'));
		Provider_Import::restore_schedule();Provider_Import::restore_schedule();
		self::assertCount(1,$GLOBALS['test_schedule']);
	}
}

final class Import_Test_Database extends wpdb {
	public bool $held=true;
	public int $release_result=1;
	public int $releases=0;
	public array $queries=[];
	public function prepare($sql,...$values){return [$sql,$values];}
	public function get_var($query){
		$this->queries[]=$query;
		if(str_contains($query[0],'RELEASE_LOCK')){++$this->releases;return $this->release_result;}
		return $this->held?'1':'0';
	}
}
