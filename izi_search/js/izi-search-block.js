/**
 * @file
 * Create an pool of running ajax get requests.
 */
(function ($, Drupal, window, document, drupalSettings) {
  'use strict';

  Drupal.behaviors.iziSearchAutoComplete = {
    attach: function (context, drupalSettings) {
      var $body = $('body');
      var ajaxCalls = [];
      var autoCompleteTimer;

      // Reset the ajaxCalls stack when all ajax requests are done to prevent memory/speed issues.
      $(document).ajaxStop(function() {
        ajaxCalls = [];
      });

      $('.izi-search-input').once('auto-complete').each(function () {
        var $input = $(this);
        var $form = $input.parents('form');
        var $suggestionsWrapper = $('.suggestions-wrapper', $form);
        var $ulSuggestions = $suggestionsWrapper.find('ul').first();

        // Make sure that we only select suggestions that are direct descendants,
        // because contextual links also contain li-elements
        var $suggestions = $ulSuggestions.children('li');

        // Create placeholder <div> for search suggestions.
        $suggestionsWrapper.prepend('<div class="search-suggestions-wrapper" />').hide();

        // Accessibility and usability.
        $input.attr({'autocomplete':'off', 'aria-autocomplete':'list'});
        $input.data('selected', -1);

        // Respond on all action concerning user input. (Warning: may result in firing this event multiple times).
        $input.on('input change paste keyup mouseup', function (e) {

          // Ignore enter, key up and key down.
          switch (e.keyCode) {
            case 13:
              // enter.
            case 40:
              // Down arrow.
            case 38:
              // Up arrow.
              return false;
          }

          // Get the search term without leading and trailing whitespace (prevents unnecessary searches).
          var values = $input.val().trim();

          // Call Drupal pagecallback to return suggestion for search term.
          // Act only when search term is at least three characters AND the last search isn't the same as the new search (prevent multiple identical calls).
          if (values.length > 2 && $input.data('last-search') != values) {
            // Set timeout for the third character to 0
            var searchTimeout = 0;
            // New searches have a longer timeout to relieve the API.
            if (values.length > 3) {
              searchTimeout = 500;
            }
            autoCompleteTimer = setTimeout(() => {
                var ajaxCallsCount = ajaxCalls.length;

                let path = drupalSettings.path.baseUrl
                  + (drupalSettings.path.pathPrefix || '')
                  + "search_autocomplete/";

                let searchValues = encodeURIComponent(values);

                path = path + searchValues;

                ajaxCalls.push(
                  $.get(path, function (data) {
                    $suggestionsWrapper.html(data);
                    if (data.length > 0) {
                      $suggestionsWrapper.show();

                      var $ulSuggestions = $suggestionsWrapper.find('ul').first();

                      // Make sure that we only select suggestions that are direct descendants,
                      // because contextual links also contain li-elements
                      $suggestions = $ulSuggestions.children('li');

                      $input.data('selected', -1);

                      // Abort older ajax calls.
                      ajaxCalls.forEach(function(call, ajaxCallId) {
                        if (ajaxCallId < ajaxCallsCount) {
                          ajaxCalls[ajaxCallId].abort();
                        }
                      });

                      // On click of a suggestion change the input text to the clicked suggestion.
                      $suggestions.find('a').click(function() {
                        $input.val($(this).text());
                      });

                      // Change the selected item when hovering
                      // This works together with using up and down keys.
                      $suggestions.hover(function() {
                        var $this = $(this);
                        var selected = $input.data('selected');
                        var hovered = $this.index();
                        if (hovered != selected) {
                          var delta = hovered - selected;
                          $input.iziSearchHighlight($suggestions, delta, $input);
                        }
                      });
                    }
                    else {
                      // @todo (legacy) no results feedback
                      $suggestionsWrapper.hide();
                    }
                  })
                );
              }, searchTimeout
            );
          }
          // Show previous suggestions when the last search was the same and the suggestions are hidden.
          else if (values.length > 2 && $input.data('last-search') == values && $suggestionsWrapper.not(':visible')) {
            $suggestionsWrapper.show();
          }
          // Hide the search results when there are not enough search characters.
          else if (values.length < 3) {
            $suggestionsWrapper.hide();
          }
          $input.data('last-search', values);
        });

        $input.keydown(function (e) {
          var $this = $(this);
          switch (e.keyCode) {
            case 13:
              // enter.
              if ($this.data('selected') == -1) {
                // Nothing is selected, so do the default.
                return true;
              }
              $this.iziSearchEnter($suggestions);
              return false;

            case 40:
              // Down arrow.
              $this.iziSearchSelectDown($suggestions, $input);
              return false;

            case 38:
              // Up arrow.
              $this.iziSearchSelectUp($suggestions, $input);
              return false;

            default:
              // All other keys.
              return true;
          }
        });
      });

      // Focus and blur events are triggered on all inputs of the search form.
      $('.izi-search-form input').once('auto-complete-focus-blur').focus(function(){
        $(this).parents('.izi-search-form').addClass('focused');
      }).blur(function() {
        var $this = $(this);

        // Restore input value.
        $this.val($this.data('last-search'));

        $this.parents('.izi-search-form').removeClass('focused');
        // If there was a click, we want to capture that click
        // So wait just a bit with hiding everything.
        setTimeout(function(){
          $('.suggestions-wrapper').hide();
          clearTimeout(autoCompleteTimer);
        }, 250);
      });

    }
  };

  Drupal.behaviors.iziSearchBrowse = {
    attach: function (context, drupalSettings) {
      // Skip if contexct does not contain search block
      if (!context.querySelector('.fotorama-countries-cities-container')) return;
      if($("body").hasClass("page-izi-404")) return;

      const $mainSlides = $('.fotorama-countries-cities-container');
      const $countriesSlide = $mainSlides.find('.menu-slider-one');
      const $citiesSlide = $mainSlides.find('.menu-slider-two');

      const fotoramaDefaults = {
        width: '100%',
        height: '100%',
        maxheight: '370px',
        nav: false,
        arrows: 'always',
        swipe: false,
        transitionduration: 500
      };

      // Main fotorama settings (inherit defaults)
      // We need to also show arrows because the nested fotorama's won't show the arrows otherwise
      // The main fotorama arrows are hidden in CSS.
      let fotoramaMainSettings = $.extend({}, fotoramaDefaults);
      // Disable click region navigation.
      fotoramaMainSettings.click = false;

      // Main fotorama, has two slides, one for countries, one for cities.
      $mainSlides.fotorama(fotoramaMainSettings);
      let mainFotorama = $mainSlides.data('fotorama');

      // Countries fotorama.
      $countriesSlide.fotorama(fotoramaMainSettings);


      // $countriesSlide.on('click', '.slide a', (function(e) {
      $countriesSlide.on("click", (e) => {

        if (!e.target.matches('.slide a')) return;

        // Prevent default.
        e.preventDefault ? e.preventDefault() : e.defaultPrevented = false;

        // Get the clicked country.
        var country = e.target.getAttribute('data-country');

        // Get cities container after fotorama is initialized.
        const $mainSlides = $('.fotorama-countries-cities-container');
        const $citiesSlide = $mainSlides.find('.menu-slider-two');
        const $citiesContainer = $citiesSlide.find('.fotorama-cities-container');
        const citiesFotorama = $citiesContainer.data('fotorama');
        const $citiesPoule = $('.browse-cities-per-country-hidden');
        if (citiesFotorama) {
          citiesFotorama.destroy();
        }

        // Get the html list of cities of the selected country.
        var citiesListHtml = $citiesPoule.children("[data-country='" + country + "']").html();

        // Replace the cities container html.
        $citiesContainer.html(citiesListHtml);
        // Initialize new cities fotorama with new content.
        $citiesContainer.fotorama(fotoramaMainSettings);

        // Finally show the second slide with cities.
        mainFotorama.show(1);

      });

      // When clicking on 'all countries' show slide one.
      $citiesSlide.on('click', '.all_countries', (function(e) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        mainFotorama.show(0);
      }));

    }
  };

  Drupal.behaviors.iziSearchFilters = {
    attach: function (context, drupalSettings) {
      var $searchBlock = $('.search-block');
      var $toggleLink = $('.show_filters', $searchBlock);
      var $chooseLocContainer = $('.fotorama-countries-cities-container').parent();
      var $filters = $('.base-filters');
      var $filterShowLink = $('.text', $filters);
      var $filterLinks = $('a', $filters);
      var $body = $('body');

      $toggleLink.once().click(function(e) {
        var $this = $(this);

        // Needed height of location container when visible.
        var height = 394;

        // Get current link text.
        var linkTextCurrent = $this.text();

        // Get new link text.
        var linkTextNew = $this.data('text-toggle');

        // Store current link text for next toggle.
        $this.data('text-toggle', linkTextCurrent);

        // Change link text and show again.
        $this.animate({opacity: 'hide'}, 0).text(linkTextNew).animate({opacity: 'show'}, 500);

        // If locations container is visible before clicking we need to set the new height to zero and show the filters.
        if ($chooseLocContainer.height() > 0) {
          height = 0;
          $filters.animate({opacity: 'show'}, 500)
        }
        else {
          $filters.animate({opacity: 'hide'}, 500)
        }

        $chooseLocContainer.animate({height: height}, 500);
      });

      $filterShowLink.once().click(function(e) {
          e.stopPropagation();

          $filterShowLink.next().fadeOut(250);

          var $select = $(this).next();

          if ($select.is(':visible')) {
            $select.fadeOut(250);
          }
          else {
            $select.fadeIn(250);
          }
        }
      );

      // Change label text to clicked filter.
      $filterLinks.once().click(function(e) {
        var $this = $(this);
        $this.parent().prev().text($this.text());
      });

      // Can't use once() on document, do it old fashioned way.
      if (!$body.hasClass('hide-filter-click-processed')) {
        // Hide filter selection options when clicking outside the selection div.
        $(document).click(function(e) {
          $filterShowLink.next().fadeOut(250);
        });
      }
      $body.addClass('hide-filter-click-processed');

    }
  };

  $.fn.iziSearchSelectDown = function($suggestions, $input) {
    var selected = this.data('selected');
    if ($suggestions.length > (selected + 1)) {
      this.iziSearchHighlight($suggestions, 1, $input);
    }
    return this;
  };

  $.fn.iziSearchSelectUp = function($suggestions, $input) {
    var selected = this.data('selected');
    if (selected !== -1) {
      this.iziSearchHighlight($suggestions, -1, $input);
    }
    return this;
  };

  /**
   * Follow a suggestion link.
   */
  $.fn.iziSearchEnter = function($suggestions) {
    var selected = this.data('selected');
    window.location.href = $suggestions.eq(selected).find('a').filter(function(){
      return !($(this).parent().parent().hasClass('contextual-links'));
    }).attr('href');
    return this;
  };

  /**
   * Highlights a suggestion.
   */
  $.fn.iziSearchHighlight = function($suggestions, delta, $input) {
    var selected = this.data('selected');
    var next = selected + delta;
    $suggestions.eq(selected).removeClass('selected');
    this.data('selected', next);

    if (next !== -1) {
      var $next = $suggestions.eq(next);
      $next.addClass('selected');
    }

    // Set input text to selection if it is a search suggestion or restore otherwise.
    if (next < $suggestions.length - 1 && next > -1) {
      $input.val($next.text());
    }
    else {
      $input.val($input.data('last-search'));
    }
    return this;
  };

}(this.jQuery, this.Drupal, this, this.document, this.drupalSettings));
// Define the 'fotoramaVersion' variable. Otherwise the first line in the
// fotorama.js library causes an error. An upstream issue will be created.
var fotoramaVersion = '';
