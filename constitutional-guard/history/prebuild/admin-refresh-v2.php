<?php
/** Protected consent, service discovery, administration and URI regressions. */
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
Iron_Warden_Test_Driver::run_historical_suite( 'admin-refresh-v2' );
