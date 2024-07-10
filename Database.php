<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    /**
     * Database constructor.
     *
     * @param mysqli $mysqli The MySQLi connection.
     */
    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Handle buildQuery.
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $query = $this->handlePlaceholders($query, $args);
        $query = $this->handleConditionalBlocks($query, $args);

        return $query;
    }

    /**
     * Handle skip.
     */
    public function skip()
    {
        return new class {};
    }

    /**
     * Handle placeholders in the query template.
     *
     * @param string $query The query template.
     * @param array $args The parameters to replace the placeholders.
     * @return string The query with placeholders replaced by actual values.
     */
    private function handlePlaceholders(string $query, array &$args): string
    {
        return preg_replace_callback('/(\?d|\?f|\?a|\?#|\?)/', function($matches) use (&$args) {
            if (empty($args)) {
                throw new Exception('Insufficient arguments for query placeholders');
            }
            $value = array_shift($args);
            return $this->formatValue($value, $matches[0]);
        }, $query);
    }

    /**
     * Handle conditional blocks in the query template.
     *
     * @param string $query The query template.
     * @param array $args The parameters to evaluate the conditional blocks.
     * @return string The query with conditional blocks evaluated.
     */
    private function handleConditionalBlocks(string $query, array $args): string
    {
        return preg_replace_callback('/\{([^{}]*)\}/', function($matches) use ($args) {
            foreach ($args as $arg) {
                if ($arg === $this->skip()) {
                    return '';
                }
            }
            return $matches[1];
        }, $query);
    }

    /**
     * Format a value based on its type specifier.
     *
     * @param mixed $value The value to format.
     * @param string $type The type specifier.
     * @return string The formatted value.
     * @throws Exception If the type specifier is unknown.
     */
    private function formatValue($value, string $type): string
    {
        switch ($type) {
            case '?d':
                return $this->formatInt($value);
            case '?f':
                return $this->formatFloat($value);
            case '?a':
                return $this->formatArray($value);
            case '?#':
                return $this->formatIdentifier($value);
            case '?':
                return $this->formatUniversal($value);
            default:
                throw new Exception('Unknown placeholder type');
        }
    }

    /**
     * Format an integer value.
     *
     * @param mixed $value The value to format.
     * @return string The formatted integer value.
     */
    private function formatInt($value): string
    {
        return $value === null ? 'NULL' : (string)(int)$value;
    }

    /**
     * Format a float value.
     *
     * @param mixed $value The value to format.
     * @return string The formatted float value.
     */
    private function formatFloat($value): string
    {
        return $value === null ? 'NULL' : (string)(float)$value;
    }

    /**
     * Format an array value.
     *
     * @param mixed $value The value to format.
     * @return string The formatted array value.
     * @throws Exception If the value is not an array.
     */
    private function formatArray($value): string
    {
        if (!is_array($value)) {
            throw new Exception('Expected array for ?a');
        }
        $formattedArray = array_map([$this, 'formatUniversal'], $value);
        return implode(', ', $formattedArray);
    }

    /**
     * Format an identifier value.
     *
     * @param mixed $value The value to format.
     * @return string The formatted identifier value.
     * @throws Exception If the value is not an array or string.
     */
    private function formatIdentifier($value): string
    {
        if (is_array($value)) {
            $formattedArray = array_map(function($v) {
                return "`" . $this->escapeString($v) . "`";
            }, $value);
            return implode(', ', $formattedArray);
        }
        return "`" . $this->escapeString($value) . "`";
    }

    /**
     * Format a universal value.
     *
     * @param mixed $value The value to format.
     * @return string The formatted value.
     */
    private function formatUniversal($value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $this->escapeString($value) . "'";
    }

    /**
     * Escape and quote a string value.
     *
     * @param mixed $value The string value to escape and quote.
     * @return string The escaped and quoted string value.
     */
    private function escapeString($value): string
    {
        return $this->mysqli->real_escape_string((string)$value);
    }
}
