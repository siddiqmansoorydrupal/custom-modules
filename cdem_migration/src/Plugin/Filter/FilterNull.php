<?php

namespace Drupal\cdem_migration\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides PHP code filter. Use with care.
 *
 * @Filter(
 *   id = "filter_null",
 *   module = "cdem_migration",
 *   title = @Translation("Filter null?"),
 *   description = @Translation("Fix migration missing filter_null error."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterNull extends FilterBase
{

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode)
  {
    $result = new FilterProcessResult($text);
    return $result;
  }
}
