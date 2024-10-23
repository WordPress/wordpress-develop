const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { execSync } = require( 'child_process' );
const local_env_utils = require( './utils' );
const { constants, copyFile } = require( 'node:fs' );

// Copy the default .env file when one is not present.
copyFile( '.env.example', '.env', constants.COPYFILE_EXCL, (e) => {
	console.log( '.env file already exists. .env.example was not copied.' );
});

dotenvExpand.expand( dotenv.config() );

const composeFiles = local_env_utils.get_compose_files();

// Determine if a non-default database authentication plugin needs to be used.
local_env_utils.determine_auth_option();

// Check if the Docker service is running.
try {
	execSync( 'docker info' );
} catch ( e ) {
	if ( e.message.startsWith( 'Command failed: docker info' ) ) {
		throw new Error( 'Could not retrieve Docker system info. Is the Docker service running?' );
	}

	throw e;
}

// Start the local-env containers.
const containers = ( process.env.LOCAL_PHP_MEMCACHED === 'true' )
	? 'wordpress-develop memcached'
	: 'wordpress-develop';
execSync( `docker compose ${composeFiles} up -d ${containers}`, { stdio: 'inherit' } );

// If Docker Toolbox is being used, we need to manually forward LOCAL_PORT to the Docker VM.
if ( process.env.DOCKER_TOOLBOX_INSTALL_PATH ) {
	// VBoxManage is added to the PATH on every platform except Windows.
	const vboxmanage = process.env.VBOX_MSI_INSTALL_PATH ? `${ process.env.VBOX_MSI_INSTALL_PATH }/VBoxManage` : 'VBoxManage'

	// Check if the port forwarding is already configured for this port.
	const vminfoBuffer = execSync( `"${ vboxmanage }" showvminfo "${ process.env.DOCKER_MACHINE_NAME }" --machinereadable` );
	const vminfo = vminfoBuffer.toString().split( /[\r\n]+/ );

	vminfo.forEach( ( info ) => {
		if ( ! info.startsWith( 'Forwarding' ) ) {
			return;
		}

		// `info` is in the format: Forwarding(1)="tcp-port8889,tcp,127.0.0.1,8889,,8889"
		// Parse it down so `rule` only contains the data inside quotes, split by ','.
		const rule = info.replace( /(^.*?"|"$)/, '' ).split( ',' );

		// Delete rules that are using the port we need.
		if ( rule[ 3 ] === process.env.LOCAL_PORT || rule[ 5 ] === process.env.LOCAL_PORT ) {
			execSync( `"${ vboxmanage }" controlvm "${ process.env.DOCKER_MACHINE_NAME }" natpf1 delete ${ rule[ 0 ] }`, { stdio: 'inherit' } );
		}
	} );

	// Add our port forwarding rule.
	execSync( `"${ vboxmanage }" controlvm "${ process.env.DOCKER_MACHINE_NAME }" natpf1 "tcp-port${ process.env.LOCAL_PORT },tcp,127.0.0.1,${ process.env.LOCAL_PORT },,${ process.env.LOCAL_PORT }"`, { stdio: 'inherit' } );
}
