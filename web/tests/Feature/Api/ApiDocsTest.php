<?php

it('la documentación OpenAPI solo la ve el administrador fuera de local', function () {
    $this->get('/docs/api')->assertForbidden();
    $this->actingAs(cook())->get('/docs/api')->assertForbidden();

    $this->actingAs(admin())->get('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'API Antojería Pingüin')
        ->assertJsonStructure(['paths' => ['/orders', '/tables/{table}/payments', '/kitchen/orders']]);
});
