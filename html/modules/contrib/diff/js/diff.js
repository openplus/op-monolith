(function ($, Drupal, drupalSettings, once) {

  'use strict';

  Drupal.behaviors.diffRevisions = {
    attach: function (context, settings) {
      // drupalSettings in not anymore bound to attached functions.
      // It is available outside the scope of this anonymous function also.
      var rows = once('diff-revisions', 'table.diff-revisions tbody tr');
      var $rows = $(rows);
      if ($rows.length === 0) {
        return;
      }

      function updateDiffRadios() {
        var newTd = false;
        var oldTd = false;
        if (!$rows.length) {
          return true;
        }
        $rows.each(function () {
          var $row = $(this);
          var $inputs = $row.find('input[type="radio"]');
          var $oldRadio = $inputs.filter('[name="radios_left"]').eq(0);
          var $newRadio = $inputs.filter('[name="radios_right"]').eq(0);
          if (!$oldRadio.length || !$newRadio.length) {
            return true;
          }
          if ($oldRadio.prop('checked')) {
            oldTd = true;
            $oldRadio.parent().css('visibility', 'visible');
            $newRadio.parent().css('visibility', 'hidden');
          }
          else if ($newRadio.prop('checked')) {
            newTd = true;
            $oldRadio.parent().css('visibility', 'hidden');
            $newRadio.parent().css('visibility', 'visible');
          }
          else {
            if (drupalSettings.diffRevisionRadios === 'linear') {
              if (newTd && oldTd) {
                $oldRadio.parent().css('visibility', 'visible');
                $newRadio.parent().css('visibility', 'hidden');
              }
              else if (newTd) {
                $newRadio.parent().css('visibility', 'visible');
                $oldRadio.parent().css('visibility', 'visible');
              }
              else {
                $newRadio.parent().css('visibility', 'visible');
                $oldRadio.parent().css('visibility', 'hidden');
              }
            }
            else {
              $newRadio.parent().css('visibility', 'visible');
              $oldRadio.parent().css('visibility', 'visible');
            }
          }
        });
        return true;
      }

      if (drupalSettings.diffRevisionRadios) {
        $rows.find('input[name="radios_left"], input[name="radios_right"]').click(updateDiffRadios);
        updateDiffRadios();
      }
    }
  };

})(jQuery, Drupal, drupalSettings, once);
