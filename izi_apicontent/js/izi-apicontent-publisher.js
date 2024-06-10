/**
 * @file
 */
(function ($, Drupal, window, document, drupalSettings) {
  'use strict';

  /**
   * Much code in this file is an is an exact copy from izi-search-results.js.
   *
   * @todo (legacy) combine the JS, remove the duplication.
   */

  // Use click to see all language options dropdown
  // This is similar but not equal to izi-apicontent-language-select.js
  Drupal.behaviors.iziSearchLanguageSelect = {
    attach: function (context, drupalSettings) {
      var $trigger = $('.content-languages-more-wrapper > a');
      if ($trigger) {
        $trigger.once().click(function(e) {
          e.preventDefault ? e.preventDefault() : e.returnValue = false;
          $(this).parent().toggleClass('open');
        });
      }
    }
  };

  Drupal.behaviors.iziApicontentPublisherLoadMore = {
    attach: function (context, drupalSettings) {
      $("a[data-role='s-output-show-more']").once().each(function () {
        var $link = $(this);
        var $resultsWrapper = $(this).parents('.s-output').find('.s-output-results-wrapper');

        var publisherId = $link.data('publisher-id');
        var filterLanguage = $link.data('filter-language');

        // Load more click event.
        $link.click(function (event) {
          // Prevent default.
          event.preventDefault ? event.preventDefault() : event.returnValue = false;
          $link.addClass('show-more-rotate-sign');

          // Get the offset, after every load more action this value differs.
          var offset = $link.data('offset');

          // Get the extra publisher results.
          var url = drupalSettings.path.baseUrl +
            drupalSettings.path.pathPrefix +
            'publisher_load_more/' +
            publisherId +
            '/' +
            offset +
            '?' +
            'lang=' + filterLanguage;

          $.get(url, function (data) {
            var $newResults = $(data.results);

            // Hide the new results.
            $newResults.hide().appendTo($resultsWrapper);
            // $resultsWrapper.html($newResults);
            // Fade in the extra results.
            $newResults.animate({opacity: 'show'}, 500);

            // Show and update load more button, with new offset value, if we still have results
            // Otherwise hide the link.
            if (data.load_more == true) {
              $link.data('offset', data.offset).removeClass('show-more-rotate-sign');
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

  Drupal.behaviors.iziApicontentPublisherInformation = {
    attach: function (context, drupalSettings) {
      if (Modernizr.csstransitions) {
        $('body').addClass('brand-head-transition');
      }
      $('.brand-head-information-link').click(function() {
        $('body').addClass('brand-head-opened');
      });
      $('.brand-head-information-description-close').click(function() {
        $('body').removeClass('brand-head-opened');
      });
    }
  };

})(jQuery, this.Drupal, this, document, this.drupalSettings);
