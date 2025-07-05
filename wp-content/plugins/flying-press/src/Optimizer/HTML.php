<?php

namespace FlyingPress\Optimizer;

class HTML
{
  public $original_tag;
  private $tag;
  private $self_closing;

  public function __construct($tag)
  {
    $this->original_tag = $tag;
    $this->tag = $tag;
    $this->self_closing = preg_match('/\/>$/', $tag) > 0;
  }

  public function getContent()
  {
    if ($this->self_closing) {
      return '';
    }

    $tag_name = $this->getTagName();
    $start = strpos($this->tag, '>') + 1;
    $end = strrpos($this->tag, "</$tag_name>");
    return trim(substr($this->tag, $start, $end - $start));
  }

  public function setContent($content)
  {
    if ($this->self_closing) {
      return;
    }

    $tag_name = $this->getTagName();
    $start = strpos($this->tag, '>') + 1;
    $end = strrpos($this->tag, "</$tag_name>");
    $this->tag = substr_replace($this->tag, $content, $start, $end - $start);
    return true;
  }

  public function __get($attribute)
  {
    // Extract the first opening tag
    if (!preg_match('/<[^>]+>/', $this->tag, $tags)) {
      return null;
    }

    $first_tag = $tags[0];

    // Use the existing regex to check for the attribute within the first tag
    if (preg_match("/\s$attribute=([\"'])(.*?)\\1[^<]*?>/", $first_tag, $matches)) {
      return $matches[2];
    } elseif (preg_match("/\s$attribute(\s|>)/", $first_tag)) {
      return true;
    }

    return null;
  }

  public function __set($attribute, $value = null)
  {
    if (strpos($value, '"') !== false) {
      $attribute_string = $value === true ? $attribute : "$attribute='$value'";
    } else {
      $attribute_string = $value === true ? $attribute : "$attribute=\"$value\"";
    }

    $this->tag = $this->$attribute
      ? preg_replace(
        "/\s$attribute(=(\"|').*?(\\2)(?=\s|>|\/))?(?:(?=[^\w]))/",
        " $attribute_string",
        $this->tag,
        1
      )
      : preg_replace('/(>|\/>)/', " $attribute_string$1", $this->tag, 1);
  }

  public function __unset($attribute)
  {
    $this->tag = preg_replace("/\s$attribute(=(\"|').*?\\2)?(?=\s|>|\/)/", '', $this->tag);
  }

  public function getTagName()
  {
    preg_match('/<(.*?)[\s|>]/', $this->tag, $matches);
    return $matches[1];
  }

  public function getTagsBySelector($selector)
  {
    if ($selector[0] === '.') {
      $class = substr($selector, 1);
      preg_match_all(
        "/(<[^>]*class=[\"'][^\"']*{$class}[^\"']*[\"'][^>]*>)/s",
        $this->tag,
        $matches,
        PREG_OFFSET_CAPTURE
      );
    } elseif ($selector[0] === '#') {
      $id = substr($selector, 1);
      preg_match_all(
        "/(<[^>]*id=[\"']{$id}[\"'][^>]*>)/s",
        $this->tag,
        $matches,
        PREG_OFFSET_CAPTURE
      );
    } elseif ($selector[0] === '[' && $selector[strlen($selector) - 1] === ']') {
      $selector = substr($selector, 1, -1);
      // Split the selector into attribute name and optional value
      preg_match('/^([a-zA-Z0-9-]+)(?:=["\']([^"\']+)["\'])?$/', $selector, $parts);

      if (empty($parts)) {
        return [];
      }

      $attribute_name = $parts[1];
      $attribute_value = $parts[2] ?? null;

      $pattern = $attribute_value
        ? "/(<[^>]*\s{$attribute_name}=['\"][^'\"]*\b{$attribute_value}\b[^'\"]*['\"][^>]*>)/"
        : "/(<[^>]*\s{$attribute_name}(\s|=[\"'][^\"']*[\"'])?[^>]*>)/";

      preg_match_all($pattern, $this->tag, $matches, PREG_OFFSET_CAPTURE);
    } else {
      $tag = $selector;
      preg_match_all("/(<$tag.*?>)/s", $this->tag, $matches, PREG_OFFSET_CAPTURE);
    }
    return $matches[1];
  }

  public function getElementsBySelector($selector)
  {
    $tags = $this->getTagsBySelector($selector);

    if (empty($tags)) {
      return [];
    }

    $captured_elements = [];

    preg_match_all('/<[^>]*>/', $this->tag, $matches, PREG_OFFSET_CAPTURE);

    foreach ($tags as $tag) {
      $stack = [];

      foreach ($matches[0] as $match) {
        // Start only after the current element
        if ($match[1] < $tag[1]) {
          continue;
        }

        // Skip self-closing tags, comments, scripts, styles etc
        if ($this->shouldSkip($match[0])) {
          continue;
        }

        // Push the opening tag to the stack
        if (!preg_match('/<\//', $match[0])) {
          array_push($stack, $match[0]);
        }
        // Pop the closing tag from the stack
        else {
          array_pop($stack);
        }

        // If the stack is empty, its the end of the element
        // Capture the element and break the loop
        if (empty($stack)) {
          // Starting position of the element
          $offset_start = $tag[1];
          // Length is the current match position + length of the match - the starting position
          $length = $match[1] + strlen($match[0]) - $tag[1];
          $element = substr($this->tag, $offset_start, $length);
          $captured_elements[] = $element;
          break;
        }
      }
    }
    return $captured_elements;
  }

  public function setUid()
  {
    $counter = 0;
    // Regex pattern to match only uid-safe tags
    $pattern =
      '/<(div|section|body|article|main|aside|header|footer|nav|figure|fieldset|img|video|iframe|picture|source|canvas|svg|h1|h2|h3|h4|h5|h6|p|span|blockquote|pre|code|ul|ol|li|table|thead|tbody|tr|td|th|a|button|form|input|textarea|label|details|summary|select)\b([^>]*)>/i';

    $this->tag = preg_replace_callback(
      $pattern,
      function ($matches) use (&$counter) {
        $counter++;
        return "<{$matches[1]} data-uid=\"{$counter}\"{$matches[2]}>";
      },
      $this->original_tag
    );

    return (string) $this->tag;
  }

  public function shouldSkip($tag)
  {
    // Define a regular expression pattern to match the start of the tag names
    $pattern =
      '/^<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr|script|style|circle|rect|ellipse|line|path|poly|use|view|stop|set|image|animate|fe[a-zA-Z]+|!--|!DOCTYPE)/i';

    // Use preg_match to check if the tag matches the pattern
    return preg_match($pattern, $tag) === 1;
  }

  public function __toString()
  {
    return (string) $this->tag;
  }
}
