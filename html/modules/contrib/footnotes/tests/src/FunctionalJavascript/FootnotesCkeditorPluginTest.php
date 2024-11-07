<?php

namespace Drupal\Tests\footnotes\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\footnotes\Traits\FootnotesTestHelperTrait;

/**
 * Contains Footnotes CKEditor plugin functionality tests.
 *
 * @group footnotes
 */
class FootnotesCkeditorPluginTest extends WebDriverTestBase {

  use StringTranslationTrait;
  use CKEditor5TestTrait;
  use FootnotesTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'filter',
    'ckeditor5_test',
    'footnotes',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpFormatAndEditor();
    $this->setUpNodeTypeAndField();
    $this->setUpDefaultUserWithAccess();
  }

  /**
   * Tests CKEditor5 plugin dialog loads.
   */
  public function testUiLoads() {
    $session = $this->getSession();
    $assert_session = $this->assertSession();

    $this->drupalGet("node/add/page");
    $page = $session->getPage();

    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Footnotes');
    $this->pressEditorButton('Footnotes');

    // Open the dialog and check it contains the form
    // fields and nested CK Editor.
    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $this->assertNotNull($dialog_div, 'Footnotes dialog was opened');
    $assert_session->elementTextContains('css', '.ui-dialog', $this->t('Footnote content'));
    $assert_session->elementTextContains('css', '.ui-dialog', $this->t('Footnote value'));
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.ui-dialog .ck'),
      'CK Editor 5 loaded within the dialog form'
    );
    $page->find('css', 'button.ui-dialog-titlebar-close')->click();
  }

  /**
   * Tests CKEditor5 plugin dialog loads.
   */
  public function testBasicFootnoteCreation() {
    $assert_session = $this->assertSession();

    $this->drupalGet("node/add/page");

    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Footnotes');
    $this->pressEditorButton('Footnotes');

    // Open the dialog and check it contains the form
    // fields and nested CK Editor.
    $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $content_area = $assert_session->waitForElementVisible('css', 'div.ui-dialog .ck-editor__editable');
    $content_area->click();
    $content_area->setValue('Lorem ipsum dolor sit amet.');
    $this->assertNotEmpty($content_area->getText());
    $this->click('.ui-dialog-buttonset .form-submit');
    $this->assertSession()->waitForElementRemoved('css', 'div.ui-dialog');
    $this->assertSession()->waitForElementVisible('css', '.ck .footnotes-preview');
    $this->assertSession()->elementTextContains('css', '.ck .footnotes-preview', 'Lorem ipsum dolor sit amet.');
    $this->assertSession()->elementTextContains('css', '.ck .footnotes-preview', '[#]');

    // Ensure that the main ck editor area now contains the footnote preview.
    $content_area = $assert_session->waitForElementVisible('css', '.field--name-body .ck-editor__editable');
    $this->assertNotEmpty($content_area->getText());
    $this->htmlOutput($content_area->getText());
    $this->assertSession()->elementTextContains('css', '.footnote-content-ckeditor-preview', 'Lorem ipsum dolor sit amet.');
    $this->assertSession()->elementTextContains('css', '.footnotes-preview', '[#]');

    // Test double-click to change the text.
    $this->getSession()->getDriver()->doubleClick("//*[contains(@class, 'ck')]//span[contains(@class, 'footnotes-preview')]");
    $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $content_area = $assert_session->waitForElementVisible('css', 'div.ui-dialog .ck-editor__editable');
    $content_area->click();
    $content_area->setValue('Added text.');
    $this->assertNotEmpty($content_area->getText());
    $this->click('.ui-dialog-buttonset .form-submit');
    $this->assertSession()->waitForElementRemoved('css', 'div.ui-dialog');
    $this->assertSession()->waitForElementVisible('css', '.ck .footnotes-preview');
    $this->assertSession()->elementTextContains('css', '.ck .footnotes-preview', 'Lorem ipsum dolor sit amet.Added text');
    $this->assertSession()->elementTextContains('css', '.ck .footnotes-preview', '[#]');
  }

  /**
   * Test paste from various sources.
   */
  public function testPaste() {
    $this->assertSession();
    $this->drupalGet("node/add/page");
    $this->waitForEditor();

    // Arrays of the expected resulting converted footnotes keyed by the html
    // filename.
    $htmlFileTestPaste = [
      'libre-office-1.html' => [
        '<span class="ck-widget" contenteditable="false"><span class="footnotes-preview" data-footnotes-preview="ready">[#]<span class="footnote-content-ckeditor-preview">Footnote - 1 - Test</span></span></span>',
        '<span class="ck-widget" contenteditable="false"><span class="footnotes-preview" data-footnotes-preview="ready">[#]<span class="footnote-content-ckeditor-preview">Footnote - 2 Test</span></span></span>',
      ],
      'word-1.html' => [
        '<span class="ck-widget" contenteditable="false"><span class="footnotes-preview" data-footnotes-preview="ready">[#]<span class="footnote-content-ckeditor-preview">Footnote - 1 - Test from Word</span></span></span>',
        '<span class="ck-widget" contenteditable="false"><span class="footnotes-preview" data-footnotes-preview="ready">[#]<span class="footnote-content-ckeditor-preview">Footnote - 2 Test from Word</span></span></span>',
      ],
      'word-2.html' => [
        '<span class="ck-widget" contenteditable="false"><span class="footnotes-preview" data-footnotes-preview="ready">[#]<span class="footnote-content-ckeditor-preview"><span lang="EN-GB"></span><span lang="EN-GB">See United Nations, Guidelines for gender-sensitive language <span>, </span></span><span lang="EN-GB"><a href="https://www.un.org/en/gender-inclusive-language/guidelines.shtml"><span>https://www.un.org/en/gender-inclusive-language/guidelines.shtml</span> </a></span><span lang="EN-GB">.</span></span></span></span>',
      ],
    ];

    foreach ($htmlFileTestPaste as $fileName => $expectedFootnotes) {
      $htmlContent = file_get_contents(__DIR__ . '/../../paste-assets/' . $fileName);
      $encodedHtmlContent = base64_encode($htmlContent);

      // Execute JavaScript to paste HTML content into the CKEditor field.
      $this->getSession()->executeScript("
        const editorElement = document.querySelector('.ck-editor__editable');

        // Reset the existing data to empty the editor.
        editorElement.ckeditorInstance.setData('');

        // Mimic a clipboard paste event.
        const clipboardEvent = new ClipboardEvent('paste', {
          bubbles: true,
          cancelable: true,
          clipboardData: new DataTransfer()
        });
        clipboardEvent.clipboardData.setData('text/html', atob('" . $encodedHtmlContent . "'));
        editorElement.dispatchEvent(clipboardEvent);
      ");

      // Verify the content is correctly pasted.
      $this->assertSession()->waitForField('body[0][value]', 1000);
      $page = $this->getSession()->getPage();

      $ckeditorContent = $page->find('css', '.ck-editor__editable')->getHtml();
      $ckeditorContent = preg_replace('/\s+/', ' ', trim($ckeditorContent));
      $ckeditorContent = mb_convert_encoding($ckeditorContent, 'UTF-8', 'UTF-8');
      foreach ($expectedFootnotes as $expectedFootnote) {
        $expectedFootnote = preg_replace('/\s+/', ' ', trim($expectedFootnote));
        $expectedFootnote = mb_convert_encoding($expectedFootnote, 'UTF-8', 'UTF-8');
        $this->assertStringContainsString($expectedFootnote, $ckeditorContent, 'Missing from filename: ' . $fileName);
      }
    }
  }

}
