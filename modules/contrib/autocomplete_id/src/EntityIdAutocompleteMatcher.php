<?php

namespace Drupal\autocomplete_id;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * This matcher class is using default matcher.
 *
 * It use default matcher class as dependency to have default functionality
 * and include matches by entity id.
 *
 * @see \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
 */
class EntityIdAutocompleteMatcher implements EntityAutocompleteMatcherInterface {

  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
   */
  protected $matcher;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * Constructs a EntityIdAutocompleteMatcher object.
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
   */
  public function __construct(EntityAutocompleteMatcherInterface $matcher, SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, AccountInterface $current_user) {
    $this->matcher = $matcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->currentUser = $current_user;
    $this->selectionManager = $selection_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    if (empty($string)) {
      return [];
    }

    // Get default result.
    $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, $string);

    // Check access to additional autocomplete results.
    if (!$this->access()) {
      return $matches;
    }

    try {
      $entity_storage = $this->entityTypeManager->getStorage($target_type);
    }
    catch (PluginNotFoundException $e) {
      return $matches;
    }
    $entity = $entity_storage->load($string);
    // Check if entity exist and meets settings requirements.
    if (!isset($entity) ||
      !$entity->access('view', $this->currentUser) ||
      (isset($selection_settings['target_bundles']) && is_array($selection_settings['target_bundles']) && !in_array($entity->bundle(), $selection_settings['target_bundles']))) {
      return $matches;
    }

    $entity_id = $entity->id();
    $label = Html::escape($this->entityRepository->getTranslationFromContext($entity)
      ->label());

    // Loop through the entities and convert them into autocomplete output.
    $key = "$label ($entity_id)";
    // Strip things like starting/trailing white spaces, line breaks and
    // tags.
    $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
    // Names containing commas or quotes must be wrapped in quotes.
    $key = Tags::encode($key);
    // Remove last result if we reach configured limit.
    if (!empty($selection_settings['match_limit']) && count($matches) == $selection_settings['match_limit']) {
      array_pop($matches);
    }
    array_unshift($matches, [
      'value' => $key,
      'label' => "$label ($entity_id)",
    ]);

    return $matches;
  }

  /**
   * Check access to autocomplete results by entity id.
   */
  protected function access() {
    return $this->currentUser->hasPermission('view entity autocomplete id results');
  }

}
