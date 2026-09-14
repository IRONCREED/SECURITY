<?php
/** Active provider limit behavioral successor. */
exit( passthru( escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/vendor/bin/phpunit' ) . ' -c ' . escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/tests/historical/provider-v3/phpunit.xml' ) ) );
