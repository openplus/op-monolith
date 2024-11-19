CSVFile Formatter

INTRODUCTION
------------

A Field formatter that renders CSV files uploaded to a Drupal File
field as a themable HTML table.

 * For a full description of the module, see the project page:
   https://www.drupal.org/project/csvfile_formatter

 * To submit bug reports, feature suggestions, or track changes:
   https://www.drupal.org/project/issues/csvfile_formatter

REQUIREMENTS
------------

This module requires the following modules:

 * File field

INSTALLATION
------------

 * Install as you would normally install contributed Drupal modules.

CONFIGURATION
-------------

Create a Drupal File field on a content type.

On the "Manage Display" tab for the content type, select
"CSV File as Table" as the Format for the created File field.

Any properly formatted CSV file uploaded through the created
File field will be processed to generate an HTML table.

This field formatter includes options to provide a download
link for the original File, plus options to process the rows
and fields in the CSV file, and provide CSS classes for
components of the generated HTML table.
