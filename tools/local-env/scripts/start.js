const dotenv = require( 'dotenv' );
const { execSync } = require( 'child_process' );

dotenv.config();

// Start the local-env containers.
execSync( 'docker-compose up -d wordpress-develop', { stdio: 'inherit' } );

// If Docker Toolbox is being used, we need to manually forward LOCAL_PORT to the Docker VM.
if ( process.env.DOCKER_TOOLBOX_INSTALL_PATH ) {
	// VBoxManage is added to the PATH on every platform except Windows.
	const vboxmanage = process.env.VBOX_MSI_INSTALL_PATH ? `${process.env.VBOX_MSI_INSTALL_PATH}/VBoxManage` : 'VBoxManage'
	execSync( `"${vboxmanage}" controlvm "${process.env.DOCKER_MACHINE_NAME}" natpf1 "tcp-port${process.env.LOCAL_PORT},tcp,127.0.0.1,${process.env.LOCAL_PORT},,${process.env.LOCAL_PORT}"`, { stdio: 'inherit' } );
}
