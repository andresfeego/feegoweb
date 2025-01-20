/**
 * @file
 * Global utilities.
 *
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.feego_sys = {
    attach: function (context, settings) {
      jQuery('.path-solicitudes .views-row .views-field-field-request-state .field-content').each(function (){
        if (!jQuery(this).hasClass( "bar" )) {
          jQuery(this).addClass('state-request-' + jQuery(this)[0].innerHTML.toLowerCase().replace(' ','-') );
        }
      })
      
      jQuery('.path-servicios .views-row .views-field-field-service-state .field-content').each(function (){
        if (!jQuery(this).hasClass( "bar" )) {
          jQuery(this).addClass('state-service-' + jQuery(this)[0].innerHTML.toLowerCase().replace(' ','-') );
        }
      })
      
    }
  };
})(Drupal);
