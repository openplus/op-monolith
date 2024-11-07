<?php

namespace Drupal\insert_view_adv\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\insert_view_adv\Plugin\Filter\InsertView;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class AdvancedInsertViewController contains ajax view render.
 *
 * @package Drupal\insert_view_adv\Controller
 */
class AdvancedInsertViewController extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ViewAjaxController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Loads and renders a view via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\filter\Entity\FilterFormat|null $filter_format
   *   The filter format object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The view response as ajax response.
   */
  public function ajaxView(Request $request, FilterFormat $filter_format = NULL) {
    $name = $request->query->get('view_name');
    $display_id = $request->query->get('view_display_id');
    if (isset($name) && isset($display_id)) {
      $args = $request->query->get('view_args');
      $args = isset($args) && $args !== '' ? explode('/', $args) : [];

      // Arguments can be empty, make sure they are passed on as NULL so that
      // argument validation is not triggered.
      $args = array_map(function ($arg) {
        return ($arg == '' ? NULL : $arg);
      }, $args);
      if ($args) {
        // Transform the arguments back to string.
        $args = implode('/', $args);
      }
      $context = new RenderContext();
      $configuration = $filter_format->filters('insert_view_adv')->getConfiguration();
      $configuration = Json::encode($configuration);
      $preview = $this->renderer->executeInRenderContext($context, function () use ($name, $display_id, $args, $configuration) {
        return InsertView::build($name, $display_id, $args, $configuration);
      });
      if (!$context->isEmpty() && !empty($preview)) {
        $bubbleable_metadata = $context->pop();
        BubbleableMetadata::createFromRenderArray($preview)
          ->merge($bubbleable_metadata)
          ->applyTo($preview);
      }
      $html = $this->renderer->renderPlain($preview);
      return (new Response($html, 200))
        // Do not allow any intermediary to cache the response, only the end
        // user.
        ->setPrivate()
        // Allow the end user to cache it for up to 5 minutes.
        ->setMaxAge(300);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
