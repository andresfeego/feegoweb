<?php

namespace Drupal\field_permissions\Plugin\migrate\process;

use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Migration plugin for field permission settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_field_permission_settings"
 * )
 */
class FieldPermissionSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = $row->getSourceProperty('field_permissions');
    if ($value === NULL) {
      return ['permission_type' => FieldPermissionTypeInterface::ACCESS_PUBLIC];
    }
    switch ($value['type']) {
      case 0:
        $permission_type = FieldPermissionTypeInterface::ACCESS_PUBLIC;
        break;

      case 1:
        $permission_type = FieldPermissionTypeInterface::ACCESS_PRIVATE;
        break;

      case 2:
        $permission_type = FieldPermissionTypeInterface::ACCESS_CUSTOM;
        break;
    }
    return ['permission_type' => $permission_type];
  }

}
