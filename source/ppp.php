<?php

class PPP {

    const VERSION = '0.0.3';

    const IS_REGEX    = 4500;
    const IS_MATCH    = 4501;
    const IS_IN_LIST  = 4502;
    const IS_RESERVED = 4503;

    const BAREWORD =      'PPP_Token_Bareword';
    const VARIABLE =      'PPP_Token_Variable';
    const OPERATOR =      'PPP_Token_Operator';
    const OPERATOR_RETURN =  'PPP_Token_Operator_Return';
    const OPERATOR_COLON =   'PPP_Token_Operator_Colon';
    const SELF =          'PPP_Token_Self';
    const STRING =        'PPP_Token_String';
    const WHITESPACE =    'PPP_Token_Whitespace';
    const INDENTATION =   'PPP_Token_Indentation';
    const PROPERTY =      'PPP_Token_Property';
    const UNKNOWN =       'PPP_Token_Unknown';
    const RESERVED =      'PPP_Token_Reserved';
    const BLOCKQUOTES =   'PPP_Token_Blockquotes';
    const BOOLEAN =       'PPP_Token_Boolean';
    const NUMBER =        'PPP_Token_Number';
    const STATIC_SELF =   'PPP_Token_StaticSelf';
    const RESERVED_IF =   'PPP_Token_Reserved_If';
    const RESERVED_DEF =  'PPP_Token_Reserved_Def';
    const DOT =           'PPP_Token_Dot';
    const RESERVED_BLOCK =  'PPP_Token_Reserved_Block';
    const RESERVED_CATCH =  'PPP_Token_Reserved_Catch';
    const RESERVED_STD         =    'PPP_Token_Reserved_Standard';
    const RESERVED_ELIF        =   'PPP_Token_Reserved_Elif';
    const VARIABLE_ARRAY       =  'PPP_Token_Variable_Array';
    const VARIABLE_ARRAY_CLOSE =  'PPP_Token_Variable_ArrayClose';

    const BRACKET_ARRAY = 301;

    public static $operator = array('(', ',', ')', '=', '->', '+', '-');
    public static $reserved_words = array('try', 'else', 'class');
    public static $reserved_std = array('public', 'extends', 'const', 'private', 'throw', 'static', 'new');
    public static $booleans = array('yes', 'no', 'on', 'off', 'nil', 'none', 'true', 'false');
    public static $synonyms = array(
        'on'   => 'true',
        'yes'  => 'true',
        'off'  => 'false',
        'no'   => 'false',
        'nil'  => 'null',
        'none' => 'null',
    );

    public static $production = false;

    public static function synonym_list() {
        return array_keys(self::$synonyms);
    }

    public static function import($source_name, $file=false) {
        if ($file === false) {
            $callers = debug_backtrace();
            $caller = $callers[0];
            $file = $caller['file'];
        }
        $relative_dir = dirname($file);
        $file_base = $relative_dir.'/'.$source_name;

        if (self::$production) {
            $basedir = dirname($file_base);
            $filename = basename($file_base);
            require_once(realpath($basedir."/.ppp.".$filename.".php"));
        } else {
            $parser = new PPP_Parser(array('init' => false));
            $content = $parser->parseFile(realpath($file_base.".ppp"));
            eval($content);
        }
    }

}

require('parser.php');
require('cli.php');
