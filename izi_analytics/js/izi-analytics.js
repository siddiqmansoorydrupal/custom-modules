/**
 * IZI Analytics - provides functions to track users actions in google anaylytics GA4 and 
 *                 collects some statistics on the izi.travel website
 */
function getTimestampInSeconds () {
  return Math.floor(Date.now() / 1000)
}

function dbg() {
  let hostname=window.location.hostname
  if (hostname == "izi.travel") {
    return false
  }
  else {
    return true
  }
}

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(';');
  for(let i = 0; i <ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}

function setCookie(cname, cvalue, exdays) {
  const d = new Date();
  d.setTime(d.getTime() + (exdays*24*60*60*1000));
  let expires = "expires="+ d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

(function ($, Drupal) {

  Drupal.iziGaEvents = {

    // Store players already played
    playersPlayed: [],
    sendEvent : (eventCategory, eventAction, params) => {
      if (gtag) {
        const eventParams = {
          event_label: eventCategory,
          event_name: eventAction,
          ...params
        };
        gtag('event', eventAction, eventParams);
        // console.log('sendEvent', eventAction, eventParams)
      }
      else {
        if (dbg()) {
          console.log(`No gtag sending event ${eventName}`)
        }
      }
    },
    analyticsTracker : {
      delay_ms: 200,
      logConsole: function() {
        // Uncomment for development
        if (dbg()) {
          console.log.apply(console, arguments);
        }
      },
      init: function() {
        this.external_links_init();
        this.mobile_apps_links_init();
      },
      external_links_init: function() {
        $("a[data-role='external_link']").on('click', function(e) {
          var link, url;
          link = $(e.target);
          url = link.attr('href');
          Drupal.iziGaEvents.analyticsTracker.logConsole('send', 'event', 'Outbound Traffic', 'CPoutgoing', url);
          // ga('send', 'event', 'Outbound Traffic', 'CPoutgoing', url);
          Drupal.iziGaEvents.sendEvent( 'CPoutgoing', 'Outbound Traffic', {
            value: url
          });
        });
      },
      mobile_apps_links_init: function() {
        $("a[href^='https://itunes.apple.com/app']").on('click', (function(e) {
          var link, url;
          link = $(e.currentTarget);
          url = link.attr('href');
          Drupal.iziGaEvents.analyticsTracker.logConsole('send', 'event', 'Auto', 'Outside', 'AppStore');
          // ga('send', 'event', 'Auto', 'Outside', 'AppStore');
          Drupal.iziGaEvents.sendEvent('Auto', 'Outside', {value: 'AppStore'});

          Drupal.iziGaEvents.analyticsTracker.logConsole('send', 'pageview', '/virtual/Gotostore');
          // ga('send', 'pageview', '/virtual/Gotostore');
          Drupal.iziGaEvents.sendEvent('pageview', '/virtual/Gotostore', {});

          setTimeout((function() {
            window.location.href = url;
          }), Drupal.iziGaEvents.analyticsTracker.delay_ms);
        }));

        $("a[href^='https://play.google.com/store']").on('click', (function(e) {
          var link, url;
          link = $(e.currentTarget);
          url = link.attr('href');

          Drupal.iziGaEvents.sendEvent('Auto', 'Outside', {value: 'Google Play Store'});
          Drupal.iziGaEvents.sendEvent('pageview', '/virtual/Gotostore', {});

          setTimeout((function() {
            window.location.href = url;
          }), Drupal.iziGaEvents.analyticsTracker.delay_ms);
        }));

        $("a[href^='http://www.windowsphone.com/']").on('click', (function(e) {
          var link, url;
          link = $(e.currentTarget);
          url = link.attr('href');
          Drupal.iziGaEvents.sendEvent('Auto', 'Outside', {value: 'Windows Phone Store'});

          Drupal.iziGaEvents.sendEvent('pageview', '/virtual/Gotostore', {});

          setTimeout((function() {
            window.location.href = url;
          }), Drupal.iziGaEvents.analyticsTracker.delay_ms);
        }));
      }
    },
    analytics : {
      initMtg: function (language, title, type, uuid) {
        this.trackOpen(language, title, type, uuid);
      },
      trackEvent: function (category, action, label) {
        Drupal.iziGaEvents.analyticsTracker.logConsole('track event: ' + action);
        Drupal.iziGaEvents.sendEvent(category, action, {value: label});
      },

      trackOpen: function (language, title, type, uuid) {
        Drupal.iziGaEvents.analyticsTracker.logConsole('track Open');

        let tIzi = getCookie("tIzi");
        if (tIzi == "") {

          tIzi = self.crypto.randomUUID();

          if (tIzi != "" && tIzi != null) {
            setCookie("tIzi", tIzi, 365);
          }
          else {
            tIzi = "Unknown"
          }
        }

        let url = "/stats/" + tIzi + "/Open/" + uuid + "/" + language
        fetch(url, {
          method: "GET"
        })
        
        var dt = getTimestampInSeconds();
        Drupal.iziGaEvents.sendEvent('IZIDirectory', 'Open', {
          type: type,
          dimension1: title,
          dimension2: uuid,
          dimension3: language,
          dimension4: 'Web',
          dimension5: 'nonrental',
          dimension12: dt
        });
      },
      trackPlay: function (language, title, type, uuid) {
        Drupal.iziGaEvents.analyticsTracker.logConsole('track Play');

        let tIzi = getCookie("tIzi");
        if (tIzi == "") {

          tIzi = self.crypto.randomUUID();

          if (tIzi != "" && tIzi != null) {
            setCookie("tIzi", tIzi, 365);
          }
          else {
            tIzi = "Unknown"
          }
        }
        
        let url = "/stats/" + tIzi + "/Play/" + uuid + "/" + language
        fetch(url, {
          method: "GET"
        })

        var dt = getTimestampInSeconds();
        Drupal.iziGaEvents.sendEvent('IZIDirectory', 'Play', {
          type: type,
          dimension1: title,
          dimension2: uuid,
          dimension3: language,
          dimension4: 'Web',
          dimension5: 'nonrental',
          dimension6: 'Audio',
          dimension7: 'Web',
          dimension8: 'Unknown',
          dimension12: dt
        });
      },

      // Manually trigger play events
      trackPlayFull: ($player) => {

        const playerId = $player[0] ? $player[0].id : $player.id;
        if (!Drupal.iziGaEvents.playersPlayed[playerId]) {

          $this = $player;
            var iziMtgInfo = drupalSettings?.iziMtgInfo;
            var language = iziMtgInfo?.language || 'undefined';
            var title = iziMtgInfo?.title || 'undefined';
            var type = iziMtgInfo?.type || 'undefined';
            var uuid = iziMtgInfo?.uuid || 'undefined';

            switch (type) {
              case 'museum':
                var $child = $this.parents('.slideout');
                break;
              case 'collection':
                var $child = $this.parents('.slideout');
                break;
              case 'tour':
                var $child = $this.parents('.tour__details-item');
                break;
              case 'exhibit':
                var $child = $this.parents('.slideout');
                break;
              default:
                var $child = $this.parents('.mtg-child-full');
                break;
            }

            var languagePlay = language;
            var titlePlay = title;
            var typePlay = type;
            var uuidPlay = uuid;

            // Check if this item is a child.
            if ($child.length > 0 && drupalSettings.iziMtgInfoChildren != undefined) {
              // Get child uuid (the MTG object with the media played).
              var uuidChildPlay = $child.data('uuid');
              if (!uuidChildPlay || uuidChildPlay.length === 0) {
                var uuidChildPlay = $child.data('iziuuid');
              }

              if (uuidChildPlay != undefined) {
                // Get child info.
                var iziMtgInfoChild = drupalSettings.iziMtgInfoChildren[uuidChildPlay];

                // Overwrite values for the child.
                languagePlay = iziMtgInfoChild.language;
                titlePlay = iziMtgInfoChild.title;
                typePlay = iziMtgInfoChild.type;
                uuidPlay = uuidChildPlay;
              }
            }

            // Fire track play event.
            Drupal.iziGaEvents.analytics.trackPlay(languagePlay, titlePlay, typePlay, uuidPlay)
            Drupal.iziGaEvents.playersPlayed[playerId] = 1;

          }
      }
    }
  };

  Drupal.behaviors.iziGaEvents = {
    attach: function (context, settings) {
      // Initialize MTG trackers.
      $context = $(context);
      if (settings.iziMtgInfo != undefined) {
        var iziMtgInfo = settings.iziMtgInfo;

        var language = iziMtgInfo.language;
        var title = iziMtgInfo.title;
        var type = iziMtgInfo.type;
        var uuid = iziMtgInfo.uuid;

        if (!Drupal.iziGaEvents.itemsOpened) {
          Drupal.iziGaEvents.itemsOpened = [];
        }

        if (Drupal.iziGaEvents.itemsOpened.indexOf(uuid) > -1) {
          return;
        }

        Drupal.iziGaEvents.itemsOpened.push(uuid);

        // Fire Event for the MTG Object page load.
        Drupal.iziGaEvents.analytics.initMtg(language, title, type, uuid);

        // Fire events for playing media.
        if (typeof $.jPlayer !== "undefined") {
          const jPlayers = $context.find(".jp-jplayer");
          // console.log(`Register analytics for (analytics.js)`);
          // console.log(jPlayers);

          jPlayers.bind($.jPlayer.event.play, function(event) {
            // Add a listener to report the time play began.
            var $this = $(this);

            // Make sure we only fire once, and not after resuming play.
            if (!$this.hasClass('played-earlier')) {
              switch (type) {
                case 'museum': var $child = $this.parents('.slideout'); break;
                case 'collection': var $child = $this.parents('.slideout'); break;
                case 'tour': var $child = $this.parents('.tour__details-item'); break;
                default: var $child = $this.parents('.mtg-child-full'); break;
              }

              var languagePlay = language;
              var titlePlay = title;
              var typePlay = type;
              var uuidPlay = uuid;

              // Check if this item is a child.
              if ($child.length > 0 && settings.iziMtgInfoChildren != undefined) {
                // Get child uuid (the MTG object with the media played).
                var uuidChildPlay = $child.data('uuid');
                if (!uuidChildPlay || uuidChildPlay.length === 0) {
                  var uuidChildPlay = $child.data('iziuuid');
                }

                if (uuidChildPlay != undefined) {
                  // Get child info.
                  var iziMtgInfoChild = settings.iziMtgInfoChildren[uuidChildPlay];

                  // Overwrite values for the child.
                  languagePlay = iziMtgInfoChild.language;
                  titlePlay = iziMtgInfoChild.title;
                  typePlay = iziMtgInfoChild.type;
                  uuidPlay = uuid + '.' + uuidChildPlay;
                }
              }

              // Fire track play event.
              Drupal.iziGaEvents.analytics.trackPlay(languagePlay, titlePlay, typePlay, uuidPlay)
              $this.addClass('played-earlier');
            }
          });
      }
        else {
          console.log('no jPlayer')
        }
      }

      // Initialize other events.
      Drupal.iziGaEvents.analyticsTracker.init();
    }
  };

})(jQuery, Drupal)
