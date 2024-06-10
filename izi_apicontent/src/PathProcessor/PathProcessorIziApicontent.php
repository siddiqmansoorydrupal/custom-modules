<?php

namespace Drupal\izi_apicontent\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PathProcessor processes publisher URLs with content language.
 *
 * Publishers are not translated. They have only one alias. They still must be
 * available in multiple IZI languages. This processor ensures that publisher
 * aliases will extend to accept an extra suffix.
 * Path: `/browse/publishers/138c7d3f-263e-47cb-8047-d34241771772`
 * Alias: `/138c-gosudarstvennyy-muzey-l-n-tolstogo`
 * Extended alias: `/138c-gosudarstvennyy-muzey-l-n-tolstogo/zh`
 */
class PathProcessorIziApicontent implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * The alias manager that caches alias lookups based on the request.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new PathSubscriber instance.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   */
  public function __construct(AliasManagerInterface $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {

    $trimmed_path = trim($path, '/');
    $path_segments = explode('/', $trimmed_path);
    if (sizeof($path_segments) === 2) {
      $alias = "/$path_segments[0]";
      $found_path = $this->aliasManager->getPathByAlias($alias);
      if (strpos($found_path, '/browse/publishers/') === 0) {
        // Change internal path to equals path / language.
        return "$found_path/$path_segments[1]";
      }
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (strpos($path, '/browse/publishers/') === 0) {
      $trimmed_path = trim($path, '/');
      $path_segments = explode('/', $trimmed_path);
      // If path equals language / alias / language.
      if (sizeof($path_segments) === 4) {
        $lang_code = array_pop($path_segments);
        // Check if path exists for.
        $real_path = '/' . implode('/', $path_segments);
        $alias = $this->aliasManager->getAliasByPath($real_path);
        if ($alias) {
          // Change path to equals language / alias.
          return "$alias/$lang_code";
        }
      }
    }
    return $path;
  }

}
