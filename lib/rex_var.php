<?php

class naju_rex_var
{
		public static function toArray($value, $flags = ENT_QUOTES)
		{
			$value = json_decode(htmlspecialchars_decode($value, $flags), true);
			return is_array($value) ? $value : null;
		}
}
