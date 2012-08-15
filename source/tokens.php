<?php

define('PPP_JSON_RESERVED_STANDARD', json_encode(PPP::$reserved_std));
define('PPP_JSON_RESERVED_BLOCK',    json_encode(PPP::$reserved_words));
define('PPP_JSON_OPERATOR',          json_encode(PPP::$operator));
define('PPP_JSON_BOOLEANS',          json_encode(PPP::$booleans));

abstract class PPP_Token {


    private static $definitions = array(
        PPP::IS_RESERVED => array(
            'if'    => PPP::RESERVED_IF,
            'def'   => PPP::RESERVED_DEF,
            'catch' => PPP::RESERVED_CATCH,
            'elif'  => PPP::RESERVED_ELIF,
        ),
        PPP::IS_IN_LIST => array(
            PPP_JSON_RESERVED_STANDARD => PPP::RESERVED_STD,
            PPP_JSON_RESERVED_BLOCK    => PPP::RESERVED_BLOCK,
            PPP_JSON_BOOLEANS          => PPP::BOOLEAN,
            PPP_JSON_OPERATOR          => PPP::OPERATOR,
        ),
        PPP::IS_REGEX => array(
            '/^[a-zA-Z]/'                 => PPP::BAREWORD,  
            '/^\$[a-zA-Z][A-Za-z0-9_]*$/' => PPP::VARIABLE,
            '/^[0-9]+$/'                  => PPP::NUMBER,
            '/^@@\$?[a-zA-Z_]+$/'         => PPP::STATIC_SELF,
            '/@[a-zA-Z_]+/'               => PPP::PROPERTY,
            '/^"[^(\")]*"$/'              => PPP::STRING,
            "/^'[^(\')]*'$/"              => PPP::STRING,
        ),
        PPP::IS_MATCH => array(
            '['   => PPP::VARIABLE_ARRAY,
            '{'   => PPP::VARIABLE_ARRAY,
            ']'   => PPP::VARIABLE_ARRAY_CLOSE,
            '}'   => PPP::VARIABLE_ARRAY_CLOSE,
            ''    => PPP::WHITESPACE,
            '<-'  => PPP::OPERATOR_RETURN,
            ':'   => PPP::OPERATOR_COLON,
            '.'   => PPP::DOT,
            '@'   => PPP::SELF,
            '###' => PPP::BLOCKQUOTES,
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
        case PPP::IS_RESERVED: return ($test == $matcher);         break;
        case PPP::IS_IN_LIST:  return (in_array($matcher, json_decode($test))); break;
        case PPP::IS_MATCH:    return ($test == $matcher);         break;
        case PPP::IS_REGEX:    return preg_match($test, $matcher); break;
        }
    }

    protected function functionCall($parser) {
        if (!$parser->is_in_catch && $parser->next_token &&
                $parser->next_token->is(PPP::STRING, PPP::NUMBER, PPP::STATIC_SELF, PPP::BOOLEAN, PPP::BAREWORD, PPP::VARIABLE, PPP::SELF, PPP::PROPERTY)) {
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
        return self::create($str, PPP::UNKNOWN);
    }
}

class PPP_Token_Blockquotes extends PPP_Token {
    protected function defaultType() {
        return PPP::BLOCKQUOTES;
    }
}

class PPP_Token_Bareword extends PPP_Token {

    protected function defaultType() {
        return PPP::BAREWORD;
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
        if ($parser->next_token->is(PPP::BAREWORD)) {
            $parser->token_list[] = '->';
        } else {
            $parser->token_list[] = $this->value;
        }
    }
}

class PPP_Token_Operator extends PPP_Token {

}

class PPP_Token_Operator_Colon extends PPP_Token_Operator {

    public function parse($parser) {
        $parser->token_list[] = ($parser->array_stack) ? '=>' : ':';
    }
}

class PPP_Token_Operator_Return extends PPP_Token_Operator {
    public function parse($parser) {
        $parser->token_list[] = 'return';
    }    
}

class PPP_Token_Variable_Array extends PPP_Token_Variable {
    public function parse($parser) {
        switch ($this->value) {
        case '[':
            if ($parser->prev_token && $parser->prev_token->is(PPP::VARIABLE)) {
                $parser->token_list[] = '[';
                $parser->bracket_stack[] = null;
                break;
            }

            $parser->bracket_stack[] = PPP::BRACKET_ARRAY;
        case '{':
            $parser->token_list[] = 'array(';
            $parser->array_stack++;
            break;
        }
    }
}

class PPP_Token_Variable_ArrayClose extends PPP_Token_Variable {
    public function parse($parser) {
        switch ($this->value) {
        case ']':
            $popped = array_pop($parser->bracket_stack);
            if ($popped !== PPP::BRACKET_ARRAY) {
                $parser->token_list[] = ']';
                break;
            }
        case '}':
            $parser->token_list[] = ')';
            $parser->array_stack--;
            break;
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
        if (in_array($this->value, PPP::synonym_list())) {
            $parser->token_list[] = PPP::$synonyms[$this->value];
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
