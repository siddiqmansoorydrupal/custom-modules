/**
 * @file
 * ToDo: Resolve with mtg-new. Both are brought over from D7
 */

(function ($) {
  'use strict';

  Drupal.behaviors.iziApicontentMtg = {
    attach: function (context, settings) {
      // Get the child elements.
      var $list = $('.mtg-children-wrapper');
      var $listItems = $('.mtg-child', '.mtg-children');

      // If there's no list, the rest is pointless.
      if ($listItems.length == 0) {
        return;
      }

      // Initialize some variables.
      var $window = $(window);
      var viewportHeight;
      var listWidth = 0;
      var itemWidth = $listItems.outerWidth(true);
      var itemHeight = $listItems.outerHeight(true);
      var numCols = 0;
      var resizeTimeOut;

      // Get all description texts (inc children).
      var $fullText = $('.full-text');

      var expansionPlaceholderClass = 'mtg-child-expansion';
      var brandModifier = 'default';
      if ($('body').hasClass('body-modifier--brand')) {
        brandModifier = 'brand';
      }

      var expansionPlaceholder = '<li class="' + expansionPlaceholderClass + ' drop-down-block--' + brandModifier + '-dynamic" style="float: none; clear: both;"></li>';

      var readmore = function($that) {
        if ($that.prop('scrollHeight') > $that.height()) {
          $that.addClass('read-more');

          // Show full text on click on text.
          $that.once().click(function(event) {
            var $this = $(this);

            if ($this.css('overflow') == 'hidden') {
              if ($this.hasClass('open')) {
                $this.removeClass('open');
              }
              else {
                $this.addClass('open');
              }
            }
          });
        }
      };

      // Check if the shown texts are overflowing and need read more.
      $fullText.each(function() {
        readmore($(this));
      });

      var resize = function() {
        itemWidth = $listItems.outerWidth(true);
        listWidth = $list.width();
        var newNumCols = Math.floor(listWidth / itemWidth);

        // Only if the number of columns changed, we need to react.
        if (numCols != newNumCols) {
          // Set the new standard.
          numCols = newNumCols;

          // Remove the existing placeholders.
          var $activeItem = closeMtgExpansions();
          $('.' + expansionPlaceholderClass).remove();

          var i = 0;
          $listItems.each(function() {
            i++;
            var $listItem = $(this);
            if (i == numCols) {
              // Insert after every nth item, where n equals the number of cols.
              $(this).after(expansionPlaceholder);
              // Reset counter.
              i = 0;
            }
          });

          // Also, insert a placeholder after the last incomplete row.
          if (i != numCols) {
            $listItems.last().after(expansionPlaceholder);
          }

          // Finally, re-open the active item if there was one.
          if ($activeItem.length > 0) {
            openMtgExpansion($activeItem);
          }
        }

        // Recalculate viewport height, needed elsewhere.
        viewportHeight = $window.height();
      };

      // Insert / move placeholders on resize and on page load.
      // Use a timeout to prevent excessive calculations.
      $window.resize(function() {
        clearTimeout(resizeTimeOut);
        resizeTimeOut = setTimeout(resize, 50);
      });

      resize();

      // Handle click events on the list items and expansion buttons.
      $list
        .on('click', '.mtg-child', function(event) {
          // Do not follow the link.
          event.preventDefault();

          // Open expansion if the list item contains expansion content.
          if ($('.mtg-child-full', this).length > 0) {
            var $this = $(this);

            if ($this.data('hash').replace('#', '') == $.param.fragment()) {
              openMtgExpansion($this);
            }
            else {
              // Open the clicked expansion by changing the url fragment.
              $.bbq.pushState($this.data('hash'));
            }
          }
        })
        .on('click', '.' + expansionPlaceholderClass + ' .close', function(event) {
          closeMtgExpansions();
        })
        .on('click', '.' + expansionPlaceholderClass + ' .next-ex', function(event) {
          var $next = $('.mtg-child.active').nextAll('.mtg-child:first');
          if ($next.length == 0) {
            $next = $('.mtg-child:first');
          }
          $.bbq.pushState($next.data('hash'));
        })
        .on('click', '.' + expansionPlaceholderClass + ' .prev-ex', function(event) {
          var $prev = $('.mtg-child.active').prevAll('.mtg-child:first');
          if ($prev.length == 0) {
            $prev = $('.mtg-child:last');
          }
          $.bbq.pushState($prev.data('hash'));
        });

      $window.bind('hashchange', function(e) {
        var url = $.param.fragment();
        var $listItem = $('[data-hash="#' + url + '"]');
        if ($listItem.length > 0) {
          closeMtgExpansions();
          openMtgExpansion($listItem);
        }
      }).trigger('hashchange');

      /**
       * Opens an expansion for a given list item.
       *
       * @param $listItem
       *   The list item that contains the child content to be opened.
       */
      function openMtgExpansion($listItem) {

        listWidth = $list.width();
        var paddingHorizontal = (listWidth - itemWidth * numCols) / 2;
        var $expansion = $listItem.nextAll('.mtg-child-expansion:first');
        var $expansionContent = $('.mtg-child-full', $listItem);

        // Replace all service links default classes.
        var $socialTwitter = $('a.service-links-twitter-widget-lazy', $expansionContent);
        $socialTwitter.once('lazySocial').addClass('service-links-twitter-widget twitter-share-button');
        if (typeof(twttr) == 'undefined') {
          $.getScript(window.location.protocol + '//platform.twitter.com/widgets.js');
        }
        else {
          twttr.widgets.load($socialTwitter[0]);
        }
        var $socialFacebook = $('a.service-links-facebook-like-lazy', $expansionContent);
        $socialFacebook.once('lazySocial').addClass('service-links-facebook-like');
        if ($.isFunction(Drupal.behaviors.ws_fl.attach)) {
          Drupal.behaviors.ws_fl.attach($socialFacebook.parent(), Drupal.settings);
        }

        // Set the left and right negative margin to fill the screen horizontally.
        $expansion.css({'margin': '0px -' + paddingHorizontal + 'px', 'width' : listWidth + 'px'});

        $expansionContent.appendTo($expansion).show();

        // Restart the jPlayer if it was playing. This is needed because
        // moving the expansionContent around stops the player.
        if ($expansionContent.find('.jp-state-playing').length > 0) {
          $expansionContent.find('.jp-play').click();
        }

        // Add read more functionality when needed.
        readmore($expansion.find('.full-text'));

        // Add left and right padding to keep the items centered.
        $list.css('padding', '0px ' + paddingHorizontal + 'px');

        $listItem.addClass('active');
        scrollExpansion($expansion);

        // Trigger a fotorama ready to resize the gallery.
        var $gallery = $expansion.find('.fotorama');
        $gallery.trigger('fotorama:ready', [$gallery]);

      }

      /**
       * Clears all expansions by placing the content back in its list item.
       *
       * @returns {{}}
       *   Returns the list item of the closed expansion; normally there should
       *   only be one item opened at any moment.
       */
      function closeMtgExpansions() {
        var $listItem = {};
        var $expansions = $('.' + expansionPlaceholderClass);
        $('.mtg-child-full', $expansions).each(function() {
          var $expansionContent = $(this);
          $listItem = $('.mtg-child.active').removeClass('active');
          $expansionContent.hide().appendTo($listItem);
        });
        $list.css('padding', '0px');
        return $listItem;
      }

      /**
       * Scrolling logic, adapted from previous site.
       *
       * @param $expansion
       *
       * @returns {*}
       */
      function scrollExpansion($expansion) {
        var expansionHeight, offset;
        expansionHeight = $expansion.height();
        switch (true) {
          case (viewportHeight < expansionHeight):
            offset = -20;
            break;

          // Next 3 lines disabled; I don't know which problem they should solve, but I do know that they *caused* problems.
          // case (viewportHeight < (expansionHeight + itemHeight + itemHeight * 0.25)):
          //  offset = -((viewportHeight - expansionHeight) * 0.75);
          //  break;
          default:
            offset = -(itemHeight + 30);
        }
        return $window.scrollTo($expansion, {
          duration: 500,
          offset: offset
        });
      }

    }
  };

})(jQuery);
