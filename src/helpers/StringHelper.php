<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Glook\IsolatedComposer\helpers;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alex Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class StringHelper
{
	/**
	 * Returns the number of bytes in the given string.
	 * This method ensures the string is treated as a byte array by using `mb_strlen()`.
	 * @param string $string the string being measured for length
	 * @return int the number of bytes in the given string.
	 */
	public static function byteLength($string)
	{
		return mb_strlen($string, '8bit');
	}

	/**
	 * Returns the portion of string specified by the start and length parameters.
	 * This method ensures the string is treated as a byte array by using `mb_substr()`.
	 * @param string $string the input string. Must be one character or longer.
	 * @param int $start the starting position
	 * @param int $length the desired portion length. If not specified or `null`, there will be
	 * no limit on length i.e. the output will be until the end of the string.
	 * @return string the extracted part of string, or FALSE on failure or an empty string.
	 * @see https://secure.php.net/manual/en/function.substr.php
	 */
	public static function byteSubstr($string, $start, $length = null)
	{
		return mb_substr($string, $start, $length === null ? mb_strlen($string, '8bit') : $length, '8bit');
	}

	/**
	 * Checks if the passed string would match the given shell wildcard pattern.
	 * This function emulates [[fnmatch()]], which may be unavailable at certain environment, using PCRE.
	 * @param string $pattern the shell wildcard pattern.
	 * @param string $string the tested string.
	 * @param array $options options for matching. Valid options are:
	 * - caseSensitive: bool, whether pattern should be case sensitive. Defaults to `true`.
	 * - escape: bool, whether backslash escaping is enabled. Defaults to `true`.
	 * - filePath: bool, whether slashes in string only matches slashes in the given pattern. Defaults to `false`.
	 * @return bool whether the string matches pattern or not.
	 * @since 2.0.14
	 */
	public static function matchWildcard($pattern, $string, $options = [])
	{
		if ($pattern === '*' && empty($options['filePath'])) {
			return true;
		}

		$replacements = [
			'\\\\\\\\' => '\\\\',
			'\\\\\\*' => '[*]',
			'\\\\\\?' => '[?]',
			'\*' => '.*',
			'\?' => '.',
			'\[\!' => '[^',
			'\[' => '[',
			'\]' => ']',
			'\-' => '-',
		];

		if (isset($options['escape']) && !$options['escape']) {
			unset($replacements['\\\\\\\\']);
			unset($replacements['\\\\\\*']);
			unset($replacements['\\\\\\?']);
		}

		if (!empty($options['filePath'])) {
			$replacements['\*'] = '[^/\\\\]*';
			$replacements['\?'] = '[^/\\\\]';
		}

		$pattern = strtr(preg_quote($pattern, '#'), $replacements);
		$pattern = '#^' . $pattern . '$#us';

		if (isset($options['caseSensitive']) && !$options['caseSensitive']) {
			$pattern .= 'i';
		}

		return preg_match($pattern, $string) === 1;
	}

	public static function toBoolean($string)
	{
		return filter_var($string, FILTER_VALIDATE_BOOLEAN);
	}

}
