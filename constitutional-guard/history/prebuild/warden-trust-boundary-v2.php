<?php
/** Active WARDEN trust-boundary behavioral successor. */
exit( passthru( escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/vendor/bin/phpunit' ) . ' -c ' . escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/tests/historical/warden-v3/phpunit.xml' ) ) );
