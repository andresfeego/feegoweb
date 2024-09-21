<?php

namespace Drupal\entity_reference_finder\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller to find Entity Reference.
 *
 * @ingroup orejime
 */
class EntityReferenceFinderForm extends FormBase {

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a EntityReferencesFinderForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'entity_reference_finder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['autocomplete'] = 'off';

    // Select an entity.
    $form['entity'] = [
      '#title' => $this->t('Entity'),
      '#type' => 'select',
      '#default_value' => 'node',
      '#options' => $this->getEntities(),
      '#ajax' => [
        'callback' => '::changeBundleSelect',
        'disable-refocus' => TRUE,
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Verifying entry...'),
        ],
        'wrapper' => 'bundle',
      ],
    ];

    $form['container'] = [
      '#prefix' => '<div id="bundle">',
      '#suffix' => '</div>',
    ];

    // Select a Bundle.
    $entity = $form_state->getValue("entity") ?: 'node';
    $bundles = $this->getBundles($entity);
    $default_value = $form_state->getValue("bundle") ?: array_key_first($bundles);
    $form['container']['bundle'] = [
      '#title' => $this->t('Bundle'),
      '#type' => 'select',
      '#options' => $bundles,
      '#default_value' => $default_value,
      '#ajax' => [
        'callback' => '::changeTable',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Verifying entry...'),
        ],
        'wrapper' => 'table',
      ],
    ];

    // Fields list table.
    $fields = $this->getFields($entity, $default_value);
    $form['container']['table'] = [
      '#type' => 'table',
      '#prefix' => '<div id="table">',
      '#suffix' => '</div>',
      '#header' => [
        'field' => $this->t('Field'),
        'entity' => $this->t('Entity type'),
        'bundle' => $this->t('Bundle'),
      ],
      '#empty' => $this->t('No reference found'),
      '#sticky' => TRUE,
    ];
    foreach ($fields as $i => $field) {
      foreach ($field as $field_item) {
        $form['container']['table'][$i][]['data'] = ['#markup' => $field_item];
      }
    }
    return $form;
  }

  /**
   * Ajax callback after select an entity.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A reference to a keyed array containing the current state of the form.
   *
   * @return mixed
   *   Ajax replacement.
   */
  public function changeBundleSelect(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    return $form['container'];
  }

  /**
   * Ajax callback after select a bundle.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A reference to a keyed array containing the current state of the form.
   *
   * @return mixed
   *   Ajax replacement.
   */
  public function changeTable(array &$form, FormStateInterface $form_state) {
    return $form['container']['table'];
  }

  /**
   * Get all content entities.
   *
   * @return array
   *   Array of content entities.
   */
  private function getEntities() {
    $options = [];
    $entity_type_definitions = $this->entityTypeManager->getDefinitions();
    foreach ($entity_type_definitions as $definition) {
      if ($definition instanceof ContentEntityType) {
        $options[$definition->id()] = $definition->getLabel();
      }
    }
    return $options;
  }

  /**
   * Get all bundle for an entity.
   *
   * @param string $entity
   *   Entity id.
   *
   * @return array
   *   Array of bundle.
   */
  private function getBundles($entity) {
    $options[''] = $this->t('- Select -');
    $bundles_infos = $this->entityTypeBundleInfo->getBundleInfo($entity);
    foreach ($bundles_infos as $id => $bundle) {
      $options[$id] = $bundle['label'];
    }
    return $options;
  }

  /**
   * Get all fields for an entity and bundle.
   *
   * @param string $entityBase
   *   The entity id.
   * @param string $bundleBase
   *   The bundle id.
   *
   * @return array
   *   Array of fields.
   */
  private function getFields($entityBase, $bundleBase) {
    $fields = [];
    foreach (['entity_reference', 'entity_reference_revisions'] as $field_type) {
      $list_field = $this->entityFieldManager->getFieldMapByFieldType($field_type);
      foreach ($list_field as $entity => $entity_fields) {
        foreach ($entity_fields as $field_name => $field) {
          foreach ($field['bundles'] as $bundle => $field_bundle) {
            /** @var \Drupal\field\FieldConfigInterface $def */
            $def = FieldConfig::loadByName($entity, $field_bundle, $field_name);
            if ($def !== NULL && $def->getSetting('target_type') === $entityBase && isset($def->getSetting('handler_settings')['target_bundles'][$bundleBase])) {
              $fields[] = [
                $field_name,
                $entity,
                $this->entityTypeBundleInfo->getBundleInfo($entity)[$bundle]['label'],
              ];
            }
          }
        }
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
