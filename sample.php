<?php
class Bar {}

require("source/ppp.php");
function wrapper() {
    PPP::import("samples/2");
}

wrapper();
if (class_exists("Foo")) {
    echo "It worked!";
}
