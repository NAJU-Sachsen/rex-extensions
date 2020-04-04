<?php

define('NAJU_FLOAT_MIN_NORMAL', 0.0 + PHP_FLOAT_EPSILON);

class naju_float
{
    public static function eq($a, $b, $epsilon)
    {
        return abs($a - $b) < $epsilon;   
    }

}
