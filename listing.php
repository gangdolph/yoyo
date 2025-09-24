<?php
// Update: Added policy guidance box so buyers can review fees and fulfillment rules.
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/tags.php';
require_once __DIR__ . '/includes/PurchaseOffersService.php';

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
if (!$listing_id) {
    header('Location: buy.php');
    exit;
}

$stmt = $conn->prepare('SELECT l.id, l.product_sku, l.title, l.description, l.price, l.sale_price, l.category, l.tags, l.image, l.pickup_only, l.is_official_listing, l.quantity, l.reserved_qty, l.owner_id, p.is_skuze_official, p.is_skuze_product FROM listings l LEFT JOIN products p ON l.product_sku = p.sku WHERE l.id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

if (!$listing) {
    http_response_code(404);
    echo 'Listing not found';
    exit;
}

if (!isset($_SESSION['checkout_quantities']) || !is_array($_SESSION['checkout_quantities'])) {
    $_SESSION['checkout_quantities'] = [];
}

$storedSelections = &$_SESSION['checkout_quantities'];
$hadStoredSelection = array_key_exists($listing_id, $storedSelections);
$existingSelection = $hadStoredSelection ? (int) $storedSelections[$listing_id] : 1;
$rawQuantity = isset($listing['quantity']) ? (int) $listing['quantity'] : 0;
$rawReserved = isset($listing['reserved_qty']) ? (int) $listing['reserved_qty'] : 0;
$availableQuantity = max(0, $rawQuantity - $rawReserved);
$quantityNotice = '';
$offersContext = [
    'offers' => [],
    'seller_id' => isset($listing['owner_id']) ? (int) $listing['owner_id'] : 0,
    'actor_role' => 'viewer',
];
$offersError = '';
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($availableQuantity > 0) {
    $selectedQuantity = max(1, min($existingSelection, $availableQuantity));
    if ($hadStoredSelection && $selectedQuantity !== $existingSelection) {
        $quantityNotice = 'Quantity adjusted to available stock.';
    }
    $storedSelections[$listing_id] = $selectedQuantity;
} else {
    if ($hadStoredSelection) {
        $quantityNotice = 'This listing is currently out of stock.';
    }
    unset($storedSelections[$listing_id]);
    $selectedQuantity = 0;
}
if ($currentUserId > 0) {
    try {
        $offersService = new PurchaseOffersService($conn);
        $offersContext = $offersService->listOffersForListing($listing_id, $currentUserId);
    } catch (Throwable $offersException) {
        error_log('[listing] Failed to load offers: ' . $offersException->getMessage());
        $offersError = 'Offers are unavailable right now. Please try again later.';
    }
}

$offerCsrfToken = generate_token();

?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($listing['title']); ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/offers.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="content listing-detail">
    <div class="listing-image">
      <?php if (!empty($listing['image'])): ?>
        <img class="thumb-square" src="uploads/<?= htmlspecialchars($listing['image']); ?>" alt="<?= htmlspecialchars($listing['title']); ?>">
      <?php endif; ?>
    </div>
    <section class="listing-info">
      <h2>
        <?= htmlspecialchars($listing['title']); ?>
        <?php
          $listingBadges = [];
          if (!empty($listing['is_official_listing']) || !empty($listing['is_skuze_official'])) {
              $listingBadges[] = ['class' => 'badge-official', 'label' => 'SkuzE Official'];
          }
          if (!empty($listing['is_skuze_product'])) {
              $listingBadges[] = ['class' => 'badge-product', 'label' => 'SkuzE Product'];
          }
          foreach ($listingBadges as $badge) {
              echo ' <span class="badge ' . htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') . '</span>';
          }
        ?>
      </h2>
      <p class="description">
        <?= nl2br(htmlspecialchars($listing['description'])); ?>
      </p>
      <?php $detailTags = tags_from_storage($listing['tags']); ?>
      <?php if ($detailTags): ?>
        <ul class="tag-badge-list">
          <?php foreach ($detailTags as $tag): ?>
            <li class="tag-chip tag-chip-static">#<?= htmlspecialchars($tag); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($listing['sale_price'] !== null): ?>
        <p class="price"><span class="original">$<?= htmlspecialchars($listing['price']); ?></span> <span class="sale">$<?= htmlspecialchars($listing['sale_price']); ?></span></p>
      <?php else: ?>
        <p class="price">$<?= htmlspecialchars($listing['price']); ?></p>
      <?php endif; ?>
      <p class="stock-availability <?= $availableQuantity > 0 ? '' : 'out-of-stock'; ?>" aria-live="polite">
        <?= $availableQuantity > 0
            ? 'In stock: ' . htmlspecialchars((string) $availableQuantity)
            : 'Out of stock'; ?>
      </p>
      <?php if ($quantityNotice !== ''): ?>
        <p class="stock-availability notice"><?= htmlspecialchars($quantityNotice); ?></p>
      <?php endif; ?>
      <?php if (!empty($listing['pickup_only'])): ?>
        <p class="pickup-only">Pickup only - no shipping available</p>
      <?php endif; ?>
      <aside class="policy-callout listing-policy-hint">
        <h3>How this works</h3>
        <p>We break fees down at checkout and stick to <a href="/policies/fees.php">published rates</a>. Review <a href="/policies/shipping.php">shipping &amp; returns</a> before you place your order.</p>
      </aside>
    </section>
    <section class="listing-cta">
      <form method="get" action="shipping.php" class="listing-checkout-form">
        <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
        <label for="listing-quantity">Quantity</label>
        <input
          type="number"
          id="listing-quantity"
          name="quantity"
          min="1"
          max="<?= max(1, $availableQuantity); ?>"
          value="<?= $selectedQuantity > 0 ? $selectedQuantity : ($availableQuantity > 0 ? 1 : 0); ?>"
          <?= $availableQuantity <= 0 ? 'disabled' : ''; ?>
        >
        <button type="submit" class="btn" <?= $availableQuantity <= 0 ? 'disabled' : ''; ?>>Proceed to Checkout</button>
      </form>
      <div class="related-items">
        <h3>Related Items</h3>
        <p>
          <a href="search.php?category=<?= urlencode($listing['category']); ?>">More in this category</a>
        </p>
      </div>
      <?php if ($offersContext['actor_role'] !== 'viewer' || $offersError !== ''): ?>
        <section
          class="listing-offers"
          data-offers
          data-csrf="<?= htmlspecialchars($offerCsrfToken); ?>"
        >
          <header class="listing-offers__header">
            <h3>Offers</h3>
            <?php if ($offersContext['actor_role'] === 'seller'): ?>
              <p>You see buyer offers for this listing.</p>
            <?php elseif ($offersContext['actor_role'] === 'buyer'): ?>
              <p>Only you and the seller can view this negotiation.</p>
            <?php endif; ?>
          </header>
          <div class="listing-offers__messages" aria-live="polite">
            <p
              class="listing-offers__message<?= $offersError !== '' ? ' listing-offers__message--error' : ''; ?>"
              data-offers-message
              <?= $offersError === '' ? 'hidden' : ''; ?>
            >
              <?= $offersError !== '' ? htmlspecialchars($offersError) : ''; ?>
            </p>
          </div>
          <?php if (empty($offersContext['offers']) && $offersError === ''): ?>
            <p class="listing-offers__empty">No offers yet.</p>
          <?php else: ?>
            <ul class="listing-offers__list">
              <?php foreach ($offersContext['offers'] as $offer): ?>
                <?php
                  $statusClass = 'offer-status--' . preg_replace('/[^a-z0-9_-]/i', '-', strtolower($offer['status']));
                  $isOpen = $offer['status'] === 'open';
                ?>
                <li
                  class="listing-offers__item"
                  data-offer-row
                  data-offer-id="<?= (int) $offer['id']; ?>"
                  data-offer-status="<?= htmlspecialchars($offer['status']); ?>"
                >
                  <div class="listing-offers__meta">
                    <span class="listing-offers__initiator"><?= htmlspecialchars($offer['initiator_display']); ?></span>
                    <span class="listing-offers__details">
                      offered $<?= htmlspecialchars($offer['offer_price_display']); ?>
                      for <?= htmlspecialchars((string) $offer['quantity']); ?>
                      <?php if ($offer['quantity'] === 1): ?>item<?php else: ?>items<?php endif; ?>
                      (total $<?= htmlspecialchars($offer['total_display']); ?>)
                    </span>
                    <?php if (!empty($offer['is_counter'])): ?>
                      <span class="offer-pill">Counter</span>
                    <?php endif; ?>
                  </div>
                  <div class="listing-offers__status">
                    <span class="offer-status badge <?= htmlspecialchars($statusClass); ?>" data-offer-status>
                      <?= htmlspecialchars($offer['status_label']); ?>
                    </span>
                    <time class="listing-offers__timestamp" datetime="<?= htmlspecialchars($offer['created_at_iso'] ?? ''); ?>">
                      <?= htmlspecialchars($offer['created_at_display'] ?? '—'); ?>
                    </time>
                  </div>
                  <div class="listing-offers__actions" data-offer-actions>
                    <?php if ($isOpen && !empty($offer['can_accept'])): ?>
                      <form method="post" action="/offers/accept/index.php" data-offer-action="accept">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($offerCsrfToken); ?>">
                        <input type="hidden" name="offer_id" value="<?= (int) $offer['id']; ?>">
                        <button type="submit" class="btn btn-small">Accept</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($isOpen && !empty($offer['can_decline'])): ?>
                      <form method="post" action="/offers/decline/index.php" data-offer-action="decline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($offerCsrfToken); ?>">
                        <input type="hidden" name="offer_id" value="<?= (int) $offer['id']; ?>">
                        <button type="submit" class="btn btn-small btn-secondary">Decline</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($isOpen && !empty($offer['can_cancel'])): ?>
                      <form method="post" action="/offers/cancel/index.php" data-offer-action="cancel">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($offerCsrfToken); ?>">
                        <input type="hidden" name="offer_id" value="<?= (int) $offer['id']; ?>">
                        <button type="submit" class="btn btn-small btn-secondary">Cancel</button>
                      </form>
                    <?php endif; ?>
                    <?php if (!$isOpen || (empty($offer['can_accept']) && empty($offer['can_decline']) && empty($offer['can_cancel']))): ?>
                      <span class="listing-offers__note">No actions available.</span>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>
      <?php if (is_admin()): ?>
        <form method="post" action="listing-delete.php" onsubmit="return confirm('Delete listing?');">
          <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
          <input type="hidden" name="id" value="<?= $listing['id']; ?>">
          <input type="hidden" name="redirect" value="buy.php">
          <button type="submit" class="btn">Delete Listing</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
