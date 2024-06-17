<?php

namespace Drupal\Tests\taxonomy\Functional\Views;

use Drupal\Core\Url;
use Drupal\Tests\taxonomy\Functional\TaxonomyTranslationTestTrait;

/**
 * Tests for views translation.
 *
 * @group taxonomy
 */
class TermTranslationViewsTest extends TaxonomyTestBase {

  use TaxonomyTranslationTestTrait;

  /**
   * Term to translated term mapping.
   *
   * @var array
   */
  protected $termTranslationMap = [
    'one' => 'translatedOne',
    'two' => 'translatedTwo',
    'three' => 'translatedThree',
  ];

  /**
   * Created terms.
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'language', 'content_translation', 'views'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['taxonomy_translated_term_name_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Language object.
   *
   * @var \Drupal\Core\Language\LanguageInterface|null
   */
  protected $translationLanguage;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    $this->setupLanguages();
    $this->enableTranslation();
    $this->setUpTerms();
    $this->translationLanguage = \Drupal::languageManager()->getLanguage($this->translateToLangcode);
  }

  /**
   * Ensure that proper translation is returned when contextual filter
   * "Content: Has taxonomy term ID (with depth)" is enabled.
   */
  public function testTermsTranslationWithContextualFilter() {
    $this->drupalLogin($this->rootUser);
    foreach ($this->terms as $term) {
      // Generate base language url and send request.
      $url = Url::fromRoute('view.taxonomy_translated_term_name_test.page_1', ['arg_0' => $term->id()])->toString();
      $this->drupalGet($url);
      $this->assertSession()->pageTextContains($term->label());

      // Generate translation URL and send request.
      $url = Url::fromRoute('view.taxonomy_translated_term_name_test.page_1', ['arg_0' => $term->id()], ['language' => $this->translationLanguage])->toString();
      $this->drupalGet($url);
      $this->assertSession()->pageTextContains($this->termTranslationMap[$term->label()]);
    }
  }

  /**
   * Setup translated terms in a hierarchy.
   */
  protected function setUpTerms() {
    $parent_vid = 0;
    foreach ($this->termTranslationMap as $name => $translation) {

      $term = $this->createTerm([
        'name' => $name,
        'langcode' => $this->baseLangcode,
        'parent' => $parent_vid,
        'vid' => $this->vocabulary->id(),
      ]);

      $term->addTranslation($this->translateToLangcode, [
        'name' => $translation,
      ]);
      $term->save();

      // Each term is nested under the last.
      $parent_vid = $term->id();

      $this->terms[] = $term;
    }
  }

}
