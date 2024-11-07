<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor_abbreviation\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ckeditor5\FunctionalJavascript\CKEditor5TestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\RoleInterface;

/**
 * Test the CKEditor Abbreviation plugin.
 *
 * Inspired by \Drupal\Tests\ckeditor5\FunctionalJavascript\CKEditor5DialogTest.
 *
 * @group ckeditor_abbreviation
 */
class Ckeditor5AbbreviationTest extends CKEditor5TestBase {
  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor_abbreviation',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a filter format, and editor configuration with the abbreviation
    // plugin configured.
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'CKEditor 5 with abbreviation',
      'roles' => [RoleInterface::AUTHENTICATED_ID],
    ])->save();
    Editor::create([
      'format' => 'test_format',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => ['items' => ['sourceEditing', 'abbreviation']],
        'plugins' => ['ckeditor5_sourceEditing' => ['allowed_tags' => []]],
      ],
    ])->save();
  }

  /**
   * Test that enabling the abbreviation plugin adds 'abbr' to the allowed tags.
   */
  public function testAbbreviationPluginAllowsAbbreviationTag(): void {
    $testTitle = 'Allows Abbreviation Tag Test';
    $expectedOutput = '<abbr title="Web Hypertext Application Technology Working Group">WHATWG</abbr>';

    // Edit a page, click CKEditor button for source editing, enter text with an
    // abbreviation, go back to the regular editing mode, ensure the
    // abbreviation button is now active, save, and verify the abbreviation is
    // visible on the saved page.
    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    $this->editSourceSetContent($expectedOutput);

    $this->assertEditorButtonEnabled('Abbreviation');
    $this->assertTrue($this->getEditorButton('Abbreviation')->hasClass('ck-on'));
    $this->assertStringContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput);
  }

  /**
   * Test that we can edit existing abbreviations.
   */
  public function testEditAbbreviation(): void {
    $testTitle = 'Edit Abbreviation';
    $expectedOutput1 = '<abbr title="Math Working Group">MWG</abbr>';
    $expectedOutput2 = '<abbr title="Media Working Group">MWG</abbr>';

    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);
    $this->waitForEditor();
    $this->editSourceSetContent($expectedOutput1);
    $this->assertStringContainsString($expectedOutput1, $this->getEditorDataAsHtmlString());
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput1);

    $node = $this->drupalGetNodeByTitle($testTitle);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->waitForEditor();
    $this->assertStringContainsString($expectedOutput1, $this->getEditorDataAsHtmlString());

    // Change the abbreviation, and make sure it is saved.
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');
    [, $abbrText, $abbrTitle, , $saveButton] = $this->checkAbbreviationBalloon();
    $this->assertStringContainsString('MWG', $abbrText->getValue());
    $abbrTitle->setValue('Media Working Group');
    $saveButton->click();
    $this->assertStringContainsString($expectedOutput2, $this->getEditorDataAsHtmlString());
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been updated.");
    $this->assertSession()->responseContains($expectedOutput2);
  }

  /**
   * Test that we can add an abbreviation by selecting the abbreviation text.
   */
  public function testExistingTextActuallyAdd(): void {
    $testTitle = 'Actually Add Abbreviation To Existing Text';
    $expectedOutput = '<abbr title="Accessible Rich Internet Applications Working Group">ARIAWG</abbr>';

    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    $this->waitForEditor();
    $this->editSourceSetContent('The mission of the ARIAWG is to enhance the accessibility of web content.');

    $this->selectTextInsideEditor('ARIAWG');
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    [, $abbrText, $abbrTitle, , $saveButton] = $this->checkAbbreviationBalloon();
    $this->assertStringContainsString('ARIAWG', $abbrText->getValue());
    $abbrTitle->setValue('Accessible Rich Internet Applications Working Group');
    $saveButton->click();

    // Make sure the editor text contains the abbreviation we just created.
    $this->assertStringContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response contains the abbreviation we
    // just created.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()
      ->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput);
  }

  /**
   * Test that clicking the cancel button works correctly with text selected.
   */
  public function testExistingTextCancelAdd() {
    $testTitle = 'Cancel Adding Abbreviation To Existing Text';
    $expectedOutput = '<abbr title="Authoring Tool Accessibility Guidelines Working Group">ATAGWG</abbr>';

    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    $this->waitForEditor();
    $this->editSourceSetContent('The ATAGWG was chartered to maintain and support the Authoring Tool Accessibility Guidelines.');

    $this->selectTextInsideEditor('ATAGWG');
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    [, $abbrText, $abbrTitle, $cancelButton] = $this->checkAbbreviationBalloon();
    $this->assertStringContainsString('ATAGWG', $abbrText->getValue());
    $abbrTitle->setValue('Authoring Tool Accessibility Guidelines Working Group');
    $cancelButton->click();

    // Make sure the editor text does not contain the abbreviation we just
    // cancelled.
    $this->assertStringNotContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response does not contain the
    // abbreviation we just cancelled.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()
      ->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseNotContains($expectedOutput);
  }

  /**
   * Test that we can add an abbreviation with no title through text selection.
   */
  public function testExistingTextNoTitle(): void {
    $testTitle = 'Add Abbreviation With No Title To Existing Text';
    $expectedOutput = '<abbr>JSON-LD</abbr>';

    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    $this->waitForEditor();
    $this->editSourceSetContent('The mission of the JSON-LD Working Group is to maintain the family of Recommendations and related Working Group Notes.');

    $this->selectTextInsideEditor('JSON-LD');
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    [, $abbrText, $abbrTitle, , $saveButton] = $this->checkAbbreviationBalloon();
    $this->assertStringContainsString('JSON-LD', $abbrText->getValue());
    $abbrTitle->setValue('');
    $saveButton->click();

    // Make sure the editor text contains the abbreviation we just created.
    $this->assertStringContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response contains the abbreviation we
    // just created.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()
      ->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput);
  }

  /**
   * Test that we can add an abbreviation without selecting text.
   */
  public function testNewAbbreviationActuallyAdd(): void {
    $testTitle = 'Actually Add New Abbreviation Test';
    $expectedOutput = '<abbr title="World Wide Web Consortium">W3C</abbr>';

    // Load a node/edit page and set a title.
    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    // Try adding an abbreviation, i.e.: without selecting text first.
    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    // Check the abbreviation balloon, then set abbreviation text and title, and
    // click the balloon's Save button.
    [, $abbrText, $abbrTitle, , $saveButton] = $this->checkAbbreviationBalloon();
    $abbrText->setValue('W3C');
    $abbrTitle->setValue('World Wide Web Consortium');
    $saveButton->click();

    // Make sure the editor text contains the abbreviation we just created.
    $this->assertStringContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response contains the abbreviation we
    // just created.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput);
  }

  /**
   * Test that clicking the cancel button works correctly without text selected.
   */
  public function testNewAbbreviationCancelAdd(): void {
    $testTitle = 'Cancel Add New Abbreviation Test';
    $expectedOutput = '<abbr title="Web Applications Working Group">WAWG</abbr>';

    // Load a node/edit page and set a title.
    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    // Try adding an abbreviation, i.e.: without selecting text first.
    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    // Check the abbreviation balloon, then set abbreviation text and title, and
    // click the balloon's Cancel button.
    [, $abbrText, $abbrTitle, $cancelButton] = $this->checkAbbreviationBalloon();
    $abbrText->setValue('WAWG');
    $abbrTitle->setValue('Web Applications Working Group');
    $cancelButton->click();

    // Make sure the editor text does not contain the abbreviation we just
    // cancelled.
    $this->assertStringNotContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response does not contain the
    // abbreviation we just cancelled.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseNotContains($expectedOutput);
  }

  /**
   * Test that we can add an abbreviation with no title, without selecting text.
   */
  public function testNewAbbreviationNoTitle(): void {
    $testTitle = 'Add New Abbreviation With No Title';
    $expectedOutput = '<abbr>PNG</abbr>';

    $this->drupalGet('node/add');
    $this->getSession()->getPage()
      ->fillField('title[0][value]', $testTitle);

    // Try adding an abbreviation, i.e.: without selecting text first.
    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Abbreviation');
    $this->pressEditorButton('Abbreviation');

    [, $abbrText, $abbrTitle, , $saveButton] = $this->checkAbbreviationBalloon();
    $abbrText->setValue('PNG');
    $abbrTitle->setValue('');
    $saveButton->click();

    // Make sure the editor text contains the abbreviation we just created.
    $this->assertStringContainsString($expectedOutput, $this->getEditorDataAsHtmlString());

    // Save the page and make sure the response contains the abbreviation we
    // just created.
    $this->getSession()->getPage()
      ->pressButton('Save');
    $this->assertSession()->statusMessageContains("page $testTitle has been created.");
    $this->assertSession()->responseContains($expectedOutput);
  }

  /**
   * Check that the abbreviation prompt balloon appears with correct controls.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   An array of five NodeElements in the following order, suitable for Array
   *   Unpacking / Array Destructuring (i.e.: with list() or [...]):
   *   1. the Balloon containing all the controls;
   *   2. the Abbreviation Text field;
   *   3. the Abbreviation Title field;
   *   4. the Balloon's Cancel button; and;
   *   5. the Balloon's Save button.
   */
  protected function checkAbbreviationBalloon(): array {
    $balloon = $this->assertVisibleBalloon('-abbr-form');

    $abbrText = $balloon->findField('Add abbreviation');
    $this->assertNotEmpty($abbrText);

    $abbrTitle = $balloon->findField('Add title');
    $this->assertNotEmpty($abbrTitle);

    $cancelButton = $this->getBalloonButton('Cancel');
    $this->assertNotEmpty($cancelButton);

    $saveButton = $this->getBalloonButton('Save');
    $this->assertNotEmpty($saveButton);

    return [$balloon, $abbrText, $abbrTitle, $cancelButton, $saveButton];
  }

  /**
   * Edit a CKEditor field's source, setting it to the given content.
   *
   * @param string $content
   *   The content to place in the CKEditor field.
   */
  protected function editSourceSetContent(string $content): void {
    $this->waitForEditor();
    $this->assertEditorButtonEnabled('Source');
    $this->pressEditorButton('Source');
    $this->assertEditorButtonDisabled('Abbreviation');
    $sourceTextArea = $this->assertSession()
      ->waitForElement('css', '.ck-source-editing-area textarea');
    $sourceTextArea->setValue($content);
    $this->assertEditorButtonEnabled('Source');
    $this->pressEditorButton('Source');
    $this->assertEditorButtonEnabled('Abbreviation');
  }

  /**
   * Find text inside the CKEditor, and select it.
   *
   * @param string $textToSelect
   *   The text to find and select.
   */
  protected function selectTextInsideEditor(string $textToSelect): void {
    $javascript = <<<JS
(function() {
  const textToSelect = "$textToSelect";
  const el = document.querySelector(".ck-editor__main .ck-editor__editable p");
  const startIndex = el.textContent.indexOf(textToSelect);
  const endIndex = startIndex + textToSelect.length;
  const range = document.createRange();
  const sel = window.getSelection();

  sel.removeAllRanges();
  range.setStart(el.firstChild, startIndex);
  range.setEnd(el.firstChild, endIndex);
  sel.addRange(range);
})();
JS;
    $this->getSession()->evaluateScript($javascript);
  }

}
