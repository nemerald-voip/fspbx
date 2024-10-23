<?php

    $tag  = exec('git describe --tags --abbrev=0');
    
    if(empty($tag)) {
        $tag = '-.-.-';
    }

    $hash = trim(exec('git log --pretty="%h" -n1 HEAD'));
    $date = Carbon\Carbon::parse(trim(exec('git log -n1 --pretty=%ci HEAD')));

return [

    'tag' => $tag,
    'date' => $date,
    'hash' => $hash,
    'full' => sprintf('%s-%s',$tag, $hash),
    'short' => ltrim($tag, "v"),
    'string' => sprintf('%s-%s (%s)',$tag, $hash, $date->format('d/m/y H:i')),
    
];