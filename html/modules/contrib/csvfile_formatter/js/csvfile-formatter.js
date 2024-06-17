/**
 * @file
 * CSV File Formatter behaviors.
 */

 (function ($, Drupal) {

  'use strict';

  /**
   * Adds DataTable to tables with the class '.add-externaljs-csvfiletable'.
   */
  Drupal.behaviors.csvfileFormatter = {
    attach: function (context, settings) {
      var dataTableSettings = drupalSettings.csvFormatter.dataTable;

      var elements = once('csvfiletable-once', jQuery('.add-externaljs-csvfiletable'), context);
      elements.forEach(element => {
        var dataTableSetting = Object.assign({}, dataTableSettings[0]);
        var numColumns = jQuery(element).find('th').length;

        var scrollXMin = dataTableSetting['scrollX'];
        if (scrollXMin >= 0 && numColumns >= scrollXMin) {
          dataTableSetting['scrollX'] = true;
        }
        else {
          dataTableSetting['scrollX'] = false;
        }

        if (dataTableSetting['scrollY'] < 0) {
          delete dataTableSetting.scrollY;
        }

        jQuery(element).DataTable(dataTableSetting);
      })
    }
  };

} (jQuery, Drupal));
