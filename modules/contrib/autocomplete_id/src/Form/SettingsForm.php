<?php

namespace Drupal\autocomplete_id\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Autocomplete id settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'autocomplete_id_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'autocomplete_id.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('autocomplete_id.settings');
    $form['autocomplete_id_global'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable globally.'),
      '#description' => $this->t('All entity_autocomplete render elements that are using system.entity_autocomplete route will display first match by entity id if exist. You can configure permissions to restrict by role.'),
      '#default_value' => $config->get('autocomplete_id_global'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('autocomplete_id.settings');
    $config->set('autocomplete_id_global', $form_state->getValue('autocomplete_id_global'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
