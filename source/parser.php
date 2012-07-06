<?php

require_once 'globals.php';
require_once 'tokens.php';

// States
define('PPP_BRACKET_ARRAY', 301);
define('PPP_BRACKET_PROP',  302);

function interlock($parts, $with) {
    $locks = array();
    foreach($parts as $part) {
        $locks[] = $part;
        $locks[] = $with;
    }
    return $locks;
}

function tokenizer($string) {
    $tokens = array();
    $string = trim($string);



    $parts = preg_split('#(\"[^\"]*\")|(\'[^\']*\')|(\#\#\#)|(\<\-)|(\-\>)|(\s+|\.|:|\]|\[|,|\(|\))#', $string, null, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $sub) {
        $token = Token::generate($sub);
        if (!empty($token)) {
            $tokens[] = $token;
        }
    }
    return $tokens;
}

function tabs($number=0) {
    if ($number == 0) {
        return '';
    }
    return implode('', array_fill(0, $number*4, ' '));
}

class PPP_Parser {

    protected function indent($count) {
        $this->id['count']++;
        $this->id['stack'][] = $count;
        $this->id['cur_spaces'] = $count;
    }

    protected function dedent($print = false) {
        $this->id['count']--;
        array_pop($this->id['stack']);
        if (empty($this->id['stack'])) {
            $this->id['cur_spaces'] = 0;
        }
        if ($print) {
            $this->output[] = tabs($this->id['count'])."}";
        }

        if (!empty($this->id['cur_spaces'])) {
            $this->id['cur_spaces'] = $this->id['stack'][count($this->id['stack'])-1];
        }
    }

    protected function match_indentation($count) {
        if ($count == $this->id['cur_spaces']) {
            return;
        } else if ($count > $this->id['cur_spaces']) {
            $this->indent($count);
        } else if ($count < $this->id['cur_spaces']) {
            $this->dedent(true);
            $this->match_indentation($count);
        }
    }

    public function parse($content) {
        global $synonyms;

// Fix over zealous new-line removal

$lines = explode("\n", $content);

$this->output = array("<?php");

$indents = 0;
$in_block_quotes = false;

$bracket_stack = array();
$reset = true;
$synonym_list = array_keys($synonyms);

$this->array_stack = 0;
$this->rewrites = array();
$cur_token = 0;
$token_count;
$open_stack = array();

$ends_curly = false;
$in_catch = false;

$this->id = array(
    'stack' => array(),
    'level' => array(),
    'count' => 0,
    'cur_spaces' => 0,
);

foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed)) {
        $this->output[] = '';
        continue;
    }
    if (empty($this->array_stack)&& !$in_block_quotes) {
        // Capture indentation
        preg_match('/^(\s+?)[^\s]/', $line, $leading_whitespace);
        if (!empty($leading_whitespace) && !empty($leading_whitespace[1])) {
            $this->match_indentation(strlen($leading_whitespace[1]));
        } else {
            $this->match_indentation(0);
        }
    }


    $tokens = tokenizer($line);
    if ($reset) {
        $this->array_stack = 0;
        $this->rewrites = array();
        $open_stack = array();
        $ends_curly = false;
        $in_catch = false;
    }

    $token_count = count($tokens);
    $cur_token = 0;

    $reset = true;

    while ($cur_token < $token_count) {
        $prev_token = null;
        $next_token = null;
        $end = false;
        $token = $tokens[$cur_token];

        if ($cur_token == 0) {
            if ($token->value == ']' || $token->value == '}') {
                $tab_variation = -1;
            } else {
                $tab_variation = 0;
            }
            $this->rewrites[] = tabs($this->id['count']+$this->array_stack+$tab_variation);
        }

        if ($cur_token-1 >= 0) {
            $prev_token = $tokens[$cur_token-1];
        }

        // Find the next NON-whitespace token. If you reach end of the line, then keep next_token as null
        $next_token_num = $cur_token;
        while ((++$next_token_num < $token_count) && empty($next_token)) {
            $possible_next_token = $tokens[$next_token_num];
            if ($possible_next_token->type !== PPP_WHITESPACE) {
                $next_token = $possible_next_token;
                break;
            }
        }
        $cur_token++;

        //echo "Type: ({$token->value}): ".get_class($token)."\n";

        if ($token->type === PPP_BLOCKQUOTES) {
            $in_block_quotes = !$in_block_quotes;
            $this->rewrites[] = ($in_block_quotes) ? '/*' : '*/';
            continue;
        }

        if ($in_block_quotes) {
            $this->rewrites[] = $token->value;
            continue;
        }


        switch ($token->type) {
        case PPP_SELF:
            $this->rewrites[] = '$this';
            break;
        case PPP_PROPERTY:
        case PPP_BAREWORD:

            if ($token->type === PPP_PROPERTY) {
                $this->rewrites[] = '$this->'.preg_replace('/^@/', '', $token->value);
            } else {
                $this->rewrites[] = $token->value;
            }
            if (!$in_catch && $next_token && ($next_token->type === PPP_STRING || $next_token->type === PPP_NUMBER || $next_token->type === PPP_STATIC_SELF || $next_token->type === PPP_BOOLEAN || $next_token->type === PPP_BAREWORD || $next_token->type === PPP_VARIABLE || $next_token->type === PPP_SELF || $next_token->type === PPP_PROPERTY)) {
                $this->rewrites[] = '(';
                $open_stack[] = ')';
            }
            break;

        case PPP_WHITESPACE:
            $last_word = $this->rewrites[count($this->rewrites)-1];
            if (substr($last_word, -1) == '(') {
                continue;
            }
            $this->rewrites[] = $token->value;
            break;

        case PPP_BOOLEAN:
            if (in_array($token->value, $synonym_list)) {
                $this->rewrites[] = $synonyms[$token->value];
            } else {
                $this->rewrites[] = $token->value;
            }
            break;

        case PPP_RESERVED:
            if (in_array($token->value, $synonym_list)) {
                $this->rewrites[] = $synonyms[$token->value];
            } else {
                switch ($token->value) {
                case '(':
                    $open_stack[] = ')';
                    $this->rewrites[] = '(';
                    break;
                case ')':
                    array_pop($open_stack);
                    $this->rewrites[] = ')';
                    break;

                case 'if':
                    $this->rewrites[] = 'if (';
                    $open_stack[] = ')';
                    $ends_curly = true;
                    break;
                case 'def':
                    $this->rewrites[] = 'function';
                    $ends_curly = true;
                    break;

                case 'else':
                    $this->rewrites[] = 'else';
                    $ends_curly = true;
                    break;

                case 'try':
                    $this->rewrites[] = 'try';
                    $ends_curly = true;
                    break;

                case 'catch':
                    $this->rewrites[] = 'catch (';
                    $open_stack[] = ')';
                    $in_catch = true;
                    $ends_curly = true;
                    break;

                case 'elif':
                    $this->rewrites[] = 'else if (';
                    $open_stack[] = ')';
                    $ends_curly = true;
                    break;

                case 'class':
                    $ends_curly = true;
                default:
                    $this->rewrites[] = $token->value;
                }
            }
            break;

        case PPP_STATIC_SELF:
            $this->rewrites[] = str_replace('@@', 'self::', $token->value);
            break;

        case PPP_PUNCTUATION:
            switch ($token->value) {
            case ':':
                if ($this->array_stack) {
                    $this->rewrites[] = '=>';
                } else {
                    $this->rewrites[] = ':';
                }
                break;
            case '{':
                $this->rewrites[] = 'array(';
                $this->array_stack++;
                break;
            case '[':
                if ($prev_token->is(PPP_VARIABLE)) {
                    $bracket_stack[] = PPP_BRACKET_PROP;
                    $this->rewrites[] = '[';
                } else {
                    $bracket_stack[] = PPP_BRACKET_ARRAY;
                    $this->rewrites[] = 'array(';
                    $this->array_stack++;
                }
                break;
            case '}':
                $this->rewrites[] = ')';
                $this->array_stack--;
                break;
            case ']':
                $popped = array_pop($bracket_stack);
                if ($popped == PPP_BRACKET_ARRAY) {
                    $this->rewrites[] = ')';
                    $this->array_stack--;
                } else {
                    $this->rewrites[] = ']';
                }
                break;

            case '.':
                if ($next_token->is(PPP_BAREWORD)) {
                    $this->rewrites[] = '->';
                } else {
                    $this->rewrites[] = $token->value;
                }
                break;
            case '<-':
                $this->rewrites[] = 'return';
                break;
            default:
                $this->rewrites[] = $token->value;
            }
            break;
        default:
            $this->rewrites[] = $token->value;
        }

        if (is_null($next_token)) {
            if ($token->value == ',' || !empty($this->array_stack)) {
                $reset = false;
                continue;
            }
            while (!empty($open_stack)) {
                $this->rewrites[] = array_pop($open_stack);
            }

            if ($ends_curly) {
                $this->rewrites[] = ' {';
                $indents++;
            } else if (!$end) {
                $this->rewrites[] = ';';
            }
        }
    }
    if ($reset) {
        $processed = implode('', $this->rewrites);
        $this->output[] = $processed;
    } else {
        $this->rewrites[] = "\n";
    }

}
while ($this->id['count']) {
    $this->dedent(true);
}
$printer =  implode("\n", $this->output);
return $printer;
}
}
