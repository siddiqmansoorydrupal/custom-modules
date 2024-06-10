/**
 * @file
 */
(function ($) {
  'use strict';

  Drupal.behaviors.iziApiontentLanguageSelectNew = {
    attach: function (context, settings) {
      var $languageSelector = $('.language-select.selectbox', context);

      $languageSelector.change(function(e) {
        // Load page in a different language.
        window.location.href = $(this).val();
      });
    }
  };

})(jQuery);
