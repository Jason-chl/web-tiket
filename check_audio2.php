<?php
function c($url) {
    echo $url . ": " . @get_headers($url)[0] . "\n";
}
c('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');
c('https://actions.google.com/sounds/v1/cartoon/cartoon_blinking_sound.ogg');
c('https://ext.sfx.best/success.mp3');
?>
