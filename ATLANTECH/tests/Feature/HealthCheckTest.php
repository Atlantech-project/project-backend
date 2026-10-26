<?php

test('the health route used by the container and NGINX checks returns 200', function () {
    $this->get('/up')->assertOk();
});
