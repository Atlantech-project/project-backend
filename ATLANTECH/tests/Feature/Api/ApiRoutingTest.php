<?php

it('returns a NOT_FOUND problem for an unknown path under /api/v1', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('keeps the health route at /up outside the API prefix', function () {
    $this->get('/up')->assertOk();
    $this->get('/api/v1/up')->assertNotFound();
});
