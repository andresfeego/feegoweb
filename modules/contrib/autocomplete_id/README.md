Autocomplete Id
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Autocomplete entity id module extends standard entity autocomplete. Allows users
to see autocomplete match by entity id and not only by entity label. Provides
form element, entity reference field widget and allows to be enabled globally
for all existing autocomplete widgets.


REQUIREMENTS
------------

Module doesn't have any special requirements because has deal only with core's
functionality.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. See:
https://drupal.org/documentation/install/modules-themes/modules-8 for further
information.


CONFIGURATION
-------------

* Use "entity_id_autocomplete" form element instead of "entity_autocomplete".

* Configure entity form mode to use "entity_reference_autocomplete_id" widget
for entity reference fields.

* Or you can enable globally and match by entity id in all autocomplete fields.
Don't forget to configure permissions if you want to restrict access by roles.


MAINTAINERS
-----------

Current maintainers:
 * Volodymyr Mostepaniuk (mostepaniukvm) -
https://www.drupal.org/u/mostepaniukvm
 * Steve Ayers (bluegeek9) -
https://www.drupal.org/u/bluegeek9
