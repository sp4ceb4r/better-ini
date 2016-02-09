<?php

namespace BetterIni;

/**
 * Class Ini
 */
class Ini
{
    const GLOBAL_SECTION = '__GLOBAL';

    const SPLIT_PATTERN = '/ ?= ?/';
    const KEY_PATTERN = '/^[a-zA-Z]\w+(?:\[\])?$/';
    const ARRAY_KEY_PATTERN = '/^[a-zA-Z]\w+\[(?:(?<array_key>[^\]]+))?\]$/';
    const HEADER_PATTERN = '/^\[(?<header>[a-zA-Z][^\]]+)\]$/';
    const QUOTED_TEXT_PATTERN = '/^((?<!\\\)[\'"])([^\1]+)\1$/';
    const ESCAPED_QUOTE_PATTERN = '/\\\([\'"])/';
    const QUOTE_CHARS = "'\"";

    protected $handle;
    protected $delimiter;
    protected $linenumber = 0;
    protected $config = [];

    /**
     * Ini constructor.
     *
     * @param $path
     * @param string $delimiter
     */
    public function __construct($path, $delimiter = ':')
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("$path is invalid");
        }

        $this->delimiter = $delimiter;
        try {
            $this->handle = fopen($path, 'r');
        } catch (\Exception $ex) {
            $this->close();
            throw new \RuntimeException("Error opening [$path] for read.");
        }

        $this->parse();
    }

    /**
     * Ensure the file handle was released.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Get the value for the key using dot expansion.
     *
     * @param null $key
     * @param null $default
     * @return array|bool|float|int|string
     */
    public function get($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config;
        }

        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        $tmp = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($tmp) || !array_key_exists($segment, $tmp)) {
                return $default;
            }

            $tmp = $tmp[$segment];
        }

        return $tmp;
    }

    /**
     * Release the file handle
     */
    protected function close()
    {
        if (isset($this->handle)) {
            fclose($this->handle);
            unset($this->handle);
        }
    }

    /**
     * Parse the ini file.
     */
    protected function parse()
    {
        $sectionName = static::GLOBAL_SECTION;
        $values = [];

        try {
            while ($line = fgets($this->handle)) {
                $this->linenumber++;
                $line = trim($line);
                if (empty($line) || substr($line, 0, 1) === ';') {
                    continue;
                }

                if ($this->isSectionHeader($line)) {
                    if ($sectionName === static::GLOBAL_SECTION) {
                        $this->config = $values;
                    } else {
                        $tokens = explode($this->delimiter, $sectionName);
                        $this->config = array_merge_recursive($this->config,
                                                              $this->subdivide($tokens, $values));

                        unset($tokens);
                    }

                    $sectionName = $this->getSectionHeader($line);
                    $values = [];
                    continue;
                }

                list($key, $value) = array_pad(preg_split(static::SPLIT_PATTERN, trim($line), 2), 2, '');

                if ($this->isArrayKey($key)) {
                    if (is_null($array_key = $this->parseArrayKey($key))) {
                        $key = trim(trim($key), '[]');

                        $values[$key][] = $this->parseValue($value);
                    } else {
                        $key = trim(substr($key, 0, strpos($key, '[')));
                        if (!isset($values[$key])) {
                            $values[$key] = [];
                        }

                        $values[$key][$array_key] = $this->parseValue($value);
                    }
                } else {
                    $values[trim($key)] = $this->parseValue($value);
                }
            }
        } catch (\Exception $ex) {
            throw new \RuntimeException("{$ex->getMessage()} Line: {$this->linenumber}.");
        } finally {
            $this->close();
        }

        if ($sectionName === static::GLOBAL_SECTION) {
            $this->config = $values;
        } else {
            $section = $this->subdivide(explode($this->delimiter, $sectionName), $values);
            $this->config = array_merge($this->config, $section);
        }
    }

    /**
     * Parse the value for a key.
     *
     * @param $value
     * @return bool|float|int|string
     */
    protected function parseValue($value)
    {
        if ($this->isQuoted($value)) {
            return trim(preg_replace(static::ESCAPED_QUOTE_PATTERN, '\1', $value), static::QUOTE_CHARS);
        } elseif ($this->opensQuote($value)) {
            $value = $this->parseMultilineValue(ltrim($value, static::QUOTE_CHARS));
            if ($value === false) {
                throw new \RuntimeException('Error parsing ini.');
            }

            return $value;
        } elseif (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                return floatval($value);
            }
            return intval($value);
        } elseif (in_array(strtolower($value), ['true', 'false'])) {
            return (strtolower($value) === 'true');
        } else {
            return $value;
        }
    }

    /**
     * Parse a multiline string value.
     *
     * @param string $seed
     * @return bool|string
     */
    protected function parseMultilineValue($seed = '')
    {
        $closed = false;
        $lines = empty($seed) ? []: [$seed];

        while ($line = fgets($this->handle)) {
            $line = trim($line);
            $this->linenumber++;

            if ($this->closesQuote($line)) {
                $closed = true;
                $lines[] = rtrim($line, static::QUOTE_CHARS);
                break;
            } else {
                $lines[] = "$line\n";
            }

            unset($trimmed);
        }

        return ($closed) ? join("\n", $lines) : false;
    }

    /**
     * Check the key is valid.
     *
     * @param $key
     * @return bool
     */
    protected function isValidKey($key)
    {
        return (preg_match(static::KEY_PATTERN, $key) === 1);
    }

    /**
     * Check if the text is a section header.
     *
     * @param $text
     * @return bool
     */
    protected function isSectionHeader($text)
    {
        return (preg_match(static::HEADER_PATTERN, $text) === 1);
    }

    /**
     * Get the section name from header.
     *
     * @param $text
     * @return mixed
     */
    protected function getSectionHeader($text)
    {
        preg_match(static::HEADER_PATTERN, $text, $matches);
        return $matches['header'];
    }

    /**
     * Check if the value is quoted.
     *
     * @param $text
     * @return bool
     */
    protected function isQuoted($text)
    {
        return (preg_match(static::QUOTED_TEXT_PATTERN, $text) === 1);
    }

    /**
     * Check if the key is an array.
     *
     * @param $key
     * @return bool
     */
    protected function isArrayKey($key)
    {
        return (preg_match(static::ARRAY_KEY_PATTERN, $key) === 1);
    }

    /**
     * Parse associative array key from ini array key value.
     *
     * @param $key
     * @return mixed
     */
    protected function parseArrayKey($key)
    {
        preg_match(static::ARRAY_KEY_PATTERN, $key, $matches);
        return (isset($matches['array_key']) ? $matches['array_key'] : null);
    }

    /**
     * Check if the value is an incomplete quote.
     *
     * @param $text
     * @return bool
     */
    protected function opensQuote($text)
    {
        return in_array(substr($text, 0, 1), ["'", '"']);
    }

    /**
     * Check if the quote is completed.
     *
     * @param $text
     * @return bool
     */
    protected function closesQuote($text)
    {
        return in_array(substr($text, -1), ["'", '"']);
    }

    /**
     * Expand delimited section headers into nested arrays.
     *
     * @param $sections
     * @param $value
     * @return array
     */
    protected function subdivide($sections, $value)
    {
        if (empty($sections)) {
            return $value;
        }

        $section = array_shift($sections);
        return [
            $section => $this->subdivide($sections, $value),
        ];
    }
}