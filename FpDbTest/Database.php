<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
  private mysqli $mysqli;
  private const string SKIP                          = '__SKIP__FLAG__';
  private const string PLACEHOLDER_PATTERN           = '\?[dfa#]?';
  private const string CONDITIONAL_BLOCK_PATTERN     = '{([^{}]*)}';
  private const string NESTED_CONDITIONAL_PATTERN = '{[^{}]*{[^{}]*}[^{}]*}';

  public function __construct(mysqli $mysqli)
  {
    $this->mysqli = $mysqli;
  }

  /**
   * @param string $query - SQL query with placeholders
   * @param array $args - array of arguments to replace placeholders
   * @return string - SQL query with replaced placeholders
   * @throws Exception
   */
  public function buildQuery(string $query, array $args = []): string
  {
    if (preg_match('/' . self::NESTED_CONDITIONAL_PATTERN . '/', $query)) {
      throw new Exception("Conditional block must not be nested.");
    }
    if (substr_count($query, '{') !== substr_count($query, '}')) {
      throw new Exception("Conditional block must be closed.");
    }

    $pattern = '/' . self::PLACEHOLDER_PATTERN . '|' . self::CONDITIONAL_BLOCK_PATTERN . '/';
    $argIndex = 0;


    $callback = function ($matches) use ($args, &$argIndex) {
      $token = $matches[0];
      if ($token[0] === '{') { // Handle conditional block
        return $this->matchConditionalBlock($matches[1], $args, $argIndex);
      } else {
        return $this->matchToken($token, $args, $argIndex);
      }
    };

    return preg_replace_callback($pattern, $callback, $query);
  }

  /**
   * @param string $innerQuery - query with placeholders inside conditional block
   * @param array $args - array of arguments to replace placeholders
   * @param int $argIndex - index of the current argument
   * @return string
   * @throws Exception
   */
  private function matchConditionalBlock(string $innerQuery, array $args, int &$argIndex): string
  {
    $pattern = '/' . self::PLACEHOLDER_PATTERN . '/';
    $matches = [];
    $result = $innerQuery;
    $indexAccumulator = 0;

    preg_match_all($pattern, $innerQuery, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[0] as $tokenIndex => $match) {
      $token = $match[0];
      $index = $match[1];

      if ($args[$argIndex] === self::SKIP) {
        // Skip whole block if any of the placeholders is skipped
        $argIndex = $argIndex - $tokenIndex + count($matches[0]);
        return '';
      } else {
        $replacement = $this->matchToken($token, $args, $argIndex);
        $result = substr_replace($result, $replacement, $index + $indexAccumulator, strlen($token));
        $indexAccumulator += strlen($replacement) - strlen($token);
      }
    }

    return $result;
  }

  /**
   * @param string $token - placeholder token
   * @param array $args - array of arguments to replace placeholders
   * @param int $argIndex - index of the current argument
   * @return string - formatted value for given placeholder
   * @throws Exception
   */
  private function matchToken(string $token, array $args, int &$argIndex): string
  {
    if ($argIndex >= count($args)) {
      throw new Exception("Not enough parameters provided for placeholders.");
    }
    $arg = $args[$argIndex++];
    return match ($token) {
      '?d' => $this->formatInt($arg),
      '?f' => $this->formatFloat($arg),
      '?a' => $this->formatArray($arg),
      '?#' => $this->formatIdentifier($arg),
      default => $this->formatValue($arg),
    };
  }

  /**
   * @param mixed $value - value to be formatted
   * @return string - formatted string
   * @throws Exception
   */
  private function formatValue(mixed $value): string
  {
    if (is_null($value)) {
      return 'NULL';
    } elseif (is_bool($value)) {
      return $value ? '1' : '0';
    } elseif (is_string($value)) {
      return "'" . $this->escapeString($value) . "'";
    } elseif (is_numeric($value)) {
      return $value;
    } else {
      throw new Exception("Unsupported parameter type.");
    }
  }

  /**
   * @param mixed $value - value to be formatted as integer
   * @return string - formatted string
   * @throws Exception
   */
  private function formatInt(mixed $value): string
  {
    if (is_array($value)) {
      throw new Exception("Array parameter cannot be used as an integer.");
    }
    return is_null($value) ? 'NULL' : intval($value);
  }

  /**
   * @param mixed $value - value to be formatted as float
   * @return string - formatted string
   * @throws Exception
   */
  private function formatFloat(mixed $value): string
  {
    if (is_array($value)) {
      throw new Exception("Array parameter cannot be used as a float.");
    }
    return is_null($value) ? 'NULL' : floatval($value);
  }

  /**
   * @param mixed $array - array to be formatted
   * @return string - formatted string
   * @throws Exception
   */
  private function formatArray(mixed $array): string
  {
    if (is_null($array)) {
      throw new Exception("Array parameter cannot be NULL.");
    }
    if (!is_array($array)) {
      throw new Exception("Array parameter expected.");
    }
    if (empty($array)) {
      throw new Exception("Array parameter cannot be empty.");
    }
    $result = [];
    foreach ($array as $key => $value) {
      if (is_int($key)) {
        $result[] = $this->formatValue($value);
      } else {
        $result[] = $this->escapeIdentifier($key) . " = " . $this->formatValue($value);
      }
    }
    return implode(', ', $result);
  }

  /**
   * @param mixed $identifiers - identifier(s) to be formatted
   * @return string - formatted string
   * @throws Exception
   */
  private function formatIdentifier(mixed $identifiers): string
  {
    if (is_null($identifiers)) {
      throw new Exception("Identifier parameter cannot be NULL.");
    }
    if (!is_array($identifiers) && !is_string($identifiers)) {
      throw new Exception("Identifier parameter must be a string or an array.");
    }
    if (is_array($identifiers)) {
      if (empty($identifiers)) {
        throw new Exception("Identifier array cannot be empty.");
      }
      return implode(', ', array_map([$this, 'escapeIdentifier'], $identifiers));
    } else {
      return $this->escapeIdentifier($identifiers);
    }
  }

  /**
   * @param mixed $identifier - identifier to be escaped
   * @return string - escaped identifier
   * @throws Exception
   */
  private function escapeIdentifier(mixed $identifier): string
  {
    if (!is_string($identifier)) {
      throw new Exception("Identifier must be a string.");
    }
    if (preg_match('/[^a-zA-Z0-9_]/', $identifier)) {
      throw new Exception("Invalid identifier.");
    }
    return "`" . $this->mysqli->real_escape_string($identifier) . "`";
  }


  /**
   * @param string $string - string to be escaped
   * @return string - escaped string
   */
  private function escapeString(string $string): string
  {
    return $this->mysqli->real_escape_string($string);
  }

  /**
   * @return string - skip flag
   */
  public function skip(): string
  {
    return self::SKIP;
  }
}