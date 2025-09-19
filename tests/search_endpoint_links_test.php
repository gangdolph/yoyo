<?php
$search = file_get_contents(__DIR__ . '/../search.php');
if ($search === false) {
    throw new RuntimeException('Unable to read search.php');
}

$listingAnchor = "href=\"listing.php?listing_id=<?= \$l['id']; ?>\"";
if (strpos($search, $listingAnchor) === false) {
    throw new Exception('Search results should link to listing detail pages.');
}

$shippingAnchor = "href=\"shipping.php?listing_id=<?= \$l['id']; ?>\"";
if (strpos($search, $shippingAnchor) !== false) {
    throw new Exception('Search results should not link directly to shipping.');
}

echo "Search endpoint link tests passed\n";
