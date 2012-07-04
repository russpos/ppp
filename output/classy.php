<?php

class Foo {

    /*
    Block motha fucking quotes
    */
    function __construct($foo, $bar) {
        $this->foo = $foo;
        $this->say($foo, true);
    }

    private function say($msg, $silly) {
        if ($silly) {
            echo($msg);
        } else {
            echo(">>>>$msg<<<<");
        }
    }
}

$c = new Foo("hello", 123);
