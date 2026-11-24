<?php

it('menjawab ping di /api/v1/ping', function () {
    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.version', 'v1')
        ->assertJsonStructure(['success', 'message', 'data' => ['version', 'server_time']]);
});

it('memberi nama route dengan prefix api.v1.', function () {
    expect(route('api.v1.ping', absolute: false))->toBe('/api/v1/ping');
});

it('mengembalikan JSON 404 untuk endpoint yang tidak ada', function () {
    $this->getJson('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertExactJson([
            'success' => false,
            'message' => 'Endpoint not found.',
            'code' => 'NOT_FOUND',
            'errors' => null,
        ]);
});

it('tetap menjawab JSON walau klien tidak mengirim header Accept', function () {
    // get() biasa (bukan getJson) = tanpa "Accept: application/json"
    $this->get('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('tidak memakai session/cookie di route API', function () {
    $this->getJson('/api/v1/ping')
        ->assertCookieMissing(config('session.cookie'));
});
