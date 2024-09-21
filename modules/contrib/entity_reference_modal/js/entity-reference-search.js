(function (Drupal, $, drupalSettings, once) {
  'use strict';

  // Save object to local storage.
  function saveObjectToLocalStorage(object, storageKey, fieldKey) {
    let storedData = localStorage.getItem(storageKey);
    let dataObject = storedData ? JSON.parse(storedData) : {};
    if (!dataObject[fieldKey]) {
      dataObject[fieldKey] = {};
    }
    dataObject[fieldKey][object.id] = object;
    localStorage.setItem(storageKey, JSON.stringify(dataObject));
  }

  Drupal.behaviors.entity_reference_search = {
    attach: function (context) {
      $(once('search-reference', '.entity-reference-search', context)).on("click", function (event) {
        event.preventDefault();
        let url = $(this).attr('href');
        let data = $(this).data();
        let settings = drupalSettings.entity_reference_search[data['field_reference']];
        let search_key = settings.search_key || 'name';
        let table = $(`<table
          class="table table-striped responsive-enabled caption-top"
          id="search-reference"
          data-toggle="table"
          data-search="true"
          data-click-to-select="true"
          data-pagination="true"
          data-id-field="id"
          data-filter-control="true"
          data-locale="${data.locale}"
          data-url="${url}">
        <thead class="thead-light">
          <tr></tr>
        </thead>
        </table>`);
        let fields = [];
        settings.columns.forEach(function(column) {
          let th = $('<th>');
          Object.entries(column).forEach(function([key, value]) {
            if(key != 'title') {
              th.data(key, value);
            } else {
              th.html(value);
            }
          });
          let field = column.field;
          if(!['state', 'id', 'name'].includes(field)) {
            fields.push(field);
          }
          table.find('thead tr').append(th);
        });
        let inputReference = $(this).closest('.form-item').find('input');
        let field_reference = data['field_reference'];
        let dialog = {
          title: data['bsTitle'],
          autoResize: true,
          dialogClass: 'search-dialog',
          width: '90%',
          modal: true,
          height: $(window).height() * 0.8,
          position: {my: "center"},
          buttons: [
            {
              text: "✔ " + Drupal.t('Select'),
              click: function () {
                let $table = $('#search-reference');
                let selected = $table.bootstrapTable('getSelections');
                if (selected[0]) {
                  saveObjectToLocalStorage(selected[0], 'Drupal.entityReferenceModal', field_reference);
                  inputReference.val(selected[0][search_key] + ' (' + selected[0].id + ')');
                  // Emit event referenceModalSelected then another javascript that can catch it.
                  const event = new CustomEvent('referenceModalSelected', {
                    detail: {
                      field: field_reference,
                      value: selected[0]
                    }
                  });
                  document.dispatchEvent(event);
                }
                $(this).dialog("close");
              }
            }
          ],
          close: function (event) {
            $(this).dialog('destroy').remove();
          },
          open: function (event, ui) {
            $(event.target).parent().css('background-color','white');
            let $table = $('#search-reference');
            $table.bootstrapTable('destroy').bootstrapTable();
          }
        };
        let showDialog = table.dialog(dialog);
      });
    }
  }
}(Drupal, jQuery, drupalSettings, once));
