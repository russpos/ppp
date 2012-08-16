# ppp
### Pretty PHP Parser

PHP. Everyone knows it, it runs everywhere, and it's request life-cycle model makes it super easy
to build scalable applications.  There are alot of obvious reasons to write an app in PHP.  Except
that I don't like writing PHP.

Call me petty, but I just don't like the way it looks, and I don't have fun writing it.

Welcome, `ppp`!

`ppp` is a language that compiles down to PHP, either via a commandline utility, or 
since its written in PHP, at run time.

#### Sample PPP

```
PPP::import "foo/bar"

class Foo

  ###
  Block quotes
  ###
  def __construct($foo, $bar)
    @foo = $foo
    @say $foo, true

  private def say($msg, $silly)
    if $silly
      echo $msg
    else 
      echo ">>>>$msg<<<<"

$c = new Foo "hello", 123
```

PPP really is not designed for pages which mix PHP / HTML or for scripts, but rather for
a class based styled of OO PHP programming. TRY IT
