<?php

namespace Drupal\Tests\layout_builder_st\Functional\Rest;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\node\Entity\Node;
use GuzzleHttp\RequestOptions;

/**
 * Tests that override layout sections are not exposed via the REST API.
 *
 * @group layout_builder
 * @group rest
 */
class OverrideSectionsTest extends LayoutRestTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $resourceConfigId = 'entity.node';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // @todo Figure why field definitions have to cleared in
    //   https://www.drupal.org/project/drupal/issues/2985882.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests that the layout override field is not normalized.
   */
  public function testOverrideField() {
    $this->assertCount(1, $this->node->get(OverridesSectionStorage::FIELD_NAME));

    // Make a GET request and ensure override field is not included.
    $response = $this->request(
      'GET',
      Url::fromRoute('rest.entity.node.GET', ['node' => $this->node->id()])
    );
    $expected_cache_tags = [
        'config:filter.format.plain_text',
        'config:rest.resource.entity.node',
        'http_response',
        'node:1',
    ];
    // Drupal 8 has the rest.settings config object, and its cache tags are
    // expected to be present in the response. In Drupal 9, the config does not
    // exist.
    // @todo Remove this when Drupal 9 is the minimum supported version of core.
    $rest_settings = $this->config('rest.settings');
    if (!$rest_settings->isNew()) {
      $expected_cache_tags = Cache::mergeTags($expected_cache_tags, $rest_settings->getCacheTags());
    }
    $this->assertResourceResponse(
      200,
      FALSE,
      $response,
      $expected_cache_tags,
      [
        'languages:language_interface',
        'theme',
        'url.site',
        'user.permissions',
      ],
      FALSE,
      'MISS'
    );
    $get_data = $this->getDecodedContents($response);
    $this->assertSame('A node at rest will stay at rest.', $get_data['title'][0]['value']);
    $this->assertArrayNotHasKey('layout_builder__layout', $get_data);

    // Make a POST request without the override field.
    $new_node = [
      'type' => [
        [
          'target_id' => 'bundle_with_section_field',
        ],
      ],
      'title' => [
        [
          'value' => 'On with the rest of the test.',
        ],
      ],
    ];
    $response = $this->request(
      'POST',
      Url::fromRoute(
        'rest.entity.node.POST'),
      [
        RequestOptions::BODY => $this->serializer->encode($new_node, static::$format),
      ]
    );
    $this->assertResourceResponse(201, FALSE, $response);
    $posted_node = $this->nodeStorage->load(2);
    $this->assertEquals('On with the rest of the test.', $posted_node->getTitle());

    // Make a POST request with override field.
    $new_node['layout_builder__layout'] = [];
    $post_contents = $this->serializer->encode($new_node, static::$format);
    $response = $this->request(
      'POST',
      Url::fromRoute(
        'rest.entity.node.POST'),
      [
        RequestOptions::BODY => $post_contents,
      ]
    );
    $this->assertResourceErrorResponse(403, 'Access denied on creating field \'layout_builder__layout\'.', $response);

    // Make a PATCH request without the override field.
    $patch_data = [
      'title' => [
        [
          'value' => 'New and improved title',
        ],
      ],
      'type' => [
        [
          'target_id' => 'bundle_with_section_field',
        ],
      ],
    ];
    $response = $this->request(
      'PATCH',
      Url::fromRoute(
        'rest.entity.node.PATCH',
        ['node' => 1]
      ),
      [
        RequestOptions::BODY => $this->serializer->encode($patch_data, static::$format),
      ]
    );
    $this->assertResourceResponse(200, FALSE, $response);
    $this->nodeStorage->resetCache([1]);
    $this->node = $this->nodeStorage->load(1);
    $this->assertEquals('New and improved title', $this->node->getTitle());

    // Make a PATCH request with the override field.
    $patch_data['title'][0]['value'] = 'This title will not save.';
    $patch_data['layout_builder__layout'] = [];
    $response = $this->request(
      'PATCH',
      Url::fromRoute(
        'rest.entity.node.PATCH',
        ['node' => 1]
      ),
      [
        RequestOptions::BODY => $this->serializer->encode($patch_data, static::$format),
      ]
    );

    $this->assertResourceErrorResponse(403, 'Access denied on updating field \'layout_builder__layout\'.', $response);
    // Ensure the title has not changed.
    $this->assertEquals('New and improved title', Node::load(1)->getTitle());
  }

}
