<?php
/** Active transactional storage behavioral successor. */
exit( passthru( escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/vendor/bin/phpunit' ) . ' -c ' . escapeshellarg( __DIR__ . '/../../../plugins/ironcreed-request-log/tests/historical/storage-v3/phpunit.xml' ) ) );
