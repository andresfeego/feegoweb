<?php

namespace Drupal\autocomplete_id\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Extended default autocomplete.
 *
 * Included entity selection by id.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects.
 *
 * @see \Drupal\Core\Render\Annotation\FormElement
 * @see \Drupal\Core\Entity\Element\EntityAutocomplete
 * @see plugin_api
 *
 * @FormElement("entity_id_autocomplete")
 */
class EntityIdAutocomplete extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = static::class;

    $info['#element_validate'] = [[$class, 'validateEntityIdAutocomplete']];

    return $info;
  }

  /**
   * {@inheritdoc}
   *
   * Just change autocomplete route name.
   */
  public static function processEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element = parent::processEntityAutocomplete($element, $form_state, $complete_form);
    $element['#autocomplete_route_name'] = 'autocomplete_id.entity_id_autocomplete';
    return $element;
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityIdAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {
      $options = $element['#selection_settings'] + [
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ];
      /** @var /Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $auto_create = (bool) $element['#autocreate'] && $handler instanceof SelectionWithAutocreateInterface;
      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = $element['#tags'] ? Tags::explode($element['#value']) : [$element['#value']];

        foreach ($input_values as $input) {
          $match = static::extractEntityIdFromAutocompleteInput($input);
          if ($match === NULL) {
            // Try to get a match from the input string when the user didn't use
            // the autocomplete but filled in a value manually.
            $match = static::matchEntityById($handler, $input, $element, $form_state, !$auto_create);
          }

          if ($match !== NULL) {
            $value[] = [
              'target_id' => $match,
            ];
          }
          elseif ($auto_create) {
            /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
            // Auto-create item. See an example of how this is handled in
            // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
            $value[] = [
              'entity' => $handler->createNewEntity($element['#target_type'], $element['#autocreate']['bundle'], $input, $element['#autocreate']['uid']),
            ];
          }
        }
      }

      // Check that the referenced entities are valid, if needed.
      if ($element['#validate_reference'] && !empty($value)) {
        // Validate existing entities.
        $ids = array_reduce($value, function ($return, $item) {
          if (isset($item['target_id'])) {
            $return[] = $item['target_id'];
          }
          return $return;
        });

        if ($ids) {
          $valid_ids = $handler->validateReferenceableEntities($ids);
          if ($invalid_ids = array_diff($ids, $valid_ids)) {
            foreach ($invalid_ids as $invalid_id) {
              $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', [
                '%type' => $element['#target_type'],
                '%id' => $invalid_id,
              ]));
            }
          }
        }

        // Validate newly created entities.
        $new_entities = array_reduce($value, function ($return, $item) {
          if (isset($item['entity'])) {
            $return[] = $item['entity'];
          }
          return $return;
        });

        if ($new_entities) {
          if ($auto_create) {
            $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
            $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
          }
          else {
            // If the selection handler does not support referencing newly
            // created entities, all of them should be invalidated.
            $invalid_new_entities = $new_entities;
          }

          foreach ($invalid_new_entities as $entity) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity */
            $form_state->setError($element, t('This entity (%type: %label) cannot be referenced.', [
              '%type' => $element['#target_type'],
              '%label' => $entity->label(),
            ]));
          }
        }
      }

      // Use only the last value if the form element does not support multiple
      // matches (tags).
      if (!$element['#tags'] && !empty($value)) {
        $last_value = $value[count($value) - 1];
        $value = $last_value['target_id'] ?? $last_value;
      }
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Finds an entity from an autocomplete input without an explicit ID.
   *
   * The method will return an entity ID if one single entity unambiguously
   * matches the incoming input, and assign form errors otherwise.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler
   *   Entity reference selection plugin.
   * @param string $input
   *   Single string from autocomplete element.
   * @param array $element
   *   The form element to set a form error.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $strict
   *   Whether to trigger a form error if an element from $input (eg. an entity)
   *   is not found.
   *
   * @return int|null
   *   Value of a matching entity ID, or NULL if none.
   */
  protected static function matchEntityById(SelectionInterface $handler, $input, array &$element, FormStateInterface $form_state, $strict) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type = $entity_type_manager->getDefinition($element['#target_type']);
    $entity_id_key = $entity_type->getKey('id');
    $entities = $entity_type_manager->getStorage($element['#target_type'])->loadByProperties([$entity_id_key => $input]);

    $params = [
      '%value' => $input,
      '@value' => $input,
      '@entity_type_plural' => $entity_type->getPluralLabel(),
    ];
    if (empty($entities)) {
      if ($strict) {
        // Error if there are no entities available for a required field.
        $form_state->setError($element, t('There are no @entity_type_plural matching "%value".', $params));
      }
    }
    elseif (count($entities) > 5) {
      $params['@id'] = key($entities);
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many @entity_type_plural are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($entities) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = [];
      foreach ($entities as $id => $name) {
        $multiples[] = $name . ' (' . $id . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple @entity_type_plural match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => strip_tags(implode('", "', $multiples))] + $params));
    }
    else {
      // Take the one and only matching entity.
      return key($entities);
    }
  }

}
