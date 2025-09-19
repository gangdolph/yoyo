<?php
/**
 * @var array $serviceTaxonomy Structured taxonomy describing all service flows.
 * @var array $brandOptions    Array of brand records with `id` and `name` keys.
 * @var string $modelsEndpoint Endpoint used to fetch models by brand.
 */
$encodedTaxonomy = [];
foreach ($serviceTaxonomy as $serviceId => $definition) {
    $encodedTaxonomy[] = array_merge(['id' => $serviceId], $definition);
}
$wizardConfig = [
    'taxonomy' => $encodedTaxonomy,
    'brands' => array_values(array_map(static function (array $brand): array {
        return [
            'id' => (int) $brand['id'],
            'name' => $brand['name'],
        ];
    }, $brandOptions)),
    'endpoints' => [
        'models' => $modelsEndpoint,
    ],
];
$json = json_encode($wizardConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<div class="service-wizard" data-service-wizard>
  <div class="wizard-picker" data-service-picker></div>
  <form class="wizard-form" method="post" action="submit-request.php" enctype="multipart/form-data" data-service-form>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="type" value="service">
    <input type="hidden" name="category" id="serviceCategory">
    <div class="wizard-steps" data-step-container></div>
    <div class="wizard-footer" data-wizard-footer>
      <button type="button" class="btn btn-secondary" data-action="back" disabled>Back</button>
      <button type="button" class="btn" data-action="next" disabled>Next</button>
      <button type="submit" class="btn btn-primary" data-action="submit" disabled>Submit Request</button>
    </div>
  </form>
  <script type="application/json" id="serviceWizardData"><?= htmlspecialchars($json, ENT_QUOTES, 'UTF-8'); ?></script>
  <?php include __DIR__ . '/service-wizard-templates.php'; ?>
</div>
