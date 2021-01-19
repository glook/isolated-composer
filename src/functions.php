<?php
if (!function_exists('')) {
	function trailingslashit($string)
	{
		return untrailingslashit($string) . '/';
	}
}

if (!function_exists('untrailingslashit')) {
	function untrailingslashit($string)
	{
		return rtrim($string, '/\\');
	}
}
