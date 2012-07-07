<?php

define('PPP_BAREWORD',     'PPP_Token_Bareword');
define('PPP_VARIABLE',     'PPP_Token_Variable');
define('PPP_PUNCTUATION',  'PPP_Token_Punctuation');
define('PPP_SELF',         'PPP_Token_Self');
define('PPP_STRING',       'PPP_Token_String');
define('PPP_WHITESPACE',   'PPP_Token_Whitespace');
define('PPP_INDENTATION',  'PPP_Token_Indentation');
define('PPP_PROPERTY',     'PPP_Token_Property');
define('PPP_UNKNOWN',      'PPP_Token_Unknown');
define('PPP_RESERVED',     'PPP_Token_Reserved');
define('PPP_BLOCKQUOTES',  'PPP_Token_Blockquotes');
define('PPP_BOOLEAN',      'PPP_Token_Boolean');
define('PPP_NUMBER',       'PPP_Token_Number');
define('PPP_STATIC_SELF',  'PPP_Token_StaticSelf');
define('PPP_RESERVED_IF',  'PPP_Token_Reserved_If');
define('PPP_RESERVED_DEF', 'PPP_Token_Reserved_Def');
define('PPP_DOT',          'PPP_Token_Dot');
define('PPP_RESERVED_BLOCK', 'PPP_Token_Reserved_Block');
define('PPP_RESERVED_CATCH', 'PPP_Token_Reserved_Catch');
define('PPP_RESERVED_STD',   'PPP_Token_Reserved_Standard');
define('PPP_RESERVED_ELIF',  'PPP_Token_Reserved_Elif');

define('IS_REGEX',    4500);
define('IS_MATCH',    4501);
define('IS_IN_LIST',  4502);
define('IS_RESERVED', 4503);

define('PPP_JSON_RESERVED_STANDARD', json_encode($reserved_std));
define('PPP_JSON_RESERVED_WORDS', json_encode($reserved_words));
define('PPP_JSON_PUNCTUATION',    json_encode($punctuation));
define('PPP_JSON_BOOLEANS',       json_encode($booleans));

abstract class PPP_Token {


    private static $definitions = array(
        IS_RESERVED => array(
            'if'  => PPP_RESERVED_IF,
            'def' => PPP_RESERVED_DEF,
            'try' => PPP_RESERVED_BLOCK,
            'else' => PPP_RESERVED_BLOCK,
            'class' => PPP_RESERVED_BLOCK,
            'catch' => PPP_RESERVED_CATCH,
            'elif'  => PPP_RESERVED_ELIF,
        ),
        IS_IN_LIST => array(
            PPP_JSON_RESERVED_STANDARD => PPP_RESERVED_STD,
            PPP_JSON_RESERVED_WORDS => PPP_RESERVED,
            PPP_JSON_BOOLEANS       => PPP_BOOLEAN,
            PPP_JSON_PUNCTUATION    => PPP_PUNCTUATION,
        ),
        IS_REGEX => array(
            '/^[a-zA-Z]/'                 => PPP_BAREWORD,  
            '/^\$[a-zA-Z][A-Za-z0-9_]*$/' => PPP_VARIABLE,
            '/^[0-9]+$/'                  => PPP_NUMBER,
            '/^@@\$?[a-zA-Z_]+$/'         => PPP_STATIC_SELF,
            '/@[a-zA-Z_]+/'               => PPP_PROPERTY,
            '/^"[^(\")]*"$/'              => PPP_STRING,
            "/^'[^(\')]*'$/"              => PPP_STRING,
        ),
        IS_MATCH => array(
            ''    => PPP_WHITESPACE,
            '.'   => PPP_DOT,
            '@'   => PPP_SELF,
            '###' => PPP_BLOCKQUOTES,
        )
    );

    public function __construct($str) {
        $this->value = $str;
        $this->type = get_class($this);
    }

    public function parse($parser) {
        $parser->token_list[] = $this->value;
    }

    public function is() {
        $types = func_get_args();
        return in_array($this->type, $types);
    }

    private static function create($str, $type) {
        if (!class_exists($type)) {
            throw new PPP_Token_UnrecognizedTokenException($type);
        }
        return new $type($str);
    }

    private static function compare($method, $test, $matcher) {
        switch ($method) {
        case IS_RESERVED: return ($test == $matcher);         break;
        case IS_IN_LIST:  return (in_array($matcher, json_decode($test))); break;
        case IS_MATCH:    return ($test == $matcher);         break;
        case IS_REGEX:    return preg_match($test, $matcher); break;
        }
    }

    protected function functionCall($parser) {
        if (!$parser->is_in_catch && $parser->next_token &&
                $parser->next_token->is(PPP_STRING, PPP_NUMBER, PPP_STATIC_SELF, PPP_BOOLEAN, PPP_BAREWORD, PPP_VARIABLE, PPP_SELF, PPP_PROPERTY)) {
            $parser->token_list[] = '(';
            $parser->open_stack[] = ')';
        }
    }

    public static function generate($str) {
        global $punctuation, $reserved_words, $booleans;

        $trimmed = trim($str);

        if (empty($str)) { return null; }
        foreach (self::$definitions as $method => $defs) {
            foreach ($defs as $matcher => $type) {
                if (self::compare($method, $matcher, $trimmed)) {
                    return self::create($str, $type);
                }
            }
        }
        return self::create($str, PPP_UNKNOWN);
    }
}

class PPP_Token_Blockquotes extends PPP_Token {
    protected function defaultType() {
        return PPP_BLOCKQUOTES;
    }
}

class PPP_Token_Bareword extends PPP_Token {

    protected function defaultType() {
        return PPP_BAREWORD;
    }

    public function parse($parser) {
        $parser->token_list[] = $this->value;
        $this->functionCall($parser);
    }

}

class PPP_Token_Whitespace extends PPP_Token {

    public function parse($parser) {
        $last_word = $parser->token_list[count($parser->token_list)-1];
        if (substr($last_word, -1) == '(') {
            throw new PPP_ParserException_TokenContinue();
        }
        $parser->token_list[] = $this->value;
    }
}

class PPP_Token_Variable extends PPP_Token {

}

class PPP_Token_Dot extends PPP_Token {

    public function parse($parser) {
        if ($parser->next_token->is(PPP_BAREWORD)) {
            $parser->token_list[] = '->';
        } else {
            $parser->token_list[] = $this->value;
        }
    }
}

class PPP_Token_Punctuation extends PPP_Token {

    public function parse($parser) {
        switch ($this->value) {
        case ':':
            if ($parser->array_stack) {
                $parser->token_list[] = '=>';
            } else {
                $parser->token_list[] = ':';
            }
            break;
        case '{':
            $parser->token_list[] = 'array(';
            $parser->array_stack++;
            break;
        case '[':
            if ($parser->prev_token && $parser->prev_token->is(PPP_VARIABLE)) {
                $parser->bracket_stack[] = PPP_BRACKET_PROP;
                $parser->token_list[] = '[';
            } else {
                $parser->bracket_stack[] = PPP_BRACKET_ARRAY;
                $parser->token_list[] = 'array(';
                $parser->array_stack++;
            }
            break;
        case '}':
            $parser->token_list[] = ')';
            $parser->array_stack--;
            break;
        case ']':
            $popped = array_pop($parser->bracket_stack);
            if ($popped == PPP_BRACKET_ARRAY) {
                $parser->token_list[] = ')';
                $parser->array_stack--;
            } else {
                $parser->token_list[] = ']';
            }
            break;

        case '<-':
            $parser->token_list[] = 'return';
            break;
        default:
            parent::parse($parser);
        }
    }
}

class PPP_Token_Self extends PPP_Token {

    public function parse($parser) {
        $parser->token_list[] = '$this';
    }
}

class PPP_Token_Indentation extends PPP_Token {

}

class PPP_Token_Property extends PPP_Token {

    public function parse($parser) {
        $parser->token_list[] = '$this->'.preg_replace('/^@/', '', $this->value);
        $this->functionCall($parser);
    }
}

class PPP_Token_Unknown extends PPP_Token {

}

class PPP_Token_Reserved_If extends PPP_Token_Reserved {

    public function parse($parser) {
        $parser->token_list[] = 'if (';
        $parser->open_stack[] = ')';
        $parser->ends_curly = true;
    }
}

abstract class PPP_Token_Reserved extends PPP_Token {

}

class PPP_Token_Reserved_Elif extends PPP_Token_Reserved {
    public function parse($parser) {
        $parser->token_list[] = 'else if (';
        $parser->open_stack[] = ')';
        $parser->ends_curly = true;
    }

}

class PPP_Token_Reserved_Def extends PPP_Token_Reserved {
    public function parse($parser) {
        $parser->token_list[] = 'function';
        $parser->ends_curly = true;
    }
}

class PPP_Token_Reserved_Catch extends PPP_Token_Reserved {
    public function parse($parser) {
        $parser->token_list[] = 'catch (';
        $parser->open_stack[] = ')';
        $parser->is_in_catch = true;
        $parser->ends_curly = true;
    }
}

class PPP_Token_Reserved_Standard extends PPP_Token_Reserved {

    public function parse($parser) {
        $parser->token_list[] = $this->value;
    }
}

class PPP_Token_Reserved_Block extends PPP_Token_Reserved {

    public function parse($parser) {
        $parser->token_list[] = $this->value;
        $parser->ends_curly = true;
    }

}

class PPP_Token_Boolean extends PPP_Token {

    public function parse($parser) {
        global $synonym_list, $synonyms;
        if (in_array($this->value, $synonym_list)) {
            $parser->token_list[] = $synonyms[$this->value];
        } else {
            $parser->token_list[] = $this->value;
        }
    }
}

class PPP_Token_StaticSelf extends PPP_Token {

    public function parse($parser) {
        $parser->token_list[] = str_replace('@@', 'self::', $this->value);
    }
}

class PPP_Token_String extends PPP_Token {

}

class PPP_Token_Number extends PPP_Token {

}


class PPP_Token_UnrecognizedTokenException extends Exception {

    public function __construct($clss) {
        $this->message = $clss;
    }


}
