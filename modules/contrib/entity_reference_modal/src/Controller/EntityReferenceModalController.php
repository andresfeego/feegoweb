<?php

namespace Drupal\entity_reference_modal\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Entity reference modal routes.
 */
class EntityReferenceModalController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(protected KillSwitch $pageCacheKillSwitch, protected RequestStack $requestStack, protected $formBuilder, protected SelectionPluginManagerInterface $selectionManager) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('request_stack'),
      $container->get('form_builder'),
      $container->get('plugin.manager.entity_reference_selection'),
    );
  }

  /**
   * Builds the response.
   */
  public function build(Request $request, $entity_type, $bundle, $mode = 'default') {
    $this->pageCacheKillSwitch->trigger();
    $bundleKey = $this->entityTypeManager()->getDefinition($entity_type)
      ->getKey('bundle');
    $entity = $this->entityTypeManager->getStorage($entity_type)->create([
      $bundleKey => $bundle,
    ]);
    $form_mode = $request->query->get('mode');
    // @todo Fix problem custom form https://www.drupal.org/project/drupal/issues/2511720
    // Remove if $handler_type will be fixed.
    $handler_type = $this->entityTypeManager()
      ->getDefinition($entity_type, TRUE);
    $form_handlers = $handler_type->getHandlerClasses()['form'];
    if (!empty($form_mode)) {
      $mode = $form_mode;
      if (empty($form_handlers[$mode])) {
        $handler_type->setFormClass($mode, $form_handlers['default']);
      }
    }
    $form = $this->entityTypeManager()->getFormObject($entity_type, $mode)
      ->setEntity($entity);
    $form = $this->formBuilder->getForm($form);
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $mode
   *   Mode default.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $entity_type, $bundle, $mode = 'default') {
    $request = $this->requestStack->getCurrentRequest();
    $wrapper_format = $request->query->get('_wrapper_format');
    $drupal_ajax = $request->request->get('_drupal_ajax');
    if ($wrapper_format != 'drupal_modal' && $drupal_ajax != 1) {
      return AccessResult::forbidden();
    }
    $access_handler = $this->entityTypeManager()->getAccessControlHandler($entity_type);
    if ($bundle) {
      $access = $access_handler->createAccess($bundle);
    }
    else {
      $access = $access_handler->createAccess();
    }
    return AccessResult::allowedIf($access);
  }

  /**
   * Get entity for search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The controller request service.
   * @param string $target_type
   *   The entity type.
   * @param string $selection_handler
   *   The selection handler.
   * @param string $selection_settings_key
   *   The key crypto.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Ajax to get select list.
   */
  public function fieldReference(Request $request, $target_type, $selection_handler, $selection_settings_key): JsonResponse {
    $selection_settings = $this->keyValue('entity_autocomplete')->get($selection_settings_key);
    $options['target_type'] = $target_type;
    $separator = '';
    $fields = [];
    if ($selection_handler == 'views') {
      $options += [
        'view' => $selection_settings["view"],
        'handler' => 'views',
      ];
      $view = Views::getView($selection_settings["view"]["view_name"]);
      $view->setDisplay($selection_settings["view"]["display_name"]);
      $displayHandler = $view->displayHandlers->get($selection_settings["view"]["display_name"]);
      $rowOptions = $displayHandler->getPlugin('row')->options;
      $separator = $rowOptions["separator"];
      $fields = array_keys($displayHandler->getOption('fields'));
    }
    else {
      $options += [
        'target_bundles' => $selection_settings['target_bundles'],
        'handler' => 'default:' . $target_type,
      ];
      if ($target_type == 'user') {
        $options['target_bundles'] = NULL;
      }
    }
    $handler = $this->selectionManager->getInstance($options);
    $entity_labels = $handler->getReferenceableEntities();
    $rows = [];
    foreach ($entity_labels as $values) {
      foreach ($values as $entity_id => $label) {
        $row = ['id' => $entity_id];
        if (!empty($separator)) {
          $fieldValues = explode($separator, strip_tags($label));
          foreach ($fields as $index => $fieldName) {
            $row[$fieldName] = $fieldValues[$index] ?? '';
          }
        }
        else {
          $row['name'] = $label;
        }
        $rows[] = $row;
      }
    }
    return new JsonResponse($rows);
  }

}
