/**
 * @file
 */

(function ($, Drupal, window, document, drupalSettings) {
  'use strict';

  const FORM_SELECTOR = '#izi-reviews-form';

  /**
   * Better fade toggle with slide.
   * Thanks to: http://stackoverflow.com/a/734747
   */
  $.fn.fadeThenSlideToggle = function(speed, callback) {
    if (this.is(":hidden")) {
      return this.slideDown(speed).fadeTo(speed, 1, callback);
    } else {
      return this.fadeTo(speed, 0).slideUp(speed, callback);
    }
  };

  Drupal.behaviors.iziReviewsForm = {
    attach: function (context, settings) {
      var $reviewForm = $(FORM_SELECTOR);

      var $submit = $reviewForm.find('input[type="submit"], button[type="submit"]');
      // Review submit effect.
      $submit.once('extra-click').on('click', function(event){
        event.preventDefault ? event.preventDefault() : event.returnValue = false;

        if ($reviewForm.hasClass('disabled')) {
          // Prevent Drupal AJAX submit when disabled.
          event.stopImmediatePropagation();
        }
        else {
          // Add disabled class to the form.
          // $reviewForm.addClass('disabled');

          // Disable the input fields and textarea.
          // $reviewForm.find('input, textarea').prop('readonly', true);
          // $reviewForm.fadeTo(100, 0.5);
        }
      });
    }
  };

  Drupal.behaviors.iziReviewsLoadMore = {
    attach: function (context, settings) {
      $('.reviews__footer button').once().each(function () {
        var $button = $(this);
        var $reviewsList = $button.parents('.reviews').find('.reviews__list');
        // Load more click event.
        $button.click(function (event) {
          // Prevent default.
          event.preventDefault ? event.preventDefault() : event.returnValue = false;
          // Get the search offset, after every load more action this value differs.
          var offset = $button.data('offset');
          var uuid = $button.data('uuid');
          // @todo (legacy) Remove? Depends on new design.
          $button.addClass('show-more-rotate-sign');
          // Get the extra reviews.
          $.get(drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix + 'reviews_load_more/' + offset + '/' + uuid, function (data) {
            // Get the new results.
            var $newResults = $(data.results);
            // Hide and append the new results.
            $newResults.hide().appendTo($reviewsList);
            // Fade in the extra results.
            $newResults.animate({opacity: 'show'}, 500);
            // Update load more button, with new offset value, if we still have results.
            // Otherwise hide the link.
            if (data.load_more_count > 0) {
              // @todo (legacy) Remove class thingy? Depends on new design.
              $button.data('offset', data.offset).removeClass('show-more-rotate-sign');
              var buttonText = Drupal.t('Load @amount more reviews', {'@amount': data.load_more_count});
              $button.html(buttonText);
            }
            else {
              $button.hide();
            }
          });
        });
      });
    }
  };

  // Prepend newly posted review.
  Drupal.AjaxCommands.prototype.iziReviewsPostSuccess = function(ajax, response, status) {
    var $reviewsList = $('.reviews__list');
    var $reviewForm = $(FORM_SELECTOR);

    // Remove previous errors.
    $reviewForm.find('.messages').remove();

    // Check if the view was empty.
    if ($reviewsList.find('li').length == 0) {
      // @todo (legacy) Hide and remove empty message
      //$reviewsList.find('p').fadeThenSlideToggle(200).remove();
    }

    // Create new review with DOMParser. We can't guarantee Drupal will return
    // a single clean element.
    let parser = new DOMParser();
    let doc = parser.parseFromString(response.new_review, 'text/html');
    let newReview = doc.querySelector('body > *');

    if (newReview) {
      Drupal.attachBehaviors(newReview);

      let $newReview = $(newReview);
      $reviewsList.prepend(newReview);
      // Prepend the new review.
      $newReview.hide().addClass('hidden').prependTo($reviewsList);

      // Show new review with effect and remove processing class
      $reviewsList.find('.hidden').fadeThenSlideToggle(200, function(){
        $(this).removeClass('hidden');
      });

      // Increase the counter.
      var $counter = $('.reviews__total .count');
      var newCount = parseInt($counter.text()) + 1;

      $counter.html(newCount);

      $reviewsList.prepend('<div class="notification notification--new">' + Drupal.t('Thanks for writing a review.') + '<a class="notification__close js-close" data-close=".notification">' + Drupal.t('Close notification') + '</a></div>');
    }
  };

  // Handle review post errors.
  Drupal.AjaxCommands.prototype.iziReviewsPostError = function(ajax, response, status) {
    var $reviewForm = $(FORM_SELECTOR);

    // Remove previous errors.
    $reviewForm.find('.messages').remove();

    // Get the (error) messages.
    let parser = new DOMParser();
    let doc = parser.parseFromString(response.messages, 'text/html');
    let messages = doc.querySelector('body > *');

    let $messages = $(messages);
    // Prepend the error to the form.
    $messages.hide().prependTo($reviewForm);

    // Show new comment with effect and remove processing class
    $messages.animate({opacity: 'show'}, 500);
  };

  // Restore comment form after posting comment is completed.
  Drupal.AjaxCommands.prototype.iziReviewsRestoreReviewForm = function(ajax, response, status) {
    var $reviewForm = $(FORM_SELECTOR);

    // If successfully posted the review;
    if (typeof response !== "undefined" && response.success) {
      // Clear the values of the fields.
      $reviewForm.find('#edit-stars, #edit-name, #edit-text').val('');
    }

    // Enable input, trigger blur.
    $reviewForm.find('input, textarea').prop('readonly', false).blur();

    // Remove disabled class to the form.
    $reviewForm.removeClass('disabled');

    // Restore opacity.
    $reviewForm.fadeTo(100, 1);
  };

  Drupal.AjaxCommands.prototype.iziReviewsRemoveReviewForm = function(ajax, response, status) {
    var $reviewForm = $(FORM_SELECTOR);
    $reviewForm.remove();
    // Also remove the review this tour button.
    $('a.reviews__add-button').remove();
  };

})(jQuery, Drupal, this, document, drupalSettings);
