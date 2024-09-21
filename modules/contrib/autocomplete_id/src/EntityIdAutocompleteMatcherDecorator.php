<?php

namespace Drupal\autocomplete_id;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * This class is decorator for "entity.autocomplete_matcher" service.
 *
 * Decorates the EntityAutocompleteMatcher service. Implements the
 * EntityAutocompleteMatcherInterface interface through our
 * EntityIdAutocompleteMatcher class.
 *
 * By implementing the interface instead of extending the core class, we allow
 * easier layers of decoration.
 *
 * @see \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
 * @see \Drupal\autocomplete_id\EntityIdAutocompleteMatcher
 */
class EntityIdAutocompleteMatcherDecorator extends EntityIdAutocompleteMatcher {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a EntityIdAutocompleteMatcherDecorator object.
   *
   * @param \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $matcher
   *   The autocomplete matcher for entity references.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityAutocompleteMatcherInterface $matcher, SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    parent::__construct($matcher, $selection_manager, $entity_type_manager, $entity_repository, $current_user);
  }

  /**
   * {@inheritdoc}
   */
  protected function access() {
    // Also check whether enabled globally.
    $config = $this->configFactory->get('autocomplete_id.settings');
    return $this->currentUser->hasPermission('view entity autocomplete id results') && $config->get('autocomplete_id_global');
  }

}
