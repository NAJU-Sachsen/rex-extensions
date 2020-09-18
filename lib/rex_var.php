<?php

class naju_rex_var
{
		public static function toArray($value)
		{
			$value = json_decode(htmlspecialchars_decode($value, ENT_QUOTES), true);
			return is_array($value) ? $value : null;
		}
}
