<?php declare(strict_types=1);
// Original implementations from https://gist.github.com/mathiasverraes/9046427
function it($m,$p){echo"\e[3",(is_callable($p)?$p():$p)?'2m✅':'1m❌'.register_shutdown_function(fn()=>die(1))," It $m\e[0m\n";}
function all(array$l){return array_reduce($l,fn($a,$p)=>$a&&(is_callable($p)?$p():$p),1);}
function throws($x,callable$c){try{return$c()&&0;}catch(Throwable$e){return$e instanceof$x;}}
function xit($m){echo"\e[90m⏩ It $m\e[0m\n";}
