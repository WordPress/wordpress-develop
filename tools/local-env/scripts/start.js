const dotenv = require( 'dotenv' );
const { execSync } = require( 'child_process' );

dotenv.config();

// Start the local-env containers.
execSync( 'docker-compose up -d wordpress-develop', { stdio: 'inherit' } );

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
