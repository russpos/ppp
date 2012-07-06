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

class ParserException_TokenBreak extends Exception { }
class ParserException_TokenContinue extends Exception { }

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
            $this->output[] = $this->tabs(0, false)."}";
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

    protected function tabs($offset, $write = true) {
        $number = $this->id['count']+$this->array_stack+$offset;
        if ($number == 0) {
            return '';
        }
        $space = implode('', array_fill(0, $number*4, ' '));
        if ($write) {
            $this->rewrites[] = $space;
        }
        return $space;
    }

    protected function reset_state() {
        $this->array_stack = 0;
        $this->rewrites = array();
        $this->open_stack = array();
        $this->bracket_stack = array();
        $this->ends_curly = false;
        $this->is_in_catch = false;
    }

    protected function PPP_BLOCKQUOTES() {
        if ($this->token->is(PPP_BLOCKQUOTES)) {
            $this->in_block_quotes = !$this->in_block_quotes;
            $this->rewrites[] = ($this->in_block_quotes) ? '/*' : '*/';
            throw new ParserException_TokenContinue();
        }
        if ($this->in_block_quotes) {
            $this->rewrites[] = $this->token->value;
            throw new ParserException_TokenContinue();
        }
    }

    protected function reset_indentation() {
        $this->id = array(
            'stack' => array(),
            'level' => array(),
            'count' => 0,
            'cur_spaces' => 0,
        );
    }

    protected function next_token() {
        // Find the next NON-whitespace token. If you reach end of the line, then keep next_token as null
        $next_token_num = $this->cur_token;
        while ((++$next_token_num < $this->token_count) && empty($next_token)) {
            $possible_next_token = $this->tokens[$next_token_num];
            if ($possible_next_token->type !== PPP_WHITESPACE) {
                $next_token = $possible_next_token;
                break;
            }
        }
        return (empty($next_token)) ? null : $next_token;
    }

    public function parse($content) {
        global $synonyms;

        $lines = explode("\n", $content);

        $this->output = array("<?php");

        $this->in_block_quotes = false;

        $this->reset = true;
        $synonym_list = array_keys($synonyms);

        $this->cur_token = 0;
        $this->token_count = 0;

        $this->reset_state();
        $this->reset_indentation();

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $this->output[] = '';
                continue;
            }
            if (empty($this->array_stack)&& !$this->in_block_quotes) {
                // Capture indentation
                preg_match('/^(\s+?)[^\s]/', $line, $leading_whitespace);
                if (!empty($leading_whitespace) && !empty($leading_whitespace[1])) {
                    $this->match_indentation(strlen($leading_whitespace[1]));
                } else {
                    $this->match_indentation(0);
                }
            }


            $this->tokens = tokenizer($line);
            if ($this->reset) {
                $this->reset_state();
            }

            $this->token_count = count($this->tokens);
            $this->cur_token = 0;

            $this->reset = true;

            while ($this->cur_token < $this->token_count) {
                try {
                $prev_token = null;
                $next_token = null;
                $end = false;
                $this->token = $token = $this->tokens[$this->cur_token];

                if ($this->cur_token == 0) {
                    if ($token->value == ']' || $token->value == '}') {
                        $tab_variation = -1;
                    } else {
                        $tab_variation = 0;
                    }
                    $this->tabs($tab_variation);
                }

                if ($this->cur_token-1 >= 0) {
                    $prev_token = $this->tokens[$this->cur_token-1];
                }

                $next_token = $this->next_token();

                $this->cur_token++;

                //echo "Type: ({$token->value}): ".get_class($token)."\n";
                $this->PPP_BLOCKQUOTES();

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
                    if (!$this->is_in_catch && $next_token && ($next_token->type === PPP_STRING || $next_token->type === PPP_NUMBER || $next_token->type === PPP_STATIC_SELF || $next_token->type === PPP_BOOLEAN || $next_token->type === PPP_BAREWORD || $next_token->type === PPP_VARIABLE || $next_token->type === PPP_SELF || $next_token->type === PPP_PROPERTY)) {
                        $this->rewrites[] = '(';
                        $this->open_stack[] = ')';
                    }
                    break;

                case PPP_RESERVED:
                    if (in_array($token->value, $synonym_list)) {
                        $this->rewrites[] = $synonyms[$token->value];
                    } else {
                        switch ($token->value) {
                        case '(':
                            $this->open_stack[] = ')';
                            $this->rewrites[] = '(';
                            break;
                        case ')':
                            array_pop($this->open_stack);
                            $this->rewrites[] = ')';
                            break;

                        case 'if':
                            $this->rewrites[] = 'if (';
                            $this->open_stack[] = ')';
                            $this->ends_curly = true;
                            break;
                        case 'def':
                            $this->rewrites[] = 'function';
                            $this->ends_curly = true;
                            break;

                        case 'else':
                            $this->rewrites[] = 'else';
                            $this->ends_curly = true;
                            break;

                        case 'try':
                            $this->rewrites[] = 'try';
                            $this->ends_curly = true;
                            break;

                        case 'catch':
                            $this->rewrites[] = 'catch (';
                            $this->open_stack[] = ')';
                            $this->is_in_catch = true;
                            $this->ends_curly = true;
                            break;

                        case 'elif':
                            $this->rewrites[] = 'else if (';
                            $this->open_stack[] = ')';
                            $this->ends_curly = true;
                            break;

                        case 'class':
                            $this->ends_curly = true;
                        default:
                            $this->rewrites[] = $token->value;
                        }
                    }
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
                            $this->bracket_stack[] = PPP_BRACKET_PROP;
                            $this->rewrites[] = '[';
                        } else {
                            $this->bracket_stack[] = PPP_BRACKET_ARRAY;
                            $this->rewrites[] = 'array(';
                            $this->array_stack++;
                        }
                        break;
                    case '}':
                        $this->rewrites[] = ')';
                        $this->array_stack--;
                        break;
                    case ']':
                        $popped = array_pop($this->bracket_stack);
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
                        $token->parse($this);
                        
                    }

                    if (is_null($next_token)) {
                        if ($token->value == ',' || !empty($this->array_stack)) {
                            $this->reset = false;
                            continue;
                        }
                        while (!empty($this->open_stack)) {
                            $this->rewrites[] = array_pop($this->open_stack);
                        }

                        if ($this->ends_curly) {
                            $this->rewrites[] = ' {';
                        } else if (!$end) {
                            $this->rewrites[] = ';';
                        }
                    }
                } catch (ParserException_TokenBreak $e) {
                    break;
                } catch (ParserException_TokenContinue $e) {
                    continue;
                }
            }
            if ($this->reset) {
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
