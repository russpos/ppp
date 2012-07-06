<?php

class Foo extends Bar {

    const SOME_CONST = 'foo';

    static $static_property = 5;

    static $multi_line_array = array(
        'foo',
        'bar',
        'baz',
        'barf',
        );

    static $multi_2d_array = array(
        'foo' => array(
            'bar'=> 'baz'
            )
        );

    static $crazy_json_obj = array(
        "foo" => array("bar" => "baz" ),
        "barf" => array(
            "magic" => array(1, 2, 3)
            )
        );

    static $three_deep = array(
        "foo" => array(
            "bar"=> array(
                'hello'
                )
            )
        );

    static $one_d_json_object = array(
        "foo" => 124
        );

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
    function someMethodThatThrows() {
        try {
            $this->funcWithArg(42);
        }
        catch (ORM_Exception $e) {
            Logger::log($e->getMessage());
            throw $e;
        }
        catch (UnexecptedArgumentException $e) {
            return 42;

        }
    }
    function funcWithArg($x = 23) {
        $do = ($foo > 'barf') ? true : false;

        $barf = new BarfMachine('test');
        $barf->turnMachine(false);

        if ($this->variable('return value', $x)) {
            return 24;
        }
        else if (23 + 34 > 12) {
            return 12;
        }
        else {
            return 6;


        }
    }
}
