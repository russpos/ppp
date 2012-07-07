<?php

// Tokens
define('PPP_BAREWORD',    200);
define('PPP_VARIABLE',    201);
define('PPP_PUNCTUATION', 202);
define('PPP_SELF',        203);
define('PPP_STRING',      204);
define('PPP_WHITESPACE',  205);
define('PPP_INDENTATION', 206);
define('PPP_PROPERTY',    207);
define('PPP_UNKNOWN',     208);
define('PPP_RESERVED',    209);
define('PPP_BLOCKQUOTES', 210);
define('PPP_BOOLEAN',     211);
define('PPP_STATIC_SELF', 212);
define('PPP_NUMBER',      213);


define('IS_REGEX', 4500);
define('IS_MATCH', 4501);
define('IS_IN_LIST', 4502);

class Token {

    private static $classes = array(
        PPP_BAREWORD    => 'Token_Bareword',
        PPP_VARIABLE    => 'Token_Variable',
        PPP_PUNCTUATION => 'Token_Punctuation',
        PPP_SELF        => 'Token_Self',
        PPP_STRING      => 'Token_String',
        PPP_WHITESPACE  => 'Token_Whitespace',
        PPP_INDENTATION => 'Token_Indentation',
        PPP_PROPERTY    => 'Token_Property',
        PPP_UNKNOWN     => 'Token_Unknown',
        PPP_RESERVED    => 'Token_Reserved',
        PPP_BLOCKQUOTES => 'Token_Blockquotes',
        PPP_BOOLEAN     => 'Token_Boolean',
        PPP_NUMBER      => 'Token_Number',
        PPP_STATIC_SELF => 'Token_StaticSelf',
    );

    private static $definitions = array(
        IS_REGEX => array('/^\$[a-zA-Z][A-Za-z0-9_]*$/' => PPP_VARIABLE,
                          '/^[0-9]+$/'                  => PPP_NUMBER)
    );

    public function __construct($str, $type=null) {
        $this->value = $str;
        if ($type) {
            $this->type = $type;
        } else {
            $this->type = $this->defaultType();
        }
    }

    public function parse($parser) {
        $parser->token_list[] = $this->value;
    }

    public function is($type) {
        return $this->type === $type;
    }

    private static function create($str, $type) {
        return new self::$classes[$type]($str);
    }

    private static function compare($method, $test, $matcher) {
        switch ($method) {
        case IS_REGEX:   return preg_match($test, $matcher); break;
        case IS_MATCH:   return ($test == $matcher);         break;
        case IS_IN_LIST: return (in_array($matcher, $test)); break;
        }
    }

    protected function functionCall($parser) {
        if (!$parser->is_in_catch && $parser->next_token && ($parser->next_token->type === PPP_STRING || $parser->next_token->type === PPP_NUMBER || $parser->next_token->type === PPP_STATIC_SELF || $parser->next_token->type === PPP_BOOLEAN || $parser->next_token->type === PPP_BAREWORD || $parser->next_token->type === PPP_VARIABLE || $parser->next_token->type === PPP_SELF || $parser->next_token->type === PPP_PROPERTY)) {
            $parser->token_list[] = '(';
            $parser->open_stack[] = ')';
        }
    }

    public static function generate($str) {
        global $punctuation, $reserved_words, $booleans;

        $trimmed = trim($str);

        if (empty($str)) {
            return null;
        }

        foreach (self::$definitions as $method => $defs) {
            foreach ($defs as $matcher => $type) {
                if (self::compare($method, $matcher, $trimmed)) {
                    return self::create($str, $type);
                }
            }
        }

        if ($trimmed == '###') {
            return self::create($str, PPP_BLOCKQUOTES);
        }

        if (in_array($trimmed, $booleans)) {
            return self::create($str, PPP_BOOLEAN);
        }

        // Whitespace
        if (empty($trimmed)) {
            return self::create($str, PPP_WHITESPACE);
        }

        if (in_array($trimmed, $reserved_words)) {
            return self::create($str, PPP_RESERVED);
        }

        // Barewords
        if (preg_match('/^[a-zA-Z]/', $str)) {
            return self::create($str, PPP_BAREWORD);
        }

        // Static self
        if (preg_match('/^@@\$?[a-zA-Z_]+$/', $str)) {
            return self::create($str, PPP_STATIC_SELF);
        }

        // Self 
        if ($trimmed === '@') {
            return self::create($str, PPP_SELF);
        }

        // Property
        if (preg_match('/@[a-zA-Z_]+/', $str)) {
            return self::create($str, PPP_PROPERTY);
        }

        // String
        if (preg_match('/^"[^(\")]*"$/', $str) || preg_match("/^'[^(\')]*'$/", $str)) {
            return self::create($str, PPP_STRING);
        }

        // Punctuation
        if (in_array($str, $punctuation)) {
            return self::create($str, PPP_PUNCTUATION);
        }
        return self::create($str, PPP_UNKNOWN);

    }
}

class Token_Blockquotes extends Token {
    protected function defaultType() {
        return PPP_BLOCKQUOTES;
    }
}

class Token_Bareword extends Token {

    protected function defaultType() {
        return PPP_BAREWORD;
    }

    public function parse($parser) {
        $parser->token_list[] = $this->value;
        $this->functionCall($parser);
    }

}

class Token_Whitespace extends Token {
    protected function defaultType() {
        return PPP_WHITESPACE;
    }

    public function parse($parser) {
        $last_word = $parser->token_list[count($parser->token_list)-1];
        if (substr($last_word, -1) == '(') {
            throw new ParserException_TokenContinue();
        }
        $parser->token_list[] = $this->value;
    }
}

class Token_Variable extends Token {
    protected function defaultType() {
        return PPP_VARIABLE;
    }
}

class Token_Punctuation extends Token {
    protected function defaultType() {
        return PPP_PUNCTUATION;
    }

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

        case '.':
            if ($parser->next_token->is(PPP_BAREWORD)) {
                $parser->token_list[] = '->';
            } else {
                $parser->token_list[] = $this->value;
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

class Token_Self extends Token {
    protected function defaultType() {
        return PPP_SELF;
    }

    public function parse($parser) {
        $parser->token_list[] = '$this';
    }
}

class Token_Indentation extends Token {
    protected function defaultType() {
        return PPP_INDENTATION;
    }
}

class Token_Property extends Token {
    protected function defaultType() {
        return PPP_PROPERTY;
    }

    public function parse($parser) {
        $parser->token_list[] = '$this->'.preg_replace('/^@/', '', $this->value);
        $this->functionCall($parser);
    }
}

class Token_Unknown extends Token {
    protected function defaultType() {
        return PPP_UNKNOWN;
    }
}

class Token_Reserved extends Token {
    protected function defaultType() {
        return PPP_RESERVED;
    }

    public function parse($parser) {
        global $synonym_list, $synonyms;
        if (in_array($this->value, $synonym_list)) {
            $parser->token_list[] = $synonyms[$this->value];
        } else {
            switch ($this->value) {
            case '(':
                $parser->open_stack[] = ')';
                $parser->token_list[] = '(';
                break;
            case ')':
                array_pop($parser->open_stack);
                $parser->token_list[] = ')';
                break;

            case 'if':
                $parser->token_list[] = 'if (';
                $parser->open_stack[] = ')';
                $parser->ends_curly = true;
                break;
            case 'def':
                $parser->token_list[] = 'function';
                $parser->ends_curly = true;
                break;

            case 'else':
                $parser->token_list[] = 'else';
                $parser->ends_curly = true;
                break;

            case 'try':
                $parser->token_list[] = 'try';
                $parser->ends_curly = true;
                break;

            case 'catch':
                $parser->token_list[] = 'catch (';
                $parser->open_stack[] = ')';
                $parser->is_in_catch = true;
                $parser->ends_curly = true;
                break;

            case 'elif':
                $parser->token_list[] = 'else if (';
                $parser->open_stack[] = ')';
                $parser->ends_curly = true;
                break;

            case 'class':
                $parser->ends_curly = true;
            default:
                parent::parse($parser);
            }
        }
    }
}

class Token_Boolean extends Token {
    protected function defaultType() {
        return PPP_BOOLEAN;
    }

    public function parse($parser) {
        global $synonym_list, $synonyms;
        if (in_array($this->value, $synonym_list)) {
            $parser->token_list[] = $synonyms[$this->value];
        } else {
            $parser->token_list[] = $this->value;
        }
    }
}

class Token_StaticSelf extends Token {
    protected function defaultType() {
        return PPP_STATIC_SELF;
    }

    public function parse($parser) {
        $parser->token_list[] = str_replace('@@', 'self::', $this->value);
    }
}

class Token_String extends Token {
    protected function defaultType() {
        return PPP_STRING;
    }
}

class Token_Number extends Token {
    protected function defaultType() {
        return PPP_NUMBER;
    }
}
