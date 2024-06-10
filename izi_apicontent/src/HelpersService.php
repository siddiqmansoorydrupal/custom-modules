<?php

namespace Drupal\izi_apicontent;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 *
 */
class HelpersService {

  /**
   * Get the server protocol.
   *
   * @return string
   */
  public function _izi_apicontent_get_server_protocol() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
      return "https";
    }
    return "http";
  }

  /**
   * Generate QR code image.
   */
  public function generateQRCode($url, $size = 200, $filename = NULL) {
    $url = preg_match("#^https?\:\/\/#", $url) ? $url : "http://{$url}";
    $googleChartAPI = 'https://chart.googleapis.com/chart';
    return [
      '#theme' => 'image',
      '#uri' => "{$googleChartAPI}?chs={$size}x{$size}&chld=L|0&cht=qr&chl={$url}&choe=UTF-8",
      '#alt' => 'qr code',
      '#title' => 'Link to Google.com',
    ];
  }

  /**
   * Helper function to parse url from youtube to be able to render on
   * fancybox modal window.
   *
   * @param null $videoString
   *   String with the original youtube url.
   *
   * @return array|bool
   *   Array with parsed url or FALSE if not a valid url.
   */
  public function _izi_apicontent_parse_youtube_url($videoString = NULL) {
    if (!empty($videoString)) {
      // Split on line breaks.
      $videoString = stripslashes(trim($videoString));
      $videoString = explode("\n", $videoString);
      $videoString = array_filter($videoString, 'trim');

      // Check each video for proper formatting.
      foreach ($videoString as $video) {
        // If we have a URL, parse it down.
        if (!empty($video)) {
          // Initial values.
          $video_id = NULL;
          $videoIdRegex = NULL;
          $results = [];
          // Check for type of youtube link.
          if (strpos($video, 'youtu') !== FALSE) {
            if (strpos($video, 'youtube.com') !== FALSE) {
              // Works on:
              // http://www.youtube.com/embed/VIDEOID
              // http://www.youtube.com/embed/VIDEOID?modestbranding=1&amp;rel=0
              // http://www.youtube.com/v/VIDEO-ID?fs=1&amp;hl=en_US
              $videoIdRegex = '/youtube.com\/(?:embed|v){1}\/([a-zA-Z0-9_-]+)\??/i';

              // Works on:
              // http://www.youtube.com/watch?v=VIDEOID
              if (strpos($video, 'youtube.com/watch') !== FALSE) {
                $videoIdRegex = '/youtube.*watch\?v=([a-zA-Z0-9\-_]+)/';
              }
            }
            elseif (strpos($video, 'youtu.be') !== FALSE) {
              // Works on:
              // http://youtu.be/daro6K6mym8
              $videoIdRegex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
            }

            if ($videoIdRegex !== NULL) {
              if (preg_match($videoIdRegex, $video, $results)) {
                $video_str = 'https://www.youtube.com/embed/%s';
                $video_id = $results[1];
              }
            }
          }
          // Check if we have a video id, if so, add the video metadata.
          if (!empty($video_id)) {
            // Add to return.
            $videos[] = [
              'url' => sprintf($video_str, $video_id),
            ];
          }
        }
      }
    }

    // Return array of parsed videos.
    return (!empty($videos)) ? $videos : FALSE;
  }

  /**
   * Prepares a potentially unsafe html attribute for output.
   *
   * @param $attribute
   *   Attribute string. Example HTML title attribute.
   *
   * @return string
   *   Escaped and converted string.
   */
  public function _izi_apicontent_prepare_html_attribute($attribute) {
    return html_entity_decode(strip_tags($attribute));
  }

  /**
   * Helper function to compare two titles.
   */
  public function _cmp_array_title($a, $b) {
    return strcmp($a['title'], $b['title']);
  }

  /**
   * Helper function to compare two exhibit numbers.
   */
  public function _cmp_array_exhibit_number($a, $b) {
    return strnatcasecmp($a['exhibit_number'], $b['exhibit_number']);
  }

  /**
   * Filter content via Xss::filter, but leave certain HTML tags intact.
   *
   * @param string $content
   *   A string of text provided by the izi api.
   *
   * @return string
   *   The result of Xss::filter.
   */
  public function _izi_apicontent_filter_html_tags($content) {

    $allowed_tags = [
      'b',
      'strong',
      'i',
      'em',
      'u',
      'a',
      'sup',
      'br',
      'p',
    ];

    return empty($content) ? '' : Xss::filter($content, $allowed_tags);
  }

  /**
   * Custom implementation of _filter_url().
   *
   * Backport of the Drupal 8 _filter_url() including patch:
   * https://www.drupal.org/node/2016739#comment-9609953
   */
  public function _izi_apicontent_filter_url($text, $filter = NULL) {

    // Set default filter.
    if (!isset($filter)) {
      $filter = new \stdClass();
      $filter->settings['filter_url_length'] = 72;
    }

    // Tags to skip and not recurse into.
    $ignore_tags = 'a|script|style|code|pre';

    // Pass length to regexp callback.
    _filter_url_trim(NULL, $filter->settings['filter_url_length']);

    // Create an array which contains the regexps for each type of link.
    // The key to the regexp is the name of a function that is used as
    // callback function to process matches of the regexp. The callback function
    // is to return the replacement for the match. The array is used and
    // matching/replacement done below inside some loops.
    $tasks = [];

    // Prepare protocols pattern for absolute URLs.
    // check_url() will replace any bad protocols with HTTP, so we need to support
    // the identical list. While '//' is technically optional for MAILTO only,
    // we cannot cleanly differ between protocols here without hard-coding MAILTO,
    // so '//' is optional for all protocols.
    // @see \Drupal\Component\Utility\UrlHelper::filterBadProtocol()
    // $protocols = variable_get('filter_allowed_protocols', array('ftp', 'http', 'https', 'irc', 'mailto', 'news', 'nntp', 'rtsp', 'sftp', 'ssh', 'tel', 'telnet', 'webcal'));
    $protocols = \Drupal::state()->get('filter_allowed_protocols', ['ftp', 'http', 'https', 'irc', 'mailto', 'news', 'nntp', 'rtsp', 'sftp', 'ssh', 'tel', 'telnet', 'webcal']);
    $protocols = implode(':(?://)?|', $protocols) . ':(?://)?';

    $valid_url_path_characters = "[\p{L}\p{M}\p{N}!\*\';:=\+,\.\$\/%#\[\]\-_~@&]";

    // Allow URL paths to contain balanced parens
    // 1. Used in Wikipedia URLs like /Primer_(film)
    // 2. Used in IIS sessions like /S(dfd346)/.
    $valid_url_balanced_parens = '\(' . $valid_url_path_characters . '+\)';

    // Valid end-of-path characters (so /foo. does not gobble the period).
    // 1. Allow =&# for empty URL parameters and other URL-join artifacts.
    $valid_url_ending_characters = '[\p{L}\p{M}\p{N}:_+~#=/]|(?:' . $valid_url_balanced_parens . ')';

    $valid_url_query_chars = '[A-z0-9!?\*\'@\(\);:&=\+\$\/%#\[\]\-_\.,~|]';
    $valid_url_query_ending_chars = '[A-z0-9_&=#\/]';

    // Full path
    // and allow @ in a url, but only in the middle. Catch things like http://example.com/@user/
    $valid_url_path = '(?:(?:' . $valid_url_path_characters . '*(?:' . $valid_url_balanced_parens . $valid_url_path_characters . '*)*' . $valid_url_ending_characters . ')|(?:@' . $valid_url_path_characters . '+\/))';

    // Prepare domain name pattern.
    // The ICANN seems to be on track towards accepting more diverse top level
    // domains, so this pattern has been "future-proofed" to allow for TLDs
    // of length 2-64.
    $domain = '(?:[\p{L}\p{M}\p{N}._+-]+\.)?[\p{L}\p{M}]{2,64}\b';
    $ip = '(?:[0-9]{1,3}\.){3}[0-9]{1,3}';
    $auth = '[\p{L}\p{M}\p{N}:%_+*~#?&=.,/;-]+@';
    $trail = '(' . $valid_url_path . '*)?(\\?' . $valid_url_query_chars . '*' . $valid_url_query_ending_chars . ')?';

    // Match absolute URLs.
    $url_pattern = "(?:$auth)?(?:$domain|$ip)/?(?:$trail)?";
    $pattern = "`((?:$protocols)(?:$url_pattern))`u";
    $tasks['_izi_apicontent_filter_url_parse_full_links'] = $pattern;

    // Mail domain pattern differs from the general domain pattern by requiring
    // a subdomain match. This allows patterns like foo@bar in text without
    // being converted to a mailto -link.
    $email_domain = '(?:[\p{L}\p{M}\p{N}._+-]+\.)+[\p{L}\p{M}]{2,64}\b';

    // Match email addresses.
    $url_pattern = "[\p{L}\p{M}\p{N}._-]{1,254}@(?:$email_domain)";
    $pattern = "`($url_pattern)`u";
    $tasks['_izi_apicontent_filter_url_parse_email_links'] = $pattern;

    // Match www domains.
    $url_pattern = "www\.(?:$domain)/?(?:$trail)?";
    $pattern = "`($url_pattern)`u";
    $tasks['_izi_apicontent_filter_url_parse_partial_links'] = $pattern;

    // Each type of URL needs to be processed separately. The text is joined and
    // re-split after each task, since all injected HTML tags must be correctly
    // protected before the next task.
    foreach ($tasks as $task => $pattern) {
      // HTML comments need to be handled separately, as they may contain HTML
      // markup, especially a '>'. Therefore, remove all comment contents and add
      // them back later.
      _filter_url_escape_comments('', TRUE);
      $text = preg_replace_callback('`<!--(.*?)-->`s', '_filter_url_escape_comments', $text);

      // Split at all tags; ensures that no tags or attributes are processed.
      $chunks = preg_split('/(<.+?>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
      // PHP ensures that the array consists of alternating delimiters and
      // literals, and begins and ends with a literal (inserting NULL as
      // required). Therefore, the first chunk is always text:
      $chunk_type = 'text';
      // If a tag of $ignore_tags is found, it is stored in $open_tag and only
      // removed when the closing tag is found. Until the closing tag is found,
      // no replacements are made.
      $open_tag = '';

      for ($i = 0; $i < count($chunks); $i++) {
        if ($chunk_type == 'text') {
          // Only process this text if there are no unclosed $ignore_tags.
          if ($open_tag == '') {
            // If there is a match, inject a link into this chunk via the callback
            // function contained in $task.
            $chunks[$i] = preg_replace_callback($pattern, [$this, $task], $chunks[$i]);
          }
          // Text chunk is done, so next chunk must be a tag.
          $chunk_type = 'tag';
        }
        else {
          // Only process this tag if there are no unclosed $ignore_tags.
          if ($open_tag == '') {
            // Check whether this tag is contained in $ignore_tags.
            if (preg_match("`<($ignore_tags)(?:\s|>)`i", $chunks[$i], $matches)) {
              $open_tag = $matches[1];
            }
          }
          // Otherwise, check whether this is the closing tag for $open_tag.
          else {
            if (preg_match("`<\/$open_tag>`i", $chunks[$i], $matches)) {
              $open_tag = '';
            }
          }
          // Tag chunk is done, so next chunk must be text.
          $chunk_type = 'text';
        }
      }

      $text = implode($chunks);
      // Revert to the original comment contents.
      _filter_url_escape_comments('', FALSE);
      $text = preg_replace_callback('`<!--(.*?)-->`', '_filter_url_escape_comments', $text);
    }

    return $text;
  }

  /**
   * Backport of Drupal 8 _filter_url_parse_full_links().
   *
   * @see _izi_apicontent_filter_url()
   */
  public function _izi_apicontent_filter_url_parse_full_links($match) {
    // The $i:th parenthesis in the regexp contains the URL.
    $i = 1;
    // $match[$i] = decode_entities($match[$i]);
    $match[$i] = Html::decodeEntities($match[$i]);
    $caption = parse_url($match[$i], PHP_URL_HOST);
    // $match[$i] = check_plain($match[$i]);
    // Rely on Twig's auto-escaping feature, or use the #plain_text key when constructing a render array that contains plain text in order to use the renderer's auto-escaping feature. If neither of these are possible,
    // \Drupal\Component\Utility\Html::escape() can be used in places where explicit escaping is needed.
    $match[$i] = Html::escape($match[$i]);
    return '<a href="' . $match[$i] . '">' . $caption . '</a>';
  }

  /**
   * Makes links out of email addresses.
   *
   * Callback for preg_replace_callback() within _filter_url().
   */
  public function _izi_apicontent_filter_url_parse_email_links($match) {
    // The $i:th parenthesis in the regexp contains the URL.
    $i = 0;

    // $match[$i] = decode_entities($match[$i]);
    $match[$i] = Html::decodeEntities($match[$i]);
    // $caption = check_plain(_filter_url_trim($match[$i]));
    //    $match[$i] = check_plain($match[$i]);
    // Rely on Twig's auto-escaping feature, or use the #plain_text key when constructing a render array that contains plain text in order to use the renderer's auto-escaping feature. If neither of these are possible,
    // \Drupal\Component\Utility\Html::escape() can be used in places where explicit escaping is needed.
    $caption = Html::escape(_filter_url_trim($match[$i]));
    $match[$i] = Html::escape($match[$i]);
    return '<a href="mailto:' . $match[$i] . '">' . $caption . '</a>';
  }

  /**
   * Makes links out of domain names starting with "www."
   *
   * Callback for preg_replace_callback() within _filter_url().
   */
  public function _izi_apicontent_filter_url_parse_partial_links($match) {
    // The $i:th parenthesis in the regexp contains the URL.
    $i = 1;

    // $match[$i] = decode_entities($match[$i]);
    $match[$i] = Html::decodeEntities($match[$i]);
    // $caption = check_plain(_filter_url_trim($match[$i]));
    //    $match[$i] = check_plain($match[$i]);
    $caption = Html::escape(_filter_url_trim($match[$i]));
    $match[$i] = Html::escape($match[$i]);
    return '<a href="http://' . $match[$i] . '">' . $caption . '</a>';
  }

}
