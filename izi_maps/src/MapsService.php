<?php

namespace Drupal\izi_maps;

/**
 * Service description.
 */
class MapsService {

  /**
   * Method description.
   */
  public function get_map_render_array($map_definition = []) {

    // CAUTION!!!! This id should be tourMap! Otherwise tabs won't work!
    $id = 'tourMap';

    $bounds = explode(',', $map_definition['bounds']);

    // Create the object structure to draw a polyline based on the route.
    if (!empty($map_definition['route'])) {
      $route_points = explode(';', $map_definition['route']);
      $route_coordinates = [];
      foreach ($route_points as $point) {
        $stdClass = new \stdClass();
        $point = explode(',', $point);
        $stdClass->lat = floatval($point[0]);
        $stdClass->lng = floatval($point[1]);
        $route_coordinates[] = $stdClass;
      }
    }

    // Create the markers.
    $markers = [];
    foreach ($map_definition['markers'] as $marker) {
      $markers[] = [
        'location' => (object) $marker['location'],
        'id' => $marker['id'],
      ];
    }

    // Create the settings.
    $settings = [
      'id' => $id,
      'leftUp' => [
        $bounds[0],
        $bounds[1],
      ],
      'rightDown' => [
        $bounds[2],
        $bounds[3],
      ],
      'routeCoordinates' => (isset($route_coordinates)) ? $route_coordinates : '',
      'markers' => $markers,
    ];

    $output = [
      '#theme' => 'izi_maps_tourmap',
      '#id' => $id,
    ];
    $output['#attached']['library'][] = "izi_maps/izi_maps";
    $output['#attached']['drupalSettings']['iziMaps'] = $settings;
    return $output;
  }

}
