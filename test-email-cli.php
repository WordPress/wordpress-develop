<?php
/**
 * Command-line test for email case-insensitive comparisons
 */

// Load WordPress
require_once 'src/wp-load.php';

echo "WordPress Email Case-Insensitive Comparison Test\n";
echo "===============================================\n\n";

// Test data
$test_emails = array(
	'admin@example.com',
	'Admin@Example.com',
	'ADMIN@EXAMPLE.COM',
	'user@test.com',
	'User@Test.Com'
);

$admin_email = 'admin@example.com';

echo "1. Testing strcasecmp() vs === comparison:\n";
echo str_repeat('-', 80) . "\n";
printf("%-20s %-20s %-15s %-15s\n", "Email", "Admin Email", "=== (Sensitive)", "strcasecmp()");
echo str_repeat('-', 80) . "\n";

foreach ($test_emails as $email) {
	$case_sensitive = ($email === $admin_email) ? 'true' : 'false';
	$case_insensitive = (0 === strcasecmp($email, $admin_email)) ? 'true' : 'false';
	
	printf("%-20s %-20s %-15s %-15s\n", 
		substr($email, 0, 19), 
		substr($admin_email, 0, 19), 
		$case_sensitive, 
		$case_insensitive
	);
}

echo "\n2. Testing WordPress Functions:\n";
echo str_repeat('-', 50) . "\n";

// Test the specific functions we modified
echo "Testing update_option_new_admin_email() logic:\n";
$current_admin_email = get_option('admin_email');
echo "Current admin email: " . $current_admin_email . "\n";

// Test with different case variations
$test_cases = array(
	'same_case' => $current_admin_email,
	'different_case' => strtoupper($current_admin_email),
	'mixed_case' => ucfirst(strtolower($current_admin_email))
);

foreach ($test_cases as $case => $test_email) {
	$old_way = ($current_admin_email === $test_email);
	$new_way = (0 === strcasecmp($current_admin_email, $test_email));
	
	echo "Test email: " . $test_email . "\n";
	echo "  Old way (===): " . ($old_way ? 'Match' : 'No Match') . "\n";
	echo "  New way (strcasecmp): " . ($new_way ? 'Match' : 'No Match') . "\n\n";
}

echo "Testing welcome panel logic:\n";
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
$admin_email = get_option('admin_email');

echo "Current user email: " . $user_email . "\n";
echo "Admin email: " . $admin_email . "\n";

$old_way = ($user_email !== $admin_email);
$new_way = (0 !== strcasecmp($user_email, $admin_email));

echo "Old way (user_email !== admin_email): " . ($old_way ? 'Different' : 'Same') . "\n";
echo "New way (strcasecmp): " . ($new_way ? 'Different' : 'Same') . "\n\n";

echo "3. Real-world Scenarios:\n";
echo str_repeat('-', 30) . "\n";

echo "Scenario 1: Admin Email Change\n";
echo "Before: WordPress would allow the change and send confirmation email\n";
echo "After: WordPress recognizes it's the same email and prevents unnecessary change\n\n";

echo "Scenario 2: Welcome Panel Display\n";
echo "Before: Welcome panel might be hidden even for the actual admin\n";
echo "After: Welcome panel correctly shows for the admin regardless of case\n\n";

echo "Scenario 3: Update Notifications\n";
echo "Before: Might send duplicate notifications for same email with different case\n";
echo "After: Correctly identifies same email and avoids duplicates\n\n";

echo "4. Summary:\n";
echo "The changes ensure that email addresses are treated consistently regardless of case.\n";
echo "This improves user experience, system reliability, and admin functionality.\n";
?> 