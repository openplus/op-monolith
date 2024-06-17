<?php

namespace Drupal\Tests\toc_api\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\toc_api\TocBuilderInterface;
use Drupal\toc_api\TocManager;
use Drupal\toc_api\TocManagerInterface;

/**
 * Test description.
 *
 * @group toc_api
 */
class TocBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toc_api'];

  /**
   * @var \Drupal\toc_api\TocManagerInterface
   */
  protected TocManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('toc_type');
    $this->installConfig(['toc_api']);
    $this->manager = new TocManager($this->container->get('config.factory'));
  }

  /**
   * Tests that ToC doesn't produce duplicated IDs.
   *
   * @todo Convert to unit test and include in TocTest, once that is fixed.
   */
  public function testDuplicateIds() {
    $toc_source = <<<HTML
    <p><a name="documents"></a></p>
    <h2>Documents</h2>
    <p>
      Tests that ToC API doesn't get confused by the deprecated "name" attribute.
      It was present in the XHTML 1.0, see https://www.w3.org/TR/xhtml1/#h-4.10 ,
      but removed from XHTML 1.1 , see the list of included modules for it
      here https://www.w3.org/TR/xhtml11/doctype.html , and deprecated
      "Name Identification Module" description here
      https://www.w3.org/TR/xhtml-modularization/abstract_modules.html .
    </p>

    <p><a id="documents-heading-3"></a></p>
    <h3>Documents heading 3</h3>

    <h4>Documents heading 4</h4>
    <p>It is important that existing ID comes after the heading here.</p>
    <p><a id="documents-heading-4"></a></p>
    HTML;
    $toc_options = [
      'header_min' => '2',
      'header_max' => '4',
      'header_id' => 'title',
    ];

    $toc = $this->manager->create('toc_manager_test', $toc_source, $toc_options);
    $dom = Html::load($toc->getContent());

    // Test that ToC API doesn't get confused by existing deprecated "name"
    // attribute.
    $h2 = $dom->getElementsByTagName('h2')->item(0);
    $this->assertNotEquals('documents' , $h2->getAttribute('id'), 'H2 have unique ID');

    $h3 = $dom->getElementsByTagName('h3')->item(0);
    $this->assertNotEquals('documents-heading-3' , $h3->getAttribute('id'), 'H3 have unique ID');

    $h4 = $dom->getElementsByTagName('h4')->item(0);
    $this->assertNotEquals('documents-heading-4' , $h4->getAttribute('id'), 'H4 have unique ID');
  }

}
