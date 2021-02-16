<?php

close_db();

if(isset($Cache) && is_object($Cache))
    $Cache->close();

?>