<?php

namespace Drupal\izi_search\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class IziSearchController extends BaseController {

  /**
   * Page title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A Drupal render array.
   *
   * @throws \Exception
   */
  public function getTitle() {
    return $this->t('Explore');
  }

  /**
   * Page callback for the search page.
   *
   * @Route("/search", methods="GET", name="izi_search.search")
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   *
   * @return mixed[]
   *   A Drupal render array.
   *
   * @throws \Exception
   */
  public function build(Request $request) {
    // Build search block.
    $search_block = $this->izi_search_block_view('izi_search_browse');
    // Build featured content.
    $featured = $this->izi_search_block_view('featured_content_search_page');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['page-search']],
      'search' => $search_block['content'],
      'featured' => $featured['content'],
    ];
  }

  /**
   *
   */
  public function buildSearchResults($search, $filter_type = 'all', $filter_lang = 'all') {
    return $this->izi_search_page_results_view("search", $search, $filter_type, $filter_lang);
  }

}
