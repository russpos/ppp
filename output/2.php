<?php

class Foo extends Bar {

    function __construct() {
        $this->name = 'russ';
    }

    private function doSomething() {
        $this->has($this->nested("function calls!", 12.4));
        $var = 'single quoted string';
    }

    function funcWithArg($x = 23) {
        if ($this->variable('return value', $x)) {
            return 24;
        } else if (23 + 34 > 12) {
            return 12;
        } else {
            return 6;
        }
    }

}
