/**
 * @file
 */

(function ($) {
  'use strict';

  Drupal.behaviors.iziApiContentOpeningToday = {

      attach: function(context) {

        // Predefine week.
        var week = {0:'sun', 1:'mon', 2:'tue', 3:'wed', 4:'thu', 5:'fri', 6:'sat'};

        // Get date of the current user/browser.
        var d = new Date();

        // Current the time of the visitor.
        var localTime = d.getTime();

        // Get the timezone offset of the website visitor.
        // Note that a negative return value from getTimezoneOffset() indicates that the current location is ahead of UTC, while a positive value indicates that the location is behind UTC.
        var userOffset = d.getTimezoneOffset() * 60000;

        // Obtain UTC time in msec.
        var utc = localTime + userOffset;

        // Obtain and add destination's UTC time offset
        var offset = $('.openinghours--today').data('gmt');
        var museumTime = utc + (1000*offset);

        // Create human readable date.
        var museumDate = new Date(museumTime);

        // Retrieve numeric representation of the day of the week at museum location.
        var today = museumDate.getDay();

        // Retrieve opening time based on current day.
        var $opening = $('.openinghours--today').data(week[today]);

        if($opening) {
          $('dd', '.openinghours--today').text($opening);
        } else {
          $('dd', '.openinghours--today').text(Drupal.t('Closed'));
        }
      }
  }

})(jQuery);
