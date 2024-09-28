<?php

namespace Drupal\fontawesome_iconpicker\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'fontawesome_iconpicker' widget.
 *
 * @FieldWidget(
 *   id = "fontawesome_iconpicker",
 *   label = @Translation("Font Awesome Icon Picker"),
 *   field_types = {
 *     "text",
 *     "string",
 *   }
 * )
 */
class FontawesomeIconpicker extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 60,
      'placeholder' => '',
      'type' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of Icon Picker'),
      '#default_value' => $this->getSetting('type'),
      '#required' => TRUE,
      '#options' => $this->getIconPickerTypes(),
    ];

    $elements['size'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Field Size'),
      '#description' => $this->t('Select a field size.'),
      '#default_value' => $this->getSetting('size'),
    ];

    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if (!empty($this->getSetting('type'))) {
      $label = $this->getIconPickerTypes()[$this->getSetting('type')];
      $summary[] = $this->t('Type: @type', ['@type' => $label]);
    }

    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    if (!empty($this->getSetting('size'))) {
      $summary[] = $this->t('Field size: @size', ['@size' => $this->getSetting('size')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $type = $this->getSetting('type');
    $option = [
      'theme' => 'default',
      'iconSource' => [
        "FontAwesome Solid 5",
        "FontAwesome Regular 5",
        "FontAwesome Brands 6",
        "FontAwesome Solid 6",
        "FontAwesome Regular 6",
      ],
      'closeOnSelect' => TRUE,
      'i18n' => [
        'input:placeholder' => $this->t('Search icon…'),
        'text:title' => $this->t('Select icon'),
        'text:empty' => $this->t('No results found…'),
        'btn:save' => $this->t('Save'),
      ],
    ];
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? NULL,
      '#size' => $this->getSetting('size'),
      '#icon' => $this->getSetting('icon'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
      '#attributes' => [
        'data-theme' => 'default',
        'class' => [
          'fontawesomeIconPickerVanillaIconPicker',
        ],
      ],
      '#attached' => [
        'library' => [
          'fontawesome_iconpicker/vanilla-icon-picker',
        ],
      ],
    ];
    if ($type == 'component') {
      $option['theme'] = 'bootstrap';
      $element['value']['#attached']['library'][] = 'fontawesome_iconpicker/vanilla-icon-picker-theme-bootstrap';
    }
    $element['value']['#attributes']['data-option'] = \json_encode($option);
    return $element;
  }

  /**
   * Get Supported Icon Picker Types.
   */
  private function getIconPickerTypes() {
    return [
      'default' => $this->t('Default'),
      'component' => $this->t('As a bootstrap component'),
      // 'input_search' => $this->t('Input as a search box'),
      // 'dropdown' => $this->t('Inside dropdown (with Label and Icon)'),
      // 'dropdown_icon' => $this->t('Inside dropdown (with icon only)'),
    ];
  }

}
