<?php

class HTML extends Genome {

    protected static $lot;

    public static function __callStatic($kin, $lot) {
        if (!isset(self::$lot)) {
            self::$lot = new Genome\Union;
        }
        if (!self::$lot->kin($kin)) {
            return self::$lot->__call($kin, $lot);
        }
        return parent::__callStatic($kin, $lot);
    }

}