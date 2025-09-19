<?php
// Templates for the service wizard. These are cloned by assets/service-wizard.js
?>
<template data-template="step">
  <section class="wizard-step" data-step>
    <header class="wizard-step__header">
      <h3 class="wizard-step__title" data-step-title></h3>
      <p class="wizard-step__summary" data-step-summary hidden></p>
    </header>
    <p class="wizard-step__error" data-step-error hidden></p>
    <div class="wizard-step__body" data-step-body></div>
  </section>
</template>

<template data-template="select">
  <label class="wizard-field">
    <span class="wizard-field__label" data-field-label></span>
    <select class="wizard-field__control" data-field-control></select>
  </label>
</template>

<template data-template="textarea">
  <label class="wizard-field">
    <span class="wizard-field__label" data-field-label></span>
    <textarea class="wizard-field__control" rows="5" data-field-control></textarea>
  </label>
</template>

<template data-template="input">
  <label class="wizard-field">
    <span class="wizard-field__label" data-field-label></span>
    <input class="wizard-field__control" data-field-control>
  </label>
</template>

<template data-template="options">
  <fieldset class="wizard-options" data-options-group>
    <legend class="wizard-field__label" data-field-label></legend>
    <div class="wizard-options__list" data-options-list></div>
  </fieldset>
</template>

<template data-template="option-item">
  <label class="wizard-option">
    <input class="wizard-option__input" data-option-input>
    <span class="wizard-option__label" data-option-label></span>
  </label>
</template>

<template data-template="file">
  <div class="wizard-upload">
    <label class="wizard-field">
      <span class="wizard-field__label" data-field-label></span>
      <input type="file" class="wizard-field__control" data-field-control>
    </label>
    <p class="wizard-upload__hint">JPEG or PNG up to 5 MB.</p>
  </div>
</template>
