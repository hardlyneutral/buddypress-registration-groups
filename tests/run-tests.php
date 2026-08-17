<?php
/**
 * Dependency-free test runner.
 *
 * Runs every tests/test-*.php file in its own PHP process (the render
 * function has a render-once-per-request guard, so render tests need
 * process isolation) and reports an overall pass/fail.
 *
 * Usage: php tests/run-tests.php
 */

$test_files = glob( __DIR__ . '/test-*.php' );
sort( $test_files );

if ( empty( $test_files ) ) {
	fwrite( STDERR, "No test files found.\n" );
	exit( 1 );
}

$failed_files = 0;

foreach ( $test_files as $test_file ) {
	echo '== ' . basename( $test_file ) . " ==\n";
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test_file ), $exit_code );
	if ( 0 !== $exit_code ) {
		$failed_files++;
	}
}

if ( $failed_files > 0 ) {
	echo "\nFAILED: {$failed_files} test file(s) reported failures.\n";
	exit( 1 );
}

echo "\nAll test files passed.\n";
exit( 0 );
