<?php
/** Protected behavioral successor; earlier accepted files remain immutable. */
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
Iron_Warden_Test_Driver::run_historical_suite( 'storage-v5' );
