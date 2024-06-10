/**
 * @file
 */
(function ($) {
  'use strict';

  Drupal.behaviors.iziApicontentLanguageSelect = {
    attach: function (context, settings) {
      var $languageSelector = $('.restyled_lang_dropdown, .featured-main-item-content-languages', context);
      var $trigger = $('.language-select, .featured-main-item-content-languages-button', $languageSelector);
      var $dropdown = $('.f-dropdown', $languageSelector);
      $dropdown.hide();
      if ($trigger) {
        $trigger.once().click(function($e) {
          $e.preventDefault();
          $dropdown.toggle().toggleClass('open');
          $trigger.toggleClass('open');
        });
      }
    }
  };

})(jQuery);
