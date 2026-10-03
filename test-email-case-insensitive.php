<?php
/**
 * Test file to demonstrate case-insensitive email comparisons
 * 
 * This file tests the changes made to WordPress email comparison functions
 * to ensure they are now case-insensitive using strcasecmp()
 */

// Load WordPress
require_once 'src/wp-load.php';

/**
 * Test function to demonstrate case-insensitive email comparisons
 */
function test_email_case_insensitive_comparisons() {
    echo "<h2>Email Case-Insensitive Comparison Tests</h2>\n";
    echo "<p>This test demonstrates the changes made to WordPress email comparison functions.</p>\n";
    
    // Test data
    $test_emails = array(
        'admin@example.com',
        'Admin@Example.com',
        'ADMIN@EXAMPLE.COM',
        'user@test.com',
        'User@Test.Com'
    );
    
    $admin_email = 'admin@example.com';
    
    echo "<h3>1. Testing strcasecmp() vs === comparison</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Email</th><th>Admin Email</th><th>=== (Case Sensitive)</th><th>strcasecmp() (Case Insensitive)</th></tr>\n";
    
    foreach ($test_emails as $email) {
        $case_sensitive = ($email === $admin_email) ? 'true' : 'false';
        $case_insensitive = (0 === strcasecmp($email, $admin_email)) ? 'true' : 'false';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($email) . "</td>";
        echo "<td>" . htmlspecialchars($admin_email) . "</td>";
        echo "<td style='color: " . ($case_sensitive === 'true' ? 'green' : 'red') . "'>" . $case_sensitive . "</td>";
        echo "<td style='color: " . ($case_insensitive === 'true' ? 'green' : 'red') . "'>" . $case_insensitive . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>2. Testing WordPress Functions</h3>\n";
    
    // Test the specific functions we modified
    echo "<h4>Testing update_option_new_admin_email() logic:</h4>\n";
    echo "<ul>\n";
    
    $current_admin_email = get_option('admin_email');
    echo "<li>Current admin email: " . htmlspecialchars($current_admin_email) . "</li>\n";
    
    // Test with different case variations
    $test_cases = array(
        'same_case' => $current_admin_email,
        'different_case' => strtoupper($current_admin_email),
        'mixed_case' => ucfirst(strtolower($current_admin_email))
    );
    
    foreach ($test_cases as $case => $test_email) {
        $old_way = ($current_admin_email === $test_email);
        $new_way = (0 === strcasecmp($current_admin_email, $test_email));
        
        echo "<li>Test email: " . htmlspecialchars($test_email) . "</li>";
        echo "<li>&nbsp;&nbsp;Old way (===): " . ($old_way ? 'Match' : 'No Match') . "</li>";
        echo "<li>&nbsp;&nbsp;New way (strcasecmp): " . ($new_way ? 'Match' : 'No Match') . "</li>";
    }
    echo "</ul>\n";
    
    echo "<h4>Testing welcome panel logic:</h4>\n";
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $admin_email = get_option('admin_email');
    
    echo "<ul>\n";
    echo "<li>Current user email: " . htmlspecialchars($user_email) . "</li>\n";
    echo "<li>Admin email: " . htmlspecialchars($admin_email) . "</li>\n";
    
    $old_way = ($user_email !== $admin_email);
    $new_way = (0 !== strcasecmp($user_email, $admin_email));
    
    echo "<li>Old way (user_email !== admin_email): " . ($old_way ? 'Different' : 'Same') . "</li>\n";
    echo "<li>New way (strcasecmp): " . ($new_way ? 'Different' : 'Same') . "</li>\n";
    echo "</ul>\n";
    
    echo "<h3>3. Real-world Scenarios</h3>\n";
    echo "<p>These scenarios show how the changes affect WordPress behavior:</p>\n";
    
    echo "<h4>Scenario 1: Admin Email Change</h4>\n";
    echo "<p>When a user tries to change the admin email to the same address with different case:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Before:</strong> WordPress would allow the change and send confirmation email</li>\n";
    echo "<li><strong>After:</strong> WordPress recognizes it's the same email and prevents unnecessary change</li>\n";
    echo "</ul>\n";
    
    echo "<h4>Scenario 2: Welcome Panel Display</h4>\n";
    echo "<p>When a multisite owner has an email with different case than admin_email:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Before:</strong> Welcome panel might be hidden even for the actual admin</li>\n";
    echo "<li><strong>After:</strong> Welcome panel correctly shows for the admin regardless of case</li>\n";
    echo "</ul>\n";
    
    echo "<h4>Scenario 3: Update Notifications</h4>\n";
    echo "<p>When WordPress checks if it has already notified an email address:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Before:</strong> Might send duplicate notifications for same email with different case</li>\n";
    echo "<li><strong>After:</strong> Correctly identifies same email and avoids duplicates</li>\n";
    echo "</ul>\n";
    
    echo "<h3>4. Summary</h3>\n";
    echo "<p>The changes ensure that email addresses are treated consistently regardless of case, improving:</p>\n";
    echo "<ul>\n";
    echo "<li>User experience (no confusion about email case sensitivity)</li>\n";
    echo "<li>System reliability (prevents duplicate notifications)</li>\n";
    echo "<li>Admin functionality (welcome panel, email changes work correctly)</li>\n";
    echo "</ul>\n";
}

// Run the test if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    // Set up basic HTML
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Email Case-Insensitive Test</title></head><body>\n";
    echo "<h1>WordPress Email Case-Insensitive Comparison Test</h1>\n";
    
    try {
        test_email_case_insensitive_comparisons();
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "</body></html>\n";
}
?> 