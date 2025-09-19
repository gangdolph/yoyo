<?php
/**
 * @var array       $serviceTaxonomy             Structured taxonomy describing all service flows.
 * @var array       $brandOptions                Array of brand records with `id` and `name` keys.
 * @var array       $modelOptions                Array of model records with `id`, `brand_id`, and `name` keys.
 * @var string      $modelsEndpoint              Endpoint used to fetch models by brand (used for progressive enhancement).
 * @var string|null $serviceWizardDefaultService Optional service id to preselect when rendering the wizard.
 */

$brandMap = [];
foreach ($brandOptions as $brand) {
    $brandId = (string) $brand['id'];
    $brandMap[$brandId] = $brand['name'];
}

$modelGroups = [];
foreach ($modelOptions as $model) {
    $brandId = (string) $model['brand_id'];
    if (!isset($modelGroups[$brandId])) {
        $modelGroups[$brandId] = [];
    }
    $modelGroups[$brandId][] = $model;
}

$serviceIds = array_keys($serviceTaxonomy);
$serviceWizardDefaultServiceId = '';
if (isset($serviceWizardDefaultService) && is_string($serviceWizardDefaultService)) {
    $candidate = trim($serviceWizardDefaultService);
    if ($candidate !== '' && isset($serviceTaxonomy[$candidate])) {
        $serviceWizardDefaultServiceId = $candidate;
    }
}
if ($serviceWizardDefaultServiceId === '' && $serviceIds) {
    $serviceWizardDefaultServiceId = (string) $serviceIds[0];
}

$fieldNameResolver = static function (string $serviceId, string $fieldName): array {
    $isArray = false;
    if (substr($fieldName, -2) === '[]') {
        $isArray = true;
        $fieldName = substr($fieldName, 0, -2);
    }

    $name = $isArray
        ? sprintf('service[%s][%s][]', $serviceId, $fieldName)
        : sprintf('service[%s][%s]', $serviceId, $fieldName);

    return [$name, $fieldName, $isArray];
};

function service_wizard_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function service_wizard_render_select_options(array $options, string $valueKey = 'id', string $labelKey = 'name'): string
{
    $output = '';
    foreach ($options as $option) {
        if (!isset($option[$valueKey], $option[$labelKey])) {
            continue;
        }
        $output .= sprintf(
            '<option value="%s">%s</option>',
            service_wizard_escape((string) $option[$valueKey]),
            service_wizard_escape((string) $option[$labelKey])
        );
    }

    return $output;
}

$defaultServiceAttr = $serviceWizardDefaultServiceId !== ''
    ? ' data-default-service="' . service_wizard_escape($serviceWizardDefaultServiceId) . '"'
    : '';
$modelsEndpointAttr = $modelsEndpoint !== ''
    ? ' data-models-endpoint="' . service_wizard_escape($modelsEndpoint) . '"'
    : '';
?>
<div class="service-wizard no-js" data-service-wizard<?= $defaultServiceAttr; ?><?= $modelsEndpointAttr; ?>>
  <form class="wizard-form" method="post" action="submit-request.php" enctype="multipart/form-data" data-service-form>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="type" value="service">

    <fieldset class="wizard-picker-fieldset">
      <legend class="wizard-picker__legend">What do you need help with?</legend>
      <div class="wizard-picker" data-service-picker>
        <?php foreach ($serviceTaxonomy as $serviceId => $definition):
            $checked = $serviceId === $serviceWizardDefaultServiceId;
            $label = isset($definition['label']) ? (string) $definition['label'] : ucfirst($serviceId);
            $summary = isset($definition['summary']) ? (string) $definition['summary'] : '';
        ?>
          <label class="wizard-picker__option">
            <input
              class="wizard-picker__input"
              type="radio"
              name="category"
              value="<?= service_wizard_escape($serviceId); ?>"
              data-service-option
              data-service-target="<?= service_wizard_escape($serviceId); ?>"
              <?= $checked ? 'checked' : ''; ?>
            >
            <span class="wizard-picker__title"><?= service_wizard_escape($label); ?></span>
            <?php if ($summary !== ''): ?>
              <span class="wizard-picker__summary"><?= service_wizard_escape($summary); ?></span>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="wizard-steps" data-step-container>
      <?php foreach ($serviceTaxonomy as $serviceId => $definition):
          $flow = $definition['flow'] ?? [];
          $steps = $flow['steps'] ?? [];
          $entryStep = isset($flow['entry']) ? (string) $flow['entry'] : (string) array_key_first($steps);
          $serviceLabel = isset($definition['label']) ? (string) $definition['label'] : ucfirst($serviceId);
          $serviceSummary = isset($definition['summary']) ? (string) $definition['summary'] : '';
      ?>
        <section class="wizard-flow" data-service-flow="<?= service_wizard_escape($serviceId); ?>" data-entry-step="<?= service_wizard_escape($entryStep); ?>">
          <header class="wizard-flow__header">
            <h3 class="wizard-flow__title"><?= service_wizard_escape($serviceLabel); ?></h3>
            <?php if ($serviceSummary !== ''): ?>
              <p class="wizard-flow__summary"><?= service_wizard_escape($serviceSummary); ?></p>
            <?php endif; ?>
          </header>
          <?php foreach ($steps as $stepId => $stepConfig):
              $component = isset($stepConfig['component']) ? (string) $stepConfig['component'] : 'input';
              $label = isset($stepConfig['label']) ? (string) $stepConfig['label'] : ucfirst($stepId);
              $summary = isset($stepConfig['summary']) ? (string) $stepConfig['summary'] : '';
              $required = !empty($stepConfig['required']);
              $placeholder = isset($stepConfig['placeholder']) ? (string) $stepConfig['placeholder'] : '';
              $defaultNext = isset($stepConfig['next']) && is_string($stepConfig['next']) ? (string) $stepConfig['next'] : '';
              $isTerminal = array_key_exists('next', $stepConfig) && $stepConfig['next'] === null;
              $fieldName = isset($stepConfig['name']) ? (string) $stepConfig['name'] : $stepId;
              [$nameAttr, $fieldKey, $isArrayField] = $fieldNameResolver($serviceId, $fieldName);
              $controlId = sprintf('wizard-%s-%s-%s', $serviceId, $stepId, $fieldKey);
          ?>
            <fieldset
              class="wizard-step"
              data-step
              data-step-id="<?= service_wizard_escape((string) $stepId); ?>"
              data-component="<?= service_wizard_escape($component); ?>"
              data-default-next="<?= service_wizard_escape($defaultNext); ?>"
              <?= $isTerminal ? ' data-terminal="true"' : ''; ?>
              <?= $required ? ' data-step-required="true"' : ''; ?>
              <?= !empty($stepConfig['allowMultiple']) ? ' data-allow-multiple="true"' : ''; ?>
            >
              <legend class="wizard-step__title"><?= service_wizard_escape($label); ?></legend>
              <?php if ($summary !== ''): ?>
                <p class="wizard-step__summary"><?= service_wizard_escape($summary); ?></p>
              <?php endif; ?>
              <p class="wizard-step__error" data-step-error hidden></p>
              <div class="wizard-step__body">
                <?php if ($component === 'select'): ?>
                  <label class="wizard-field" for="<?= service_wizard_escape($controlId); ?>">
                    <span class="wizard-field__label"><?= service_wizard_escape($label); ?></span>
                    <select
                      class="wizard-field__control"
                      id="<?= service_wizard_escape($controlId); ?>"
                      name="<?= service_wizard_escape($nameAttr); ?>"
                      data-field-name="<?= service_wizard_escape($fieldKey); ?>"
                      data-required="<?= $required ? 'true' : 'false'; ?>"
                      <?= isset($stepConfig['optionsSource']) ? ' data-options-source="' . service_wizard_escape((string) $stepConfig['optionsSource']) . '"' : ''; ?>
                      <?= isset($stepConfig['dependsOn']) ? ' data-depends-on="' . service_wizard_escape((string) $stepConfig['dependsOn']) . '"' : ''; ?>
                    >
                      <option value="">
                        <?= $placeholder !== '' ? service_wizard_escape($placeholder) : 'Select an option'; ?>
                      </option>
                      <?php if (($stepConfig['optionsSource'] ?? '') === 'brands'): ?>
                        <?= service_wizard_render_select_options($brandOptions); ?>
                      <?php elseif (($stepConfig['optionsSource'] ?? '') === 'models'): ?>
                        <?php foreach ($modelGroups as $brandId => $models):
                            $brandLabel = $brandMap[$brandId] ?? ('Brand ' . $brandId);
                        ?>
                          <optgroup label="<?= service_wizard_escape($brandLabel); ?>" data-brand-id="<?= service_wizard_escape($brandId); ?>">
                            <?php foreach ($models as $model): ?>
                              <option value="<?= service_wizard_escape((string) $model['id']); ?>" data-brand-id="<?= service_wizard_escape($brandId); ?>">
                                <?= service_wizard_escape((string) $model['name']); ?>
                              </option>
                            <?php endforeach; ?>
                          </optgroup>
                        <?php endforeach; ?>
                      <?php elseif (!empty($stepConfig['options']) && is_array($stepConfig['options'])): ?>
                        <?php foreach ($stepConfig['options'] as $option):
                            if (!isset($option['value'], $option['label'])) {
                                continue;
                            }
                        ?>
                          <option value="<?= service_wizard_escape((string) $option['value']); ?>">
                            <?= service_wizard_escape((string) $option['label']); ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </label>
                <?php elseif ($component === 'textarea'): ?>
                  <label class="wizard-field" for="<?= service_wizard_escape($controlId); ?>">
                    <span class="wizard-field__label"><?= service_wizard_escape($label); ?></span>
                    <textarea
                      class="wizard-field__control"
                      id="<?= service_wizard_escape($controlId); ?>"
                      name="<?= service_wizard_escape($nameAttr); ?>"
                      rows="5"
                      data-field-name="<?= service_wizard_escape($fieldKey); ?>"
                      data-required="<?= $required ? 'true' : 'false'; ?>"
                      <?= $placeholder !== '' ? ' placeholder="' . service_wizard_escape($placeholder) . '"' : ''; ?>
                    ></textarea>
                  </label>
                <?php elseif ($component === 'input'): ?>
                  <?php $inputType = isset($stepConfig['type']) ? (string) $stepConfig['type'] : 'text'; ?>
                  <label class="wizard-field" for="<?= service_wizard_escape($controlId); ?>">
                    <span class="wizard-field__label"><?= service_wizard_escape($label); ?></span>
                    <input
                      class="wizard-field__control"
                      id="<?= service_wizard_escape($controlId); ?>"
                      type="<?= service_wizard_escape($inputType); ?>"
                      name="<?= service_wizard_escape($nameAttr); ?>"
                      data-field-name="<?= service_wizard_escape($fieldKey); ?>"
                      data-required="<?= $required ? 'true' : 'false'; ?>"
                      <?= $placeholder !== '' ? ' placeholder="' . service_wizard_escape($placeholder) . '"' : ''; ?>
                    >
                  </label>
                <?php elseif ($component === 'options'): ?>
                  <fieldset class="wizard-options" role="group">
                    <legend class="wizard-field__label"><?= service_wizard_escape($label); ?></legend>
                    <div class="wizard-options__list">
                      <?php
                      $inputType = ($stepConfig['inputType'] ?? '') === 'checkbox' ? 'checkbox' : 'radio';
                      $allowMultiple = !empty($stepConfig['allowMultiple']) || $inputType === 'checkbox';
                      $optionIndex = 0;
                      foreach ($stepConfig['options'] ?? [] as $option):
                          if (!isset($option['value'], $option['label'])) {
                              continue;
                          }
                          $optionIndex++;
                          $optionId = sprintf('%s-%s-%d', $controlId, $inputType, $optionIndex);
                          $optionNext = isset($option['next']) ? (string) $option['next'] : '';
                      ?>
                        <label class="wizard-option" for="<?= service_wizard_escape($optionId); ?>">
                          <input
                            class="wizard-option__input"
                            id="<?= service_wizard_escape($optionId); ?>"
                            type="<?= service_wizard_escape($inputType); ?>"
                            name="<?= service_wizard_escape($nameAttr); ?>"
                            value="<?= service_wizard_escape((string) $option['value']); ?>"
                            data-field-name="<?= service_wizard_escape($fieldKey); ?>"
                            <?= $required && $inputType === 'radio' ? ' data-required="true"' : ''; ?>
                            <?= $optionNext !== '' ? ' data-next-step="' . service_wizard_escape($optionNext) . '"' : ''; ?>
                            <?= $allowMultiple ? ' data-multiple="true"' : ''; ?>
                          >
                          <span class="wizard-option__label"><?= service_wizard_escape((string) $option['label']); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                <?php elseif ($component === 'file'): ?>
                  <div class="wizard-upload">
                    <label class="wizard-field" for="<?= service_wizard_escape($controlId); ?>">
                      <span class="wizard-field__label"><?= service_wizard_escape($label); ?></span>
                      <input
                        class="wizard-field__control"
                        id="<?= service_wizard_escape($controlId); ?>"
                        type="file"
                        name="<?= service_wizard_escape($nameAttr); ?>"
                        data-field-name="<?= service_wizard_escape($fieldKey); ?>"
                        <?= $required ? ' data-required="true"' : ''; ?>
                        <?= isset($stepConfig['accept']) ? ' accept="' . service_wizard_escape((string) $stepConfig['accept']) . '"' : ''; ?>
                      >
                    </label>
                    <p class="wizard-upload__hint">JPEG or PNG up to 5 MB.</p>
                  </div>
                <?php endif; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="wizard-footer" data-wizard-footer>
      <button type="button" class="btn btn-secondary" data-action="back" disabled>Back</button>
      <button type="button" class="btn" data-action="next" disabled>Next</button>
      <button type="submit" class="btn btn-primary" data-action="submit" disabled>Submit Request</button>
    </div>
  </form>
  <noscript>
    <p class="wizard-noscript">JavaScript is disabled. All service flows are shown below—complete the section that best matches your request and submit the form when you are ready.</p>
  </noscript>
</div>
