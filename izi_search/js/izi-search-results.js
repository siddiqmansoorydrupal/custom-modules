/**
 * @file
 */

(function ($, Drupal, window, document, drupalSettings) {
  'use strict';

  // Use click to see all language options dropdown
  // This is similar but not equal to izi-apicontent-language-select.js
  Drupal.behaviors.iziSearchLanguageSelect = {
    attach: function (context) {
      var $trigger = $('.content-languages-more-wrapper > a');
      if ($trigger) {
        $trigger.once().click(function (e) {
          e.preventDefault ? e.preventDefault() : e.returnValue = false;
          $(this).parent().toggleClass('open');
        });
      }
    }
  };

  Drupal.behaviors.iziSearchLoadMore = {
    attach: function (context, drupalSettings) {
      $('.s-output .btn-show-more')
        .once()
        .each(function () {
          // Load more click event.
          $(this).click( event => {
            // Prevent default.
            event.preventDefault ? event.preventDefault() : event.returnValue = false;

            let $link = $(event.target);
            let $resultsWrapper = $(this).parents('.s-output').find('.s-output-results-wrapper');

            // Search options.
            let searchType = $link.data('search-type');
            let searchString = encodeURIComponent($link.data('search-string'));
            // Filter options.
            let filterType = $link.data('filter-type');
            let filterLanguage = $link.data('filter-lang');
            // Get the search offset, after every load more action this value differs.
            let searchOffset = $link.data('search-offset');

            let url = drupalSettings.path.baseUrl
              + (drupalSettings.path.pathPrefix || '')
              + 'search_load_more/';
            // Get the extra search results.
            let extraSearchresults = searchType + '/'
              + searchString + '/'
              + searchOffset + '/'
              + filterType + '/'
              + filterLanguage;

            url = url + extraSearchresults;

            $link.addClass('show-more-rotate-sign');

            $.get(url, function (data) {
              // Get the new results.
              var $newResults = $(data.results);

              // Hide the new results.
              $newResults.hide().appendTo($resultsWrapper);


              // Fade in the extra results.
              $newResults.animate({opacity: 'show'}, 500);

              // Show and update load more button, with new offset value, if we still have results
              // Otherwise hide the link.
              if (data.load_more === true) {
                $link.data('search-offset', data.offset).removeClass('show-more-rotate-sign');
              }
              else {
                $link.hide();
              }

              // Re-attach behaviors in order to get the new load more link to work as well.
              Drupal.attachBehaviors();
            });
          });
        });
    }
  };

})(this.jQuery, this.Drupal, this, this.document, this.drupalSettings);
