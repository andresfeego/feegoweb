/**
 * @file
 * Entity reference modal behaviors.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.entity_reference_modal = {
    attach (context, settings) {
      $.fn.injectEntity = (data) => {
        data = JSON.parse(data);
        $('.ui-dialog-titlebar-close').click();
        $(data.selector).val(data.entity);
      };
    }
  };

} (jQuery, Drupal));
