(function ($, Drupal) {
    Drupal.behaviors.iziCredit = {
      attach: function (context, settings) {
        $('.use-ajax').once('iziCredit').each(function () {
          $(this).on('click', function (event) {
            event.preventDefault();
            Drupal.ajax({url: $(this).attr('href')}).execute();
          });
        });
      }
    };
  })(jQuery, Drupal);
  