(function ($, Drupal, drupalSettings) {
  'use strict';

  // Fixed next button issues on tour pages.
  Drupal.behaviors.mapTourBehavior = {
    attach: function (context, drupalSettings) {
      $(".js-itinerary-button.controller-first-next").click(function() {
        $(".tour__itinerary .tour__itinerary-item[data-ta-index='1'] a").click();
      });
    }
  };

  Drupal.behaviors.iziApiContentJplayer = {
    attach: function(context, drupalSettings) {

      var $jplayers = $('.jp-jplayer', context);
      // The current player holds the currently active player.
      var $currentPlayer;
      $.each($jplayers, function() {
        let media_url = $(this).data('url');
        let cssSelectorAncestor = "#jp_container_"+$(this).data('uuid');

        // Point to the slide controllers.
        var myControl = {
          progress: $(this).next().find('.jp-progress-slider')
        };

        // Initialize jPlayer
        var myPlayer = $(this).jPlayer({
          ready: function () {
            $(this).jPlayer("setMedia", {
              m4a: media_url
            });
          },
          swfPath: drupalSettings.jplayerSwfPath,
          supplied: "m4a",
          cssSelectorAncestor: cssSelectorAncestor,
          timeupdate: function(event) {
            myControl.progress.slider("value", event.jPlayer.status.currentPercentAbsolute);
          },
          play: function() { // To avoid both jPlayers playing together.
            // Other items already being paused.
            // $(this).jPlayer("pauseOthers");
            Drupal.iziGaEvents.analytics.trackPlayFull($(this));
          },
          // cssSelectorAncestor: $(this).next().attr('id'),//'#'+$(this).next().attr('id'),
          useStateClassSkin: true,
          autoBlur: false,
          smoothPlayBar: true,
          keyEnabled: true,
          remainingDuration: true,
          toggleDuration: true,
          preload: 'metadata',
        });

        var myPlayerData = myPlayer.data("jPlayer");

        // Create the progress slider control functionality.
        myControl.progress.slider({
            animate: "fast",
            max: 100,
            range: "min",
            step: 0.1,
            value: 0,
            slide: function(event, ui) {
              var sp = myPlayerData.status.seekPercent;
              if(sp > 0) {
                // Move the play-head to the value and factor in the seek percent.
                myPlayer.jPlayer("playHead", ui.value * (100 / sp));
              } else {
                // Create a timeout to reset this slider to zero.
                setTimeout(function() {
                  myControl.progress.slider("value", 0);
                }, 0);
              }
            }
          }
        );
      });

      $('div.slideout__next, div.slideout__close').click(function(event) {
        if (typeof $currentPlayer != 'undefined' && typeof $currentPlayer.jPlayer != 'undefined') {
          $currentPlayer.jPlayer('pause');
        }
      });

      // Attach a listener to play audio-tour to open the first card en trigger the play button.
      $('a.button--icon-controller-play', context).click(function(event) {
        event.preventDefault();

        var $firstToggle = $('.card__toggle').first();

        $firstToggle.trigger('click');

        var $parent = $firstToggle.parent();
        // Find the player and send a play message.
        var $player = $parent.find('.jp-jplayer').first();
        $player.jPlayer('play');
        $currentPlayer = $player;

      });
    }
  }

})(jQuery, Drupal, drupalSettings);

/**
 * Fixed fist previous click issue on tour page.
 */
function tourDetailsFirstFunction(e) {
  var indexId = jQuery(e).parent().parent().attr("data-index");
  if (indexId == "1") {
    jQuery('.tour__itinerary-item a[href="#tour_details_first"]').click();
  }
}
