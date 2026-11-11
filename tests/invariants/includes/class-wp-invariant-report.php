<?php
/**
 * Writes the invariant report after the suite runs.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Writes what was collected as JSON, and as Markdown for the workflow summary.
 */
final class WP_Invariant_Report {

	/**
	 * Examples shown for each invariant in the Markdown report.
	 */
	const SHOWN_EXAMPLES = 3;

	/**
	 * Writes `<name>.json` and `<name>.md` into a directory.
	 *
	 * @param string $directory Directory for the reports.
	 * @param string $name      Base name of the report files.
	 * @return string The Markdown report.
	 */
	public static function write( $directory, $name ) {
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( sprintf( 'Cannot create the report directory %s.', $directory ) );
		}

		$data     = self::data();
		$markdown = self::markdown( $data );
		file_put_contents( "{$directory}/{$name}.json", json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n" );
		file_put_contents( "{$directory}/{$name}.md", $markdown );

		return $markdown;
	}

	/**
	 * Gathers the invariants, what they found, and the extension's status.
	 *
	 * @return array
	 */
	private static function data() {
		$status     = function_exists( 'gameshark_invariants_status' ) ? gameshark_invariants_status() : array();
		$executions = array();
		foreach ( isset( $status['specs'] ) ? $status['specs'] : array() as $spec ) {
			$executions[ $spec['id'] ] = $spec;
		}

		$invariants = array();
		$recorded   = WP_Invariant_Violations::all();
		foreach ( WP_Invariant::all() as $id => $invariant ) {
			$hooks = array();
			foreach ( $invariant->hooks() as $index => $hook ) {
				$spec    = isset( $executions[ "{$id}#{$index}" ] ) ? $executions[ "{$id}#{$index}" ] : array();
				$hooks[] = array(
					'when'       => 'pre' === $hook['when'] ? 'before' : 'after',
					'target'     => $hook['target'],
					'matched'    => ! empty( $spec['matched'] ),
					'executions' => isset( $spec['executions'] ) ? $spec['executions'] : 0,
				);
			}

			$found             = isset( $recorded[ $id ] ) ? $recorded[ $id ] : null;
			$invariants[ $id ] = array(
				'description' => $invariant->description(),
				'hooks'       => $hooks,
				'violations'  => $found ? $found['violations'] : 0,
				'hook_errors' => $found ? $found['hook_errors'] : 0,
				'tests'       => $found ? $found['tests'] : array(),
				'examples'    => $found ? $found['examples'] : array(),
			);
		}

		return array(
			'invariants' => $invariants,
			'extension'  => array_diff_key( $status, array( 'specs' => true ) ),
		);
	}

	/**
	 * Formats the report as Markdown.
	 *
	 * @param array $data What data() gathered.
	 * @return string
	 */
	private static function markdown( array $data ) {
		$violated   = array();
		$never_ran  = array();
		$violations = 0;
		$tests      = array();
		foreach ( $data['invariants'] as $id => $invariant ) {
			if ( $invariant['violations'] + $invariant['hook_errors'] > 0 ) {
				$violated[ $id ] = $invariant;
				$violations     += $invariant['violations'] + $invariant['hook_errors'];
				$tests          += $invariant['tests'];
			}
			foreach ( $invariant['hooks'] as $hook ) {
				if ( ! $hook['matched'] ) {
					$never_ran[] = sprintf( '- `%s`: %s `%s` was never called.', $id, $hook['when'], $hook['target'] );
				}
			}
		}
		uasort(
			$violated,
			static function ( $a, $b ) {
				return ( $b['violations'] + $b['hook_errors'] ) <=> ( $a['violations'] + $a['hook_errors'] );
			}
		);

		$out  = "## Invariants\n\n";
		$out .= sprintf(
			"%s of %d invariants were violated, %s times in %s tests. This report does not fail the job.\n\n",
			count( $violated ),
			count( $data['invariants'] ),
			number_format( $violations ),
			number_format( count( $tests ) )
		);

		if ( array() !== $violated ) {
			$out .= "| Invariant | Violations | Hook errors | Tests | First example |\n| --- | ---: | ---: | ---: | --- |\n";
			foreach ( $violated as $id => $invariant ) {
				$first = $invariant['examples'][0];
				$out  .= sprintf(
					"| `%s` | %s | %s | %s | %s<br>in `%s` |\n",
					$id,
					number_format( $invariant['violations'] ),
					number_format( $invariant['hook_errors'] ),
					number_format( count( $invariant['tests'] ) ),
					self::cell( $first['message'] ),
					self::cell( $first['test'] )
				);
			}

			$out .= "\n<details><summary>Examples</summary>\n\n";
			foreach ( $violated as $id => $invariant ) {
				$out .= sprintf( "### `%s`\n\n%s\n\n", $id, $invariant['description'] );
				foreach ( array_slice( $invariant['examples'], 0, self::SHOWN_EXAMPLES ) as $example ) {
					$out .= sprintf( "- %s%s<br>%s `%s`, in `%s`\n", 'hook-error' === $example['kind'] ? '**Hook error:** ' : '', self::one_line( $example['message'] ), ucfirst( $example['when'] ), $example['target'], $example['test'] );
					foreach ( $example['stack'] as $frame ) {
						$out .= "  - `{$frame}`\n";
					}
				}
				$out .= "\n";
			}
			$out .= "</details>\n\n";
		}

		if ( array() !== $never_ran ) {
			$out .= "### Hooks which never ran\n\nTheir target was never called while the suite ran. A misspelled target looks like this.\n\n" . implode( "\n", $never_ran ) . "\n\n";
		}

		$out .= "<details><summary>How many times each hook ran</summary>\n\n| Invariant | Hook | Runs |\n| --- | --- | ---: |\n";
		foreach ( $data['invariants'] as $id => $invariant ) {
			foreach ( $invariant['hooks'] as $hook ) {
				$out .= sprintf( "| `%s` | %s `%s` | %s |\n", $id, $hook['when'], $hook['target'], number_format( $hook['executions'] ) );
			}
		}
		$out .= "\n</details>\n";

		return $out;
	}

	/**
	 * Makes text safe for a Markdown table cell.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function cell( $text ) {
		return str_replace( '|', '\\|', self::one_line( $text ) );
	}

	/**
	 * Puts text on one line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function one_line( $text ) {
		return preg_replace( '/\s*[\r\n]+\s*/', ' ', $text );
	}
}
