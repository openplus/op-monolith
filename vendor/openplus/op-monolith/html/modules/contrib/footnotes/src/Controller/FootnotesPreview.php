<?php

namespace Drupal\footnotes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the preview for embedded content.
 */
class FootnotesPreview extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(Renderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Controller callback that renders the preview for CKeditor.
   */
  public function preview(Request $request, Editor $editor) {
    $text = $request->query->get('text');
    $text = str_replace(['<p>', '</p>'], '', $text);
    $value = $request->query->get('value');
    $build = [
      'markup' => [
        '#type' => 'markup',
        '#markup' => ($value ?: '[#]') . '<span class="footnote-content-ckeditor-preview">' . $text . '</span>',
      ],
    ];

    return new Response($this->renderer->renderRoot($build));
  }

  /**
   * Access callback for viewing the preview.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultReasonInterface
   *   The access result.
   */
  public function checkAccess(Editor $editor, AccountProxy $account) {
    return AccessResult::allowedIfHasPermission($account, 'use text format ' . $editor->getFilterFormat()->id());
  }

}
