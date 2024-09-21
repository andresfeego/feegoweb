<?php

namespace Drupal\autocomplete_id\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity_reference_autocomplete_id' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_autocomplete_id",
 *   label = @Translation("Autocomplete match ID"),
 *   description = @Translation("An autocomplete text field. Include matching
 *   by entity id."), field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceAutocompleteIdWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $form_element = parent::formElement($items, $delta, $element, $form, $form_state);
    $form_element['target_id']['#type'] = 'entity_id_autocomplete';
    return $form_element;
  }

}
