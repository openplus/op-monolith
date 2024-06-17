<?php

namespace Drupal\Tests\comment\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\RoleInterface;

/**
 * Tests comment block functionality.
 *
 * @group comment
 */
class CommentBlockTest extends CommentTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'views', 'block_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Update admin user to have the 'administer blocks' permission.
    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer comments',
      'skip comment approval',
      'post comments',
      'access comments',
      'access content',
      'administer blocks',
    ]);
  }

  /**
   * Tests the recent comments block.
   */
  public function testRecentCommentBlock() {
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('views_block:comments_recent-block_1');

    // Add some test comments, with and without subjects. Because the 10 newest
    // comments should be shown by the block, we create 11 to test that behavior
    // below.
    $timestamp = REQUEST_TIME;
    for ($i = 0; $i < 11; ++$i) {
      $subject = ($i % 2) ? $this->randomMachineName() : '';
      $comments[$i] = $this->postComment($this->node, $this->randomMachineName(), $subject);
      $comments[$i]->created->value = $timestamp--;
      $comments[$i]->save();
    }

    // Test that a user without the 'access comments' permission cannot see the
    // block.
    $this->drupalLogout();
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['access comments']);
    $this->drupalGet('');
    $this->assertSession()->pageTextNotContains('Recent comments');
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access comments']);

    // Test that a user with the 'access comments' permission can see the
    // block.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('');
    $this->assertSession()->pageTextContains('Recent comments');

    // Test the only the 10 latest comments are shown and in the proper order.
    $this->assertSession()->pageTextNotContains($comments[10]->getSubject());
    for ($i = 0; $i < 10; $i++) {
      $this->assertSession()->pageTextContains($comments[$i]->getSubject());
      if ($i > 1) {
        $previous_position = $position;
        $position = strpos($this->getSession()->getPage()->getContent(), $comments[$i]->getSubject());
        $this->assertGreaterThan($previous_position, $position, sprintf('Comment %d does not appear after comment %d', 10 - $i, 11 - $i));
      }
      $position = strpos($this->getSession()->getPage()->getContent(), $comments[$i]->getSubject());
    }

    // Test that links to comments work when comments are across pages.
    $this->setCommentsPerPage(1);

    for ($i = 0; $i < 10; $i++) {
      $this->clickLink($comments[$i]->getSubject());
      $this->assertSession()->pageTextContains($comments[$i]->getSubject());
      $this->assertSession()->responseContains('<link rel="canonical"');
    }
  }

  /**
   * Test to ensure that correct destination exists for comment action links.
   */
  public function testCommentDestination() {
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => FALSE,
    ]);
    $bundle->save();
    $block_content = BlockContent::create([
      'type' => 'basic',
      'label' => 'Some block title',
      'info' => 'Test block',
    ]);
    $block_content->save();

    // Create comment field on block_content.
    $this->addDefaultCommentField('block_content', 'basic', 'block_comment', CommentItemInterface::OPEN, 'block_comment');
    $this->drupalPlaceBlock("block_content:{$block_content->uuid()}", [
      'region' => 'content',
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => FALSE,
          'pages' => '/user/*',
        ],
      ],
    ]);
    // Add a comment.
    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = Comment::create([
      'entity_id' => $block_content->id(),
      'entity_type' => 'block_content',
      'field_name' => 'block_comment',
      'uid' => $this->rootUser->id(),
      'status' => CommentInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'comment_body' => [LanguageInterface::LANGCODE_NOT_SPECIFIED => [$this->randomMachineName()]],
    ]);
    $comment->save();

    $this->drupalLogin($this->rootUser);
    $this->drupalGet("user/{$this->rootUser->id()}");
    // Test to ensure that destination parameter exist.
    $this->assertSession()->linkByHrefExists("comment/{$comment->id()}/delete?destination=/user/{$this->rootUser->id()}");
    $this->assertSession()->linkByHrefExists("comment/{$comment->id()}/edit?destination=/user/{$this->rootUser->id()}");
    $this->assertSession()->linkByHrefExists("comment/reply/block_content/{$block_content->id()}/block_comment/{$comment->id()}?destination=/user/{$this->rootUser->id()}");
  }

}
