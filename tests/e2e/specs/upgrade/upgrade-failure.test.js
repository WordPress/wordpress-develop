/**
 * External dependencies
 */
import path from 'path';
import fs from 'fs';

/**
 * WordPress dependencies
 */
import {
	visitAdminPage,
} from "@wordpress/e2e-test-utils";


/**
## A notice should be displayed in Site Health if the /wp-content/upgrade/temp-backup folder is not writable
- Given that the /wp-content/upgrade/temp-backup folder exists and is not writable
- When I go to the Site Health status page
- Then I should see a critical security issue with the message "The temp-backup directory exists but is not writable"
 */

async function checkTempBackupFolderStatus() {
	const tempBackupFolder = path.join(
		__dirname,
		'..',
		'..',
		'..',
		'..',
		'build',
		'wp-content',
		'upgrade',
		'temp-backup'
	);
	
	if(!fs.existsSync(tempBackupFolder)) {
		fs.mkdirSync(tempBackupFolder);
	}

	console.log(fs.access(tempBackupFolder, fs.constants.W_OK));
}

describe('Update failures tests', () => {
	it('should display a notice in Site Health if the /wp-content/upgrade/temp-backup folder is not writable', async () => {
		await checkTempBackupFolderStatus();
	});
});
