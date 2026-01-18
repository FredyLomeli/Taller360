<?php

test('la pagina de login carga correctamente', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});