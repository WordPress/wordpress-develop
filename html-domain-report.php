<?php

require_once __DIR__ . '/src/wp-load.php';

function go() {
	$opts    = getopt( 'o:q::w:' ); // -o output file, -q quiet mode
	$loud    = ! isset( $opts['q'] );
	$workers = intval( $opts['w'] );

	$domains_file     = fopen( './domain-list.txt', 'r' );
	$domain_file_size = filesize( './domain-list.txt' );
	$worker_size      = $domain_file_size / $workers;

	$report_filename = isset( $opts['o'] ) ? $opts['o'] : './domain-report.csv';
	$report          = fopen( $report_filename, isset( $otps['o'] ) ? 'a' : 'w' );

	fputcsv(
		$report,
		array(
			'i',
			'domain',
			'bytes',
			'tag count',
			'tag time in nanoseconds',
			'html tag count',
			'time in nanoseconds',
			'success',
			'failing tag',
			'error tag',
			'ms tags',
			'ms html',
			'tags mem',
			'html mem',
			'ratio',
		)
	);

	$total = 10000001;
	$count = 0;

	// Clear terminal screen and move cursor to top left.
	if ( $loud ) {
		echo "\e[2J\e[1;1H";
	}

	$wid = 0;
	for ( $w = 0; $w < $workers; ++$w ) {
		$pid = pcntl_fork();
		if ( 0 === $pid ) {
			$wid = $w;
			break;
		}
	}

	$at = floor( $wid * $worker_size );
	fseek( $domains_file, $at );
	$stop_at = ( $wid + 1 ) * $worker_size;

	// Flush out content until the first newline.
	$at += strlen( fgets( $domains_file ) );

	while ( ! feof( $domains_file ) && $at < $stop_at ) {
		++$count;
		$line   = fgets( $domains_file );
		$at    += strlen( $line );
		$domain = trim( $line );
		$url    = "https://{$domain}/";

		// Move cursor to second line and clear it.
		if ( $loud ) {
			echo "\e[1;1H\e[0K";
		}

		$progress        = $count / $total;
		$progress_length = floor( $progress * 80 );

		// Move cursor to start of third line and clear it.
		if ( $loud ) {
			$progress_color  = $progress > 0.8 ? "\x1b[31m" : ( $progress > 0.5 ? "\x1b[33m" : "\x1b[32m" );
			echo $progress_color . str_pad( '', $progress_length, '█' ) . "\x1b[0m";
			echo "\e[2;1H\e[0K";
			echo "\e[90mWorking on \e[36m{$url}\e[m";
		}

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 4 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 8 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 3 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'WordPress/6.5.0; http://wordpress.org/' );

		$response = curl_exec( $ch );
		$info     = curl_getinfo( $ch );

		// Move cursor to start of third line and clear it.
		if ( $loud ) {
			echo "\e[3;1H\e[0K";
			echo "\e[90mStatus: \e[36m{$info['http_code']}\e[90m, Size: \e[36m{$info['size_download']}\e[90m, Time: \e[36m{$info['total_time']}\e[90m, Redirects: \e[36m{$info['redirect_count']}\e[m";
		}

		if ( false !== $response && 200 === $info['http_code'] ) {
			$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			$body        = substr( $response, $header_size );

			$html_tag_count = 0;
			$failure_tag    = null;
			$html_mem = memory_get_peak_usage();
			memory_reset_peak_usage();
			$tic            = hrtime( true );
			$processor      = WP_HTML_Processor::create_fragment( $body );
			try {
				while ( $processor->next_tag() ) {
					++$html_tag_count;
				}
			} catch ( Exception $e ) {
				$failure_tag = $e->getMessage();
			}
			$toc = hrtime( true );
			$html_mem = memory_get_peak_usage() - $html_mem;
			$ns  = $toc - $tic;

			$did_succeed = null === $processor->get_last_error() ? 'success' : 'failure';

			$tag_count         = 0;
			$tags_mem = memory_get_peak_usage();
			memory_reset_peak_usage();
			$tic               = hrtime( true );
			$tag_processor     = new WP_HTML_Tag_Processor( $body );
			while ( $tag_processor->next_tag() ) {
				++$tag_count;
			}
			$toc = hrtime( true );
			$tags_mem = memory_get_peak_usage() - $tags_mem;
			$ns_tags = $toc - $tic;

			// Move cursor to start of fourth line and clear it.
			if ( $loud ) {
				echo "\e[4;1H\e[0K";
			}

			$ms_tags = $ns_tags / 1000000;
			$ms_html = $ns / 1000000;
			$ratio   = $ns / $ns_tags;
			$bytes   = strlen( $body );

			// Print results as CSV row with: domain, tag count, tag time in nanoseconds, html tag count, time in nanoseconds, success.
			fputcsv(
				$report,
				[
					$count,
					$domain,
					$bytes,
					$tag_count,
					$ns_tags,
					$html_tag_count,
					$ns,
					$did_succeed,
					$failure_tag ?? '',
					$processor->get_last_error(),
					$ms_tags,
					$ms_html,
					$tags_mem,
					$html_mem,
					$ratio,
				]
			);

			if ( $loud ) {
				echo "\e[90m{$domain} \e[36m{$tag_count}\e[90m tags in \e[36m{$ms_tags}\e[90m ms, \e[36m{$html_tag_count}\e[90m html tags in \e[36m{$ms_html}\e[90m ms, ratio: \e[36m{$ratio}\e[90m\e[m\n";
				if ( $failure_tag ) {
					echo "\e[90mfailed at: \e[35m{$failure_tag}\e[m\n";
				}
			}
		} else {
			// Move cursor to start of fifth line and clear it, then print error in red.
			if ( $loud ) {
				echo "\e[5;1H\e[0K";
				echo "\e[90mError: \e[36m" . curl_error( $ch ) . "\e[m";
			}
		}

		curl_close( $ch );
	}
}

go();
