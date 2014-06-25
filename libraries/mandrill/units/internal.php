<?php

namespace Library\Mandrill;

class Mandrill_Internal {
    public function __construct(Mandrill $master) {
        $this->master = $master;
    }

}


