<?php
/**
 * Guards against manual line wrapping in the description prose. The
 * WordPress.org readme parser renders those source newlines as hard breaks.
 */

$assertions = 0;
$failures   = 0;

function bprg_readme_assert_true( $condition, $label ) {
	global $assertions, $failures;
	$assertions++;
	if ( $condition ) {
		echo "  ok - {$label}\n";
	} else {
		$failures++;
		echo "  FAIL - {$label}\n";
	}
}

function bprg_readme_description_wrapped_paragraphs( $path, $start_pattern, $end_pattern ) {
	$content = file_get_contents( $path );
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

	if ( ! preg_match( $start_pattern, $content, $start_match, PREG_OFFSET_CAPTURE ) ) {
		return array( 'description start marker not found' );
	}

	$start = $start_match[0][1] + strlen( $start_match[0][0] );
	$tail  = substr( $content, $start );
	if ( ! preg_match( $end_pattern, $tail, $end_match, PREG_OFFSET_CAPTURE ) ) {
		return array( 'description end marker not found' );
	}

	$description = trim( substr( $tail, 0, $end_match[0][1] ) );
	$blocks      = preg_split( '/\n{2,}/', $description );
	$wrapped     = array();

	foreach ( $blocks as $block ) {
		$lines = explode( "\n", trim( $block ) );
		if ( count( $lines ) < 2 ) {
			continue;
		}

		$is_list = true;
		foreach ( $lines as $line ) {
			if ( 0 !== strpos( $line, '* ' ) ) {
				$is_list = false;
				break;
			}
		}

		if ( ! $is_list ) {
			$wrapped[] = $lines[0];
		}
	}

	return $wrapped;
}

$root = dirname( __DIR__ );
$wordpress_wrapped = bprg_readme_description_wrapped_paragraphs(
	$root . '/readme.txt',
	'/^== Description ==\n/m',
	'/^== Installation ==$/m'
);
$github_wrapped = bprg_readme_description_wrapped_paragraphs(
	$root . '/README.md',
	'/^Description\n-+\n/m',
	'/^Installation\n-+$/m'
);

bprg_readme_assert_true(
	empty( $wordpress_wrapped ),
	'wordpress.org description prose has no manual line wraps' . ( empty( $wordpress_wrapped ) ? '' : ': ' . implode( ' | ', $wordpress_wrapped ) )
);
bprg_readme_assert_true(
	empty( $github_wrapped ),
	'GitHub description prose has no manual line wraps' . ( empty( $github_wrapped ) ? '' : ': ' . implode( ' | ', $github_wrapped ) )
);

echo "  {$assertions} assertions, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );
