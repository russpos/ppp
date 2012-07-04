<?php

class Foo extends Bar {

    const SOME_CONST = 'foo';

    static $static_property = 5;

    function __construct() {
        $this->name = 'russ';
    }

    private function doSomething() {
        $this->has($this->nested("function calls!", 12.4));
        $var = 'single quoted string';
        $var->supports->dotNotation = "also"."supports".'string'.$concatenation;

        $name = func(self::SOME_CONST);
        $name = func(self::$static_property);

        $array_style_one = array('list', 'of', 4, $things);
        $nested_arrays = array('stuff', $with, array('stuff'=> $between));
        $arrays = array('name' => 'russ', 'phone_number' => '1800flowers');
        $arrays[$foo] = 'bar';
        $var->supports($dotNotation, 'for functions!');
        return $x < $y ? true : false;
    }

    function funcWithArg($x = 23) {
        $do = ($foo > 'barf') ? true : false;

        $barf = new BarfMachine('test');
        $barf->turnMachine(false);

        if ($this->variable('return value', $x)) {
            return 24;
        } else if (23 + 34 > 12) {
            return 12;
        } else {
            return 6;
        }
    }

}
