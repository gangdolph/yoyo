<?php
function assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message);
    }
}

$base = __DIR__ . '/..';
$sidebar = file_get_contents($base . '/includes/sidebar.php');
assert_contains('href="trade.php"', $sidebar, 'Sidebar missing Trade Offers link');
assert_contains('Trade Offers', $sidebar, 'Sidebar should label Trade Offers');
assert_contains('href="trade-listing.php"', $sidebar, 'Sidebar missing Create Listing link');

$trade = file_get_contents($base . '/trade.php');
assert_contains('href="trade-listing.php"', $trade, 'Trade page missing link to create listing');

$listings = file_get_contents($base . '/trade-listings.php');
assert_contains('href="trade.php"', $listings, 'Listings page missing link back to trade offers');
assert_contains('href="trade-listing.php"', $listings, 'Listings page missing link to create listing');

echo "All trade navigation tests passed\n";

