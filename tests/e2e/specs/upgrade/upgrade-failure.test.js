/**
 * External dependencies
 */
import path from 'path';
const fs = require('fs');

/**
 * WordPress dependencies
 */
import {
    visitAdminPage,
} from "@wordpress/e2e-test-utils";

const TEMP_BACKUP_FOLDER = path.join(
    __dirname,
    '..',
    '..',
    '..',
    '..',
    'src',
    'wp-content',
    'upgrade',
    'temp-backup'
);

/**
 * Create the temp backup folder if it doesn't exist
 */
async function createTempBackupFolder() {
    if (!fs.existsSync(TEMP_BACKUP_FOLDER)) {
        fs.mkdirSync(TEMP_BACKUP_FOLDER);
    }
}

/**
 * Make the temp backup folder non-writable
 * Checks if the folder is writable and if it is, make it non-writable
 */
async function makeTempBackupNotWritable() {
    fs.access(TEMP_BACKUP_FOLDER, fs.constants.W_OK, (err) => {
        if (!err) {
            console.log('is writable');

            // Make the folder 444
            fs.chmodSync(TEMP_BACKUP_FOLDER, 0o444);
        }
    });
}

describe('Update failures tests', () => {
    it('should display a notice in Site Health if the temp-backup folder is not writable', async() => {
        await createTempBackupFolder();
        await makeTempBackupNotWritable();

        await visitAdminPage('site-health.php');

        let criticalNotices = await page.waitForSelector('#health-check-site-status-critical');
        expect(
            await criticalNotices.evaluate((el) => el.textContent)
        ).toContain('The temp-backup directory exists but is not writable');
    });
});
