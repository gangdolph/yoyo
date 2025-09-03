<?php
function assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message);
    }
}

$base = __DIR__ . '/..';
$services = file_get_contents($base . '/services.php');
$step = file_get_contents($base . '/service-step.php');

$categories = ['repair', 'clean', 'build', 'other'];
foreach ($categories as $cat) {
    assert_contains('data-category="' . $cat . '"', $services, "Missing $cat button");
    assert_contains("'$cat'", $step, "Service step missing $cat handling");
}
assert_contains('Device Type', $step, 'Device type field missing');
assert_contains('Make', $step, 'Make field missing');

echo "All service category flow tests passed\n";
