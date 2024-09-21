<?php

namespace Drupal\entity_reference_modal\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_modal' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_modal",
 *   label = @Translation("Autocomplete (add new with Modal)"),
 *   description = @Translation("An autocomplete text field with tagging support."),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class EntityReferenceModalWidget extends EntityReferenceAutocompleteWidget implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  /**
   * Selector form id.
   *
   * @var string
   */
  protected string $selector;

  /**
   * Constructs a EntityReferenceModalWidget instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   Entity display service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Entity type bundle info service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   The key value service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, protected EntityDisplayRepositoryInterface $entityDisplayRepository, protected EntityTypeManagerInterface $entityTypeManager, protected EntityFieldManagerInterface $entityFieldManager, protected EntityTypeBundleInfoInterface $entityTypeBundleInfo, protected KeyValueFactoryInterface $keyValue, protected LanguageManagerInterface $languageManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('keyvalue'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'add_new_button_title' => '+',
      'modal_form_mode' => 'default',
      'modal_width' => '80%',
      'modal_title' => t('Add new'),
      'duplicate' => FALSE,
      'search' => TRUE,
      'bootstrap' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $entityType = $this->getFieldSetting('target_type');
    $modes = $this->entityDisplayRepository->getFormModeOptions($entityType);
    $element['modal_form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t("Form mode"),
      '#default_value' => $this->getSetting('modal_form_mode'),
      '#options' => $modes,
    ];
    $element['add_new_button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Button title"),
      '#default_value' => $this->getSetting('add_new_button_title'),
    ];
    $element['modal_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Modal width"),
      '#default_value' => $this->getSetting('modal_width'),
    ];
    $element['modal_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Modal title"),
      '#default_value' => $this->getSetting('modal_title'),
    ];
    $element['duplicate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Duplicates allowed"),
      '#default_value' => $this->getSetting('duplicate'),
    ];
    $element['search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Button search'),
      '#default_value' => $this->getSetting('search'),
    ];
    $element['bootstrap'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load bootstrap'),
      '#default_value' => $this->getSetting('bootstrap'),
      '#description' => $this->t('If your admin theme is not bootstrap 5 admin, it will load bootstrap 5 cdn'),
      '#states' => [
        'visible' => [
          ':input[name*="[settings_edit_form][settings][search]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $form['#attached']['library'][] = 'entity_reference_modal/entity_reference_modal';
    $input = array_merge($form['#parents'], [
      $items->getName(),
      $delta,
      'target_id',
    ]);
    $name = array_shift($input);
    $selector = $name . '[' . implode('][', $input) . ']';
    $this->selector = 'input[name="' . $selector . '"]';
    $modalTitle = $this->getSetting('modal_title');
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $attributes = [
      'title' => $modalTitle,
      'data-delta' => $delta,
      'data-field' => $items->getName(),
      'data-mode' => $this->getSetting('modal_form_mode'),
      'data-duplicate' => $this->getSetting('duplicate'),
      'class' => ['use-ajax', 'button', 'btn', 'btn-success'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'title' => $modalTitle,
        'width' => $this->getSetting('modal_width'),
      ]),
    ];
    $query = [
      'query' => [
        'selector' => $this->selector,
        'mode' => $this->getSetting('modal_form_mode'),
        'duplicate' => $this->getSetting('duplicate'),
        'delta' => $delta,
        'field' => $items->getName(),
      ],
    ];
    // Add information for hook form to know is widget modal.
    $this->prepareFormState($form_state, $items);

    $links = [];
    $settings = $this->getFieldSettings();
    $target = $settings["target_type"];
    $handlerSettings = $settings["handler_settings"];
    $handlers = $handlerSettings['target_bundles'] ?? [];
    if (empty($handlers)) {
      $handlers = [$target];
      if (!empty($handlerSettings['view'])) {
        $view = Views::getView($handlerSettings["view"]["view_name"]);
        $display_name = $handlerSettings["view"]["display_name"];
        if (is_object($view)) {
          $view->setArguments($handlerSettings["view"]["arguments"]);
          $view->setDisplay($display_name);
          $filters = $view->getHandlers('filter');
          foreach (['type', 'vid'] as $filterType) {
            if (!empty($filters[$filterType]["value"])) {
              $handlers = $filters[$filterType]["value"];
              break;
            }
          }
        }
      }
    }
    if (!empty($this->getSetting('search'))) {
      $searchIcon = '🔎';
      $searchKey = 'name';
      $selector = str_replace('_', '-', "edit-" . $items->getName() . "-$delta-target-id");
      $columns = [
        ['field' => "state", 'radio' => "true"],
      ];
      if ($this->getFieldSetting('handler') == 'views' && !empty($handlerSettings["view"])) {
        $view_name = $handlerSettings["view"]["view_name"];
        $display_name = $handlerSettings["view"]["display_name"];
        if (empty($view)) {
          $view = Views::getView($view_name);
        }
        $displayHandler = $view->displayHandlers->get($display_name);
        $fields = $displayHandler->getOption('fields');
        $styleOptions = $displayHandler->getPlugin('style')->options['search_fields'];
        $styleOptions = array_filter($styleOptions);
        $searchKey = array_key_first($styleOptions);
        $listType = [
          'list_default',
          'options_select',
          'list_key',
          'options_buttons',
          'list_key',
        ];
        foreach ($fields as $fieldId => $field) {
          $type = $field["type"] ?? '';
          $typeSearch = in_array($type, $listType) ? 'select' : 'input';
          $title = $field['label'] ?: '';
          if (empty($title)) {
            $entity_type = $field["entity_type"] ?? current(explode('__', $field["table"]));
            $field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type);
            if (empty($field_definitions[$fieldId])) {
              $bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type));
              foreach ($bundles as $bundle) {
                $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
                if (isset($field_definitions[$fieldId])) {
                  $title = $field_definitions[$fieldId]->getLabel() ?? '';
                  break;
                }
              }
            }
            else {
              $field_definition = $field_definitions[$fieldId];
              $title = $field_definition->getLabel();
            }
          }
          if (empty($title)) {
            $title = $field['entity_field'] ?? $field["field"];
            $title = ucfirst(str_replace(['field_', '_'], ['', ' '], $title));
          }
          $columns[] = [
            'field' => $fieldId,
            'title' => $title,
            'sortable' => "true",
            'filter-control' => $typeSearch,
          ];
        }
      }
      else {
        $columns[] = [
          'field' => $searchKey,
          'title' => $items->getFieldDefinition()->getLabel(),
          'sortable' => "true",
        ];
      }
      $searchTitle = $this->t('Select %type', [
        '%type' => $items->getFieldDefinition()->getLabel(),
      ]);
      $selection_settings = $settings["handler_settings"] ?? [];
      if (!empty($selection_settings)) {
        $selection_settings['match_operator'] = $selection_settings['match_operator'] ?? 'CONTAINS';
        $selection_settings['match_limit'] = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
        // Don't serialize the entity, it will be added explicitly afterwards.
        if (isset($selection_settings['entity']) && ($selection_settings['entity'] instanceof EntityInterface)) {
          unset($selection_settings['entity']);
        }
      }
      $data = serialize($selection_settings) . $target . $settings["handler"];
      $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

      $key_value_storage = $this->keyValue->get('entity_autocomplete');
      if (!$key_value_storage->has($selection_settings_key)) {
        $key_value_storage->set($selection_settings_key, $selection_settings);
      }
      $url = Url::fromRoute('entity_reference_modal.search', [
        'target_type' => $target,
        'selection_handler' => $settings["handler"],
        'selection_settings_key' => $selection_settings_key,
      ]);
      $attributesBootstrapTable = [
        'data-bs-title' => $searchTitle,
        'data-delta' => $delta,
        'data-locale' => $language,
        'data-selector' => $selector,
        'data-filter-control' => 'true',
        'data-show-search-clear-button' => 'true',
        'data-field_reference' => $items->getName(),
        'class' => ['btn', 'btn-default', 'entity-reference-search'],
      ];
      $element['#attached']['library'][] = 'entity_reference_modal/search-bootstrapTable';
      $element['#attached']['drupalSettings']['entity_reference_search'][$items->getName()]['columns'] = $columns;
      $element['#attached']['drupalSettings']['entity_reference_search'][$items->getName()]['search_key'] = $searchKey;
      $links['search'] = [
        'title' => Markup::create($searchIcon),
        'attributes' => $attributesBootstrapTable,
        'url' => $url,
      ];
    }
    foreach ($handlers as $bundle) {
      $title = $this->getSetting('add_new_button_title');
      if (count($handlers) > 1) {
        $bundle_label = $this->entityTypeBundleInfo->getBundleInfo($target)[$bundle]['label'] ?? '';
        $title .= ' ' . $bundle_label;
      }
      $access_handler = $this->entityTypeManager->getAccessControlHandler($target);
      if (!empty($bundle)) {
        $access = $access_handler->createAccess($bundle);
      }
      else {
        $access = $access_handler->createAccess();
      }
      if (!$access) {
        continue;
      }
      $links[$bundle] = [
        'title' => Markup::create($title),
        'attributes' => $attributes,
        'url' => Url::fromRoute('entity_reference_modal.entity_form', [
          'entity_type' => $target,
          'bundle' => $bundle,
        ], $query),
      ];
    }
    $element['target_id']['#wrapper_attributes'] = [
      'class' => ['input-group', 'container-inline', 'flex-inline'],
    ];
    $element['target_id']['#description'] = [
      '#type' => 'dropbutton',
      '#links' => $links,
    ];
    if (!empty($this->getSetting('bootstrap'))) {
      $element['#attached']['library'][] = 'entity_reference_modal/bootstrap';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFormState(FormStateInterface $form_state, FieldItemListInterface $items, $translating = FALSE) {
    $widget_state = $form_state->get(['entity_reference_modal', $this->selector]);
    if (empty($widget_state)) {
      $widget_state = [
        'instance' => $this->fieldDefinition,
        'delete' => [],
        'entities' => [],
      ];
      // Store $items entities in the widget state, for further manipulation.
      foreach ($items->referencedEntities() as $delta => $entity) {
        $widget_state['entities'][$delta] = [
          'entity' => $entity,
          'weight' => $delta,
          'needs_save' => $entity->isNew(),
        ];
      }
      $form_state->set(['entity_reference_modal', $this->selector], $widget_state);
    }
  }

}
