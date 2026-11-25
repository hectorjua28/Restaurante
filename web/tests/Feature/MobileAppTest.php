<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    if (isset($this->createdApp)) {
        File::deleteDirectory(public_path('app'));
    }
});

it('sirve index.html de la PWA para las rutas internas de /app', function () {
    if (! is_file(public_path('app/index.html'))) {
        File::ensureDirectoryExists(public_path('app'));
        File::put(public_path('app/index.html'), '<!doctype html><title>Pingüin Meseros</title>');
        $this->createdApp = true;
    }

    $this->get('/app')->assertOk();
    $response = $this->get('/app/tabs/mesas')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-cache')->toContain('no-store');
});

it('explica cómo publicarla si todavía no se compiló', function () {
    if (is_file(public_path('app/index.html'))) {
        $this->markTestSkipped('La PWA ya está compilada en public/app.');
    }

    $this->get('/app/tabs/mesas')->assertNotFound();
});
