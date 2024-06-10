/**
 * Created by jur on 17/09/15.
 * Map function. For Slides & content shown on tour maps  see
 * /web/themes/custom/izi_travel/assets/js/components/tourmap.js
 */
(function ($, Drupal, window, document, drupalSettings) {

  var strokeColor = '#0e5671';

  /**
   * Thanks to http://humaan.com/custom-html-markers-google-maps/.
   */

  function IziMarker(latlng, map, args) {
    this.latlng = latlng;
    this.args = args;
    this.setMap(map);
  }

  IziMarker.prototype = new google.maps.OverlayView();

  IziMarker.prototype.draw = function() {

    var self = this;

    var div = this.div;

    if (!div) {

      div = this.div = document.createElement('div');

      div.className = 'marker';
      div.id = "marker-" + self.args.marker_id;

      div.style.position = 'absolute';
      div.style.cursor = 'pointer';
      div.style.width = '32px';
      div.style.height = '32px';

      if (typeof(self.args.marker_id) !== 'undefined') {
        div.dataset.marker_id = self.args.marker_id;
      }

      // Go to jquery.
      $(div).html("<p>" + this.args.label + "</p>");

      div.addEventListener('click', (evt => {
        if (self.getMap()) {
          self.getMap().panTo(self.latlng);
          var selector = $(".tour__itinerary-link[href='#" + self.args.marker_id + "']");
          selector.click();
        }
      }));

      // Add a click handler on the triggering element.
      $(".tour__itinerary-link[href='#" + self.args.marker_id + "']").click(function(event) {
        // Only do something if the parent is not active.
        if (! $(this).parent('li').hasClass('.tour__itinerary-item--active')) {
          // Resize the map window.
          $('div.tour__map').addClass('overlay__present');
          var map = self.getMap();
          google.maps.event.trigger(map, "resize");
          var activeClass = 'marker-active';
          // Make the marker for the current item active.
          $('div.marker-active').removeClass(activeClass);
          var markerId = self.args.marker_id;
          $("div#marker-" + markerId).addClass(activeClass);

          const uuid = markerId;
          if (Drupal.iziGaEvents) {
            const exhibit = drupalSettings.iziMtgInfoChildren[uuid];
            Drupal.iziGaEvents.analytics.trackOpen(exhibit.language, exhibit.title, exhibit.type, uuid)
          }

          setTimeout(function() {
            // Scroll the map into view when no tabs are present.
            if (self.getMap()) {
              self.getMap().panTo(self.latlng);
            }
          }, 500);

          if ($('div.tour__tabs').css('display') != 'none') {
            window.setTimeout(function() {
              // Only if the target list has display none, the click should be triggered.
              var $tourDetailsItem = $('#' + markerId);
              $('button.tour__tabs-button--list').trigger('click');
            }, 500);
          }
        }
      })

      // React on window resize events.
      var panes = this.getPanes();
      panes.overlayImage.appendChild(div);
    }

    var point = this.getProjection().fromLatLngToDivPixel(this.latlng);

    if (point) {
      div.style.left = (point.x - 16) + 'px';
      div.style.top = (point.y - 16) + 'px';
    }
  };

  IziMarker.prototype.remove = function() {
    if (this.div) {
      this.div.parentNode.removeChild(this.div);
      this.div = null;
    }
  };

  IziMarker.prototype.getPosition = function() {
    return this.latlng;
  };

  Drupal.behaviors.iziMaps = {
    attach: function (context, drupalSettings) {
      // var isDraggable = ! is.mobile();

      var id = drupalSettings.iziMaps.id;

      // Make sure we only render the map once
      var idContainer = $('#'+id);

      // Check if the map container has the processed class
      if(idContainer.hasClass('izi-map-processed')) {
        // If so, return right away
        return true;
      }
      // Make sure that for the next time behaviors are attached, we stop processing
      // so add a class izi-map-processed class
      idContainer.addClass('izi-map-processed');

      // Now render the map once.
      var map = new google.maps.Map(document.getElementById(id), {
        center: {lat: -34.397, lng: 150.644},
        zoom: 8,
        // draggable: isDraggable
      });

      var latlangLeftUp = new google.maps.LatLng(drupalSettings.iziMaps.leftUp[0], drupalSettings.iziMaps.leftUp[1]);
      var latlangRightDown = new google.maps.LatLng(drupalSettings.iziMaps.rightDown[0], drupalSettings.iziMaps.rightDown[1]);
      // Create bounds
      var bounds = new google.maps.LatLngBounds(latlangLeftUp, latlangRightDown);
      map.fitBounds(bounds);

      // Create the path.
      var route = new google.maps.Polyline({
        path: drupalSettings.iziMaps.routeCoordinates,
        strokeColor: strokeColor,
        strokeOpacity: 1.0,
        strokeWeight: 2
      });

      route.setMap(map);

      // Create the markers.
      if (drupalSettings.iziMaps.markers !== null) {
        for (var i=0; i < drupalSettings.iziMaps.markers.length; i++) {
          var label = i + 1;
          addMarker(drupalSettings.iziMaps.markers[i], map, label);
        }
      }

      google.maps.event.addListenerOnce(map, 'idle', function(){
        $(document).trigger('izimap-loaded');
      });

      // Mobile behavior.
      $(document).on('map-toggle', function(event) {
        google.maps.event.trigger(map, "resize");
      });

      $(document).on('map-fit', function(event) {
        map.fitBounds(bounds);
      });

      $(document).on('map-center', function(event) {
        google.maps.event.trigger(map, "resize");
        map.fitBounds(bounds);
        $('.marker-active').click();
      });

      $('.tour__details-close').click(function(event) {
        window.setTimeout(function() {google.maps.event.trigger(map, "resize");}, 400);
      });
    }
  }

  function addMarker(definition, map, label) {
    var myLatlng = new google.maps.LatLng(definition.location.lat,definition.location.lng);
    var overlay = new IziMarker(myLatlng, map, {
      marker_id: definition.id,
      label: label
    });
  }
})(jQuery, Drupal, this, document, drupalSettings);
