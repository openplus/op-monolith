<?php

namespace Drupal\Tests\taxonomy_term_depth\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Getting calculating dynamically the depth of the term.
 *
 * @group taxonomy_term_depth
 */
class DynamicDepthCalculationTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['taxonomy', 'taxonomy_term_depth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Creates and ensures that a feed is unique, checks source, and deletes feed.
   */
  public function testCalculateDepth() {
    $voc = Vocabulary::create([
      'vid' => 'testvoc',
      'name' => 'testvoc',
      'description' => 'testvoc',
    ]);

    $voc->save();

    /** @var \Drupal\taxonomy\TermInterface $term1 */
    $term1 = Term::create([
      'vid' => $voc->id(),
      'name' => 'Depth 1 term',
    ]);

    $term1->save();

    /** @var \Drupal\taxonomy\TermInterface $term2 */
    $term2 = Term::create([
      'vid' => $voc->id(),
      'name' => 'Depth 2 term',
    ]);

    $term2->parent->set(0, $term1->id());
    $term2->save();

    /** @var \Drupal\taxonomy\TermInterface $term3 */
    $term3 = Term::create([
      'vid' => $voc->id(),
      'name' => 'Depth 2 term',
    ]);

    $term3->parent->set(0, $term3->id());
    $term3->parent->set(1, $term2->id());
    $term3->save();

    $this->assertEquals(taxonomy_term_depth_get_by_tid($term1->id()), 1, 'Depth of first term');
    $this->assertEquals(taxonomy_term_depth_get_by_tid($term2->id()), 2, 'Depth of second term');
    $this->assertEquals(taxonomy_term_depth_get_by_tid($term3->id()), 3, 'Depth of third term');

    $this->assertEquals($term1->depth_level->first() ? $term1->depth_level->first()->value : NULL, 1, 'Saved depth of first term');
    $this->assertEquals($term2->depth_level->first() ? $term2->depth_level->first()->value : NULL, 2, 'Saved depth of second term');
    $this->assertEquals($term3->depth_level->first() ? $term3->depth_level->first()->value : NULL, 3, 'Saved depth of third term');

    $chain = taxonomy_term_depth_get_full_chain($term2->id());
    $compare = [
      $term1->id(),
      $term2->id(),
      $term3->id(),
    ];

    $this->assertTrue($chain === $compare, 'Testing fullchain for term2');

    $chain = taxonomy_term_depth_get_full_chain($term2->id(), TRUE);
    $this->assertTrue($chain === array_reverse($compare), 'Testing reversed fullchain for term2');

    $this->assertEquals(\Drupal::database()
      ->query('SELECT depth_level FROM {taxonomy_term_field_data} WHERE tid=:tid', [':tid' => $term1->id()])
      ->fetchField(), 1, 'DB depth_level field of first term');
    $this->assertEquals(\Drupal::database()
      ->query('SELECT depth_level FROM {taxonomy_term_field_data} WHERE tid=:tid', [':tid' => $term2->id()])
      ->fetchField(), 2, 'DB depth_level field of second term');
    $this->assertEquals(\Drupal::database()
      ->query('SELECT depth_level FROM {taxonomy_term_field_data} WHERE tid=:tid', [':tid' => $term3->id()])
      ->fetchField(), 3, 'DB depth_level field of third term');
  }

  /**
   * Test cron queue.
   */
  public function testCronQueue() {
    $this->assertTrue(TRUE, 'Clearing all depths and running cron to update, then checking again');
    taxonomy_term_depth_queue_manager()->clearDepths();
    $this->_cronRun();
    $this->testCalculateDepth();
  }

  /**
   *
   */
  protected function _cronRun() {
    $this->drupalGet('cron/' . \Drupal::state()->get('system.cron_key'));
  }

}
