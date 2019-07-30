<?php

namespace Drupal\spectrum\Utils;

/**
 * This class provides functionality to parse parenthesis in a string to a nested array structure.
 * We use it to parse condition logic of queries to the correct format
 */
class ParenthesisParser
{
  /**
   * something to keep track of parens nesting
   *
   * @var array
   */
  protected $stack = null;

  /**
   * current level
   *
   * @var array
   */
  protected $current = null;

  /**
   * input string to parse
   *
   * @var string
   */
  protected $string = null;

  /**
   * current character offset in string
   *
   * @var int
   */
  protected $position = null;

  /**
   * start of text-buffer
   *
   * @var int
   */
  protected $buffer_start = null;

  /**
   * Parses an input string to a nested array
   *
   * @param string $string
   * @return array
   */
  public function parse(string $string): array
  {
    if (empty($string)) {
      // no string, no data
      return [];
    }

    if ($string[0] == '(') {
      // killer outer parens, as they're unnecessary
      $string = substr($string, 1, -1);
    }

    $this->current = [];
    $this->stack = [];

    $this->string = $string;
    $this->length = strlen($this->string);
    // look at each character
    for ($this->position = 0; $this->position < $this->length; $this->position++) {
      switch ($this->string[$this->position]) {
        case '(':
          $this->push();
          // push current scope to the stack an begin a new scope
          array_push($this->stack, $this->current);
          $this->current = [];
          break;

        case ')':
          $this->push();
          // save current scope
          $t = $this->current;
          // get the last scope from stack
          $this->current = array_pop($this->stack);
          // add just saved scope to current scope
          $this->current[] = $t;
          break;
        case ',':
          // make each word its own token
          $this->push();
          break;
        case ' ':
          // ignore whitespace
          break;
        default:
          // remember the offset to do a string capture later
          // could've also done $buffer .= $string[$position]
          // but that would just be wasting resources…
          if ($this->buffer_start === null) {
            $this->buffer_start = $this->position;
          }
          break;
      }
    }

    return $this->current;
  }

  /**
   * Pushes the current cycle 1 level up
   *
   * @return ParenthesisParser
   */
  protected function push(): ParenthesisParser
  {
    if ($this->buffer_start !== null) {
      // extract string from buffer start to current position
      $buffer = substr($this->string, $this->buffer_start, $this->position - $this->buffer_start);
      // clean buffer
      $this->buffer_start = null;
      // throw token into current scope
      $this->current[] = $buffer;
    }

    return $this;
  }
}
