<?php
function assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message);
    }
}

$base = __DIR__ . '/..';
$services = file_get_contents($base . '/services.php');
assert_contains('data-step="1"', $services, 'Wizard missing step 1');
assert_contains('data-category="repair"', $services, 'Repair option missing');
assert_contains('name="brand_id"', $services, 'Brand select missing');
assert_contains('api/models.php', $services, 'Model API endpoint missing');
assert_contains('name="model_id"', $services, 'Model select missing');
assert_contains('name="issue"', $services, 'Issue field missing');
assert_contains('Please select a brand', $services, 'Client validation for brand missing');
assert_contains('Please select a model', $services, 'Client validation for model missing');

echo "All service wizard tests passed\n";
